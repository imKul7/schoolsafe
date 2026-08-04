import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    CheckCircle2,
    CircleAlert,
    Clock3,
    RefreshCw,
    ScanFace,
    ShieldAlert,
    ShieldCheck,
    UserCheck,
    Users,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import AppLayout from '@/layouts/app-layout';

type StatTone = 'blue' | 'green' | 'yellow' | 'red';

type RefreshFeedback = {
    type: 'success' | 'error';
    message: string;
} | null;

interface DashboardPermissions {
    can_open_face_scanner: boolean;
    can_view_pickup_history: boolean;
    can_view_gate_activity: boolean;
}

interface DashboardStatistics {
    active_students: number;
    active_pickup_persons: number;
    registered_faces: number;
    pickup_events_today: number;
    confirmed_today: number;
    cancelled_today: number;
}

interface DashboardActivity {
    id: number;
    pickup_person_name: string;
    status: string;
    verification_method: string;
    confirmed_at: string | null;
    student_count: number;
}

interface DashboardData {
    has_school: boolean;
    timezone: string;
    generated_at: string;
    permissions: DashboardPermissions;
    statistics: DashboardStatistics;
    recent_activities: DashboardActivity[];
}

interface DashboardPageProps {
    dashboard: DashboardData;
}

interface StatCardProps {
    title: string;
    value: string;
    description: string;
    icon: LucideIcon;
    tone: StatTone;
}

const toneStyles: Record<
    StatTone,
    {
        card: string;
        icon: string;
        indicator: string;
    }
> = {
    blue: {
        card: 'border-[#dceaf8] bg-[#eef6ff]',
        icon: 'bg-[#dcecff] text-[#4f7cac]',
        indicator: 'text-[#4f7cac]',
    },

    green: {
        card: 'border-[#d9eee9] bg-[#eef9f6]',
        icon: 'bg-[#d9f1eb] text-[#4c9e94]',
        indicator: 'text-[#4c9e94]',
    },

    yellow: {
        card: 'border-[#f5e8bd] bg-[#fff9e9]',
        icon: 'bg-[#fdf0c8] text-[#b88a22]',
        indicator: 'text-[#b88a22]',
    },

    red: {
        card: 'border-[#f2dada] bg-[#fff2f2]',
        icon: 'bg-[#fce2e2] text-[#cf6464]',
        indicator: 'text-[#cf6464]',
    },
};

const numberFormatter = new Intl.NumberFormat('id-ID');

function formatNumber(value: number): string {
    return numberFormatter.format(Number.isFinite(value) ? value : 0);
}

function percentage(value: number, total: number): number {
    if (!Number.isFinite(value) || !Number.isFinite(total) || total <= 0) {
        return 0;
    }

    return Math.min(100, Math.max(0, Math.round((value / total) * 100)));
}

function activityInitials(name: string): string {
    const initials = name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase();

    return initials || 'PJ';
}

function activityDisplayName(name: string): string {
    const normalizedName = name.trim();

    return normalizedName !== '' ? normalizedName : 'Penjemput tidak diketahui';
}

function activityStatusLabel(status: string): string {
    if (status === 'confirmed') {
        return 'Terkonfirmasi';
    }

    if (status === 'cancelled') {
        return 'Dibatalkan';
    }

    return 'Status lainnya';
}

function activityStatusStyle(status: string): string {
    if (status === 'confirmed') {
        return 'bg-[#e8f6f3] text-[#4c9e94]';
    }

    if (status === 'cancelled') {
        return 'bg-[#fff0f0] text-[#cf6464]';
    }

    return 'bg-[#fff9e9] text-[#a77b18]';
}

function verificationMethodLabel(method: string): string {
    if (method === 'face' || method === 'face_recognition') {
        return 'Verifikasi wajah';
    }

    if (method === 'manual') {
        return 'Verifikasi manual';
    }

    const normalizedMethod = method.trim().replaceAll('_', ' ');

    return normalizedMethod !== '' ? normalizedMethod : 'Metode tidak diketahui';
}

function formatActivityTime(value: string | null, timezone: string): string {
    if (!value) {
        return 'Waktu tidak tersedia';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Waktu tidak tersedia';
    }

    const options: Intl.DateTimeFormatOptions = {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: timezone,
    };

    try {
        return new Intl.DateTimeFormat('id-ID', options).format(date);
    } catch {
        return new Intl.DateTimeFormat('id-ID', {
            ...options,
            timeZone: 'UTC',
        }).format(date);
    }
}

