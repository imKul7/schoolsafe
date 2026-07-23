import { prepareHuman } from './human-client';

export type FaceChallengeAction =
    | 'blink'
    | 'turn_head';

export interface FaceChallengeDefinition {
    id: string;
    sequence: FaceChallengeAction[];
    expires_in: number;
}

export type FaceChallengePhase =
    | 'centering'
    | 'performing'
    | 'returning'
    | 'completed';

export interface FaceChallengeProgress {
    phase: FaceChallengePhase;
    action: FaceChallengeAction | null;
    message: string;
    completedActions: number;
    totalActions: number;
    elapsedMs: number;
}

export interface FaceChallengeEvidence {
    completed_actions: FaceChallengeAction[];
    blink_duration_ms: number | null;
    maximum_yaw_delta: number | null;
    returned_to_center: boolean;
    duration_ms: number;
    sample_count: number;
}

export interface RunFaceChallengeOptions {
    blinkMinMs?: number;
    blinkMaxMs?: number;
    headTurnYawDelta?: number;
    centerYawTolerance?: number;
    requiredCenterFrames?: number;
    maximumDurationMs?: number;

    /*
     * Interval tambahan setelah setiap inferensi.
     * Nilai kecil membantu menangkap kedipan singkat,
     * tetapi jangan dibuat terlalu kecil agar perangkat
     * tidak bekerja terlalu berat.
     */
    frameIntervalMs?: number;

    /*
     * Rasio penurunan bukaan mata dibanding baseline.
     *
     * Contoh:
     * baseline 0.30
     * rasio    0.78
     * threshold tertutup = 0.234
     */
    blinkCloseRatio?: number;

    /*
     * Jumlah frame posisi tengah yang digunakan untuk
     * membentuk baseline yaw dan bukaan mata.
     */
    baselineFrames?: number;

    signal?: AbortSignal;

    onProgress?: (
        progress: FaceChallengeProgress,
    ) => void;
}

export class FaceChallengeError extends Error {
    public readonly code: string;

    public constructor(
        code: string,
        message: string,
    ) {
        super(message);

        this.name = 'FaceChallengeError';
        this.code = code;
    }
}

interface GestureLike {
    gesture?: unknown;
}

type MeshPoint =
    | number[]
    | {
        x?: number;
        y?: number;
        z?: number;
    };

function abortError(): DOMException {
    return new DOMException(
        'Challenge dibatalkan.',
        'AbortError',
    );
}

function assertNotAborted(
    signal?: AbortSignal,
): void {
    if (signal?.aborted) {
        throw abortError();
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
            if (signal?.aborted) {
                reject(abortError());

                return;
            }

            let completed = false;

            const cleanup = (): void => {
                signal?.removeEventListener(
                    'abort',
                    handleAbort,
                );
            };

            const finish = (): void => {
                if (completed) {
                    return;
                }

                completed = true;

                cleanup();
                resolve();
            };

            const handleAbort = (): void => {
                if (completed) {
                    return;
                }

                completed = true;

                window.clearTimeout(
                    timeoutId,
                );

                cleanup();
                reject(abortError());
            };

            const timeoutId =
                window.setTimeout(
                    finish,
                    milliseconds,
                );

            signal?.addEventListener(
                'abort',
                handleAbort,
                {
                    once: true,
                },
            );
        },
    );
}

function clampNumber(
    value: number | undefined,
    fallback: number,
    minimum: number,
    maximum: number,
): number {
    const numericValue =
        typeof value === 'number'
        && Number.isFinite(value)
            ? value
            : fallback;

    return Math.min(
        maximum,
        Math.max(
            minimum,
            numericValue,
        ),
    );
}

function clampInteger(
    value: number | undefined,
    fallback: number,
    minimum: number,
    maximum: number,
): number {
    return Math.trunc(
        clampNumber(
            value,
            fallback,
            minimum,
            maximum,
        ),
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
        throw new FaceChallengeError(
            'VIDEO_NOT_READY',
            'Kamera belum siap. Tunggu sampai video tampil lalu coba kembali.',
        );
    }
}

function normalizeGestureName(
    value: string,
): string {
    return value
        .trim()
        .toLowerCase()
        .replace(/\s+/g, ' ');
}

