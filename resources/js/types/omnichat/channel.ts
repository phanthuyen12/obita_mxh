export interface OmnichatChannel {
    id: string;
    workspace_id: string;
    provider: string;
    external_id: string;
    name: string;
    avatar_url: string | null;
    status: 'connected' | 'disconnected' | 'error' | 'pending';
    capabilities: {
        send_text: boolean;
        send_image: boolean;
        send_video: boolean;
        send_document: boolean;
        reactions: boolean;
        read_receipts: boolean;
        typing_indicator: boolean;
    };
    last_synced_at: string | null;
}

export interface ChannelSummary {
    id: string;
    provider: string;
    name: string;
}
