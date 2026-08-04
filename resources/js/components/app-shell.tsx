import { SidebarProvider } from '@/components/ui/sidebar';
import { useState, type ReactNode } from 'react';

interface AppShellProps {
    children: ReactNode;
    variant?: 'header' | 'sidebar';
}

export function AppShell({ children, variant = 'header' }: AppShellProps) {
    const [isOpen, setIsOpen] = useState(() => (typeof window !== 'undefined' ? localStorage.getItem('sidebar') !== 'false' : true));

    const handleSidebarChange = (open: boolean) => {
        setIsOpen(open);

        if (typeof window !== 'undefined') {
            localStorage.setItem('sidebar', String(open));
        }
    };

    if (variant === 'header') {
        return <div className="flex min-h-svh w-full flex-col bg-slate-50">{children}</div>;
    }

    return (
        <SidebarProvider
            defaultOpen={isOpen}
            open={isOpen}
            onOpenChange={handleSidebarChange}
            className="h-svh min-h-0 w-full overflow-hidden bg-slate-50"
        >
            {children}
        </SidebarProvider>
    );
}
