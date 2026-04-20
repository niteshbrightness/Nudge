import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/app-layout';
import { humanReadableDate } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index } from '@/routes/sms-history';
import type { BreadcrumbItem, NotificationLog, Paginator } from '@/types';

/** Decode HTML entities from Laravel's paginator labels (e.g. &laquo; → «). */
function decodePaginationLabel(html: string): string {
    const doc = new DOMParser().parseFromString(html, 'text/html');

    return doc.documentElement.textContent ?? html;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'SMS History', href: index() },
];

interface Filters {
    search?: string;
    status?: string;
    project_id?: string;
}

interface SimpleProject {
    id: number;
    name: string;
}

const STATUS_OPTIONS = [
    { value: 'sent', label: 'Sent' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'undelivered', label: 'Undelivered' },
    { value: 'failed', label: 'Failed' },
];

function statusBadge(status: NotificationLog['status'], errorMessage: string | null) {
    const variants: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
        delivered: 'default',
        sent: 'secondary',
        undelivered: 'outline',
        failed: 'destructive',
    };

    const labels: Record<string, string> = {
        delivered: 'Delivered',
        sent: 'Sent',
        undelivered: 'Undelivered',
        failed: 'Failed',
    };

    return (
        <Badge variant={variants[status] ?? 'secondary'} title={errorMessage ?? undefined} className="cursor-default">
            {labels[status] ?? status}
        </Badge>
    );
}

export default function SmsHistoryIndex({
    logs,
    filters,
    projects,
}: {
    logs: Paginator<NotificationLog>;
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

    const hasFilters = !!(filters.search || filters.status || filters.project_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SMS History" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading title="SMS History" description="All outbound SMS messages sent to clients." />

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        placeholder="Search by client name or phone..."
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            handleSearch(e.target.value);
                        }}
                        className="max-w-xs"
                    />
                    <SearchableSelect
                        value={filters.status ?? 'all'}
                        options={STATUS_OPTIONS}
                        onChange={(value) => applyFilters({ ...filters, status: value === 'all' ? undefined : value })}
                        allLabel="All Statuses"
                        className="w-40"
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
                    {logs.data.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-muted-foreground">
                            {hasFilters ? 'No SMS messages match your filters.' : 'No SMS messages have been sent yet.'}
                        </p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70 text-left dark:border-sidebar-border">
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Client</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Phone</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Project</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Message</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Status</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Sent</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {logs.data.map((log) => (
                                    <tr key={log.id} className="hover:bg-sidebar-accent/50">
                                        <td className="px-6 py-3 font-medium">{log.client?.name ?? '—'}</td>
                                        <td className="px-6 py-3 text-muted-foreground">{log.client?.phone ?? '—'}</td>
                                        <td className="px-6 py-3 text-muted-foreground">{log.project?.name ?? '—'}</td>
                                        <td className="max-w-xs px-6 py-3 text-muted-foreground">
                                            <span className="line-clamp-2" title={log.message}>
                                                {log.message}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3">{statusBadge(log.status, log.error_message)}</td>
                                        <td className="px-6 py-3 text-xs text-muted-foreground" title={log.sent_at}>
                                            {humanReadableDate(log.sent_at)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {logs.last_page > 1 && (
                    <div className="flex items-center justify-end gap-2">
                        {logs.links.map((link, i) => (
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
