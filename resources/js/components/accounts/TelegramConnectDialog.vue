<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import {
    IconCheck,
    IconCopy,
    IconLoader2,
    IconMessage,
    IconSend,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useWorkspaceEcho } from '@/composables/echo/useWorkspaceEcho';
import dayjs from '@/dayjs';
import { copyToClipboard } from '@/lib/utils';
import { connect as connectTelegram } from '@/routes/app/social/telegram';

const open = defineModel<boolean>('open', { required: true });

type Mode = 'choose' | 'publish';
type Phase = 'loading' | 'ready' | 'connected' | 'expired' | 'error';

interface ConnectResponse {
    code: string;
    nonce: string;
    bot_username: string;
    expires_at: string;
}

const SUCCESS_CLOSE_DELAY_MS = 1200;

const mode = ref<Mode>('choose');
const phase = ref<Phase>('loading');
const code = ref('');
const nonce = ref('');
const botUsername = ref('');
const errorMessage = ref('');

const httpConnect = useHttp<Record<string, never>, ConnectResponse>({});

let expiryTimer: ReturnType<typeof setTimeout> | null = null;

const clearExpiry = () => {
    if (expiryTimer !== null) {
        clearTimeout(expiryTimer);
        expiryTimer = null;
    }
};

// The channel is linked server-side by the webhook; Reverb pushes the result here.
useWorkspaceEcho<{ nonce: string }>(
    '.telegram.channel.connected',
    (payload) => {
        if (phase.value !== 'ready' || payload.nonce !== nonce.value) {
            return;
        }

        phase.value = 'connected';
        clearExpiry();
        toast.success(trans('accounts.telegram.connected_toast'));
        setTimeout(() => {
            open.value = false;
            router.reload();
        }, SUCCESS_CLOSE_DELAY_MS);
    },
);

useWorkspaceEcho<{ nonce: string; reason: string }>(
    '.telegram.connect.failed',
    (payload) => {
        if (phase.value !== 'ready' || payload.nonce !== nonce.value) {
            return;
        }

        phase.value = 'error';
        clearExpiry();
        errorMessage.value =
            payload.reason === 'network_taken'
                ? trans('accounts.telegram.network_taken')
                : trans('accounts.telegram.error_generic');
    },
);

const startPublish = async () => {
    mode.value = 'publish';
    phase.value = 'loading';
    errorMessage.value = '';

    try {
        const response = await httpConnect.post(connectTelegram.url());
        code.value = response.code;
        nonce.value = response.nonce;
        botUsername.value = response.bot_username;
        phase.value = 'ready';

        clearExpiry();
        expiryTimer = setTimeout(
            () => {
                if (phase.value === 'ready') {
                    phase.value = 'expired';
                }
            },
            Math.max(0, dayjs(response.expires_at).diff(dayjs())),
        );
    } catch (error) {
        phase.value = 'error';
        errorMessage.value =
            (error as { response?: { data?: { message?: string } } })?.response
                ?.data?.message ?? trans('accounts.telegram.error_generic');
    }
};

const goToOmnichat = () => {
    open.value = false;
    router.visit('/omnichat/telegram');
};

const copyCommand = () => {
    copyToClipboard(
        `/connect ${code.value}`,
        trans('accounts.telegram.copied_toast'),
    );
};

watch(open, (isOpen) => {
    if (isOpen) {
        mode.value = 'choose';
    } else {
        clearExpiry();
    }
});

