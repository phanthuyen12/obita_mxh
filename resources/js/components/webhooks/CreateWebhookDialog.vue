<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { IconAlertCircle, IconPlus, IconWebhook } from '@tabler/icons-vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface EventOption {
    id: string;
    label: string;
    group: string;
    description: string;
}

interface WebhookItem {
    id: string;
    name: string;
    url: string;
    events: string[];
    is_active: boolean;
}

const props = defineProps<{
    open: boolean;
    availableEvents: EventOption[];
    editingWebhook?: WebhookItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}>();

const form = useForm({
    name: '',
    url: '',
    events: [] as string[],
    is_active: true,
});

watch(
    () => props.editingWebhook,
    (wh) => {
        if (wh) {
            form.name = wh.name;
            form.url = wh.url;
            form.events = [...wh.events];
            form.is_active = wh.is_active;
        } else {
            form.reset();
            form.events = ['message.inbound', 'lead.detected'];
            form.is_active = true;
        }
    },
    { immediate: true },
);

const toggleEvent = (eventId: string) => {
    const idx = form.events.indexOf(eventId);
    if (idx > -1) {
        form.events.splice(idx, 1);
    } else {
        form.events.push(eventId);
    }
};

const selectAllEvents = () => {
    form.events = props.availableEvents.map((e) => e.id);
};

const clearAllEvents = () => {
    form.events = [];
};

const submit = () => {
    if (props.editingWebhook) {
        form.put(`/settings/workspace/webhooks/${props.editingWebhook.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã cập nhật Webhook thành công!');
                emit('update:open', false);
                emit('saved');
            },
            onError: (err) => {
                toast.error(Object.values(err)[0] as string || 'Có lỗi xảy ra.');
            },
        });
    } else {
        form.post('/settings/workspace/webhooks', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã tạo Webhook mới thành công!');
                emit('update:open', false);
                emit('saved');
                form.reset();
            },
            onError: (err) => {
                toast.error(Object.values(err)[0] as string || 'Có lỗi xảy ra.');
            },
        });
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-lg">
                    <IconWebhook class="size-5 text-primary" />
                    {{ editingWebhook ? 'Chỉnh Sửa Webhook' : 'Thêm Webhook Mới' }}
                </DialogTitle>
                <DialogDescription>
                    Hệ thống sẽ tự động gửi thông báo HTTP POST (JSON) kèm chữ ký số HMAC-SHA256 mỗi khi có sự kiện diễn ra.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5 py-3" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="webhook-name" class="font-medium">
                        Tên gợi nhớ Webhook <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="webhook-name"
                        v-model="form.name"
                        placeholder="Ví dụ: Đồng bộ Lead về CRM HubSpot, N8N Flow..."
                        required
                    />
                    <p v-if="form.errors.name" class="text-xs text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="webhook-url" class="font-medium">
                        Endpoint URL <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="webhook-url"
                        v-model="form.url"
                        placeholder="https://your-server.com/api/webhooks hoặc webhook n8n/make"
                        type="url"
                        required
                        class="font-mono text-xs"
                    />
                    <p v-if="form.errors.url" class="text-xs text-destructive">
                        {{ form.errors.url }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label class="font-medium">
                            Các sự kiện lắng nghe <span class="text-destructive">*</span>
                        </Label>
                        <div class="flex gap-2 text-xs">
                            <button
                                type="button"
                                class="text-primary hover:underline font-medium"
                                @click="selectAllEvents"
                            >
                                Chọn tất cả
                            </button>
                            <span>·</span>
                            <button
                                type="button"
                                class="text-muted-foreground hover:underline"
                                @click="clearAllEvents"
                            >
                                Bỏ chọn
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-2 rounded-lg border p-3 bg-muted/20">
                        <div
                            v-for="ev in availableEvents"
                            :key="ev.id"
                            class="flex items-start gap-3 rounded-md p-2 hover:bg-muted/50 cursor-pointer transition-colors"
                            @click="toggleEvent(ev.id)"
                        >
                            <input
                                type="checkbox"
                                :checked="form.events.includes(ev.id)"
                                class="mt-0.5 rounded border-muted-foreground/40 text-primary focus:ring-primary"
                                @click.stop
                                @change="toggleEvent(ev.id)"
                            />
                            <div class="grid gap-0.5 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-foreground">{{ ev.label }}</span>
                                    <span class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground">
                                        {{ ev.id }}
                                    </span>
                                </div>
                                <span class="text-muted-foreground">{{ ev.description }}</span>
                            </div>
                        </div>
                    </div>
                    <p v-if="form.errors.events" class="text-xs text-destructive">
                        {{ form.errors.events }}
                    </p>
                </div>

                <div class="flex items-center justify-between rounded-lg border p-3">
                    <div>
                        <div class="text-sm font-semibold">Kích hoạt Webhook</div>
                        <div class="text-xs text-muted-foreground">
                            Bật để nhận dữ liệu ngay lập tức khi phát sinh sự kiện.
                        </div>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input
                            type="checkbox"
                            v-model="form.is_active"
                            class="peer sr-only"
                        />
                        <div
                            class="peer h-6 w-11 rounded-full bg-muted-foreground/30 after:absolute after:top-[2px] after:start-[2px] after:size-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-primary peer-checked:after:translate-x-full"
                        ></div>
                    </label>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                    >
                        Hủy
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || form.events.length === 0"
                        class="bg-primary"
                    >
                        {{ form.processing ? 'Đang lưu...' : (editingWebhook ? 'Lưu Thay Đổi' : 'Tạo Webhook') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
