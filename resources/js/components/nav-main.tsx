import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface NavMainProps {
    items?: NavItem[];
}

function normalizePath(value: string): string {
    const path = value.split(/[?#]/)[0]?.replace(/\/+$/, '');

    return path && path !== '' ? path : '/';
}

function navigationIsActive(currentUrl: string, targetUrl: string): boolean {
    const currentPath = normalizePath(currentUrl);
    const targetPath = normalizePath(targetUrl);

    if (targetPath === '/dashboard') {
        return currentPath === targetPath;
    }

    return currentPath === targetPath || currentPath.startsWith(`${targetPath}/`);
}

export function NavMain({ items = [] }: NavMainProps) {
    const { url } = usePage();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="mb-2 px-3 text-[10px] font-bold tracking-[0.22em] text-slate-400/90 uppercase group-data-[collapsible=icon]:hidden">
                Menu Utama
            </SidebarGroupLabel>

            <SidebarMenu className="gap-1.5">
                {items.map((item) => {
                    const active = navigationIsActive(url, item.url);
                    const Icon = item.icon;

                    return (
                        <SidebarMenuItem key={item.url} className="relative">
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'absolute top-1/2 left-0 z-10 h-6 w-1 -translate-y-1/2 rounded-r-full bg-cyan-300 opacity-0 shadow-[0_0_14px_rgba(103,232,249,0.8)] transition-all duration-300',
                                    active && 'opacity-100',
                                )}
                            />

                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                tooltip={item.title}
                                className={cn(
                                    'group/menu h-11 rounded-xl px-3 text-sm font-medium transition-all duration-300 ease-out group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2',
                                    active
                                        ? 'bg-gradient-to-r from-blue-500/95 to-indigo-500/90 text-white shadow-lg ring-1 shadow-blue-950/30 ring-white/10 hover:text-white'
                                        : 'text-slate-300 hover:translate-x-0.5 hover:bg-white/[0.08] hover:text-white',
                                )}
                            >
                                <Link href={item.url} prefetch>
                                    {Icon && (
                                        <Icon
                                            className={cn(
                                                'size-[18px] shrink-0 transition-all duration-300',
                                                active ? 'text-white drop-shadow-sm' : 'text-slate-400 group-hover/menu:text-cyan-200',
                                            )}
                                            strokeWidth={active ? 2.2 : 1.9}
                                            aria-hidden="true"
                                        />
                                    )}

                                    <span className="truncate group-data-[collapsible=icon]:hidden">{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