function gestureNames(
    gestureResult: unknown,
): string[] {
    if (
        typeof gestureResult !== 'object'
        || gestureResult === null
    ) {
        return [];
    }

    return Object.values(
        gestureResult,
    )
        .map(
            (
                item,
            ): string | null => {
                /*
                 * Beberapa versi atau konfigurasi dapat
                 * memberikan gesture langsung sebagai string.
                 */
                if (typeof item === 'string') {
                    return normalizeGestureName(
                        item,
                    );
                }

                if (
                    typeof item !== 'object'
                    || item === null
                ) {
                    return null;
                }

                const gesture =
                    (
                        item as GestureLike
                    ).gesture;

                return typeof gesture
                    === 'string'
                    ? normalizeGestureName(
                        gesture,
                    )
                    : null;
            },
        )
        .filter(
            (
                value,
            ): value is string =>
                value !== null
                && value !== '',
        );
}

function containsBlink(
    gestures: string[],
): boolean {
    return gestures.some(
        (gesture) =>
            gesture.includes('blink')
            || gesture.includes(
                'eye closed',
            )
            || gesture.includes(
                'eyes closed',
            ),
    );
}

function pointCoordinates(
    point: unknown,
): [number, number] | null {
    if (Array.isArray(point)) {
        const x = Number(point[0]);
        const y = Number(point[1]);

        if (
            Number.isFinite(x)
            && Number.isFinite(y)
        ) {
            return [
                x,
                y,
            ];
        }

        return null;
    }

    if (
        typeof point === 'object'
        && point !== null
    ) {
        const candidate =
            point as {
                x?: unknown;
                y?: unknown;
            };

        const x =
            Number(candidate.x);

        const y =
            Number(candidate.y);

        if (
            Number.isFinite(x)
            && Number.isFinite(y)
        ) {
            return [
                x,
                y,
            ];
        }
    }

    return null;
}

function pointDistance(
    first: unknown,
    second: unknown,
): number | null {
    const firstPoint =
        pointCoordinates(first);

    const secondPoint =
        pointCoordinates(second);

    if (
        firstPoint === null
        || secondPoint === null
    ) {
        return null;
    }

    return Math.hypot(
        secondPoint[0]
            - firstPoint[0],

        secondPoint[1]
            - firstPoint[1],
    );
}

function singleEyeAspectRatio(
    mesh: MeshPoint[],
    indices: readonly [
        number,
        number,
        number,
        number,
        number,
        number,
    ],
): number | null {
    const [
        outerCorner,
        upperOuter,
        upperInner,
        innerCorner,
        lowerInner,
        lowerOuter,
    ] = indices;

    const horizontal =
        pointDistance(
            mesh[outerCorner],
            mesh[innerCorner],
        );

    const verticalOuter =
        pointDistance(
            mesh[upperOuter],
            mesh[lowerOuter],
        );

    const verticalInner =
        pointDistance(
            mesh[upperInner],
            mesh[lowerInner],
        );

    if (
        horizontal === null
        || verticalOuter === null
        || verticalInner === null
        || horizontal <= 0
    ) {
        return null;
    }

    const ratio = (
        verticalOuter
        + verticalInner
    ) / (
        2 * horizontal
    );

    return Number.isFinite(ratio)
        && ratio > 0
            ? ratio
            : null;
}

function eyeAspectRatio(
    mesh: unknown,
): number | null {
    if (
        !Array.isArray(mesh)
        || mesh.length < 468
    ) {
        return null;
    }

    const typedMesh =
        mesh as MeshPoint[];

    /*
     * Indeks landmark MediaPipe Face Mesh.
     *
     * Nilai kiri dan kanan dirata-ratakan agar
     * noise pada satu mata tidak langsung dianggap
     * sebagai kedipan.
     */
    const firstEye =
        singleEyeAspectRatio(
            typedMesh,
            [
                33,
                160,
                158,
                133,
                153,
                144,
            ],
        );

    const secondEye =
        singleEyeAspectRatio(
            typedMesh,
            [
                362,
                385,
                387,
                263,
                373,
                380,
            ],
        );

    const validValues = [
        firstEye,
        secondEye,
    ].filter(
        (
            value,
        ): value is number =>
            value !== null
            && Number.isFinite(value),
    );

    if (validValues.length === 0) {
        return null;
    }

    return validValues.reduce(
        (
            total,
            value,
        ) => total + value,
        0,
    ) / validValues.length;
}

