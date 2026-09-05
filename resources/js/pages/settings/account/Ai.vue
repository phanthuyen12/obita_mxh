<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    IconBolt,
    IconCheck,
    IconDeviceFloppy,
    IconKey,
    IconPlugConnected,
    IconRobot,
    IconSparkles,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageAiCareCard from '@/components/settings/PageAiCareCard.vue';
import SettingsTabsNav from '@/components/settings/SettingsTabsNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit as accountEdit } from '@/routes/app/account';
import {
    edit as aiSettingsEdit,
    update as aiSettingsUpdate,
} from '@/routes/app/ai-settings';
import { index as billingIndex } from '@/routes/app/billing';
import { index as usageIndex } from '@/routes/app/usage';

interface PageAiCare {
    enabled: boolean;
    provider?: string;
    bot_name?: string;
    dify_api_key?: string;
    dify_base_url?: string;
    reply_mode?: string;
    reply_delay_seconds: number;
    operating_hours: {
        mode: '24/7' | 'custom';
        days: number[];
        start_time: string;
        end_time: string;
        timezone: string;
    };
    off_hours_behavior: string;
    off_hours_message: string;
    auto_tag_leads: boolean;
    lead_keywords: string[];
}

interface PageItem {
    id: string;
    display_name: string;
    username: string;
    platform: string;
    avatar_url: string | null;
    ai_care: PageAiCare;
}

interface AiSettings {
    content_clone_ai_provider: string;
    ai_text_provider: string;
    ai_image_provider: string;
    dify_base_url: string;
    dify_connect_timeout: number;
    dify_timeout: number;
    dify_api_key_configured: boolean;
    dify_text_api_key_configured: boolean;
    dify_image_api_key_configured: boolean;
    dify_content_clone_api_key_configured: boolean;
    provider_keys: Record<string, boolean>;
    provider_models: Record<string, Record<string, string>>;
}

interface AiOptions {
    contentCloneProviders: string[];
    textProviders: string[];
    imageProviders: string[];
    secretProviders: string[];
    providerModelCapabilities: Record<string, string[]>;
}

const props = defineProps<{
    settings: AiSettings;
    options: AiOptions;
    pages?: PageItem[];
}>();

const currentSection = ref<'pages' | 'global'>('pages');

const tabs = computed(() => [
    {
        name: 'account',
        label: trans('settings.account.tabs.account'),
        href: accountEdit().url,
    },
    {
        name: 'ai',
        label: trans('settings.account.tabs.ai'),
        href: aiSettingsEdit().url,
    },
    {
        name: 'usage',
        label: trans('settings.account.tabs.usage'),
        href: usageIndex().url,
    },
    {
        name: 'billing',
        label: trans('settings.account.tabs.billing'),
        href: billingIndex().url,
    },
]);

const globalDifyUrl = ref(
    props.settings.dify_base_url || 'https://kingai.tnicorporation.com/v1',
);
const globalDifyKey = ref('');

const applyGlobalDifyToAllPages = () => {
    if (!globalDifyKey.value.trim()) {
        toast.error('Vui lòng nhập Dify API Key trước khi đồng bộ.');
        return;
    }

    if (
        !confirm(
            'Bạn có chắc chắn muốn áp dụng Dify API Key & Base URL này cho TẤT CẢ các Fanpage/Kênh không?',
        )
    ) {
        return;
    }

    router.put(
        '/settings/account/ai/pages',
        {
            enabled: true,
            provider: 'dify',
            dify_api_key: globalDifyKey.value.trim(),
            dify_base_url: globalDifyUrl.value.trim(),
        } as any,
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    'Đã áp dụng cấu hình Dify cho toàn bộ Page trong Workspace.',
                );
            },
        },
    );
};

