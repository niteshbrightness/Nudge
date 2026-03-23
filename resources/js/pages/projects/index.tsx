import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index, show, sync } from '@/routes/projects';
import type { BreadcrumbItem, Paginator, Project } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Projects', href: index() },
];

const statusVariant = (status: Project['status']) => {
    if (status === 'completed') return 'secondary';
    if (status === 'on_hold') return 'outline';
    return 'default';
};

export default function ProjectsIndex({ projects }: { projects: Paginator<Project> }) {
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

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {projects.data.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-muted-foreground">
                            No projects yet. Click <strong>Sync from ActiveCollab</strong> to import your projects.
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
                                            <Link
                                                href={show(project.id)}
                                                className="hover:underline"
                                            >
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
            </div>
        </AppLayout>
    );
}
