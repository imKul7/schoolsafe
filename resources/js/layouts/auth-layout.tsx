import { BellRing, ScanFace, ShieldCheck, UserRoundCheck } from 'lucide-react';
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

export default function AuthLayout({ title = 'Selamat datang', description = 'Masuk untuk mengelola SchoolSafe.', children }: AuthLayoutProps) {
    return (
        <main className="min-h-screen bg-gradient-to-r from-sky-50 via-white to-emerald-50 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900">
            <div className="grid min-h-screen lg:grid-cols-[1.08fr_0.92fr]">
                <section className="relative hidden overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-10 lg:flex lg:flex-col xl:p-14">
                    <div className="absolute top-24 -left-24 size-72 rounded-full bg-[#0b1220]/40 blur-3xl" />
                    <div className="absolute -right-20 bottom-10 size-80 rounded-full bg-[#0f1724]/30 blur-3xl" />

                    <div className="relative z-10 flex items-center gap-3">
                        <div className="flex size-12 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-lg shadow-emerald-900/40">
                            <ShieldCheck className="size-7" />
                        </div>

                        <div>
                            <h1 className="text-xl font-bold text-white">SchoolSafe</h1>
                            <p className="text-sm text-slate-300">Smart Student Pickup System</p>
                        </div>
                    </div>

                    <div className="relative z-10 my-auto max-w-xl py-16">
                        <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-slate-700 bg-white/6 px-4 py-2 text-sm font-medium text-slate-200 backdrop-blur">
                            <ShieldCheck className="size-4 text-emerald-300" />
                            Penjemputan aman setiap hari
                        </div>

                        <h2 className="max-w-lg text-4xl leading-tight font-bold tracking-tight text-white xl:text-5xl">
                            Pastikan setiap siswa pulang bersama orang yang tepat.
                        </h2>

                        <p className="mt-5 max-w-lg text-base leading-7 text-slate-300">
                            Verifikasi identitas penjemput, kelola izin orang tua, dan pantau aktivitas penjemputan dalam satu sistem yang nyaman
                            digunakan.
                        </p>

                        <div className="mt-9 grid gap-3">
                            {features.map((feature) => {
                                const Icon = feature.icon;

                                return (
                                    <div
                                        key={feature.title}
                                        className="flex items-start gap-4 rounded-2xl border border-slate-800 bg-slate-800/60 p-4 shadow-sm backdrop-blur"
                                    >
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-700 text-emerald-300">
                                            <Icon className="size-5" />
                                        </div>

                                        <div>
                                            <h3 className="font-semibold text-white">{feature.title}</h3>
                                            <p className="mt-1 text-sm leading-5 text-slate-300">{feature.description}</p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <p className="relative z-10 text-sm text-slate-400">© 2026 SchoolSafe. Keamanan siswa adalah prioritas.</p>
                </section>

                <section className="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
                    <div className="w-full max-w-md">
                        <div className="mb-8 flex items-center gap-3 lg:hidden">
                            <div className="flex size-11 items-center justify-center rounded-2xl bg-emerald-500 text-white">
                                <ShieldCheck className="size-6" />
                            </div>

                            <div>
                                <div className="font-bold text-slate-50">SchoolSafe</div>
                                <div className="text-xs text-slate-300">Smart Pickup System</div>
                            </div>
                        </div>

                        <div className="rounded-[28px] border border-transparent bg-white/5 p-6 shadow-[0_20px_60px_rgba(0,0,0,0.4)] sm:p-9 dark:bg-slate-900 dark:border-slate-700">
                            <div className="mb-8">
                                <div className="mb-4 flex size-12 items-center justify-center rounded-2xl bg-slate-800 text-emerald-300">
                                    <ShieldCheck className="size-6" />
                                </div>

                                <h2 className="text-2xl font-bold tracking-tight text-white">{title}</h2>

                                <p className="mt-2 text-sm leading-6 text-slate-300">{description}</p>
                            </div>

                            {children}
                        </div>

                        <p className="mt-6 text-center text-xs leading-5 text-slate-400">
                            Akses hanya diperuntukkan bagi petugas yang telah mendapatkan izin.
                        </p>
                    </div>
                </section>
            </div>
        </main>
    );
}
