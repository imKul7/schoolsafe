import { Head, Link } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="SchoolSafe — Smart Student Pickup" />

            <div className="min-h-screen overflow-hidden bg-slate-950 text-white">
                <div className="mx-auto max-w-7xl px-6 py-16 lg:flex lg:items-center lg:justify-between lg:gap-16">
                    <div className="lg:w-1/2 xl:max-w-xl">
                        <div className="mb-6 inline-flex items-center gap-3 rounded-full bg-slate-900/70 px-4 py-2 ring-1 ring-white/10 backdrop-blur-sm">
                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-400 text-slate-950 font-bold">SS</div>
                            <div className="text-sm font-semibold text-slate-200">SchoolSafe</div>
                        </div>

                        <h1 className="text-5xl font-extrabold tracking-tight text-white sm:text-6xl">
                            Belajar pulang lebih aman, terkontrol, dan profesional.
                        </h1>

                        <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                            SchoolSafe membantu sekolah mengelola proses penjemputan dengan verifikasi wajah real-time, izin orang tua yang jelas, dan audit aktivitas yang dapat ditelusuri.
                        </p>

                        <div className="mt-10 flex flex-wrap items-center gap-4">
                            <Link
                                href={route('login')}
                                className="inline-flex items-center justify-center rounded-full bg-emerald-400 px-7 py-3 text-sm font-semibold text-slate-950 shadow-lg shadow-emerald-500/20 transition hover:-translate-y-0.5 hover:bg-emerald-300"
                            >
                                Masuk ke SchoolSafe
                            </Link>

                            <a
                                href="#features"
                                className="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/5 px-7 py-3 text-sm font-semibold text-slate-100 transition hover:border-white/30 hover:bg-white/10"
                            >
                                Lihat fitur
                            </a>
                        </div>

                        <div className="mt-12 grid gap-4 sm:grid-cols-2">
                            <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40 backdrop-blur-sm">
                                <p className="text-sm uppercase tracking-[0.24em] text-emerald-300">Kinerja</p>
                                <p className="mt-3 text-3xl font-semibold text-white">99% waktu respon</p>
                                <p className="mt-2 text-sm text-slate-400">Proses penjemputan cepat, bebas antre, dan dapat dipantau langsung.</p>
                            </div>

                            <div className="rounded-3xl border border-white/10 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/40 backdrop-blur-sm">
                                <p className="text-sm uppercase tracking-[0.24em] text-emerald-300">Kepercayaan</p>
                                <p className="mt-3 text-3xl font-semibold text-white">Audit penuh</p>
                                <p className="mt-2 text-sm text-slate-400">Semua aktivitas tercatat untuk keamanan sekolah dan orang tua.</p>
                            </div>
                        </div>
                    </div>

                    <div className="mt-14 lg:mt-0 lg:w-1/2 xl:order-2">
                        <div className="relative mx-auto max-w-md overflow-hidden rounded-[40px] border border-white/10 bg-slate-900/75 p-8 shadow-2xl shadow-slate-950/50 backdrop-blur-xl">
                            <div className="absolute -right-14 top-10 h-32 w-32 rounded-full bg-emerald-400/20 blur-3xl" />
                            <div className="absolute left-8 top-8 h-28 w-28 rounded-full bg-sky-400/10 blur-3xl" />

                            <div className="relative z-10 space-y-6">
                                <div className="rounded-3xl bg-slate-950/90 p-6 ring-1 ring-white/10">
                                    <div className="mb-4 flex items-center justify-between">
                                        <span className="rounded-full bg-slate-800/90 px-3 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">
                                            Dashboard
                                        </span>
                                        <span className="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-300">Active</span>
                                    </div>

                                    <div className="space-y-4">
                                        <div className="rounded-3xl bg-slate-900/90 p-4 ring-1 ring-white/5">
                                            <p className="text-sm text-slate-400">Verifikasi Wajah</p>
                                            <p className="mt-2 text-xl font-semibold text-white">3 Detik per identitas</p>
                                        </div>

                                        <div className="rounded-3xl bg-slate-900/90 p-4 ring-1 ring-white/5">
                                            <p className="text-sm text-slate-400">Orang tua terverifikasi</p>
                                            <p className="mt-2 text-xl font-semibold text-white">97% kepercayaan</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="rounded-3xl bg-white/5 p-6 ring-1 ring-white/10">
                                    <div className="mb-4 flex items-center justify-between text-sm text-slate-400">
                                        <span>Progress penjemputan</span>
                                        <span className="text-emerald-300">+12% minggu ini</span>
                                    </div>

                                    <div className="h-2 overflow-hidden rounded-full bg-slate-800">
                                        <div className="h-full w-[82%] rounded-full bg-emerald-400" />
                                    </div>

                                    <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                        <div className="rounded-3xl bg-slate-950/90 p-4 text-white">
                                            <p className="text-xs uppercase tracking-[0.2em] text-slate-500">Target</p>
                                            <p className="mt-2 text-2xl font-semibold">12 sesi</p>
                                        </div>
                                        <div className="rounded-3xl bg-slate-950/90 p-4 text-white">
                                            <p className="text-xs uppercase tracking-[0.2em] text-slate-500">Streak</p>
                                            <p className="mt-2 text-2xl font-semibold">6 hari</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <section id="features" className="border-t border-white/10 bg-slate-950/90 py-20">
                    <div className="mx-auto max-w-6xl px-6">
                        <div className="grid gap-10 lg:grid-cols-3">
                            <div className="space-y-4 rounded-3xl bg-slate-900/70 p-8 ring-1 ring-white/10">
                                <p className="text-sm uppercase tracking-[0.24em] text-emerald-300">Keunggulan</p>
                                <h2 className="text-3xl font-semibold text-white">Sistem penjemputan modern untuk sekolah</h2>
                                <p className="text-slate-400">Semua data tersentral, proses dioptimalkan, dan keamanan ditingkatkan bagi setiap siswa dan orang tua.</p>
                            </div>

                            {[
                                {
                                    title: 'Verifikasi Wajah',
                                    description: 'Identifikasi penjemput dengan rekam wajah, deteksi liveness, dan peringatan otomatis.',
                                },
                                {
                                    title: 'Akses Terstruktur',
                                    description: 'Kelompok pengguna, peran, dan persetujuan penjemputan dalam satu kontrol dashboard.',
                                },
                                {
                                    title: 'Audit Penuh',
                                    description: 'Riwayat penjemputan tersimpan rapih untuk pemeriksaan keamanan kapan saja.',
                                },
                            ].map((feature) => (
                                <div key={feature.title} className="rounded-3xl bg-slate-900/80 p-6 ring-1 ring-white/5">
                                    <h3 className="text-xl font-semibold text-white">{feature.title}</h3>
                                    <p className="mt-3 text-slate-400">{feature.description}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
