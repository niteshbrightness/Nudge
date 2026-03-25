import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { create, destroy, edit, index } from '@/routes/clients';
import type { BreadcrumbItem, Client, Paginator } from '@/types';

/** Decode HTML entities from Laravel's paginator labels (e.g. &laquo; → «). */
function decodePaginationLabel(html: string): string {
    const doc = new DOMParser().parseFromString(html, 'text/html');

    return doc.documentElement.textContent ?? html;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Clients', href: index() },
];

interface Filters {
    search?: string;
    project_id?: string;
}

interface SimpleProject {
    id: number;
    name: string;
}

export default function ClientsIndex({
    clients,
    filters,
    projects,
}: {
    clients: Paginator<Client>;
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
            <Head title="Clients" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Clients" description="Manage your clients and their notification preferences." />
                    <Button asChild>
                        <Link href={create()}>Add Client</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        placeholder="Search by name or phone..."
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
                    {clients.data.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-muted-foreground">
                            {hasFilters ? (
                                'No clients match your filters.'
                            ) : (
                                <>
                                    No clients yet.{' '}
                                    <Link href={create()} className="underline">
                                        Add your first client.
                                    </Link>
                                </>
                            )}
                        </p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70 text-left dark:border-sidebar-border">
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Name</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Phone</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Timezone</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Projects</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Status</th>
                                    <th className="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {clients.data.map((client) => (
                                    <tr key={client.id} className="hover:bg-sidebar-accent/50">
                                        <td className="px-6 py-3 font-medium">{client.name}</td>
                                        <td className="px-6 py-3 text-muted-foreground">{client.phone}</td>
                                        <td className="px-6 py-3 text-muted-foreground">
                                            {client.timezone?.label ?? '—'}
                                        </td>
                                        <td className="px-6 py-3 text-muted-foreground">
                                            {(client as Client & { projects_count: number }).projects_count ?? 0}
                                        </td>
                                        <td className="px-6 py-3">
                                            <Badge variant={client.is_active ? 'default' : 'secondary'}>
                                                {client.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={edit(client.id)}>Edit</Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    asChild
                                                >
                                                    <Link href={destroy(client.id)} method="delete" as="button">
                                                        Delete
                                                    </Link>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {clients.last_page > 1 && (
                    <div className="flex items-center justify-end gap-2">
                        {clients.links.map((link, i) => (
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
