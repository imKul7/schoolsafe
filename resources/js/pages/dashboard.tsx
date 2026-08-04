import { Head, Link, router } from '@inertiajs/react';
import {
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

type RefreshFeedback = { type: 'success' | 'error'; message: string } | null;

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
    label: string;
    value: string;
    description: string;
    icon: LucideIcon;
    accent: string;
    iconStyle: string;
}

const numberFormatter = new Intl.NumberFormat('id-ID');

function formatNumber(value: number): string {
    return numberFormatter.format(Number.isFinite(value) ? value : 0);
}
function percentage(value: number, total: number): number {
    return !Number.isFinite(value) || !Number.isFinite(total) || total <= 0 ? 0 : Math.min(100, Math.max(0, Math.round((value / total) * 100)));
}
function activityInitials(name: string): string {
    return (
        name
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0))
            .join('')
            .toUpperCase() || 'PJ'
    );
}
function activityDisplayName(name: string): string {
    return name.trim() || 'Penjemput tidak diketahui';
}
function activityStatusLabel(status: string): string {
    return status === 'confirmed' ? 'Terkonfirmasi' : status === 'cancelled' ? 'Dibatalkan' : 'Diproses';
}
function activityStatusStyle(status: string): string {
    return status === 'confirmed'
        ? 'bg-[#e8f7f3] text-[#267c70]'
        : status === 'cancelled'
          ? 'bg-[#fff0ef] text-[#bf6259]'
          : 'bg-[#fff7e5] text-[#a97817]';
}
function verificationMethodLabel(method: string): string {
    return method === 'face' || method === 'face_recognition' ? 'Verifikasi wajah' : method === 'manual' ? 'Verifikasi manual' : 'Metode verifikasi';
}

function formatTime(value: string | null, timezone: string): string {
    if (!value || Number.isNaN(new Date(value).getTime())) return 'Waktu tidak tersedia';
    try {
        return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: timezone }).format(new Date(value));
    } catch {
        return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'UTC' }).format(new Date(value));
    }
}

function formatDate(value: string, timezone: string): string {
    if (Number.isNaN(new Date(value).getTime())) return 'Pembaruan terbaru';
    try {
        return new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', timeZone: timezone }).format(new Date(value));
    } catch {
        return 'Pembaruan terbaru';
    }
}

