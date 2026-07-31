import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
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

    /*
     * Dashboard hanya aktif pada halaman dashboard.
     * Tanpa kondisi ini, menu dashboard dapat aktif
     * pada route lain yang memiliki prefix serupa.
     */
    if (targetPath === '/dashboard') {
        return currentPath === targetPath;
    }

    return currentPath === targetPath || currentPath.startsWith(`${targetPath}/`);
}

export function NavMain({ items = [] }: NavMainProps) {
    const { url } = usePage();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Menu Utama</SidebarGroupLabel>

            <SidebarMenu>
                {items.map((item) => {
                    const active = navigationIsActive(url, item.url);

                    const Icon = item.icon;

                    return (
                        <SidebarMenuItem key={item.url}>
                            <SidebarMenuButton asChild isActive={active} tooltip={item.title}>
                                <Link href={item.url} prefetch>
                                    {Icon && <Icon />}

                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