function median(
    values: number[],
): number | null {
    const validValues =
        values
            .filter(
                (value) =>
                    Number.isFinite(value),
            )
            .sort(
                (
                    first,
                    second,
                ) => first - second,
            );

    if (validValues.length === 0) {
        return null;
    }

    const middleIndex =
        Math.floor(
            validValues.length / 2,
        );

    if (
        validValues.length % 2 === 0
    ) {
        const lowerValue =
            validValues[
                middleIndex - 1
            ];

        const upperValue =
            validValues[
                middleIndex
            ];

        if (
            lowerValue === undefined
            || upperValue === undefined
        ) {
            return null;
        }

        return (
            lowerValue
            + upperValue
        ) / 2;
    }

    return validValues[
        middleIndex
    ] ?? null;
}

function appendLimited(
    values: number[],
    value: number,
    maximumLength: number,
): void {
    values.push(value);

    while (
        values.length
        > maximumLength
    ) {
        values.shift();
    }
}

function isFacingCenter(
    gestures: string[],
    yaw: number,
    tolerance: number,
): boolean {
    const gestureShowsCenter =
        gestures.some(
            (gesture) =>
                gesture.includes(
                    'facing center',
                )
                || gesture.includes(
                    'face center',
                )
                || gesture.includes(
                    'looking center',
                ),
        );

    return (
        gestureShowsCenter
        || Math.abs(yaw)
            <= tolerance
    );
}

function challengeMessage(
    action: FaceChallengeAction,
    returningToCenter: boolean,
): string {
    if (returningToCenter) {
        return 'Kembalikan wajah menghadap lurus ke kamera.';
    }

    switch (action) {
        case 'blink':
            return 'Kedipkan kedua mata satu kali dengan jelas.';

        case 'turn_head':
            return 'Gerakkan kepala ke kiri atau kanan.';
    }
}

function progress(
    options: RunFaceChallengeOptions,
    phase: FaceChallengePhase,
    action: FaceChallengeAction | null,
    message: string,
    completedActions: number,
    totalActions: number,
    elapsedMs: number,
): void {
    options.onProgress?.({
        phase,
        action,
        message,
        completedActions,
        totalActions,
        elapsedMs:
            Math.max(
                0,
                Math.round(elapsedMs),
            ),
    });
}

function validateChallenge(
    challenge: FaceChallengeDefinition,
): void {
    if (
        typeof challenge.id !== 'string'
        || challenge.id.trim() === ''
    ) {
        throw new FaceChallengeError(
            'INVALID_CHALLENGE_ID',
            'ID challenge dari server tidak valid.',
        );
    }

    if (
        !Array.isArray(
            challenge.sequence,
        )
        || challenge.sequence.length !== 2
    ) {
        throw new FaceChallengeError(
            'INVALID_CHALLENGE_SEQUENCE',
            'Urutan challenge dari server tidak valid.',
        );
    }

    const validActions =
        challenge.sequence.every(
            (action) =>
                action === 'blink'
                || action === 'turn_head',
        );

    if (!validActions) {
        throw new FaceChallengeError(
            'INVALID_CHALLENGE_ACTION',
            'Challenge berisi aksi yang tidak dikenal.',
        );
    }

    /*
     * Challenge SchoolSafe wajib terdiri dari
     * satu kedipan dan satu gerakan kepala.
     */
    if (
        new Set(
            challenge.sequence,
        ).size !== 2
    ) {
        throw new FaceChallengeError(
            'DUPLICATE_CHALLENGE_ACTION',
            'Challenge dari server memiliki aksi yang sama.',
        );
    }

    if (
        typeof challenge.expires_in
            !== 'number'
        || !Number.isFinite(
            challenge.expires_in,
        )
        || challenge.expires_in <= 0
    ) {
        throw new FaceChallengeError(
            'INVALID_CHALLENGE_EXPIRY',
            'Masa berlaku challenge tidak valid.',
        );
    }
}

