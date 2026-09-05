<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    IconAward,
    IconChartLine,
    IconCrown,
    IconExternalLink,
    IconEye,
    IconFlame,
    IconHeart,
    IconMessageCircle,
    IconRefresh,
    IconSearch,
    IconShare,
    IconTarget,
    IconUserPlus,
} from '@tabler/icons-vue';
import { VisAxis, VisLine, VisXYContainer } from '@unovis/vue';
import { computed, ref, watch } from 'vue';

import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    exportMethod as analyticsExport,
    index as analyticsIndex,
} from '@/routes/app/post-analytics';
import {
    syncAll as syncAllFacebook,
    sync as syncFacebook,
} from '@/routes/app/post-analytics/facebook';
import {
    syncAll as syncAllTikTok,
    sync as syncTikTok,
} from '@/routes/app/post-analytics/tiktok';
import {
    syncAll as syncAllYouTube,
    sync as syncYouTube,
} from '@/routes/app/post-analytics/youtube';

interface PostRow {
    id: string;
    content: string;
    excerpt: string;
    media_preview: string | null;
    published_at: string | null;
    platforms: string[];
    external_url?: string | null;
    platform_urls?: Record<string, string>;
    views: number;
    reach: number;
    impressions: number;
    reactions: number;
    comments: number;
    shares: number;
    likes: number;
    interactions: number;
    engagement_rate: number;
    is_ceo_content?: boolean;
    topic_tags?: string[];
    growth: number;
}

type MetricKey =
    | 'posts'
    | 'reach'
    | 'impressions'
    | 'reactions'
    | 'comments'
    | 'shares'
    | 'engagement_rate';

type ChartMetric =
    | 'reach'
    | 'impressions'
    | 'reactions'
    | 'comments'
    | 'shares'
    | 'interactions'
    | 'views';

type ComparisonValue = {
    current: number;
    previous: number;
    change: number | null;
};

type GrowthPoint = {
    date: string;
    views: number;
    reach: number;
    impressions: number;
    interactions: number;
    reactions: number;
    likes: number;
    comments: number;
    shares: number;
    engagement_rate: number;
};

interface KpiSummary {
    target_posts: number;
    actual_posts: number;
    completion_rate: number;
    target_ceo_posts: number;
    actual_ceo_posts: number;
    ceo_completion_rate: number;
}

interface FollowerGrowth {
    start_followers: number;
    end_followers: number;
    net_growth: number;
    growth_percent: number | null;
}

