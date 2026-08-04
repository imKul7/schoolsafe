import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { useIsMobile } from '@/hooks/use-mobile';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';

function roleLabel(role: string): string {
    switch (role) {
        case 'super_admin':
            return 'Super Admin';

        case 'school_admin':
            return 'Admin Sekolah';

        case 'gate_officer':
            return 'Petugas Gerbang';

        case 'teacher':
            return 'Guru';

        case 'parent':
            return 'Orang Tua';

        default:
            return 'Pengguna';
    }
}

export function NavUser() {
    const { auth } = usePage<SharedData>().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const getInitials = useInitials();

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="h-16 rounded-2xl border border-white/10 bg-white/[0.06] px-3 text-white shadow-lg shadow-black/10 transition-all duration-300 group-data-[collapsible=icon]:size-11 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:rounded-xl group-data-[collapsible=icon]:p-1.5 hover:bg-white/[0.1] hover:text-white data-[state=open]:bg-white/[0.12] data-[state=open]:text-white"
                        >
                            <Avatar className="size-9 shrink-0 border border-white/15 shadow-sm">
                                <AvatarImage src={auth.user.avatar} alt={auth.user.name} />

                                <AvatarFallback className="bg-gradient-to-br from-blue-500 to-indigo-500 text-xs font-bold text-white">
                                    {getInitials(auth.user.name)}
                                </AvatarFallback>
                            </Avatar>

                            <div className="grid min-w-0 flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                                <span className="truncate text-sm font-semibold text-white">{auth.user.name}</span>

                                <span className="mt-0.5 truncate text-[11px] font-medium text-slate-400">{roleLabel(auth.user.role)}</span>
                            </div>

                            <ChevronsUpDown className="ml-auto size-4 text-slate-400 group-data-[collapsible=icon]:hidden" aria-hidden="true" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-xl border-slate-200/80 shadow-xl"
                        align="end"
                        side={isMobile ? 'bottom' : state === 'collapsed' ? 'left' : 'bottom'}
                    >
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
