import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    CalendarDays,
    GraduationCap,
    IdCard,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Power,
    ScanFace,
    ShieldAlert,
    Trash2,
    UserRoundCheck,
    UsersRound,
    type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/app-layout';

type FaceStatus =
    | 'not_registered'
    | 'registered'
    | 'needs_update';

type StudentStatus =
    | 'active'
    | 'inactive'
    | 'graduated';

interface LinkedStudent {
    id: number;
    full_name: string;
    student_number: string;
    status: StudentStatus;
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
    face_status: FaceStatus;
    is_active: boolean;
    notes: string | null;
    students: LinkedStudent[];
}

interface Permissions {
    can_manage: boolean;
    can_archive: boolean;
}

interface PickupPersonShowProps {
    pickupPerson: PickupPerson;
    permissions: Permissions;
}

interface InfoItemProps {
    icon: LucideIcon;
    label: string;
    value: string;
}

const faceStatusLabels: Record<FaceStatus, string> = {
    not_registered: 'Belum terdaftar',
    registered: 'Wajah terdaftar',
    needs_update: 'Perlu diperbarui',
};

const faceStatusStyles: Record<FaceStatus, string> = {
    not_registered:
        'border-[#f0dfb6] bg-[#fff8e8] text-[#9a741f]',

    registered:
        'border-[#cfe9e3] bg-[#e8f6f3] text-[#438f86]',

    needs_update:
        'border-[#efd1d1] bg-[#fff1f1] text-[#b85c5c]',
};

const studentStatusLabels: Record<StudentStatus, string> = {
    active: 'Aktif',
    inactive: 'Tidak aktif',
    graduated: 'Lulus',
};

const studentStatusStyles: Record<StudentStatus, string> = {
    active:
        'border-[#cfe9e3] bg-[#e8f6f3] text-[#438f86]',

    inactive:
        'border-[#dde5ec] bg-[#f1f5f9] text-[#627d98]',

    graduated:
        'border-[#dce3f5] bg-[#eef3ff] text-[#5b73b8]',
};

const relationshipLabels: Record<string, string> = {
    father: 'Ayah',
    mother: 'Ibu',
    sibling: 'Saudara',
    relative: 'Kerabat',
    driver: 'Pengemudi',
    guardian: 'Wali',
    other: 'Lainnya',
};

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    const datePart = value.slice(0, 10);
    const [year, month, day] = datePart.split('-');

    if (!year || !month || !day) {
        return value;
    }

    return `${day}-${month}-${year}`;
}

