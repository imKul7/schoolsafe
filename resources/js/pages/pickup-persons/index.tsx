import { Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    BadgeCheck,
    Eye,
    Filter,
    IdCard,
    Phone,
    Plus,
    ScanFace,
    Search,
    ShieldAlert,
    UserRoundCheck,
    UsersRound,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useMemo, useState, type FormEventHandler } from 'react';

import AppLayout from '@/layouts/app-layout';

type FaceStatus = 'not_registered' | 'registered' | 'needs_update';

interface LinkedStudent {
    id: number;
    full_name: string;
    student_number: string;
    relationship_type: string;
    is_primary: boolean;
}

interface PickupPerson {
    id: number;
    full_name: string;
    initials: string;
    identity_number: string | null;
    phone: string;
    email: string | null;
    photo_path: string | null;
    face_status: FaceStatus;
    is_active: boolean;
    students_count: number;
    students: LinkedStudent[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PickupPersonsPagination {
    data: PickupPerson[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Filters {
    search: string;
    status: string;
    face_status: string;
}

interface Stats {
    total: number;
    active: number;
    face_registered: number;
}

interface Permissions {
    can_manage: boolean;
    can_archive: boolean;
}

interface PickupPersonsIndexProps {
    pickupPersons: PickupPersonsPagination;
    filters: Filters;
    stats: Stats;
    permissions: Permissions;
}

interface SummaryCardProps {
    title: string;
    value: number;
    description: string;
    icon: LucideIcon;
    tone: 'blue' | 'green' | 'yellow';
}

const faceStatusLabels: Record<FaceStatus, string> = {
    not_registered: 'Belum terdaftar',
    registered: 'Wajah terdaftar',
    needs_update: 'Perlu diperbarui',
};

const faceStatusStyles: Record<FaceStatus, string> = {
    not_registered: 'border-[#f0dfb6] bg-[#fff8e8] text-[#9a741f]',

    registered: 'border-[#cfe9e3] bg-[#e8f6f3] text-[#438f86]',

    needs_update: 'border-[#efd1d1] bg-[#fff1f1] text-[#b85c5c]',
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

const summaryToneStyles: Record<
    SummaryCardProps['tone'],
    {
        card: string;
        icon: string;
    }
> = {
    blue: {
        card: 'border-[#dceaf8] bg-[#eef6ff]',
        icon: 'bg-white text-[#5b8def]',
    },

    green: {
        card: 'border-[#d9eee9] bg-[#eef9f6]',
        icon: 'bg-white text-[#4c9e94]',
    },

    yellow: {
        card: 'border-[#f5e8bd] bg-[#fff9e9]',
        icon: 'bg-white text-[#b88a22]',
    },
};

function paginationLabel(label: string): string {
    return label.replace('&laquo; Previous', 'Sebelumnya').replace('Next &raquo;', 'Berikutnya').replace('&laquo;', '‹').replace('&raquo;', '›');
}

export default function PickupPersonsIndex({ pickupPersons, filters, stats, permissions }: PickupPersonsIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const [status, setStatus] = useState(filters.status ?? '');

    const [faceStatus, setFaceStatus] = useState(filters.face_status ?? '');

    const hasActiveFilters = useMemo(() => search.trim() !== '' || status !== '' || faceStatus !== '', [search, status, faceStatus]);

    const submitFilters: FormEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();

        router.get(
            '/pickup-persons',
            {
                search: search.trim(),
                status,
                face_status: faceStatus,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const resetFilters = (): void => {
        setSearch('');
        setStatus('');
        setFaceStatus('');

        router.get(
            '/pickup-persons',
            {},
            {
                preserveState: false,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Data Penjemput" />

            <main className="module-page module-pickup-persons min-h-full">
                <div className="module-container mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 md:p-6">
                    <section className="module-hero module-hero-pickup relative overflow-hidden rounded-[30px] p-6 md:p-8">
                        <div className="absolute -top-24 -right-16 size-60 rounded-full bg-white/55 blur-3xl" />

                        <div className="relative flex flex-col justify-between gap-5 md:flex-row md:items-center">
                            <div className="flex items-start gap-4">
                                <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white text-[#5b8def] shadow-sm">
                                    <UserRoundCheck aria-hidden="true" className="size-6" />
                                </div>

                                <div>
                                    <h1 className="text-2xl font-bold tracking-tight text-[#243b53] md:text-3xl">Data Penjemput</h1>

                                    <p className="mt-2 max-w-2xl text-sm leading-6 text-[#627d98]">
                                        Kelola orang tua, wali, kerabat, dan pihak lain yang memiliki izin menjemput siswa.
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {permissions.can_archive && (
                                    <Link
                                        href="/pickup-persons/archive"
                                        className="module-secondary-button inline-flex h-11 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-bold"
                                    >
                                        <Archive aria-hidden="true" className="size-4" />
                                        Lihat Arsip
                                    </Link>
                                )}

                                {permissions.can_manage && (
                                    <Link
                                        href="/pickup-persons/create"
                                        className="module-primary-button inline-flex h-11 items-center justify-center gap-2 rounded-2xl px-5 text-sm font-bold text-white"
                                    >
                                        <Plus aria-hidden="true" className="size-4" />
                                        Tambah Penjemput
                                    </Link>
                                )}
                            </div>
                        </div>
                    </section>

                    <section aria-label="Ringkasan data penjemput" className="module-summary-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <SummaryCard
                            title="Total Penjemput"
                            value={stats.total}
                            description="Seluruh penjemput terdaftar"
                            icon={UsersRound}
                            tone="blue"
                        />

                        <SummaryCard
                            title="Penjemput Aktif"
                            value={stats.active}
                            description="Dapat digunakan dalam sistem"
                            icon={BadgeCheck}
                            tone="green"
                        />

                        <SummaryCard
                            title="Wajah Terdaftar"
                            value={stats.face_registered}
                            description="Siap digunakan pada scanner"
                            icon={ScanFace}
                            tone="yellow"
                        />
                    </section>

                    <section className="module-filter-panel rounded-[24px] p-5">
                        <div className="mb-4">
                            <h2 className="font-bold text-[#243b53]">Cari dan Filter</h2>

                            <p className="mt-1 text-sm leading-6 text-[#829ab1]">
                                Cari berdasarkan nama, nomor identitas, nomor telepon, atau email.
                            </p>
                        </div>

                        <form onSubmit={submitFilters} className="grid gap-3 lg:grid-cols-[minmax(260px,1fr)_190px_210px_auto]" role="search">
                            <div className="relative">
                                <Search
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]"
                                />

                                <input
                                    type="search"
                                    value={search}
                                    onChange={(event) => setSearch(event.currentTarget.value)}
                                    placeholder="Cari nama, identitas, telepon, atau email..."
                                    aria-label="Cari penjemput"
                                    className="h-11 w-full rounded-xl border border-[#d9e5ee] bg-[#fbfdff] pr-4 pl-10 text-sm text-[#334e68] transition outline-none placeholder:text-[#bcccdc] focus:border-[#7fa9d8] focus:ring-2 focus:ring-[#dcebf8]"
                                />
                            </div>

                            <select
                                value={status}
                                onChange={(event) => setStatus(event.currentTarget.value)}
                                aria-label="Filter status penjemput"
                                className="h-11 rounded-xl border border-[#d9e5ee] bg-[#fbfdff] px-3 text-sm text-[#486581] transition outline-none focus:border-[#7fa9d8] focus:ring-2 focus:ring-[#dcebf8]"
                            >
                                <option value="">Semua status</option>

                                <option value="active">Aktif</option>

                                <option value="inactive">Tidak aktif</option>
                            </select>

                            <select
                                value={faceStatus}
                                onChange={(event) => setFaceStatus(event.currentTarget.value)}
                                aria-label="Filter status wajah"
                                className="h-11 rounded-xl border border-[#d9e5ee] bg-[#fbfdff] px-3 text-sm text-[#486581] transition outline-none focus:border-[#7fa9d8] focus:ring-2 focus:ring-[#dcebf8]"
                            >
                                <option value="">Semua status wajah</option>

                                <option value="registered">Wajah terdaftar</option>

                                <option value="not_registered">Belum terdaftar</option>

                                <option value="needs_update">Perlu diperbarui</option>
                            </select>

                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    className="module-filter-apply inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold text-white"
                                >
                                    <Filter aria-hidden="true" className="size-4" />
                                    Terapkan
                                </button>

                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    disabled={!hasActiveFilters}
                                    title="Reset filter"
                                    aria-label="Reset filter"
                                    className="module-filter-reset inline-flex size-11 shrink-0 items-center justify-center rounded-xl border disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <X aria-hidden="true" className="size-4" />
                                </button>
                            </div>
                        </form>
                    </section>

                    <section className="module-table-panel overflow-hidden rounded-[24px]">
                        <div className="flex flex-col justify-between gap-2 border-b border-[#edf2f7] px-5 py-4 sm:flex-row sm:items-center">
                            <div>
                                <h2 className="font-bold text-[#243b53]">Daftar Penjemput</h2>

                                <p className="mt-1 text-sm text-[#829ab1]">Penjemput dan siswa yang terhubung dengannya.</p>
                            </div>

                            <p className="text-sm font-medium text-[#627d98]">{pickupPersons.total} data</p>
                        </div>

                        {pickupPersons.data.length > 0 ? (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[1150px]">
                                        <thead className="bg-[#f8fbfd]">
                                            <tr className="border-b border-[#e6eef5]">
                                                <th className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase">
                                                    Penjemput
                                                </th>

                                                <th className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase">
                                                    Kontak
                                                </th>

                                                <th className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase">
                                                    Siswa
                                                </th>

                                                <th className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase">
                                                    Status wajah
                                                </th>

                                                <th className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase">
                                                    Status
                                                </th>

                                                <th className="px-5 py-4 text-right text-xs font-semibold tracking-wide text-[#829ab1] uppercase">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-[#edf2f7]">
                                            {pickupPersons.data.map((pickupPerson) => (
                                                <tr key={pickupPerson.id} className="module-table-row transition">
                                                    <td className="px-5 py-4">
                                                        <div className="flex items-center gap-3">
                                                            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#eaf3fa] text-sm font-bold text-[#4f7cac]">
                                                                {pickupPerson.initials}
                                                            </div>

                                                            <div className="min-w-0">
                                                                <Link
                                                                    href={`/pickup-persons/${pickupPerson.id}`}
                                                                    className="font-semibold text-[#334e68] transition hover:text-[#4f7cac]"
                                                                >
                                                                    {pickupPerson.full_name}
                                                                </Link>

                                                                <p className="mt-1 inline-flex items-center gap-1.5 text-xs text-[#829ab1]">
                                                                    <IdCard aria-hidden="true" className="size-3.5" />

                                                                    {pickupPerson.identity_number ?? 'Identitas belum diisi'}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <p className="inline-flex items-center gap-2 text-sm font-medium text-[#627d98]">
                                                            <Phone aria-hidden="true" className="size-4 text-[#9fb3c8]" />

                                                            {pickupPerson.phone}
                                                        </p>

                                                        <p className="mt-1 text-xs text-[#829ab1]">{pickupPerson.email ?? 'Email belum diisi'}</p>
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        {pickupPerson.students.length > 0 ? (
                                                            <div className="space-y-2">
                                                                {pickupPerson.students.map((student) => (
                                                                    <div key={student.id} className="flex flex-wrap items-center gap-2">
                                                                        <Link
                                                                            href={`/students/${student.id}`}
                                                                            className="rounded-lg bg-[#eef6ff] px-2.5 py-1 text-xs font-semibold text-[#4f7cac] transition hover:bg-[#e1effc]"
                                                                        >
                                                                            {student.full_name}
                                                                        </Link>

                                                                        <span className="text-xs text-[#9fb3c8]">
                                                                            {relationshipLabels[student.relationship_type] ??
                                                                                student.relationship_type}
                                                                        </span>

                                                                        {student.is_primary && (
                                                                            <span title="Penjemput utama" aria-label="Penjemput utama">
                                                                                <BadgeCheck aria-hidden="true" className="size-4 text-[#4c9e94]" />
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                ))}

                                                                {pickupPerson.students_count > 3 && (
                                                                    <p className="text-xs text-[#829ab1]">
                                                                        +{pickupPerson.students_count - 3} siswa lainnya
                                                                    </p>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <span className="text-sm text-[#9fb3c8]">Belum terhubung ke siswa</span>
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <span
                                                            className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold ${faceStatusStyles[pickupPerson.face_status]}`}
                                                        >
                                                            {pickupPerson.face_status === 'registered' ? (
                                                                <ScanFace aria-hidden="true" className="size-3.5" />
                                                            ) : (
                                                                <ShieldAlert aria-hidden="true" className="size-3.5" />
                                                            )}

                                                            {faceStatusLabels[pickupPerson.face_status]}
                                                        </span>
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <span
                                                            className={[
                                                                'inline-flex rounded-full border px-3 py-1.5 text-xs font-semibold',
                                                                pickupPerson.is_active
                                                                    ? 'border-[#cfe9e3] bg-[#e8f6f3] text-[#438f86]'
                                                                    : 'border-[#dde5ec] bg-[#f1f5f9] text-[#627d98]',
                                                            ].join(' ')}
                                                        >
                                                            {pickupPerson.is_active ? 'Aktif' : 'Tidak aktif'}
                                                        </span>
                                                    </td>

                                                    <td className="px-5 py-4 text-right">
                                                        <Link
                                                            href={`/pickup-persons/${pickupPerson.id}`}
                                                            title={`Lihat detail ${pickupPerson.full_name}`}
                                                            aria-label={`Lihat detail ${pickupPerson.full_name}`}
                                                            className="module-detail-button inline-flex h-9 items-center justify-center gap-2 rounded-xl border px-3 text-xs font-bold"
                                                        >
                                                            <Eye aria-hidden="true" className="size-4" />
                                                            Detail
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="flex flex-col justify-between gap-4 border-t border-[#edf2f7] px-5 py-4 sm:flex-row sm:items-center">
                                    <p className="text-sm text-[#829ab1]">
                                        Menampilkan {pickupPersons.from ?? 0}–{pickupPersons.to ?? 0} dari {pickupPersons.total} penjemput
                                    </p>

                                    <nav aria-label="Navigasi halaman penjemput" className="flex flex-wrap gap-1">
                                        {pickupPersons.links.map((link, index) => {
                                            const label = paginationLabel(link.label);

                                            if (link.url === null) {
                                                return (
                                                    <span
                                                        key={`${link.label}-${index}`}
                                                        className="inline-flex min-h-9 min-w-9 cursor-not-allowed items-center justify-center rounded-lg border border-[#edf2f7] px-3 text-sm text-[#bcccdc]"
                                                    >
                                                        {label}
                                                    </span>
                                                );
                                            }

                                            return (
                                                <Link
                                                    key={`${link.label}-${index}`}
                                                    href={link.url}
                                                    preserveScroll
                                                    preserveState
                                                    className={[
                                                        'inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg px-3 text-sm font-medium transition',
                                                        link.active
                                                            ? 'bg-[#5b8def] text-white'
                                                            : 'border border-[#e6eef5] bg-white text-[#627d98] hover:bg-[#f7fafc]',
                                                    ].join(' ')}
                                                >
                                                    {label}
                                                </Link>
                                            );
                                        })}
                                    </nav>
                                </div>
                            </>
                        ) : (
                            <div className="module-empty-state px-6 py-16 text-center">
                                <div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-[#f1f6fa] text-[#9fb3c8]">
                                    <UserRoundCheck className="size-8" />
                                </div>

                                <h3 className="mt-5 font-semibold text-[#334e68]">Data penjemput tidak ditemukan</h3>

                                <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-[#829ab1]">
                                    Tidak ada penjemput yang sesuai dengan pencarian atau filter.
                                </p>

                                {hasActiveFilters ? (
                                    <button
                                        type="button"
                                        onClick={resetFilters}
                                        className="module-secondary-button module-empty-action mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold"
                                    >
                                        <X aria-hidden="true" className="size-4" />
                                        Reset Filter
                                    </button>
                                ) : (
                                    permissions.can_manage && (
                                        <Link
                                            href="/pickup-persons/create"
                                            className="module-primary-button module-empty-action mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold text-white"
                                        >
                                            <Plus aria-hidden="true" className="size-4" />
                                            Tambah Penjemput
                                        </Link>
                                    )
                                )}
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}

function SummaryCard({ title, value, description, icon: Icon, tone }: SummaryCardProps) {
    const styles = summaryToneStyles[tone];

    return (
        <article className={`module-summary-card rounded-[24px] border p-5 ${styles.card}`}>
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-medium text-[#627d98]">{title}</p>

                    <p className="mt-2 text-3xl font-bold tracking-tight text-[#243b53]">{value}</p>
                </div>

                <div className={`flex size-11 shrink-0 items-center justify-center rounded-xl shadow-sm ${styles.icon}`}>
                    <Icon aria-hidden="true" className="size-5" />
                </div>
            </div>

            <p className="mt-4 text-xs leading-5 text-[#829ab1]">{description}</p>
        </article>
    );
}
