import { Head } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    CheckCircle2,
    Clock3,
    ScanFace,
    ShieldAlert,
    ShieldCheck,
    UserCheck,
    Users,
    type LucideIcon,
} from 'lucide-react';

import AppLayout from '@/layouts/app-layout';

type StatTone = 'blue' | 'green' | 'yellow' | 'red';

interface StatCardProps {
    title: string;
    value: string;
    description: string;
    icon: LucideIcon;
    tone: StatTone;
}

interface PickupActivity {
    id: number;
    pickupName: string;
    studentName: string;
    studentClass: string;
    time: string;
    status: 'Terverifikasi' | 'Izin sementara' | 'Perlu ditinjau';
    initials: string;
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

const weeklyPickupData = [
    { day: 'Sen', value: 72, total: 350 },
    { day: 'Sel', value: 84, total: 408 },
    { day: 'Rab', value: 68, total: 330 },
    { day: 'Kam', value: 92, total: 447 },
    { day: 'Jum', value: 78, total: 379 },
];

const pickupActivities: PickupActivity[] = [
    {
        id: 1,
        pickupName: 'Ratna Putri',
        studentName: 'Kayla Putri',
        studentClass: 'Kelas 3A',
        time: '13.42',
        status: 'Terverifikasi',
        initials: 'RP',
    },
    {
        id: 2,
        pickupName: 'Budi Pratama',
        studentName: 'Andi Pratama',
        studentClass: 'Kelas 4B',
        time: '13.36',
        status: 'Terverifikasi',
        initials: 'BP',
    },
    {
        id: 3,
        pickupName: 'Siti Aminah',
        studentName: 'Aulia Rahma',
        studentClass: 'Kelas 2C',
        time: '13.25',
        status: 'Izin sementara',
        initials: 'SA',
    },
    {
        id: 4,
        pickupName: 'Rian Saputra',
        studentName: 'Dimas Saputra',
        studentClass: 'Kelas 5A',
        time: '13.18',
        status: 'Perlu ditinjau',
        initials: 'RS',
    },
];

function StatCard({
    title,
    value,
    description,
    icon: Icon,
    tone,
}: StatCardProps) {
    const styles = toneStyles[tone];

    return (
        <article
            className={`rounded-2xl border p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md ${styles.card}`}
        >
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-medium text-[#627d98]">
                        {title}
                    </p>

                    <p className="mt-3 text-3xl font-bold tracking-tight text-[#243b53]">
                        {value}
                    </p>
                </div>

                <div
                    className={`flex size-11 shrink-0 items-center justify-center rounded-xl ${styles.icon}`}
                >
                    <Icon className="size-5" aria-hidden="true" />
                </div>
            </div>

            <p
                className={`mt-4 flex items-center gap-1.5 text-xs font-medium ${styles.indicator}`}
            >
                <Activity className="size-3.5" aria-hidden="true" />
                {description}
            </p>
        </article>
    );
}

function ActivityStatus({
    status,
}: {
    status: PickupActivity['status'];
}) {
    const styles = {
        Terverifikasi: 'bg-[#e8f6f3] text-[#4c9e94]',
        'Izin sementara': 'bg-[#fff6dc] text-[#a77a16]',
        'Perlu ditinjau': 'bg-[#fff0f0] text-[#cf6464]',
    };

    return (
        <span
            className={`rounded-full px-3 py-1.5 text-xs font-semibold ${styles[status]}`}
        >
            {status}
        </span>
    );
}

export default function Dashboard() {
    return (
        <AppLayout>
            <Head title="Dashboard" />

            <main className="min-h-full bg-[#f8fafc]">
                <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 md:p-6">
                    {/* Hero */}
                    <section className="relative overflow-hidden rounded-[28px] border border-[#deebf5] bg-gradient-to-r from-[#eaf4ff] via-[#eef9f6] to-[#fff9eb] p-6 shadow-sm md:p-8">
                        <div className="absolute -top-20 -right-10 size-60 rounded-full bg-white/50 blur-3xl" />
                        <div className="absolute -bottom-28 left-1/3 size-52 rounded-full bg-[#ccece5]/40 blur-3xl" />

                        <div className="relative z-10 flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                            <div>
                                <span className="inline-flex items-center gap-2 rounded-full border border-white bg-white/75 px-3 py-1.5 text-xs font-semibold text-[#4f7cac] shadow-sm backdrop-blur">
                                    <CheckCircle2
                                        className="size-3.5 text-[#64b6ac]"
                                        aria-hidden="true"
                                    />
                                    Sistem berjalan normal
                                </span>

                                <h1 className="mt-4 text-2xl font-bold tracking-tight text-[#243b53] md:text-3xl">
                                    Selamat datang, Admin SchoolSafe 👋
                                </h1>

                                <p className="mt-2 max-w-2xl text-sm leading-6 text-[#627d98]">
                                    Pantau penjemputan siswa, izin orang tua,
                                    dan aktivitas gerbang sekolah melalui satu
                                    dashboard yang aman dan mudah digunakan.
                                </p>
                            </div>

                            <div className="flex flex-col gap-3 sm:flex-row">
                                <button
                                    type="button"
                                    disabled
                                    title="Modul scanner akan dibuat pada tahap berikutnya"
                                    className="inline-flex h-12 cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-[#5b8def] px-5 text-sm font-semibold text-white opacity-80 shadow-lg shadow-blue-200/60"
                                >
                                    <ScanFace
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                    Buka Face Scanner
                                </button>

                                <div className="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-white bg-white/70 px-4 text-sm font-medium text-[#627d98] shadow-sm backdrop-blur">
                                    <Clock3
                                        className="size-4 text-[#4f7cac]"
                                        aria-hidden="true"
                                    />
                                    Penjemputan aktif
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* Statistics */}
                    <section
                        className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                        aria-label="Statistik penjemputan"
                    >
                        <StatCard
                            title="Total Siswa"
                            value="486"
                            description="12 siswa baru bulan ini"
                            icon={Users}
                            tone="blue"
                        />

                        <StatCard
                            title="Sudah Dijemput"
                            value="327"
                            description="67% dari seluruh siswa"
                            icon={UserCheck}
                            tone="green"
                        />

                        <StatCard
                            title="Masih Menunggu"
                            value="159"
                            description="Penjemputan sedang berlangsung"
                            icon={Clock3}
                            tone="yellow"
                        />

                        <StatCard
                            title="Perlu Ditinjau"
                            value="3"
                            description="Memerlukan pemeriksaan petugas"
                            icon={ShieldAlert}
                            tone="red"
                        />
                    </section>

                    {/* Chart and progress */}
                    <section className="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">
                        {/* Bar chart */}
                        <article className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-6">
                            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                <div>
                                    <h2 className="font-bold text-[#243b53]">
                                        Aktivitas Penjemputan
                                    </h2>

                                    <p className="mt-1 text-sm text-[#829ab1]">
                                        Jumlah siswa yang dijemput selama
                                        minggu ini.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    className="w-fit rounded-xl border border-[#e6eef5] bg-[#fbfdff] px-3 py-2 text-xs font-semibold text-[#627d98]"
                                >
                                    Minggu ini
                                </button>
                            </div>

                            <div className="mt-8 flex h-64 items-end justify-between gap-3">
                                {weeklyPickupData.map((item) => (
                                    <div
                                        key={item.day}
                                        className="flex h-full flex-1 flex-col items-center justify-end gap-3"
                                    >
                                        <div className="group relative flex h-full w-full items-end justify-center overflow-hidden rounded-xl bg-[#f5f8fb]">
                                            <div
                                                className="w-full max-w-12 rounded-t-xl bg-gradient-to-t from-[#5b8def] to-[#9ec2e8] transition-opacity group-hover:opacity-85"
                                                style={{
                                                    height: `${item.value}%`,
                                                }}
                                            />

                                            <span className="absolute top-3 rounded-lg bg-white/90 px-2 py-1 text-[11px] font-semibold text-[#627d98] shadow-sm">
                                                {item.total}
                                            </span>
                                        </div>

                                        <span className="text-xs font-semibold text-[#829ab1]">
                                            {item.day}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </article>

                        {/* Circular progress */}
                        <article className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-6">
                            <div>
                                <h2 className="font-bold text-[#243b53]">
                                    Status Hari Ini
                                </h2>

                                <p className="mt-1 text-sm text-[#829ab1]">
                                    Progres penjemputan seluruh siswa.
                                </p>
                            </div>

                            <div className="mt-7 flex justify-center">
                                <div className="relative flex size-44 items-center justify-center rounded-full bg-[conic-gradient(#64b6ac_0deg_241deg,#edf2f7_241deg_360deg)] shadow-inner">
                                    <div className="flex size-32 flex-col items-center justify-center rounded-full bg-white shadow-sm">
                                        <strong className="text-3xl font-bold text-[#243b53]">
                                            67%
                                        </strong>

                                        <span className="mt-1 text-xs text-[#829ab1]">
                                            Selesai
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-7 space-y-3">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="flex items-center gap-2 text-[#627d98]">
                                        <span className="size-2.5 rounded-full bg-[#64b6ac]" />
                                        Sudah dijemput
                                    </span>

                                    <strong className="text-[#334e68]">
                                        327
                                    </strong>
                                </div>

                                <div className="flex items-center justify-between text-sm">
                                    <span className="flex items-center gap-2 text-[#627d98]">
                                        <span className="size-2.5 rounded-full bg-[#dfe7ef]" />
                                        Masih menunggu
                                    </span>

                                    <strong className="text-[#334e68]">
                                        159
                                    </strong>
                                </div>

                                <div className="flex items-center justify-between text-sm">
                                    <span className="flex items-center gap-2 text-[#627d98]">
                                        <span className="size-2.5 rounded-full bg-[#e97a7a]" />
                                        Perlu ditinjau
                                    </span>

                                    <strong className="text-[#334e68]">
                                        3
                                    </strong>
                                </div>
                            </div>
                        </article>
                    </section>

                    {/* Recent activity */}
                    <section className="rounded-2xl border border-[#e6eef5] bg-white p-5 shadow-sm md:p-6">
                        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div>
                                <h2 className="font-bold text-[#243b53]">
                                    Aktivitas Terbaru
                                </h2>

                                <p className="mt-1 text-sm text-[#829ab1]">
                                    Verifikasi penjemput yang baru dilakukan.
                                </p>
                            </div>

                            <button
                                type="button"
                                disabled
                                className="flex w-fit cursor-not-allowed items-center gap-1.5 text-sm font-semibold text-[#4f7cac] opacity-70"
                            >
                                Lihat seluruh riwayat
                                <ArrowRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>

                        <div className="mt-5 divide-y divide-[#edf2f7]">
                            {pickupActivities.map((activity) => (
                                <div
                                    key={activity.id}
                                    className="flex flex-col gap-4 py-4 first:pt-1 last:pb-0 sm:flex-row sm:items-center"
                                >
                                    <div className="flex flex-1 items-center gap-3">
                                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#eaf3fa] text-sm font-bold text-[#4f7cac]">
                                            {activity.initials}
                                        </div>

                                        <div className="min-w-0">
                                            <p className="truncate font-semibold text-[#334e68]">
                                                {activity.pickupName}
                                            </p>

                                            <p className="mt-0.5 truncate text-sm text-[#829ab1]">
                                                Menjemput{' '}
                                                {activity.studentName} ·{' '}
                                                {activity.studentClass}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-between gap-4 pl-14 sm:justify-end sm:pl-0">
                                        <ActivityStatus
                                            status={activity.status}
                                        />

                                        <span className="w-12 text-right text-sm font-medium text-[#829ab1]">
                                            {activity.time}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* Security notice */}
                    <section className="flex flex-col gap-4 rounded-2xl border border-[#d7ebe6] bg-[#eef9f6] p-5 sm:flex-row sm:items-center">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white text-[#4c9e94] shadow-sm">
                            <ShieldCheck
                                className="size-5"
                                aria-hidden="true"
                            />
                        </div>

                        <div className="flex-1">
                            <h2 className="font-semibold text-[#335e68]">
                                Sistem keamanan aktif
                            </h2>

                            <p className="mt-1 text-sm leading-6 text-[#627d98]">
                                Seluruh aktivitas penjemputan akan tercatat dan
                                dapat ditinjau kembali oleh administrator
                                sekolah.
                            </p>
                        </div>
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}