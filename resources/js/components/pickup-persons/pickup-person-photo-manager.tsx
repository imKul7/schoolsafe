import { router, useForm } from '@inertiajs/react';
import type { ChangeEvent, FormEvent } from 'react';
import {
    useEffect,
    useRef,
    useState,
} from 'react';

interface PhotoForm {
    [key: string]: File | null;
    photo: File | null;
}

interface PickupPersonPhotoManagerProps {
    pickupPersonId: number;
    pickupPersonName: string;
    initials: string;
    currentPhotoUrl: string | null;
    faceStatus: string;
    canManage: boolean;
}

type CameraFacingMode = 'user' | 'environment';

const MAX_PHOTO_SIZE = 5 * 1024 * 1024;

const ALLOWED_PHOTO_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

function faceStatusLabel(status: string): string {
    switch (status) {
        case 'registered':
            return 'Wajah Terdaftar';

        case 'needs_update':
            return 'Perlu Registrasi Ulang';

        default:
            return 'Belum Terdaftar';
    }
}

function faceStatusClass(status: string): string {
    switch (status) {
        case 'registered':
            return [
                'bg-emerald-100',
                'text-emerald-700',
                'dark:bg-emerald-950',
                'dark:text-emerald-300',
            ].join(' ');

        case 'needs_update':
            return [
                'bg-amber-100',
                'text-amber-700',
                'dark:bg-amber-950',
                'dark:text-amber-300',
            ].join(' ');

        default:
            return [
                'bg-slate-100',
                'text-slate-700',
                'dark:bg-slate-800',
                'dark:text-slate-300',
            ].join(' ');
    }
}

function cameraErrorMessage(error: unknown): string {
    if (!(error instanceof DOMException)) {
        return 'Kamera gagal dibuka. Periksa izin kamera pada browser.';
    }

    switch (error.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'Izin kamera ditolak. Izinkan akses kamera melalui pengaturan browser.';

        case 'NotFoundError':
        case 'DevicesNotFoundError':
            return 'Kamera tidak ditemukan pada perangkat ini.';

        case 'NotReadableError':
        case 'TrackStartError':
            return 'Kamera sedang digunakan oleh aplikasi lain atau tidak dapat diakses.';

        case 'OverconstrainedError':
        case 'ConstraintNotSatisfiedError':
            return 'Kamera tidak mendukung pengaturan yang diminta.';

        case 'AbortError':
            return 'Pembukaan kamera dibatalkan oleh perangkat.';

        default:
            return 'Kamera gagal dibuka. Periksa izin dan kondisi kamera perangkat.';
    }
}

function createCameraFileName(
    pickupPersonId: number,
): string {
    return [
        'pickup-person',
        pickupPersonId,
        Date.now(),
    ].join('-') + '.jpg';
}

