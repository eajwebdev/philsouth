import * as React from 'react';
import { usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { NavSidebar } from '@/components/nav-sidebar';
import { TopBar } from '@/components/top-bar';
import type { PageProps } from '@/types';

function useFlashToasts() {
    const { flash } = usePage<PageProps>().props;

    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);
}

export default function AppLayout({ children }: { children: React.ReactNode }) {
    useFlashToasts();

    return (
        <TooltipProvider delayDuration={200}>
            <div className="flex min-h-screen bg-background">
                <NavSidebar />
                <div className="flex min-w-0 flex-1 flex-col">
                    <TopBar />
                    <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                        <div className="mx-auto w-full max-w-7xl">{children}</div>
                    </main>
                </div>
            </div>
        </TooltipProvider>
    );
}
