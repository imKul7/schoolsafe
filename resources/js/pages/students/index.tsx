import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Eye, Filter, GraduationCap, Plus, Search, UserRound, UsersRound, X, type LucideIcon } from 'lucide-react';
import { useMemo, useState, type FormEventHandler } from 'react';

import AppLayout from '@/layouts/app-layout';

type Gender = 'male' | 'female';

type StudentStatus = 'active' | 'inactive' | 'graduated';

interface SchoolClass {
    id: number;
    name: string;
    grade_level: number;
    academic_year: string;
}

interface Student {
    id: number;
    student_number: string;
    nisn: string | null;
    full_name: string;
    gender: Gender;
    date_of_birth: string | null;
    status: StudentStatus;
    initials: string;
    class: SchoolClass;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface StudentsPagination {
    data: Student[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Filters {
    search: string;
    class_id: number | string;
    status: string;
}

interface StudentsIndexProps {
    students: StudentsPagination;
    classes: SchoolClass[];
    filters: Filters;
}

interface SummaryCardProps {
    title: string;
    value: number;
    description: string;
    icon: LucideIcon;
    tone: 'blue' | 'green' | 'yellow';
}

const statusStyles: Record<StudentStatus, string> = {
    active: 'border-[#cfe9e3] bg-[#e8f6f3] text-[#438f86]',
    inactive: 'border-[#dde5ec] bg-[#f1f5f9] text-[#627d98]',
    graduated: 'border-[#dce3f5] bg-[#eef3ff] text-[#5b73b8]',
};

const statusLabels: Record<StudentStatus, string> = {
    active: 'Aktif',
    inactive: 'Tidak aktif',
    graduated: 'Lulus',
};

const genderLabels: Record<Gender, string> = {
    male: 'Laki-laki',
    female: 'Perempuan',
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

function formatPaginationLabel(label: string): string {
    return label.replace('&laquo; Previous', 'Sebelumnya').replace('Next &raquo;', 'Berikutnya').replace('&laquo;', '‹').replace('&raquo;', '›');
}

export default function StudentsIndex({ students, classes, filters }: StudentsIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const [classId, setClassId] = useState(String(filters.class_id ?? ''));

    const [status, setStatus] = useState(filters.status ?? '');

    const hasActiveFilters = useMemo(() => search.trim() !== '' || classId !== '' || status !== '', [search, classId, status]);

    const submitFilters: FormEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();

        router.get(
            '/students',
            {
                search: search.trim(),
                class_id: classId,
                status,
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
        setClassId('');
        setStatus('');

        router.get(
            '/students',
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
            <Head title="Data Siswa" />

            <main className="min-h-full bg-[#eef4fb]">
                <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 md:p-6">
                    {/* Header */}
                    <section className="relative overflow-hidden rounded-[32px] border border-[#dce8f4] bg-gradient-to-r from-[#eef6ff] via-[#f9fcff] to-[#fff9f0] p-6 shadow-2xl shadow-[#d9e7f6]/70 transition-transform duration-200 hover:-translate-y-0.5 md:p-8">
                        <div className="absolute -top-24 -right-16 h-56 w-56 rounded-full bg-white/60 blur-3xl" />

                        <div className="relative flex flex-col justify-between gap-5 md:flex-row md:items-center">
                            <div className="flex items-start gap-4">
                                <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-[24px] bg-white text-[#5b8def] shadow-lg shadow-[#dfe8ff]/90">
                                    <UsersRound aria-hidden="true" className="size-6" />
                                </div>

                                <div>
                                    <h1 className="text-3xl font-bold tracking-tight text-[#1f334f] md:text-4xl">Data Siswa</h1>

                                    <p className="mt-2 max-w-xl text-sm leading-6 text-[#526a88]">
                                        Kelola identitas, kelas, status, dan informasi siswa yang terdaftar di SchoolSafe.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/students/create"
                                className="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#5b8def] to-[#3b6bdc] px-5 text-sm font-semibold text-white shadow-lg shadow-blue-200/45 transition duration-200 hover:-translate-y-0.5 hover:bg-[#4c7fd9] focus-visible:ring-2 focus-visible:ring-[#bdd7f3] focus-visible:outline-none"
                            >
                                <Plus aria-hidden="true" className="size-4" />
                                Tambah Siswa
                            </Link>
                        </div>
                    </section>

                    {/* Ringkasan */}
                    <section aria-label="Ringkasan data siswa" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <SummaryCard
                            title="Total Siswa"
                            value={students.total}
                            description="Seluruh siswa sesuai filter"
                            icon={UsersRound}
                            tone="blue"
                        />

                        <SummaryCard
                            title="Kelas Aktif"
                            value={classes.length}
                            description="Kelas yang dapat dipilih"
                            icon={GraduationCap}
                            tone="green"
                        />

                        <SummaryCard
                            title="Ditampilkan"
                            value={students.data.length}
                            description={`Halaman ${students.current_page} dari ${students.last_page}`}
                            icon={UserRound}
                            tone="yellow"
                        />
                    </section>

                    {/* Filter */}
                    <section className="rounded-3xl border border-[#dfe8f2] bg-white p-5 shadow-sm shadow-[#dce4ee]/50">
                        <div className="mb-4 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                            <div>
                                <h2 className="font-semibold text-[#1f334f]">Cari dan Filter</h2>

                                <p className="mt-1 text-sm text-[#607289]">Temukan siswa berdasarkan nama, nomor siswa, NISN, kelas, atau status.</p>
                            </div>

                            {hasActiveFilters && (
                                <span className="inline-flex w-fit items-center rounded-full bg-[#eef6ff] px-3 py-1.5 text-xs font-semibold text-[#4f7cac]">
                                    Filter aktif
                                </span>
                            )}
                        </div>

                        <form onSubmit={submitFilters} className="grid gap-3 lg:grid-cols-[minmax(260px,1fr)_210px_190px_auto]" role="search">
                            <div className="relative">
                                <Search
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]"
                                />

                                <input
                                    type="search"
                                    value={search}
                                    onChange={(event) => setSearch(event.currentTarget.value)}
                                    autoComplete="off"
                                    placeholder="Cari nama, nomor siswa, atau NISN..."
                                    aria-label="Cari siswa"
                                    className="h-11 w-full rounded-xl border border-[#d9e5ee] bg-[#fbfdff] pr-4 pl-10 text-sm text-[#334e68] transition outline-none placeholder:text-[#bcccdc] focus:border-[#7fa9d8] focus:ring-2 focus:ring-[#dcebf8]"
                                />
                            </div>

                            <select
                                value={classId}
                                onChange={(event) => setClassId(event.currentTarget.value)}
                                aria-label="Filter kelas"
                                className="h-11 rounded-xl border border-[#d9e5ee] bg-[#fbfdff] px-3 text-sm text-[#486581] transition outline-none focus:border-[#7fa9d8] focus:ring-2 focus:ring-[#dcebf8]"
                            >
                                <option value="">Semua kelas</option>

                                {classes.map((schoolClass) => (
                                    <option key={schoolClass.id} value={schoolClass.id}>
                                        Kelas {schoolClass.name} · {schoolClass.academic_year}
                                    </option>
                                ))}
                            </select>

                            <select
                                value={status}
                                onChange={(event) => setStatus(event.currentTarget.value)}
                                aria-label="Filter status"
                                className="h-11 rounded-xl border border-[#d9e5ee] bg-[#fbfdff] px-3 text-sm text-[#486581] transition outline-none focus:border-[#7fa9d8] focus:ring-2 focus:ring-[#dcebf8]"
                            >
                                <option value="">Semua status</option>

                                <option value="active">Aktif</option>

                                <option value="inactive">Tidak aktif</option>

                                <option value="graduated">Lulus</option>
                            </select>

                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    className="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#5b8def] to-[#3b6bdc] px-4 text-sm font-semibold text-white shadow-lg shadow-blue-200/40 transition duration-200 hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-[#bdd7f3] focus-visible:outline-none"
                                >
                                    <Filter aria-hidden="true" className="size-4" />
                                    Terapkan
                                </button>

                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    disabled={!hasActiveFilters}
                                    aria-label="Reset filter"
                                    title="Reset filter"
                                    className="inline-flex h-11 items-center justify-center rounded-2xl border border-[#d7e2ed] bg-white px-3 text-[#5e6c7f] transition duration-200 hover:border-[#c3d5ec] hover:bg-[#f7fbff] focus-visible:ring-2 focus-visible:ring-[#bdd7f3] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <X aria-hidden="true" className="size-4" />
                                </button>
                            </div>
                        </form>
                    </section>

                    {/* Tabel */}
                    <section className="overflow-hidden rounded-2xl border border-[#e6eef5] bg-white shadow-sm">
                        <div className="flex flex-col justify-between gap-2 border-b border-[#edf2f7] px-5 py-4 sm:flex-row sm:items-center">
                            <div>
                                <h2 className="font-bold text-[#243b53]">Daftar Siswa</h2>

                                <p className="mt-1 text-sm text-[#829ab1]">Menampilkan data siswa yang sesuai pencarian dan filter.</p>
                            </div>

                            <p className="text-sm font-medium text-[#627d98]">{students.total} data</p>
                        </div>

                        {students.data.length > 0 ? (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[920px] text-left text-sm">
                                        <thead className="bg-[#f7faff]">
                                            <tr className="border-b border-[#e6eef5]">
                                                <th
                                                    scope="col"
                                                    className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase"
                                                >
                                                    Siswa
                                                </th>

                                                <th
                                                    scope="col"
                                                    className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase"
                                                >
                                                    Nomor Siswa
                                                </th>

                                                <th
                                                    scope="col"
                                                    className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase"
                                                >
                                                    Kelas
                                                </th>

                                                <th
                                                    scope="col"
                                                    className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase"
                                                >
                                                    Jenis Kelamin
                                                </th>

                                                <th
                                                    scope="col"
                                                    className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase"
                                                >
                                                    Status
                                                </th>

                                                <th
                                                    scope="col"
                                                    className="px-5 py-4 text-right text-xs font-semibold tracking-wide text-[#829ab1] uppercase"
                                                >
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-[#edf2f7]">
                                            {students.data.map((student) => (
                                                <tr key={student.id} className="group transition duration-200 hover:bg-[#f4f8ff]">
                                                    <td className="px-5 py-4">
                                                        <div className="flex items-center gap-3">
                                                            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#eaf3fa] text-sm font-bold text-[#4f7cac] transition group-hover:bg-[#dcecff]">
                                                                {student.initials}
                                                            </div>

                                                            <div className="min-w-0">
                                                                <Link
                                                                    href={`/students/${student.id}`}
                                                                    className="block truncate font-semibold text-[#334e68] transition hover:text-[#4f7cac]"
                                                                >
                                                                    {student.full_name}
                                                                </Link>

                                                                <p className="mt-0.5 truncate text-xs text-[#829ab1]">NISN: {student.nisn ?? '-'}</p>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <span className="font-mono text-sm font-medium text-[#627d98]">{student.student_number}</span>
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <div className="inline-flex flex-col rounded-xl bg-[#eef6ff] px-3 py-2">
                                                            <span className="inline-flex items-center gap-2 text-sm font-semibold text-[#4f7cac]">
                                                                <GraduationCap aria-hidden="true" className="size-4" />
                                                                Kelas {student.class.name}
                                                            </span>

                                                            <span className="mt-0.5 pl-6 text-[11px] text-[#829ab1]">
                                                                {student.class.academic_year}
                                                            </span>
                                                        </div>
                                                    </td>

                                                    <td className="px-5 py-4 text-sm text-[#627d98]">{genderLabels[student.gender]}</td>

                                                    <td className="px-5 py-4">
                                                        <span
                                                            className={`inline-flex rounded-full border px-3 py-1.5 text-xs font-semibold ${statusStyles[student.status]}`}
                                                        >
                                                            {statusLabels[student.status]}
                                                        </span>
                                                    </td>

                                                    <td className="px-5 py-4 text-right">
                                                        <Link
                                                            href={`/students/${student.id}`}
                                                            title={`Lihat detail ${student.full_name}`}
                                                            aria-label={`Lihat detail ${student.full_name}`}
                                                            className="inline-flex h-9 items-center justify-center gap-2 rounded-xl border border-[#dce6ee] bg-white px-3 text-xs font-semibold text-[#627d98] transition hover:border-[#bdd7f0] hover:bg-[#eef6ff] hover:text-[#4f7cac] focus-visible:ring-2 focus-visible:ring-[#dcebf8] focus-visible:outline-none"
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

                                {/* Pagination */}
                                <div className="flex flex-col justify-between gap-4 border-t border-[#edf2f7] px-5 py-4 sm:flex-row sm:items-center">
                                    <p className="text-sm text-[#829ab1]">
                                        Menampilkan {students.from ?? 0}–{students.to ?? 0} dari {students.total} siswa
                                    </p>

                                    <nav aria-label="Navigasi halaman siswa" className="flex flex-wrap gap-1">
                                        {students.links.map((link, index) => {
                                            const label = formatPaginationLabel(link.label);

                                            if (link.url === null) {
                                                return (
                                                    <span
                                                        key={`${link.label}-${index}`}
                                                        aria-disabled="true"
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
                                                    aria-current={link.active ? 'page' : undefined}
                                                    className={[
                                                        'inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg px-3 text-sm font-medium transition',
                                                        link.active
                                                            ? 'bg-[#5b8def] text-white shadow-sm'
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
                            <div className="px-6 py-16 text-center">
                                <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-[28px] bg-[#eef4fb] text-[#5b8def] shadow-sm">
                                    <UsersRound aria-hidden="true" className="size-8" />
                                </div>

                                <h3 className="mt-5 text-xl font-semibold text-[#243b53]">Data siswa tidak ditemukan</h3>

                                <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-[#627d98]">
                                </p>

                                {hasActiveFilters ? (
                                    <button
                                        type="button"
                                        onClick={resetFilters}
                                        className="mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#d9e5ee] bg-white px-4 text-sm font-semibold text-[#627d98] transition hover:bg-[#f7fafc]"
                                    >
                                        <X className="size-4" />
                                        Reset Filter
                                    </button>
                                ) : (
                                    <Link
                                        href="/students/create"
                                        className="mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#5b8def] px-4 text-sm font-semibold text-white transition hover:bg-[#4c7fd9]"
                                    >
                                        <Plus className="size-4" />
                                        Tambah Siswa
                                    </Link>
                                )}
                            </div>
                        )}
                    </section>

                    {/* Bantuan */}
                    <section className="flex flex-col justify-between gap-4 rounded-2xl border border-[#dceaf5] bg-[#eef7fc] p-5 sm:flex-row sm:items-center">
                        <div className="flex items-start gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-[#5b8def] shadow-sm">
                                <UserRound className="size-5" />
                            </div>

                            <div>
                                <h2 className="font-semibold text-[#334e68]">Lihat informasi lengkap siswa</h2>

                                <p className="mt-1 text-sm leading-6 text-[#627d98]">
                                    Gunakan tombol Detail untuk melihat, mengedit, mengaktifkan, menonaktifkan, atau mengarsipkan siswa.
                                </p>
                            </div>
                        </div>

                        {students.data.length > 0 && (
                            <Link
                                href={`/students/${students.data[0].id}`}
                                className="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-[#4f7cac] transition hover:text-[#37658f]"
                            >
                                Lihat siswa pertama
                                <ArrowRight className="size-4" />
                            </Link>
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
        <article className={`rounded-[28px] border p-5 shadow-sm shadow-[#dce4ee]/50 transition duration-200 hover:-translate-y-0.5 hover:shadow-lg ${styles.card}`}>
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold text-[#546d87]">{title}</p>

                    <p className="mt-3 text-3xl font-bold tracking-tight text-[#1f334f]">{value}</p>
                </div>

                <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-3xl ${styles.icon}`}>
                    <Icon aria-hidden="true" className="size-5" />
                </div>
            </div>

            <p className="mt-4 text-xs leading-5 text-[#728199]">{description}</p>
        </article>
    );
}
