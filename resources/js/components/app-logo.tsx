import { cn } from '@/lib/utils';
import { ShieldCheck } from 'lucide-react';

interface AppLogoProps {
    variant?: 'default' | 'sidebar';
}

export default function AppLogo({ variant = 'default' }: AppLogoProps) {
    const sidebar = variant === 'sidebar';

    return (
        <>
            <div
                className={cn(
                    'flex size-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 via-indigo-500 to-cyan-400 text-white shadow-lg ring-1 shadow-blue-950/25 ring-white/20 transition-transform duration-300 group-hover:scale-105',
                    sidebar && 'shadow-blue-950/40',
                )}
            >
                <ShieldCheck className="size-5" strokeWidth={2.1} aria-hidden="true" />
            </div>

            <div className="ml-2 grid min-w-0 flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                <span className={cn('truncate text-sm font-bold tracking-tight', sidebar ? 'text-white' : 'text-[#1f3b5b]')}>SchoolSafe</span>

                <span
                    className={cn('truncate text-[10px] font-semibold tracking-[0.28em] uppercase', sidebar ? 'text-blue-200/70' : 'text-[#829ab1]')}
                >
                    Smart Pickup
                </span>
            </div>
        </>
    );
}
