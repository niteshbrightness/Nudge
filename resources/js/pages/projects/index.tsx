import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index, show, sync } from '@/routes/projects';
import type { BreadcrumbItem, Paginator, Project } from '@/types';

/** Decode HTML entities from Laravel's paginator labels (e.g. &laquo; → «). */
function decodePaginationLabel(html: string): string {
    const doc = new DOMParser().parseFromString(html, 'text/html');

    return doc.documentElement.textContent ?? html;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Projects', href: index() },
];

const statusVariant = (status: Project['status']) => {
    if (status === 'completed') {
        return 'secondary';
    }

    if (status === 'on_hold') {
        return 'outline';
    }

    return 'default';
};

interface Filters {
    search?: string;
    status?: string;
    client_id?: string;
}

interface SimpleClient {
    id: number;
    name: string;
}

export default function ProjectsIndex({
    projects,
    filters,
    clients,
}: {
    projects: Paginator<Project>;
    filters: Filters;
    clients: SimpleClient[];
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

    const hasFilters = !!(filters.search || filters.status || filters.client_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Projects" description="Projects synced from ActiveCollab." />
                    <Button asChild>
                        <Link href={sync()} method="post" as="button">
                            Sync from ActiveCollab
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        placeholder="Search by name..."
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            handleSearch(e.target.value);
                        }}
                        className="max-w-xs"
                    />
                    <SearchableSelect
                        value={filters.status ?? 'all'}
                        options={[
                            { value: 'active', label: 'Active' },
                            { value: 'completed', label: 'Completed' },
                            { value: 'on_hold', label: 'On Hold' },
                        ]}
                        onChange={(value) => applyFilters({ ...filters, status: value === 'all' ? undefined : value })}
                        allLabel="All Statuses"
                        className="w-40"
                    />
                    <SearchableSelect
                        value={filters.client_id ?? 'all'}
                        options={clients.map((c) => ({ value: String(c.id), label: c.name }))}
                        onChange={(value) =>
                            applyFilters({ ...filters, client_id: value === 'all' ? undefined : value })
                        }
                        allLabel="All Clients"
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
                    {projects.data.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-muted-foreground">
                            {hasFilters ? (
                                'No projects match your filters.'
                            ) : (
                                <>
                                    No projects yet. Click <strong>Sync from ActiveCollab</strong> to import your
                                    projects.
                                </>
                            )}
                        </p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70 text-left dark:border-sidebar-border">
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Name</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Client</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Status</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">AC ID</th>
                                    <th className="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {projects.data.map((project) => (
                                    <tr key={project.id} className="hover:bg-sidebar-accent/50">
                                        <td className="px-6 py-3 font-medium">
                                            <Link href={show(project.id)} className="hover:underline">
                                                {project.name}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-3 text-muted-foreground">
                                            {project.client?.name ?? <span className="italic">Unassigned</span>}
                                        </td>
                                        <td className="px-6 py-3">
                                            <Badge variant={statusVariant(project.status)}>
                                                {project.status.replace('_', ' ')}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-3 text-muted-foreground">
                                            {project.activecollab_id ?? '—'}
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={show(project.id)}>View</Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {projects.last_page > 1 && (
                    <div className="flex items-center justify-end gap-2">
                        {projects.links.map((link, i) => (
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
