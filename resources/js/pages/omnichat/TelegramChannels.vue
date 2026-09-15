<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    IconAlertCircle,
    IconBrandTelegram,
    IconCheck,
    IconChevronDown,
    IconChevronUp,
    IconCopy,
    IconExternalLink,
    IconInfoCircle,
    IconPlus,
    IconRefresh,
    IconRobot,
    IconShieldCheck,
    IconTrash,
    IconWebhook,
} from '@tabler/icons-vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';

interface Channel {
    id: string;
    name: string;
    username?: string;
    avatar_url?: string | null;
    status: string;
    webhook_url?: string;
    settings: {
        bot_id?: number;
        bot_username?: string;
        ai_care?: {
            enabled?: boolean;
            provider?: string;
            dify_api_key?: string;
            dify_base_url?: string;
            persona?: string;
            knowledge_base?: string;
            reply_delay_seconds?: number;
            off_hours_message?: string;
            handover_message?: string;
        };
    };
    created_at: string;
}

const props = defineProps<{ channels: Channel[] }>();

const showConnectForm = ref(false);
const expandedSettingsId = ref<string | null>(null);
const syncingWebhookId = ref<string | null>(null);
const inspectingWebhookId = ref<string | null>(null);

const webhookInfoModal = ref<{
    open: boolean;
    channelName: string;
    targetUrl: string;
    info: any;
}>({
    open: false,
    channelName: '',
    targetUrl: '',
    info: null,
});

const connectForm = useForm({
    token: '',
});

const handleConnect = () => {
    connectForm.post('/omnichat/telegram', {
        preserveScroll: true,
        onSuccess: () => {
            showConnectForm.value = false;
            connectForm.reset();
            toast.success('Đã kết nối Telegram Bot và đăng ký Webhook thành công!');
        },
        onError: (errors) => {
            toast.error(errors.token || 'Không thể kết nối Telegram Bot.');
        },
    });
};

const toggleSettings = (channelId: string) => {
    expandedSettingsId.value =
        expandedSettingsId.value === channelId ? null : channelId;
};

