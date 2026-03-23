import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/projects';
import type { BreadcrumbItem, Project } from '@/types';

const breadcrumbs = (project: Project): BreadcrumbItem[] => [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Projects', href: index() },
    { title: project.name, href: show(project.id) },
];

export default function ProjectShow({ project }: { project: Project }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs(project)}>
            <Head title={project.name} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-start justify-between">
                    <Heading
                        title={project.name}
                        description={project.description ?? 'No description provided.'}
                    />
                    <div className="flex items-center gap-2">
                        {project.url && (
                            <Button variant="outline" size="sm" asChild>
                                <a href={project.url} target="_blank" rel="noopener noreferrer">
                                    Open in ActiveCollab
                                </a>
                            </Button>
                        )}
                        <Badge variant={project.status === 'completed' ? 'secondary' : 'default'}>
                            {project.status.replace('_', ' ')}
                        </Badge>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-xs text-muted-foreground uppercase tracking-wide">Client</p>
                        <p className="mt-1 font-medium">
                            {project.client ? (
                                <Link href={`/clients/${project.client.id}/edit`} className="hover:underline">
                                    {project.client.name}
                                </Link>
                            ) : (
                                <span className="italic text-muted-foreground">Unassigned</span>
                            )}
                        </p>
                    </div>
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-xs text-muted-foreground uppercase tracking-wide">ActiveCollab ID</p>
                        <p className="mt-1 font-medium">{project.activecollab_id ?? '—'}</p>
                    </div>
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-xs text-muted-foreground uppercase tracking-wide">Webhook Events</p>
                        <p className="mt-1 font-medium">{project.webhook_events?.length ?? 0}</p>
                    </div>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                        <h2 className="font-medium">Recent Webhook Events</h2>
                    </div>
                    {!project.webhook_events || project.webhook_events.length === 0 ? (
                        <p className="px-6 py-8 text-center text-sm text-muted-foreground">
                            No webhook events for this project yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {project.webhook_events.map((event) => (
                                <li key={event.id} className="flex items-start justify-between px-6 py-4">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="rounded-full bg-sidebar-accent px-2 py-0.5 text-xs font-medium">
                                                {event.event_type}
                                            </span>
                                            {event.parsed_data?.title && (
                                                <span className="truncate text-sm">
                                                    {String(event.parsed_data.title)}
                                                </span>
                                            )}
                                        </div>
                                        {event.short_url && (
                                            <a
                                                href={event.short_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="mt-1 block text-xs text-muted-foreground hover:underline"
                                            >
                                                {event.short_url}
                                            </a>
                                        )}
                                    </div>
                                    <span className="ml-4 shrink-0 text-xs text-muted-foreground">
                                        {event.received_at}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
