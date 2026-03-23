import { Head } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import ClientController from '@/actions/App/Http/Controllers/Clients/ClientController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { edit, index } from '@/routes/clients';
import type { BreadcrumbItem, Client, Timezone } from '@/types';

const breadcrumbs = (client: Client): BreadcrumbItem[] => [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Clients', href: index() },
    { title: `Edit ${client.name}`, href: edit(client.id) },
];

export default function EditClient({ client, timezones }: { client: Client; timezones: Timezone[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs(client)}>
            <Head title={`Edit ${client.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    title={`Edit ${client.name}`}
                    description="Update client details and notification preferences."
                />

                <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <Form
                        {...ClientController.update.form(client.id)}
                        options={{ preserveScroll: true }}
                        className="space-y-5"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={client.name}
                                        placeholder="Company or person name"
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone number</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        defaultValue={client.phone}
                                        placeholder="+1 555 000 0000"
                                        required
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="timezone_id">Timezone</Label>
                                    <Select name="timezone_id" defaultValue={String(client.timezone_id)} required>
                                        <SelectTrigger id="timezone_id">
                                            <SelectValue placeholder="Select timezone" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {timezones.map((tz) => (
                                                <SelectItem key={tz.id} value={String(tz.id)}>
                                                    {tz.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.timezone_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notes (optional)</Label>
                                    <Input
                                        id="notes"
                                        name="notes"
                                        defaultValue={client.notes ?? ''}
                                        placeholder="Any additional context"
                                    />
                                    <InputError message={errors.notes} />
                                </div>

                                <div className="flex gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save Changes'}
                                    </Button>
                                    <Button variant="ghost" asChild>
                                        <a href={index()}>Cancel</a>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </AppLayout>
    );
}