export async function runFaceChallenge(
    video: HTMLVideoElement,
    challenge: FaceChallengeDefinition,
    options: RunFaceChallengeOptions = {},
): Promise<FaceChallengeEvidence> {
    ensureVideoReady(video);
    validateChallenge(challenge);
    assertNotAborted(options.signal);

    const blinkMinMs =
        clampInteger(
            options.blinkMinMs,
            30,
            10,
            2000,
        );

    const blinkMaxMs =
        clampInteger(
            options.blinkMaxMs,
            1200,
            blinkMinMs,
            3000,
        );

    const headTurnYawDelta =
        clampNumber(
            options.headTurnYawDelta,
            0.18,
            0.03,
            1.5,
        );

    const centerYawTolerance =
        clampNumber(
            options.centerYawTolerance,
            0.1,
            0.02,
            Math.max(
                0.03,
                headTurnYawDelta * 0.8,
            ),
        );

    const requiredCenterFrames =
        clampInteger(
            options.requiredCenterFrames,
            2,
            1,
            10,
        );

    const maximumDurationMs =
        clampInteger(
            options.maximumDurationMs,
            30000,
            5000,
            60000,
        );

    const frameIntervalMs =
        clampInteger(
            options.frameIntervalMs,
            50,
            30,
            500,
        );

    const blinkCloseRatio =
        clampNumber(
            options.blinkCloseRatio,
            0.78,
            0.5,
            0.95,
        );

    const baselineFrames =
        clampInteger(
            options.baselineFrames,
            4,
            3,
            10,
        );

    const human =
        await prepareHuman();

    assertNotAborted(options.signal);

    const startedAt =
        performance.now();

    const completedActions:
        FaceChallengeAction[] = [];

    let currentActionIndex = 0;

    /*
     * Baseline posisi wajah.
     */
    let baselineYaw:
        number | null = null;

    let baselineEyeAspectRatio:
        number | null = null;

    const centerYawSamples:
        number[] = [];

    const openEyeSamples:
        number[] = [];

    /*
     * State kedipan.
     */
    let blinkArmed = false;

    let blinkStartedAt:
        number | null = null;

    let blinkDurationMs:
        number | null = null;

    let blinkClosedFrames = 0;
    let blinkOpenFrames = 0;

    /*
     * State gerakan kepala.
     */
    let headTurnDetected = false;

    let centerFrameCount = 0;

    let returnedToCenter = false;

    let maximumHeadTurnYawDelta = 0;

    let sampleCount = 0;

    const resetBlinkTransientState =
        (): void => {
            blinkStartedAt = null;
            blinkClosedFrames = 0;
            blinkOpenFrames = 0;
        };

    while (
        currentActionIndex
        < challenge.sequence.length
    ) {
        assertNotAborted(
            options.signal,
        );

        const elapsedMs =
            performance.now()
            - startedAt;

        if (
            elapsedMs
            > maximumDurationMs
        ) {
            throw new FaceChallengeError(
                'CHALLENGE_TIMEOUT',
                'Waktu challenge habis. Jalankan verifikasi ulang.',
            );
        }

        ensureVideoReady(video);

        const result =
            await human.detect(video);

        assertNotAborted(
            options.signal,
        );

        sampleCount += 1;

        const faces =
            Array.isArray(result.face)
                ? result.face
                : [];

        const currentAction =
            challenge.sequence[
                currentActionIndex
            ] ?? null;

        if (
            faces.length !== 1
        ) {
            /*
             * Kehilangan wajah pada saat kedipan tidak
             * boleh dianggap sebagai mata kembali terbuka.
             */
            resetBlinkTransientState();

            centerFrameCount = 0;

            progress(
                options,
                'centering',
                currentAction,
                faces.length === 0
                    ? 'Wajah belum terdeteksi. Posisikan wajah di dalam bingkai.'
                    : 'Pastikan hanya satu wajah terlihat di kamera.',
                completedActions.length,
                challenge.sequence.length,
                elapsedMs,
            );

            await sleep(
                frameIntervalMs,
                options.signal,
            );

            continue;
        }

        const face =
            faces[0];

        if (!face) {
            await sleep(
                frameIntervalMs,
                options.signal,
            );

            continue;
        }

        const yaw =
            face.rotation
                ?.angle
                .yaw;

        if (
            typeof yaw !== 'number'
            || !Number.isFinite(yaw)
        ) {
            resetBlinkTransientState();
            centerFrameCount = 0;

            progress(
                options,
                'centering',
                currentAction,
                'Posisi kepala belum dapat dibaca. Tatap kamera dengan pencahayaan yang cukup.',
                completedActions.length,
                challenge.sequence.length,
                elapsedMs,
            );

            await sleep(
                frameIntervalMs,
                options.signal,
            );

            continue;
        }

        const gestures =
            gestureNames(
                result.gesture,
            );

        const currentEyeAspectRatio =
            eyeAspectRatio(
                face.mesh,
            );

        /*
         * Ambil baseline posisi kepala terlebih dahulu.
         *
         * Baseline yaw tidak lagi bergantung pada
         * keberhasilan pembacaan face mesh. Dengan begitu,
         * pengguna berkacamata tetap dapat melanjutkan
         * challenge menggunakan detektor gesture apabila
         * mesh mata kurang stabil.
         */
        if (baselineYaw === null) {
            const centered =
                isFacingCenter(
                    gestures,
                    yaw,
                    centerYawTolerance
                        * 2.5,
                );

            if (centered) {
                appendLimited(
                    centerYawSamples,
                    yaw,
                    baselineFrames + 4,
                );

                if (
                    currentEyeAspectRatio
                        !== null
                    && currentEyeAspectRatio
                        > 0
                    && !containsBlink(
                        gestures,
                    )
                ) {
                    appendLimited(
                        openEyeSamples,
                        currentEyeAspectRatio,
                        baselineFrames + 6,
                    );
                }

                progress(
                    options,
                    'centering',
                    currentAction,
                    'Tatap lurus ke kamera dan buka mata secara normal.',
                    completedActions.length,
                    challenge.sequence.length,
                    elapsedMs,
                );
            } else {
                centerYawSamples.length = 0;
                openEyeSamples.length = 0;

                progress(
                    options,
                    'centering',
                    currentAction,
                    'Hadapkan wajah lurus ke kamera.',
                    completedActions.length,
                    challenge.sequence.length,
                    elapsedMs,
                );
            }

            if (
                centerYawSamples.length
                >= baselineFrames
            ) {
                baselineYaw =
                    median(
                        centerYawSamples,
                    );

                /*
                 * EAR bersifat tambahan. Baseline tetap
                 * boleh selesai saat pembacaan mata gagal.
                 */
                if (
                    openEyeSamples.length
                    >= 2
                ) {
                    baselineEyeAspectRatio =
                        median(
                            openEyeSamples,
                        );
                }

                /*
                 * Frame baseline dianggap kondisi mata
                 * terbuka sehingga detektor kedip siap.
                 */
                blinkArmed = true;
            }

            await sleep(
                frameIntervalMs,
                options.signal,
            );

            continue;
        }

        const action =
            challenge.sequence[
                currentActionIndex
            ];

        if (!action) {
            throw new FaceChallengeError(
                'MISSING_CHALLENGE_ACTION',
                'Tahap challenge berikutnya tidak tersedia.',
            );
        }

        const yawDelta =
            Math.abs(
                yaw - baselineYaw,
            );

        /*
         * Challenge kedipan.
         */
        if (action === 'blink') {
            const blinkingByGesture =
                containsBlink(
                    gestures,
                );

            const blinkingByGeometry =
                currentEyeAspectRatio
                    !== null
                && baselineEyeAspectRatio
                    !== null
                && currentEyeAspectRatio
                    <= baselineEyeAspectRatio
                        * blinkCloseRatio;

            const blinking =
                blinkingByGesture
                || blinkingByGeometry;

            const now =
                performance.now();

            /*
             * Detektor harus melihat kondisi terbuka
             * sebelum menerima kondisi tertutup.
             */
            if (
                !blinking
                && blinkStartedAt === null
            ) {
                blinkArmed = true;
            }

            if (
                blinking
                && blinkArmed
            ) {
                blinkOpenFrames = 0;
                blinkClosedFrames += 1;

                if (
                    blinkStartedAt === null
                ) {
                    blinkStartedAt = now;
                }
            }

            if (
                !blinking
                && blinkStartedAt !== null
                && blinkClosedFrames >= 1
            ) {
                blinkOpenFrames += 1;

                const duration =
                    now - blinkStartedAt;

                /*
                 * Satu frame terbuka setelah kondisi
                 * tertutup sudah cukup. Inferensi model
                 * biasanya lebih lambat dari interval timer.
                 */
                if (blinkOpenFrames >= 1) {
                    if (
                        duration >= blinkMinMs
                        && duration <= blinkMaxMs
                    ) {
                        blinkDurationMs =
                            Math.round(
                                duration,
                            );

                        completedActions.push(
                            'blink',
                        );

                        currentActionIndex += 1;

                        blinkArmed = false;

                        resetBlinkTransientState();
                    } else {
                        /*
                         * Kedipan terlalu cepat atau terlalu
                         * lama tidak diterima. Pengguna harus
                         * membuka mata sebelum mencoba lagi.
                         */
                        resetBlinkTransientState();
                        blinkArmed = true;
                    }
                }
            }

            if (
                blinking
                && blinkStartedAt !== null
                && now - blinkStartedAt
                    > blinkMaxMs
            ) {
                resetBlinkTransientState();
                blinkArmed = false;
            }

            progress(
                options,
                'performing',
                action,
                challengeMessage(
                    action,
                    false,
                ),
                completedActions.length,
                challenge.sequence.length,
                elapsedMs,
            );
        }

        /*
         * Challenge gerakan kepala.
         */
        if (action === 'turn_head') {
            maximumHeadTurnYawDelta =
                Math.max(
                    maximumHeadTurnYawDelta,
                    yawDelta,
                );

            if (
                !headTurnDetected
                && yawDelta
                    >= headTurnYawDelta
            ) {
                headTurnDetected = true;
                centerFrameCount = 0;
            }

            if (headTurnDetected) {
                if (
                    yawDelta
                    <= centerYawTolerance
                ) {
                    centerFrameCount += 1;
                } else {
                    centerFrameCount = 0;
                }

                if (
                    centerFrameCount
                    >= requiredCenterFrames
                ) {
                    returnedToCenter = true;

                    completedActions.push(
                        'turn_head',
                    );

                    currentActionIndex += 1;

                    centerFrameCount = 0;
                }
            }

            progress(
                options,
                headTurnDetected
                    ? 'returning'
                    : 'performing',
                action,
                challengeMessage(
                    action,
                    headTurnDetected,
                ),
                completedActions.length,
                challenge.sequence.length,
                elapsedMs,
            );
        }

        await sleep(
            frameIntervalMs,
            options.signal,
        );
    }

    const durationMs =
        Math.round(
            performance.now()
            - startedAt,
        );

    /*
     * Perlindungan terhadap state internal yang tidak
     * lengkap. Challenge yang berhasil harus selalu
     * memiliki bukti kedipan dan gerakan kepala.
     */
    if (blinkDurationMs === null) {
        throw new FaceChallengeError(
            'BLINK_EVIDENCE_MISSING',
            'Bukti kedipan belum berhasil direkam.',
        );
    }

    if (
        maximumHeadTurnYawDelta
        < headTurnYawDelta
    ) {
        throw new FaceChallengeError(
            'HEAD_TURN_EVIDENCE_MISSING',
            'Bukti gerakan kepala belum berhasil direkam.',
        );
    }

    if (!returnedToCenter) {
        throw new FaceChallengeError(
            'CENTER_RETURN_MISSING',
            'Wajah belum kembali menghadap lurus ke kamera.',
        );
    }

    if (
        completedActions.length
            !== challenge.sequence.length
        || completedActions.some(
            (
                action,
                index,
            ) =>
                action
                !== challenge.sequence[
                    index
                ],
        )
    ) {
        throw new FaceChallengeError(
            'CHALLENGE_SEQUENCE_MISMATCH',
            'Urutan challenge tidak berhasil diselesaikan.',
        );
    }

    progress(
        options,
        'completed',
        null,
        'Challenge berhasil diselesaikan.',
        completedActions.length,
        challenge.sequence.length,
        durationMs,
    );

    return {
        completed_actions:
            completedActions,

        blink_duration_ms:
            blinkDurationMs,

        maximum_yaw_delta:
            Number(
                maximumHeadTurnYawDelta
                    .toFixed(4),
            ),

        returned_to_center:
            returnedToCenter,

        duration_ms:
            durationMs,

        sample_count:
            sampleCount,
    };
}