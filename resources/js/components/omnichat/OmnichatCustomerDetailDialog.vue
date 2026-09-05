<script setup lang="ts">
import {
    IconMail,
    IconPhone,
    IconRobot,
    IconStar,
    IconTag,
} from '@tabler/icons-vue';

import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface Customer {
    id: string;
    name: string;
    avatar_url: string | null;
    email: string | null;
    phone: string | null;
    platform: string;
    page_id: string;
    page_name: string;
    status: string;
    lead_status: string;
    ai_status: string;
    assigned_user: {
        id: string;
        name: string;
        avatar_url: string | null;
    } | null;
    last_message: string;
    last_message_at: string;
    last_message_display: string;
    total_messages: number;
    csat_score: number;
    tags: string[];
    notes: string;
}

defineProps<{
    open: boolean;
    customer: Customer | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const getLeadBadge = (status: string) => {
    switch (status) {
        case 'hot':
            return {
                label: '🔥 Tiềm năng cao (Hot)',
                class: 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300',
            };
        case 'warm':
            return {
                label: '⚡ Quan tâm (Warm)',
                class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
            };
        case 'converted':
            return {
                label: '✅ Đã chốt đơn',
                class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
            };
        default:
            return {
                label: '❄️ Lạnh / Hỏi thông tin',
                class: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
            };
    }
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'open':
            return { label: 'Đang mở', class: 'bg-blue-500 text-white' };
        case 'pending':
            return { label: 'Chờ phản hồi', class: 'bg-amber-500 text-white' };
        case 'resolved':
            return {
                label: 'Đã giải quyết',
                class: 'bg-emerald-500 text-white',
            };
        default:
            return { label: 'Đã đóng', class: 'bg-gray-500 text-white' };
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-2xl">
            <DialogHeader v-if="customer">
                <div class="flex items-center gap-3">
                    <Avatar
                        :src="customer.avatar_url"
                        :name="customer.name"
                        class="size-12 rounded-full border-2 border-foreground"
                    />
                    <div class="space-y-0.5 text-left">
                        <DialogTitle class="text-lg font-bold text-foreground">
                            {{ customer.name }}
                        </DialogTitle>
                        <DialogDescription
                            class="text-xs text-muted-foreground"
                        >
                            Kênh: {{ customer.page_name }} ({{
                                customer.platform
                            }}) • ID: {{ customer.id }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div v-if="customer" class="space-y-4 py-2 text-sm">
                <!-- Status bar -->
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        :class="[
                            'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            getStatusBadge(customer.status).class,
                        ]"
                    >
                        {{ getStatusBadge(customer.status).label }}
                    </span>
                    <span
                        :class="[
                            'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            getLeadBadge(customer.lead_status).class,
                        ]"
                    >
                        {{ getLeadBadge(customer.lead_status).label }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="[
                            customer.ai_status === 'active'
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                : customer.ai_status === 'handed_off'
                                  ? 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300'
                                  : 'bg-muted text-muted-foreground',
                        ]"
                    >
                        <IconRobot class="size-3.5" />
                        {{
                            customer.ai_status === 'active'
                                ? 'AI đang chăm sóc'
                                : customer.ai_status === 'handed_off'
                                  ? 'AI đã chuyển giao nhân viên'
                                  : customer.ai_status === 'human_only'
                                    ? 'Nhân viên trực tiếp'
                                    : 'Chưa bật AI'
                        }}
                    </span>

                    <div
                        class="ml-auto flex items-center gap-1 text-xs font-bold text-amber-500"
                    >
                        <IconStar class="size-3.5 fill-amber-500" />
                        <span>CSAT: {{ customer.csat_score }}/5</span>
                    </div>
                </div>

                <!-- Contact & Assignment Grid -->
                <div
                    class="grid grid-cols-2 gap-3 rounded-xl border border-foreground/10 bg-muted/30 p-3"
                >
                    <div class="space-y-1">
                        <div
                            class="text-xs font-semibold text-muted-foreground"
                        >
                            Thông tin liên hệ
                        </div>
                        <div
                            class="flex items-center gap-1.5 text-xs font-medium text-foreground"
                        >
                            <IconPhone class="size-3.5 text-muted-foreground" />
                            {{ customer.phone || 'Chưa cập nhật SĐT' }}
                        </div>
                        <div
                            class="flex items-center gap-1.5 text-xs font-medium text-foreground"
                        >
                            <IconMail class="size-3.5 text-muted-foreground" />
                            {{ customer.email || 'Chưa cập nhật Email' }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div
                            class="text-xs font-semibold text-muted-foreground"
                        >
                            Nhân viên phụ trách
                        </div>
                        <div class="flex items-center gap-2 pt-0.5">
                            <Avatar
                                :src="customer.assigned_user?.avatar_url"
                                :name="customer.assigned_user?.name || 'T'"
                                class="size-6 rounded-full border border-foreground/20"
                            />
                            <span class="text-xs font-semibold text-foreground">
                                {{
                                    customer.assigned_user?.name ||
                                    'Chưa gán nhân viên'
                                }}
                            </span>
                        </div>
                        <div class="text-[11px] text-muted-foreground">
                            Tổng trao đổi: {{ customer.total_messages }} tin
                            nhắn
                        </div>
                    </div>
                </div>

                <!-- Recent message snippet & Chat preview -->
                <div class="space-y-2">
                    <div
                        class="flex items-center justify-between text-xs font-semibold"
                    >
                        <span class="text-foreground"
                            >Lịch sử tương tác gần nhất</span
                        >
                        <span class="text-muted-foreground">{{
                            customer.last_message_display
                        }}</span>
                    </div>

                    <div
                        class="space-y-2 rounded-xl border border-foreground/10 bg-background p-3"
                    >
                        <!-- Customer message -->
                        <div class="flex gap-2">
                            <Avatar
                                :src="customer.avatar_url"
                                :name="customer.name"
                                class="size-7 shrink-0 rounded-full"
                            />
                            <div
                                class="rounded-2xl rounded-tl-none bg-muted px-3 py-2 text-xs text-foreground"
                            >
                                {{ customer.last_message }}
                            </div>
                        </div>

                        <!-- AI / Agent reply -->
                        <div class="flex justify-end gap-2">
                            <div
                                class="rounded-2xl rounded-tr-none bg-primary px-3 py-2 text-xs text-primary-foreground"
                            >
                                Dạ chào bạn {{ customer.name }}, cảm ơn bạn đã
                                quan tâm! Trợ lý AI của shop đã ghi nhận yêu cầu
                                và phản hồi thông tin chi tiết cho bạn ngay đây
                                ạ.
                            </div>
                            <div
                                class="flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white"
                            >
                                <IconRobot class="size-4" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tags & Notes -->
                <div class="space-y-2">
                    <div class="text-xs font-semibold text-foreground">
                        Tags & Ghi chú
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <Badge
                            v-for="tag in customer.tags"
                            :key="tag"
                            variant="outline"
                            class="gap-1 border-foreground/20 text-xs"
                        >
                            <IconTag class="size-3" />
                            {{ tag }}
                        </Badge>
                    </div>
                    <p
                        class="rounded-lg border border-foreground/10 bg-muted/40 p-2 text-xs text-muted-foreground"
                    >
                        {{ customer.notes }}
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">
                    Đóng
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
