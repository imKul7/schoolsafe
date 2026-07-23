import { FaceAnalysisError } from '@/lib/biometrics/analyze-face';
import {
    analyzeFaceProbe,
    FaceProbeAnalysisError,
    type FaceProbeAnalysis,
    type FaceProbeProgress,
} from '@/lib/biometrics/analyze-face-probe';
import {
    FaceChallengeError,
    runFaceChallenge,
    type FaceChallengeDefinition,
    type FaceChallengeEvidence,
    type FaceChallengeProgress,
} from '@/lib/biometrics/run-face-challenge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface VerificationChallengeConfig {
    blink_min_ms?: number;
    blink_max_ms?: number;
    head_turn_yaw_delta?: number;
    center_yaw_tolerance?: number;
    required_center_frames?: number;
    maximum_duration_ms?: number;
    frame_interval_ms?: number;
    blink_close_ratio?: number;
    baseline_frames?: number;
}

interface VerificationSecurityConfig {
    cooldown_seconds?: number;
}

interface VerificationConfig {
    minimum_quality_score: number;
    minimum_similarity: number;
    minimum_margin: number;
    probe_samples: number;
    probe_delay_milliseconds?: number;
    minimum_frame_quality?: number;
    challenge?: VerificationChallengeConfig;
    security?: VerificationSecurityConfig;
}

interface MatchedStudent {
    id: number;
    full_name: string;
    student_number: string | null;
    class_name: string | null;
    academic_year: string | null;
    relationship_type: string;
    is_primary: boolean;
}

interface MatchedPickupPerson {
    id: number;
    full_name: string;
    phone: string | null;
    photo_url: string | null;
    students: MatchedStudent[];
}

type VerificationResultType =
    | 'match'
    | 'no_match'
    | 'ambiguous'
    | 'no_candidates';

interface VerificationResponse {
    matched: boolean;
    result: VerificationResultType;
    message: string;
    similarity: number | null;
    threshold: number;
    margin: number | null;
    verification_attempt_id: number | null;
    pickup_person: MatchedPickupPerson | null;
}

interface PickupConfirmationStudent {
    id: number;
    student_id: number | null;
    student_name: string;
    student_number: string | null;
    class_name: string | null;
    academic_year: string | null;
    relationship_type: string | null;
    is_primary: boolean;
    status: string;
    status_label: string;
    released_at: string | null;
}

interface PickupConfirmationPerson {
    id: number | null;
    full_name: string;
    phone: string | null;
}

interface PickupConfirmationUser {
    id: number;
    name: string;
}

interface PickupConfirmationEvent {
    id: number;
    idempotency_key: string;
    status: string;
    status_label: string;
    verification_method: string;
    verification_method_label: string;
    confirmed_at: string | null;
    pickup_person: PickupConfirmationPerson;
    confirmed_by: PickupConfirmationUser | null;
    students: PickupConfirmationStudent[];
}

interface PickupConfirmationResponse {
    message: string;
    replayed: boolean;
    pickup_event: PickupConfirmationEvent;
}

interface PageProps {
    verificationConfig: VerificationConfig;
}

interface LaravelErrorPayload {
    message?: string;
    retry_after?: number;

    errors?: Record<
        string,
        string[] | string
    >;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Verifikasi Gerbang',
        href: '/gate/face-verification',
    },
];

function percentage(
    value: number | null,
): string {
    if (
        value === null
        || !Number.isFinite(value)
    ) {
        return '-';
    }

    return `${Math.round(value * 100)}%`;
}

function finiteNumber(
    value: number | undefined,
    fallback: number,
): number {
    if (
        typeof value !== 'number'
        || !Number.isFinite(value)
    ) {
        return fallback;
    }

    return value;
}

function positiveInteger(
    value: number | undefined,
    fallback: number,
): number {
    return Math.max(
        1,
        Math.trunc(
            finiteNumber(
                value,
                fallback,
            ),
        ),
    );
}

function clampNumber(
    value: number | undefined,
    fallback: number,
    minimum: number,
    maximum: number,
): number {
    return Math.min(
        maximum,
        Math.max(
            minimum,
            finiteNumber(
                value,
                fallback,
            ),
        ),
    );
}

function isPositiveIntegerValue(
    value: unknown,
): value is number {
    return (
        typeof value === 'number'
        && Number.isInteger(value)
        && value > 0
    );
}

function isNullableFiniteNumber(
    value: unknown,
): value is number | null {
    return (
        value === null
        || (
            typeof value === 'number'
            && Number.isFinite(value)
        )
    );
}

function isNullableString(
    value: unknown,
): value is string | null {
    return (
        value === null
        || typeof value === 'string'
    );
}

function relationshipLabel(
    value: string | null,
): string {
    switch (value) {
        case 'father':
            return 'Ayah';

        case 'mother':
            return 'Ibu';

        case 'guardian':
            return 'Wali';

        case 'sibling':
            return 'Saudara';

        case 'grandparent':
            return 'Kakek/Nenek';

        case 'driver':
            return 'Sopir';

        case 'relative':
            return 'Kerabat';

        default:
            return 'Lainnya';
    }
}

function challengeActionLabel(
    action: string | null,
): string {
    switch (action) {
        case 'blink':
            return 'Kedipkan Mata';

        case 'turn_head':
            return 'Gerakkan Kepala';

        default:
            return 'Persiapan';
    }
}

function challengePhaseLabel(
    phase: FaceChallengeProgress['phase'],
): string {
    switch (phase) {
        case 'centering':
            return 'Mengatur Posisi';

        case 'performing':
            return 'Menjalankan Challenge';

        case 'returning':
            return 'Kembali ke Tengah';

        case 'completed':
            return 'Selesai';
    }
}

function verificationResultTitle(
    result: VerificationResponse,
): string {
    if (result.matched) {
        return 'Wajah Cocok';
    }

    switch (result.result) {
        case 'ambiguous':
            return 'Hasil Ambigu';

        case 'no_candidates':
            return 'Belum Ada Kandidat';

        case 'no_match':
        default:
            return 'Wajah Tidak Cocok';
    }
}

function csrfHeaders(): Record<string, string> {
    const metaToken =
        document.querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )?.content;

    if (metaToken) {
        return {
            'X-CSRF-TOKEN':
                metaToken,
        };
    }

    const xsrfCookie =
        document.cookie
            .split('; ')
            .find(
                (cookie) =>
                    cookie.startsWith(
                        'XSRF-TOKEN=',
                    ),
            );

    if (!xsrfCookie) {
        return {};
    }

    const encodedToken =
        xsrfCookie.substring(
            'XSRF-TOKEN='.length,
        );

    return {
        'X-XSRF-TOKEN':
            decodeURIComponent(
                encodedToken,
            ),
    };
}

function asLaravelErrorPayload(
    payload: unknown,
): LaravelErrorPayload | null {
    if (
        typeof payload !== 'object'
        || payload === null
    ) {
        return null;
    }

    return payload as LaravelErrorPayload;
}

function validationMessages(
    payload: LaravelErrorPayload | null,
): string[] {
    if (!payload?.errors) {
        return [];
    }

    return Object.values(
        payload.errors,
    )
        .flatMap((value) =>
            Array.isArray(value)
                ? value
                : [value],
        )
        .map((message) =>
            String(message),
        )
        .filter(
            (message) =>
                message.trim() !== '',
        );
}

async function readResponsePayload(
    response: globalThis.Response,
): Promise<unknown> {
    const contentType =
        response.headers.get(
            'content-type',
        ) ?? '';

    if (
        contentType.includes('json')
    ) {
        try {
            return await response.json();
        } catch {
            return null;
        }
    }

    try {
        const text =
            await response.text();

        return text.trim() !== ''
            ? {
                message: text,
            }
            : null;
    } catch {
        return null;
    }
}

