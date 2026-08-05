import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Check, ChevronRight, ClipboardCheck, Menu, ScanFace, ShieldCheck, UsersRound, X } from 'lucide-react';
import { type MouseEvent, useState } from 'react';

const features = [
    {
        icon: ScanFace,
        title: 'Verifikasi wajah yang meyakinkan',
        description: 'Pencocokan wajah dengan liveness challenge untuk membantu petugas memverifikasi penjemput secara cepat.',
    },
    {
        icon: UsersRound,
        title: 'Izin penjemputan yang jelas',
        description: 'Setiap siswa terhubung dengan daftar penjemput yang sah, aktif, dan sesuai masa berlaku izin.',
    },
    {
        icon: ClipboardCheck,
        title: 'Riwayat yang siap ditelusuri',
        description: 'Konfirmasi, pembatalan, dan aktivitas gerbang tercatat rapi untuk kebutuhan keamanan sekolah.',
    },
];

export default function Welcome() {
    const [isMenuOpen, setIsMenuOpen] = useState(false);

    const scrollToSection = (event: MouseEvent<HTMLAnchorElement>, href: string): void => {
        event.preventDefault();

        const target = document.querySelector(href);

        if (target instanceof HTMLElement) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });

            window.history.replaceState(null, '', href);
        }
    };

    return (
        <>
            <Head title="SchoolSafe — Penjemputan siswa yang lebih aman" />

            <main className="min-h-screen overflow-x-hidden bg-[#f7fafc] text-[#17324d]">
                <section className="relative isolate overflow-hidden bg-[#0b1f33] pt-5 pb-16 text-white sm:pb-24">
                    <div className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_75%_18%,rgba(62,180,163,0.22),transparent_27%),radial-gradient(circle_at_18%_82%,rgba(63,125,204,0.22),transparent_32%)]" />
                    <div className="pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-40 bg-gradient-to-t from-[#0b1f33] to-transparent" />

                    <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                        <nav
                            className="flex items-center justify-between rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-3 backdrop-blur-md sm:px-5"
                            aria-label="Navigasi utama"
                        >
                            <a href="#top" className="flex items-center gap-3" aria-label="SchoolSafe beranda">
                                <span className="grid size-10 place-items-center rounded-xl bg-[#5b8def] text-white shadow-lg shadow-blue-950/30">
                                    <ShieldCheck className="size-5" strokeWidth={2.4} />
                                </span>
                                <span>
                                    <span className="block text-base font-bold tracking-tight">SchoolSafe</span>
                                    <span className="block text-[10px] font-semibold tracking-[0.18em] text-[#a9c6d9] uppercase">
                                        Smart pickup system
                                    </span>
                                </span>
                            </a>

                            <div className="hidden items-center gap-7 text-sm font-medium text-[#c5d6e4] md:flex">
                                <a
                                    className="transition hover:text-white"
                                    href="#cara-kerja"
                                    onClick={(event) => scrollToSection(event, '#cara-kerja')}
                                >
                                    Cara kerja
                                </a>
                                <a className="transition hover:text-white" href="#fitur" onClick={(event) => scrollToSection(event, '#fitur')}>
                                    Fitur
                                </a>
                                <a className="transition hover:text-white" href="#keamanan" onClick={(event) => scrollToSection(event, '#keamanan')}>
                                    Keamanan
                                </a>
                            </div>

                            <div className="hidden items-center gap-4 md:flex">
                                <Link href={route('login')} className="text-sm font-semibold text-white transition hover:text-[#9edbd3]">
                                    Masuk
                                </Link>
                                <Link
                                    href={route('login')}
                                    className="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#17324d] shadow-sm transition hover:-translate-y-0.5 hover:bg-[#eaf7f5]"
                                >
                                    Mulai gunakan
                                </Link>
                            </div>

                            <button
                                type="button"
                                className="grid size-10 place-items-center rounded-xl border border-white/15 text-white md:hidden"
                                aria-label={isMenuOpen ? 'Tutup menu' : 'Buka menu'}
                                aria-expanded={isMenuOpen}
                                onClick={() => setIsMenuOpen((isOpen) => !isOpen)}
                            >
                                {isMenuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                            </button>
                        </nav>

                        {isMenuOpen && (
                            <div className="mt-3 rounded-2xl border border-white/10 bg-[#102b43] p-3 shadow-xl md:hidden">
                                {[
                                    ['Cara kerja', '#cara-kerja'],
                                    ['Fitur', '#fitur'],
                                    ['Keamanan', '#keamanan'],
                                ].map(([label, href]) => (
                                    <a
                                        key={href}
                                        href={href}
                                        onClick={(event) => {
                                            setIsMenuOpen(false);
                                            scrollToSection(event, href);
                                        }}
                                        className="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold text-[#d8e6f0] hover:bg-white/10"
                                    >
                                        {label}
                                        <ChevronRight className="size-4" />
                                    </a>
                                ))}
                                <Link
                                    href={route('login')}
                                    className="mt-2 block rounded-xl bg-[#5b8def] px-4 py-3 text-center text-sm font-bold"
                                    onClick={() => setIsMenuOpen(false)}
                                >
                                    Masuk ke SchoolSafe
                                </Link>
                            </div>
                        )}

                        <div id="top" className="grid items-center gap-14 pt-16 pb-6 lg:grid-cols-[1fr_0.93fr] lg:pt-24 lg:pb-10">
                            <div className="max-w-2xl">
                                <div className="inline-flex items-center gap-2 rounded-full border border-[#8bd4c8]/25 bg-[#1f665f]/20 px-3 py-1.5 text-xs font-bold text-[#b6eee5]">
                                    <span className="size-1.5 rounded-full bg-[#66dbc9]" />
                                    Keamanan penjemputan untuk sekolah modern
                                </div>
                                <h1 className="mt-6 text-4xl font-bold tracking-[-0.045em] text-white sm:text-5xl lg:text-[3.7rem] lg:leading-[1.06]">
                                    Setiap anak pulang bersama orang yang tepat.
                                </h1>
                                <p className="mt-6 max-w-xl text-base leading-8 text-[#bdd0de] sm:text-lg">
                                    SchoolSafe membuat proses penjemputan lebih tertib, cepat, dan dapat dipertanggungjawabkan—dari verifikasi
                                    identitas hingga riwayat aktivitas di gerbang.
                                </p>
                                <div className="mt-8 flex flex-wrap items-center gap-3">
                                    <Link
                                        href={route('login')}
                                        className="group inline-flex items-center gap-2 rounded-xl bg-[#5b8def] px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-950/30 transition hover:-translate-y-0.5 hover:bg-[#719cf2]"
                                    >
                                        Masuk ke SchoolSafe <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                                    </Link>
                                    <a
                                        href="#cara-kerja"
                                        onClick={(event) => scrollToSection(event, '#cara-kerja')}
                                        className="inline-flex items-center gap-2 rounded-xl border border-white/15 px-5 py-3.5 text-sm font-bold text-white transition hover:border-white/30 hover:bg-white/10"
                                    >
                                        Pelajari cara kerja
                                    </a>
                                </div>
                                <div className="mt-9 flex flex-wrap gap-x-6 gap-y-3 text-sm text-[#b4c9d8]">
                                    {['Terpisah per sekolah', 'Audit aktivitas lengkap', 'Akses sesuai peran'].map((item) => (
                                        <span key={item} className="inline-flex items-center gap-2">
                                            <Check className="size-4 text-[#69d2c4]" strokeWidth={3} />
                                            {item}
                                        </span>
                                    ))}
                                </div>
                            </div>

                            <div className="relative mx-auto w-full max-w-xl lg:mr-0">
                                <div className="absolute -inset-8 rounded-full bg-[#58b9b0]/15 blur-3xl" />
                                <div className="relative overflow-hidden rounded-[26px] border border-white/15 bg-[#f9fcff] p-3 shadow-2xl shadow-[#020b15]/45 sm:p-4">
                                    <div className="rounded-[19px] border border-[#dce8f0] bg-white p-4 text-[#17324d] sm:p-5">
                                        <div className="flex items-center justify-between border-b border-[#e7eef4] pb-4">
                                            <div className="flex items-center gap-3">
                                                <span className="grid size-9 place-items-center rounded-lg bg-[#e9f1ff] text-[#4d79d4]">
                                                    <ShieldCheck className="size-5" />
                                                </span>
                                                <div>
                                                    <p className="text-sm font-bold">Gerbang Utama</p>
                                                    <p className="text-xs text-[#71869b]">Senin, 04 Agustus 2026</p>
                                                </div>
                                            </div>
                                            <span className="rounded-full bg-[#e9f8f4] px-2.5 py-1 text-xs font-bold text-[#248576]">Aktif</span>
                                        </div>
                                        <div className="mt-5 grid gap-3 sm:grid-cols-3">
                                            {[
                                                ['24', 'Siswa dijemput'],
                                                ['12', 'Menunggu'],
                                                ['0', 'Perlu perhatian'],
                                            ].map(([value, label], index) => (
                                                <div key={label} className={`rounded-xl p-3 ${index === 2 ? 'bg-[#fff5f1]' : 'bg-[#f5f8fb]'}`}>
                                                    <p className={`text-xl font-bold ${index === 2 ? 'text-[#cf664e]' : 'text-[#203c58]'}`}>
                                                        {value}
                                                    </p>
                                                    <p className="mt-1 text-[11px] leading-4 font-medium text-[#71869b]">{label}</p>
                                                </div>
                                            ))}
                                        </div>
                                        <div className="mt-5 rounded-2xl border border-[#dce8f0] p-4">
                                            <div className="flex items-center justify-between">
                                                <p className="text-sm font-bold">Verifikasi terbaru</p>
                                                <span className="text-xs font-semibold text-[#4d79d4]">Lihat riwayat</span>
                                            </div>
                                            <div className="mt-4 flex items-center gap-3">
                                                <span className="grid size-10 place-items-center rounded-full bg-[#d7f2ec] text-sm font-bold text-[#267c70]">
                                                    RP
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-bold">Rina Pratama</p>
                                                    <p className="mt-0.5 text-xs text-[#71869b]">Penjemput Kayla Putri · Kelas 3A</p>
                                                </div>
                                                <span className="inline-flex items-center gap-1 rounded-full bg-[#e9f8f4] px-2 py-1 text-[11px] font-bold text-[#248576]">
                                                    <Check className="size-3" strokeWidth={3} /> Cocok
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="cara-kerja" className="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-24">
                    <div className="max-w-2xl">
                        <p className="text-sm font-bold tracking-[0.16em] text-[#4684c6] uppercase">Proses sederhana, kontrol menyeluruh</p>
                        <h2 className="mt-3 text-3xl font-bold tracking-tight text-[#17324d] sm:text-4xl">
                            Satu alur yang membuat gerbang lebih tenang.
                        </h2>
                    </div>
                    <div className="mt-10 grid gap-5 md:grid-cols-3">
                        {[
                            ['01', 'Siapkan data', 'Kelola siswa, penjemput, dan hak penjemputan dalam satu tempat.'],
                            ['02', 'Verifikasi di gerbang', 'Petugas memeriksa wajah penjemput dengan challenge liveness.'],
                            ['03', 'Simpan riwayat aman', 'Setiap penjemputan tercatat agar mudah ditinjau bila diperlukan.'],
                        ].map(([number, title, description]) => (
                            <article
                                key={number}
                                className="group rounded-2xl border border-[#dce7ef] bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg hover:shadow-[#315c7c]/10"
                            >
                                <span className="text-sm font-bold text-[#5b8def]">{number}</span>
                                <h3 className="mt-7 text-lg font-bold text-[#17324d]">{title}</h3>
                                <p className="mt-3 text-sm leading-6 text-[#617990]">{description}</p>
                            </article>
                        ))}
                    </div>
                </section>

                <section id="fitur" className="border-y border-[#dfe9f0] bg-white">
                    <div className="mx-auto grid max-w-7xl gap-10 px-5 py-16 sm:px-8 lg:grid-cols-[0.82fr_1.18fr] lg:px-10 lg:py-24">
                        <div>
                            <p className="text-sm font-bold tracking-[0.16em] text-[#4684c6] uppercase">Dirancang untuk keamanan</p>
                            <h2 className="mt-3 text-3xl font-bold tracking-tight text-[#17324d] sm:text-4xl">
                                Teknologi yang bekerja tanpa membuat proses menjadi rumit.
                            </h2>
                            <p className="mt-5 max-w-md leading-7 text-[#617990]">
                                Antarmuka yang jelas membantu setiap peran fokus pada tugasnya, sementara sistem menjaga jejak penjemputan di belakang
                                layar.
                            </p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            {features.map(({ icon: Icon, title, description }) => (
                                <article key={title} className="rounded-2xl bg-[#f5f9fc] p-5 transition hover:bg-[#eef5fb]">
                                    <span className="grid size-10 place-items-center rounded-xl bg-white text-[#4d79d4] shadow-sm">
                                        <Icon className="size-5" />
                                    </span>
                                    <h3 className="mt-5 text-base font-bold text-[#17324d]">{title}</h3>
                                    <p className="mt-2 text-sm leading-6 text-[#617990]">{description}</p>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                <section id="keamanan" className="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-10 lg:py-24">
                    <div className="rounded-3xl bg-[#143854] px-6 py-10 text-white sm:px-10 lg:flex lg:items-center lg:justify-between lg:px-14 lg:py-14">
                        <div className="max-w-2xl">
                            <span className="inline-flex items-center gap-2 text-sm font-bold text-[#9ee4db]">
                                <ShieldCheck className="size-4" /> Dibangun dengan keamanan sebagai fondasi
                            </span>
                            <h2 className="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">Buat pengalaman pulang sekolah terasa lebih aman.</h2>
                            <p className="mt-4 leading-7 text-[#c4d8e7]">
                                Masuk untuk mengelola data sekolah, menjalankan verifikasi, dan melihat aktivitas penjemputan hari ini.
                            </p>
                        </div>
                        <Link
                            href={route('login')}
                            className="mt-7 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3.5 text-sm font-bold text-[#17324d] transition hover:-translate-y-0.5 hover:bg-[#eaf7f5] lg:mt-0"
                        >
                            Masuk ke aplikasi <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </section>

                <footer className="border-t border-[#dfe9f0] bg-white">
                    <div className="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-7 text-sm text-[#71869b] sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
                        <div className="flex items-center gap-2 font-bold text-[#284762]">
                            <ShieldCheck className="size-4 text-[#5b8def]" /> SchoolSafe
                        </div>
                        <p>Keamanan siswa adalah prioritas.</p>
                    </div>
                </footer>
            </main>
        </>
    );
}
