<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    IconAlertCircle,
    IconBrandFacebook,
    IconBrandTelegram,
    IconCheck,
    IconClock,
    IconCopy,
    IconExternalLink,
    IconFilter,
    IconInfoCircle,
    IconRefresh,
    IconRotate,
    IconWebhook,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';

interface EndpointInfo {
    provider: string;
    name: string;
    icon: string;
    endpoint: string;
    method: string;
    verify_token?: string;
    secret_header?: string;
    description: string;
    guide: string;
    connected_count: number;
}

interface WebhookEventItem {
    id: string;
    provider: string;
    event_type: string;
    external_event_id: string | null;
    status: string;
    attempts: number;
    received_at: string;
    processed_at: string | null;
    error_message: string | null;
    payload_preview: any;
}

interface Counts {
    total: number;
    processed: number;
    failed: number;
    pending: number;
}

const props = defineProps<{
    endpoints: EndpointInfo[];
    events: WebhookEventItem[];
    counts: Counts;
    baseUrl: string;
}>();

const selectedStatusFilter = ref<string>('all');
const selectedProviderFilter = ref<string>('all');
const retryingId = ref<string | null>(null);
const payloadModal = ref<{
    open: boolean;
    event: WebhookEventItem | null;
}>({
    open: false,
    event: null,
});

const filteredEvents = computed(() => {
    return props.events.filter((ev) => {
        if (selectedStatusFilter.value !== 'all' && ev.status !== selectedStatusFilter.value) {
            return false;
        }
        if (selectedProviderFilter.value !== 'all' && ev.provider !== selectedProviderFilter.value) {
            return false;
        }
        return true;
    });
});

const copyText = (text: string, msg: string = 'Đã sao chép vào bộ nhớ tạm!') => {
    navigator.clipboard.writeText(text);
    toast.success(msg);
};

const handleRetry = (event: WebhookEventItem) => {
    retryingId.value = event.id;
    router.post(
        `/omnichat/webhooks/${event.id}/retry`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Đã đưa sự kiện #${event.id.slice(0, 8)} vào hàng đợi để xử lý lại!`);
            },
            onError: () => {
                toast.error('Không thể kích hoạt xử lý lại sự kiện.');
            },
            onFinish: () => {
                retryingId.value = null;
            },
        },
    );
};

const viewPayload = (event: WebhookEventItem) => {
    payloadModal.value = {
        open: true,
        event,
    };
};
</script>

