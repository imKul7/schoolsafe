import { ShieldCheck } from 'lucide-react';

export default function AppLogo() {
    return (
        <>
            <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#5b8def] text-white shadow-sm shadow-blue-200">
                <ShieldCheck className="size-5" strokeWidth={2.2} />
            </div>

            <div className="ml-1 grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-bold text-[#243b53]">SchoolSafe</span>

                <span className="truncate text-xs text-[#829ab1]">Smart Pickup System</span>
            </div>
        </>
    );
}
