import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { LayoutDashboard } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const pageTitle = breadcrumbs.at(-1)?.title ?? 'Dashboard';

    return (
        <header className="flex h-[72px] shrink-0 items-center justify-between gap-3 border-b border-[#e2eaf0] bg-white/90 px-5 backdrop-blur-sm transition-[width,height] duration-200 ease-linear md:px-7">
            <div className="flex min-w-0 items-center gap-3">
                <SidebarTrigger className="-ml-1 rounded-xl text-[#486b8d] hover:bg-[#eef4fa] hover:text-[#315fae]" />
                <div className="hidden h-8 w-px bg-[#e2eaf0] sm:block" />
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <LayoutDashboard className="size-4 text-[#5b8def]" />
                        <p className="truncate text-sm font-bold text-[#17324d]">{pageTitle}</p>
                    </div>
                    {breadcrumbs.length > 1 && (
                        <div className="mt-0.5 hidden text-xs text-[#829ab1] sm:block">
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    )}
                </div>
            </div>
            <p className="hidden text-xs font-medium text-[#829ab1] md:block">Pusat kontrol penjemputan sekolah</p>
        </header>
    );
}
