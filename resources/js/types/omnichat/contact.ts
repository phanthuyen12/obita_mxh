export interface OmnichatContact {
    id: string;
    workspace_id: string;
    display_name: string;
    first_name: string | null;
    last_name: string | null;
    avatar_url: string | null;
    email: string | null;
    phone: string | null;
    notes: string | null;
    locale: string | null;
    timezone: string | null;
    status: 'active' | 'blocked' | 'archived';
    is_lead: boolean;
    lead_stage: 'new' | 'qualified' | 'contacted' | 'converted' | 'lost';
    phone_detected_at: string | null;
    last_seen_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface OmnichatContactIdentity {
    id: string;
    contact_id: string;
    channel_id: string;
    provider: string;
    external_id: string;
    username: string | null;
    display_name: string | null;
    avatar_url: string | null;
}
