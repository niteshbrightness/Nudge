import { usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: AppLayoutProps) {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        setVisible(true);
        const t = setTimeout(() => setVisible(false), 4000);
        return () => clearTimeout(t);
    }, [flash]);

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />

                {visible && flash?.success && (
                    <div className="px-4 pt-2">
                        <Alert className="border-green-500/30 bg-green-50 text-green-800 dark:bg-green-950/30 dark:text-green-300">
                            <CheckCircle2 />
                            <AlertDescription>{flash.success}</AlertDescription>
                        </Alert>
                    </div>
                )}

                {visible && flash?.error && (
                    <div className="px-4 pt-2">
                        <Alert variant="destructive">
                            <XCircle />
                            <AlertDescription>{flash.error}</AlertDescription>
                        </Alert>
                    </div>
                )}

                {children}
            </AppContent>
        </AppShell>
    );
}
