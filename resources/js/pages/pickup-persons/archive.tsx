import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Archive, ArrowLeft, IdCard, Phone, RefreshCcw, ScanFace, Search, ShieldAlert, Trash2, UserRoundCheck, X } from 'lucide-react';
import { type FormEventHandler, useMemo, useState } from 'react';

import AppLayout from '@/layouts/app-layout';

type FaceStatus = 'not_registered' | 'registered' | 'needs_update';

interface ArchivedPickupPerson {
    id: number;
    full_name: string;
    initials: string;
    identity_number: string | null;
    phone: string;
    email: string | null;
    face_status: FaceStatus;
    is_active: boolean;
    deleted_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface ArchivedPickupPersonsPagination {
    data: ArchivedPickupPerson[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface ArchiveFilters {
    search: string;
}

interface PickupPersonArchiveProps {
    pickupPersons: ArchivedPickupPersonsPagination;
    filters: ArchiveFilters;
}

type ProcessingAction = 'restore' | 'force-delete' | null;

interface ProcessingState {
    id: number | null;
    action: ProcessingAction;
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

function paginationLabel(label: string): string {
    return label.replace('&laquo; Previous', 'Sebelumnya').replace('Next &raquo;', 'Berikutnya').replace('&laquo;', '‹').replace('&raquo;', '›');
}

function formatArchivedDate(value: string | null): string {
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

export default function PickupPersonArchive({ pickupPersons, filters }: PickupPersonArchiveProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const [processingState, setProcessingState] = useState<ProcessingState>({
        id: null,
        action: null,
    });

    const hasActiveFilter = useMemo(() => search.trim() !== '', [search]);

    const isProcessing = (pickupPersonId: number, action: ProcessingAction): boolean =>
        processingState.id === pickupPersonId && processingState.action === action;

    const submitSearch: FormEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();

        router.get(
            '/pickup-persons/archive',
            {
                search: search.trim(),
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const resetSearch = (): void => {
        setSearch('');

        router.get(
            '/pickup-persons/archive',
            {},
            {
                preserveState: false,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const restorePickupPerson = (pickupPerson: ArchivedPickupPerson): void => {
        const confirmed = window.confirm(`Pulihkan data ${pickupPerson.full_name} dari arsip?`);

        if (!confirmed) {
            return;
        }

        router.patch(
            `/pickup-persons/archive/${pickupPerson.id}/restore`,
            {},
            {
                preserveScroll: true,

                onStart: () => {
                    setProcessingState({
                        id: pickupPerson.id,
                        action: 'restore',
                    });
                },

                onFinish: () => {
                    setProcessingState({
                        id: null,
                        action: null,
                    });
                },
            },
        );
    };

    const forceDeletePickupPerson = (pickupPerson: ArchivedPickupPerson): void => {
        const firstConfirmation = window.confirm(
            `Hapus permanen data ${pickupPerson.full_name}?\n\nData yang dihapus permanen tidak dapat dipulihkan.`,
        );

        if (!firstConfirmation) {
            return;
        }

        const secondConfirmation = window.confirm(
            `Konfirmasi terakhir: seluruh hubungan siswa milik ${pickupPerson.full_name} juga akan dihapus. Lanjutkan?`,
        );

        if (!secondConfirmation) {
            return;
        }

        router.delete(`/pickup-persons/archive/${pickupPerson.id}/force-delete`, {
            preserveScroll: true,

            onStart: () => {
                setProcessingState({
                    id: pickupPerson.id,
                    action: 'force-delete',
                });
            },

            onFinish: () => {
                setProcessingState({
                    id: null,
                    action: null,
                });
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Arsip Penjemput" />

            <main className="min-h-full bg-[#f8fafc]">
                <div className="mx-auto flex w-full max-w-[1500px] flex-col gap-6 p-4 md:p-6">
                    <Link
                        href="/pickup-persons"
                        className="inline-flex w-fit items-center gap-2 text-sm font-semibold text-[#627d98] transition hover:text-[#4f7cac]"
                    >
                        <ArrowLeft aria-hidden="true" className="size-4" />
                        Kembali ke Data Penjemput
                    </Link>

                    <section className="relative overflow-hidden rounded-[28px] border border-[#eadfcb] bg-gradient-to-r from-[#fff8eb] via-[#fffaf4] to-[#f5f8fc] p-6 shadow-sm md:p-8">
                        <div className="absolute -top-20 -right-16 size-56 rounded-full bg-white/60 blur-3xl" />

                        <div className="relative flex flex-col justify-between gap-5 md:flex-row md:items-center">
                            <div className="flex items-start gap-4">
                                <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white text-[#b88a22] shadow-sm">
                                    <Archive aria-hidden="true" className="size-6" />
                                </div>

                                <div>
                                    <p className="text-xs font-semibold tracking-[0.16em] text-[#a18a5f] uppercase">Data Penjemput</p>

                                    <h1 className="mt-1 text-2xl font-bold tracking-tight text-[#243b53] md:text-3xl">Arsip Penjemput</h1>

                                    <p className="mt-2 max-w-2xl text-sm leading-6 text-[#627d98]">
                                        Kelola data penjemput yang telah diarsipkan. Data dapat dipulihkan atau dihapus secara permanen.
                                    </p>
                                </div>
                            </div>

                            <div className="inline-flex w-fit items-center gap-2 rounded-full border border-[#eadfcb] bg-white/80 px-4 py-2 text-sm font-semibold text-[#8b681c] shadow-sm">
                                <Archive aria-hidden="true" className="size-4" />
                                {pickupPersons.total} data arsip
                            </div>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-[#efd7d7] bg-[#fff6f6] p-4 shadow-sm">
                        <div className="flex items-start gap-3">
                            <AlertTriangle aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-[#c45d5d]" />

                            <div>
                                <h2 className="text-sm font-semibold text-[#a64f4f]">Perhatian</h2>

                                <p className="mt-1 text-sm leading-6 text-[#b46363]">
                                    Hapus permanen tidak dapat dibatalkan. Gunakan fitur tersebut hanya ketika data benar-benar tidak diperlukan lagi.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm">
                        <div className="mb-4">
                            <h2 className="font-bold text-[#243b53]">Cari Data Arsip</h2>

                            <p className="mt-1 text-sm leading-6 text-[#829ab1]">
                                Cari berdasarkan nama, identitas, nomor telepon, atau email penjemput.
                            </p>
                        </div>

                        <form onSubmit={submitSearch} role="search" className="flex flex-col gap-3 sm:flex-row">
                            <div className="relative flex-1">
                                <Search
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#9fb3c8]"
                                />

                                <input
                                    type="search"
                                    value={search}
                                    onChange={(event) => setSearch(event.currentTarget.value)}
                                    placeholder="Cari data penjemput yang diarsipkan..."
                                    aria-label="Cari arsip penjemput"
                                    className="h-11 w-full rounded-xl border border-[#d9e5ee] bg-[#fbfdff] pr-4 pl-10 text-sm text-[#334e68] transition outline-none placeholder:text-[#bcccdc] focus:border-[#7fa9d8] focus:ring-2 focus:ring-[#dcebf8]"
                                />
                            </div>

                            <button
                                type="submit"
                                className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#5b8def] px-5 text-sm font-semibold text-white transition hover:bg-[#4c7fd9]"
                            >
                                <Search aria-hidden="true" className="size-4" />
                                Cari
                            </button>

                            <button
                                type="button"
                                onClick={resetSearch}
                                disabled={!hasActiveFilter}
                                className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[#d9e5ee] bg-white px-4 text-sm font-semibold text-[#627d98] transition hover:bg-[#f7fafc] disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                <X aria-hidden="true" className="size-4" />
                                Reset
                            </button>
                        </form>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-[#e6eef5] bg-white shadow-sm">
                        <div className="flex flex-col justify-between gap-2 border-b border-[#edf2f7] px-5 py-4 sm:flex-row sm:items-center">
                            <div>
                                <h2 className="font-bold text-[#243b53]">Daftar Arsip</h2>

                                <p className="mt-1 text-sm text-[#829ab1]">Data penjemput yang telah dipindahkan dari daftar utama.</p>
                            </div>

                            <p className="text-sm font-medium text-[#627d98]">{pickupPersons.total} data</p>
                        </div>

                        {pickupPersons.data.length > 0 ? (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[1100px]">
                                        <thead className="bg-[#f8fbfd]">
                                            <tr className="border-b border-[#e6eef5]">
                                                <th className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase">
                                                    Penjemput
                                                </th>

                                                <th className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase">
                                                    Kontak
                                                </th>

                                                <th className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase">
                                                    Status wajah
                                                </th>

                                                <th className="px-5 py-4 text-left text-xs font-semibold tracking-wide text-[#829ab1] uppercase">
                                                    Diarsipkan
                                                </th>

                                                <th className="px-5 py-4 text-right text-xs font-semibold tracking-wide text-[#829ab1] uppercase">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-[#edf2f7]">
                                            {pickupPersons.data.map((pickupPerson) => (
                                                <tr key={pickupPerson.id} className="transition hover:bg-[#fbfdff]">
                                                    <td className="px-5 py-4">
                                                        <div className="flex items-center gap-3">
                                                            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#fff4df] text-sm font-bold text-[#a5791e]">
                                                                {pickupPerson.initials}
                                                            </div>

                                                            <div className="min-w-0">
                                                                <p className="font-semibold text-[#334e68]">{pickupPerson.full_name}</p>

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
                                                        <p className="text-sm font-medium text-[#627d98]">
                                                            {formatArchivedDate(pickupPerson.deleted_at)}
                                                        </p>

                                                        <p className="mt-1 text-xs text-[#9fb3c8]">
                                                            Status sebelumnya: {pickupPerson.is_active ? 'Aktif' : 'Tidak aktif'}
                                                        </p>
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <div className="flex justify-end gap-2">
                                                            <button
                                                                type="button"
                                                                onClick={() => restorePickupPerson(pickupPerson)}
                                                                disabled={processingState.id !== null}
                                                                className="inline-flex h-9 items-center justify-center gap-2 rounded-xl border border-[#cfe9e3] bg-[#eef9f6] px-3 text-xs font-semibold text-[#438f86] transition hover:bg-[#e1f3ef] disabled:cursor-not-allowed disabled:opacity-50"
                                                            >
                                                                {isProcessing(pickupPerson.id, 'restore') ? (
                                                                    <>
                                                                        <span className="size-3.5 animate-spin rounded-full border-2 border-[#438f86]/30 border-t-[#438f86]" />
                                                                        Memulihkan...
                                                                    </>
                                                                ) : (
                                                                    <>
                                                                        <RefreshCcw aria-hidden="true" className="size-4" />
                                                                        Pulihkan
                                                                    </>
                                                                )}
                                                            </button>

                                                            <button
                                                                type="button"
                                                                onClick={() => forceDeletePickupPerson(pickupPerson)}
                                                                disabled={processingState.id !== null}
                                                                className="inline-flex h-9 items-center justify-center gap-2 rounded-xl border border-[#f0d0d0] bg-[#fff5f5] px-3 text-xs font-semibold text-[#c95b5b] transition hover:bg-[#ffeaea] disabled:cursor-not-allowed disabled:opacity-50"
                                                            >
                                                                {isProcessing(pickupPerson.id, 'force-delete') ? (
                                                                    <>
                                                                        <span className="size-3.5 animate-spin rounded-full border-2 border-[#c95b5b]/30 border-t-[#c95b5b]" />
                                                                        Menghapus...
                                                                    </>
                                                                ) : (
                                                                    <>
                                                                        <Trash2 aria-hidden="true" className="size-4" />
                                                                        Hapus Permanen
                                                                    </>
                                                                )}
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="flex flex-col justify-between gap-4 border-t border-[#edf2f7] px-5 py-4 sm:flex-row sm:items-center">
                                    <p className="text-sm text-[#829ab1]">
                                        Menampilkan {pickupPersons.from ?? 0}–{pickupPersons.to ?? 0} dari {pickupPersons.total} data arsip
                                    </p>

                                    <nav aria-label="Navigasi halaman arsip penjemput" className="flex flex-wrap gap-1">
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
                            <div className="px-6 py-16 text-center">
                                <div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-[#f3f6f9] text-[#9fb3c8]">
                                    {hasActiveFilter ? <Search className="size-8" /> : <Archive className="size-8" />}
                                </div>

                                <h3 className="mt-5 font-semibold text-[#334e68]">
                                    {hasActiveFilter ? 'Data arsip tidak ditemukan' : 'Arsip masih kosong'}
                                </h3>

                                <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-[#829ab1]">
                                    {hasActiveFilter
                                        ? 'Tidak ada data penjemput yang sesuai dengan pencarian.'
                                        : 'Data penjemput yang diarsipkan akan muncul di halaman ini.'}
                                </p>

                                {hasActiveFilter ? (
                                    <button
                                        type="button"
                                        onClick={resetSearch}
                                        className="mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#d9e5ee] bg-white px-4 text-sm font-semibold text-[#627d98] transition hover:bg-[#f7fafc]"
                                    >
                                        <X aria-hidden="true" className="size-4" />
                                        Reset Pencarian
                                    </button>
                                ) : (
                                    <Link
                                        href="/pickup-persons"
                                        className="mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#5b8def] px-4 text-sm font-semibold text-white transition hover:bg-[#4c7fd9]"
                                    >
                                        <UserRoundCheck aria-hidden="true" className="size-4" />
                                        Kembali ke Data Penjemput
                                    </Link>
                                )}
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}
