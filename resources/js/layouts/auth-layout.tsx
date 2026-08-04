import { BellRing, ScanFace, ShieldCheck, UserRoundCheck } from 'lucide-react';
import type { ReactNode } from 'react';

interface AuthLayoutProps {
    title?: string;
    description?: string;
    children: ReactNode;
}

const features = [
    { icon: ScanFace, title: 'Verifikasi wajah', description: 'Identitas penjemput diperiksa di gerbang sekolah.' },
    { icon: BellRing, title: 'Informasi real-time', description: 'Aktivitas penjemputan mudah dipantau oleh sekolah.' },
    { icon: UserRoundCheck, title: 'Akses terstruktur', description: 'Setiap petugas bekerja sesuai peran dan kewenangannya.' },
];

export default function AuthLayout({ title = 'Selamat datang', description = 'Masuk untuk mengelola SchoolSafe.', children }: AuthLayoutProps) {
    return (
        <main className="min-h-screen bg-[#f4f8fb] text-[#17324d]">
            <div className="grid min-h-screen lg:grid-cols-[1.08fr_0.92fr]">
                <section className="relative hidden overflow-hidden bg-[#0b1f33] p-10 lg:flex lg:flex-col xl:p-14">
                    <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_8%_15%,rgba(91,141,239,0.28),transparent_30%),radial-gradient(circle_at_90%_84%,rgba(77,190,171,0.2),transparent_33%)]" />
                    <div className="relative z-10 flex items-center gap-3">
                        <div className="grid size-12 place-items-center rounded-2xl bg-[#5b8def] text-white shadow-lg shadow-blue-950/40">
                            <ShieldCheck className="size-7" strokeWidth={2.3} />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold tracking-tight text-white">SchoolSafe</h1>
                            <p className="text-xs font-semibold tracking-[0.13em] text-[#a9c6d9] uppercase">Smart pickup system</p>
                        </div>
                    </div>

                    <div className="relative z-10 my-auto max-w-xl py-16">
                        <div className="inline-flex items-center gap-2 rounded-full border border-[#8bd4c8]/25 bg-[#1f665f]/20 px-3 py-1.5 text-xs font-bold text-[#b6eee5]">
                            <span className="size-1.5 rounded-full bg-[#66dbc9]" /> Portal sekolah yang terlindungi
                        </div>
                        <h2 className="mt-6 max-w-lg text-4xl leading-[1.12] font-bold tracking-tight text-white xl:text-5xl">
                            Pulang sekolah yang lebih aman, untuk semua.
                        </h2>
                        <p className="mt-5 max-w-lg text-base leading-7 text-[#bdd0de]">
                            Kelola izin penjemputan, verifikasi identitas, dan riwayat aktivitas dalam satu sistem yang jelas bagi seluruh tim
                            sekolah.
                        </p>

                        <div className="mt-10 space-y-3">
                            {features.map(({ icon: Icon, title: featureTitle, description: featureDescription }) => (
                                <div
                                    key={featureTitle}
                                    className="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.055] p-4 backdrop-blur-sm"
                                >
                                    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10 text-[#8fe2d5]">
                                        <Icon className="size-5" />
                                    </span>
                                    <div>
                                        <h3 className="text-sm font-bold text-white">{featureTitle}</h3>
                                        <p className="mt-0.5 text-sm text-[#b7cad9]">{featureDescription}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <p className="relative z-10 text-xs text-[#8fa8bb]">© 2026 SchoolSafe. Keamanan siswa adalah prioritas.</p>
                </section>

                <section className="relative flex min-h-screen items-center justify-center overflow-hidden px-5 py-10 sm:px-8 lg:px-12">
                    <div className="pointer-events-none absolute top-[-10%] right-[-15%] size-96 rounded-full bg-[#dfeaff] blur-3xl" />
                    <div className="pointer-events-none absolute bottom-[-16%] left-[-15%] size-96 rounded-full bg-[#d9f4ef] blur-3xl" />
                    <div className="relative w-full max-w-md">
                        <div className="mb-10 flex items-center gap-3 lg:hidden">
                            <span className="grid size-11 place-items-center rounded-2xl bg-[#5b8def] text-white shadow-lg shadow-blue-200">
                                <ShieldCheck className="size-6" />
                            </span>
                            <span>
                                <strong className="block text-base">SchoolSafe</strong>
                                <small className="block text-[10px] font-bold tracking-[0.14em] text-[#71869b] uppercase">Smart pickup system</small>
                            </span>
                        </div>
                        <div className="rounded-[28px] border border-white/80 bg-white/90 p-6 shadow-[0_24px_70px_rgba(42,77,109,0.14)] backdrop-blur sm:p-9">
                            <div className="mb-8">
                                <span className="grid size-12 place-items-center rounded-2xl bg-[#eef4ff] text-[#4d79d4]">
                                    <ShieldCheck className="size-6" />
                                </span>
                                <h2 className="mt-5 text-2xl font-bold tracking-tight text-[#17324d]">{title}</h2>
                                <p className="mt-2 text-sm leading-6 text-[#617990]">{description}</p>
                            </div>
                            {children}
                        </div>
                        <p className="mt-6 text-center text-xs leading-5 text-[#71869b]">
                            Akses hanya diperuntukkan bagi petugas sekolah yang berwenang.
                        </p>
                    </div>
                </section>
            </div>
        </main>
    );
}
