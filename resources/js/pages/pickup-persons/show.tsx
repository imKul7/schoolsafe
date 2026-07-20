import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ChangeEvent, FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';

interface StudentItem {
    id: number;
    full_name: string;
    student_number: string;
    status: string;
    class_name: string | null;
    academic_year: string | null;
    relationship_type: string;
    is_primary: boolean;
    is_active: boolean;
    valid_from: string | null;
    valid_until: string | null;
}

interface PickupPerson {
    id: number;
    full_name: string;
    initials: string;
    identity_number: string | null;
    phone: string;
    email: string | null;
    address: string | null;
    photo_path: string | null;
    photo_url: string | null;
    face_status: string;
    is_active: boolean;
    notes: string | null;
    students: StudentItem[];
}

interface Permissions {
    can_manage: boolean;
    can_archive: boolean;
}

interface PageProps {
    pickupPerson: PickupPerson;
    permissions: Permissions;
}

interface PhotoForm {
    [key: string]: File | null;
    photo: File | null;
}

const MAX_PHOTO_SIZE = 5 * 1024 * 1024;

const allowedPhotoTypes = [
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
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300';

        case 'needs_update':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300';

        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
    }
}

function relationshipLabel(value: string): string {
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

function studentStatusLabel(status: string): string {
    return status === 'active' ? 'Aktif' : 'Tidak Aktif';
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Tidak dibatasi';
    }

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(date);
}