const providerLabel = (provider: string): string => {
    const labels: Record<string, string> = {
        openai: 'OpenAI',
        anthropic: 'Anthropic',
        gemini: 'Gemini',
        openrouter: 'OpenRouter',
        xai: 'xAI',
        groq: 'Groq',
        mistral: 'Mistral',
        deepseek: 'DeepSeek',
        ollama: 'Ollama',
        fal: 'Fal.ai',
        replicate: 'Replicate',
        dify: 'Dify',
    };

    return labels[provider] ?? provider;
};

const handleApplyToAll = (careData: PageAiCare) => {
    router.put('/settings/account/ai/pages', careData as any, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(
                'Đã sao chép và áp dụng cấu hình cho toàn bộ Page thành công!',
            );
        },
    });
};
</script>

<template>
    <Head :title="trans('settings.account.ai.title')" />

    <AppLayout>
        <div
            class="mx-auto flex h-full w-full max-w-7xl flex-col gap-6 px-6 py-8"
        >
            <PageHeader
                :title="trans('settings.account.title')"
                :description="trans('settings.account.description')"
            />

            <SettingsTabsNav :tabs="tabs" active="ai" />

            <!-- Sub Navigation Tabs -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-foreground/15 pb-3"
            >
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-colors sm:text-sm"
                        :class="[
                            currentSection === 'pages'
                                ? 'bg-foreground text-background shadow-xs'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                        ]"
                        @click="currentSection = 'pages'"
                    >
                        <IconRobot class="size-4" />
                        AI Chăm sóc Fanpage (Dify Chatbot)
                        <Badge
                            v-if="pages && pages.length"
                            variant="secondary"
                            class="ml-1 text-xs"
                        >
                            {{ pages.length }}
                        </Badge>
                    </button>

                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-colors sm:text-sm"
                        :class="[
                            currentSection === 'global'
                                ? 'bg-foreground text-background shadow-xs'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                        ]"
                        @click="currentSection = 'global'"
                    >
                        <IconKey class="size-4" />
                        AI Tạo Bài Viết & Providers Hệ Thống
                    </button>
                </div>
            </div>

            <!-- SECTION 1: PER-PAGE AI CARE & OPERATING SCHEDULE -->
            <div v-if="currentSection === 'pages'" class="space-y-6">
                <!-- Info & Quick Apply Bar -->
                <div
                    class="space-y-4 rounded-2xl border-2 border-foreground bg-card p-5 shadow-2xs"
                >
                    <div
                        class="flex items-start gap-3 border-b border-foreground/10 pb-4"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary font-bold text-primary-foreground"
                        >
                            <IconSparkles class="size-5" />
                        </div>
                        <div class="space-y-0.5">
                            <h3 class="text-sm font-bold text-foreground">
                                Quản lý AI Chăm Sóc Khách Hàng (Tích hợp Dify)
                            </h3>
                            <p
                                class="text-xs leading-relaxed text-muted-foreground"
                            >
                                Toàn bộ kịch bản, Prompt, AI Model và Kiến thức
                                sản phẩm (Knowledge Base) đã được xử lý trên
                                Dify. Bạn chỉ cần nhập Dify API Key, chọn bật AI
                                và thiết lập khung giờ trực cho từng trang.
                            </p>
                        </div>
                    </div>

                    <!-- Quick Apply for all Pages -->
                    <div class="space-y-3 pt-1">
                        <div
                            class="flex items-center gap-2 text-xs font-bold text-foreground"
                        >
                            <IconPlugConnected class="size-4 text-primary" />
                            <span
                                >⚡ Áp dụng nhanh Dify API Key chung cho tất cả
                                Fanpage / Kênh:</span
                            >
                        </div>
                        <div class="grid gap-3 sm:grid-cols-12">
                            <div class="sm:col-span-4">
                                <Label class="text-[11px] font-bold"
                                    >Dify Base URL</Label
                                >
                                <Input
                                    v-model="globalDifyUrl"
                                    placeholder="https://kingai.tnicorporation.com/v1"
                                    class="h-9 text-xs"
                                />
                            </div>
                            <div class="sm:col-span-5">
                                <Label class="text-[11px] font-bold"
                                    >Dify App API Key</Label
                                >
                                <Input
                                    v-model="globalDifyKey"
                                    type="password"
                                    placeholder="app-xxxxxxxxxxxxxxxxxxxxxxxx"
                                    class="h-9 font-mono text-xs"
                                />
                            </div>
                            <div class="flex items-end sm:col-span-3">
                                <Button
                                    type="button"
                                    class="h-9 w-full cursor-pointer gap-1.5 text-xs font-bold"
                                    @click="applyGlobalDifyToAllPages"
                                >
                                    <IconPlugConnected class="size-4" />
                                    Đồng bộ tới tất cả Page
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="!pages || pages.length === 0"
                    class="rounded-2xl border-2 border-foreground bg-card p-12 text-center"
                >
                    <IconRobot
                        class="mx-auto size-12 text-muted-foreground/50"
                    />
                    <h3 class="mt-3 text-base font-bold text-foreground">
                        Chưa có Fanpage / Kênh nào được kết nối
                    </h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Hãy kết nối các trang mạng xã hội của bạn ở mục Kênh Kết
                        Nối để thiết lập AI chăm sóc.
                    </p>
                </div>

                <!-- Page Cards List -->
                <div v-else class="space-y-5">
                    <PageAiCareCard
                        v-for="page in pages"
                        :key="page.id"
                        :page="page"
                        @apply-to-all="handleApplyToAll"
                    />
                </div>
            </div>

            <!-- SECTION 2: GLOBAL API KEYS & PROVIDERS FOR POST GENERATION -->
            <div v-if="currentSection === 'global'" class="space-y-8">
                <Form
                    v-bind="aiSettingsUpdate.form()"
                    #default="{ errors, processing, recentlySuccessful }"
                    class="space-y-8"
                >
                    <!-- Defaults -->
                    <div
                        class="space-y-4 rounded-2xl border-2 border-foreground bg-card p-6 shadow-2xs"
                    >
                        <div class="border-b border-foreground/10 pb-3">
                            <HeadingSmall
                                :title="
                                    trans('settings.ai.default_providers_title')
                                "
                                :description="
                                    trans(
                                        'settings.ai.default_providers_description',
                                    )
                                "
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="content_clone_ai_provider">
                                    {{
                                        trans(
                                            'settings.ai.fields.content_clone_provider',
                                        )
                                    }}
                                </Label>
                                <select
                                    id="content_clone_ai_provider"
                                    name="content_clone_ai_provider"
                                    :value="settings.content_clone_ai_provider"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground focus:ring-1 focus:ring-ring focus:outline-hidden"
                                >
                                    <option
                                        v-for="p in options.contentCloneProviders"
                                        :key="p"
                                        :value="p"
                                    >
                                        {{ providerLabel(p) }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.content_clone_ai_provider"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="ai_text_provider">
                                    {{
                                        trans(
                                            'settings.ai.fields.text_provider',
                                        )
                                    }}
                                </Label>
                                <select
                                    id="ai_text_provider"
                                    name="ai_text_provider"
                                    :value="settings.ai_text_provider"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground focus:ring-1 focus:ring-ring focus:outline-hidden"
                                >
                                    <option
                                        v-for="p in options.textProviders"
                                        :key="p"
                                        :value="p"
                                    >
                                        {{ providerLabel(p) }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.ai_text_provider"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="ai_image_provider">
                                    {{
                                        trans(
                                            'settings.ai.fields.image_provider',
                                        )
                                    }}
                                </Label>
                                <select
                                    id="ai_image_provider"
                                    name="ai_image_provider"
                                    :value="settings.ai_image_provider"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground focus:ring-1 focus:ring-ring focus:outline-hidden"
                                >
                                    <option
                                        v-for="p in options.imageProviders"
                                        :key="p"
                                        :value="p"
                                    >
                                        {{ providerLabel(p) }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.ai_image_provider"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Dify Server & Workflows Configuration -->
                    <div
                        class="space-y-6 rounded-2xl border-2 border-foreground bg-card p-6 shadow-2xs"
                    >
                        <div
                            class="flex items-center justify-between border-b border-foreground/10 pb-3"
                        >
                            <div>
                                <h3
                                    class="flex items-center gap-2 text-sm font-bold text-foreground"
                                >
                                    <IconBolt class="size-4 text-amber-500" />
                                    <span
                                        >Cấu hình Máy chủ Dify (Dify AI
                                        Platform)</span
                                    >
                                </h3>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Kết nối trực tiếp tới Dify Server để chạy
                                    Flow Clone Nội Dung (King Coffee Creative
                                    Suite) và các tác vụ AI.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2 sm:col-span-2">
                                <Label for="dify_base_url" class="font-bold">
                                    Dify Base URL
                                </Label>
                                <Input
                                    id="dify_base_url"
                                    name="dify_base_url"
                                    :default-value="
                                        settings.dify_base_url ||
                                        'https://kingai.tnicorporation.com/v1'
                                    "
                                    placeholder="https://kingai.tnicorporation.com/v1"
                                    class="font-mono text-xs"
                                    required
                                />
                                <InputError :message="errors.dify_base_url" />
                            </div>

                            <div
                                class="space-y-2 rounded-xl border border-foreground/10 p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <Label
                                        for="dify_content_clone_api_key"
                                        class="font-bold"
                                    >
                                        Dify Content Clone Workflow API Key
                                    </Label>
                                    <span
                                        v-if="
                                            settings.dify_content_clone_api_key_configured
                                        "
                                        class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        <IconCheck class="size-3.5" /> Đã cấu
                                        hình
                                    </span>
                                </div>
                                <p class="text-3xs text-muted-foreground">
                                    API Key của App/Workflow
                                    <strong
                                        >Content Generator - King Coffee AI
                                        Creative Suite</strong
                                    >
                                    trên Dify (tạo bài viết, ảnh & video).
                                </p>
                                <Input
                                    id="dify_content_clone_api_key"
                                    name="dify_content_clone_api_key"
                                    type="password"
                                    :placeholder="
                                        settings.dify_content_clone_api_key_configured
                                            ? '••••••••••••••••'
                                            : 'app-xxxxxxxxxxxxxxxxxxxxxxxx'
                                    "
                                    class="font-mono text-xs"
                                />
                                <InputError
                                    :message="errors.dify_content_clone_api_key"
                                />
                            </div>

                            <div
                                class="space-y-2 rounded-xl border border-foreground/10 p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <Label for="dify_api_key" class="font-bold">
                                        Dify API Key (Chung / OmniChat)
                                    </Label>
                                    <span
                                        v-if="settings.dify_api_key_configured"
                                        class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        <IconCheck class="size-3.5" /> Đã cấu
                                        hình
                                    </span>
                                </div>
                                <p class="text-3xs text-muted-foreground">
                                    API Key mặc định cho các dịch vụ Dify khác
                                    trong hệ thống.
                                </p>
                                <Input
                                    id="dify_api_key"
                                    name="dify_api_key"
                                    type="password"
                                    :placeholder="
                                        settings.dify_api_key_configured
                                            ? '••••••••••••••••'
                                            : 'app-xxxxxxxxxxxxxxxxxxxxxxxx'
                                    "
                                    class="font-mono text-xs"
                                />
                                <InputError :message="errors.dify_api_key" />
                            </div>

                            <div
                                class="space-y-2 rounded-xl border border-foreground/10 p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <Label
                                        for="dify_text_api_key"
                                        class="font-bold"
                                    >
                                        Dify Text Generation API Key
                                    </Label>
                                    <span
                                        v-if="
                                            settings.dify_text_api_key_configured
                                        "
                                        class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        <IconCheck class="size-3.5" /> Đã cấu
                                        hình
                                    </span>
                                </div>
                                <Input
                                    id="dify_text_api_key"
                                    name="dify_text_api_key"
                                    type="password"
                                    :placeholder="
                                        settings.dify_text_api_key_configured
                                            ? '••••••••••••••••'
                                            : 'app-xxxxxxxxxxxxxxxxxxxxxxxx'
                                    "
                                    class="font-mono text-xs"
                                />
                                <InputError
                                    :message="errors.dify_text_api_key"
                                />
                            </div>

                            <div
                                class="space-y-2 rounded-xl border border-foreground/10 p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <Label
                                        for="dify_image_api_key"
                                        class="font-bold"
                                    >
                                        Dify Image Generation API Key
                                    </Label>
                                    <span
                                        v-if="
                                            settings.dify_image_api_key_configured
                                        "
                                        class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        <IconCheck class="size-3.5" /> Đã cấu
                                        hình
                                    </span>
                                </div>
                                <Input
                                    id="dify_image_api_key"
                                    name="dify_image_api_key"
                                    type="password"
                                    :placeholder="
                                        settings.dify_image_api_key_configured
                                            ? '••••••••••••••••'
                                            : 'app-xxxxxxxxxxxxxxxxxxxxxxxx'
                                    "
                                    class="font-mono text-xs"
                                />
                                <InputError
                                    :message="errors.dify_image_api_key"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label
                                    for="dify_connect_timeout"
                                    class="text-xs font-bold"
                                    >Thời gian chờ kết nối (giây)</Label
                                >
                                <Input
                                    id="dify_connect_timeout"
                                    name="dify_connect_timeout"
                                    type="number"
                                    min="1"
                                    max="60"
                                    :default-value="
                                        settings.dify_connect_timeout || 10
                                    "
                                    class="h-9 text-xs"
                                    required
                                />
                                <InputError
                                    :message="errors.dify_connect_timeout"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label
                                    for="dify_timeout"
                                    class="text-xs font-bold"
                                    >Thời gian chờ phản hồi tối đa (giây)</Label
                                >
                                <Input
                                    id="dify_timeout"
                                    name="dify_timeout"
                                    type="number"
                                    min="10"
                                    max="300"
                                    :default-value="
                                        settings.dify_timeout || 120
                                    "
                                    class="h-9 text-xs"
                                    required
                                />
                                <InputError :message="errors.dify_timeout" />
                            </div>
                        </div>
                    </div>

                    <!-- Other API Keys Configuration -->
                    <div
                        class="space-y-6 rounded-2xl border-2 border-foreground bg-card p-6 shadow-2xs"
                    >
                        <div class="border-b border-foreground/10 pb-3">
                            <HeadingSmall
                                :title="trans('settings.ai.api_keys_title')"
                                :description="
                                    trans('settings.ai.api_keys_description')
                                "
                            />
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div
                                v-for="provider in options.secretProviders"
                                :key="provider"
                                class="space-y-2 rounded-xl border border-foreground/10 p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <Label
                                        :for="`${provider}_api_key`"
                                        class="font-bold"
                                    >
                                        {{ providerLabel(provider) }} API Key
                                    </Label>
                                    <span
                                        v-if="settings.provider_keys[provider]"
                                        class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        <IconCheck class="size-3.5" /> Đã cấu
                                        hình
                                    </span>
                                </div>
                                <Input
                                    :id="`${provider}_api_key`"
                                    :name="`${provider}_api_key`"
                                    type="password"
                                    :placeholder="
                                        settings.provider_keys[provider]
                                            ? '••••••••••••••••'
                                            : 'Nhập API key...'
                                    "
                                    class="font-mono text-xs"
                                />
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-end gap-3 border-t border-foreground/10 pt-4"
                        >
                            <span
                                v-if="recentlySuccessful"
                                class="text-xs font-bold text-emerald-600"
                            >
                                {{ trans('settings.saved') }}
                            </span>
                            <Button
                                type="submit"
                                :disabled="processing"
                                class="font-bold"
                            >
                                <IconDeviceFloppy class="size-4" />
                                {{
                                    processing
                                        ? trans('common.saving')
                                        : trans('settings.save')
                                }}
                            </Button>
                        </div>
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
