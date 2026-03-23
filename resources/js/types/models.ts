export interface Timezone {
    id: number;
    name: string;
    label: string;
    offset: string;
    offset_minutes: number;
}

export interface Client {
    id: number;
    tenant_id: string;
    name: string;
    phone: string;
    timezone_id: number;
    notes: string | null;
    timezone?: Timezone;
    projects?: Project[];
    created_at: string;
    updated_at: string;
}

export interface Project {
    id: number;
    tenant_id: string;
    client_id: number | null;
    activecollab_id: number | null;
    name: string;
    description: string | null;
    status: 'active' | 'completed' | 'on_hold';
    url: string | null;
    client?: Client;
    webhook_events?: WebhookEvent[];
    created_at: string;
    updated_at: string;
}

export interface WebhookEvent {
    id: number;
    tenant_id: string | null;
    project_id: number | null;
    event_type: string;
    raw_payload: Record<string, unknown>;
    parsed_data: Record<string, unknown> | null;
    activecollab_url: string | null;
    short_url: string | null;
    received_at: string;
    project?: Project;
    created_at: string;
    updated_at: string;
}

export interface NotificationLog {
    id: number;
    tenant_id: string;
    client_id: number;
    channel: string;
    message: string;
    status: 'sent' | 'failed';
    error_message: string | null;
    sent_at: string;
    client?: Client;
    created_at: string;
    updated_at: string;
}

export interface IntegrationField {
    name: string;
    label: string;
    type: 'text' | 'password' | 'url' | 'tel' | 'email';
    required: boolean;
    placeholder?: string;
    hint?: string;
}

export interface IntegrationDefinition {
    service: string;
    label: string;
    description: string;
    logoIcon: string;
    hasWebhook: boolean;
    isConnected: boolean;
    integrationId: number | null;
    credentialFields?: IntegrationField[];
    setupSteps?: string[];
}

export interface Integration {
    id: number;
    tenant_id: string;
    service: string;
    meta: Record<string, unknown> | null;
    is_active: boolean;
    credentials?: Record<string, string>;
    created_at: string;
    updated_at: string;
}

export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}
