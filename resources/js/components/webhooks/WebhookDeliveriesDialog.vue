<script setup lang="ts">
import {
    IconAlertCircle,
    IconCheck,
    IconClock,
    IconCopy,
    IconHistory,
    IconRefresh,
} from '@tabler/icons-vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface DeliveryItem {
    id: string;
    event: string;
    payload: any;
    response_status: number | null;
    response_body: string | null;
    duration_ms: number | null;
    status: string;
    attempts: number;
    error_message: string | null;
    created_at: string;
}

const props = defineProps<{
    open: boolean;
    webhookId: string | null;
    webhookName: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const deliveries = ref<DeliveryItem[]>([]);
const loading = ref(false);
const resendingId = ref<string | null>(null);
const selectedDelivery = ref<DeliveryItem | null>(null);

const fetchDeliveries = async () => {
    if (!props.webhookId) return;
    loading.value = true;
    try {
        const res = await fetch(`/settings/workspace/webhooks/${props.webhookId}/deliveries`);
        if (!res.ok) throw new Error('Không thể tải lịch sử gửi.');
        const data = await res.json();
        deliveries.value = data.deliveries || [];
        if (deliveries.value.length > 0 && !selectedDelivery.value) {
            selectedDelivery.value = deliveries.value[0];
        }
    } catch (e: any) {
        toast.error(e.message || 'Lỗi tải lịch sử');
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.open,
    (val) => {
        if (val && props.webhookId) {
            selectedDelivery.value = null;
            fetchDeliveries();
        }
    },
);

const copyText = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.success('Đã sao chép vào bộ nhớ tạm!');
};

const handleResend = async (delivery: DeliveryItem) => {
    resendingId.value = delivery.id;
    try {
        const res = await fetch(`/settings/workspace/webhooks/deliveries/${delivery.id}/resend`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.split('XSRF-TOKEN=')[1]?.split(';')[0] || ''),
            },
        });
        if (!res.ok) throw new Error('Không thể gửi lại webhook.');
        toast.success('Đã xếp hàng gửi lại webhook thành công!');
        fetchDeliveries();
    } catch (e: any) {
        toast.error(e.message || 'Lỗi gửi lại webhook');
    } finally {
        resendingId.value = null;
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-3xl max-h-[85vh] flex flex-col p-6">
            <DialogHeader class="shrink-0">
                <div class="flex items-center justify-between">
                    <DialogTitle class="flex items-center gap-2 text-lg">
                        <IconHistory class="size-5 text-primary" />
                        Lịch Sử Gửi (Deliveries) - {{ webhookName }}
                    </DialogTitle>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 gap-1 text-xs"
                        :disabled="loading"
                        @click="fetchDeliveries"
                    >
                        <IconRefresh :class="['size-3.5', loading && 'animate-spin']" />
                        Làm mới
                    </Button>
                </div>
                <DialogDescription>
                    Xem chi tiết mã HTTP phản hồi, thời gian trễ và nội dung payload gửi đi.
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading && deliveries.length === 0" class="py-12 text-center text-sm text-muted-foreground">
                <IconRefresh class="mx-auto size-6 animate-spin mb-2 text-primary" />
                Đang tải nhật ký gửi...
            </div>

            <div v-else-if="deliveries.length === 0" class="py-12 text-center text-sm text-muted-foreground">
                Chưa có lịch sử gửi webhook nào cho endpoint này. Hãy bấm "Gửi Test" để thử nghiệm!
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-5 gap-4 overflow-hidden pt-2 flex-1 min-h-0">
                <!-- Delivery List (Left) -->
                <div class="md:col-span-2 border rounded-lg overflow-y-auto max-h-[50vh] divide-y">
                    <div
                        v-for="del in deliveries"
                        :key="del.id"
                        :class="[
                            'p-2.5 cursor-pointer text-xs transition-colors hover:bg-muted/50',
                            selectedDelivery?.id === del.id && 'bg-muted font-medium',
                        ]"
                        @click="selectedDelivery = del"
                    >
                        <div class="flex items-center justify-between gap-1 mb-1">
                            <span class="font-mono font-semibold">{{ del.event }}</span>
                            <Badge
                                :class="[
                                    del.status === 'success'
                                        ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'
                                        : 'bg-destructive/10 text-destructive border-destructive/20',
                                    'text-[10px] px-1.5 py-0 h-4',
                                ]"
                            >
                                {{ del.response_status ? `${del.response_status}` : del.status }}
                            </Badge>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                            <span>{{ del.duration_ms ? `${del.duration_ms}ms` : '...' }}</span>
                            <span>{{ new Date(del.created_at).toLocaleTimeString() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Delivery Details (Right) -->
                <div v-if="selectedDelivery" class="md:col-span-3 border rounded-lg p-3 overflow-y-auto max-h-[50vh] flex flex-col gap-3 text-xs">
                    <div class="flex items-center justify-between border-b pb-2">
                        <div class="flex items-center gap-2">
                            <Badge
                                :class="[
                                    selectedDelivery.status === 'success'
                                        ? 'bg-emerald-500/10 text-emerald-600'
                                        : 'bg-destructive/10 text-destructive',
                                    'font-bold',
                                ]"
                            >
                                HTTP {{ selectedDelivery.response_status || 'N/A' }}
                            </Badge>
                            <span class="font-semibold">{{ selectedDelivery.event }}</span>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 text-xs gap-1"
                            :disabled="resendingId === selectedDelivery.id"
                            @click="handleResend(selectedDelivery)"
                        >
                            <IconRefresh :class="['size-3', resendingId === selectedDelivery.id && 'animate-spin']" />
                            Gửi lại (Resend)
                        </Button>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-[11px] text-muted-foreground">
                        <div>Độ trễ phản hồi: <strong class="text-foreground">{{ selectedDelivery.duration_ms ?? '0' }} ms</strong></div>
                        <div>Số lần thử: <strong class="text-foreground">{{ selectedDelivery.attempts }}</strong></div>
                        <div>Thời gian: <strong class="text-foreground">{{ new Date(selectedDelivery.created_at).toLocaleString() }}</strong></div>
                    </div>

                    <div v-if="selectedDelivery.error_message" class="rounded bg-destructive/10 p-2 text-destructive">
                        <strong>Lỗi:</strong> {{ selectedDelivery.error_message }}
                    </div>

                    <!-- Payload -->
                    <div>
                        <div class="flex items-center justify-between mb-1 font-semibold text-muted-foreground">
                            <span>Payload Gửi Đi:</span>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                                @click="copyText(JSON.stringify(selectedDelivery.payload, null, 2))"
                            >
                                <IconCopy class="size-3" />
                                Copy JSON
                            </button>
                        </div>
                        <pre class="bg-muted p-2 rounded text-[11px] overflow-x-auto max-h-36 font-mono">{{ JSON.stringify(selectedDelivery.payload, null, 2) }}</pre>
                    </div>

                    <!-- Response Body -->
                    <div v-if="selectedDelivery.response_body">
                        <div class="flex items-center justify-between mb-1 font-semibold text-muted-foreground">
                            <span>Phản Hồi Nhận Về:</span>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                                @click="copyText(selectedDelivery.response_body)"
                            >
                                <IconCopy class="size-3" />
                                Copy
                            </button>
                        </div>
                        <pre class="bg-muted p-2 rounded text-[11px] overflow-x-auto max-h-36 font-mono">{{ selectedDelivery.response_body }}</pre>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
