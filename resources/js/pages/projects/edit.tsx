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
import type { BreadcrumbItem, Project } from '@/types';

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

export default function EditProject({ project }: { project: Project }) {
    const form = useForm('put', ProjectController.update(project.id).url, {
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
                    description="Update this project's status. Client assignments are managed from the Clients page."
                />

                <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <form onSubmit={submit} className="space-y-5">
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
