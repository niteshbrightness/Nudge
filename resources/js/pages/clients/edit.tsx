import { Head, Link } from '@inertiajs/react';
import { useForm } from 'laravel-precognition-react-inertia';
import ClientController from '@/actions/App/Http/Controllers/Clients/ClientController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { InfoTooltip } from '@/components/ui/info-tooltip';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ProjectMultiSelect } from '@/components/ui/project-multi-select';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { TimezoneSelect } from '@/components/ui/timezone-select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { edit, index } from '@/routes/clients';
import type { BreadcrumbItem, Client, Timezone } from '@/types';

const breadcrumbs = (client: Client): BreadcrumbItem[] => [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Clients', href: index() },
    { title: `Edit ${client.name}`, href: edit(client.id) },
];

const statusOptions = [
    { value: 'true', label: 'Active' },
    { value: 'false', label: 'Inactive' },
];

export default function EditClient({
    client,
    timezones,
    availableProjects,
    selectedProjectIds,
}: {
    client: Client;
    timezones: Timezone[];
    availableProjects: Array<{ id: number; name: string }>;
    selectedProjectIds: number[];
}) {
    const form = useForm('patch', ClientController.update(client.id).url, {
        name: client.name,
        phone: client.phone,
        timezone_id: client.timezone_id as number | null,
        notes: client.notes ?? '',
        is_active: client.is_active ? 'true' : 'false',
        project_ids: selectedProjectIds,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit({ preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs(client)}>
            <Head title={`Edit ${client.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title={`Edit ${client.name}`}
                    description="Update client details and notification preferences."
                />

                <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <form onSubmit={submit} className="space-y-5">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                onBlur={() => form.validate('name')}
                                placeholder="Company or person name"
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone number</Label>
                            <Input
                                id="phone"
                                type="tel"
                                value={form.data.phone}
                                onChange={(e) => form.setData('phone', e.target.value)}
                                onBlur={() => form.validate('phone')}
                                placeholder="+17096789000"
                            />
                            <p className="text-sm text-muted-foreground">
                                Must include + and country code, e.g. +17096789000
                            </p>
                            <InputError message={form.errors.phone} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center gap-1.5">
                                <Label htmlFor="timezone_id">Timezone</Label>
                                <InfoTooltip text="Notifications are delivered at the configured time slots converted to the client's local timezone." />
                            </div>
                            <TimezoneSelect
                                options={timezones}
                                value={form.data.timezone_id}
                                onChange={(val) => form.setData('timezone_id', val)}
                                onBlur={() => form.validate('timezone_id')}
                                placeholder="Select timezone"
                            />
                            <InputError message={form.errors.timezone_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes (optional)</Label>
                            <Input
                                id="notes"
                                value={form.data.notes}
                                onChange={(e) => form.setData('notes', e.target.value)}
                                onBlur={() => form.validate('notes')}
                                placeholder="Any additional context"
                            />
                            <InputError message={form.errors.notes} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center gap-1.5">
                                <Label>Status</Label>
                                <InfoTooltip text="Inactive clients will not receive any SMS notifications, regardless of project status." />
                            </div>
                            <SearchableSelect
                                value={form.data.is_active}
                                options={statusOptions}
                                onChange={(val) => {
                                    form.setData('is_active', val);
                                    form.validate('is_active');
                                }}
                                allValue=""
                                allLabel="Select status…"
                            />
                            <InputError message={form.errors.is_active} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center gap-1.5">
                                <Label>Projects (optional)</Label>
                                <InfoTooltip text="Only webhook events from assigned projects with Active status are included in notifications." />
                            </div>
                            <ProjectMultiSelect
                                options={availableProjects}
                                value={form.data.project_ids}
                                onChange={(vals) => form.setData('project_ids', vals)}
                                onBlur={() => form.validate('project_ids')}
                                placeholder="Search and assign projects…"
                            />
                            <InputError message={form.errors.project_ids} />
                        </div>

                        <div className="flex gap-3">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving…' : 'Save Changes'}
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link href={index()}>Cancel</Link>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
