import {
    analyzeFace,
    FaceAnalysisError,
    type FaceBiometricAnalysis,
    type FaceAnalysisMetadata,
} from './analyze-face';

export interface FaceProbeMetadata
    extends FaceAnalysisMetadata {
    sample_count: number;
    required_liveness_samples: number;
    passed_liveness_samples: number;
    minimum_quality_score: number;
    frame_quality_scores: number[];
    frame_live_scores: Array<number | null>;
    frame_real_scores: Array<number | null>;
}

export interface FaceProbeAnalysis
    extends Omit<
        FaceBiometricAnalysis,
        'metadata'
    > {
    metadata: FaceProbeMetadata;
    sampleCount: number;
    passedLivenessSamples: number;
    requiredLivenessSamples: number;
    minimumFrameQuality: number;
}

export interface FaceProbeProgress {
    acceptedSamples: number;
    requiredSamples: number;
    attempt: number;
    maximumAttempts: number;
}

export interface AnalyzeFaceProbeOptions {
    sampleCount?: number;
    delayMilliseconds?: number;
    minimumQualityScore?: number;
    signal?: AbortSignal;
    onProgress?: (
        progress: FaceProbeProgress,
    ) => void;
}

export class FaceProbeAnalysisError extends Error {
    public readonly code: string;

    public constructor(
        code: string,
        message: string,
    ) {
        super(message);

        this.name = 'FaceProbeAnalysisError';
        this.code = code;
    }
}

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

function rounded(
    value: number,
    precision = 4,
): number {
    return Number(
        value.toFixed(precision),
    );
}

function average(
    values: number[],
): number {
    if (values.length === 0) {
        return 0;
    }

    return values.reduce(
        (
            total,
            value,
        ) => total + value,
        0,
    ) / values.length;
}

function averageNullable(
    values: Array<number | null>,
): number | null {
    const validValues = values.filter(
        (value): value is number =>
            typeof value === 'number'
            && Number.isFinite(value),
    );

    if (validValues.length === 0) {
        return null;
    }

    return rounded(
        average(validValues),
    );
}

function minimum(
    values: number[],
): number {
    if (values.length === 0) {
        return 0;
    }

    return Math.min(...values);
}

function assertNotAborted(
    signal?: AbortSignal,
): void {
    if (signal?.aborted) {
        throw new DOMException(
            'Analisis dibatalkan.',
            'AbortError',
        );
    }
}

function sleep(
    milliseconds: number,
    signal?: AbortSignal,
): Promise<void> {
    return new Promise(
        (
            resolve,
            reject,
        ) => {
            assertNotAborted(signal);

            const timeoutId = window.setTimeout(
                () => {
                    signal?.removeEventListener(
                        'abort',
                        abortHandler,
                    );

                    resolve();
                },
                milliseconds,
            );

            function abortHandler(): void {
                window.clearTimeout(timeoutId);

                reject(
                    new DOMException(
                        'Analisis dibatalkan.',
                        'AbortError',
                    ),
                );
            }

            signal?.addEventListener(
                'abort',
                abortHandler,
                {
                    once: true,
                },
            );
        },
    );
}

function ensureVideoReady(
    video: HTMLVideoElement,
): void {
    if (
        video.readyState
            < HTMLMediaElement.HAVE_CURRENT_DATA
        || video.videoWidth <= 0
        || video.videoHeight <= 0
    ) {
        throw new FaceProbeAnalysisError(
            'VIDEO_NOT_READY',
            'Kamera belum siap. Tunggu sampai video tampil lalu coba kembali.',
        );
    }
}

function normalizeEmbedding(
    embedding: number[],
): number[] {
    const norm = Math.sqrt(
        embedding.reduce(
            (
                total,
                value,
            ) => total + value * value,
            0,
        ),
    );

    if (
        !Number.isFinite(norm)
        || norm <= 0
    ) {
        throw new FaceProbeAnalysisError(
            'INVALID_EMBEDDING_NORM',
            'Descriptor wajah tidak dapat dinormalisasi.',
        );
    }

    return embedding.map(
        (value) => value / norm,
    );
}

function averageEmbeddings(
    analyses: FaceBiometricAnalysis[],
): number[] {
    const firstAnalysis = analyses[0];

    if (!firstAnalysis) {
        throw new FaceProbeAnalysisError(
            'NO_ANALYSIS',
            'Belum ada sampel wajah yang dapat diproses.',
        );
    }

    const dimension =
        firstAnalysis.embedding.length;

    const accumulated = new Array<number>(
        dimension,
    ).fill(0);

    analyses.forEach((analysis) => {
        if (
            analysis.embedding.length
            !== dimension
        ) {
            throw new FaceProbeAnalysisError(
                'EMBEDDING_DIMENSION_MISMATCH',
                'Dimensi descriptor berubah antarframe. Jalankan ulang verifikasi.',
            );
        }

        analysis.embedding.forEach(
            (
                value,
                index,
            ) => {
                if (!Number.isFinite(value)) {
                    throw new FaceProbeAnalysisError(
                        'INVALID_EMBEDDING',
                        'Descriptor wajah mengandung nilai yang tidak valid.',
                    );
                }

                accumulated[index] += value;
            },
        );
    });

    const averagedEmbedding =
        accumulated.map(
            (value) =>
                value / analyses.length,
        );

    /*
     * Backend menggunakan cosine similarity.
     * Normalisasi tidak mengubah arah vektor,
     * tetapi menjaga descriptor hasil rata-rata
     * tetap stabil.
     */
    return normalizeEmbedding(
        averagedEmbedding,
    );
}

/**
 * Mengambil beberapa sampel wajah dari video kamera.
 *
 * Sampel yang berhasil dianalisis akan digabungkan
 * menjadi satu descriptor rata-rata.
 */
