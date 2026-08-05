import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

interface AppSidebarLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function AppSidebarLayout({ children, breadcrumbs = [] }: AppSidebarLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />

            <AppContent variant="sidebar">
                <div className="relative z-20 shrink-0">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                </div>

                <div className="dashboard-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain scroll-smooth">{children}</div>
            </AppContent>
        </AppShell>
    );
}