export default function PickupPersonShow({
    pickupPerson,
    permissions,
}: PageProps) {
    const fileInputRef = useRef<HTMLInputElement | null>(null);

    const [previewUrl, setPreviewUrl] = useState<string | null>(
        null,
    );

    const photoForm = useForm<PhotoForm>({
        photo: null,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Data Penjemput',
            href: '/pickup-persons',
        },
        {
            title: pickupPerson.full_name,
            href: `/pickup-persons/${pickupPerson.id}`,
        },
    ];

    useEffect(() => {
        if (!photoForm.data.photo) {
            setPreviewUrl(null);

            return;
        }

        const objectUrl = URL.createObjectURL(
            photoForm.data.photo,
        );

        setPreviewUrl(objectUrl);

        return () => {
            URL.revokeObjectURL(objectUrl);
        };
    }, [photoForm.data.photo]);

    const displayedPhotoUrl =
        previewUrl ?? pickupPerson.photo_url;

    const handlePhotoChange = (
        event: ChangeEvent<HTMLInputElement>,
    ): void => {
        const file = event.target.files?.[0] ?? null;

        photoForm.clearErrors('photo');

        if (!file) {
            photoForm.setData('photo', null);

            return;
        }

        if (!allowedPhotoTypes.includes(file.type)) {
            photoForm.setError(
                'photo',
                'Foto harus berformat JPG, JPEG, PNG, atau WEBP.',
            );

            event.target.value = '';

            photoForm.setData('photo', null);

            return;
        }

        if (file.size > MAX_PHOTO_SIZE) {
            photoForm.setError(
                'photo',
                'Ukuran foto maksimal 5 MB.',
            );

            event.target.value = '';

            photoForm.setData('photo', null);

            return;
        }

        photoForm.setData('photo', file);
    };

    const cancelSelectedPhoto = (): void => {
        photoForm.setData('photo', null);
        photoForm.clearErrors('photo');

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const submitPhoto = (
        event: FormEvent<HTMLFormElement>,
    ): void => {
        event.preventDefault();

        if (!photoForm.data.photo) {
            photoForm.setError(
                'photo',
                'Pilih foto penjemput terlebih dahulu.',
            );

            return;
        }

        photoForm.post(
            `/pickup-persons/${pickupPerson.id}/photo`,
            {
                forceFormData: true,
                preserveScroll: true,

                onSuccess: () => {
                    cancelSelectedPhoto();
                },
            },
        );
    };

    const deletePhoto = (): void => {
        if (!pickupPerson.photo_url) {
            return;
        }

        const confirmed = window.confirm(
            `Hapus foto ${pickupPerson.full_name}?`,
        );

        if (!confirmed) {
            return;
        }

        cancelSelectedPhoto();

        router.delete(
            `/pickup-persons/${pickupPerson.id}/photo`,
            {
                preserveScroll: true,
            },
        );
    };

    const toggleStatus = (): void => {
        const action = pickupPerson.is_active
            ? 'menonaktifkan'
            : 'mengaktifkan';

        const confirmed = window.confirm(
            `Yakin ingin ${action} ${pickupPerson.full_name}?`,
        );

        if (!confirmed) {
            return;
        }

        router.patch(
            `/pickup-persons/${pickupPerson.id}/toggle-status`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const archivePickupPerson = (): void => {
        const confirmed = window.confirm(
            `Pindahkan ${pickupPerson.full_name} ke arsip?`,
        );

        if (!confirmed) {
            return;
        }

        router.delete(
            `/pickup-persons/${pickupPerson.id}`,
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`Detail Penjemput - ${pickupPerson.full_name}`}
            />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Detail Data Penjemput
                        </p>

                        <h1 className="text-2xl font-bold tracking-tight">
                            {pickupPerson.full_name}
                        </h1>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Link
                            href="/pickup-persons"
                            className="inline-flex h-10 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium transition-colors hover:bg-muted"
                        >
                            Kembali
                        </Link>

                        {permissions.can_manage && (
                            <>
                                <Link
                                    href={`/pickup-persons/${pickupPerson.id}/edit`}
                                    className="inline-flex h-10 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium transition-colors hover:bg-muted"
                                >
                                    Edit Data
                                </Link>

                                <button
                                    type="button"
                                    onClick={toggleStatus}
                                    className="inline-flex h-10 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium transition-colors hover:bg-muted"
                                >
                                    {pickupPerson.is_active
                                        ? 'Nonaktifkan'
                                        : 'Aktifkan'}
                                </button>
                            </>
                        )}

                        {permissions.can_archive && (
                            <button
                                type="button"
                                onClick={archivePickupPerson}
                                className="inline-flex h-10 items-center justify-center rounded-md bg-red-600 px-4 text-sm font-medium text-white transition-colors hover:bg-red-700"
                            >
                                Arsipkan
                            </button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                    <section className="rounded-xl border bg-card p-5 shadow-sm">
                        <div className="mb-4">
                            <h2 className="font-semibold">
                                Foto Penjemput
                            </h2>

                            <p className="mt-1 text-sm text-muted-foreground">
                                Gunakan foto wajah yang jelas dan tidak
                                tertutup.
                            </p>
                        </div>

                        <div className="overflow-hidden rounded-xl border bg-muted">
                            <div className="flex aspect-square items-center justify-center">
                                {displayedPhotoUrl ? (
                                    <img
                                        src={displayedPhotoUrl}
                                        alt={`Foto ${pickupPerson.full_name}`}
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <div className="flex h-full w-full flex-col items-center justify-center gap-3">
                                        <div className="flex h-24 w-24 items-center justify-center rounded-full bg-background text-3xl font-bold shadow-sm">
                                            {pickupPerson.initials}
                                        </div>

                                        <p className="text-sm text-muted-foreground">
                                            Belum ada foto
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {previewUrl && (
                            <div className="mt-3 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300">
                                Ini adalah preview foto baru. Tekan
                                tombol simpan untuk mengunggahnya.
                            </div>
                        )}

                        <div className="mt-4">
                            <span
                                className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${faceStatusClass(
                                    pickupPerson.face_status,
                                )}`}
                            >
                                {faceStatusLabel(
                                    pickupPerson.face_status,
                                )}
                            </span>
                        </div>

                        {permissions.can_manage && (
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

                                <button
                                    type="button"
                                    onClick={() =>
                                        fileInputRef.current?.click()
                                    }
                                    disabled={photoForm.processing}
                                    className="inline-flex h-10 w-full items-center justify-center rounded-md border bg-background px-4 text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {pickupPerson.photo_url
                                        ? 'Pilih Foto Pengganti'
                                        : 'Pilih Foto'}
                                </button>

                                {photoForm.data.photo && (
                                    <div className="rounded-md border bg-muted/40 p-3">
                                        <p className="truncate text-sm font-medium">
                                            {
                                                photoForm.data.photo
                                                    .name
                                            }
                                        </p>

                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {(
                                                photoForm.data.photo
                                                    .size /
                                                1024 /
                                                1024
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

                                {pickupPerson.photo_url &&
                                    !photoForm.data.photo && (
                                        <button
                                            type="button"
                                            onClick={deletePhoto}
                                            disabled={
                                                photoForm.processing
                                            }
                                            className="inline-flex h-10 w-full items-center justify-center rounded-md border border-red-300 bg-background px-4 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900 dark:hover:bg-red-950"
                                        >
                                            Hapus Foto
                                        </button>
                                    )}
                            </form>
                        )}
                    </section>

                    <div className="space-y-6">
                        <section className="rounded-xl border bg-card p-5 shadow-sm">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 className="font-semibold">
                                        Informasi Penjemput
                                    </h2>

                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Identitas dan kontak penjemput.
                                    </p>
                                </div>

                                <span
                                    className={`inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold ${
                                        pickupPerson.is_active
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                            : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'
                                    }`}
                                >
                                    {pickupPerson.is_active
                                        ? 'Aktif'
                                        : 'Tidak Aktif'}
                                </span>
                            </div>

                            <dl className="mt-6 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Nama Lengkap
                                    </dt>

                                    <dd className="mt-1 font-medium">
                                        {pickupPerson.full_name}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Nomor Identitas
                                    </dt>

                                    <dd className="mt-1 font-medium">
                                        {pickupPerson.identity_number ||
                                            '-'}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Nomor Telepon
                                    </dt>

                                    <dd className="mt-1 font-medium">
                                        {pickupPerson.phone}
                                    </dd>
                                </div>

                                <div>
                                    <dt className="text-sm text-muted-foreground">
                                        Email
                                    </dt>

                                    <dd className="mt-1 break-all font-medium">
                                        {pickupPerson.email || '-'}
                                    </dd>
                                </div>

                                <div className="sm:col-span-2">
                                    <dt className="text-sm text-muted-foreground">
                                        Alamat
                                    </dt>

                                    <dd className="mt-1 whitespace-pre-line font-medium">
                                        {pickupPerson.address || '-'}
                                    </dd>
                                </div>

                                <div className="sm:col-span-2">
                                    <dt className="text-sm text-muted-foreground">
                                        Catatan
                                    </dt>

                                    <dd className="mt-1 whitespace-pre-line font-medium">
                                        {pickupPerson.notes || '-'}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <section className="rounded-xl border bg-card shadow-sm">
                            <div className="border-b p-5">
                                <h2 className="font-semibold">
                                    Siswa yang Boleh Dijemput
                                </h2>

                                <p className="mt-1 text-sm text-muted-foreground">
                                    {pickupPerson.students.length}{' '}
                                    siswa terhubung dengan penjemput
                                    ini.
                                </p>
                            </div>

                            {pickupPerson.students.length === 0 ? (
                                <div className="p-8 text-center text-sm text-muted-foreground">
                                    Belum ada siswa yang terhubung.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[850px] text-sm">
                                        <thead className="bg-muted/50">
                                            <tr className="border-b text-left">
                                                <th className="px-5 py-3 font-medium">
                                                    Siswa
                                                </th>

                                                <th className="px-5 py-3 font-medium">
                                                    Kelas
                                                </th>

                                                <th className="px-5 py-3 font-medium">
                                                    Hubungan
                                                </th>

                                                <th className="px-5 py-3 font-medium">
                                                    Berlaku
                                                </th>

                                                <th className="px-5 py-3 font-medium">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            {pickupPerson.students.map(
                                                (student) => (
                                                    <tr
                                                        key={
                                                            student.id
                                                        }
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="px-5 py-4">
                                                            <p className="font-medium">
                                                                {
                                                                    student.full_name
                                                                }
                                                            </p>

                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                NIS:{' '}
                                                                {
                                                                    student.student_number
                                                                }
                                                            </p>
                                                        </td>

                                                        <td className="px-5 py-4">
                                                            <p>
                                                                {student.class_name ||
                                                                    '-'}
                                                            </p>

                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {student.academic_year ||
                                                                    '-'}
                                                            </p>
                                                        </td>

                                                        <td className="px-5 py-4">
                                                            <p>
                                                                {relationshipLabel(
                                                                    student.relationship_type,
                                                                )}
                                                            </p>

                                                            {student.is_primary && (
                                                                <span className="mt-1 inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                                                    Utama
                                                                </span>
                                                            )}
                                                        </td>

                                                        <td className="px-5 py-4">
                                                            <p className="text-xs">
                                                                Mulai:{' '}
                                                                {formatDate(
                                                                    student.valid_from,
                                                                )}
                                                            </p>

                                                            <p className="mt-1 text-xs">
                                                                Sampai:{' '}
                                                                {formatDate(
                                                                    student.valid_until,
                                                                )}
                                                            </p>
                                                        </td>

                                                        <td className="px-5 py-4">
                                                            <div className="space-y-1">
                                                                <span
                                                                    className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                                                                        student.is_active
                                                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                                                            : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'
                                                                    }`}
                                                                >
                                                                    Relasi{' '}
                                                                    {student.is_active
                                                                        ? 'Aktif'
                                                                        : 'Tidak Aktif'}
                                                                </span>

                                                                <p className="text-xs text-muted-foreground">
                                                                    Siswa{' '}
                                                                    {studentStatusLabel(
                                                                        student.status,
                                                                    )}
                                                                </p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}