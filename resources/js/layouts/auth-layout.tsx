import {
    BellRing,
    ScanFace,
    ShieldCheck,
    UserRoundCheck,
} from 'lucide-react';
import type { ReactNode } from 'react';

interface AuthLayoutProps {
    title?: string;
    description?: string;
    children: ReactNode;
}

const features = [
    {
        icon: ScanFace,
        title: 'Verifikasi penjemput',
        description: 'Memastikan siswa pulang bersama orang yang tepat.',
    },
    {
        icon: BellRing,
        title: 'Notifikasi real-time',
        description: 'Orang tua mendapatkan informasi penjemputan.',
    },
    {
        icon: UserRoundCheck,
        title: 'Riwayat terkontrol',
        description: 'Seluruh aktivitas tersimpan dan mudah ditinjau.',
    },
];

export default function AuthLayout({
    title = 'Selamat datang',
    description = 'Masuk untuk mengelola SchoolSafe.',
    children,
}: AuthLayoutProps) {
    return (
        <main className="min-h-screen bg-[#f7fafc]">
            <div className="grid min-h-screen lg:grid-cols-[1.08fr_0.92fr]">
                <section className="relative hidden overflow-hidden bg-gradient-to-br from-[#edf7ff] via-[#f2fbfa] to-[#fffaf0] p-10 lg:flex lg:flex-col xl:p-14">
                    <div className="absolute -left-24 top-24 size-72 rounded-full bg-[#b8d9f3]/35 blur-3xl" />
                    <div className="absolute -right-20 bottom-10 size-80 rounded-full bg-[#bce8df]/40 blur-3xl" />

                    <div className="relative z-10 flex items-center gap-3">
                        <div className="flex size-12 items-center justify-center rounded-2xl bg-[#5b8def] text-white shadow-lg shadow-blue-200/70">
                            <ShieldCheck className="size-7" />
                        </div>

                        <div>
                            <h1 className="text-xl font-bold text-[#243b53]">
                                SchoolSafe
                            </h1>
                            <p className="text-sm text-[#627d98]">
                                Smart Student Pickup System
                            </p>
                        </div>
                    </div>

                    <div className="relative z-10 my-auto max-w-xl py-16">
                        <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-[#cfe5f4] bg-white/70 px-4 py-2 text-sm font-medium text-[#486581] backdrop-blur">
                            <ShieldCheck className="size-4 text-[#64b6ac]" />
                            Penjemputan aman setiap hari
                        </div>

                        <h2 className="max-w-lg text-4xl font-bold leading-tight tracking-tight text-[#243b53] xl:text-5xl">
                            Pastikan setiap siswa pulang bersama orang yang
                            tepat.
                        </h2>

                        <p className="mt-5 max-w-lg text-base leading-7 text-[#627d98]">
                            Verifikasi identitas penjemput, kelola izin orang
                            tua, dan pantau aktivitas penjemputan dalam satu
                            sistem yang nyaman digunakan.
                        </p>

                        <div className="mt-9 grid gap-3">
                            {features.map((feature) => {
                                const Icon = feature.icon;

                                return (
                                    <div
                                        key={feature.title}
                                        className="flex items-start gap-4 rounded-2xl border border-white/80 bg-white/65 p-4 shadow-sm backdrop-blur"
                                    >
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#e8f6f3] text-[#4c9e94]">
                                            <Icon className="size-5" />
                                        </div>

                                        <div>
                                            <h3 className="font-semibold text-[#334e68]">
                                                {feature.title}
                                            </h3>
                                            <p className="mt-1 text-sm leading-5 text-[#829ab1]">
                                                {feature.description}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <p className="relative z-10 text-sm text-[#829ab1]">
                        © 2026 SchoolSafe. Keamanan siswa adalah prioritas.
                    </p>
                </section>

                <section className="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
                    <div className="w-full max-w-md">
                        <div className="mb-8 flex items-center gap-3 lg:hidden">
                            <div className="flex size-11 items-center justify-center rounded-2xl bg-[#5b8def] text-white">
                                <ShieldCheck className="size-6" />
                            </div>

                            <div>
                                <div className="font-bold text-[#243b53]">
                                    SchoolSafe
                                </div>
                                <div className="text-xs text-[#829ab1]">
                                    Smart Pickup System
                                </div>
                            </div>
                        </div>

                        <div className="rounded-[28px] border border-[#e6eef5] bg-white p-6 shadow-[0_20px_60px_rgba(50,80,110,0.08)] sm:p-9">
                            <div className="mb-8">
                                <div className="mb-4 flex size-12 items-center justify-center rounded-2xl bg-[#eaf3fa] text-[#4f7cac]">
                                    <ShieldCheck className="size-6" />
                                </div>

                                <h2 className="text-2xl font-bold tracking-tight text-[#243b53]">
                                    {title}
                                </h2>

                                <p className="mt-2 text-sm leading-6 text-[#829ab1]">
                                    {description}
                                </p>
                            </div>

                            {children}
                        </div>

                        <p className="mt-6 text-center text-xs leading-5 text-[#9fb3c8]">
                            Akses hanya diperuntukkan bagi petugas yang telah
                            mendapatkan izin.
                        </p>
                    </div>
                </section>
            </div>
        </main>
    );
}