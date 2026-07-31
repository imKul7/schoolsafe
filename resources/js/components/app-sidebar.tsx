import { Link, usePage } from '@inertiajs/react';
import { CalendarCheck2, History, LayoutDashboard, ScanFace, ShieldCheck, UserRoundCheck, UsersRound, type LucideIcon } from 'lucide-react';

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
        <Sidebar collapsible="icon" variant="inset" className="border-r border-[#e6eef5] bg-[#fbfdff]">
            <SidebarHeader className="border-b border-[#edf2f7] px-2 py-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="rounded-2xl transition-colors hover:bg-[#eef6ff]">
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="px-2 py-3">
                <NavMain items={visibleMainNavItems} />

                {canViewSchoolManagement && (
                    <SidebarGroup>
                        <SidebarGroupLabel className="text-xs font-semibold tracking-wider text-[#9fb3c8] uppercase">
                            Manajemen Sekolah
                        </SidebarGroupLabel>

                        <SidebarMenu className="gap-1">
                            {comingSoonItems.map((item) => {
                                const Icon = item.icon;

                                return (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            type="button"
                                            disabled
                                            aria-disabled="true"
                                            className="cursor-not-allowed rounded-xl text-[#829ab1] opacity-75"
                                        >
                                            <Icon className="size-4" />

                                            <span>{item.title}</span>

                                            <span className="ml-auto rounded-full bg-[#f1f5f9] px-2 py-0.5 text-[10px] font-semibold text-[#829ab1] group-data-[collapsible=icon]:hidden">
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

            <SidebarFooter className="border-t border-[#edf2f7] p-2">
                <div className="mx-1 mb-2 rounded-2xl border border-[#dceaf5] bg-gradient-to-br from-[#eef7fc] to-[#eef9f6] p-3 text-xs group-data-[collapsible=icon]:hidden">
                    <div className="mb-1.5 flex items-center gap-2 font-semibold text-[#335e7e]">
                        <div className="flex size-7 items-center justify-center rounded-lg bg-white text-[#64b6ac] shadow-sm">
                            <ShieldCheck className="size-4" />
                        </div>
                        Sistem Aman
                    </div>

                    <p className="leading-relaxed text-[#627d98]">Seluruh aktivitas penjemputan tercatat dan dapat ditinjau oleh pihak sekolah.</p>
                </div>

                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
