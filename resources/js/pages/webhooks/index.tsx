import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/webhooks';
import type { BreadcrumbItem, Paginator, WebhookEvent } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Webhook Events', href: index() },
];

export default function WebhooksIndex({ events }: { events: Paginator<WebhookEvent> }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Webhook Events" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title="Webhook Events"
                    description="Incoming events from ActiveCollab."
                />

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {events.data.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-muted-foreground">
                            No webhook events received yet. Configure your ActiveCollab webhook endpoint to start receiving events.
                        </p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70 text-left dark:border-sidebar-border">
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Event Type</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Project</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Short URL</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Received</th>
                                    <th className="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {events.data.map((event) => (
                                    <tr key={event.id} className="hover:bg-sidebar-accent/50">
                                        <td className="px-6 py-3">
                                            <span className="rounded-full bg-sidebar-accent px-2 py-0.5 text-xs font-medium">
                                                {event.event_type}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3 text-muted-foreground">
                                            {event.project?.name ?? '—'}
                                        </td>
                                        <td className="px-6 py-3">
                                            {event.short_url ? (
                                                <a
                                                    href={event.short_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-xs text-muted-foreground hover:underline"
                                                >
                                                    {event.short_url}
                                                </a>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-3 text-xs text-muted-foreground">
                                            {event.received_at}
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={show(event.id)}>View</Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {events.last_page > 1 && (
                    <div className="flex items-center justify-end gap-2">
                        {events.links.map((link, i) => (
                            <Button
                                key={i}
                                variant={link.active ? 'default' : 'ghost'}
                                size="sm"
                                disabled={!link.url}
                                asChild={!!link.url}
                            >
                                {link.url ? (
                                    <Link href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />
                                ) : (
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                )}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