onUnmounted(clearExpiry);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <div class="flex items-start gap-3">
                    <img
                        src="/images/accounts/telegram.png"
                        alt="Telegram"
                        class="size-10 rounded-lg"
                    />
                    <div class="text-left">
                        <DialogTitle>{{
                            $t('accounts.telegram.title')
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                mode === 'choose'
                                    ? 'Chọn cách bạn muốn sử dụng Telegram'
                                    : $t('accounts.telegram.description')
                            }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <!-- Mode: Choose between Omnichat and Publish -->
            <div v-if="mode === 'choose'" class="space-y-3 py-2">
                <button
                    type="button"
                    class="group flex w-full items-start gap-4 rounded-xl border-2 border-transparent bg-muted/50 p-4 text-left transition-all hover:border-primary hover:bg-primary/5"
                    @click="goToOmnichat"
                >
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-600 transition-colors group-hover:bg-violet-200"
                    >
                        <IconMessage class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-foreground">
                            Omnichat — Chat với khách hàng
                        </p>
                        <p
                            class="mt-0.5 text-xs leading-relaxed text-muted-foreground"
                        >
                            Khách nhắn tin vào Telegram Bot → Sale trả lời từ
                            OmniChat Inbox. Giống chat Page Facebook.
                        </p>
                        <span
                            class="mt-2 inline-flex items-center rounded-full border border-violet-300 bg-violet-50 px-2 py-0.5 text-[10px] font-medium text-violet-700"
                        >
                            Omnichat
                        </span>
                    </div>
                </button>

                <button
                    type="button"
                    class="group flex w-full items-start gap-4 rounded-xl border-2 border-transparent bg-muted/50 p-4 text-left transition-all hover:border-primary hover:bg-primary/5"
                    @click="startPublish"
                >
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 transition-colors group-hover:bg-emerald-200"
                    >
                        <IconSend class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-foreground">
                            Đăng bài — Post lên Channel/Group
                        </p>
                        <p
                            class="mt-0.5 text-xs leading-relaxed text-muted-foreground"
                        >
                            Kết nối channel/group Telegram để lên lịch và đăng
                            bài tự động từ hệ thống.
                        </p>
                        <span
                            class="mt-2 inline-flex items-center rounded-full border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700"
                        >
                            Đăng bài
                        </span>
                    </div>
                </button>
            </div>

            <!-- Mode: Publish — loading / error / expired / connected / ready -->
            <template v-else>
                <div
                    v-if="phase === 'loading'"
                    class="flex items-center justify-center py-10"
                >
                    <IconLoader2
                        class="size-6 animate-spin text-muted-foreground"
                    />
                </div>

                <div v-else-if="phase === 'error'" class="space-y-4 py-2">
                    <p class="text-sm text-destructive">{{ errorMessage }}</p>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            class="flex-1"
                            @click="mode = 'choose'"
                        >
                            Quay lại
                        </Button>
                        <Button class="flex-1" @click="startPublish">{{
                            $t('accounts.telegram.retry')
                        }}</Button>
                    </div>
                </div>

                <div v-else-if="phase === 'expired'" class="space-y-4 py-2">
                    <p class="text-sm text-muted-foreground">
                        {{ $t('accounts.telegram.expired') }}
                    </p>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            class="flex-1"
                            @click="mode = 'choose'"
                        >
                            Quay lại
                        </Button>
                        <Button class="flex-1" @click="startPublish">{{
                            $t('accounts.telegram.new_code')
                        }}</Button>
                    </div>
                </div>

                <div
                    v-else-if="phase === 'connected'"
                    class="flex flex-col items-center gap-3 py-8 text-center"
                >
                    <span
                        class="inline-flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"
                    >
                        <IconCheck class="size-6" stroke-width="3" />
                    </span>
                    <p class="text-sm font-medium">
                        {{ $t('accounts.telegram.connected') }}
                    </p>
                </div>

                <div v-else class="min-w-0 space-y-5 py-2">
                    <ol class="space-y-4 text-sm">
                        <li class="flex gap-3">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full border-2 border-foreground text-xs font-semibold"
                                >1</span
                            >
                            <span>{{
                                trans('accounts.telegram.step_admin', {
                                    bot: `@${botUsername}`,
                                })
                            }}</span>
                        </li>
                        <li class="flex gap-3">
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full border-2 border-foreground text-xs font-semibold"
                                >2</span
                            >
                            <div class="min-w-0 flex-1 space-y-2">
                                <span>{{
                                    $t('accounts.telegram.step_command')
                                }}</span>
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <button
                                                type="button"
                                                class="group flex w-full cursor-pointer items-center justify-between gap-2 rounded-lg border bg-muted px-3 py-2 text-left font-mono text-sm transition-colors hover:bg-muted/70"
                                                @click="copyCommand"
                                            >
                                                <span
                                                    class="min-w-0 truncate"
                                                    >/connect
                                                    {{ code }}</span
                                                >
                                                <IconCopy
                                                    class="size-4 shrink-0 text-muted-foreground group-hover:text-foreground"
                                                />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>
                                                {{
                                                    $t(
                                                        'accounts.telegram.copy_tooltip',
                                                    )
                                                }}
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </li>
                    </ol>

                    <div
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <IconLoader2 class="size-4 animate-spin" />
                        {{ $t('accounts.telegram.waiting') }}
                    </div>

                    <button
                        type="button"
                        class="text-xs text-muted-foreground underline hover:text-foreground"
                        @click="mode = 'choose'"
                    >
                        ← Quay lại chọn chế độ
                    </button>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
