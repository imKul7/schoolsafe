import PickupPersonFaceRegistration, {
    type PickupPersonFaceProfile,
} from '@/components/pickup-persons/pickup-person-face-registration';
import PickupPersonPhotoManager from '@/components/pickup-persons/pickup-person-photo-manager';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

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
    face_profile: PickupPersonFaceProfile | null;
    is_active: boolean;
    notes: string | null;
    students: StudentItem[];
}

interface Permissions {
    can_manage: boolean;
    can_archive: boolean;
    can_manage_face: boolean;
}

interface BiometricConfig {
    minimum_quality_score: number;
    consent_version: string;
}

interface PageProps {
    pickupPerson: PickupPerson;
    permissions: Permissions;
    biometricConfig: BiometricConfig;
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
    biometricConfig,
}: PageProps) {
    const [isTogglingStatus, setIsTogglingStatus] =
        useState(false);

    const [isArchiving, setIsArchiving] =
        useState(false);

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

    const toggleStatus = (): void => {
        if (isTogglingStatus) {
            return;
        }

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

                onStart: () => {
                    setIsTogglingStatus(true);
                },

                onFinish: () => {
                    setIsTogglingStatus(false);
                },
            },
        );
    };

    const archivePickupPerson = (): void => {
        if (isArchiving) {
            return;
        }

        const confirmed = window.confirm(
            `Pindahkan ${pickupPerson.full_name} ke arsip?`,
        );

        if (!confirmed) {
            return;
        }

        router.delete(
            `/pickup-persons/${pickupPerson.id}`,
            {
                onStart: () => {
                    setIsArchiving(true);
                },

                onFinish: () => {
                    setIsArchiving(false);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`Detail Penjemput - ${pickupPerson.full_name}`}
            />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Detail Data Penjemput
                        </p>

                        <div className="mt-1 flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-bold tracking-tight">
                                {pickupPerson.full_name}
                            </h1>

                            <span
                                className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
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
                                    disabled={
                                        isTogglingStatus
                                        || isArchiving
                                    }
                                    className="inline-flex h-10 items-center justify-center rounded-md border bg-background px-4 text-sm font-medium transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {isTogglingStatus
                                        ? 'Memproses...'
                                        : pickupPerson.is_active
                                          ? 'Nonaktifkan'
                                          : 'Aktifkan'}
                                </button>
                            </>
                        )}

                        {permissions.can_archive && (
                            <button
                                type="button"
                                onClick={archivePickupPerson}
                                disabled={
                                    isArchiving
                                    || isTogglingStatus
                                }
                                className="inline-flex h-10 items-center justify-center rounded-md bg-red-600 px-4 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {isArchiving
                                    ? 'Mengarsipkan...'
                                    : 'Arsipkan'}
                            </button>
                        )}
                    </div>
                </header>

                <div className="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                    <div className="space-y-6">
                        <PickupPersonPhotoManager
                            pickupPersonId={pickupPerson.id}
                            pickupPersonName={pickupPerson.full_name}
                            initials={pickupPerson.initials}
                            currentPhotoUrl={pickupPerson.photo_url}
                            faceStatus={pickupPerson.face_status}
                            canManage={permissions.can_manage}
                        />

                        <PickupPersonFaceRegistration
                            pickupPersonId={pickupPerson.id}
                            pickupPersonName={pickupPerson.full_name}
                            currentPhotoUrl={pickupPerson.photo_url}
                            faceStatus={pickupPerson.face_status}
                            faceProfile={pickupPerson.face_profile}
                            canManageFace={
                                permissions.can_manage_face
                            }
                            biometricConfig={biometricConfig}
                        />
                    </div>

                    <div className="space-y-6">
                        <section className="rounded-xl border bg-card p-5 shadow-sm">
                            <div>
                                <h2 className="font-semibold">
                                    Informasi Penjemput
                                </h2>

                                <p className="mt-1 text-sm text-muted-foreground">
                                    Identitas dan kontak penjemput.
                                </p>
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

                        <section className="overflow-hidden rounded-xl border bg-card shadow-sm">
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
                                                <th
                                                    scope="col"
                                                    className="px-5 py-3 font-medium"
                                                >
                                                    Siswa
                                                </th>

                                                <th
                                                    scope="col"
                                                    className="px-5 py-3 font-medium"
                                                >
                                                    Kelas
                                                </th>

                                                <th
                                                    scope="col"
                                                    className="px-5 py-3 font-medium"
                                                >
                                                    Hubungan
                                                </th>

                                                <th
                                                    scope="col"
                                                    className="px-5 py-3 font-medium"
                                                >
                                                    Berlaku
                                                </th>

                                                <th
                                                    scope="col"
                                                    className="px-5 py-3 font-medium"
                                                >
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
                                                        <td className="px-5 py-4 align-top">
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

                                                        <td className="px-5 py-4 align-top">
                                                            <p>
                                                                {student.class_name ||
                                                                    '-'}
                                                            </p>

                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {student.academic_year ||
                                                                    '-'}
                                                            </p>
                                                        </td>

                                                        <td className="px-5 py-4 align-top">
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

                                                        <td className="px-5 py-4 align-top">
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

                                                        <td className="px-5 py-4 align-top">
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
