/**
 * Mirrors the `color()` methods on the PHP enums in `app/Enums/EmailCampaign`,
 * mapping their colour names onto the Badge component's variants.
 */
export type BadgeVariant =
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'success'
    | 'warning'
    | 'info'
    | 'outline';

const CAMPAIGN_VARIANTS: Record<string, BadgeVariant> = {
    draft: 'secondary',
    scheduled: 'info',
    sending: 'warning',
    paused: 'warning',
    completed: 'success',
    failed: 'destructive',
    cancelled: 'secondary',
};

const RECIPIENT_VARIANTS: Record<string, BadgeVariant> = {
    queued: 'secondary',
    sent: 'info',
    delivered: 'success',
    opened: 'success',
    clicked: 'success',
    bounced: 'destructive',
    failed: 'destructive',
    skipped: 'warning',
};

const IMPORT_VARIANTS: Record<string, BadgeVariant> = {
    pending: 'secondary',
    importing: 'warning',
    completed: 'success',
    failed: 'destructive',
};

const CAMPAIGN_LABELS: Record<string, string> = {
    draft: 'Nháp',
    scheduled: 'Đã hẹn giờ',
    sending: 'Đang gửi',
    paused: 'Tạm dừng',
    completed: 'Hoàn tất',
    failed: 'Lỗi',
    cancelled: 'Đã huỷ',
};

const RECIPIENT_LABELS: Record<string, string> = {
    queued: 'Chờ gửi',
    sent: 'Đã gửi',
    delivered: 'Đã nhận',
    opened: 'Đã mở',
    clicked: 'Đã click',
    bounced: 'Bị trả lại',
    failed: 'Lỗi',
    skipped: 'Bỏ qua',
};

const IMPORT_LABELS: Record<string, string> = {
    pending: 'Chờ xử lý',
    importing: 'Đang nhập',
    completed: 'Hoàn tất',
    failed: 'Lỗi',
};

export const campaignVariant = (status: string): BadgeVariant =>
    CAMPAIGN_VARIANTS[status] ?? 'secondary';

export const campaignLabel = (status: string): string =>
    CAMPAIGN_LABELS[status] ?? status;

export const recipientVariant = (status: string): BadgeVariant =>
    RECIPIENT_VARIANTS[status] ?? 'secondary';

export const recipientLabel = (status: string): string =>
    RECIPIENT_LABELS[status] ?? status;

export const importVariant = (status: string): BadgeVariant =>
    IMPORT_VARIANTS[status] ?? 'secondary';

export const importLabel = (status: string): string =>
    IMPORT_LABELS[status] ?? status;
