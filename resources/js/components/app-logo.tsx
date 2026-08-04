import { ShieldCheck } from 'lucide-react';

export default function AppLogo() {
    return (
        <>
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-[22px] bg-gradient-to-br from-[#4367c0] to-[#5b8def] text-white shadow-lg shadow-[#4f7cac]/20">
                <ShieldCheck className="size-5" strokeWidth={2.2} />
            </div>

            <div className="ml-2 grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-semibold text-[#1f3b5b]">SchoolSafe</span>
                <span className="truncate text-[11px] tracking-[0.28em] text-[#829ab1] uppercase">Smart Pickup</span>
            </div>
        </>
    );
}
