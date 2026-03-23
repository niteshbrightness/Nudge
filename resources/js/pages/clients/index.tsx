import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { create, destroy, edit, index } from '@/routes/clients';
import type { BreadcrumbItem, Client, Paginator } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Clients', href: index() },
];

export default function ClientsIndex({ clients }: { clients: Paginator<Client> }) {
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

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {clients.data.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-muted-foreground">
                            No clients yet.{' '}
                            <Link href={create()} className="underline">
                                Add your first client.
                            </Link>
                        </p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70 text-left dark:border-sidebar-border">
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Name</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Phone</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Timezone</th>
                                    <th className="px-6 py-3 font-medium text-muted-foreground">Projects</th>
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
                                            {client.projects?.length ?? 0}
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
