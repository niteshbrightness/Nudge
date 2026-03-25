import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { humanReadableDate } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/webhooks';
import type { BreadcrumbItem, WebhookEvent } from '@/types';

const breadcrumbs = (event: WebhookEvent): BreadcrumbItem[] => [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Webhook Events', href: index() },
    { title: `Event #${event.id}`, href: show(event.id) },
];

export default function WebhookEventShow({ event }: { event: WebhookEvent }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs(event)}>
            <Head title={`Webhook Event #${event.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={`Event #${event.id}`}
                        description={`Received ${humanReadableDate(event.received_at)}`}
                    />
                    <span className="rounded-full bg-sidebar-accent px-3 py-1 text-sm font-medium">
                        {event.event_type}
                    </span>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Project</p>
                        <p className="mt-1">
                            {event.project?.id ? (
                                <Link href={`/projects/${event.project.id}`} className="hover:underline">
                                    {event.project.name}
                                </Link>
                            ) : (
                                <span className="text-muted-foreground italic">Unknown</span>
                            )}
                        </p>
                    </div>
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Short URL</p>
                        <p className="mt-1">
                            {event.short_url ? (
                                <a
                                    href={event.short_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-sm hover:underline"
                                >
                                    {event.short_url}
                                </a>
                            ) : (
                                <span className="text-muted-foreground italic">—</span>
                            )}
                        </p>
                    </div>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                        <h2 className="font-medium">Parsed Data</h2>
                    </div>
                    <pre className="overflow-auto px-6 py-4 text-xs">{JSON.stringify(event.parsed_data, null, 2)}</pre>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                        <h2 className="font-medium">Raw Payload</h2>
                    </div>
                    <pre className="overflow-auto px-6 py-4 text-xs">{JSON.stringify(event.raw_payload, null, 2)}</pre>
                </div>

                <div>
                    <Button variant="ghost" asChild>
                        <Link href={index()}>← Back to events</Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
