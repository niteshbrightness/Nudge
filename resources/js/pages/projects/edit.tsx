import { Head } from '@inertiajs/react';
import { useForm } from 'laravel-precognition-react-inertia';
import ProjectController from '@/actions/App/Http/Controllers/Projects/ProjectController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { InfoTooltip } from '@/components/ui/info-tooltip';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { edit, index, show } from '@/routes/projects';
import type { BreadcrumbItem, Client, Project } from '@/types';

const breadcrumbs = (project: Project): BreadcrumbItem[] => [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Projects', href: index() },
    { title: project.name, href: show(project.id) },
    { title: 'Edit', href: edit(project.id) },
];

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
    { value: 'on_hold', label: 'On Hold' },
];

export default function EditProject({
    project,
    clients,
}: {
    project: Project;
    clients: Pick<Client, 'id' | 'name'>[];
}) {
    const form = useForm('put', ProjectController.update(project.id).url, {
        client_id: project.client_id ? String(project.client_id) : 'none',
        status: project.status,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit({ preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(project)}>
            <Head title={`Edit ${project.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title={`Edit ${project.name}`}
                    description="Assign this project to a client and update its status."
                />

                <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <form onSubmit={submit} className="space-y-5">
                        <div className="grid gap-2">
                            <div className="flex items-center gap-1.5">
                                <Label htmlFor="client_id">Client</Label>
                                <InfoTooltip text="Webhook events from this project will be sent as SMS updates to the assigned client." />
                            </div>
                            <SearchableSelect
                                value={form.data.client_id}
                                options={clients.map((c) => ({ value: String(c.id), label: c.name }))}
                                onChange={(val) => {
                                    form.setData('client_id', val);
                                    form.validate('client_id');
                                }}
                                allValue="none"
                                allLabel="Unassigned"
                            />
                            <InputError message={form.errors.client_id} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center gap-1.5">
                                <Label htmlFor="status">Status</Label>
                                <InfoTooltip text="Only Active projects send SMS notifications. On Hold and Completed projects are excluded." />
                            </div>
                            <SearchableSelect
                                value={form.data.status}
                                options={statusOptions}
                                onChange={(val) => {
                                    form.setData('status', val);
                                    form.validate('status');
                                }}
                                allValue=""
                                allLabel="Select status…"
                            />
                            <InputError message={form.errors.status} />
                        </div>

                        <div className="flex gap-3">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving…' : 'Save Changes'}
                            </Button>
                            <Button variant="ghost" asChild>
                                <a href={show.url(project.id)}>Cancel</a>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