interface Props {
    posts?: {
        data: PostRow[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    summary: Record<string, number>;
    comparison: Record<string, ComparisonValue>;
    growth: GrowthPoint[];
    top_content?: PostRow[];
    kpi_summary?: KpiSummary;
    follower_growth?: FollowerGrowth;
    filters: {
        search: string;
        platform: string;
        account_id: string;
        content_type?: string;
        topic_tag?: string;
        sort: string;
        period: string;
        from: string;
        to: string;
    };
    dateRange: {
        from: string;
        to: string;
        previous_from: string;
        previous_to: string;
        days: number;
    };
    facebookPages: {
        id: string;
        display_name: string | null;
        username: string | null;
        avatar_url: string | null;
    }[];
    youtubeChannels: {
        id: string;
        display_name: string | null;
        username: string | null;
        avatar_url: string | null;
    }[];
    tiktokAccounts: {
        id: string;
        display_name: string | null;
        username: string | null;
        avatar_url: string | null;
    }[];
    workspaceTags?: {
        id: string;
        name: string;
        color?: string;
    }[];
}

const props = withDefaults(defineProps<Props>(), {
    posts: () => ({
        data: [],
        links: [],
        current_page: 1,
        last_page: 1,
        from: null,
        to: null,
        total: 0,
    }),
    summary: () => ({}),
    comparison: () => ({}),
    growth: () => [],
    top_content: () => [],
    facebookPages: () => [],
    youtubeChannels: () => [],
    tiktokAccounts: () => [],
    workspaceTags: () => [],
});

const safePosts = computed(() => ({
    data: Array.isArray(props.posts?.data) ? props.posts.data : [],
    links: Array.isArray(props.posts?.links) ? props.posts.links : [],
    current_page: props.posts?.current_page ?? 1,
    last_page: props.posts?.last_page ?? 1,
    from: props.posts?.from ?? null,
    to: props.posts?.to ?? null,
    total: props.posts?.total ?? 0,
}));

const search = ref(props.filters.search);
const platform = ref(props.filters.platform);
const accountId = ref(props.filters.account_id ?? 'all');
const contentType = ref(props.filters.content_type ?? 'all');
const topicTag = ref(props.filters.topic_tag ?? 'all');
const sort = ref(props.filters.sort);
const period = ref(props.filters.period ?? '30d');
const from = ref(props.dateRange.from.slice(0, 10));
const to = ref(props.dateRange.to.slice(0, 10));
const selectedMetric = ref<ChartMetric>('reach');

const platforms = [
    'all',
    'facebook',
    'instagram',
    'instagram-facebook',
    'tiktok',
    'youtube',
    'x',
    'linkedin',
    'linkedin-page',
    'threads',
];

const periodOptions = [
    { value: '7d', label: '7 ngày' },
    { value: '30d', label: '30 ngày' },
    { value: '90d', label: '3 tháng' },
    { value: '365d', label: '1 năm' },
    { value: 'baseline_july_2026', label: '📊 Baseline T7/2026' },
    { value: 'custom', label: 'Tùy chọn' },
];

const metricOptions: { value: ChartMetric; label: string }[] = [
    { value: 'reach', label: 'Người xem (Reach)' },
    { value: 'impressions', label: 'Lượt xem (Impressions)' },
    { value: 'reactions', label: 'Lượt cảm xúc (Reactions)' },
    { value: 'comments', label: 'Bình luận (Comments)' },
    { value: 'shares', label: 'Chia sẻ (Shares)' },
    { value: 'interactions', label: 'Tổng tương tác' },
];

const filterQuery = () => ({
    search: search.value || undefined,
    platform: platform.value,
    account_id: ['facebook', 'youtube', 'tiktok'].includes(platform.value)
        ? accountId.value
        : undefined,
    content_type: contentType.value !== 'all' ? contentType.value : undefined,
    topic_tag: topicTag.value !== 'all' ? topicTag.value : undefined,
    sort: sort.value,
    period: period.value,
    from: period.value === 'custom' ? from.value : undefined,
    to: period.value === 'custom' ? to.value : undefined,
});

const applyFilters = () => {
    if (period.value === 'custom' && (!from.value || !to.value)) return;
    router.get(analyticsIndex.url(), filterQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearSearch = () => {
    search.value = '';
    applyFilters();
};

watch(
    () => props.filters,
    (newFilters) => {
        if (newFilters) {
            search.value = newFilters.search || '';
            platform.value = newFilters.platform || 'all';
            accountId.value = newFilters.account_id || 'all';
            contentType.value = newFilters.content_type || 'all';
            topicTag.value = newFilters.topic_tag || 'all';
            sort.value = newFilters.sort || 'recent';
            period.value = newFilters.period || '30d';
        }
    },
    { deep: true },
);

watch(platform, (value) => {
    if (!['facebook', 'youtube', 'tiktok'].includes(value))
        accountId.value = 'all';
    applyFilters();
});

watch([accountId, contentType, topicTag, sort], applyFilters);

watch(period, (value) => {
    if (value !== 'custom') applyFilters();
});

watch([from, to], () => {
    if (period.value === 'custom') applyFilters();
});

const exportUrl = computed(() => analyticsExport.url({ query: filterQuery() }));

const format = (value: number): string =>
    new Intl.NumberFormat('vi-VN', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value || 0);

const formatNumber = (value: number): string =>
    new Intl.NumberFormat('vi-VN').format(value || 0);

const date = (value: string | null): string =>
    value
        ? new Intl.DateTimeFormat('vi-VN', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
          }).format(new Date(value))
        : '—';

const rangeLabel = computed(
    () => `${date(props.dateRange.from)} – ${date(props.dateRange.to)}`,
);
const previousRangeLabel = computed(
    () =>
        `${date(props.dateRange.previous_from)} – ${date(props.dateRange.previous_to)}`,
);

const x = (_point: GrowthPoint, index: number) => index;
const xTickFormat = (index: number) => {
    const point = props.growth?.[index];
    if (!point) return '';
    const value = new Date(point.date);
    return props.dateRange.days > 180
        ? new Intl.DateTimeFormat('vi-VN', {
              month: '2-digit',
              year: '2-digit',
          }).format(value)
        : new Intl.DateTimeFormat('vi-VN', {
              day: '2-digit',
              month: '2-digit',
          }).format(value);
};

const yMetric = (point: GrowthPoint) =>
    (point as any)[selectedMetric.value] ?? point?.views ?? 0;
const hasGrowth = computed(() => (props.growth?.length ?? 0) > 1);

const comparisonLabel = (value?: ComparisonValue): string => {
    if (!value || value.change === null)
        return (value?.current ?? 0) > 0
            ? 'Chưa có dữ liệu kỳ trước'
            : 'Chưa có dữ liệu';
    if (value.change === 0) return 'Không đổi so với kỳ trước';
    return `${value.change > 0 ? '+' : ''}${value.change}% so với kỳ trước`;
};

const syncing = ref(false);
const previewPost = ref<PostRow | null>(null);
const previewOpen = ref(false);

const syncPosts = (
    provider?: 'facebook' | 'youtube' | 'tiktok',
    specificAccountId?: string,
) => {
    const targetProvider =
        provider ||
        (['facebook', 'youtube', 'tiktok'].includes(platform.value)
            ? (platform.value as 'facebook' | 'youtube' | 'tiktok')
            : 'facebook');

    let targetAccountId = specificAccountId || accountId.value;

    syncing.value = true;

    if (!targetAccountId || targetAccountId === 'all') {
        const routeToUse =
            targetProvider === 'facebook'
                ? syncAllFacebook
                : targetProvider === 'youtube'
                  ? syncAllYouTube
                  : syncAllTikTok;

        router.post(
            routeToUse.url(),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    syncing.value = false;
                },
            },
        );
    } else {
        const routeToUse =
            targetProvider === 'facebook'
                ? syncFacebook
                : targetProvider === 'youtube'
                  ? syncYouTube
                  : syncTikTok;

        router.post(
            routeToUse.url({ account: targetAccountId }),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    syncing.value = false;
                },
            },
        );
    }
};

