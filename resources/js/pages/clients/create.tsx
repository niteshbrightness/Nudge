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
import { create, index } from '@/routes/clients';
import type { BreadcrumbItem, Timezone } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Clients', href: index() },
    { title: 'Add Client', href: create() },
];

export default function CreateClient({ timezones }: { timezones: Timezone[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add Client" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading title="Add Client" description="Create a new client to receive project notifications." />

                <div className="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <Form {...ClientController.store.form()} className="space-y-5">
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input id="name" name="name" placeholder="Company or person name" required />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone number</Label>
                                    <Input id="phone" name="phone" type="tel" placeholder="+1 555 000 0000" required />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="timezone_id">Timezone</Label>
                                    <Select name="timezone_id" required>
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
                                    <Input id="notes" name="notes" placeholder="Any additional context" />
                                    <InputError message={errors.notes} />
                                </div>

                                <div className="flex gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Add Client'}
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
