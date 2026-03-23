import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index as clientsRoute } from '@/routes/clients';
import { index as projectsRoute } from '@/routes/projects';
import { index as webhooksRoute } from '@/routes/webhooks';
import type { BreadcrumbItem, WebhookEvent } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

interface Stats {
    clients: number;
    projects: number;
    webhookEvents: number;
    recentEvents: WebhookEvent[];
}

export default function Dashboard({ stats }: { stats: Stats }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <Link
                        href={clientsRoute()}
                        className="rounded-xl border border-sidebar-border/70 p-6 transition-colors hover:bg-sidebar-accent dark:border-sidebar-border"
                    >
                        <p className="text-sm text-muted-foreground">Clients</p>
                        <p className="mt-1 text-3xl font-semibold">{stats.clients}</p>
                    </Link>
                    <Link
                        href={projectsRoute()}
                        className="rounded-xl border border-sidebar-border/70 p-6 transition-colors hover:bg-sidebar-accent dark:border-sidebar-border"
                    >
                        <p className="text-sm text-muted-foreground">Projects</p>
                        <p className="mt-1 text-3xl font-semibold">{stats.projects}</p>
                    </Link>
                    <Link
                        href={webhooksRoute()}
                        className="rounded-xl border border-sidebar-border/70 p-6 transition-colors hover:bg-sidebar-accent dark:border-sidebar-border"
                    >
                        <p className="text-sm text-muted-foreground">Webhook Events</p>
                        <p className="mt-1 text-3xl font-semibold">{stats.webhookEvents}</p>
                    </Link>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                        <h2 className="font-medium">Recent Webhook Events</h2>
                    </div>
                    {stats.recentEvents.length === 0 ? (
                        <p className="px-6 py-8 text-center text-sm text-muted-foreground">
                            No webhook events yet. Configure your ActiveCollab webhook to get started.
                        </p>
                    ) : (
                        <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {stats.recentEvents.map((event) => (
                                <li key={event.id} className="flex items-center justify-between px-6 py-3">
                                    <div>
                                        <span className="rounded-full bg-sidebar-accent px-2 py-0.5 text-xs font-medium">
                                            {event.event_type}
                                        </span>
                                        {event.project && (
                                            <span className="ml-2 text-sm text-muted-foreground">
                                                {event.project.name}
                                            </span>
                                        )}
                                    </div>
                                    <span className="text-xs text-muted-foreground">{event.received_at}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
