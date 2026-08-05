import { Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck2,
    CircleUserRound,
    History,
    LayoutDashboard,
    ScanFace,
    ShieldCheck,
    UserRoundCheck,
    UsersRound,
    type LucideIcon,
} from 'lucide-react';

import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem, SharedData, UserRole } from '@/types';

type RoleAwareNavItem = NavItem & {
    roles: UserRole[];
};

const mainNavItems: RoleAwareNavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutDashboard,
        roles: ['super_admin', 'school_admin', 'gate_officer', 'teacher', 'parent'],
    },
    {
        title: 'Data Siswa',
        url: '/students',
        icon: UsersRound,
        roles: ['school_admin', 'teacher'],
    },
    {
        title: 'Data Penjemput',
        url: '/pickup-persons',
        icon: UserRoundCheck,
        roles: ['school_admin', 'gate_officer', 'teacher'],
    },
    {
        title: 'Verifikasi Gerbang',
        url: '/gate/face-verification',
        icon: ScanFace,
        roles: ['school_admin', 'gate_officer'],
    },
    {
        title: 'Riwayat Gerbang',
        url: '/gate/pickup-events',
        icon: History,
        roles: ['school_admin', 'gate_officer'],
    },
    {
        title: 'Profil',
        url: '/profile',
        icon: CircleUserRound,
        roles: ['super_admin', 'school_admin', 'gate_officer', 'teacher', 'parent'],
    },
];

type ComingSoonItem = {
    title: string;
    icon: LucideIcon;
};

const comingSoonItems: ComingSoonItem[] = [
    {
        title: 'Izin Penjemputan',
        icon: CalendarCheck2,
    },
    {
        title: 'Riwayat',
        icon: ShieldCheck,
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

    const visibleMainNavItems = mainNavItems.filter((item) => item.roles.includes(auth.user.role));

    const canViewSchoolManagement = auth.user.role === 'school_admin';

    return (
        <Sidebar
            collapsible="icon"
            variant="sidebar"
            className="border-r border-slate-800/80 shadow-none transition-all duration-300 ease-in-out [&_[data-sidebar=sidebar]]:bg-gradient-to-b [&_[data-sidebar=sidebar]]:from-[#071426] [&_[data-sidebar=sidebar]]:via-[#0a1830] [&_[data-sidebar=sidebar]]:to-[#0b1e3a] [&_[data-sidebar=sidebar]]:text-slate-200"
        >
            <SidebarHeader className="border-b border-white/[0.08] px-3 py-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="group h-16 rounded-2xl border border-white/[0.08] bg-white/[0.045] px-3 text-white shadow-lg shadow-black/10 transition-all duration-300 group-data-[collapsible=icon]:size-12 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:rounded-xl group-data-[collapsible=icon]:px-1 hover:bg-white/[0.08] hover:text-white hover:shadow-xl"
                        >
                            <Link href="/dashboard" prefetch aria-label="SchoolSafe Dashboard">
                                <AppLogo variant="sidebar" />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="px-1 py-4">
                <NavMain items={visibleMainNavItems} />

                {canViewSchoolManagement && (
                    <SidebarGroup className="mt-4 border-t border-white/[0.07] px-2 pt-4">
                        <SidebarGroupLabel className="mb-2 px-3 text-[10px] font-bold tracking-[0.22em] text-slate-400/90 uppercase group-data-[collapsible=icon]:hidden">
                            Manajemen Sekolah
                        </SidebarGroupLabel>

                        <SidebarMenu className="gap-1.5">
                            {comingSoonItems.map((item) => {
                                const Icon = item.icon;

                                return (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            type="button"
                                            disabled
                                            aria-disabled="true"
                                            tooltip={`${item.title} — segera`}
                                            className="h-10 cursor-not-allowed rounded-xl px-3 text-slate-500 opacity-80 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2"
                                        >
                                            <Icon className="size-[17px] shrink-0" strokeWidth={1.8} aria-hidden="true" />

                                            <span className="group-data-[collapsible=icon]:hidden">{item.title}</span>

                                            <span className="ml-auto rounded-full border border-white/[0.08] bg-white/[0.06] px-2 py-0.5 text-[9px] font-bold tracking-wide text-slate-400 uppercase group-data-[collapsible=icon]:hidden">
                                                Segera
                                            </span>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                )}
            </SidebarContent>

            <SidebarFooter className="border-t border-white/[0.08] p-3">
                <div className="mx-0 mb-2 overflow-hidden rounded-2xl border border-white/[0.1] bg-gradient-to-br from-white/[0.09] to-cyan-400/[0.04] p-4 text-xs shadow-lg shadow-black/10 group-data-[collapsible=icon]:hidden">
                    <div className="mb-2 flex items-center gap-3 font-semibold text-white">
                        <div className="flex size-9 items-center justify-center rounded-xl bg-cyan-300/10 text-cyan-200 ring-1 ring-cyan-200/15">
                            <ShieldCheck className="size-4" strokeWidth={2} aria-hidden="true" />
                        </div>

                        <div>
                            <p className="text-sm font-bold">Sistem Aman</p>

                            <p className="mt-0.5 text-[10px] font-medium tracking-wide text-emerald-300 uppercase">Terlindungi</p>
                        </div>
                    </div>

                    <p className="leading-relaxed text-slate-400">Seluruh aktivitas penjemputan tercatat dan dapat ditinjau oleh pihak sekolah.</p>
                </div>

                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
