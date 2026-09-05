<script setup lang="ts">
import {
    IconBrandFacebook,
    IconBrandInstagram,
    IconBrandTiktok,
    IconBrandYoutube,
    IconClock,
    IconMessageCircle,
    IconRobot,
} from '@tabler/icons-vue';
import { computed } from 'vue';

interface PageMetric {
    account_id: string;
    display_name: string;
    username: string;
    platform: string;
    avatar_url: string | null;
    ai_care_enabled: boolean;
    bot_name: string;
    schedule_mode: string;
    total_conversations: number;
    total_messages: number;
    ai_handled_rate: number;
    resolved_rate: number;
    avg_response_seconds: number;
}

const props = defineProps<{
    pageMetrics: PageMetric[];
    selectedPageId?: string;
}>();

const emit = defineEmits<{
    (e: 'select-page', pageId: string): void;
}>();

const maxConversations = computed(() => {
    const max = Math.max(
        ...props.pageMetrics.map((p) => p.total_conversations),
        1,
    );
    return max;
});

const getPlatformIcon = (platform: string) => {
    switch (platform) {
        case 'facebook':
            return IconBrandFacebook;
        case 'instagram':
        case 'instagram-facebook':
            return IconBrandInstagram;
        case 'tiktok':
            return IconBrandTiktok;
        case 'youtube':
            return IconBrandYoutube;
        default:
            return IconMessageCircle;
    }
};

const getPlatformColor = (platform: string) => {
    switch (platform) {
        case 'facebook':
            return 'text-blue-600 bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-900';
        case 'instagram':
        case 'instagram-facebook':
            return 'text-pink-600 bg-pink-50 dark:bg-pink-950/40 border-pink-200 dark:border-pink-900';
        case 'tiktok':
            return 'text-foreground bg-foreground/5 border-foreground/15';
        case 'youtube':
            return 'text-red-600 bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-900';
        default:
            return 'text-primary bg-primary/10 border-primary/20';
    }
};
</script>

<template>
    <div
        class="space-y-4 rounded-2xl border-2 border-foreground bg-card p-5 shadow-2xs"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-2 border-b border-foreground/10 pb-3"
        >
            <div>
                <h3 class="text-base font-bold text-foreground">
                    Thống kê & Hiệu suất từng Page / Kênh
                </h3>
                <p class="text-xs text-muted-foreground">
                    So sánh lưu lượng tin nhắn, tỷ lệ AI xử lý tự động và chất
                    lượng chăm sóc của từng trang.
                </p>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span
                    class="inline-flex items-center gap-1.5 font-medium text-emerald-600 dark:text-emerald-400"
                >
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    AI đang kích hoạt
                </span>
                <span
                    class="inline-flex items-center gap-1.5 font-medium text-muted-foreground"
                >
                    <span
                        class="size-2 rounded-full bg-muted-foreground/40"
                    ></span>
                    AI thủ công / tắt
                </span>
            </div>
        </div>

        <div
            v-if="pageMetrics.length === 0"
            class="py-8 text-center text-sm text-muted-foreground"
        >
            Chưa có Page / Kênh nào được kết nối trong Workspace này.
        </div>

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="page in pageMetrics"
                :key="page.account_id"
                class="group relative flex flex-col justify-between rounded-xl border-2 p-4 transition-all hover:border-foreground hover:shadow-xs"
                :class="[
                    selectedPageId === page.account_id
                        ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                        : 'border-foreground/15 bg-background',
                ]"
                @click="emit('select-page', page.account_id)"
            >
                <div class="space-y-3">
                    <!-- Page Header -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex size-10 items-center justify-center rounded-lg border text-base font-bold"
                                :class="getPlatformColor(page.platform)"
                            >
                                <img
                                    v-if="page.avatar_url"
                                    :src="page.avatar_url"
                                    :alt="page.display_name"
                                    class="size-full rounded-lg object-cover"
                                />
                                <component
                                    :is="getPlatformIcon(page.platform)"
                                    v-else
                                    class="size-5"
                                />
                            </div>
                            <div class="min-w-0">
                                <h4
                                    class="truncate text-sm font-bold text-foreground"
                                >
                                    {{ page.display_name }}
                                </h4>
                                <p class="text-xs text-muted-foreground">
                                    {{ page.platform }} • {{ page.bot_name }}
                                </p>
                            </div>
                        </div>

                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="[
                                page.ai_care_enabled
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                    : 'bg-muted text-muted-foreground',
                            ]"
                        >
                            <IconRobot class="size-3" />
                            {{
                                page.ai_care_enabled
                                    ? 'AI Bật (' + page.schedule_mode + ')'
                                    : 'Chưa bật AI'
                            }}
                        </span>
                    </div>

                    <!-- Progress bar of volume -->
                    <div class="space-y-1">
                        <div class="flex justify-between text-xs font-semibold">
                            <span class="text-muted-foreground">Hội thoại</span>
                            <span class="text-foreground"
                                >{{ page.total_conversations }} cuộc ({{
                                    page.total_messages
                                }}
                                tin nhắn)</span
                            >
                        </div>
                        <div
                            class="h-2 w-full overflow-hidden rounded-full bg-foreground/10"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-all duration-500"
                                :style="{
                                    width: `${Math.max((page.total_conversations / maxConversations) * 100, 8)}%`,
                                }"
                            ></div>
                        </div>
                    </div>

                    <!-- Key stats cards inside page -->
                    <div
                        class="grid grid-cols-3 gap-1.5 rounded-lg border border-foreground/10 bg-card/60 p-2 text-center"
                    >
                        <div>
                            <div class="text-[11px] text-muted-foreground">
                                Tỷ lệ AI
                            </div>
                            <div
                                class="text-xs font-bold"
                                :class="
                                    page.ai_care_enabled &&
                                    page.ai_handled_rate > 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ page.ai_handled_rate }}%
                            </div>
                        </div>
                        <div class="border-x border-foreground/10 px-1">
                            <div class="text-[11px] text-muted-foreground">
                                Giải quyết
                            </div>
                            <div class="text-xs font-bold text-foreground">
                                {{ page.resolved_rate }}%
                            </div>
                        </div>
                        <div>
                            <div class="text-[11px] text-muted-foreground">
                                Tốc độ
                            </div>
                            <div class="text-xs font-bold text-foreground">
                                {{ page.avg_response_seconds }}s
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-3 flex items-center justify-between border-t border-foreground/10 pt-2 text-xs font-medium text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1">
                        <IconClock class="size-3" />
                        Trực: {{ page.schedule_mode }}
                    </span>
                    <span class="text-primary group-hover:underline">
                        Lọc theo Page &rarr;
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