const disconnectChannel = (channel: Channel) => {
    if (
        !confirm(
            `Bạn có chắc chắn muốn ngắt kết nối Telegram Bot ${channel.name}? Webhook sẽ bị xóa khỏi Telegram.`,
        )
    ) {
        return;
    }

    router.delete(`/omnichat/telegram/${channel.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã ngắt kết nối bot và hủy Webhook thành công.');
        },
    });
};

const updateAiCare = (channel: Channel, settingsForm: any) => {
    router.put(
        `/omnichat/telegram/${channel.id}`,
        {
            name: channel.name,
            ai_care: settingsForm,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã lưu cấu hình AI Care thành công!');
            },
        },
    );
};

const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.success('Đã sao chép Webhook URL vào bộ nhớ tạm!');
};

const handleSyncWebhook = (channel: Channel) => {
    syncingWebhookId.value = channel.id;
    router.post(
        `/omnichat/telegram/${channel.id}/sync-webhook`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã đồng bộ Webhook thành công với Telegram!');
            },
            onError: (errors) => {
                toast.error(errors.webhook || 'Không thể đồng bộ Webhook.');
            },
            onFinish: () => {
                syncingWebhookId.value = null;
            },
        },
    );
};

const handleCheckWebhook = async (channel: Channel) => {
    inspectingWebhookId.value = channel.id;
    try {
        const res = await fetch(`/omnichat/telegram/${channel.id}/webhook-info`);
        if (!res.ok) {
            throw new Error('Không thể kiểm tra thông tin Webhook từ máy chủ.');
        }
        const data = await res.json();
        webhookInfoModal.value = {
            open: true,
            channelName: channel.name,
            targetUrl: data.webhook_url,
            info: data.info?.result || {},
        };
    } catch (e: any) {
        toast.error(e.message || 'Lỗi kiểm tra Webhook');
    } finally {
        inspectingWebhookId.value = null;
    }
};
</script>

<template>
    <Head title="Kênh Telegram Bot - OmniChat" />
    <AppLayout>
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
            <!-- Header section -->
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div class="grid gap-1">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-9 items-center justify-center rounded-lg bg-[#26A5E4]/10 text-[#26A5E4]"
                        >
                            <IconBrandTelegram class="size-5" />
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight">
                            Telegram Bot OmniChat
                        </h1>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Kết nối Bot Telegram riêng với OmniChat để trò chuyện 2
                        chiều với khách và tự động hóa với AI Chatbot 24/7.
                    </p>
                </div>
                <Button
                    class="bg-[#26A5E4] text-white hover:bg-[#208bc2]"
                    @click="showConnectForm = !showConnectForm"
                >
                    <IconPlus class="mr-1 size-4" />
                    {{ showConnectForm ? 'Đóng form' : 'Kết nối Bot mới' }}
                </Button>
            </div>

            <!-- Webhook Information Banner -->
            <div
                class="flex items-start gap-3 rounded-xl border border-[#26A5E4]/30 bg-[#26A5E4]/5 p-4 text-sm"
            >
                <IconInfoCircle
                    class="mt-0.5 size-5 shrink-0 text-[#26A5E4]"
                />
                <div class="grid gap-1">
                    <span class="font-semibold text-foreground">
                        Cơ chế Webhook tự động của Telegram:
                    </span>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        Khi bạn kết nối Telegram Bot, hệ thống sẽ <strong>tự động đăng ký Webhook</strong> trực tiếp với máy chủ Telegram. Telegram yêu cầu URL Webhook phải có giao thức <strong>HTTPS hợp lệ</strong>. Nếu bạn đang chạy ứng dụng tại máy cá nhân hoặc local test (Herd/Valet), vui lòng cấu hình <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs font-semibold text-foreground">WEBHOOK_URL=https://your-tunnel.ngrok-free.app</code> trong file <code class="rounded bg-muted px-1 py-0.5 font-mono text-xs">.env</code> rồi bấm nút <strong>Đồng bộ Webhook</strong>.
                    </p>
                </div>
            </div>

            <!-- Connect Bot Card Form -->
            <Card v-if="showConnectForm" class="border-[#26A5E4]/30 shadow-md">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <IconBrandTelegram class="size-5 text-[#26A5E4]" />
                        Kết Nối Telegram Bot Mới
                    </CardTitle>
                    <CardDescription>
                        Lấy HTTP API Token từ BotFather trên Telegram và dán vào
                        bên dưới. Webhook sẽ được tự động cài đặt.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-6">
                    <!-- Step-by-step instructions -->
                    <div
                        class="grid gap-3 rounded-lg border bg-muted/40 p-4 text-sm"
                    >
                        <div class="flex items-start gap-2">
                            <span
                                class="flex size-5 shrink-0 items-center justify-center rounded-full bg-[#26A5E4] text-xs font-bold text-white"
                                >1</span
                            >
                            <div>
                                Mở Telegram, tìm kiếm
                                <a
                                    href="https://t.me/BotFather"
                                    target="_blank"
                                    class="font-medium text-[#26A5E4] hover:underline"
                                    >@BotFather</a
                                >
                                và bấm <strong>Start</strong>.
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <span
                                class="flex size-5 shrink-0 items-center justify-center rounded-full bg-[#26A5E4] text-xs font-bold text-white"
                                >2</span
                            >
                            <div>
                                Gửi lệnh <code>/newbot</code>, nhập tên hiển thị
                                và username kết thúc bằng <code>bot</code> (ví
                                dụ: <code>my_shop_bot</code>).
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <span
                                class="flex size-5 shrink-0 items-center justify-center rounded-full bg-[#26A5E4] text-xs font-bold text-white"
                                >3</span
                            >
                            <div>
                                Copy mã <strong>HTTP API Token</strong> (dạng
                                <code>123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ</code
                                >) dán vào ô bên dưới.
                            </div>
                        </div>
                    </div>

                    <form class="grid gap-4" @submit.prevent="handleConnect">
                        <div class="grid gap-2">
                            <Label for="bot-token" class="font-medium"
                                >Bot Token từ BotFather</Label
                            >
                            <Input
                                id="bot-token"
                                v-model="connectForm.token"
                                placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ"
                                required
                                class="font-mono text-sm"
                            />
                            <p
                                v-if="connectForm.errors.token"
                                class="text-xs text-destructive"
                            >
                                {{ connectForm.errors.token }}
                            </p>
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="showConnectForm = false"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                :disabled="connectForm.processing"
                                class="bg-[#26A5E4] text-white hover:bg-[#208bc2]"
                            >
                                {{
                                    connectForm.processing
                                        ? 'Đang kết nối & đăng ký Webhook...'
                                        : 'Xác nhận & Kích hoạt Webhook'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Channels List -->
            <div v-if="channels.length" class="grid gap-4">
                <Card
                    v-for="channel in channels"
                    :key="channel.id"
                    class="overflow-hidden border transition-all"
                >
                    <CardHeader class="pb-3">
                        <div
                            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="relative flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full border bg-muted"
                                >
                                    <img
                                        v-if="channel.avatar_url"
                                        :src="channel.avatar_url"
                                        :alt="channel.name"
                                        class="size-full object-cover"
                                    />
                                    <IconBrandTelegram
                                        v-else
                                        class="size-6 text-[#26A5E4]"
                                    />
                                </div>
                                <div class="grid gap-0.5">
                                    <div class="flex items-center gap-2">
                                        <CardTitle class="text-base font-bold">
                                            {{ channel.name }}
                                        </CardTitle>
                                        <Badge
                                            v-if="
                                                channel.status === 'connected'
                                            "
                                            class="bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20"
                                        >
                                            <span
                                                class="mr-1.5 size-1.5 rounded-full bg-emerald-500"
                                            ></span>
                                            Đang hoạt động
                                        </Badge>
                                        <Badge
                                            v-else
                                            variant="outline"
                                            class="text-muted-foreground"
                                        >
                                            Đã ngắt kết nối
                                        </Badge>
                                    </div>
                                    <div
                                        class="flex items-center gap-2 text-xs text-muted-foreground"
                                    >
                                        <span v-if="channel.username"
                                            >@{{ channel.username }}</span
                                        >
                                        <span>•</span>
                                        <a
                                            v-if="channel.username"
                                            :href="`https://t.me/${channel.username}`"
                                            target="_blank"
                                            class="inline-flex items-center gap-0.5 text-[#26A5E4] hover:underline"
                                        >
                                            Mở bot trên Telegram
                                            <IconExternalLink class="size-3" />
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="gap-1.5"
                                    @click="toggleSettings(channel.id)"
                                >
                                    <IconRobot class="size-4 text-primary" />
                                    Cấu hình AI Chatbot
                                    <component
                                        :is="
                                            expandedSettingsId === channel.id
                                                ? IconChevronUp
                                                : IconChevronDown
                                        "
                                        class="size-3.5"
                                    />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    title="Ngắt kết nối"
                                    @click="disconnectChannel(channel)"
                                >
                                    <IconTrash class="size-4" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>

                    <!-- Webhook Section Always Visible -->
                    <CardContent class="pt-0 pb-4">
                        <div
                            class="flex flex-col gap-2 rounded-lg border bg-muted/40 p-3"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div
                                    class="flex items-center gap-1.5 text-xs font-semibold text-foreground"
                                >
                                    <IconWebhook class="size-4 text-[#26A5E4]" />
                                    <span>Telegram Webhook Endpoint:</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-7 text-xs gap-1"
                                        :disabled="syncingWebhookId === channel.id"
                                        @click="handleSyncWebhook(channel)"
                                    >
                                        <IconRefresh
                                            :class="[
                                                'size-3.5',
                                                syncingWebhookId === channel.id &&
                                                    'animate-spin',
                                            ]"
                                        />
                                        Đồng bộ Webhook
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-7 text-xs gap-1"
                                        :disabled="inspectingWebhookId === channel.id"
                                        @click="handleCheckWebhook(channel)"
                                    >
                                        <IconShieldCheck
                                            class="size-3.5 text-emerald-600"
                                        />
                                        Kiểm tra trạng thái
                                    </Button>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Input
                                    :value="channel.webhook_url"
                                    readonly
                                    class="h-8 bg-background font-mono text-xs"
                                />
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    class="h-8 shrink-0 px-2.5 text-xs"
                                    @click="
                                        copyToClipboard(
                                            channel.webhook_url || '',
                                        )
                                    "
                                >
                                    <IconCopy class="mr-1 size-3.5" />
                                    Sao chép
                                </Button>
                            </div>
                        </div>
                    </CardContent>

                    <!-- Expanded Settings / AI Care Configuration -->
                    <CardContent
                        v-if="expandedSettingsId === channel.id"
                        class="border-t bg-muted/20 pt-4"
                    >
                        <form
                            class="grid gap-4"
                            @submit.prevent="
                                updateAiCare(
                                    channel,
                                    channel.settings.ai_care || {},
                                )
                            "
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold">
                                        Trợ Lý AI Care Tự Động Phản Hồi
                                    </h4>
                                    <p class="text-xs text-muted-foreground">
                                        Kích hoạt AI trả lời 24/7 theo tri thức
                                        sản phẩm, dịch vụ của doanh nghiệp.
                                    </p>
                                </div>
                                <label
                                    class="relative inline-flex cursor-pointer items-center"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="
                                            channel.settings.ai_care?.enabled
                                        "
                                        class="peer sr-only"
                                        @change="
                                            channel.settings.ai_care =
                                                channel.settings.ai_care || {};
                                            channel.settings.ai_care.enabled = (
                                                $event.target as HTMLInputElement
                                            ).checked;
                                        "
                                    />
                                    <div
                                        class="peer h-6 w-11 rounded-full bg-muted-foreground/30 after:absolute after:top-[2px] after:start-[2px] after:size-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-primary peer-checked:after:translate-x-full"
                                    ></div>
                                </label>
                            </div>

                            <div class="grid gap-4 pt-2 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="dify-key" class="text-xs"
                                        >Dify API Key</Label
                                    >
                                    <Input
                                        id="dify-key"
                                        v-model="
                                            channel.settings.ai_care!
                                                .dify_api_key
                                        "
                                        type="password"
                                        placeholder="app-xxxxxxxxxxxxxxxx"
                                        class="text-xs"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="dify-url" class="text-xs"
                                        >Dify Base URL</Label
                                    >
                                    <Input
                                        id="dify-url"
                                        v-model="
                                            channel.settings.ai_care!
                                                .dify_base_url
                                        "
                                        placeholder="https://api.dify.ai/v1"
                                        class="text-xs"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="persona" class="text-xs"
                                    >Vai trò / Persona của Bot</Label
                                >
                                <Textarea
                                    id="persona"
                                    v-model="
                                        channel.settings.ai_care!.persona
                                    "
                                    rows="2"
                                    placeholder="Bạn là trợ lý tư vấn khách hàng chuyên nghiệp của cửa hàng..."
                                    class="text-xs"
                                />
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="handover-msg" class="text-xs"
                                        >Câu phản hồi khi chuyển giao nhân viên
                                        (/human)</Label
                                    >
                                    <Input
                                        id="handover-msg"
                                        v-model="
                                            channel.settings.ai_care!
                                                .handover_message
                                        "
                                        placeholder="Dạ em đã chuyển thông tin đến nhân viên tư vấn..."
                                        class="text-xs"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="delay" class="text-xs"
                                        >Độ trễ phản hồi giả lập gõ phím
                                        (giây)</Label
                                    >
                                    <Input
                                        id="delay"
                                        v-model.number="
                                            channel.settings.ai_care!
                                                .reply_delay_seconds
                                        "
                                        type="number"
                                        min="0"
                                        max="10"
                                        class="text-xs"
                                    />
                                </div>
                            </div>

                            <div class="flex justify-end pt-2">
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="bg-primary"
                                >
                                    Lưu Cấu Hình
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty state -->
            <div
                v-else-if="!showConnectForm"
                class="flex flex-col items-center justify-center rounded-xl border border-dashed p-12 text-center"
            >
                <div
                    class="flex size-14 items-center justify-center rounded-full bg-[#26A5E4]/10 text-[#26A5E4]"
                >
                    <IconBrandTelegram class="size-7" />
                </div>
                <h3 class="mt-4 text-lg font-semibold">
                    Chưa có Telegram Bot nào được kết nối
                </h3>
                <p class="mt-1.5 max-w-md text-sm text-muted-foreground">
                    Kết nối Bot Telegram để đưa toàn bộ khách hàng và tin nhắn
                    vào OmniChat Inbox với Profile CRM riêng biệt.
                </p>
                <Button
                    class="mt-5 bg-[#26A5E4] text-white hover:bg-[#208bc2]"
                    @click="showConnectForm = true"
                >
                    <IconPlus class="mr-1.5 size-4" />
                    Kết nối Bot ngay
                </Button>
            </div>
        </div>

        <!-- Webhook Status Modal Dialog -->
        <Dialog
            :open="webhookInfoModal.open"
            @update:open="webhookInfoModal.open = $event"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base">
                        <IconShieldCheck class="size-5 text-emerald-600" />
                        Trạng Thái Webhook Telegram
                    </DialogTitle>
                    <DialogDescription>
                        Chi tiết phản hồi trực tiếp từ Telegram Bot API cho bot:
                        <strong class="text-foreground">{{ webhookInfoModal.channelName }}</strong>
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 py-2 text-sm">
                    <div class="grid gap-1 rounded-lg border bg-muted/40 p-3">
                        <span class="text-xs font-semibold text-muted-foreground"
                            >URL Webhook Đã Đăng Ký Trên Telegram:</span
                        >
                        <span
                            v-if="webhookInfoModal.info?.url"
                            class="break-all font-mono text-xs text-foreground"
                        >
                            {{ webhookInfoModal.info.url }}
                        </span>
                        <span
                            v-else
                            class="text-xs font-medium text-amber-600"
                        >
                            Chưa đăng ký Webhook nào trên máy chủ Telegram.
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg border p-2.5">
                            <span class="text-muted-foreground">Trạng thái URL:</span>
                            <div class="mt-1 font-semibold">
                                <span
                                    v-if="
                                        webhookInfoModal.info?.url ===
                                        webhookInfoModal.targetUrl
                                    "
                                    class="text-emerald-600 inline-flex items-center gap-1"
                                >
                                    <IconCheck class="size-3.5" />
                                    Chính xác & Khớp
                                </span>
                                <span
                                    v-else
                                    class="text-amber-600 inline-flex items-center gap-1"
                                >
                                    <IconAlertCircle class="size-3.5" />
                                    Cần đồng bộ lại
                                </span>
                            </div>
                        </div>

                        <div class="rounded-lg border p-2.5">
                            <span class="text-muted-foreground">Tin nhắn chờ gửi (Pending):</span>
                            <div class="mt-1 font-semibold text-foreground">
                                {{ webhookInfoModal.info?.pending_update_count ?? 0 }} tin nhắn
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="webhookInfoModal.info?.last_error_message"
                        class="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive"
                    >
                        <div class="font-semibold flex items-center gap-1 mb-1">
                            <IconAlertCircle class="size-3.5" />
                            Lỗi gần nhất Telegram báo về:
                        </div>
                        <div>{{ webhookInfoModal.info.last_error_message }}</div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="webhookInfoModal.open = false"
                    >
                        Đóng
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
