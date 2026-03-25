import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/app-layout';
import { humanReadableDate } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/webhooks';
import type { BreadcrumbItem, Paginator, WebhookEvent } from '@/types';

/** Decode HTML entities from Laravel's paginator labels (e.g. &laquo; → «). */
function decodePaginationLabel(html: string): string {
    const doc = new DOMParser().parseFromString(html, 'text/html');

    return doc.documentElement.textContent ?? html;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Webhook Events', href: index() },
];

interface Filters {
    search?: string;
    project_id?: string;
}

interface SimpleProject {
    id: number;
    name: string;
}

export default function WebhooksIndex({
    events,
    filters,
    projects,
}: {
    events: Paginator<WebhookEvent>;
    filters: Filters;
    projects: SimpleProject[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = useCallback((newFilters: Filters) => {
        router.get(
            index(),
            Object.fromEntries(
                Object.entries(newFilters).filter(([, v]) => v !== '' && v !== undefined && v !== 'all'),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, []);

    const searchTimerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    const handleSearch = useCallback(
        (value: string) => {
            clearTimeout(searchTimerRef.current);
            searchTimerRef.current = setTimeout(() => {
                applyFilters({ ...filters, search: value || undefined });
            }, 300);
        },
        [filters, applyFilters],
    );

    const hasFilters = !!(filters.search || filters.project_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Webhook Events" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading title="Webhook Events" description="Incoming events from ActiveCollab." />

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        placeholder="Search by event type..."
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            handleSearch(e.target.value);
                        }}
                        className="max-w-xs"
                    />
                    <SearchableSelect
                        value={filters.project_id ?? 'all'}
                        options={projects.map((p) => ({ value: String(p.id), label: p.name }))}
                        onChange={(value) =>
                            applyFilters({ ...filters, project_id: value === 'all' ? undefined : value })
                        }
                        allLabel="All Projects"
                        className="w-48"
                    />
                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                applyFilters({});
                            }}
                        >
                            Clear filters
                        </Button>
                    )}
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {events.data.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-muted-foreground">
                            {hasFilters
                                ? 'No webhook events match your filters.'
                                : 'No webhook events received yet. Configure your ActiveCollab webhook endpoint to start receiving events.'}
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
                                        <td
                                            className="px-6 py-3 text-xs text-muted-foreground"
                                            title={event.received_at}
                                        >
                                            {humanReadableDate(event.received_at)}
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
                                    <Link href={link.url}>{decodePaginationLabel(link.label)}</Link>
                                ) : (
                                    <span>{decodePaginationLabel(link.label)}</span>
                                )}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
