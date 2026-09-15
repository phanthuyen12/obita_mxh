<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    IconAlertCircle,
    IconCheck,
    IconCopy,
    IconDots,
    IconEye,
    IconEyeOff,
    IconHistory,
    IconInfoCircle,
    IconPlus,
    IconSend,
    IconTrash,
    IconWebhook,
} from '@tabler/icons-vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import PageHeader from '@/components/PageHeader.vue';
import SettingsTabsNav from '@/components/settings/SettingsTabsNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import CreateWebhookDialog from '@/components/webhooks/CreateWebhookDialog.vue';
import WebhookDeliveriesDialog from '@/components/webhooks/WebhookDeliveriesDialog.vue';
import { useWorkspaceSettingsTabs } from '@/composables/useWorkspaceSettingsTabs';
import AppLayout from '@/layouts/AppLayout.vue';

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
    secret: string;
    events: string[];
    is_active: boolean;
    total_deliveries: number;
    failed_deliveries: number;
    created_at: string;
}

const props = defineProps<{
    workspace: any;
    webhooks: WebhookItem[];
    availableEvents: EventOption[];
}>();

const tabs = useWorkspaceSettingsTabs();

const createDialogOpen = ref(false);
const editingWebhook = ref<WebhookItem | null>(null);
const deliveriesDialogOpen = ref(false);
const activeWebhookForDeliveries = ref<{ id: string; name: string } | null>(null);
const testingWebhookId = ref<string | null>(null);
const revealedSecrets = ref<Record<string, boolean>>({});

const confirmDeleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const copyText = (text: string, msg: string = 'Đã sao chép vào bộ nhớ tạm!') => {
    navigator.clipboard.writeText(text);
    toast.success(msg);
};

const toggleSecret = (id: string) => {
    revealedSecrets.value[id] = !revealedSecrets.value[id];
};

const openCreateDialog = () => {
    editingWebhook.value = null;
    createDialogOpen.value = true;
};

const openEditDialog = (wh: WebhookItem) => {
    editingWebhook.value = wh;
    createDialogOpen.value = true;
};

const openDeliveries = (wh: WebhookItem) => {
    activeWebhookForDeliveries.value = { id: wh.id, name: wh.name };
    deliveriesDialogOpen.value = true;
};