function StatCard({ label, value, description, icon: Icon, accent, iconStyle }: StatCardProps) {
    return (
        <article className="group relative overflow-hidden rounded-2xl border border-[#e0e9f0] bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg hover:shadow-[#315c7c]/10">
            <span className={`absolute inset-x-0 top-0 h-1 ${accent}`} />
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold text-[#617990]">{label}</p>
                    <p className="mt-3 text-3xl font-bold tracking-tight text-[#17324d]">{value}</p>
                </div>
                <span className={`grid size-11 place-items-center rounded-xl ${iconStyle}`}>
                    <Icon className="size-5" />
                </span>
            </div>
            <p className="mt-4 text-xs font-medium text-[#829ab1]">{description}</p>
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

    useEffect(() => {
        if (refreshFeedback?.type !== 'success') return;
        const timeoutId = window.setTimeout(() => setRefreshFeedback(null), 5000);
        return () => window.clearTimeout(timeoutId);
    }, [refreshFeedback]);

    const refreshDashboard = (): void => {
        if (isRefreshing) return;
        let requestHandled = false;
        router.reload({
            only: ['dashboard'],
            onStart: () => {
                setIsRefreshing(true);
                setRefreshFeedback(null);
            },
            onSuccess: () => {
                requestHandled = true;
                setRefreshFeedback({ type: 'success', message: 'Data dashboard berhasil diperbarui.' });
            },
            onError: () => {
                requestHandled = true;
                setRefreshFeedback({ type: 'error', message: 'Data dashboard gagal diperbarui. Silakan coba kembali.' });
            },
            onFinish: () => {
                setIsRefreshing(false);
                if (!requestHandled)
                    setRefreshFeedback({ type: 'error', message: 'Pembaruan dashboard tidak selesai. Periksa koneksi lalu coba kembali.' });
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <main className="min-h-full bg-[#f5f8fc]" aria-busy={isRefreshing}>
                <div className="mx-auto w-full max-w-[1500px] space-y-6 p-4 sm:p-6 lg:p-8">
                    <section className="relative overflow-hidden rounded-3xl border border-[#dce8f0] bg-white px-6 py-7 shadow-sm sm:px-8">
                        <div className="pointer-events-none absolute top-0 right-0 size-72 translate-x-1/3 -translate-y-1/2 rounded-full bg-[#e9f1ff] blur-3xl" />
                        <div className="relative flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between">
                            <div>
                                <div className="inline-flex items-center gap-2 rounded-full bg-[#edf6ff] px-3 py-1.5 text-xs font-bold text-[#4d79d4]">
                                    {dashboard.has_school ? (
                                        <CheckCircle2 className="size-3.5 text-[#43a797]" />
                                    ) : (
                                        <ShieldAlert className="size-3.5 text-[#d37267]" />
                                    )}
                                    {dashboard.has_school ? 'Sekolah terhubung' : 'Akun tanpa sekolah'}
                                </div>
                                <h1 className="mt-4 text-3xl font-bold tracking-tight text-[#17324d] sm:text-4xl">Selamat datang di SchoolSafe</h1>
                                <p className="mt-2 text-sm font-medium text-[#71869b] sm:text-base">
                                    {formatDate(dashboard.generated_at, dashboard.timezone)} · Ringkasan operasional sekolah Anda.
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                {permissions.can_open_face_scanner && (
                                    <Link
                                        href="/gate/face-verification"
                                        prefetch
                                        className="inline-flex h-11 items-center gap-2 rounded-xl bg-[#5b8def] px-4 text-sm font-bold text-white shadow-lg shadow-[#5b8def]/25 transition hover:-translate-y-0.5 hover:bg-[#4979da]"
                                    >
                                        <ScanFace className="size-4" />
                                        Buka face scanner
                                    </Link>
                                )}
                                <button
                                    type="button"
                                    onClick={refreshDashboard}
                                    disabled={isRefreshing}
                                    aria-label={isRefreshing ? 'Memperbarui data dashboard' : 'Perbarui data dashboard'}
                                    className="inline-flex h-11 items-center gap-2 rounded-xl border border-[#d9e5ef] bg-white px-4 text-sm font-bold text-[#486b8d] transition hover:border-[#b9cfe7] hover:bg-[#f8fbff] disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <RefreshCw className={`size-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                                    {isRefreshing ? 'Memperbarui...' : 'Perbarui'}
                                </button>
                            </div>
                        </div>
                    </section>

                    {refreshFeedback && (
                        <div
                            role={refreshFeedback.type === 'error' ? 'alert' : 'status'}
                            aria-live={refreshFeedback.type === 'error' ? 'assertive' : 'polite'}
                            className={`flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-medium ${refreshFeedback.type === 'success' ? 'border-[#cce9e2] bg-[#effaf7] text-[#277b70]' : 'border-[#f0d0cd] bg-[#fff3f2] text-[#b84f4f]'}`}
                        >
                            {refreshFeedback.type === 'success' ? <CheckCircle2 className="size-5" /> : <CircleAlert className="size-5" />}
                            <span className="flex-1">{refreshFeedback.message}</span>
                            <button
                                type="button"
                                aria-label="Tutup notifikasi pembaruan"
                                onClick={() => setRefreshFeedback(null)}
                                className="grid size-7 place-items-center rounded-lg hover:bg-black/5"
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                    )}

                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Statistik sekolah">
                        <StatCard
                            label="Siswa Aktif"
                            value={formatNumber(statistics.active_students)}
                            description="Terdaftar dan aktif saat ini"
                            icon={Users}
                            accent="bg-[#5b8def]"
                            iconStyle="bg-[#edf3ff] text-[#4d79d4]"
                        />
                        <StatCard
                            label="Penjemput aktif"
                            value={formatNumber(statistics.active_pickup_persons)}
                            description="Dapat melakukan penjemputan"
                            icon={UserCheck}
                            accent="bg-[#54b6a5]"
                            iconStyle="bg-[#eaf8f5] text-[#319687]"
                        />
                        <StatCard
                            label="Wajah terdaftar"
                            value={formatNumber(statistics.registered_faces)}
                            description={`${registeredFacePercentage}% dari penjemput aktif`}
                            icon={ScanFace}
                            accent="bg-[#e0a83b]"
                            iconStyle="bg-[#fff7e4] text-[#c68b1d]"
                        />
                        <StatCard
                            label="Penjemputan hari ini"
                            value={formatNumber(statistics.pickup_events_today)}
                            description={`${formatNumber(statistics.confirmed_today)} transaksi terkonfirmasi`}
                            icon={Clock3}
                            accent="bg-[#d87068]"
                            iconStyle="bg-[#fff0ef] text-[#c65f58]"
                        />
                    </section>

                    <section className="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                        <article className="rounded-3xl border border-[#e0e9f0] bg-white p-6 shadow-sm">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="text-sm font-bold text-[#17324d]">Kesiapan biometrik</p>
                                    <p className="mt-1 text-sm leading-6 text-[#71869b]">
                                        Profil wajah yang siap digunakan untuk verifikasi gerbang.
                                    </p>
                                </div>
                                <span className="rounded-lg bg-[#eef6ff] px-2.5 py-1 text-xs font-bold text-[#4d79d4]">
                                    {registeredFacePercentage}% siap
                                </span>
                            </div>
                            <div className="mt-8 flex items-center gap-6">
                                <div
                                    className="relative grid size-32 shrink-0 place-items-center rounded-full"
                                    role="progressbar"
                                    aria-label="Persentase wajah penjemput terdaftar"
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-valuenow={registeredFacePercentage}
                                    style={{
                                        background: `conic-gradient(#54b6a5 0deg ${registeredFacePercentage * 3.6}deg, #edf2f7 ${registeredFacePercentage * 3.6}deg 360deg)`,
                                    }}
                                >
                                    <div className="grid size-[102px] place-items-center rounded-full bg-white shadow-sm">
                                        <strong className="text-2xl font-bold text-[#17324d]">{registeredFacePercentage}%</strong>
                                        <span className="text-[11px] text-[#829ab1]">terdaftar</span>
                                    </div>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-bold text-[#284762]">
                                        {formatNumber(statistics.registered_faces)} dari {formatNumber(statistics.active_pickup_persons)} penjemput
                                    </p>
                                    <div className="mt-3 h-2 overflow-hidden rounded-full bg-[#edf2f7]">
                                        <div
                                            className="h-full rounded-full bg-[#54b6a5] transition-all duration-500"
                                            style={{ width: `${registeredFacePercentage}%` }}
                                        />
                                    </div>
                                    <p className="mt-3 text-xs leading-5 text-[#71869b]">
                                        Daftarkan wajah penjemput agar proses di gerbang berjalan lebih cepat.
                                    </p>
                                </div>
                            </div>
                            {permissions.can_open_face_scanner && (
                                <Link
                                    href="/gate/face-verification"
                                    prefetch
                                    className="mt-7 inline-flex items-center gap-2 text-sm font-bold text-[#4d79d4] hover:text-[#315fae]"
                                >
                                    Mulai verifikasi <ArrowRight className="size-4" />
                                </Link>
                            )}
                        </article>

                        <article className="rounded-3xl border border-[#e0e9f0] bg-white p-6 shadow-sm">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="text-sm font-bold text-[#17324d]">Status penjemputan hari ini</p>
                                    <p className="mt-1 text-sm leading-6 text-[#71869b]">Pantau progres transaksi pada gerbang sekolah.</p>
                                </div>
                                <span className="grid size-10 place-items-center rounded-xl bg-[#edf6ff] text-[#4d79d4]">
                                    <Clock3 className="size-5" />
                                </span>
                            </div>
                            <div className="mt-7 space-y-5">
                                <div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="font-semibold text-[#486b8d]">Terkonfirmasi</span>
                                        <strong className="text-[#267c70]">{formatNumber(statistics.confirmed_today)}</strong>
                                    </div>
                                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-[#edf2f7]">
                                        <div
                                            className="h-full rounded-full bg-[#54b6a5] transition-all duration-500"
                                            style={{ width: `${confirmedPercentage}%` }}
                                        />
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3 border-t border-[#edf2f7] pt-5">
                                    <div className="rounded-xl bg-[#f6f9fc] p-3">
                                        <p className="text-xs font-medium text-[#71869b]">Dibatalkan</p>
                                        <p className="mt-1 text-xl font-bold text-[#c65f58]">{formatNumber(statistics.cancelled_today)}</p>
                                    </div>
                                    <div className="rounded-xl bg-[#f6f9fc] p-3">
                                        <p className="text-xs font-medium text-[#71869b]">Total transaksi</p>
                                        <p className="mt-1 text-xl font-bold text-[#17324d]">{formatNumber(statistics.pickup_events_today)}</p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </section>

                    {permissions.can_view_gate_activity && (
                        <section className="rounded-3xl border border-[#e0e9f0] bg-white p-6 shadow-sm">
                            <div className="flex flex-wrap items-end justify-between gap-4">
                                <div>
                                    <h2 className="text-lg font-bold text-[#17324d]">Aktivitas terbaru</h2>
                                    <p className="mt-1 text-sm text-[#71869b]">Penjemputan terakhir yang tercatat di gerbang.</p>
                                </div>
                                {permissions.can_view_pickup_history && (
                                    <Link
                                        href="/gate/pickup-events"
                                        prefetch
                                        className="inline-flex items-center gap-1 text-sm font-bold text-[#4d79d4] hover:text-[#315fae]"
                                    >
                                        Lihat seluruh riwayat <ArrowRight className="size-4" />
                                    </Link>
                                )}
                            </div>
                            {dashboard.recent_activities.length === 0 ? (
                                <div className="mt-6 rounded-2xl border border-dashed border-[#d8e5ee] bg-[#f8fbfd] px-5 py-10 text-center">
                                    <Clock3 className="mx-auto size-6 text-[#9ab0c2]" />
                                    <p className="mt-3 font-bold text-[#486b8d]">Belum ada aktivitas penjemputan</p>
                                    <p className="mt-1 text-sm text-[#829ab1]">Transaksi terbaru akan muncul di bagian ini.</p>
                                </div>
                            ) : (
                                <div className="mt-6 divide-y divide-[#edf2f7]">
                                    {dashboard.recent_activities.map((activity) => (
                                        <div key={activity.id} className="flex items-center gap-4 py-4 first:pt-0 last:pb-0">
                                            <span className="grid size-10 shrink-0 place-items-center rounded-full bg-[#eaf2ff] text-xs font-bold text-[#4d79d4]">
                                                {activityInitials(activityDisplayName(activity.pickup_person_name))}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-bold text-[#284762]">
                                                    {activityDisplayName(activity.pickup_person_name)}
                                                </p>
                                                <p className="mt-0.5 text-xs text-[#71869b]">
                                                    {verificationMethodLabel(activity.verification_method)} · {formatNumber(activity.student_count)}{' '}
                                                    siswa
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${activityStatusStyle(activity.status)}`}
                                                >
                                                    {activityStatusLabel(activity.status)}
                                                </span>
                                                <time className="mt-1.5 block text-xs text-[#829ab1]" dateTime={activity.confirmed_at ?? undefined}>
                                                    {formatTime(activity.confirmed_at, dashboard.timezone)}
                                                </time>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    )}

                    <section className="flex items-start gap-3 rounded-2xl border border-[#d7e9e6] bg-[#effaf7] p-4 text-sm">
                        <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-white text-[#319687] shadow-sm">
                            <ShieldCheck className="size-5" />
                        </span>
                        <p className="leading-6 text-[#46756f]">
                            <strong className="font-bold text-[#277b70]">Data sekolah terlindungi.</strong> Statistik hanya dihitung dari sekolah yang
                            terhubung dengan akun aktif Anda.
                        </p>
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}
