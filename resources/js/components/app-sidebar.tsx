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
        <Sidebar collapsible="icon" variant="inset" className="border-r border-[#e6eef5] bg-white/95 shadow-sm backdrop-blur-sm">
            <SidebarHeader className="border-b border-[#edf2f7] px-3 py-4">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="rounded-3xl transition-colors duration-200 hover:bg-[#eef6ff]">
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="px-3 py-4">
                <NavMain items={visibleMainNavItems} />

                {canViewSchoolManagement && (
                    <SidebarGroup>
                        <SidebarGroupLabel className="text-xs font-semibold tracking-[0.24em] text-[#8b9fb5] uppercase">
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
                                            className="cursor-not-allowed rounded-2xl text-[#8b9fb5] opacity-80"
                                        >
                                            <Icon className="size-4" />

                                            <span>{item.title}</span>

                                            <span className="ml-auto rounded-full bg-[#f3f7fb] px-2 py-0.5 text-[10px] font-semibold text-[#7a90ae] group-data-[collapsible=icon]:hidden">
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

            <SidebarFooter className="border-t border-[#edf2f7] p-3">
                <div className="mx-1 mb-2 rounded-3xl border border-[#d9e8ef] bg-gradient-to-br from-[#f7fbff] to-[#eef7f8] p-4 text-xs group-data-[collapsible=icon]:hidden">
                    <div className="mb-1.5 flex items-center gap-3 font-semibold text-[#345a7a]">
                        <div className="flex h-9 w-9 items-center justify-center rounded-2xl bg-white text-[#53a69f] shadow-sm">
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