export async function analyzeFaceProbe(
    video: HTMLVideoElement,
    options: AnalyzeFaceProbeOptions = {},
): Promise<FaceProbeAnalysis> {
    ensureVideoReady(video);

    const sampleCount = Math.max(
        1,
        Math.min(
            10,
            Math.trunc(
                options.sampleCount ?? 3,
            ),
        ),
    );

    const delayMilliseconds = Math.max(
        250,
        Math.min(
            2000,
            Math.trunc(
                options.delayMilliseconds
                    ?? 550,
            ),
        ),
    );

    const minimumQualityScore = clamp(
        options.minimumQualityScore
            ?? 0.75,
    );

    /*
     * Memberikan dua percobaan tambahan apabila
     * satu frame gagal karena wajah bergerak atau
     * kamera belum fokus.
     */
    const maximumAttempts =
        sampleCount + 2;

    const analyses: FaceBiometricAnalysis[] =
        [];

    let attempt = 0;

    let latestAnalysisError:
        | FaceAnalysisError
        | FaceProbeAnalysisError
        | null = null;

    while (
        analyses.length < sampleCount
        && attempt < maximumAttempts
    ) {
        assertNotAborted(options.signal);

        attempt += 1;

        try {
            const analysis =
                await analyzeFace(video);

            const firstAnalysis =
                analyses[0];

            if (
                firstAnalysis
                && firstAnalysis.modelName
                    !== analysis.modelName
            ) {
                throw new FaceProbeAnalysisError(
                    'MODEL_CHANGED',
                    'Model descriptor berubah saat analisis berlangsung.',
                );
            }

            if (
                firstAnalysis
                && firstAnalysis.embeddingDimension
                    !== analysis.embeddingDimension
            ) {
                throw new FaceProbeAnalysisError(
                    'DIMENSION_CHANGED',
                    'Dimensi descriptor berubah saat analisis berlangsung.',
                );
            }

            analyses.push(analysis);

            latestAnalysisError = null;
        } catch (error) {
            if (
                error instanceof FaceAnalysisError
                || error
                    instanceof FaceProbeAnalysisError
            ) {
                latestAnalysisError = error;
            } else {
                throw error;
            }
        }

        options.onProgress?.({
            acceptedSamples:
                analyses.length,

            requiredSamples:
                sampleCount,

            attempt,

            maximumAttempts,
        });

        if (
            analyses.length < sampleCount
            && attempt < maximumAttempts
        ) {
            await sleep(
                delayMilliseconds,
                options.signal,
            );
        }
    }

    if (analyses.length < sampleCount) {
        throw new FaceProbeAnalysisError(
            'INSUFFICIENT_SAMPLES',
            latestAnalysisError?.message
                ?? `Hanya ${analyses.length} dari ${sampleCount} sampel yang berhasil dianalisis.`,
        );
    }

    const embedding =
        averageEmbeddings(analyses);

    const qualityScores = analyses.map(
        (analysis) =>
            analysis.qualityScore,
    );

    const liveScores = analyses.map(
        (analysis) =>
            analysis.liveScore,
    );

    const realScores = analyses.map(
        (analysis) =>
            analysis.realScore,
    );

    const passedLivenessSamples =
        analyses.filter(
            (analysis) =>
                analysis.livenessPassed,
        ).length;

    /*
     * Minimal dua pertiga sampel harus lulus.
     *
     * 3 sampel → minimal 2 lulus.
     * 4 sampel → minimal 3 lulus.
     */
    const requiredLivenessSamples =
        Math.ceil(
            sampleCount * 2 / 3,
        );

    const qualityScore = rounded(
        average(qualityScores),
    );

    const minimumFrameQuality = rounded(
        minimum(qualityScores),
    );

    const liveScore =
        averageNullable(liveScores);

    const realScore =
        averageNullable(realScores);

    const livenessPassed =
        passedLivenessSamples
            >= requiredLivenessSamples;

    const firstAnalysis = analyses[0];

    if (!firstAnalysis) {
        throw new FaceProbeAnalysisError(
            'NO_ANALYSIS',
            'Analisis wajah tidak menghasilkan data.',
        );
    }

    return {
        embedding,

        embeddingDimension:
            embedding.length,

        modelName:
            firstAnalysis.modelName,

        modelVersion:
            firstAnalysis.modelVersion,

        qualityScore,

        livenessPassed,

        liveScore,

        realScore,

        sampleCount,

        passedLivenessSamples,

        requiredLivenessSamples,

        minimumFrameQuality,

        metadata: {
            overall_score:
                rounded(
                    average(
                        analyses.map(
                            (analysis) =>
                                analysis
                                    .metadata
                                    .overall_score,
                        ),
                    ),
                ),

            face_score:
                rounded(
                    average(
                        analyses.map(
                            (analysis) =>
                                analysis
                                    .metadata
                                    .face_score,
                        ),
                    ),
                ),

            box_score:
                rounded(
                    average(
                        analyses.map(
                            (analysis) =>
                                analysis
                                    .metadata
                                    .box_score,
                        ),
                    ),
                ),

            face_coverage:
                rounded(
                    average(
                        analyses.map(
                            (analysis) =>
                                analysis
                                    .metadata
                                    .face_coverage,
                        ),
                    ),
                ),

            live_score:
                liveScore,

            real_score:
                realScore,

            detected_faces:
                1,

            sample_count:
                sampleCount,

            required_liveness_samples:
                requiredLivenessSamples,

            passed_liveness_samples:
                passedLivenessSamples,

            minimum_quality_score:
                minimumQualityScore,

            frame_quality_scores:
                qualityScores.map(
                    (value) =>
                        rounded(value),
                ),

            frame_live_scores:
                liveScores,

            frame_real_scores:
                realScores,
        },
    };
}