import { prepareHuman } from './human-client';

export type FaceAnalysisInput =
    | HTMLImageElement
    | HTMLCanvasElement
    | HTMLVideoElement;

export interface FaceAnalysisMetadata {
    overall_score: number;
    face_score: number;
    box_score: number;
    face_coverage: number;
    live_score: number | null;
    real_score: number | null;
    detected_faces: number;
}

export interface FaceBiometricAnalysis {
    embedding: number[];
    embeddingDimension: number;
    modelName: string;
    modelVersion: string;
    qualityScore: number;
    livenessPassed: boolean;
    liveScore: number | null;
    realScore: number | null;
    metadata: FaceAnalysisMetadata;
}

export class FaceAnalysisError extends Error {
    public readonly code: string;

    public constructor(
        code: string,
        message: string,
    ) {
        super(message);

        this.name = 'FaceAnalysisError';
        this.code = code;
    }
}

const MIN_LIVE_SCORE = 0.8;
const MIN_REAL_SCORE = 0.8;

function clamp(
    value: number,
    minimum = 0,
    maximum = 1,
): number {
    return Math.min(
        maximum,
        Math.max(minimum, value),
    );
}

function finiteScore(
    value: number | undefined,
): number | null {
    if (
        typeof value !== 'number'
        || !Number.isFinite(value)
    ) {
        return null;
    }

    return clamp(value);
}

function inputDimensions(
    input: FaceAnalysisInput,
): {
    width: number;
    height: number;
} {
    if (input instanceof HTMLVideoElement) {
        return {
            width:
                input.videoWidth
                || input.clientWidth
                || 1,

            height:
                input.videoHeight
                || input.clientHeight
                || 1,
        };
    }

    if (input instanceof HTMLImageElement) {
        return {
            width:
                input.naturalWidth
                || input.width
                || 1,

            height:
                input.naturalHeight
                || input.height
                || 1,
        };
    }

    return {
        width: input.width || 1,
        height: input.height || 1,
    };
}

function calculateFaceCoverage(
    faceWidth: number,
    faceHeight: number,
    input: FaceAnalysisInput,
): number {
    const dimensions = inputDimensions(input);

    const inputMinimumSide = Math.max(
        1,
        Math.min(
            dimensions.width,
            dimensions.height,
        ),
    );

    const faceMinimumSide = Math.max(
        0,
        Math.min(
            faceWidth,
            faceHeight,
        ),
    );

    return clamp(
        faceMinimumSide / inputMinimumSide,
    );
}

/**
 * Quality score ini merupakan heuristic aplikasi,
 * bukan skor kualitas biometrik terstandar.
 */
function calculateQualityScore(
    overallScore: number,
    faceScore: number,
    boxScore: number,
    faceCoverage: number,
): number {
    /*
     * Wajah ideal memenuhi sekitar 35–70% sisi pendek
     * gambar. Coverage kecil akan menurunkan kualitas.
     */
    const coverageScore = clamp(
        (faceCoverage - 0.18) / 0.32,
    );

    return Number(
        clamp(
            overallScore * 0.3
                + faceScore * 0.25
                + boxScore * 0.25
                + coverageScore * 0.2,
        ).toFixed(4),
    );
}

function validateEmbedding(
    embedding: number[] | undefined,
): number[] {
    if (
        !Array.isArray(embedding)
        || embedding.length < 64
    ) {
        throw new FaceAnalysisError(
            'EMBEDDING_UNAVAILABLE',
            'Descriptor wajah gagal dibuat. Ambil ulang foto dengan wajah yang lebih jelas.',
        );
    }

    const normalizedEmbedding = embedding.map(
        (value): number => Number(value),
    );

    const hasInvalidValue =
        normalizedEmbedding.some(
            (value) => !Number.isFinite(value),
        );

    if (hasInvalidValue) {
        throw new FaceAnalysisError(
            'EMBEDDING_INVALID',
            'Descriptor wajah mengandung nilai yang tidak valid.',
        );
    }

    return normalizedEmbedding;
}

/**
 * Menganalisis satu wajah dan menghasilkan descriptor.
 */
export async function analyzeFace(
    input: FaceAnalysisInput,
): Promise<FaceBiometricAnalysis> {
    const human = await prepareHuman();

    const result = await human.detect(input);

    if (result.face.length === 0) {
        throw new FaceAnalysisError(
            'FACE_NOT_FOUND',
            'Wajah tidak ditemukan. Pastikan wajah menghadap kamera dan pencahayaan cukup.',
        );
    }

    if (result.face.length > 1) {
        throw new FaceAnalysisError(
            'MULTIPLE_FACES',
            'Terdeteksi lebih dari satu wajah. Pastikan hanya penjemput yang berada di depan kamera.',
        );
    }

    const face = result.face[0];

    const embedding = validateEmbedding(
        face.embedding,
    );

    const overallScore =
        finiteScore(face.score) ?? 0;

    const faceScore =
        finiteScore(face.faceScore) ?? 0;

    const boxScore =
        finiteScore(face.boxScore) ?? 0;

    const faceCoverage =
        calculateFaceCoverage(
            face.size[0],
            face.size[1],
            input,
        );

    const qualityScore =
        calculateQualityScore(
            overallScore,
            faceScore,
            boxScore,
            faceCoverage,
        );

    const liveScore =
        finiteScore(face.live);

    const realScore =
        finiteScore(face.real);

    const livenessPassed =
        liveScore !== null
        && realScore !== null
        && liveScore >= MIN_LIVE_SCORE
        && realScore >= MIN_REAL_SCORE;

    return {
        embedding,

        embeddingDimension:
            embedding.length,

        modelName:
            'human-hse-faceres',

        modelVersion:
            human.version,

        qualityScore,

        livenessPassed,

        liveScore,

        realScore,

        metadata: {
            overall_score:
                overallScore,

            face_score:
                faceScore,

            box_score:
                boxScore,

            face_coverage:
                Number(
                    faceCoverage.toFixed(4),
                ),

            live_score:
                liveScore,

            real_score:
                realScore,

            detected_faces:
                result.face.length,
        },
    };
}