function isMatchedStudent(
    payload: unknown,
): payload is MatchedStudent {
    if (
        typeof payload !== 'object'
        || payload === null
    ) {
        return false;
    }

    const candidate =
        payload as Partial<MatchedStudent>;

    return (
        isPositiveIntegerValue(
            candidate.id,
        )
        && typeof candidate.full_name
            === 'string'
        && isNullableString(
            candidate.student_number,
        )
        && isNullableString(
            candidate.class_name,
        )
        && isNullableString(
            candidate.academic_year,
        )
        && typeof candidate.relationship_type
            === 'string'
        && typeof candidate.is_primary
            === 'boolean'
    );
}

function isMatchedPickupPerson(
    payload: unknown,
): payload is MatchedPickupPerson {
    if (
        typeof payload !== 'object'
        || payload === null
    ) {
        return false;
    }

    const candidate =
        payload as Partial<
            MatchedPickupPerson
        >;

    return (
        isPositiveIntegerValue(
            candidate.id,
        )
        && typeof candidate.full_name
            === 'string'
        && isNullableString(
            candidate.phone,
        )
        && isNullableString(
            candidate.photo_url,
        )
        && Array.isArray(
            candidate.students,
        )
        && candidate.students.every(
            isMatchedStudent,
        )
    );
}

function isChallengeDefinition(
    payload: unknown,
): payload is FaceChallengeDefinition {
    if (
        typeof payload !== 'object'
        || payload === null
    ) {
        return false;
    }

    const candidate =
        payload as Partial<
            FaceChallengeDefinition
        >;

    if (
        typeof candidate.id !== 'string'
        || candidate.id.trim() === ''
        || !Array.isArray(
            candidate.sequence,
        )
        || candidate.sequence.length !== 2
        || typeof candidate.expires_in
            !== 'number'
        || !Number.isFinite(
            candidate.expires_in,
        )
        || candidate.expires_in <= 0
    ) {
        return false;
    }

    const validActions =
        candidate.sequence.every(
            (action) =>
                action === 'blink'
                || action === 'turn_head',
        );

    return (
        validActions
        && new Set(
            candidate.sequence,
        ).size === 2
    );
}

function isVerificationResponse(
    payload: unknown,
): payload is VerificationResponse {
    if (
        typeof payload !== 'object'
        || payload === null
    ) {
        return false;
    }

    const candidate =
        payload as Partial<
            VerificationResponse
        >;

    const validResult =
        candidate.result === 'match'
        || candidate.result === 'no_match'
        || candidate.result === 'ambiguous'
        || candidate.result
            === 'no_candidates';

    const validAttemptId =
        candidate.verification_attempt_id
            === null
        || isPositiveIntegerValue(
            candidate.verification_attempt_id,
        );

    const validPickupPerson =
        candidate.pickup_person === null
        || isMatchedPickupPerson(
            candidate.pickup_person,
        );

    if (
        typeof candidate.matched !== 'boolean'
        || !validResult
        || typeof candidate.message !== 'string'
        || typeof candidate.threshold !== 'number'
        || !Number.isFinite(
            candidate.threshold,
        )
        || !isNullableFiniteNumber(
            candidate.similarity,
        )
        || !isNullableFiniteNumber(
            candidate.margin,
        )
        || !validAttemptId
        || !validPickupPerson
    ) {
        return false;
    }

    if (candidate.matched) {
        return (
            candidate.result === 'match'
            && isPositiveIntegerValue(
                candidate
                    .verification_attempt_id,
            )
            && isMatchedPickupPerson(
                candidate.pickup_person,
            )
        );
    }

    return true;
}

function isPickupConfirmationStudent(
    payload: unknown,
): payload is PickupConfirmationStudent {
    if (
        typeof payload !== 'object'
        || payload === null
    ) {
        return false;
    }

    const candidate =
        payload as Partial<
            PickupConfirmationStudent
        >;

    return (
        isPositiveIntegerValue(
            candidate.id,
        )
        && (
            candidate.student_id === null
            || isPositiveIntegerValue(
                candidate.student_id,
            )
        )
        && typeof candidate.student_name
            === 'string'
        && isNullableString(
            candidate.student_number,
        )
        && isNullableString(
            candidate.class_name,
        )
        && isNullableString(
            candidate.academic_year,
        )
        && isNullableString(
            candidate.relationship_type,
        )
        && typeof candidate.is_primary
            === 'boolean'
        && typeof candidate.status
            === 'string'
        && typeof candidate.status_label
            === 'string'
        && isNullableString(
            candidate.released_at,
        )
    );
}

function isPickupConfirmationPerson(
    payload: unknown,
): payload is PickupConfirmationPerson {
    if (
        typeof payload !== 'object'
        || payload === null
    ) {
        return false;
    }

    const candidate =
        payload as Partial<
            PickupConfirmationPerson
        >;

    return (
        (
            candidate.id === null
            || isPositiveIntegerValue(
                candidate.id,
            )
        )
        && typeof candidate.full_name
            === 'string'
        && isNullableString(
            candidate.phone,
        )
    );
}

function isPickupConfirmationUser(
    payload: unknown,
): payload is PickupConfirmationUser {
    if (
        typeof payload !== 'object'
        || payload === null
    ) {
        return false;
    }

    const candidate =
        payload as Partial<
            PickupConfirmationUser
        >;

    return (
        isPositiveIntegerValue(
            candidate.id,
        )
        && typeof candidate.name
            === 'string'
    );
}

function isPickupConfirmationEvent(
    payload: unknown,
): payload is PickupConfirmationEvent {
    if (
        typeof payload !== 'object'
        || payload === null
    ) {
        return false;
    }

    const candidate =
        payload as Partial<
            PickupConfirmationEvent
        >;

    return (
        isPositiveIntegerValue(
            candidate.id,
        )
        && typeof candidate.idempotency_key
            === 'string'
        && typeof candidate.status
            === 'string'
        && typeof candidate.status_label
            === 'string'
        && typeof candidate.verification_method
            === 'string'
        && typeof candidate
            .verification_method_label
            === 'string'
        && isNullableString(
            candidate.confirmed_at,
        )
        && isPickupConfirmationPerson(
            candidate.pickup_person,
        )
        && (
            candidate.confirmed_by === null
            || isPickupConfirmationUser(
                candidate.confirmed_by,
            )
        )
        && Array.isArray(
            candidate.students,
        )
        && candidate.students.every(
            isPickupConfirmationStudent,
        )
    );
}

function isPickupConfirmationResponse(
    payload: unknown,
): payload is PickupConfirmationResponse {
    if (
        typeof payload !== 'object'
        || payload === null
    ) {
        return false;
    }

    const candidate =
        payload as Partial<
            PickupConfirmationResponse
        >;

    return (
        typeof candidate.message
            === 'string'
        && typeof candidate.replayed
            === 'boolean'
        && isPickupConfirmationEvent(
            candidate.pickup_event,
        )
    );
}

function generateIdempotencyKey(): string {
    const cryptoObject =
        globalThis.crypto;

    if (
        cryptoObject
        && typeof cryptoObject.randomUUID
            === 'function'
    ) {
        return cryptoObject.randomUUID();
    }

    if (
        cryptoObject
        && typeof cryptoObject.getRandomValues
            === 'function'
    ) {
        const bytes =
            new Uint8Array(16);

        cryptoObject.getRandomValues(
            bytes,
        );

        bytes[6] =
            (
                (bytes[6] ?? 0)
                & 0x0f
            ) | 0x40;

        bytes[8] =
            (
                (bytes[8] ?? 0)
                & 0x3f
            ) | 0x80;

        const hexadecimal =
            Array.from(
                bytes,
                (value) =>
                    value
                        .toString(16)
                        .padStart(2, '0'),
            );

        return [
            hexadecimal
                .slice(0, 4)
                .join(''),

            hexadecimal
                .slice(4, 6)
                .join(''),

            hexadecimal
                .slice(6, 8)
                .join(''),

            hexadecimal
                .slice(8, 10)
                .join(''),

            hexadecimal
                .slice(10, 16)
                .join(''),
        ].join('-');
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'
        .replace(
            /[xy]/g,
            (character) => {
                const random =
                    Math.floor(
                        Math.random() * 16,
                    );

                const value =
                    character === 'x'
                        ? random
                        : (
                            random & 0x3
                        ) | 0x8;

                return value.toString(16);
            },
        );
}

function formatDateTime(
    value: string | null,
): string {
    if (!value) {
        return '-';
    }

    const date =
        new Date(value);

    if (
        Number.isNaN(
            date.getTime(),
        )
    ) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'id-ID',
        {
            dateStyle:
                'medium',

            timeStyle:
                'short',
        },
    ).format(date);
}

