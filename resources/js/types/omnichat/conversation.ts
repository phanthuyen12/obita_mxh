import type { OmnichatChannel } from './channel';
import type { OmnichatContact } from './contact';

export interface OmnichatConversation {
    id: string;
    workspace_id: string;
    channel_id: string;
    contact_id: string;
    external_id: string;
    status: 'open' | 'pending' | 'resolved' | 'spam';
    priority: 'low' | 'normal' | 'high' | 'urgent';
    assigned_user_id: string | null;
    subject: string | null;
    last_message_preview: string | null;
    last_message_at: string | null;
    unread_count: number;
    contact: OmnichatContact;
    channel: OmnichatChannel;
    assigned_user: {
        id: string;
        name: string;
        avatar_url: string | null;
    } | null;
    labels: Array<{
        id: string;
        name: string;
        color: string;
    }>;
    created_at: string;
    updated_at: string;
}

export interface ConversationListItem {
    id: string;
    contact: {
        display_name: string;
        avatar_url: string | null;
        phone: string | null;
    };
    channel: {
        provider: string;
    };
    last_message_preview: string | null;
    last_message_at: string | null;
    unread_count: number;
    status: 'open' | 'pending' | 'resolved' | 'spam';
    assigned_user: {
        name: string;
        avatar_url: string | null;
    } | null;
    labels: Array<{
        id: string;
        name: string;
        color: string;
    }>;
}

export interface ConversationFilters {
    search: string;
    status: string | null;
    channel: string | null;
    assignee: string | null;
    label: string | null;
}
