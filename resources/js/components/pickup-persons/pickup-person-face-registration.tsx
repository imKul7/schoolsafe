import {
    analyzeFace,
    FaceAnalysisError,
    type FaceBiometricAnalysis,
} from '@/lib/biometrics/analyze-face';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

export interface PickupPersonFaceProfile {
    status: string;
    embedding_dimension: number | null;
    model_name: string | null;
    model_version: string | null;
    quality_score: number | null;
    liveness_passed: boolean;
    capture_method: string | null;
    registration_revision: number;
    consent_version: string | null;
    consented_at: string | null;
    registered_at: string | null;
    invalidated_at: string | null;
    revoked_at: string | null;
}

interface BiometricConfig {
    minimum_quality_score: number;
    consent_version: string;
}

interface PickupPersonFaceRegistrationProps {
    pickupPersonId: number;
    pickupPersonName: string;
    currentPhotoUrl: string | null;
    faceStatus: string;
    faceProfile: PickupPersonFaceProfile | null;
    canManageFace: boolean;
    biometricConfig: BiometricConfig;
}

interface InertiaErrors {
    [key: string]: string;
}

function percentage(value: number | null): string {
    if (
        value === null
        || !Number.isFinite(value)
    ) {
        return '-';
    }

    return `${Math.round(value * 100)}%`;
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function faceStatusLabel(status: string): string {
    switch (status) {
        case 'registered':
            return 'Terdaftar';

        case 'needs_update':
            return 'Perlu Registrasi Ulang';

        default:
            return 'Belum Terdaftar';
    }
}

function profileStatusLabel(status: string): string {
    switch (status) {
        case 'registered':
            return 'Aktif';

        case 'needs_update':
            return 'Perlu Diperbarui';

        case 'revoked':
            return 'Dicabut';

        default:
            return status || '-';
    }
}

function loadImage(
    sourceUrl: string,
): Promise<HTMLImageElement> {
    return new Promise(
        (resolve, reject) => {
            const image = new Image();

            image.decoding = 'async';

            image.onload = () => {
                resolve(image);
            };

            image.onerror = () => {
                reject(
                    new Error(
                        'Foto gagal dimuat. Muat ulang halaman lalu coba kembali.',
                    ),
                );
            };

            const separator =
                sourceUrl.includes('?') ? '&' : '?';

            image.src =
                `${sourceUrl}${separator}biometric=${Date.now()}`;
        },
    );
}

function errorMessages(
    errors: InertiaErrors,
): string[] {
    return Object.values(errors)
        .map((message) => String(message))
        .filter((message) => message.trim() !== '');
}

export default function PickupPersonFaceRegistration({
    pickupPersonId,
    pickupPersonName,
    currentPhotoUrl,
    faceStatus,
    faceProfile,
    canManageFace,
    biometricConfig,
}: PickupPersonFaceRegistrationProps) {
    const [analysis, setAnalysis] =
        useState<FaceBiometricAnalysis | null>(
            null,
        );

    const [analysisError, setAnalysisError] =
        useState<string | null>(null);

    const [serverErrors, setServerErrors] =
        useState<string[]>([]);

    const [consentConfirmed, setConsentConfirmed] =
        useState(false);

    const [isAnalyzing, setIsAnalyzing] =
        useState(false);

    const [isRegistering, setIsRegistering] =
        useState(false);

    const [isRevoking, setIsRevoking] =
        useState(false);

    useEffect(() => {
        setAnalysis(null);
        setAnalysisError(null);
        setServerErrors([]);
        setConsentConfirmed(false);
    }, [currentPhotoUrl]);

    const qualityPassed = useMemo(
        (): boolean =>
            Boolean(
                analysis
                && analysis.qualityScore
                    >= biometricConfig.minimum_quality_score,
            ),
        [
            analysis,
            biometricConfig.minimum_quality_score,
        ],
    );

    const registrationReady =
        Boolean(analysis)
        && qualityPassed
        && Boolean(analysis?.livenessPassed)
        && consentConfirmed
        && !isRegistering
        && !isRevoking;

    const profileCanBeRevoked =
        Boolean(faceProfile)
        && faceProfile?.status !== 'revoked';

    const analyzeStoredPhoto =
        async (): Promise<void> => {
            if (
                !currentPhotoUrl
                || isAnalyzing
            ) {
                return;
            }

            setIsAnalyzing(true);
            setAnalysis(null);
            setAnalysisError(null);
            setServerErrors([]);
            setConsentConfirmed(false);

            try {
                const image =
                    await loadImage(
                        currentPhotoUrl,
                    );

                const result =
                    await analyzeFace(image);

                setAnalysis(result);
            } catch (error) {
                if (
                    error instanceof FaceAnalysisError
                    || error instanceof Error
                ) {
                    setAnalysisError(error.message);
                } else {
                    setAnalysisError(
                        'Analisis wajah gagal dilakukan.',
                    );
                }
            } finally {
                setIsAnalyzing(false);
            }
        };

    const registerFace = (): void => {
        if (
            !analysis
            || !registrationReady
        ) {
            return;
        }

        setServerErrors([]);

        router.post(
            `/pickup-persons/${pickupPersonId}/face/register`,
            {
                embedding:
                    analysis.embedding,

                model_name:
                    analysis.modelName,

                model_version:
                    analysis.modelVersion,

                quality_score:
                    analysis.qualityScore,

                liveness_passed:
                    analysis.livenessPassed,

                capture_method:
                    'upload',

                consent_confirmed:
                    consentConfirmed,

                metadata: {
                    ...analysis.metadata,
                },
            },
            {
                preserveScroll: true,

                onStart: () => {
                    setIsRegistering(true);
                },

                onSuccess: () => {
                    setAnalysis(null);
                    setAnalysisError(null);
                    setServerErrors([]);
                    setConsentConfirmed(false);
                },

                onError: (errors) => {
                    setServerErrors(
                        errorMessages(
                            errors as InertiaErrors,
                        ),
                    );
                },

                onFinish: () => {
                    setIsRegistering(false);
                },
            },
        );
    };

    const revokeFace = (): void => {
        if (
            !profileCanBeRevoked
            || isRevoking
        ) {
            return;
        }

        const confirmed = window.confirm(
            `Cabut registrasi wajah ${pickupPersonName}? Data embedding akan dihapus.`,
        );

        if (!confirmed) {
            return;
        }

        setServerErrors([]);

        router.delete(
            `/pickup-persons/${pickupPersonId}/face`,
            {
                preserveScroll: true,

                onStart: () => {
                    setIsRevoking(true);
                },

                onSuccess: () => {
                    setAnalysis(null);
                    setAnalysisError(null);
                    setServerErrors([]);
                    setConsentConfirmed(false);
                },

                onError: (errors) => {
                    setServerErrors(
                        errorMessages(
                            errors as InertiaErrors,
                        ),
                    );
                },

                onFinish: () => {
                    setIsRevoking(false);
                },
            },
        );
    };

    return (
        <section className="rounded-xl border bg-card p-5 shadow-sm">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="font-semibold">
                        Registrasi Biometrik
                    </h2>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Analisis foto tersimpan sebelum
                        mendaftarkan descriptor wajah.
                    </p>
                </div>

                <span className="inline-flex w-fit rounded-full bg-muted px-3 py-1 text-xs font-semibold">
                    {faceStatusLabel(faceStatus)}
                </span>
            </div>

            {!canManageFace && (
                <div className="mt-4 rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                    Registrasi biometrik hanya dapat
                    dikelola administrator sekolah.
                </div>
            )}

            {!currentPhotoUrl && (
                <div className="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
                    Simpan foto penjemput terlebih dahulu
                    sebelum melakukan registrasi wajah.
                </div>
            )}

            {faceProfile && (
                <div className="mt-5 rounded-lg border bg-muted/30 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-semibold">
                            Profil Tersimpan
                        </p>

                        <span className="rounded-full border bg-background px-2.5 py-1 text-xs font-medium">
                            {profileStatusLabel(
                                faceProfile.status,
                            )}
                        </span>
                    </div>

                    <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">
                                Model
                            </dt>

                            <dd className="mt-1 font-medium">
                                {faceProfile.model_name || '-'}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-muted-foreground">
                                Versi model
                            </dt>

                            <dd className="mt-1 font-medium">
                                {faceProfile.model_version || '-'}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-muted-foreground">
                                Dimensi embedding
                            </dt>

                            <dd className="mt-1 font-medium">
                                {faceProfile.embedding_dimension ??
                                    '-'}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-muted-foreground">
                                Kualitas
                            </dt>

                            <dd className="mt-1 font-medium">
                                {percentage(
                                    faceProfile.quality_score,
                                )}
                            </dd>
                        </div>

                        <div>
                            <dt className="text-muted-foreground">
                                Revisi registrasi
                            </dt>

                            <dd className="mt-1 font-medium">
                                {
                                    faceProfile.registration_revision
                                }
                            </dd>
                        </div>

                        <div>
                            <dt className="text-muted-foreground">
                                Terdaftar pada
                            </dt>

                            <dd className="mt-1 font-medium">
                                {formatDateTime(
                                    faceProfile.registered_at,
                                )}
                            </dd>
                        </div>
                    </dl>
                </div>
            )}

            {canManageFace && (
                <div className="mt-5 space-y-4">
                    <button
                        type="button"
                        onClick={() => {
                            void analyzeStoredPhoto();
                        }}
                        disabled={
                            !currentPhotoUrl
                            || isAnalyzing
                            || isRegistering
                            || isRevoking
                        }
                        className="inline-flex h-10 w-full items-center justify-center rounded-md border bg-background px-4 text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {isAnalyzing
                            ? 'Memuat model dan menganalisis...'
                            : analysis
                              ? 'Analisis Ulang Foto'
                              : 'Analisis Foto Wajah'}
                    </button>

                    {analysisError && (
                        <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                            {analysisError}
                        </div>
                    )}

                    {serverErrors.length > 0 && (
                        <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                            {serverErrors.map(
                                (message, index) => (
                                    <p key={`${message}-${index}`}>
                                        {message}
                                    </p>
                                ),
                            )}
                        </div>
                    )}

                    {analysis && (
                        <div className="space-y-4 rounded-lg border p-4">
                            <div>
                                <h3 className="text-sm font-semibold">
                                    Hasil Analisis
                                </h3>

                                <p className="mt-1 text-xs text-muted-foreground">
                                    Periksa seluruh hasil sebelum
                                    menyimpan registrasi.
                                </p>
                            </div>

                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Kualitas wajah
                                    </dt>

                                    <dd
                                        className={`mt-1 font-semibold ${
                                            qualityPassed
                                                ? 'text-emerald-600'
                                                : 'text-red-600'
                                        }`}
                                    >
                                        {percentage(
                                            analysis.qualityScore,
                                        )}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Minimal kualitas
                                    </dt>

                                    <dd className="mt-1 font-semibold">
                                        {percentage(
                                            biometricConfig.minimum_quality_score,
                                        )}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Liveness
                                    </dt>

                                    <dd
                                        className={`mt-1 font-semibold ${
                                            analysis.livenessPassed
                                                ? 'text-emerald-600'
                                                : 'text-red-600'
                                        }`}
                                    >
                                        {analysis.livenessPassed
                                            ? 'Lulus'
                                            : 'Tidak Lulus'}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Live / Real
                                    </dt>

                                    <dd className="mt-1 font-semibold">
                                        {percentage(
                                            analysis.liveScore,
                                        )}{' '}
                                        /{' '}
                                        {percentage(
                                            analysis.realScore,
                                        )}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Model
                                    </dt>

                                    <dd className="mt-1 break-all font-semibold">
                                        {analysis.modelName}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-muted-foreground">
                                        Dimensi embedding
                                    </dt>

                                    <dd className="mt-1 font-semibold">
                                        {
                                            analysis.embeddingDimension
                                        }
                                    </dd>
                                </div>
                            </dl>

                            {!qualityPassed && (
                                <p className="rounded-md bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">
                                    Kualitas belum memenuhi batas
                                    minimum. Gunakan foto yang lebih
                                    terang, tajam, dan wajah lebih
                                    dekat.
                                </p>
                            )}

                            {!analysis.livenessPassed && (
                                <p className="rounded-md bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">
                                    Pemeriksaan liveness atau
                                    anti-spoofing belum lulus. Ambil
                                    ulang foto langsung dari kamera
                                    dengan wajah nyata.
                                </p>
                            )}

                            <label className="flex items-start gap-3 rounded-md border bg-muted/30 p-3">
                                <input
                                    type="checkbox"
                                    checked={
                                        consentConfirmed
                                    }
                                    onChange={(event) => {
                                        setConsentConfirmed(
                                            event.target.checked,
                                        );
                                    }}
                                    disabled={
                                        isRegistering
                                        || isRevoking
                                    }
                                    className="mt-1 h-4 w-4 rounded border"
                                />

                                <span className="text-sm">
                                    Saya telah memperoleh
                                    persetujuan penjemput untuk
                                    penggunaan data biometrik sesuai
                                    kebijakan sekolah versi{' '}
                                    <strong>
                                        {
                                            biometricConfig.consent_version
                                        }
                                    </strong>
                                    .
                                </span>
                            </label>

                            <button
                                type="button"
                                onClick={registerFace}
                                disabled={!registrationReady}
                                className="inline-flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {isRegistering
                                    ? 'Menyimpan Registrasi...'
                                    : faceStatus === 'registered'
                                      ? 'Registrasi Ulang Wajah'
                                      : 'Daftarkan Wajah'}
                            </button>
                        </div>
                    )}

                    {profileCanBeRevoked && (
                        <button
                            type="button"
                            onClick={revokeFace}
                            disabled={
                                isRevoking
                                || isRegistering
                                || isAnalyzing
                            }
                            className="inline-flex h-10 w-full items-center justify-center rounded-md border border-red-300 bg-background px-4 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900 dark:hover:bg-red-950"
                        >
                            {isRevoking
                                ? 'Mencabut Registrasi...'
                                : 'Cabut Registrasi Wajah'}
                        </button>
                    )}
                </div>
            )}
        </section>
    );
}