<template>
    <Head title="Trung Tâm Webhooks Inbound - OmniChat" />
    <AppLayout>
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
            <!-- Header section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="grid gap-1">
                    <div class="flex items-center gap-2">
                        <div class="flex size-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600">
                            <IconWebhook class="size-5" />
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight">
                            Trung Tâm Webhooks Inbound (OmniChat)
                        </h1>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Theo dõi tập trung Callback URL, Secret Token và giám sát luồng sự kiện webhook đến từ Telegram, Facebook, Zalo, Shopee, Lazada.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button as-child variant="outline" size="sm">
                        <a href="/settings/workspace/webhooks">
                            <IconRotate class="size-4 mr-1 text-primary" />
                            Cấu hình Outbound Webhooks
                        </a>
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        @click="router.reload({ only: ['events', 'counts'] })"
                    >
                        <IconRefresh class="size-4 mr-1" />
                        Làm mới Logs
                    </Button>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <Card class="p-4 shadow-sm">
                    <div class="text-xs font-medium text-muted-foreground">Tổng Sự Kiện Đã Nhận</div>
                    <div class="mt-1 text-2xl font-bold tracking-tight text-foreground">{{ counts.total }}</div>
                </Card>
                <Card class="p-4 shadow-sm">
                    <div class="text-xs font-medium text-emerald-600">Đã Xử Lý Thành Công</div>
                    <div class="mt-1 text-2xl font-bold tracking-tight text-emerald-600">{{ counts.processed }}</div>
                </Card>
                <Card class="p-4 shadow-sm">
                    <div class="text-xs font-medium text-amber-600">Đang Trong Hàng Đợi (Queue)</div>
                    <div class="mt-1 text-2xl font-bold tracking-tight text-amber-600">{{ counts.pending }}</div>
                </Card>
                <Card class="p-4 shadow-sm">
                    <div class="text-xs font-medium text-destructive">Gặp Lỗi Xử Lý</div>
                    <div class="mt-1 text-2xl font-bold tracking-tight text-destructive">{{ counts.failed }}</div>
                </Card>
            </div>

            <!-- Localhost Tunnel Alert -->
            <div class="flex items-start gap-3 rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-sm text-amber-900 dark:text-amber-200">
                <IconAlertCircle class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div class="grid gap-1">
                    <span class="font-semibold">Lưu ý về Webhook Domain / SSL:</span>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        Các nền tảng (Telegram, Meta, Zalo, Shopee) bắt buộc Callback URL phải sử dụng giao thức <strong>HTTPS hợp lệ</strong>.
                        Nếu bạn đang phát triển tại máy local, hãy cập nhật biến <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs font-semibold text-foreground">WEBHOOK_URL=https://your-tunnel.ngrok-free.app</code> trong file <code class="rounded bg-muted px-1 py-0.5 font-mono text-xs">.env</code>.
                    </p>
                </div>
            </div>

            <!-- Endpoints Cards Grid -->
            <div class="grid gap-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold tracking-tight">Callback Endpoints Theo Từng Kênh</h2>
                    <span class="text-xs text-muted-foreground">Base URL: <code class="font-mono">{{ baseUrl }}</code></span>
                </div>

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="ep in endpoints"
                        :key="ep.provider"
                        class="flex flex-col justify-between overflow-hidden border shadow-sm transition-all"
                    >
                        <CardHeader class="pb-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-9 items-center justify-center rounded-lg bg-muted text-foreground font-bold text-sm">
                                        <IconBrandTelegram v-if="ep.provider === 'telegram'" class="size-5 text-[#26A5E4]" />
                                        <IconBrandFacebook v-else-if="ep.provider === 'facebook'" class="size-5 text-[#1877F2]" />
                                        <span v-else class="text-xs uppercase font-bold">{{ ep.provider.slice(0, 3) }}</span>
                                    </div>
                                    <div>
                                        <CardTitle class="text-sm font-bold">{{ ep.name }}</CardTitle>
                                        <div class="text-[11px] text-muted-foreground">
                                            {{ ep.connected_count > 0 ? `${ep.connected_count} kênh đang kết nối` : 'Chưa có kênh nào' }}
                                        </div>
                                    </div>
                                </div>
                                <Badge variant="outline" class="font-mono text-[10px] uppercase">
                                    {{ ep.method }}
                                </Badge>
                            </div>
                            <CardDescription class="text-xs mt-2 line-clamp-2">
                                {{ ep.description }}
                            </CardDescription>
                        </CardHeader>

                        <CardContent class="grid gap-3 pt-0 text-xs">
                            <div class="grid gap-1">
                                <span class="font-semibold text-muted-foreground text-[11px]">Webhook Endpoint:</span>
                                <div class="flex items-center gap-1.5">
                                    <Input
                                        :value="ep.endpoint"
                                        readonly
                                        class="h-7 text-[11px] font-mono bg-muted/40"
                                    />
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-7 px-2 shrink-0"
                                        @click="copyText(ep.endpoint)"
                                    >
                                        <IconCopy class="size-3" />
                                    </Button>
                                </div>
                            </div>

                            <div v-if="ep.verify_token" class="grid gap-1">
                                <span class="font-semibold text-muted-foreground text-[11px]">Verify Token:</span>
                                <div class="flex items-center gap-1.5">
                                    <Input
                                        :value="ep.verify_token"
                                        readonly
                                        class="h-7 text-[11px] font-mono bg-muted/40"
                                    />
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-7 px-2 shrink-0"
                                        @click="copyText(ep.verify_token)"
                                    >
                                        <IconCopy class="size-3" />
                                    </Button>
                                </div>
                            </div>

                            <div class="rounded bg-muted/30 p-2 text-[11px] text-muted-foreground">
                                💡 {{ ep.guide }}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Inbound Events Table Logs -->
            <div class="grid gap-4 pt-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold tracking-tight">Nhật Ký Sự Kiện Webhook Đến (Logs)</h2>
                        <p class="text-xs text-muted-foreground">50 sự kiện gần nhất nhận được từ các nền tảng chat.</p>
                    </div>

                    <!-- Filter Controls -->
                    <div class="flex flex-wrap items-center gap-2">
                        <select
                            v-model="selectedProviderFilter"
                            class="h-8 rounded-md border border-input bg-background px-2.5 text-xs text-foreground focus:ring-1 focus:ring-primary"
                        >
                            <option value="all">Tất cả nền tảng</option>
                            <option value="telegram">Telegram</option>
                            <option value="facebook">Facebook / Messenger</option>
                            <option value="zalo">Zalo OA</option>
                            <option value="shopee">Shopee</option>
                            <option value="lazada">Lazada</option>
                        </select>

                        <select
                            v-model="selectedStatusFilter"
                            class="h-8 rounded-md border border-input bg-background px-2.5 text-xs text-foreground focus:ring-1 focus:ring-primary"
                        >
                            <option value="all">Tất cả trạng thái</option>
                            <option value="processed">Đã xử lý (Thành công)</option>
                            <option value="pending">Chờ xử lý (Pending)</option>
                            <option value="failed">Thất bại (Failed)</option>
                        </select>
                    </div>
                </div>

                <div class="rounded-xl border bg-card overflow-hidden shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[160px]">Thời Gian</TableHead>
                                <TableHead class="w-[120px]">Nền Tảng</TableHead>
                                <TableHead>Loại Sự Kiện</TableHead>
                                <TableHead class="w-[110px] text-center">Trạng Thái</TableHead>
                                <TableHead class="w-[90px] text-center">Lần Thử</TableHead>
                                <TableHead class="w-[140px] text-right">Hành Động</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="ev in filteredEvents" :key="ev.id">
                                <TableCell class="text-xs text-muted-foreground">
                                    {{ new Date(ev.received_at).toLocaleString() }}
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline" class="capitalize font-mono text-[11px]">
                                        {{ ev.provider }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-xs font-mono">
                                    {{ ev.event_type }}
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge
                                        :class="[
                                            ev.status === 'processed'
                                                ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'
                                                : ev.status === 'pending'
                                                ? 'bg-amber-500/10 text-amber-600 border-amber-500/20'
                                                : 'bg-destructive/10 text-destructive border-destructive/20',
                                            'text-[10px] capitalize',
                                        ]"
                                    >
                                        {{ ev.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-center text-xs">
                                    {{ ev.attempts }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 px-2 text-xs"
                                            @click="viewPayload(ev)"
                                        >
                                            Chi tiết
                                        </Button>
                                        <Button
                                            v-if="ev.status !== 'processed'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-xs gap-1 text-primary"
                                            :disabled="retryingId === ev.id"
                                            @click="handleRetry(ev)"
                                        >
                                            <IconRotate :class="['size-3', retryingId === ev.id && 'animate-spin']" />
                                            Thử lại
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>

                            <TableRow v-if="filteredEvents.length === 0">
                                <TableCell colspan="6" class="py-8 text-center text-xs text-muted-foreground">
                                    Không có sự kiện webhook nào khớp với bộ lọc.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <!-- Payload Detail Modal -->
        <Dialog :open="payloadModal.open" @update:open="payloadModal.open = $event">
            <DialogContent class="sm:max-w-xl max-h-[80vh] flex flex-col p-6">
                <DialogHeader class="shrink-0">
                    <DialogTitle class="flex items-center gap-2 text-base">
                        <IconWebhook class="size-4 text-primary" />
                        Chi Tiết Sự Kiện Webhook #{{ payloadModal.event?.id.slice(0, 8) }}
                    </DialogTitle>
                    <DialogDescription>
                        Nền tảng: <strong class="capitalize text-foreground">{{ payloadModal.event?.provider }}</strong> · Loại sự kiện: <strong class="font-mono text-foreground">{{ payloadModal.event?.event_type }}</strong>
                    </DialogDescription>
                </DialogHeader>

                <div v-if="payloadModal.event" class="flex-1 overflow-y-auto space-y-3 pt-2 text-xs">
                    <div v-if="payloadModal.event.error_message" class="rounded bg-destructive/10 p-2.5 text-destructive border border-destructive/20">
                        <strong>Lỗi xử lý:</strong> {{ payloadModal.event.error_message }}
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1 font-semibold text-muted-foreground">
                            <span>Dữ Liệu Payload (Preview):</span>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                                @click="copyText(JSON.stringify(payloadModal.event.payload_preview, null, 2))"
                            >
                                <IconCopy class="size-3" />
                                Copy JSON
                            </button>
                        </div>
                        <pre class="bg-muted p-3 rounded-lg text-[11px] overflow-x-auto max-h-56 font-mono">{{ JSON.stringify(payloadModal.event.payload_preview, null, 2) }}</pre>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