function formatLastUpdated(value: string, timezone: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Waktu pembaruan tidak tersedia';
    }

    const options: Intl.DateTimeFormatOptions = {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        timeZone: timezone,
    };

    try {
        return new Intl.DateTimeFormat('id-ID', options).format(date);
    } catch {
        return new Intl.DateTimeFormat('id-ID', {
            ...options,
            timeZone: 'UTC',
        }).format(date);
    }
}

function StatCard({ title, value, description, icon: Icon, tone }: StatCardProps) {
    const styles = toneStyles[tone];

    return (
        <article className={`rounded-[28px] border p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg ${styles.card}`}>
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <p className="text-sm font-semibold text-[#556f8c]">{title}</p>

                    <p className="mt-3 text-3xl font-bold tracking-tight text-[#1f314c]">{value}</p>
                </div>

                <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-3xl ${styles.icon}`}>
                    <Icon className="size-5" aria-hidden="true" />
                </div>
            </div>

            <p className={`mt-4 flex items-center gap-1.5 text-xs font-medium ${styles.indicator}`}>
                <Activity className="size-3.5" aria-hidden="true" />
                {description}
            </p>
        </article>
    );
}

export default function Dashboard({ dashboard }: DashboardPageProps) {
    const [isRefreshing, setIsRefreshing] = useState(false);

    const [refreshFeedback, setRefreshFeedback] = useState<RefreshFeedback>(null);

    const statistics = dashboard.statistics;

    const permissions = dashboard.permissions;

    const registeredFacePercentage = percentage(statistics.registered_faces, statistics.active_pickup_persons);

    const confirmedPercentage = percentage(statistics.confirmed_today, statistics.pickup_events_today);

    const faceProgressDegrees = registeredFacePercentage * 3.6;

    useEffect(() => {
        if (refreshFeedback?.type !== 'success') {
            return;
        }

        const timeoutId = window.setTimeout(() => {
            setRefreshFeedback(null);
        }, 5000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [refreshFeedback]);

    const refreshDashboard = (): void => {
        if (isRefreshing) {
            return;
        }

        let requestHandled = false;

        router.reload({
            only: ['dashboard'],

            onStart: () => {
                setIsRefreshing(true);

                setRefreshFeedback(null);
            },

            onSuccess: () => {
                requestHandled = true;

                setRefreshFeedback({
                    type: 'success',
                    message: 'Data dashboard berhasil diperbarui.',
                });
            },

            onError: () => {
                requestHandled = true;

                setRefreshFeedback({
                    type: 'error',
                    message: 'Data dashboard gagal diperbarui. Silakan coba kembali.',
                });
            },

            onFinish: () => {
                setIsRefreshing(false);

                if (!requestHandled) {
                    setRefreshFeedback({
                        type: 'error',
                        message: 'Pembaruan dashboard tidak selesai. Periksa koneksi lalu coba kembali.',
                    });
                }
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Dashboard" />

            <main className="min-h-full bg-[#eef4fb]" aria-busy={isRefreshing}>
                <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 md:p-6">
                    <section className="relative overflow-hidden rounded-[32px] border border-[#dce8f5] bg-gradient-to-r from-[#eef6ff] via-[#f7fbff] to-[#fffaf0] p-6 shadow-2xl shadow-[#d8e6f5]/70 transition-transform duration-200 hover:-translate-y-0.5 md:p-8">
                        <div className="absolute -top-24 -right-10 h-56 w-56 rounded-full bg-white/60 blur-3xl" aria-hidden="true" />

                        <div className="absolute -bottom-28 left-1/3 h-52 w-52 rounded-full bg-[#ccece5]/40 blur-3xl" aria-hidden="true" />

                        <div className="relative z-10 flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                            <div>
                                <span className="inline-flex items-center gap-2 rounded-full border border-white bg-white/75 px-3 py-1.5 text-xs font-semibold text-[#4f7cac] shadow-sm backdrop-blur-sm">
                                    {dashboard.has_school ? (
                                        <CheckCircle2 className="size-3.5 text-[#64b6ac]" aria-hidden="true" />
                                    ) : (
                                        <ShieldAlert className="size-3.5 text-[#cf6464]" aria-hidden="true" />
                                    )}

                                    {dashboard.has_school ? 'Data sekolah terhubung' : 'Agregasi lintas sekolah dinonaktifkan'}
                                </span>

                                <h1 className="mt-4 text-3xl font-bold tracking-tight text-[#1f314c] sm:text-4xl">Selamat datang di SchoolSafe 👋</h1>

                                <p className="mt-3 max-w-2xl text-base leading-7 text-[#4f6b85]">
                                    {dashboard.has_school
                                        ? 'Pantau data siswa, penjemput, biometrik, dan transaksi penjemputan sekolah melalui satu dashboard.'
                                        : 'Akun ini tidak terhubung dengan sekolah tertentu sehingga statistik lintas sekolah tidak ditampilkan.'}
                                </p>
                            </div>

                            <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                                {permissions.can_open_face_scanner && (
                                    <Link
                                        href="/gate/face-verification"
                                        prefetch
                                        className="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#5b8def] to-[#3b6cdb] px-5 text-sm font-semibold text-white shadow-lg shadow-blue-200/50 transition duration-200 hover:-translate-y-0.5 hover:bg-[#4e83e5] focus-visible:ring-2 focus-visible:ring-[#5b8def] focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <ScanFace className="size-5" aria-hidden="true" />
                                        Face Scanner
                                    </Link>
                                )}

                                <button
                                    type="button"
                                    onClick={refreshDashboard}
                                    disabled={isRefreshing}
                                    aria-label={isRefreshing ? 'Memperbarui data dashboard' : 'Perbarui data dashboard'}
                                    className="inline-flex h-12 items-center justify-center gap-2 rounded-2xl border border-[#dcebf8] bg-white px-4 text-sm font-semibold text-[#4f7cac] shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-[#b6d4f1] hover:bg-[#fafcff] focus-visible:ring-2 focus-visible:ring-[#5b8def] focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <RefreshCw className={`size-4 ${isRefreshing ? 'animate-spin' : ''}`} aria-hidden="true" />

                                    <span>{isRefreshing ? 'Memperbarui...' : 'Perbarui'}</span>
                                </button>

                                <div className="flex min-h-12 flex-col justify-center rounded-2xl border border-white bg-white/80 px-4 py-2 text-[#627d98] shadow-sm backdrop-blur-sm">
                                    <span className="inline-flex items-center gap-2 text-sm font-semibold">
                                        <Clock3 className="size-4 text-[#4f7cac]" aria-hidden="true" />
                                        {formatNumber(statistics.pickup_events_today)} transaksi hari ini
                                    </span>

                                    <time dateTime={dashboard.generated_at} className="mt-1 pl-6 text-xs text-[#829ab1]">
                                        Diperbarui {formatLastUpdated(dashboard.generated_at, dashboard.timezone)}
                                    </time>
                                </div>
                            </div>
                        </div>
                    </section>

                    {refreshFeedback && (
                        <div
                            role={refreshFeedback.type === 'error' ? 'alert' : 'status'}
                            aria-live={refreshFeedback.type === 'error' ? 'assertive' : 'polite'}
                            className={`flex items-start gap-3 rounded-2xl border px-4 py-3 shadow-sm ${
                                refreshFeedback.type === 'success'
                                    ? 'border-[#cce9e2] bg-[#eef9f6] text-[#3f8178]'
                                    : 'border-[#f0cccc] bg-[#fff2f2] text-[#b84f4f]'
                            }`}
                        >
                            {refreshFeedback.type === 'success' ? (
                                <CheckCircle2 className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                            ) : (
                                <CircleAlert className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                            )}

                            <p className="min-w-0 flex-1 text-sm leading-6 font-medium">{refreshFeedback.message}</p>

                            <button
                                type="button"
                                onClick={() => {
                                    setRefreshFeedback(null);
                                }}
                                aria-label="Tutup notifikasi pembaruan"
                                className="inline-flex size-8 shrink-0 items-center justify-center rounded-lg transition hover:bg-black/5 focus-visible:ring-2 focus-visible:ring-current focus-visible:outline-none"
                            >
                                <X className="size-4" aria-hidden="true" />
                            </button>
                        </div>
                    )}

                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Statistik sekolah">
                        <StatCard
                            title="Siswa Aktif"
                            value={formatNumber(statistics.active_students)}
                            description="Siswa dengan status aktif"
                            icon={Users}
                            tone="blue"
                        />

                        <StatCard
                            title="Penjemput Aktif"
                            value={formatNumber(statistics.active_pickup_persons)}
                            description="Penjemput yang masih dapat digunakan"
                            icon={UserCheck}
                            tone="green"
                        />

                        <StatCard
                            title="Wajah Terdaftar"
                            value={formatNumber(statistics.registered_faces)}
                            description={`${registeredFacePercentage}% dari penjemput aktif`}
                            icon={ScanFace}
                            tone="yellow"
                        />

                        <StatCard
                            title="Transaksi Hari Ini"
                            value={formatNumber(statistics.pickup_events_today)}
                            description={`${formatNumber(statistics.confirmed_today)} terkonfirmasi`}
                            icon={Clock3}
                            tone="red"
                        />
                    </section>

                    <section className="grid gap-6 xl:grid-cols-2">
                        <article className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-6">
                            <h2 className="font-bold text-[#243b53]">Kesiapan Biometrik</h2>

                            <p className="mt-1 text-sm text-[#829ab1]">Perbandingan wajah terdaftar dengan seluruh penjemput aktif.</p>

                            <div className="mt-7 flex justify-center">
                                <div
                                    className="relative flex size-44 items-center justify-center rounded-full shadow-inner"
                                    role="progressbar"
                                    aria-label="Persentase wajah penjemput terdaftar"
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-valuenow={registeredFacePercentage}
                                    style={{
                                        background:
                                            `conic-gradient(` +
                                            `#64b6ac 0deg ` +
                                            `${faceProgressDegrees}deg, ` +
                                            `#edf2f7 ` +
                                            `${faceProgressDegrees}deg ` +
                                            '360deg)',
                                    }}
                                >
                                    <div className="flex size-32 flex-col items-center justify-center rounded-full bg-white shadow-sm">
                                        <strong className="text-3xl font-bold text-[#243b53]">{registeredFacePercentage}%</strong>

                                        <span className="mt-1 text-xs text-[#829ab1]">Terdaftar</span>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-7 space-y-3">
                                <div className="flex items-center justify-between gap-4 text-sm">
                                    <span className="text-[#627d98]">Wajah terdaftar</span>

                                    <strong className="text-[#334e68]">{formatNumber(statistics.registered_faces)}</strong>
                                </div>

                                <div className="flex items-center justify-between gap-4 text-sm">
                                    <span className="text-[#627d98]">Penjemput aktif</span>

                                    <strong className="text-[#334e68]">{formatNumber(statistics.active_pickup_persons)}</strong>
                                </div>
                            </div>
                        </article>

                        <article className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-6">
                            <h2 className="font-bold text-[#243b53]">Status Transaksi Hari Ini</h2>

                            <p className="mt-1 text-sm text-[#829ab1]">Ringkasan transaksi berdasarkan status terakhir.</p>

                            <div className="mt-7 space-y-5">
                                <div>
                                    <div className="flex items-center justify-between gap-4 text-sm">
                                        <span className="font-medium text-[#627d98]">Terkonfirmasi</span>

                                        <strong className="text-[#4c9e94]">{formatNumber(statistics.confirmed_today)}</strong>
                                    </div>

                                    <div
                                        className="mt-2 h-3 overflow-hidden rounded-full bg-[#edf2f7]"
                                        role="progressbar"
                                        aria-label="Persentase transaksi terkonfirmasi"
                                        aria-valuemin={0}
                                        aria-valuemax={100}
                                        aria-valuenow={confirmedPercentage}
                                    >
                                        <div
                                            className="h-full rounded-full bg-[#64b6ac] transition-[width] duration-300"
                                            style={{
                                                width: `${confirmedPercentage}%`,
                                            }}
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between gap-4 rounded-xl bg-[#fff2f2] px-4 py-4">
                                    <span className="flex items-center gap-2 text-sm font-medium text-[#627d98]">
                                        <ShieldAlert className="size-4 text-[#cf6464]" aria-hidden="true" />
                                        Dibatalkan
                                    </span>

                                    <strong className="text-[#cf6464]">{formatNumber(statistics.cancelled_today)}</strong>
                                </div>

                                <div className="flex items-center justify-between gap-4 rounded-xl bg-[#eef6ff] px-4 py-4">
                                    <span className="flex items-center gap-2 text-sm font-medium text-[#627d98]">
                                        <Clock3 className="size-4 text-[#4f7cac]" aria-hidden="true" />
                                        Total transaksi
                                    </span>

                                    <strong className="text-[#4f7cac]">{formatNumber(statistics.pickup_events_today)}</strong>
                                </div>
                            </div>
                        </article>
                    </section>

                    {permissions.can_view_gate_activity && (
                        <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-6">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 className="font-bold text-[#243b53]">Aktivitas Terbaru</h2>

                                    <p className="mt-1 text-sm text-[#829ab1]">
                                        Lima transaksi penjemputan terakhir berdasarkan zona waktu{' '}
                                        <span className="font-medium text-[#627d98]">{dashboard.timezone}</span>.
                                    </p>
                                </div>

                                {permissions.can_view_pickup_history && (
                                    <Link
                                        href="/gate/pickup-events"
                                        prefetch
                                        className="inline-flex shrink-0 items-center gap-1.5 self-start rounded-xl border border-[#dceaf5] bg-[#f8fbff] px-3.5 py-2 text-sm font-semibold text-[#4f7cac] transition hover:border-[#bfd7ec] hover:bg-[#eef6ff] focus-visible:ring-2 focus-visible:ring-[#5b8def] focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        Lihat seluruh riwayat
                                        <ArrowRight className="size-4" aria-hidden="true" />
                                    </Link>
                                )}
                            </div>

                            {dashboard.recent_activities.length === 0 ? (
                                <div className="mt-6 rounded-2xl border border-dashed border-[#d9e2ec] bg-[#f8fafc] px-5 py-10 text-center">
                                    <Clock3 className="mx-auto size-8 text-[#9fb3c8]" aria-hidden="true" />

                                    <p className="mt-3 font-semibold text-[#486581]">Belum ada aktivitas penjemputan</p>

                                    <p className="mt-1 text-sm text-[#829ab1]">Transaksi terbaru akan muncul di bagian ini.</p>
                                </div>
                            ) : (
                                <div className="mt-5 divide-y divide-[#edf2f7]">
                                    {dashboard.recent_activities.map((activity) => {
                                        const displayName = activityDisplayName(activity.pickup_person_name);

                                        return (
                                            <article
                                                key={activity.id}
                                                className="flex flex-col gap-4 py-4 first:pt-1 last:pb-0 sm:flex-row sm:items-center"
                                            >
                                                <div className="flex min-w-0 flex-1 items-center gap-3">
                                                    <div
                                                        className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#eaf3fa] text-sm font-bold text-[#4f7cac]"
                                                        aria-hidden="true"
                                                    >
                                                        {activityInitials(displayName)}
                                                    </div>

                                                    <div className="min-w-0">
                                                        <p className="truncate font-semibold text-[#334e68]">{displayName}</p>

                                                        <p className="mt-0.5 text-sm text-[#829ab1]">
                                                            {verificationMethodLabel(activity.verification_method)}
                                                            {' · '}
                                                            {formatNumber(activity.student_count)} siswa
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="flex flex-wrap items-center justify-between gap-3 pl-14 sm:justify-end sm:pl-0">
                                                    <span
                                                        className={`rounded-full px-3 py-1.5 text-xs font-semibold ${activityStatusStyle(
                                                            activity.status,
                                                        )}`}
                                                    >
                                                        {activityStatusLabel(activity.status)}
                                                    </span>

                                                    <time
                                                        dateTime={activity.confirmed_at ?? undefined}
                                                        className="text-sm font-medium text-[#829ab1]"
                                                    >
                                                        {formatActivityTime(activity.confirmed_at, dashboard.timezone)}
                                                    </time>
                                                </div>
                                            </article>
                                        );
                                    })}
                                </div>
                            )}
                        </section>
                    )}

                    <section className="flex flex-col gap-4 rounded-2xl border border-[#d7ebe6] bg-[#eef9f6] p-5 sm:flex-row sm:items-center">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white text-[#4c9e94] shadow-sm">
                            <ShieldCheck className="size-5" aria-hidden="true" />
                        </div>

                        <div className="flex-1">
                            <h2 className="font-semibold text-[#335e68]">Isolasi data sekolah aktif</h2>

                            <p className="mt-1 text-sm leading-6 text-[#627d98]">
                                Statistik dashboard hanya dihitung dari sekolah yang terhubung dengan akun aktif. Data sekolah lain tidak disertakan.
                            </p>
                        </div>
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}
