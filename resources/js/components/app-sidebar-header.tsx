import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { LayoutDashboard, ShieldCheck } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const pageTitle = breadcrumbs.at(-1)?.title ?? 'Dashboard';

    return (
        <header className="sticky top-0 z-30 flex h-[72px] shrink-0 items-center justify-between gap-3 border-b border-slate-200/70 bg-white/85 px-5 shadow-[0_1px_0_rgba(15,23,42,0.03)] backdrop-blur-xl transition-[width,height] duration-300 ease-in-out md:px-7">
            <div className="flex min-w-0 items-center gap-3">
                <SidebarTrigger className="-ml-1 size-10 rounded-xl border border-slate-200/80 bg-white text-slate-600 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-50 hover:text-blue-600 hover:shadow-md focus-visible:ring-2 focus-visible:ring-blue-500/30" />

                <div className="hidden h-8 w-px bg-slate-200 sm:block" />

                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <div className="flex size-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 ring-1 ring-blue-100">
                            <LayoutDashboard className="size-4" strokeWidth={2} aria-hidden="true" />
                        </div>

                        <div className="min-w-0">
                            <p className="truncate text-sm font-bold tracking-tight text-slate-900">{pageTitle}</p>

                            {breadcrumbs.length > 1 && (
                                <div className="mt-0.5 hidden text-xs text-slate-500 sm:block">
                                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <div className="hidden items-center gap-2 rounded-full border border-blue-100 bg-blue-50/80 px-3 py-1.5 text-xs font-semibold text-blue-700 shadow-sm md:flex">
                <ShieldCheck className="size-3.5" strokeWidth={2} aria-hidden="true" />

                <span>Pusat kontrol penjemputan</span>

                <span className="size-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.65)]" aria-hidden="true" />
            </div>
        </header>
    );
}
