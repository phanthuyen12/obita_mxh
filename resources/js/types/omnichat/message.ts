export interface OmnichatMessage {
    id: string;
    workspace_id: string;
    conversation_id: string;
    sender_contact_id: string | null;
    sender_user_id: string | null;
    external_id: string | null;
    client_id: string | null;
    direction: 'inbound' | 'outbound' | 'internal';
    type:
        | 'text'
        | 'image'
        | 'video'
        | 'audio'
        | 'document'
        | 'location'
        | 'system'
        | 'unsupported';
    body: string | null;
    status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
    sender: {
        id: string;
        name: string;
        avatar_url: string | null;
    } | null;
    attachments: Array<{
        id: string;
        type: 'image' | 'document' | 'file' | 'video' | 'audio' | string;
        url: string;
        original_name?: string;
        file_name?: string;
        mime_type?: string;
        size?: number;
    }>;
    metadata?: Record<string, any>;
    reply_to_message_id?: string | null;
    sent_at: string | null;
    delivered_at: string | null;
    read_at: string | null;
    failed_at: string | null;
    error_message: string | null;
    created_at: string;
}