const openPreview = (post: PostRow) => {
    previewPost.value = post;
    previewOpen.value = true;
};

const visitPage = (url: string | null) => {
    if (url) router.visit(url, { preserveScroll: true, preserveState: true });
};

const pageLabel = (label: string): string =>
    label.replace('&laquo;', '').replace('&raquo;', '').trim();
</script>

<template>
    <Head title="Phân tích & KPI Facebook - KingHub" />
    <AppLayout>
        <div
            class="mx-auto flex h-full w-full max-w-7xl flex-col gap-6 px-6 py-8"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <PageHeader
                    title="Phân tích KPI Facebook & Mạng Xã Hội"
                    description="Theo dõi toàn diện 10 chỉ số KPI chuẩn: Tiếp cận, Tương tác độc lập, Follower, Tỷ lệ tương tác và Kế hoạch bài CEO."
                />

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg border-2 border-foreground bg-primary px-3 text-xs font-bold text-primary-foreground shadow-2xs transition-all hover:bg-primary/90 disabled:opacity-60"
                        :disabled="syncing"
                        @click="syncPosts()"
                    >
                        <IconRefresh
                            class="size-4"
                            :class="{ 'animate-spin': syncing }"
                        />
                        <span>{{
                            syncing
                                ? 'Đang đồng bộ...'
                                : 'Đồng bộ dữ liệu bài viết'
                        }}</span>
                    </button>

                    <a
                        :href="exportUrl"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-foreground/20 bg-background px-3 text-xs font-bold text-foreground transition-colors hover:bg-muted"
                    >
                        Xuất báo cáo Excel / CSV
                    </a>
                </div>
            </div>

            <!-- TIME PERIOD SELECTOR & BASELINE BAR -->
            <section
                class="rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
            >
                <div
                    class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
                >
                    <div>
                        <p class="text-sm font-bold text-foreground">
                            Khoảng thời gian phân tích
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Dữ liệu tự động tính toán động từ số liệu gốc
                            Facebook Insights
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="option in periodOptions"
                            :key="option.value"
                            type="button"
                            class="h-9 cursor-pointer rounded-lg border px-3 text-xs font-bold transition-colors"
                            :class="
                                period === option.value
                                    ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                                    : 'bg-background text-muted-foreground hover:bg-muted hover:text-foreground'
                            "
                            @click="period = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="period === 'custom'"
                    class="mt-4 grid gap-3 border-t border-foreground/10 pt-4 sm:grid-cols-2 lg:max-w-xl"
                >
                    <label class="grid gap-1.5 text-xs font-bold">
                        <span>Từ ngày</span>
                        <input
                            v-model="from"
                            type="date"
                            :max="to"
                            class="h-9 rounded-lg border bg-background px-3 text-xs font-medium"
                        />
                    </label>
                    <label class="grid gap-1.5 text-xs font-bold">
                        <span>Đến ngày</span>
                        <input
                            v-model="to"
                            type="date"
                            :min="from"
                            class="h-9 rounded-lg border bg-background px-3 text-xs font-medium"
                        />
                    </label>
                </div>

                <div
                    class="mt-4 flex flex-col gap-1 border-t border-foreground/10 pt-3 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
                >
                    <span
                        >Kỳ hiện tại:
                        <strong class="font-bold text-foreground">{{
                            rangeLabel
                        }}</strong></span
                    >
                    <span
                        >Kỳ so sánh liền kề:
                        <strong class="font-bold text-foreground">{{
                            previousRangeLabel
                        }}</strong></span
                    >
                </div>
            </section>

            <!-- 10 KPI CARDS GRID (CHUẨN THEO FILE EXCEL KPI_Facebook_GD1_gui_IT.xlsx) -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <!-- 1. REACH (Người xem) -->
                <div
                    class="rounded-xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-blue-500/10 p-2 text-blue-600"
                            >
                                <IconEye class="size-5" />
                            </div>
                            <span
                                class="text-xs font-bold text-muted-foreground"
                                >Người xem (Reach)</span
                            >
                        </div>
                        <span
                            class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                        >
                            Người
                        </span>
                    </div>
                    <p
                        class="mt-3 text-2xl font-black text-foreground tabular-nums"
                    >
                        {{ formatNumber(summary.reach || 0) }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        {{ comparisonLabel(comparison.reach) }}
                    </p>
                </div>

                <!-- 2. IMPRESSIONS (Lượt xem) -->
                <div
                    class="rounded-xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-indigo-500/10 p-2 text-indigo-600"
                            >
                                <IconChartLine class="size-5" />
                            </div>
                            <span
                                class="text-xs font-bold text-muted-foreground"
                                >Lượt xem (Impressions)</span
                            >
                        </div>
                        <span
                            class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                        >
                            Lượt
                        </span>
                    </div>
                    <p
                        class="mt-3 text-2xl font-black text-foreground tabular-nums"
                    >
                        {{
                            formatNumber(
                                summary.impressions || summary.views || 0,
                            )
                        }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        {{
                            comparisonLabel(
                                comparison.impressions || comparison.views,
                            )
                        }}
                    </p>
                </div>

                <!-- 3. ENGAGEMENT RATE (Tỷ lệ tương tác %) -->
                <div
                    class="rounded-xl border-2 border-primary bg-primary/5 p-4 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-primary p-2 text-primary-foreground"
                            >
                                <IconFlame class="size-5" />
                            </div>
                            <span class="text-xs font-bold text-foreground"
                                >Engagement Rate</span
                            >
                        </div>
                        <span
                            class="rounded-full bg-primary/20 px-2 py-0.5 text-[11px] font-bold text-primary"
                        >
                            % Người xem
                        </span>
                    </div>
                    <p
                        class="mt-3 text-2xl font-black text-primary tabular-nums"
                    >
                        {{ summary.engagement_rate || 0 }}%
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        (Reaction + Comment + Share) ÷ Người xem × 100
                    </p>
                </div>

                <!-- 4. FOLLOWER GROWTH (Tăng trưởng Follower) -->
                <div
                    class="rounded-xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-emerald-500/10 p-2 text-emerald-600"
                            >
                                <IconUserPlus class="size-5" />
                            </div>
                            <span
                                class="text-xs font-bold text-muted-foreground"
                                >Tăng trưởng Follower</span
                            >
                        </div>
                        <span
                            class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                        >
                            Người
                        </span>
                    </div>
                    <p
                        class="mt-3 text-2xl font-black text-foreground tabular-nums"
                    >
                        {{
                            follower_growth?.net_growth &&
                            follower_growth.net_growth > 0
                                ? '+'
                                : ''
                        }}{{ formatNumber(follower_growth?.net_growth || 0) }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Tổng Follower hiện tại:
                        {{ formatNumber(follower_growth?.end_followers || 0) }}
                    </p>
                </div>

                <!-- 5. REACTIONS (Lượt thích) -->
                <div
                    class="rounded-xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-rose-500/10 p-2 text-rose-600"
                            >
                                <IconHeart class="size-5" />
                            </div>
                            <span
                                class="text-xs font-bold text-muted-foreground"
                                >Reaction (Cảm xúc)</span
                            >
                        </div>
                        <span
                            class="text-[11px] font-bold text-muted-foreground"
                            >Lượt</span
                        >
                    </div>
                    <p
                        class="mt-3 text-2xl font-black text-foreground tabular-nums"
                    >
                        {{
                            formatNumber(
                                summary.reactions || summary.likes || 0,
                            )
                        }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Tách riêng, không gộp chung
                    </p>
                </div>

                <!-- 6. COMMENTS (Bình luận) -->
                <div
                    class="rounded-xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-amber-500/10 p-2 text-amber-600"
                            >
                                <IconMessageCircle class="size-5" />
                            </div>
                            <span
                                class="text-xs font-bold text-muted-foreground"
                                >Comment (Bình luận)</span
                            >
                        </div>
                        <span
                            class="text-[11px] font-bold text-muted-foreground"
                            >Lượt</span
                        >
                    </div>
                    <p
                        class="mt-3 text-2xl font-black text-foreground tabular-nums"
                    >
                        {{ formatNumber(summary.comments || 0) }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Tách riêng, không gộp chung
                    </p>
                </div>

                <!-- 7. SHARES (Chia sẻ) -->
                <div
                    class="rounded-xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-teal-500/10 p-2 text-teal-600"
                            >
                                <IconShare class="size-5" />
                            </div>
                            <span
                                class="text-xs font-bold text-muted-foreground"
                                >Share (Chia sẻ)</span
                            >
                        </div>
                        <span
                            class="text-[11px] font-bold text-muted-foreground"
                            >Lượt</span
                        >
                    </div>
                    <p
                        class="mt-3 text-2xl font-black text-foreground tabular-nums"
                    >
                        {{ formatNumber(summary.shares || 0) }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Tách riêng, không gộp chung
                    </p>
                </div>

                <!-- 8. TIẾN ĐỘ KẾ HOẠCH BÀI ĐĂNG (Vận hành KPI) -->
                <div
                    class="rounded-xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="rounded-lg bg-purple-500/10 p-2 text-purple-600"
                            >
                                <IconTarget class="size-5" />
                            </div>
                            <span
                                class="text-xs font-bold text-muted-foreground"
                                >Tiến độ KPI Tuần</span
                            >
                        </div>
                        <span
                            class="rounded-full bg-purple-50 px-2 py-0.5 text-[11px] font-bold text-purple-700 dark:bg-purple-950 dark:text-purple-300"
                        >
                            {{ kpi_summary?.completion_rate || 100 }}%
                        </span>
                    </div>
                    <p
                        class="mt-3 text-2xl font-black text-foreground tabular-nums"
                    >
                        {{ kpi_summary?.actual_posts || summary.posts || 0 }} /
                        {{ kpi_summary?.target_posts || 10 }}
                        <span class="text-xs font-bold text-muted-foreground"
                            >bài</span
                        >
                    </p>
                    <p
                        class="mt-1 text-[11px] font-semibold text-purple-700 dark:text-purple-300"
                    >
                        👑 Bài CEO: {{ kpi_summary?.actual_ceo_posts || 0 }} /
                        {{ kpi_summary?.target_ceo_posts || 3 }} bài ({{
                            kpi_summary?.ceo_completion_rate || 0
                        }}%)
                    </p>
                </div>
            </div>

            <!-- TOP 3 CONTENT WIDGET (TOP BÀI VIẾT HIỆU QUẢ NHẤT THEO ENGAGEMENT RATE) -->
            <section
                v-if="top_content && top_content.length > 0"
                class="space-y-4 rounded-2xl border-2 border-foreground bg-card p-5 shadow-2xs"
            >
                <div
                    class="flex items-center justify-between border-b border-foreground/10 pb-3"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-amber-500 font-bold text-white"
                        >
                            <IconAward class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-foreground">
                                Top 3 Bài Viết Hiệu Quả Nhất Tuần (Top Content
                                by Engagement Rate)
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Xếp hạng theo tỷ lệ tương tác (Engagement Rate)
                                để tối ưu chiến lược nội dung.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div
                        v-for="(post, index) in top_content"
                        :key="post.id"
                        class="relative flex flex-col justify-between space-y-3 overflow-hidden rounded-xl border-2 border-foreground/20 bg-background p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-1.5">
                                <span
                                    class="flex size-6 items-center justify-center rounded-full bg-amber-500 text-xs font-black text-white"
                                >
                                    #{{ index + 1 }}
                                </span>
                                <Badge
                                    v-if="post.is_ceo_content"
                                    class="gap-1 border-amber-300 bg-amber-100 text-[10px] text-amber-900 dark:bg-amber-950 dark:text-amber-300"
                                >
                                    <IconCrown class="size-3" /> CEO
                                </Badge>
                            </div>
                            <span class="text-xs font-black text-primary">
                                🔥 {{ post.engagement_rate }}% ER
                            </span>
                        </div>

                        <p
                            class="line-clamp-3 text-xs leading-relaxed font-medium text-foreground"
                        >
                            {{
                                post.excerpt ||
                                post.content ||
                                'Bài viết không có văn bản.'
                            }}
                        </p>

                        <div
                            class="flex items-center justify-between border-t border-foreground/10 pt-2 text-[11px] text-muted-foreground"
                        >
                            <div class="flex gap-2 font-bold">
                                <span
                                    >👁️
                                    {{ format(post.reach || post.views) }}</span
                                >
                                <span
                                    >❤️
                                    {{
                                        format(post.reactions || post.likes)
                                    }}</span
                                >
                                <span>💬 {{ format(post.comments) }}</span>
                                <span>🔄 {{ format(post.shares) }}</span>
                            </div>
                            <button
                                type="button"
                                class="cursor-pointer font-bold text-primary hover:underline"
                                @click="openPreview(post)"
                            >
                                Chi tiết
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- GROWTH CHART & FILTER PANEL -->
            <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                <!-- Growth Chart -->
                <div
                    class="rounded-2xl border-2 border-foreground bg-card p-5 shadow-2xs"
                >
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <h2 class="text-sm font-bold text-foreground">
                                Biểu đồ Tăng trưởng & Xu hướng
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Đồng bộ định kỳ 4 tuần rolling update từ
                                Facebook Insights
                            </p>
                        </div>
                        <select
                            v-model="selectedMetric"
                            class="h-9 rounded-lg border bg-background px-3 text-xs font-bold"
                        >
                            <option
                                v-for="metric in metricOptions"
                                :key="metric.value"
                                :value="metric.value"
                            >
                                {{ metric.label }}
                            </option>
                        </select>
                    </div>
                    <div v-if="hasGrowth" class="h-56">
                        <VisXYContainer
                            :data="growth"
                            :height="220"
                            :margin="{ top: 8, right: 8, bottom: 4, left: 8 }"
                        >
                            <VisLine
                                :x="x"
                                :y="yMetric"
                                color="var(--color-primary)"
                                :line-width="3"
                            />
                            <VisAxis
                                type="x"
                                :num-ticks="5"
                                :grid-line="false"
                                :domain-line="false"
                                :tick-format="xTickFormat"
                            />
                            <VisAxis
                                type="y"
                                :num-ticks="4"
                                :grid-line="false"
                                :domain-line="false"
                            />
                        </VisXYContainer>
                    </div>
                    <div
                        v-else
                        class="flex h-56 items-center justify-center text-xs text-muted-foreground"
                    >
                        Chưa có đủ dữ liệu lịch sử. Hệ thống sẽ tự động ghi nhận
                        sau các lần đồng bộ tiếp theo.
                    </div>
                </div>

                <!-- Filter Controls -->
                <div
                    class="space-y-4 rounded-2xl border-2 border-foreground bg-card p-5 shadow-2xs"
                >
                    <h2 class="text-sm font-bold text-foreground">
                        Bộ lọc & Phân loại bài viết
                    </h2>

                    <!-- Search Input & Action Button -->
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <IconSearch
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <input
                                v-model="search"
                                class="h-9 w-full rounded-md border bg-background pr-8 pl-9 text-xs outline-none focus:ring-2 focus:ring-primary"
                                placeholder="Tìm nội dung bài viết..."
                                @keydown.enter.prevent="applyFilters"
                            />
                            <button
                                v-if="search"
                                type="button"
                                class="absolute top-1/2 right-2.5 -translate-y-1/2 cursor-pointer p-1 text-xs text-muted-foreground hover:text-foreground"
                                title="Xóa tìm kiếm"
                                @click="clearSearch"
                            >
                                ✕
                            </button>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 shrink-0 cursor-pointer items-center gap-1.5 rounded-md border-2 border-foreground bg-primary px-3 text-xs font-bold text-primary-foreground shadow-2xs transition-all hover:bg-primary/90"
                            @click="applyFilters"
                        >
                            <IconSearch class="size-3.5" />
                            <span>Tìm kiếm</span>
                        </button>
                    </div>

                    <!-- Content Type Toggle (All vs CEO vs General) -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold"
                            >Phân loại nội dung</label
                        >
                        <div
                            class="grid grid-cols-3 gap-1 rounded-lg border bg-muted p-1 text-xs"
                        >
                            <button
                                type="button"
                                class="cursor-pointer rounded py-1.5 text-center font-bold transition-colors"
                                :class="
                                    contentType === 'all'
                                        ? 'bg-background text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="contentType = 'all'"
                            >
                                Tất cả
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded py-1.5 text-center font-bold transition-colors"
                                :class="
                                    contentType === 'ceo'
                                        ? 'bg-background text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="contentType = 'ceo'"
                            >
                                👑 Bài CEO
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded py-1.5 text-center font-bold transition-colors"
                                :class="
                                    contentType === 'general'
                                        ? 'bg-background text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="contentType = 'general'"
                            >
                                Bài Thường
                            </button>
                        </div>
                    </div>

                    <!-- Topic / Tag Filter Dropdown -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold"
                            >Lọc theo thẻ bài viết</label
                        >
                        <select
                            v-model="topicTag"
                            class="h-9 w-full rounded-md border bg-background px-3 text-xs font-medium"
                        >
                            <option value="all">Tất cả thẻ bài viết</option>
                            <option
                                v-for="topic in workspaceTags"
                                :key="topic.id"
                                :value="topic.name"
                            >
                                #{{ topic.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Platform & Account Dropdown -->
                    <div class="space-y-2">
                        <select
                            v-model="platform"
                            class="h-9 w-full rounded-md border bg-background px-3 text-xs font-medium"
                        >
                            <option
                                v-for="item in platforms"
                                :key="item"
                                :value="item"
                            >
                                {{ item === 'all' ? 'Tất cả nền tảng' : item }}
                            </option>
                        </select>

                        <select
                            v-if="platform === 'facebook'"
                            v-model="accountId"
                            class="h-9 w-full rounded-md border bg-background px-3 text-xs font-medium"
                        >
                            <option value="all">Tất cả trang Facebook</option>
                            <option
                                v-for="page in facebookPages"
                                :key="page.id"
                                :value="page.id"
                            >
                                {{
                                    page.display_name ||
                                    page.username ||
                                    'Trang Facebook'
                                }}{{
                                    page.username ? ` (@${page.username})` : ''
                                }}
                            </option>
                        </select>

                        <button
                            type="button"
                            class="inline-flex h-8 w-full cursor-pointer items-center justify-center gap-1.5 rounded-md border border-primary bg-primary/10 text-xs font-bold text-primary transition-colors hover:bg-primary/20 disabled:opacity-60"
                            :disabled="syncing"
                            @click="syncPosts()"
                        >
                            <IconRefresh
                                class="size-3.5"
                                :class="{ 'animate-spin': syncing }"
                            />
                            <span>{{
                                syncing ? 'Đang đồng bộ...' : 'Đồng bộ dữ liệu'
                            }}</span>
                        </button>
                    </div>

                    <!-- Sort -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold">Sắp xếp theo</label>
                        <select
                            v-model="sort"
                            class="h-9 w-full rounded-md border bg-background px-3 text-xs font-medium"
                        >
                            <option value="recent">Mới nhất</option>
                            <option value="engagement">
                                Tỷ lệ tương tác cao nhất (Engagement Rate)
                            </option>
                            <option value="trending">
                                Xu hướng (Trend Score)
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- POSTS DATA TABLE -->
            <section
                class="overflow-hidden rounded-2xl border-2 border-foreground bg-card shadow-2xs"
            >
                <!-- ER Formula Note Header -->
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-foreground/10 bg-muted/40 px-4 py-2.5 text-xs"
                >
                    <div
                        class="flex items-center gap-2 font-semibold text-foreground"
                    >
                        <span
                            class="inline-flex size-5 items-center justify-center rounded-full bg-primary/10 text-[11px] font-bold text-primary"
                            >💡</span
                        >
                        <span>Công thức tính ER (%):</span>
                        <code
                            class="rounded border border-foreground/10 bg-background px-2 py-0.5 font-mono font-bold text-primary"
                        >
                            (Reaction + Comment + Share) ÷ Người xem (Reach) ×
                            100
                        </code>
                    </div>
                    <span class="text-[11px] text-muted-foreground">
                        *(Nếu bài viết chưa có dữ liệu Người xem, hệ thống sẽ tự
                        động tính theo Lượt xem)*
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="border-b border-foreground/10 bg-muted/70 font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="p-3.5">Nội dung bài đăng</th>
                                <th class="p-3.5 text-center">Nền tảng</th>
                                <th class="p-3.5 text-right">
                                    Người xem (Reach)
                                </th>
                                <th class="p-3.5 text-right">
                                    Lượt xem (Views)
                                </th>
                                <th class="p-3.5 text-right">Reaction</th>
                                <th class="p-3.5 text-right">Comment</th>
                                <th class="p-3.5 text-right">Share</th>
                                <th class="p-3.5 text-right">ER (%)</th>
                                <th class="p-3.5 text-right">Ngày đăng</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-foreground/10">
                            <tr v-if="safePosts.data.length === 0">
                                <td
                                    colspan="9"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    Không tìm thấy bài viết nào phù hợp với bộ
                                    lọc hiện tại.
                                </td>
                            </tr>
                            <tr
                                v-for="post in safePosts.data"
                                :key="post.id"
                                class="cursor-pointer transition-colors hover:bg-muted/30"
                                @click="openPreview(post)"
                            >
                                <td class="max-w-[280px] p-3.5">
                                    <div class="flex items-start gap-2">
                                        <Badge
                                            v-if="post.is_ceo_content"
                                            class="shrink-0 border-amber-300 bg-amber-100 text-[10px] text-amber-900 dark:bg-amber-950 dark:text-amber-300"
                                        >
                                            👑 CEO
                                        </Badge>
                                        <p
                                            class="line-clamp-2 leading-relaxed font-medium text-foreground"
                                        >
                                            {{
                                                post.excerpt ||
                                                post.content ||
                                                'Bài viết không có văn bản.'
                                            }}
                                        </p>
                                    </div>
                                </td>
                                <td
                                    class="p-3.5 text-center font-semibold text-muted-foreground capitalize"
                                >
                                    {{ post.platforms.join(', ') }}
                                </td>
                                <td
                                    class="p-3.5 text-right font-bold text-foreground tabular-nums"
                                >
                                    {{ formatNumber(post.reach || post.views) }}
                                </td>
                                <td
                                    class="p-3.5 text-right font-medium text-muted-foreground tabular-nums"
                                >
                                    {{
                                        formatNumber(
                                            post.impressions || post.views,
                                        )
                                    }}
                                </td>
                                <td
                                    class="p-3.5 text-right font-bold text-rose-600 tabular-nums"
                                >
                                    {{
                                        formatNumber(
                                            post.reactions || post.likes,
                                        )
                                    }}
                                </td>
                                <td
                                    class="p-3.5 text-right font-bold text-amber-600 tabular-nums"
                                >
                                    {{ formatNumber(post.comments) }}
                                </td>
                                <td
                                    class="p-3.5 text-right font-bold text-teal-600 tabular-nums"
                                >
                                    {{ formatNumber(post.shares) }}
                                </td>
                                <td
                                    class="p-3.5 text-right font-black text-primary tabular-nums"
                                >
                                    {{ post.engagement_rate }}%
                                </td>
                                <td
                                    class="p-3.5 text-right font-medium whitespace-nowrap text-muted-foreground"
                                >
                                    {{ date(post.published_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="safePosts.links.length > 3"
                    class="flex items-center justify-between border-t border-foreground/10 p-4"
                >
                    <span class="text-xs text-muted-foreground">
                        Hiển thị {{ safePosts.from || 0 }} -
                        {{ safePosts.to || 0 }} trong tổng số
                        {{ safePosts.total }} bài viết
                    </span>
                    <div class="flex gap-1">
                        <button
                            v-for="link in safePosts.links"
                            :key="link.label"
                            type="button"
                            :disabled="!link.url"
                            class="h-8 min-w-8 cursor-pointer rounded border px-2 text-xs font-bold transition-colors"
                            :class="[
                                link.active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-foreground/15 bg-background text-foreground hover:bg-muted',
                                !link.url
                                    ? 'cursor-not-allowed opacity-40'
                                    : '',
                            ]"
                            @click="visitPage(link.url)"
                        >
                            {{ pageLabel(link.label) }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- POST PREVIEW DIALOG -->
            <Dialog :open="previewOpen" @update:open="previewOpen = $event">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle
                            class="flex items-center gap-2 text-sm font-bold"
                        >
                            Chi tiết số liệu bài viết
                            <Badge
                                v-if="previewPost?.is_ceo_content"
                                class="border-amber-300 bg-amber-100 text-amber-900"
                            >
                                👑 Bài viết CEO
                            </Badge>
                        </DialogTitle>
                        <DialogDescription class="text-xs">
                            Ngày đăng:
                            {{ date(previewPost?.published_at || null) }}
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="previewPost" class="space-y-4 pt-2">
                        <p
                            class="max-h-40 overflow-y-auto rounded-lg bg-muted/40 p-3 text-xs leading-relaxed text-foreground"
                        >
                            {{ previewPost.content }}
                        </p>

                        <div class="grid grid-cols-2 gap-2 text-xs font-bold">
                            <div class="rounded-lg border bg-card p-2.5">
                                <span class="text-[10px] text-muted-foreground"
                                    >Người xem (Reach)</span
                                >
                                <div class="text-lg font-black text-foreground">
                                    {{ formatNumber(previewPost.reach || 0) }}
                                </div>
                            </div>
                            <div class="rounded-lg border bg-card p-2.5">
                                <span class="text-[10px] text-muted-foreground"
                                    >Lượt xem (Impressions)</span
                                >
                                <div class="text-lg font-black text-foreground">
                                    {{
                                        formatNumber(
                                            previewPost.impressions ||
                                                previewPost.views,
                                        )
                                    }}
                                </div>
                            </div>
                            <div class="rounded-lg border bg-card p-2.5">
                                <span class="text-[10px] text-muted-foreground"
                                    >Reaction (Cảm xúc)</span
                                >
                                <div class="text-lg font-black text-rose-600">
                                    {{
                                        formatNumber(
                                            previewPost.reactions ||
                                                previewPost.likes,
                                        )
                                    }}
                                </div>
                            </div>
                            <div class="rounded-lg border bg-card p-2.5">
                                <span class="text-[10px] text-muted-foreground"
                                    >Comment (Bình luận)</span
                                >
                                <div class="text-lg font-black text-amber-600">
                                    {{ formatNumber(previewPost.comments) }}
                                </div>
                            </div>
                            <div class="rounded-lg border bg-card p-2.5">
                                <span class="text-[10px] text-muted-foreground"
                                    >Share (Chia sẻ)</span
                                >
                                <div class="text-lg font-black text-teal-600">
                                    {{ formatNumber(previewPost.shares) }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border border-primary bg-primary/10 p-2.5"
                            >
                                <span class="text-[10px] text-primary"
                                    >Engagement Rate (%)</span
                                >
                                <div class="text-lg font-black text-primary">
                                    {{ previewPost.engagement_rate }}%
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="previewPost.external_url"
                            class="flex justify-end pt-2"
                        >
                            <a
                                :href="previewPost.external_url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:underline"
                            >
                                <IconExternalLink class="size-4" /> Xem bài viết
                                trên mạng xã hội
                            </a>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