export default function PickupPersonShow({
    pickupPerson,
    permissions,
}: PickupPersonShowProps) {
    const [processingStatus, setProcessingStatus] =
        useState(false);

    const [processingArchive, setProcessingArchive] =
        useState(false);

    const toggleStatus = (): void => {
        const action = pickupPerson.is_active
            ? 'menonaktifkan'
            : 'mengaktifkan';

        const confirmed = window.confirm(
            `Apakah Anda yakin ingin ${action} ${pickupPerson.full_name}?`,
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
                    setProcessingStatus(true);
                },

                onFinish: () => {
                    setProcessingStatus(false);
                },
            },
        );
    };

    const archivePickupPerson = (): void => {
        const confirmed = window.confirm(
            `Pindahkan data ${pickupPerson.full_name} ke arsip?`,
        );

        if (!confirmed) {
            return;
        }

        router.delete(
            `/pickup-persons/${pickupPerson.id}`,
            {
                onStart: () => {
                    setProcessingArchive(true);
                },

                onFinish: () => {
                    setProcessingArchive(false);
                },
            },
        );
    };

    return (
        <AppLayout>
            <Head title={pickupPerson.full_name} />

            <main className="min-h-full bg-[#f8fafc]">
                <div className="mx-auto w-full max-w-6xl p-4 md:p-6">
                    <Link
                        href="/pickup-persons"
                        className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-[#627d98] transition hover:text-[#4f7cac]"
                    >
                        <ArrowLeft
                            aria-hidden="true"
                            className="size-4"
                        />

                        Kembali ke Data Penjemput
                    </Link>

                    <section className="relative overflow-hidden rounded-[28px] border border-[#deebf5] bg-gradient-to-r from-[#edf6ff] via-[#f2faf8] to-[#fffaf0] p-6 shadow-sm md:p-8">
                        <div className="absolute -top-20 -right-16 size-56 rounded-full bg-white/50 blur-3xl" />

                        <div className="relative flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                            <div className="flex items-center gap-4">
                                <div className="flex size-20 shrink-0 items-center justify-center rounded-[24px] bg-white text-2xl font-bold text-[#4f7cac] shadow-sm">
                                    {pickupPerson.initials}
                                </div>

                                <div>
                                    <div className="flex flex-wrap gap-2">
                                        <span
                                            className={[
                                                'inline-flex rounded-full border px-3 py-1 text-xs font-semibold',
                                                pickupPerson.is_active
                                                    ? 'border-[#cfe9e3] bg-[#e8f6f3] text-[#438f86]'
                                                    : 'border-[#dde5ec] bg-[#f1f5f9] text-[#627d98]',
                                            ].join(' ')}
                                        >
                                            {pickupPerson.is_active
                                                ? 'Aktif'
                                                : 'Tidak aktif'}
                                        </span>

                                        <span
                                            className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold ${faceStatusStyles[pickupPerson.face_status]}`}
                                        >
                                            {pickupPerson.face_status ===
                                            'registered' ? (
                                                <ScanFace
                                                    aria-hidden="true"
                                                    className="size-3.5"
                                                />
                                            ) : (
                                                <ShieldAlert
                                                    aria-hidden="true"
                                                    className="size-3.5"
                                                />
                                            )}

                                            {
                                                faceStatusLabels[
                                                    pickupPerson.face_status
                                                ]
                                            }
                                        </span>
                                    </div>

                                    <h1 className="mt-3 text-2xl font-bold text-[#243b53] md:text-3xl">
                                        {pickupPerson.full_name}
                                    </h1>

                                    <p className="mt-1 text-sm text-[#627d98]">
                                        Terhubung dengan{' '}
                                        {pickupPerson.students.length}{' '}
                                        siswa
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {permissions.can_manage && (
                                    <>
                                        <Link
                                            href={`/pickup-persons/${pickupPerson.id}/edit`}
                                            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#5b8def] px-4 text-sm font-semibold text-white shadow-md transition hover:bg-[#4c7fd9]"
                                        >
                                            <Pencil className="size-4" />
                                            Edit
                                        </Link>

                                        <button
                                            type="button"
                                            onClick={toggleStatus}
                                            disabled={processingStatus}
                                            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[#d9e5ee] bg-white px-4 text-sm font-semibold text-[#627d98] transition hover:bg-[#f7fafc] disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            <Power className="size-4" />

                                            {processingStatus
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
                                        disabled={processingArchive}
                                        className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[#f0d0d0] bg-white px-4 text-sm font-semibold text-[#cf6464] transition hover:bg-[#fff2f2] disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <Trash2 className="size-4" />

                                        {processingArchive
                                            ? 'Mengarsipkan...'
                                            : 'Arsipkan'}
                                    </button>
                                )}
                            </div>
                        </div>
                    </section>

                    <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_0.75fr]">
                        <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-xl bg-[#eef6ff] text-[#5b8def]">
                                    <UserRoundCheck className="size-5" />
                                </div>

                                <div>
                                    <h2 className="font-bold text-[#243b53]">
                                        Identitas Penjemput
                                    </h2>

                                    <p className="text-sm text-[#829ab1]">
                                        Informasi identitas dan kontak.
                                    </p>
                                </div>
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <InfoItem
                                    icon={IdCard}
                                    label="Nomor Identitas"
                                    value={
                                        pickupPerson.identity_number ??
                                        '-'
                                    }
                                />

                                <InfoItem
                                    icon={Phone}
                                    label="Nomor Telepon"
                                    value={pickupPerson.phone}
                                />

                                <InfoItem
                                    icon={Mail}
                                    label="Email"
                                    value={
                                        pickupPerson.email ?? '-'
                                    }
                                />

                                <InfoItem
                                    icon={ScanFace}
                                    label="Status Wajah"
                                    value={
                                        faceStatusLabels[
                                            pickupPerson.face_status
                                        ]
                                    }
                                />
                            </div>
                        </section>

                        <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-xl bg-[#eef9f6] text-[#4c9e94]">
                                    <MapPin className="size-5" />
                                </div>

                                <div>
                                    <h2 className="font-bold text-[#243b53]">
                                        Alamat
                                    </h2>

                                    <p className="text-sm text-[#829ab1]">
                                        Tempat tinggal penjemput.
                                    </p>
                                </div>
                            </div>

                            <p className="whitespace-pre-wrap text-sm leading-7 text-[#627d98]">
                                {pickupPerson.address ||
                                    'Alamat belum diisi.'}
                            </p>
                        </section>
                    </div>

                    <section className="mt-6 overflow-hidden rounded-2xl border border-[#e6eef5] bg-white shadow-sm">
                        <div className="flex items-center gap-3 border-b border-[#edf2f7] p-5 md:p-7">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-[#eef9f6] text-[#4c9e94]">
                                <UsersRound className="size-5" />
                            </div>

                            <div>
                                <h2 className="font-bold text-[#243b53]">
                                    Siswa yang Boleh Dijemput
                                </h2>

                                <p className="text-sm text-[#829ab1]">
                                    Hubungan dan masa berlaku izin
                                    penjemputan.
                                </p>
                            </div>
                        </div>

                        {pickupPerson.students.length > 0 ? (
                            <div className="divide-y divide-[#edf2f7]">
                                {pickupPerson.students.map(
                                    (student) => (
                                        <article
                                            key={student.id}
                                            className="p-5 transition hover:bg-[#fbfdff] md:p-6"
                                        >
                                            <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                                                <div className="flex items-start gap-3">
                                                    <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#eef6ff] text-[#4f7cac]">
                                                        <GraduationCap className="size-5" />
                                                    </div>

                                                    <div>
                                                        <Link
                                                            href={`/students/${student.id}`}
                                                            className="font-semibold text-[#334e68] transition hover:text-[#4f7cac]"
                                                        >
                                                            {
                                                                student.full_name
                                                            }
                                                        </Link>

                                                        <p className="mt-1 text-xs text-[#829ab1]">
                                                            {
                                                                student.student_number
                                                            }{' '}
                                                            · Kelas{' '}
                                                            {student.class_name ??
                                                                '-'}{' '}
                                                            ·{' '}
                                                            {student.academic_year ??
                                                                '-'}
                                                        </p>

                                                        <div className="mt-3 flex flex-wrap gap-2">
                                                            <span className="rounded-full border border-[#dceaf5] bg-[#eef6ff] px-3 py-1 text-xs font-semibold text-[#4f7cac]">
                                                                {relationshipLabels[
                                                                    student
                                                                        .relationship_type
                                                                ] ??
                                                                    student.relationship_type}
                                                            </span>

                                                            <span
                                                                className={`rounded-full border px-3 py-1 text-xs font-semibold ${studentStatusStyles[student.status]}`}
                                                            >
                                                                {
                                                                    studentStatusLabels[
                                                                        student
                                                                            .status
                                                                    ]
                                                                }
                                                            </span>

                                                            {student.is_primary && (
                                                                <span className="inline-flex items-center gap-1 rounded-full border border-[#cfe9e3] bg-[#e8f6f3] px-3 py-1 text-xs font-semibold text-[#438f86]">
                                                                    <BadgeCheck className="size-3.5" />
                                                                    Penjemput
                                                                    utama
                                                                </span>
                                                            )}

                                                            <span
                                                                className={[
                                                                    'rounded-full border px-3 py-1 text-xs font-semibold',
                                                                    student.is_active
                                                                        ? 'border-[#cfe9e3] bg-[#e8f6f3] text-[#438f86]'
                                                                        : 'border-[#dde5ec] bg-[#f1f5f9] text-[#627d98]',
                                                                ].join(
                                                                    ' ',
                                                                )}
                                                            >
                                                                {student.is_active
                                                                    ? 'Izin aktif'
                                                                    : 'Izin tidak aktif'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="grid shrink-0 gap-2 text-sm sm:grid-cols-2">
                                                    <div className="rounded-xl bg-[#f8fafc] px-4 py-3">
                                                        <p className="text-xs text-[#9fb3c8]">
                                                            Berlaku mulai
                                                        </p>

                                                        <p className="mt-1 inline-flex items-center gap-2 font-semibold text-[#627d98]">
                                                            <CalendarDays className="size-4" />
                                                            {formatDate(
                                                                student.valid_from,
                                                            )}
                                                        </p>
                                                    </div>

                                                    <div className="rounded-xl bg-[#f8fafc] px-4 py-3">
                                                        <p className="text-xs text-[#9fb3c8]">
                                                            Berlaku sampai
                                                        </p>

                                                        <p className="mt-1 inline-flex items-center gap-2 font-semibold text-[#627d98]">
                                                            <CalendarDays className="size-4" />
                                                            {formatDate(
                                                                student.valid_until,
                                                            )}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </article>
                                    ),
                                )}
                            </div>
                        ) : (
                            <div className="px-6 py-14 text-center">
                                <UsersRound className="mx-auto size-10 text-[#bcccdc]" />

                                <h3 className="mt-4 font-semibold text-[#334e68]">
                                    Belum terhubung dengan siswa
                                </h3>

                                <p className="mt-1 text-sm text-[#829ab1]">
                                    Edit data penjemput untuk
                                    menambahkan hubungan siswa.
                                </p>
                            </div>
                        )}
                    </section>

                    <section className="mt-6 rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-7">
                        <h2 className="font-bold text-[#243b53]">
                            Catatan Penjemput
                        </h2>

                        <p className="mt-3 whitespace-pre-wrap text-sm leading-7 text-[#627d98]">
                            {pickupPerson.notes ||
                                'Belum ada catatan khusus untuk penjemput ini.'}
                        </p>
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}

function InfoItem({
    icon: Icon,
    label,
    value,
}: InfoItemProps) {
    return (
        <div className="flex items-start gap-3">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#f5f8fb] text-[#829ab1]">
                <Icon
                    aria-hidden="true"
                    className="size-4"
                />
            </div>

            <div className="min-w-0">
                <p className="text-xs font-medium uppercase tracking-wide text-[#9fb3c8]">
                    {label}
                </p>

                <p className="mt-1 break-words text-sm font-semibold text-[#334e68]">
                    {value}
                </p>
            </div>
        </div>
    );
}