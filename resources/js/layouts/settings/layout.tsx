import { Link } from '@inertiajs/react';
import { KeyRound, Palette, Settings2, UserRound, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface SettingsNavigationItem {
    title: string;
    description: string;
    url: string;
    icon: LucideIcon;
}

const settingsNavigationItems: SettingsNavigationItem[] = [
    {
        title: 'Profil',
        description: 'Identitas dan alamat email',
        url: '/settings/profile',
        icon: UserRound,
    },
    {
        title: 'Kata Sandi',
        description: 'Keamanan akses akun',
        url: '/settings/password',
        icon: KeyRound,
    },
    {
        title: 'Tampilan',
        description: 'Tema dan preferensi visual',
        url: '/settings/appearance',
        icon: Palette,
    },
];

export default function SettingsLayout({ children }: { children: ReactNode }) {
    const currentPath = typeof window === 'undefined' ? '' : window.location.pathname;

    return (
        <div className="settings-page min-h-full px-4 py-6 md:px-6 md:py-8">
            <div className="relative z-10 mx-auto w-full max-w-[1500px] space-y-6">
                <header className="settings-hero relative overflow-hidden rounded-[30px] p-6 md:p-8">
                    <div className="relative z-10 flex items-start gap-4">
                        <span className="settings-hero-icon grid size-14 shrink-0 place-items-center rounded-2xl">
                            <Settings2 className="size-7" />
                        </span>

                        <div className="min-w-0">
                            <p className="text-xs font-extrabold tracking-[0.18em] text-blue-200 uppercase">Pusat Preferensi</p>

                            <h1 className="mt-2 text-3xl font-extrabold tracking-[-0.04em] text-white md:text-4xl">Pengaturan Akun</h1>

                            <p className="mt-3 max-w-2xl text-sm leading-7 text-blue-100/80 md:text-base">
                                Kelola informasi profil, keamanan akun, dan preferensi tampilan SchoolSafe dalam satu tempat.
                            </p>
                        </div>
                    </div>
                </header>

                <div className="grid items-start gap-6 xl:grid-cols-[300px_minmax(0,1fr)]">
                    <aside className="settings-navigation rounded-[26px] p-4">
                        <div className="px-3 pb-3">
                            <p className="text-xs font-extrabold tracking-[0.16em] text-slate-400 uppercase">Menu Pengaturan</p>
                        </div>

                        <nav className="space-y-2" aria-label="Navigasi pengaturan akun">
                            {settingsNavigationItems.map((item) => {
                                const Icon = item.icon;
                                const active = currentPath === item.url;

                                return (
                                    <Link
                                        key={item.url}
                                        href={item.url}
                                        prefetch
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'settings-nav-link flex items-center gap-3 rounded-2xl p-3',
                                            active && 'settings-nav-link-active',
                                        )}
                                    >
                                        <span className="settings-nav-icon grid size-11 shrink-0 place-items-center rounded-xl">
                                            <Icon className="size-5" />
                                        </span>

                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-extrabold">{item.title}</span>

                                            <span className="mt-0.5 block truncate text-xs">{item.description}</span>
                                        </span>

                                        <span aria-hidden="true" className="settings-nav-indicator" />
                                    </Link>
                                );
                            })}
                        </nav>

                        <div className="settings-nav-security mt-5 rounded-2xl p-4">
                            <p className="text-sm font-extrabold text-emerald-800">Data terlindungi</p>

                            <p className="mt-1 text-xs leading-5 text-emerald-700/80">
                                Perubahan akun diproses melalui sistem autentikasi SchoolSafe.
                            </p>
                        </div>
                    </aside>

                    <main className="settings-content-panel min-w-0 rounded-[28px] p-4 sm:p-6 lg:p-8">{children}</main>
                </div>
            </div>
        </div>
    );
}
