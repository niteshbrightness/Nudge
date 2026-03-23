import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Plug, XCircle } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { create, destroy, edit, index } from '@/routes/integrations';
import type { BreadcrumbItem, IntegrationDefinition } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Integrations', href: index() },
];

export default function IntegrationsIndex({ definitions }: { definitions: IntegrationDefinition[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integrations" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Integrations"
                    description="Connect external services to enable notifications, URL shortening, and project sync."
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {definitions.map((def) => (
                        <div
                            key={def.service}
                            className="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-sidebar-accent text-sidebar-accent-foreground">
                                        <Plug className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="font-semibold">{def.label}</p>
                                        <p className="text-xs text-muted-foreground">{def.description}</p>
                                    </div>
                                </div>

                                {def.isConnected ? (
                                    <Badge
                                        variant="outline"
                                        className="shrink-0 gap-1 border-green-500/30 text-green-600 dark:text-green-400"
                                    >
                                        <CheckCircle2 className="h-3 w-3" />
                                        Connected
                                    </Badge>
                                ) : (
                                    <Badge variant="outline" className="shrink-0 gap-1 text-muted-foreground">
                                        <XCircle className="h-3 w-3" />
                                        Not connected
                                    </Badge>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                {def.isConnected && def.integrationId ? (
                                    <>
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={edit(def.integrationId)}>Edit</Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="text-destructive hover:text-destructive"
                                            asChild
                                        >
                                            <Link href={destroy(def.integrationId)} method="delete" as="button">
                                                Disconnect
                                            </Link>
                                        </Button>
                                    </>
                                ) : (
                                    <Button size="sm" asChild>
                                        <Link href={create(def.service)}>Connect</Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