function cameraErrorMessage(
    error: unknown,
): string {
    if (error instanceof DOMException) {
        switch (error.name) {
            case 'NotAllowedError':
            case 'SecurityError':
                return 'Izin kamera ditolak. Izinkan akses kamera melalui pengaturan browser.';

            case 'NotFoundError':
                return 'Kamera tidak ditemukan pada perangkat ini.';

            case 'NotReadableError':
                return 'Kamera sedang digunakan aplikasi lain atau tidak dapat dibaca.';

            case 'OverconstrainedError':
                return 'Kamera tidak mendukung konfigurasi video yang diminta.';

            case 'AbortError':
                return 'Pembukaan kamera dibatalkan.';

            default:
                return error.message
                    || 'Kamera gagal dibuka.';
        }
    }

    if (error instanceof Error) {
        return error.message;
    }

    return 'Kamera gagal dibuka.';
}

export default function GateFaceVerification({
    verificationConfig,
}: PageProps) {
    const videoRef =
        useRef<HTMLVideoElement | null>(
            null,
        );

    const streamRef =
        useRef<MediaStream | null>(
            null,
        );

    const analysisAbortRef =
        useRef<AbortController | null>(
            null,
        );

    const pickupConfirmationAbortRef =
        useRef<AbortController | null>(
            null,
        );

    const [
        cameraReady,
        setCameraReady,
    ] = useState(false);

    const [
        isStartingCamera,
        setIsStartingCamera,
    ] = useState(false);

    const [
        cameraError,
        setCameraError,
    ] = useState<string | null>(
        null,
    );

    const [
        isVerifying,
        setIsVerifying,
    ] = useState(false);

    const [
        analysis,
        setAnalysis,
    ] = useState<FaceProbeAnalysis | null>(
        null,
    );

    const [
        probeProgress,
        setProbeProgress,
    ] = useState<FaceProbeProgress | null>(
        null,
    );

    const [
        challengeProgress,
        setChallengeProgress,
    ] = useState<FaceChallengeProgress | null>(
        null,
    );

    const [
        verificationResult,
        setVerificationResult,
    ] = useState<VerificationResponse | null>(
        null,
    );

    const [
        verificationError,
        setVerificationError,
    ] = useState<string | null>(
        null,
    );

    const [
        cooldownRemaining,
        setCooldownRemaining,
    ] = useState(0);

    const [
        selectedStudentIds,
        setSelectedStudentIds,
    ] = useState<number[]>([]);

    const [
        pickupNotes,
        setPickupNotes,
    ] = useState('');

    const [
        pickupIdempotencyKey,
        setPickupIdempotencyKey,
    ] = useState(
        () => generateIdempotencyKey(),
    );

    const [
        isConfirmingPickup,
        setIsConfirmingPickup,
    ] = useState(false);

    const [
        pickupConfirmation,
        setPickupConfirmation,
    ] =
        useState<PickupConfirmationResponse | null>(
            null,
        );

    const [
        pickupConfirmationError,
        setPickupConfirmationError,
    ] = useState<string | null>(
        null,
    );

    const challengeConfig =
        verificationConfig.challenge
        ?? {};

    const securityConfig =
        verificationConfig.security
        ?? {};

    const minimumQualityScore =
        clampNumber(
            verificationConfig
                .minimum_quality_score,
            0.75,
            0,
            1,
        );

    const minimumFrameQuality =
        clampNumber(
            verificationConfig
                .minimum_frame_quality,
            0.5,
            0,
            1,
        );

    const probeSamples =
        Math.min(
            10,
            positiveInteger(
                verificationConfig
                    .probe_samples,
                3,
            ),
        );

    const probeDelayMilliseconds =
        Math.max(
            250,
            Math.min(
                2000,
                positiveInteger(
                    verificationConfig
                        .probe_delay_milliseconds,
                    550,
                ),
            ),
        );

    const cooldownSeconds =
        Math.min(
            3600,
            positiveInteger(
                securityConfig
                    .cooldown_seconds,
                5,
            ),
        );

    const blinkMinimumMilliseconds =
        Math.max(
            10,
            positiveInteger(
                challengeConfig
                    .blink_min_ms,
                30,
            ),
        );

    const blinkMaximumMilliseconds =
        Math.max(
            blinkMinimumMilliseconds,
            Math.min(
                3000,
                positiveInteger(
                    challengeConfig
                        .blink_max_ms,
                    1200,
                ),
            ),
        );

    const challengeFrameInterval =
        Math.max(
            30,
            Math.min(
                500,
                positiveInteger(
                    challengeConfig
                        .frame_interval_ms,
                    50,
                ),
            ),
        );

    const blinkCloseRatio =
        clampNumber(
            challengeConfig
                .blink_close_ratio,
            0.78,
            0.5,
            0.95,
        );

    const baselineFrames =
        Math.max(
            3,
            Math.min(
                10,
                positiveInteger(
                    challengeConfig
                        .baseline_frames,
                    4,
                ),
            ),
        );

    const matchedPickupPerson =
        verificationResult?.matched
            ? verificationResult
                .pickup_person
            : null;

    useEffect(() => {
        return () => {
            analysisAbortRef.current
                ?.abort();

            pickupConfirmationAbortRef
                .current
                ?.abort();

            streamRef.current
                ?.getTracks()
                .forEach((track) => {
                    track.stop();
                });

            streamRef.current =
                null;
        };
    }, []);

    useEffect(() => {
        if (cooldownRemaining <= 0) {
            return;
        }

        const timeoutId =
            window.setTimeout(
                () => {
                    setCooldownRemaining(
                        (current) =>
                            Math.max(
                                0,
                                current - 1,
                            ),
                    );
                },
                1000,
            );

        return () => {
            window.clearTimeout(
                timeoutId,
            );
        };
    }, [cooldownRemaining]);

    function applyServerCooldown(
        payload: LaravelErrorPayload | null,
    ): void {
        const retryAfter =
            Math.min(
                3600,
                positiveInteger(
                    payload?.retry_after,
                    cooldownSeconds,
                ),
            );

        setCooldownRemaining(
            (current) =>
                Math.max(
                    current,
                    retryAfter,
                ),
        );
    }

    function resetPickupConfirmationState(
        regenerateIdempotencyKey = true,
    ): void {
        pickupConfirmationAbortRef
            .current
            ?.abort();

        pickupConfirmationAbortRef.current =
            null;

        setSelectedStudentIds([]);
        setPickupNotes('');
        setIsConfirmingPickup(false);
        setPickupConfirmation(null);
        setPickupConfirmationError(null);

        if (regenerateIdempotencyKey) {
            setPickupIdempotencyKey(
                generateIdempotencyKey(),
            );
        }
    }

    function clearVerificationResult(): void {
        setAnalysis(null);
        setProbeProgress(null);
        setChallengeProgress(null);
        setVerificationResult(null);
        setVerificationError(null);

        resetPickupConfirmationState();
    }

    function stopCamera(): void {
        analysisAbortRef.current
            ?.abort();

        analysisAbortRef.current =
            null;

        streamRef.current
            ?.getTracks()
            .forEach((track) => {
                track.stop();
            });

        streamRef.current =
            null;

        if (videoRef.current) {
            videoRef.current.srcObject =
                null;
        }

        setCameraReady(false);
        setIsVerifying(false);
        setProbeProgress(null);
        setChallengeProgress(null);
    }

    async function startCamera(): Promise<void> {
        if (
            isStartingCamera
            || isVerifying
            || isConfirmingPickup
        ) {
            return;
        }

        setIsStartingCamera(true);
        setCameraError(null);

        clearVerificationResult();
        stopCamera();

        try {
            if (!window.isSecureContext) {
                throw new Error(
                    'Kamera memerlukan HTTPS atau localhost.',
                );
            }

            if (
                !navigator.mediaDevices
                || !navigator.mediaDevices
                    .getUserMedia
            ) {
                throw new Error(
                    'Browser tidak mendukung akses kamera.',
                );
            }

            const stream =
                await navigator.mediaDevices
                    .getUserMedia({
                        audio: false,

                        video: {
                            facingMode: {
                                ideal:
                                    'user',
                            },

                            width: {
                                ideal:
                                    1280,
                            },

                            height: {
                                ideal:
                                    1280,
                            },

                            frameRate: {
                                ideal:
                                    30,
                            },
                        },
                    });

            const video =
                videoRef.current;

            if (!video) {
                stream
                    .getTracks()
                    .forEach((track) => {
                        track.stop();
                    });

                throw new Error(
                    'Elemen kamera tidak tersedia.',
                );
            }

            streamRef.current =
                stream;

            const videoTrack =
                stream.getVideoTracks()[0];

            videoTrack?.addEventListener(
                'ended',
                () => {
                    setCameraReady(false);
                },
                {
                    once: true,
                },
            );

            video.srcObject =
                stream;

            await video.play();

            setCameraReady(true);
        } catch (error) {
            stopCamera();

            setCameraError(
                cameraErrorMessage(error),
            );
        } finally {
            setIsStartingCamera(false);
        }
    }

    async function requestChallenge(
        signal?: AbortSignal,
    ): Promise<FaceChallengeDefinition> {
        const response =
            await fetch(
                '/gate/face-verification/challenge',
                {
                    method:
                        'POST',

                    credentials:
                        'same-origin',

                    signal,

                    headers: {
                        Accept:
                            'application/json',

                        'Content-Type':
                            'application/json',

                        'X-Requested-With':
                            'XMLHttpRequest',

                        ...csrfHeaders(),
                    },

                    body:
                        JSON.stringify({}),
                },
            );

        const rawPayload =
            await readResponsePayload(
                response,
            );

        const errorPayload =
            asLaravelErrorPayload(
                rawPayload,
            );

        if (!response.ok) {
            if (response.status === 419) {
                throw new Error(
                    'Sesi keamanan telah berakhir. Muat ulang halaman lalu login kembali.',
                );
            }

            if (response.status === 429) {
                applyServerCooldown(
                    errorPayload,
                );

                throw new Error(
                    errorPayload?.message
                    || 'Terlalu banyak permintaan challenge. Tunggu sebelum mencoba kembali.',
                );
            }

            if (response.status === 403) {
                throw new Error(
                    errorPayload?.message
                    || 'Akun tidak memiliki izin membuat challenge gerbang.',
                );
            }

            const messages =
                validationMessages(
                    errorPayload,
                );

            throw new Error(
                messages[0]
                || errorPayload?.message
                || 'Challenge liveness gagal dibuat.',
            );
        }

        if (
            !isChallengeDefinition(
                rawPayload,
            )
        ) {
            throw new Error(
                'Respons challenge dari backend tidak valid.',
            );
        }

        return rawPayload;
    }

    async function submitVerification(
        biometricAnalysis: FaceProbeAnalysis,
        challengeId: string,
        challengeEvidence: FaceChallengeEvidence,
        signal?: AbortSignal,
    ): Promise<VerificationResponse> {
        const response =
            await fetch(
                '/gate/face-verification',
                {
                    method:
                        'POST',

                    credentials:
                        'same-origin',

                    signal,

                    headers: {
                        Accept:
                            'application/json',

                        'Content-Type':
                            'application/json',

                        'X-Requested-With':
                            'XMLHttpRequest',

                        ...csrfHeaders(),
                    },

                    body:
                        JSON.stringify({
                            challenge_id:
                                challengeId,

                            challenge_evidence:
                                challengeEvidence,

                            embedding:
                                biometricAnalysis
                                    .embedding,

                            model_name:
                                biometricAnalysis
                                    .modelName,

                            model_version:
                                biometricAnalysis
                                    .modelVersion,

                            quality_score:
                                biometricAnalysis
                                    .qualityScore,

                            liveness_passed:
                                biometricAnalysis
                                    .livenessPassed,

                            live_score:
                                biometricAnalysis
                                    .liveScore,

                            real_score:
                                biometricAnalysis
                                    .realScore,

                            capture_method:
                                'camera',

                            metadata:
                                biometricAnalysis
                                    .metadata,
                        }),
                },
            );

        const rawPayload =
            await readResponsePayload(
                response,
            );

        const errorPayload =
            asLaravelErrorPayload(
                rawPayload,
            );

        if (!response.ok) {
            if (response.status === 419) {
                throw new Error(
                    'Sesi keamanan telah berakhir. Muat ulang halaman lalu login kembali.',
                );
            }

            if (response.status === 429) {
                applyServerCooldown(
                    errorPayload,
                );

                throw new Error(
                    errorPayload?.message
                    || 'Terlalu banyak percobaan verifikasi. Tunggu sebelum mencoba kembali.',
                );
            }

            if (response.status === 403) {
                throw new Error(
                    errorPayload?.message
                    || 'Akun tidak memiliki izin melakukan verifikasi gerbang.',
                );
            }

            const messages =
                validationMessages(
                    errorPayload,
                );

            throw new Error(
                messages[0]
                || errorPayload?.message
                || 'Backend gagal memproses verifikasi wajah.',
            );
        }

        if (
            !isVerificationResponse(
                rawPayload,
            )
        ) {
            throw new Error(
                'Respons verifikasi dari backend tidak valid.',
            );
        }

        return rawPayload;
    }

    function regeneratePickupRequest(): void {
        setPickupIdempotencyKey(
            generateIdempotencyKey(),
        );

        setPickupConfirmation(null);
        setPickupConfirmationError(null);
    }

    function toggleStudentSelection(
        studentId: number,
    ): void {
        if (
            isConfirmingPickup
            || pickupConfirmation !== null
        ) {
            return;
        }

        setSelectedStudentIds(
            (current) => {
                if (
                    current.includes(
                        studentId,
                    )
                ) {
                    return current.filter(
                        (id) =>
                            id !== studentId,
                    );
                }

                return [
                    ...current,
                    studentId,
                ].sort(
                    (
                        first,
                        second,
                    ) => first - second,
                );
            },
        );

        regeneratePickupRequest();
    }

    function selectAllStudents(): void {
        if (
            !matchedPickupPerson
            || isConfirmingPickup
            || pickupConfirmation !== null
        ) {
            return;
        }

        setSelectedStudentIds(
            matchedPickupPerson
                .students
                .map(
                    (student) =>
                        student.id,
                )
                .sort(
                    (
                        first,
                        second,
                    ) => first - second,
                ),
        );

        regeneratePickupRequest();
    }

    function clearStudentSelection(): void {
        if (
            isConfirmingPickup
            || pickupConfirmation !== null
        ) {
            return;
        }

        setSelectedStudentIds([]);

        regeneratePickupRequest();
    }

    function updatePickupNotes(
        value: string,
    ): void {
        if (
            isConfirmingPickup
            || pickupConfirmation !== null
        ) {
            return;
        }

        setPickupNotes(
            value.slice(
                0,
                1000,
            ),
        );

        regeneratePickupRequest();
    }

    async function confirmPickup(): Promise<void> {
        if (
            isConfirmingPickup
            || pickupConfirmation !== null
        ) {
            return;
        }

        const attemptId =
            verificationResult
                ?.verification_attempt_id;

        if (
            !verificationResult?.matched
            || !isPositiveIntegerValue(
                attemptId,
            )
            || !matchedPickupPerson
        ) {
            setPickupConfirmationError(
                'Hasil verifikasi wajah belum tersedia atau tidak valid.',
            );

            return;
        }

        const studentIds =
            Array.from(
                new Set(
                    selectedStudentIds,
                ),
            ).sort(
                (
                    first,
                    second,
                ) => first - second,
            );

        if (studentIds.length === 0) {
            setPickupConfirmationError(
                'Pilih minimal satu siswa yang akan diserahkan.',
            );

            return;
        }

        const allowedStudentIds =
            new Set(
                matchedPickupPerson
                    .students
                    .map(
                        (student) =>
                            student.id,
                    ),
            );

        if (
            studentIds.some(
                (studentId) =>
                    !allowedStudentIds.has(
                        studentId,
                    ),
            )
        ) {
            setPickupConfirmationError(
                'Daftar siswa terpilih tidak valid. Jalankan verifikasi ulang.',
            );

            return;
        }

        const abortController =
            new AbortController();

        pickupConfirmationAbortRef.current
            ?.abort();

        pickupConfirmationAbortRef.current =
            abortController;

        setIsConfirmingPickup(true);
        setPickupConfirmationError(null);

        try {
            const response =
                await fetch(
                    '/gate/pickup-events',
                    {
                        method:
                            'POST',

                        credentials:
                            'same-origin',

                        signal:
                            abortController.signal,

                        headers: {
                            Accept:
                                'application/json',

                            'Content-Type':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest',

                            ...csrfHeaders(),
                        },

                        body:
                            JSON.stringify({
                                idempotency_key:
                                    pickupIdempotencyKey,

                                face_verification_attempt_id:
                                    attemptId,

                                student_ids:
                                    studentIds,

                                notes:
                                    pickupNotes.trim()
                                        || null,
                            }),
                    },
                );

            const rawPayload =
                await readResponsePayload(
                    response,
                );

            const errorPayload =
                asLaravelErrorPayload(
                    rawPayload,
                );

            if (!response.ok) {
                if (response.status === 419) {
                    throw new Error(
                        'Sesi keamanan telah berakhir. Muat ulang halaman lalu login kembali.',
                    );
                }

                if (response.status === 403) {
                    throw new Error(
                        errorPayload?.message
                        || 'Akun tidak memiliki izin mengonfirmasi penjemputan.',
                    );
                }

                if (response.status === 409) {
                    throw new Error(
                        errorPayload?.message
                        || 'Hasil verifikasi atau kunci idempotency sudah digunakan.',
                    );
                }

                if (response.status === 429) {
                    throw new Error(
                        errorPayload?.message
                        || 'Terlalu banyak permintaan konfirmasi. Tunggu sebelum mencoba kembali.',
                    );
                }

                const messages =
                    validationMessages(
                        errorPayload,
                    );

                throw new Error(
                    messages[0]
                    || errorPayload?.message
                    || 'Konfirmasi penjemputan gagal diproses.',
                );
            }

            if (
                !isPickupConfirmationResponse(
                    rawPayload,
                )
            ) {
                throw new Error(
                    'Respons konfirmasi penjemputan dari backend tidak valid.',
                );
            }

            setPickupConfirmation(
                rawPayload,
            );

            setSelectedStudentIds(
                rawPayload
                    .pickup_event
                    .students
                    .map(
                        (student) =>
                            student.student_id,
                    )
                    .filter(
                        (
                            studentId,
                        ): studentId is number =>
                            isPositiveIntegerValue(
                                studentId,
                            ),
                    ),
            );
        } catch (error) {
            if (
                error instanceof DOMException
                && error.name === 'AbortError'
            ) {
                return;
            }

            setPickupConfirmationError(
                error instanceof Error
                    ? error.message
                    : 'Konfirmasi penjemputan gagal dilakukan.',
            );
        } finally {
            if (
                pickupConfirmationAbortRef
                    .current
                === abortController
            ) {
                pickupConfirmationAbortRef.current =
                    null;
            }

            setIsConfirmingPickup(false);
        }
    }

    async function verifyNow(): Promise<void> {
        const video =
            videoRef.current;

        if (
            !video
            || !cameraReady
            || isVerifying
            || isConfirmingPickup
            || cooldownRemaining > 0
        ) {
            return;
        }

        const abortController =
            new AbortController();

        analysisAbortRef.current =
            abortController;

        setIsVerifying(true);
        setAnalysis(null);
        setProbeProgress(null);
        setChallengeProgress(null);
        setVerificationResult(null);
        setVerificationError(null);

        resetPickupConfirmationState();

        try {
            const challenge =
                await requestChallenge(
                    abortController.signal,
                );

            const configuredMaximumDuration =
                Math.max(
                    5000,
                    Math.min(
                        60000,
                        positiveInteger(
                            challengeConfig
                                .maximum_duration_ms,
                            30000,
                        ),
                    ),
                );

            const reservedProbeTime =
                Math.max(
                    6000,
                    (
                        probeSamples
                        * probeDelayMilliseconds
                    ) + 4000,
                );

            const rawChallengeLifetime =
                Math.trunc(
                    (
                        challenge.expires_in
                        * 1000
                    ) - reservedProbeTime,
                );

            if (
                rawChallengeLifetime
                < 5000
            ) {
                throw new Error(
                    'Masa berlaku challenge terlalu singkat. Muat ulang halaman dan coba kembali.',
                );
            }

            const maximumChallengeDuration =
                Math.min(
                    configuredMaximumDuration,
                    rawChallengeLifetime,
                );

            const challengeEvidence =
                await runFaceChallenge(
                    video,
                    challenge,
                    {
                        blinkMinMs:
                            blinkMinimumMilliseconds,

                        blinkMaxMs:
                            blinkMaximumMilliseconds,

                        headTurnYawDelta:
                            clampNumber(
                                challengeConfig
                                    .head_turn_yaw_delta,
                                0.18,
                                0.03,
                                1.5,
                            ),

                        centerYawTolerance:
                            clampNumber(
                                challengeConfig
                                    .center_yaw_tolerance,
                                0.1,
                                0.02,
                                0.75,
                            ),

                        requiredCenterFrames:
                            Math.min(
                                10,
                                positiveInteger(
                                    challengeConfig
                                        .required_center_frames,
                                    2,
                                ),
                            ),

                        maximumDurationMs:
                            maximumChallengeDuration,

                        frameIntervalMs:
                            challengeFrameInterval,

                        blinkCloseRatio,

                        baselineFrames,

                        signal:
                            abortController.signal,

                        onProgress:
                            setChallengeProgress,
                    },
                );

            setProbeProgress(null);

            const biometricAnalysis =
                await analyzeFaceProbe(
                    video,
                    {
                        sampleCount:
                            probeSamples,

                        delayMilliseconds:
                            probeDelayMilliseconds,

                        minimumQualityScore,

                        signal:
                            abortController.signal,

                        onProgress:
                            setProbeProgress,
                    },
                );

            setAnalysis(
                biometricAnalysis,
            );

            if (
                biometricAnalysis.qualityScore
                < minimumQualityScore
            ) {
                throw new Error(
                    `Rata-rata kualitas wajah ${percentage(
                        biometricAnalysis
                            .qualityScore,
                    )}. Minimal ${percentage(
                        minimumQualityScore,
                    )}.`,
                );
            }

            if (
                biometricAnalysis
                    .minimumFrameQuality
                < minimumFrameQuality
            ) {
                throw new Error(
                    `Kualitas frame terendah ${percentage(
                        biometricAnalysis
                            .minimumFrameQuality,
                    )}. Minimal ${percentage(
                        minimumFrameQuality,
                    )}. Hadapkan wajah ke kamera dan jangan bergerak terlalu cepat.`,
                );
            }

            if (
                !biometricAnalysis
                    .livenessPassed
            ) {
                throw new Error(
                    `${biometricAnalysis.passedLivenessSamples} dari ${biometricAnalysis.sampleCount} sampel liveness lulus. Minimal ${biometricAnalysis.requiredLivenessSamples} sampel harus lulus.`,
                );
            }

            setCooldownRemaining(
                cooldownSeconds,
            );

            const result =
                await submitVerification(
                    biometricAnalysis,
                    challenge.id,
                    challengeEvidence,
                    abortController.signal,
                );

            if (
                result.matched
                && (
                    !isPositiveIntegerValue(
                        result
                            .verification_attempt_id,
                    )
                    || result.pickup_person
                        === null
                )
            ) {
                throw new Error(
                    'Backend tidak mengirim ID attempt atau data penjemput untuk hasil yang cocok.',
                );
            }

            setVerificationResult(
                result,
            );

            setSelectedStudentIds([]);
            setPickupNotes('');
            setPickupConfirmation(null);
            setPickupConfirmationError(null);

            setPickupIdempotencyKey(
                generateIdempotencyKey(),
            );
        } catch (error) {
            if (
                error instanceof DOMException
                && error.name === 'AbortError'
            ) {
                return;
            }

            if (
                error instanceof FaceAnalysisError
                || error
                    instanceof FaceProbeAnalysisError
                || error
                    instanceof FaceChallengeError
                || error instanceof Error
            ) {
                setVerificationError(
                    error.message,
                );
            } else {
                setVerificationError(
                    'Verifikasi wajah gagal dilakukan.',
                );
            }
        } finally {
            if (
                analysisAbortRef.current
                === abortController
            ) {
                analysisAbortRef.current =
                    null;
            }

            setIsVerifying(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Verifikasi Wajah Gerbang" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <p className="text-sm text-muted-foreground">
                        Keamanan Penjemputan
                    </p>

                    <h1 className="text-2xl font-bold tracking-tight">
                        Verifikasi Wajah di Gerbang
                    </h1>

                    <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                        Ikuti challenge kedipan dan
                        gerakan kepala. Setelah wajah
                        cocok, pilih siswa lalu konfirmasi
                        transaksi penjemputan.
                    </p>
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                    <section className="rounded-xl border bg-card p-5 shadow-sm">
                        <div className="relative mx-auto aspect-square max-w-2xl overflow-hidden rounded-xl bg-black">
                            <video
                                ref={videoRef}
                                autoPlay
                                muted
                                playsInline
                                onCanPlay={() => {
                                    if (
                                        streamRef.current
                                    ) {
                                        setCameraReady(
                                            true,
                                        );
                                    }
                                }}
                                className="h-full w-full -scale-x-100 object-cover"
                            />

                            <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                                <div className="h-[72%] w-[58%] rounded-[50%] border-2 border-dashed border-white/90 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]" />
                            </div>

                            {!cameraReady
                                && !cameraError && (
                                    <div className="absolute inset-0 flex items-center justify-center bg-black/70 px-4 text-center text-sm text-white">
                                        Kamera belum aktif
                                    </div>
                                )}

                            {isVerifying && (
                                <div className="absolute inset-x-0 bottom-0 bg-black/80 p-4 text-center text-white">
                                    {challengeProgress
                                        && challengeProgress.phase
                                            !== 'completed' ? (
                                        <>
                                            <p className="text-xs font-medium uppercase tracking-wide text-white/70">
                                                {challengeActionLabel(
                                                    challengeProgress.action,
                                                )}
                                            </p>

                                            <p className="mt-1 text-sm font-semibold">
                                                {
                                                    challengeProgress.message
                                                }
                                            </p>

                                            <p className="mt-1 text-xs text-white/70">
                                                {
                                                    challengeProgress.completedActions
                                                }
                                                /
                                                {
                                                    challengeProgress.totalActions
                                                }{' '}
                                                challenge selesai
                                            </p>
                                        </>
                                    ) : probeProgress ? (
                                        <>
                                            <p className="text-sm font-semibold">
                                                Mengambil Sampel Wajah
                                            </p>

                                            <p className="mt-1 text-xs text-white/75">
                                                Sampel{' '}
                                                {
                                                    probeProgress.acceptedSamples
                                                }
                                                /
                                                {
                                                    probeProgress.requiredSamples
                                                }
                                                {' • '}
                                                Percobaan{' '}
                                                {
                                                    probeProgress.attempt
                                                }
                                                /
                                                {
                                                    probeProgress.maximumAttempts
                                                }
                                            </p>
                                        </>
                                    ) : (
                                        <p className="text-sm font-semibold">
                                            Menyiapkan challenge
                                            dan model biometrik...
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>

                        {cameraError && (
                            <div className="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                                {cameraError}
                            </div>
                        )}

                        <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <button
                                type="button"
                                onClick={() => {
                                    void startCamera();
                                }}
                                disabled={
                                    isStartingCamera
                                    || isVerifying
                                    || isConfirmingPickup
                                }
                                className="inline-flex h-10 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {isStartingCamera
                                    ? 'Membuka...'
                                    : cameraReady
                                      ? 'Mulai Ulang'
                                      : 'Buka Kamera'}
                            </button>

                            <button
                                type="button"
                                onClick={() => {
                                    void verifyNow();
                                }}
                                disabled={
                                    !cameraReady
                                    || isVerifying
                                    || isConfirmingPickup
                                    || cooldownRemaining
                                        > 0
                                }
                                className="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {isVerifying
                                    ? 'Memproses...'
                                    : cooldownRemaining
                                        > 0
                                      ? `Tunggu ${cooldownRemaining}s`
                                      : 'Verifikasi'}
                            </button>

                            <button
                                type="button"
                                onClick={
                                    clearVerificationResult
                                }
                                disabled={
                                    isVerifying
                                    || isConfirmingPickup
                                }
                                className="inline-flex h-10 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Reset Hasil
                            </button>

                            <button
                                type="button"
                                onClick={stopCamera}
                                disabled={
                                    !cameraReady
                                    || isVerifying
                                    || isConfirmingPickup
                                }
                                className="inline-flex h-10 items-center justify-center rounded-md border border-red-300 bg-background px-4 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900 dark:hover:bg-red-950"
                            >
                                Tutup Kamera
                            </button>
                        </div>

                        <div className="mt-4 rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">
                            <p>
                                Pastikan hanya satu wajah
                                terlihat di dalam bingkai.
                            </p>

                            <p className="mt-1">
                                Tatap lurus terlebih dahulu
                                agar baseline wajah dan mata
                                dapat dibaca.
                            </p>

                            <p className="mt-1">
                                Saat memakai kacamata,
                                hindari pantulan cahaya besar
                                pada permukaan lensa.
                            </p>

                            <p className="mt-1">
                                Setelah wajah cocok, pilih
                                hanya siswa yang benar-benar
                                dijemput pada transaksi ini.
                            </p>
                        </div>
                    </section>

                    <div className="space-y-6">
                        <section className="rounded-xl border bg-card p-5 shadow-sm">
                            <h2 className="font-semibold">
                                Keamanan Verifikasi
                            </h2>

                            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Similarity minimum
                                    </dt>

                                    <dd className="mt-1 font-semibold">
                                        {percentage(
                                            verificationConfig
                                                .minimum_similarity,
                                        )}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Margin kandidat minimum
                                    </dt>

                                    <dd className="mt-1 font-semibold">
                                        {percentage(
                                            verificationConfig
                                                .minimum_margin,
                                        )}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Jumlah sampel
                                    </dt>

                                    <dd className="mt-1 font-semibold">
                                        {probeSamples} frame
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Interval challenge
                                    </dt>

                                    <dd className="mt-1 font-semibold">
                                        {
                                            challengeFrameInterval
                                        }{' '}
                                        ms
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Rentang kedipan
                                    </dt>

                                    <dd className="mt-1 font-semibold">
                                        {
                                            blinkMinimumMilliseconds
                                        }
                                        –
                                        {
                                            blinkMaximumMilliseconds
                                        }{' '}
                                        ms
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Cooldown
                                    </dt>

                                    <dd className="mt-1 font-semibold">
                                        {cooldownSeconds} detik
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        {challengeProgress && (
                            <section className="rounded-xl border bg-card p-5 shadow-sm">
                                <h2 className="font-semibold">
                                    Challenge Liveness
                                </h2>

                                <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Fase
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {challengePhaseLabel(
                                                challengeProgress.phase,
                                            )}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">
                                            Aksi
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {challengeActionLabel(
                                                challengeProgress.action,
                                            )}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">
                                            Progres
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {
                                                challengeProgress.completedActions
                                            }
                                            /
                                            {
                                                challengeProgress.totalActions
                                            }
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">
                                            Durasi
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {(
                                                challengeProgress.elapsedMs
                                                / 1000
                                            ).toFixed(1)}{' '}
                                            detik
                                        </dd>
                                    </div>

                                    <div className="sm:col-span-2 xl:col-span-1">
                                        <dt className="text-muted-foreground">
                                            Instruksi
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {
                                                challengeProgress.message
                                            }
                                        </dd>
                                    </div>
                                </dl>
                            </section>
                        )}

                        <section className="rounded-xl border bg-card p-5 shadow-sm">
                            <h2 className="font-semibold">
                                Analisis Kamera
                            </h2>

                            {analysis ? (
                                <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Rata-rata kualitas
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {percentage(
                                                analysis
                                                    .qualityScore,
                                            )}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">
                                            Kualitas frame terendah
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {percentage(
                                                analysis
                                                    .minimumFrameQuality,
                                            )}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">
                                            Live / Real
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {percentage(
                                                analysis
                                                    .liveScore,
                                            )}{' '}
                                            /{' '}
                                            {percentage(
                                                analysis
                                                    .realScore,
                                            )}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">
                                            Sampel liveness
                                        </dt>

                                        <dd
                                            className={`mt-1 font-semibold ${
                                                analysis
                                                    .livenessPassed
                                                    ? 'text-emerald-600'
                                                    : 'text-red-600'
                                            }`}
                                        >
                                            {
                                                analysis
                                                    .passedLivenessSamples
                                            }
                                            /
                                            {
                                                analysis
                                                    .sampleCount
                                            }{' '}
                                            lulus
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">
                                            Dimensi embedding
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {
                                                analysis
                                                    .embeddingDimension
                                            }
                                        </dd>
                                    </div>
                                </dl>
                            ) : (
                                <p className="mt-4 text-sm text-muted-foreground">
                                    Belum ada hasil analisis.
                                </p>
                            )}
                        </section>

                        {verificationError && (
                            <section className="rounded-xl border border-red-300 bg-red-50 p-5 text-red-700 shadow-sm dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                                <h2 className="font-semibold">
                                    Verifikasi Gagal
                                </h2>

                                <p className="mt-2 text-sm">
                                    {verificationError}
                                </p>
                            </section>
                        )}

                        {verificationResult && (
                            <section
                                className={`rounded-xl border p-5 shadow-sm ${
                                    verificationResult
                                        .matched
                                        ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950'
                                        : 'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950'
                                }`}
                            >
                                <h2 className="font-semibold">
                                    {verificationResultTitle(
                                        verificationResult,
                                    )}
                                </h2>

                                <p className="mt-2 text-sm">
                                    {
                                        verificationResult
                                            .message
                                    }
                                </p>

                                <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Similarity
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {percentage(
                                                verificationResult
                                                    .similarity,
                                            )}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">
                                            Batas minimal
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {percentage(
                                                verificationResult
                                                    .threshold,
                                            )}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-muted-foreground">
                                            Margin kandidat
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {percentage(
                                                verificationResult
                                                    .margin,
                                            )}
                                        </dd>
                                    </div>

                                    {verificationResult
                                        .verification_attempt_id && (
                                        <div>
                                            <dt className="text-muted-foreground">
                                                ID verifikasi
                                            </dt>

                                            <dd className="mt-1 font-semibold">
                                                #
                                                {
                                                    verificationResult
                                                        .verification_attempt_id
                                                }
                                            </dd>
                                        </div>
                                    )}
                                </dl>
                            </section>
                        )}

                        {matchedPickupPerson && (
                            <section className="rounded-xl border bg-card p-5 shadow-sm">
                                <div className="flex items-center gap-4">
                                    {matchedPickupPerson.photo_url ? (
                                        <img
                                            src={
                                                matchedPickupPerson
                                                    .photo_url
                                            }
                                            alt={
                                                matchedPickupPerson
                                                    .full_name
                                            }
                                            className="h-20 w-20 rounded-xl border object-cover"
                                        />
                                    ) : (
                                        <div className="flex h-20 w-20 items-center justify-center rounded-xl border bg-muted text-xs text-muted-foreground">
                                            Tanpa Foto
                                        </div>
                                    )}

                                    <div className="min-w-0 flex-1">
                                        <p className="text-xs text-muted-foreground">
                                            Penjemput terverifikasi
                                        </p>

                                        <h2 className="truncate text-lg font-bold">
                                            {
                                                matchedPickupPerson
                                                    .full_name
                                            }
                                        </h2>

                                        <p className="mt-1 text-sm">
                                            {matchedPickupPerson.phone
                                                || 'Nomor telepon tidak tersedia'}
                                        </p>

                                        <Link
                                            href={`/pickup-persons/${matchedPickupPerson.id}`}
                                            className="mt-2 inline-flex text-sm font-medium text-primary hover:underline"
                                        >
                                            Lihat detail penjemput
                                        </Link>
                                    </div>
                                </div>

                                <div className="mt-5 border-t pt-4">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <h3 className="text-sm font-semibold">
                                                Siswa yang Akan Diserahkan
                                            </h3>

                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Pilih hanya siswa
                                                yang benar-benar
                                                dijemput saat ini.
                                            </p>
                                        </div>

                                        {matchedPickupPerson
                                            .students
                                            .length > 0
                                            && !pickupConfirmation && (
                                                <div className="flex gap-3">
                                                    <button
                                                        type="button"
                                                        onClick={
                                                            selectAllStudents
                                                        }
                                                        disabled={
                                                            isConfirmingPickup
                                                        }
                                                        className="text-xs font-medium text-primary hover:underline disabled:opacity-50"
                                                    >
                                                        Pilih Semua
                                                    </button>

                                                    <button
                                                        type="button"
                                                        onClick={
                                                            clearStudentSelection
                                                        }
                                                        disabled={
                                                            isConfirmingPickup
                                                        }
                                                        className="text-xs font-medium text-muted-foreground hover:underline disabled:opacity-50"
                                                    >
                                                        Kosongkan
                                                    </button>
                                                </div>
                                            )}
                                    </div>

                                    {matchedPickupPerson
                                        .students
                                        .length === 0 ? (
                                        <div className="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                                            Tidak ada relasi siswa
                                            aktif yang berlaku hari
                                            ini. Lakukan verifikasi
                                            manual sebelum
                                            mengizinkan penjemputan.
                                        </div>
                                    ) : (
                                        <div className="mt-3 space-y-2">
                                            {matchedPickupPerson
                                                .students
                                                .map(
                                                    (
                                                        student,
                                                    ) => {
                                                        const checked =
                                                            selectedStudentIds
                                                                .includes(
                                                                    student.id,
                                                                );

                                                        return (
                                                            <label
                                                                key={
                                                                    student.id
                                                                }
                                                                className={`flex gap-3 rounded-lg border p-3 transition-colors ${
                                                                    checked
                                                                        ? 'border-primary bg-primary/5'
                                                                        : 'bg-muted/30'
                                                                } ${
                                                                    pickupConfirmation
                                                                        ? 'cursor-default'
                                                                        : 'cursor-pointer hover:bg-muted/60'
                                                                }`}
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    checked={
                                                                        checked
                                                                    }
                                                                    disabled={
                                                                        isConfirmingPickup
                                                                        || pickupConfirmation
                                                                            !== null
                                                                    }
                                                                    onChange={() => {
                                                                        toggleStudentSelection(
                                                                            student.id,
                                                                        );
                                                                    }}
                                                                    className="mt-1 h-4 w-4 rounded border-border"
                                                                />

                                                                <div className="min-w-0 flex-1">
                                                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                                                        <div>
                                                                            <p className="font-medium">
                                                                                {
                                                                                    student.full_name
                                                                                }
                                                                            </p>

                                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                                NIS:{' '}
                                                                                {student.student_number
                                                                                    || '-'}
                                                                                {' • '}
                                                                                {student.class_name
                                                                                    || 'Tanpa kelas'}
                                                                            </p>
                                                                        </div>

                                                                        {student.is_primary && (
                                                                            <span className="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                                                                                Utama
                                                                            </span>
                                                                        )}
                                                                    </div>

                                                                    <p className="mt-2 text-xs">
                                                                        Hubungan:{' '}
                                                                        {relationshipLabel(
                                                                            student.relationship_type,
                                                                        )}
                                                                    </p>

                                                                    {student.academic_year && (
                                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                                            Tahun ajaran:{' '}
                                                                            {
                                                                                student.academic_year
                                                                            }
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            </label>
                                                        );
                                                    },
                                                )}
                                        </div>
                                    )}

                                    {matchedPickupPerson
                                        .students
                                        .length > 0
                                        && !pickupConfirmation && (
                                            <div className="mt-4 space-y-3 border-t pt-4">
                                                <div>
                                                    <label
                                                        htmlFor="pickup-notes"
                                                        className="text-sm font-medium"
                                                    >
                                                        Catatan Penjemputan
                                                    </label>

                                                    <textarea
                                                        id="pickup-notes"
                                                        value={
                                                            pickupNotes
                                                        }
                                                        onChange={(
                                                            event,
                                                        ) => {
                                                            updatePickupNotes(
                                                                event.target.value,
                                                            );
                                                        }}
                                                        disabled={
                                                            isConfirmingPickup
                                                        }
                                                        maxLength={
                                                            1000
                                                        }
                                                        rows={3}
                                                        placeholder="Catatan opsional untuk petugas..."
                                                        className="mt-2 w-full rounded-md border bg-background px-3 py-2 text-sm outline-none transition focus:border-primary disabled:cursor-not-allowed disabled:opacity-60"
                                                    />

                                                    <p className="mt-1 text-right text-xs text-muted-foreground">
                                                        {
                                                            pickupNotes.length
                                                        }
                                                        /1000
                                                    </p>
                                                </div>

                                                {pickupConfirmationError && (
                                                    <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                                                        {
                                                            pickupConfirmationError
                                                        }
                                                    </div>
                                                )}

                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        void confirmPickup();
                                                    }}
                                                    disabled={
                                                        selectedStudentIds
                                                            .length
                                                            === 0
                                                        || isConfirmingPickup
                                                        || !isPositiveIntegerValue(
                                                            verificationResult
                                                                ?.verification_attempt_id,
                                                        )
                                                    }
                                                    className="inline-flex h-11 w-full items-center justify-center rounded-md bg-emerald-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    {isConfirmingPickup
                                                        ? 'Menyimpan Konfirmasi...'
                                                        : selectedStudentIds
                                                              .length
                                                              === 0
                                                          ? 'Pilih Siswa Terlebih Dahulu'
                                                          : `Konfirmasi Penjemputan (${selectedStudentIds.length} Siswa)`}
                                                </button>

                                                <p className="text-center text-xs text-muted-foreground">
                                                    Hasil verifikasi
                                                    ini hanya dapat
                                                    digunakan untuk
                                                    satu transaksi.
                                                </p>
                                            </div>
                                        )}
                                </div>
                            </section>
                        )}

                        {pickupConfirmation && (
                            <section className="rounded-xl border border-emerald-300 bg-emerald-50 p-5 shadow-sm dark:border-emerald-900 dark:bg-emerald-950">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                                            Transaksi Berhasil
                                        </p>

                                        <h2 className="mt-1 text-lg font-bold text-emerald-800 dark:text-emerald-200">
                                            Penjemputan Dikonfirmasi
                                        </h2>
                                    </div>

                                    <span className="rounded-full bg-emerald-600 px-3 py-1 text-xs font-semibold text-white">
                                        {
                                            pickupConfirmation
                                                .pickup_event
                                                .status_label
                                        }
                                    </span>
                                </div>

                                <p className="mt-3 text-sm text-emerald-800 dark:text-emerald-200">
                                    {
                                        pickupConfirmation.message
                                    }
                                </p>

                                {pickupConfirmation.replayed && (
                                    <p className="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                        Respons ini berasal dari
                                        request idempotency yang
                                        sebelumnya sudah diproses.
                                    </p>
                                )}

                                <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-emerald-700/80 dark:text-emerald-300/80">
                                            ID Transaksi
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            #
                                            {
                                                pickupConfirmation
                                                    .pickup_event
                                                    .id
                                            }
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-emerald-700/80 dark:text-emerald-300/80">
                                            Waktu Konfirmasi
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {formatDateTime(
                                                pickupConfirmation
                                                    .pickup_event
                                                    .confirmed_at,
                                            )}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-emerald-700/80 dark:text-emerald-300/80">
                                            Metode
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {
                                                pickupConfirmation
                                                    .pickup_event
                                                    .verification_method_label
                                            }
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-emerald-700/80 dark:text-emerald-300/80">
                                            Petugas
                                        </dt>

                                        <dd className="mt-1 font-semibold">
                                            {pickupConfirmation
                                                .pickup_event
                                                .confirmed_by
                                                ?.name
                                                || '-'}
                                        </dd>
                                    </div>
                                </dl>

                                <div className="mt-4 border-t border-emerald-200 pt-4 dark:border-emerald-900">
                                    <h3 className="text-sm font-semibold">
                                        Siswa Diserahkan
                                    </h3>

                                    <div className="mt-3 space-y-2">
                                        {pickupConfirmation
                                            .pickup_event
                                            .students
                                            .map(
                                                (
                                                    student,
                                                ) => (
                                                    <div
                                                        key={
                                                            student.id
                                                        }
                                                        className="rounded-md border border-emerald-200 bg-white/60 px-3 py-2 text-sm dark:border-emerald-900 dark:bg-black/10"
                                                    >
                                                        <p className="font-medium">
                                                            {
                                                                student.student_name
                                                            }
                                                        </p>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {student.student_number
                                                                || '-'}
                                                            {' • '}
                                                            {student.class_name
                                                                || 'Tanpa kelas'}
                                                            {' • '}
                                                            {
                                                                student.status_label
                                                            }
                                                        </p>
                                                    </div>
                                                ),
                                            )}
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    onClick={
                                        clearVerificationResult
                                    }
                                    className="mt-5 inline-flex h-10 w-full items-center justify-center rounded-md border border-emerald-400 bg-white/70 px-4 text-sm font-semibold text-emerald-700 transition hover:bg-white dark:bg-black/10 dark:text-emerald-300"
                                >
                                    Proses Penjemput Berikutnya
                                </button>
                            </section>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}