export default function PickupPersonPhotoManager({
    pickupPersonId,
    pickupPersonName,
    initials,
    currentPhotoUrl,
    faceStatus,
    canManage,
}: PickupPersonPhotoManagerProps) {
    const fileInputRef =
        useRef<HTMLInputElement | null>(null);

    const videoRef =
        useRef<HTMLVideoElement | null>(null);

    const canvasRef =
        useRef<HTMLCanvasElement | null>(null);

    const cameraStreamRef =
        useRef<MediaStream | null>(null);

    const [selectedPreviewUrl, setSelectedPreviewUrl] =
        useState<string | null>(null);

    const [cameraOpen, setCameraOpen] =
        useState(false);

    const [cameraReady, setCameraReady] =
        useState(false);

    const [cameraError, setCameraError] =
        useState<string | null>(null);

    const [cameraFacingMode, setCameraFacingMode] =
        useState<CameraFacingMode>('user');

    const [isDeleting, setIsDeleting] =
        useState(false);

    const photoForm = useForm<PhotoForm>({
        photo: null,
    });

    const displayedPhotoUrl =
        selectedPreviewUrl ?? currentPhotoUrl;

    useEffect(() => {
        const photo = photoForm.data.photo;

        if (!photo) {
            setSelectedPreviewUrl(null);

            return;
        }

        const objectUrl = URL.createObjectURL(photo);

        setSelectedPreviewUrl(objectUrl);

        return () => {
            URL.revokeObjectURL(objectUrl);
        };
    }, [photoForm.data.photo]);

    useEffect(() => {
        if (!cameraOpen) {
            stopCamera();

            return;
        }

        void startCamera();

        return () => {
            stopCamera();
        };
    }, [cameraOpen, cameraFacingMode]);

    function stopCamera(): void {
        cameraStreamRef.current
            ?.getTracks()
            .forEach((track) => {
                track.stop();
            });

        cameraStreamRef.current = null;

        if (videoRef.current) {
            videoRef.current.srcObject = null;
        }

        setCameraReady(false);
    }

    async function startCamera(): Promise<void> {
        setCameraError(null);
        setCameraReady(false);

        if (
            !navigator.mediaDevices
            || !navigator.mediaDevices.getUserMedia
        ) {
            setCameraError(
                'Browser ini tidak mendukung akses kamera.',
            );

            return;
        }

        stopCamera();

        try {
            const stream =
                await navigator.mediaDevices.getUserMedia({
                    audio: false,

                    video: {
                        facingMode: {
                            ideal: cameraFacingMode,
                        },

                        width: {
                            ideal: 1280,
                        },

                        height: {
                            ideal: 1280,
                        },
                    },
                });

            cameraStreamRef.current = stream;

            const video = videoRef.current;

            if (!video) {
                stopCamera();

                return;
            }

            video.srcObject = stream;

            await video.play();
        } catch (error) {
            stopCamera();

            setCameraError(
                cameraErrorMessage(error),
            );
        }
    }

    function validatePhotoFile(
        file: File,
    ): string | null {
        if (!ALLOWED_PHOTO_TYPES.includes(file.type)) {
            return 'Foto harus berformat JPG, JPEG, PNG, atau WEBP.';
        }

        if (file.size > MAX_PHOTO_SIZE) {
            return 'Ukuran foto maksimal 5 MB.';
        }

        return null;
    }

    function handlePhotoChange(
        event: ChangeEvent<HTMLInputElement>,
    ): void {
        const file =
            event.target.files?.[0] ?? null;

        photoForm.clearErrors('photo');

        if (!file) {
            photoForm.setData('photo', null);

            return;
        }

        const validationError =
            validatePhotoFile(file);

        if (validationError) {
            photoForm.setError(
                'photo',
                validationError,
            );

            event.target.value = '';

            photoForm.setData('photo', null);

            return;
        }

        photoForm.setData('photo', file);
    }

    function openCamera(): void {
        photoForm.clearErrors('photo');
        setCameraError(null);
        setCameraOpen(true);
    }

    function closeCamera(): void {
        setCameraOpen(false);
        stopCamera();
    }

    function switchCamera(): void {
        setCameraReady(false);

        setCameraFacingMode((currentMode) =>
            currentMode === 'user'
                ? 'environment'
                : 'user',
        );
    }

    async function capturePhoto(): Promise<void> {
        const video = videoRef.current;
        const canvas = canvasRef.current;

        if (
            !video
            || !canvas
            || !cameraReady
            || video.videoWidth <= 0
            || video.videoHeight <= 0
        ) {
            setCameraError(
                'Kamera belum siap. Tunggu beberapa saat lalu coba kembali.',
            );

            return;
        }

        const sourceSize = Math.min(
            video.videoWidth,
            video.videoHeight,
        );

        const sourceX =
            (video.videoWidth - sourceSize) / 2;

        const sourceY =
            (video.videoHeight - sourceSize) / 2;

        const outputSize = 1024;

        canvas.width = outputSize;
        canvas.height = outputSize;

        const context =
            canvas.getContext('2d');

        if (!context) {
            setCameraError(
                'Foto gagal diproses oleh browser.',
            );

            return;
        }

        context.clearRect(
            0,
            0,
            outputSize,
            outputSize,
        );

        context.drawImage(
            video,
            sourceX,
            sourceY,
            sourceSize,
            sourceSize,
            0,
            0,
            outputSize,
            outputSize,
        );

        const blob = await new Promise<Blob | null>(
            (resolve) => {
                canvas.toBlob(
                    resolve,
                    'image/jpeg',
                    0.92,
                );
            },
        );

        if (!blob) {
            setCameraError(
                'Hasil foto kamera gagal dibuat.',
            );

            return;
        }

        const file = new File(
            [blob],
            createCameraFileName(
                pickupPersonId,
            ),
            {
                type: 'image/jpeg',
                lastModified: Date.now(),
            },
        );

        const validationError =
            validatePhotoFile(file);

        if (validationError) {
            setCameraError(validationError);

            return;
        }

        photoForm.clearErrors('photo');
        photoForm.setData('photo', file);

        closeCamera();
    }

    function cancelSelectedPhoto(): void {
        photoForm.setData('photo', null);
        photoForm.clearErrors('photo');

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }

    function submitPhoto(
        event: FormEvent<HTMLFormElement>,
    ): void {
        event.preventDefault();

        if (!photoForm.data.photo) {
            photoForm.setError(
                'photo',
                'Pilih atau ambil foto terlebih dahulu.',
            );

            return;
        }

        photoForm.post(
            `/pickup-persons/${pickupPersonId}/photo`,
            {
                forceFormData: true,
                preserveScroll: true,

                onSuccess: () => {
                    photoForm.reset();

                    if (fileInputRef.current) {
                        fileInputRef.current.value = '';
                    }
                },
            },
        );
    }

    function deletePhoto(): void {
        if (
            !currentPhotoUrl
            || isDeleting
        ) {
            return;
        }

        const confirmed = window.confirm(
            `Hapus foto ${pickupPersonName}?`,
        );

        if (!confirmed) {
            return;
        }

        cancelSelectedPhoto();
        setIsDeleting(true);

        router.delete(
            `/pickup-persons/${pickupPersonId}/photo`,
            {
                preserveScroll: true,

                onFinish: () => {
                    setIsDeleting(false);
                },
            },
        );
    }

    return (
        <>
            <section className="rounded-xl border bg-card p-5 shadow-sm">
                <div className="mb-4">
                    <h2 className="font-semibold">
                        Foto dan Registrasi Wajah
                    </h2>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Unggah foto atau ambil langsung menggunakan
                        kamera perangkat.
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border bg-muted">
                    <div className="flex aspect-square items-center justify-center">
                        {displayedPhotoUrl ? (
                            <img
                                src={displayedPhotoUrl}
                                alt={`Foto ${pickupPersonName}`}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <div className="flex h-full w-full flex-col items-center justify-center gap-3">
                                <div className="flex h-24 w-24 items-center justify-center rounded-full bg-background text-3xl font-bold shadow-sm">
                                    {initials}
                                </div>

                                <p className="text-sm text-muted-foreground">
                                    Belum ada foto
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                {selectedPreviewUrl && (
                    <div className="mt-3 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">
                        Preview foto baru. Tekan Simpan Foto untuk
                        mengunggahnya.
                    </div>
                )}

                <div className="mt-4">
                    <span
                        className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${faceStatusClass(
                            faceStatus,
                        )}`}
                    >
                        {faceStatusLabel(faceStatus)}
                    </span>
                </div>

                <div className="mt-4 rounded-lg border bg-muted/30 p-3">
                    <p className="text-xs font-medium">
                        Panduan foto wajah
                    </p>

                    <ul className="mt-2 space-y-1 text-xs text-muted-foreground">
                        <li>
                            • Pastikan hanya satu wajah terlihat.
                        </li>

                        <li>
                            • Wajah menghadap kamera dan tidak tertutup.
                        </li>

                        <li>
                            • Gunakan pencahayaan yang cukup.
                        </li>

                        <li>
                            • Hindari foto buram atau terlalu gelap.
                        </li>
                    </ul>
                </div>

                {canManage && (
                    <form
                        onSubmit={submitPhoto}
                        className="mt-5 space-y-4"
                    >
                        <input
                            ref={fileInputRef}
                            type="file"
                            name="photo"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            onChange={handlePhotoChange}
                            className="hidden"
                        />

                        <div className="grid gap-2 sm:grid-cols-2">
                            <button
                                type="button"
                                onClick={() =>
                                    fileInputRef.current?.click()
                                }
                                disabled={
                                    photoForm.processing
                                    || isDeleting
                                }
                                className="inline-flex h-10 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Pilih dari Perangkat
                            </button>

                            <button
                                type="button"
                                onClick={openCamera}
                                disabled={
                                    photoForm.processing
                                    || isDeleting
                                }
                                className="inline-flex h-10 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Buka Kamera
                            </button>
                        </div>

                        {photoForm.data.photo && (
                            <div className="rounded-md border bg-muted/40 p-3">
                                <p className="truncate text-sm font-medium">
                                    {photoForm.data.photo.name}
                                </p>

                                <p className="mt-1 text-xs text-muted-foreground">
                                    {(
                                        photoForm.data.photo.size
                                        / 1024
                                        / 1024
                                    ).toFixed(2)}{' '}
                                    MB
                                </p>
                            </div>
                        )}

                        {photoForm.errors.photo && (
                            <p className="text-sm text-red-600">
                                {photoForm.errors.photo}
                            </p>
                        )}

                        {photoForm.progress && (
                            <div>
                                <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                                    <span>
                                        Mengunggah foto
                                    </span>

                                    <span>
                                        {
                                            photoForm.progress
                                                .percentage
                                        }
                                        %
                                    </span>
                                </div>

                                <div className="h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full bg-primary transition-all"
                                        style={{
                                            width: `${photoForm.progress.percentage}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        )}

                        {photoForm.data.photo && (
                            <div className="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    onClick={
                                        cancelSelectedPhoto
                                    }
                                    disabled={
                                        photoForm.processing
                                    }
                                    className="inline-flex h-10 items-center justify-center rounded-md border bg-background px-3 text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Batalkan
                                </button>

                                <button
                                    type="submit"
                                    disabled={
                                        photoForm.processing
                                    }
                                    className="inline-flex h-10 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {photoForm.processing
                                        ? 'Menyimpan...'
                                        : 'Simpan Foto'}
                                </button>
                            </div>
                        )}

                        {currentPhotoUrl
                            && !photoForm.data.photo && (
                                <button
                                    type="button"
                                    onClick={deletePhoto}
                                    disabled={
                                        photoForm.processing
                                        || isDeleting
                                    }
                                    className="inline-flex h-10 w-full items-center justify-center rounded-md border border-red-300 bg-background px-4 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900 dark:hover:bg-red-950"
                                >
                                    {isDeleting
                                        ? 'Menghapus...'
                                        : 'Hapus Foto'}
                                </button>
                            )}
                    </form>
                )}
            </section>

            {cameraOpen && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Kamera penjemput"
                >
                    <div className="w-full max-w-xl overflow-hidden rounded-xl border bg-background shadow-xl">
                        <div className="flex items-center justify-between border-b px-5 py-4">
                            <div>
                                <h2 className="font-semibold">
                                    Ambil Foto Wajah
                                </h2>

                                <p className="mt-1 text-xs text-muted-foreground">
                                    Posisikan wajah di tengah panduan.
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={closeCamera}
                                className="inline-flex h-9 items-center justify-center rounded-md border px-3 text-sm font-medium hover:bg-muted"
                            >
                                Tutup
                            </button>
                        </div>

                        <div className="p-5">
                            <div className="relative aspect-square overflow-hidden rounded-xl bg-black">
                                <video
                                    ref={videoRef}
                                    autoPlay
                                    muted
                                    playsInline
                                    onCanPlay={() => {
                                        setCameraReady(true);
                                    }}
                                    className={`h-full w-full object-cover ${
                                        cameraFacingMode === 'user'
                                            ? '-scale-x-100'
                                            : ''
                                    }`}
                                />

                                <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                                    <div className="h-[72%] w-[58%] rounded-[50%] border-2 border-dashed border-white/90 shadow-[0_0_0_9999px_rgba(0,0,0,0.28)]" />
                                </div>

                                {!cameraReady
                                    && !cameraError && (
                                        <div className="absolute inset-0 flex items-center justify-center bg-black/60 text-sm text-white">
                                            Membuka kamera...
                                        </div>
                                    )}
                            </div>

                            {cameraError && (
                                <div className="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                                    {cameraError}
                                </div>
                            )}

                            <div className="mt-4 grid gap-2 sm:grid-cols-2">
                                <button
                                    type="button"
                                    onClick={switchCamera}
                                    disabled={
                                        !cameraStreamRef.current
                                    }
                                    className="inline-flex h-10 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Ganti Kamera
                                </button>

                                <button
                                    type="button"
                                    onClick={() => {
                                        void capturePhoto();
                                    }}
                                    disabled={
                                        !cameraReady
                                        || Boolean(cameraError)
                                    }
                                    className="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Ambil Foto
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <canvas
                ref={canvasRef}
                className="hidden"
            />
        </>
    );
}