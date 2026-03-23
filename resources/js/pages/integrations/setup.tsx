import { Head } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { useState } from 'react';
import IntegrationController from '@/actions/App/Http/Controllers/Integrations/IntegrationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index } from '@/routes/integrations';
import type { BreadcrumbItem, Integration, IntegrationDefinition } from '@/types';

interface Props {
    definition: IntegrationDefinition;
    integration: Pick<Integration, 'id' | 'credentials'> | null;
    webhookUrl: string | null;
}

export default function IntegrationSetup({ definition, integration, webhookUrl }: Props) {
    const isEdit = integration !== null;
    const [copied, setCopied] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Integrations', href: index() },
        { title: isEdit ? `Edit ${definition.label}` : `Connect ${definition.label}`, href: '#' },
    ];

    const formProps = isEdit
        ? IntegrationController.update.form(integration.id)
        : IntegrationController.store.form(definition.service);

    function handleCopy() {
        if (webhookUrl) {
            navigator.clipboard.writeText(webhookUrl);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEdit ? `Edit ${definition.label}` : `Connect ${definition.label}`} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <Heading
                    title={isEdit ? `Edit ${definition.label}` : `Connect ${definition.label}`}
                    description={definition.description}
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Credentials form */}
                    <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 className="mb-5 text-sm font-semibold">Credentials</h2>

                        <Form {...formProps} className="space-y-5">
                            {({ errors, processing }) => (
                                <>
                                    {definition.credentialFields?.map((field) => (
                                        <div key={field.name} className="grid gap-2">
                                            <Label htmlFor={field.name}>{field.label}</Label>
                                            <Input
                                                id={field.name}
                                                name={field.name}
                                                type={field.type === 'password' ? 'password' : field.type}
                                                placeholder={field.placeholder}
                                                required={field.required}
                                                defaultValue={
                                                    isEdit
                                                        ? (integration.credentials?.[field.name] ?? '')
                                                        : undefined
                                                }
                                            />
                                            {field.hint && (
                                                <p className="text-xs text-muted-foreground">{field.hint}</p>
                                            )}
                                            <InputError message={errors[field.name as keyof typeof errors]} />
                                        </div>
                                    ))}

                                    {webhookUrl && (
                                        <div className="grid gap-2">
                                            <Label>Webhook URL</Label>
                                            <div className="flex gap-2">
                                                <Input readOnly value={webhookUrl} className="font-mono text-xs" />
                                                <Button type="button" variant="outline" size="icon" onClick={handleCopy}>
                                                    <Copy className="h-4 w-4" />
                                                </Button>
                                            </div>
                                            {copied && (
                                                <p className="text-xs text-green-600 dark:text-green-400">Copied!</p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                Add this URL in {definition.label} to receive webhook events.
                                            </p>
                                        </div>
                                    )}

                                    <div className="flex gap-3 pt-1">
                                        <Button type="submit" disabled={processing}>
                                            {processing ? 'Saving…' : isEdit ? 'Update' : 'Connect'}
                                        </Button>
                                        <Button variant="ghost" asChild>
                                            <a href={index()}>Cancel</a>
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>

                    {/* Setup instructions */}
                    {definition.setupSteps && definition.setupSteps.length > 0 && (
                        <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                            <h2 className="mb-5 text-sm font-semibold">Setup instructions</h2>
                            <ol className="space-y-3">
                                {definition.setupSteps.map((step, i) => (
                                    <li key={i} className="flex gap-3 text-sm">
                                        <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-sidebar-accent text-xs font-medium text-sidebar-accent-foreground">
                                            {i + 1}
                                        </span>
                                        <span className="text-muted-foreground">{step}</span>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