const toggleActive = (wh: WebhookItem) => {
    router.put(
        `/settings/workspace/webhooks/${wh.id}`,
        {
            is_active: !wh.is_active,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Đã ${wh.is_active ? 'tạm dừng' : 'kích hoạt'} Webhook thành công!`);
            },
        },
    );
};

const handleTestPing = async (wh: WebhookItem) => {
    testingWebhookId.value = wh.id;
    try {
        const res = await fetch(`/settings/workspace/webhooks/${wh.id}/test`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.split('XSRF-TOKEN=')[1]?.split(';')[0] || ''),
            },
        });
        const data = await res.json();
        if (res.ok && data.success) {
            toast.success(`✓ Gửi Test thành công! HTTP ${data.status} (${data.duration_ms}ms)`);
        } else {
            toast.error(`✗ Test thất bại: HTTP ${data.status || 0} - ${data.error || 'Server không phản hồi'}`);
        }
    } catch (e: any) {
        toast.error(`Lỗi test: ${e.message}`);
    } finally {
        testingWebhookId.value = null;
    }
};

const deleteWebhook = (wh: WebhookItem) => {
    confirmDeleteModal.value?.open({
        title: `Xóa Webhook "${wh.name}"?`,
        description: 'Hành động này sẽ xóa vĩnh viễn cấu hình Webhook và toàn bộ lịch sử gửi.',
        confirmButtonText: 'Xóa Webhook',
        onConfirm: () => {
            router.delete(`/settings/workspace/webhooks/${wh.id}`, {
                preserveScroll: true,
                onSuccess: () => toast.success('Đã xóa Webhook thành công.'),
            });
        },
    });
};
</script>

<template>
    <Head title="Quản Lý Outbound Webhooks - Cài Đặt Workspace" />
    <AppLayout>
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Cài Đặt Workspace"
                description="Quản lý thành viên, quyền hạn, thương hiệu và tích hợp API / Webhooks của Workspace."
            />

            <SettingsTabsNav :tabs="tabs" />

            <div class="flex flex-col gap-6">
                <!-- Header with Action -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <HeadingSmall
                            title="Outbound Webhooks (Bắn Dữ Liệu Tự Động)"
                            description="Tự động bắn dữ liệu thời gian thực ra N8N, Make, Zapier, CRM hoặc Server riêng của bạn khi có sự kiện mới."
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <Button as-child variant="outline" size="sm">
                            <a href="/omnichat/webhooks">
                                <IconWebhook class="size-4 mr-1 text-emerald-600" />
                                Xem Inbound Webhook Hub
                            </a>
                        </Button>
                        <Button size="sm" class="bg-primary" @click="openCreateDialog">
                            <IconPlus class="size-4 mr-1" />
                            Thêm Webhook Mới
                        </Button>
                    </div>
                </div>

                <!-- Webhooks Table -->
                <div v-if="webhooks.length > 0" class="rounded-xl border bg-card overflow-hidden shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[200px]">Tên Webhook</TableHead>
                                <TableHead>Endpoint URL & Secret</TableHead>
                                <TableHead class="w-[220px]">Sự Kiện Lắng Nghe</TableHead>
                                <TableHead class="w-[110px] text-center">Trạng Thái</TableHead>
                                <TableHead class="w-[120px] text-center">Lịch Sử Gửi</TableHead>
                                <TableHead class="w-[120px] text-right">Hành Động</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="wh in webhooks" :key="wh.id">
                                <TableCell class="font-medium">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-semibold text-foreground">{{ wh.name }}</span>
                                        <span class="text-[11px] text-muted-foreground">
                                            Tạo ngày: {{ new Date(wh.created_at).toLocaleDateString() }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-col gap-1.5 max-w-md">
                                        <div class="flex items-center gap-1.5 font-mono text-xs bg-muted/50 rounded px-2 py-1 border">
                                            <span class="truncate">{{ wh.url }}</span>
                                            <button
                                                type="button"
                                                class="text-muted-foreground hover:text-foreground shrink-0"
                                                title="Sao chép URL"
                                                @click="copyText(wh.url, 'Đã sao chép URL!')"
                                            >
                                                <IconCopy class="size-3.5" />
                                            </button>
                                        </div>
                                        <div class="flex items-center gap-2 text-[11px] text-muted-foreground">
                                            <span class="font-semibold">Secret Key:</span>
                                            <span class="font-mono">
                                                {{ revealedSecrets[wh.id] ? wh.secret : '••••••••••••••••••••••••' }}
                                            </span>
                                            <button
                                                type="button"
                                                class="hover:text-foreground"
                                                @click="toggleSecret(wh.id)"
                                            >
                                                <component :is="revealedSecrets[wh.id] ? IconEyeOff : IconEye" class="size-3" />
                                            </button>
                                            <button
                                                type="button"
                                                class="hover:text-foreground"
                                                title="Sao chép Secret"
                                                @click="copyText(wh.secret, 'Đã sao chép Secret Key!')"
                                            >
                                                <IconCopy class="size-3" />
                                            </button>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="ev in wh.events.slice(0, 3)"
                                            :key="ev"
                                            variant="secondary"
                                            class="text-[10px] px-1.5 py-0 font-mono"
                                        >
                                            {{ ev }}
                                        </Badge>
                                        <Badge
                                            v-if="wh.events.length > 3"
                                            variant="outline"
                                            class="text-[10px] px-1.5 py-0"
                                        >
                                            +{{ wh.events.length - 3 }} nữa
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge
                                        :class="[
                                            wh.is_active
                                                ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'
                                                : 'bg-muted text-muted-foreground',
                                            'cursor-pointer select-none text-[11px]',
                                        ]"
                                        @click="toggleActive(wh)"
                                    >
                                        <span
                                            :class="[
                                                'size-1.5 rounded-full mr-1.5 inline-block',
                                                wh.is_active ? 'bg-emerald-500' : 'bg-muted-foreground',
                                            ]"
                                        ></span>
                                        {{ wh.is_active ? 'Hoạt động' : 'Tạm dừng' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-center">
                                    <button
                                        type="button"
                                        class="inline-flex flex-col items-center hover:opacity-80 transition-opacity"
                                        @click="openDeliveries(wh)"
                                    >
                                        <span class="text-xs font-semibold text-foreground">
                                            {{ wh.total_deliveries }} lần
                                        </span>
                                        <span v-if="wh.failed_deliveries > 0" class="text-[10px] text-destructive font-medium">
                                            ({{ wh.failed_deliveries }} thất bại)
                                        </span>
                                        <span v-else class="text-[10px] text-emerald-600">
                                            (100% OK)
                                        </span>
                                    </button>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-8 px-2 text-xs gap-1"
                                            :disabled="testingWebhookId === wh.id"
                                            @click="handleTestPing(wh)"
                                        >
                                            <IconSend :class="['size-3', testingWebhookId === wh.id && 'animate-spin']" />
                                            Test
                                        </Button>

                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon" class="size-8">
                                                    <IconDots class="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-44">
                                                <DropdownMenuItem @click="openDeliveries(wh)">
                                                    <IconHistory class="size-4 mr-2" />
                                                    Xem lịch sử gửi
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="openEditDialog(wh)">
                                                    Chỉnh sửa cấu hình
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="toggleActive(wh)">
                                                    {{ wh.is_active ? 'Tạm dừng' : 'Kích hoạt' }}
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    class="text-destructive focus:text-destructive"
                                                    @click="deleteWebhook(wh)"
                                                >
                                                    <IconTrash class="size-4 mr-2" />
                                                    Xóa Webhook
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Empty State -->
                <EmptyState
                    v-else
                    title="Chưa cấu hình Outbound Webhook nào"
                    description="Thêm webhook để tự động đẩy dữ liệu tin nhắn, khách hàng, lead mới và bài đăng sang CRM, N8N, Zapier hoặc hệ thống nội bộ của bạn."
                    action-text="Thêm Webhook Đầu Tiên"
                    @action="openCreateDialog"
                />

                <!-- Developer Documentation Card -->
                <div class="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm">
                    <IconInfoCircle class="size-5 text-primary shrink-0 mt-0.5" />
                    <div class="grid gap-1.5 text-xs text-muted-foreground leading-relaxed">
                        <span class="font-semibold text-foreground text-sm">
                            Xác thực tính toàn vẹn với Chữ Ký Số HMAC-SHA256:
                        </span>
                        <p>
                            Mỗi HTTP POST từ King Hub sẽ gửi kèm header <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-foreground font-semibold">X-KingHub-Signature</code> và <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-foreground font-semibold">X-KingHub-Timestamp</code>.
                            Phía server nhận của bạn có thể xác minh bằng mã nguồn mẫu:
                        </p>
                        <pre class="rounded bg-muted p-2 font-mono text-[11px] overflow-x-auto text-foreground">$signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $webhookSecret);</pre>
                    </div>
                </div>
            </div>
        </div>

        <CreateWebhookDialog
            :open="createDialogOpen"
            :available-events="availableEvents"
            :editing-webhook="editingWebhook"
            @update:open="createDialogOpen = $event"
            @saved="router.reload()"
        />

        <WebhookDeliveriesDialog
            :open="deliveriesDialogOpen"
            :webhook-id="activeWebhookForDeliveries?.id || null"
            :webhook-name="activeWebhookForDeliveries?.name || ''"
            @update:open="deliveriesDialogOpen = $event"
        />

        <ConfirmDeleteModal ref="confirmDeleteModal" />
    </AppLayout>
</template>
