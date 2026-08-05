import { cn } from '@/lib/utils';
import * as React from 'react';

interface AppContentProps extends React.ComponentProps<'main'> {
    variant?: 'header' | 'sidebar';
}

export function AppContent({ variant = 'header', children, className, ...props }: AppContentProps) {
    if (variant === 'sidebar') {
        return (
            <main className={cn('relative flex h-svh min-w-0 flex-1 flex-col overflow-hidden bg-slate-50', className)} {...props}>
                {children}
            </main>
        );
    }

    return (
        <main className={cn('mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4', className)} {...props}>
            {children}
        </main>
    );
}
