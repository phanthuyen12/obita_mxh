<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    IconArrowLeft,
    IconCalendarTime,
    IconCheck,
    IconClock,
    IconMail,
    IconMessages,
    IconRefresh,
    IconRobot,
    IconSearch,
    IconStar,
    IconTrendingUp,
    IconUsers,
    IconX,
} from '@tabler/icons-vue';
import {
    VisArea,
    VisAxis,
    VisGroupedBar,
    VisLine,
    VisXYContainer,
} from '@unovis/vue';
import { computed, ref } from 'vue';

import OmnichatCustomerDetailDialog from '@/components/omnichat/OmnichatCustomerDetailDialog.vue';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

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

interface Props {
    workspace: { id: string; name: string };
    user: {
        id: string;
        name: string;
        email: string;
        avatar_url: string | null;
    };
    user_summary: {
        total_assigned: number;
        total_messages_sent: number;
        resolved_count: number;
        resolution_rate: number;
        avg_response_minutes: number;
        avg_response_display?: string;
        csat_avg: number;
        ai_collaboration_count: number;
        active_customers_count: number;
    };
    productivity_trend: {
        date: string;
        display_date?: string;
        conversations: number;
        messages: number;
        resolved: number;
        avg_response_minutes: number;
    }[];
    hourly_distribution: {
        hour: string;
        messages: number;
    }[];
    assigned_customers: {
        data: Customer[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        per_page?: number;
        total: number;
    };
    filters: {
        search: string;
        status: string;
        date_range: string;
        from?: string;
        to?: string;
        is_single_day?: boolean;
        per_page?: number;
        page?: number;
    };
}

const props = defineProps<Props>();

const selectedDateRange = ref(props.filters.date_range || '7d');
const customFrom = ref(props.filters.from || '');
const customTo = ref(props.filters.to || '');
const isCustomDate = computed(() => selectedDateRange.value === 'custom');

const searchKeyword = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');
const perPageSelect = ref(props.filters.per_page || 10);

const selectedCustomer = ref<Customer | null>(null);
const customerDialogOpen = ref(false);

const quickDateRanges = [
    { value: 'today', label: 'Hôm nay' },
    { value: 'yesterday', label: 'Hôm qua' },
    { value: '7d', label: '7 ngày qua' },
    { value: '30d', label: '30 ngày qua' },
    { value: '90d', label: '90 ngày qua' },
    { value: 'custom', label: 'Tùy chỉnh khoảng ngày' },
];

const applyFilters = () => {
    router.get(
        `/omnichat/analytics/users/${props.user.id}`,
        {
            date_range: selectedDateRange.value,
            from: isCustomDate.value ? customFrom.value : undefined,
            to: isCustomDate.value ? customTo.value : undefined,
            search: searchKeyword.value || undefined,
            status:
                statusFilter.value !== 'all' ? statusFilter.value : undefined,
            per_page:
                perPageSelect.value !== 10 ? perPageSelect.value : undefined,
            page: 1,
        },
        { preserveState: true, replace: true },
    );
};

const setDateRange = (range: string) => {
    selectedDateRange.value = range;
    if (range !== 'custom') {
        applyFilters();
    }
};

let searchTimer: ReturnType<typeof setTimeout> | undefined;
const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
};

const resetAllFilters = () => {
    searchKeyword.value = '';
    statusFilter.value = 'all';
    selectedDateRange.value = '7d';
    perPageSelect.value = 10;
    applyFilters();
};

const openCustomerModal = (customer: Customer) => {
    selectedCustomer.value = customer;
    customerDialogOpen.value = true;
};

// Trend chart accessors
const x = (_d: any, i: number) => i;
const yConv = (d: any) => d.conversations;
const yMsg = (d: any) => d.messages;
const xTickFormat = (i: number) => {
    const item = props.productivity_trend[i];
    return item?.display_date || item?.date || '';
};

// Hourly chart accessors
const xHour = (_d: any, i: number) => i;
const yHour = (d: any) => d.messages;
const xHourTickFormat = (i: number) => props.hourly_distribution[i]?.hour || '';

const totalTrendConversations = computed(() =>
    props.productivity_trend.reduce((acc, curr) => acc + curr.conversations, 0),
);
const totalTrendMessages = computed(() =>
    props.productivity_trend.reduce((acc, curr) => acc + curr.messages, 0),
);

const getLeadBadge = (status: string) => {
    switch (status) {
        case 'hot':
            return {
                label: '🔥 Hot Lead',
                class: 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300',
            };
        case 'warm':
            return {
                label: '⚡ Quan tâm',
                class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
            };
        case 'converted':
            return {
                label: '✅ Đã chốt',
                class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
            };
        default:
            return {
                label: '❄️ Lạnh / Hỏi',
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
            return { label: 'Đã xử lý', class: 'bg-emerald-500 text-white' };
        default:
            return { label: 'Đã đóng', class: 'bg-gray-500 text-white' };
    }
};

const getAiBadge = (status: string) => {
    switch (status) {
        case 'active':
            return {
                label: '🤖 AI đang trực',
                class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
            };
        case 'handed_off':
            return {
                label: '🤝 AI bàn giao',
                class: 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300',
            };
        case 'human_only':
            return {
                label: '👤 Nhân viên trực',
                class: 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
            };
        default:
            return {
                label: '⚪ Chưa bật AI',
                class: 'bg-muted text-muted-foreground',
            };
    }
};

const visitPagination = (url: string | null) => {
    if (url) {
        router.visit(url, { preserveScroll: true, preserveState: true });
    }
};
</script>

<template>
    <Head :title="`Thống kê nhân viên: ${user.name}`" />

    <AppLayout>
        <div
            class="mx-auto flex h-full w-full max-w-7xl flex-col gap-6 px-6 py-8"
        >
            <!-- Back navigation & Breadcrumb -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link href="/omnichat/analytics">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="gap-1.5 text-xs font-semibold"
                    >
                        <IconArrowLeft class="size-4" />
                        Quay lại Omnichat Analytics
                    </Button>
                </Link>

                <div class="text-xs text-muted-foreground">
                    Workspace:
                    <span class="font-bold text-foreground">{{
                        workspace.name
                    }}</span>
                    • User ID: <span class="font-mono">{{ user.id }}</span>
                </div>
            </div>

            <!-- Profile Banner & Time Filter -->
            <div
                class="space-y-4 rounded-2xl border-2 border-foreground bg-card p-6 shadow-2xs"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-4 border-b border-foreground/10 pb-5"
                >
                    <div class="flex items-center gap-4">
                        <Avatar
                            :src="user.avatar_url"
                            :name="user.name"
                            class="size-16 rounded-2xl border-2 border-foreground shadow-xs"
                        />
                        <div class="space-y-1">
                            <div class="flex items-center gap-2.5">
                                <h2 class="text-xl font-black text-foreground">
                                    {{ user.name }}
                                </h2>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                                >
                                    <span
                                        class="size-2 rounded-full bg-emerald-500"
                                    ></span>
                                    Nhân viên hỗ trợ
                                </span>
                            </div>
                            <div
                                class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground"
                            >
                                <span class="flex items-center gap-1">
                                    <IconMail class="size-3.5" />
                                    {{ user.email }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- CSAT Score Card -->
                    <div class="flex items-center gap-3">
                        <div
                            class="rounded-xl border border-amber-300/40 bg-amber-50 px-4 py-2.5 text-right dark:bg-amber-950/40"
                        >
                            <div
                                class="text-[11px] font-semibold text-amber-800 dark:text-amber-300"
                            >
                                Đánh giá hài lòng (CSAT)
                            </div>
                            <div
                                class="flex items-center justify-end gap-1 text-lg font-black text-amber-600 dark:text-amber-400"
                            >
                                <IconStar
                                    class="size-5 fill-amber-500 text-amber-500"
                                />
                                <span>{{ user_summary.csat_avg }} / 5.0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TIME FILTER BAR -->
                <div
                    class="flex flex-wrap items-center justify-between gap-3 pt-1"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="flex items-center gap-1 text-xs font-bold text-muted-foreground"
                        >
                            <IconCalendarTime class="size-4 text-primary" />
                            Bộ lọc thời gian:
                        </span>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="r in quickDateRanges"
                                :key="r.value"
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-bold transition-colors"
                                :class="[
                                    selectedDateRange === r.value
                                        ? 'bg-foreground text-background shadow-xs'
                                        : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                ]"
                                @click="setDateRange(r.value)"
                            >
                                {{ r.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Custom Date Range Inputs -->
                    <div
                        v-if="isCustomDate"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <div class="flex items-center gap-1.5">
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                                >Từ:</span
                            >
                            <Input
                                v-model="customFrom"
                                type="date"
                                class="h-8 text-xs font-medium"
                            />
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                                >Đến:</span
                            >
                            <Input
                                v-model="customTo"
                                type="date"
                                class="h-8 text-xs font-medium"
                            />
                        </div>
                        <Button
                            size="sm"
                            class="h-8 text-xs font-bold"
                            @click="applyFilters"
                        >
                            Áp dụng lọc
                        </Button>
                    </div>

                    <!-- Date range info badge -->
                    <div
                        v-if="filters.from && filters.to"
                        class="text-xs text-muted-foreground"
                    >
                        Khoảng:
                        <span class="font-bold text-foreground">{{
                            filters.from
                        }}</span>
                        đến
                        <span class="font-bold text-foreground">{{
                            filters.to
                        }}</span>
                    </div>
                </div>
            </div>

            <!-- KPI Cards for this specific user -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div
                    class="space-y-1.5 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div
                        class="flex items-center justify-between text-muted-foreground"
                    >
                        <span class="text-xs font-semibold"
                            >Khách phụ trách</span
                        >
                        <IconUsers class="size-4 text-primary" />
                    </div>
                    <div class="text-2xl font-black text-foreground">
                        {{ user_summary.total_assigned }}
                    </div>
                    <div class="text-[11px] font-medium text-muted-foreground">
                        {{ user_summary.active_customers_count }} đang mở
                    </div>
                </div>

                <div
                    class="space-y-1.5 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div
                        class="flex items-center justify-between text-muted-foreground"
                    >
                        <span class="text-xs font-semibold">Tin nhắn gửi</span>
                        <IconMessages class="size-4 text-blue-500" />
                    </div>
                    <div class="text-2xl font-black text-foreground">
                        {{ user_summary.total_messages_sent }}
                    </div>
                    <div class="text-[11px] font-medium text-muted-foreground">
                        Trong khoảng thời gian này
                    </div>
                </div>

                <div
                    class="space-y-1.5 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div
                        class="flex items-center justify-between text-muted-foreground"
                    >
                        <span class="text-xs font-semibold">Đã giải quyết</span>
                        <IconCheck class="size-4 text-emerald-500" />
                    </div>
                    <div
                        class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                    >
                        {{ user_summary.resolved_count }}
                    </div>
                    <div
                        class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400"
                    >
                        Tỷ lệ {{ user_summary.resolution_rate }}%
                    </div>
                </div>

                <div
                    class="space-y-1.5 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div
                        class="flex items-center justify-between text-muted-foreground"
                    >
                        <span class="text-xs font-semibold"
                            >Tốc độ phản hồi</span
                        >
                        <IconClock class="size-4 text-amber-500" />
                    </div>
                    <div class="text-2xl font-black text-foreground">
                        {{
                            user_summary.avg_response_display ||
                            user_summary.avg_response_minutes + 'm'
                        }}
                    </div>
                    <div class="text-[11px] font-medium text-muted-foreground">
                        Thời gian trung bình
                    </div>
                </div>

                <div
                    class="space-y-1.5 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div
                        class="flex items-center justify-between text-muted-foreground"
                    >
                        <span class="text-xs font-semibold">AI bàn giao</span>
                        <IconRobot class="size-4 text-purple-500" />
                    </div>
                    <div
                        class="text-2xl font-black"
                        :class="
                            user_summary.ai_collaboration_count > 0
                                ? 'text-purple-600 dark:text-purple-400'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ user_summary.ai_collaboration_count }}
                    </div>
                    <div class="text-[11px] font-medium text-muted-foreground">
                        {{
                            user_summary.ai_collaboration_count > 0
                                ? 'Tiếp quản từ Bot'
                                : 'Chưa dùng AI'
                        }}
                    </div>
                </div>

                <div
                    class="space-y-1.5 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div
                        class="flex items-center justify-between text-muted-foreground"
                    >
                        <span class="text-xs font-semibold">Trạng thái</span>
                        <IconTrendingUp class="size-4 text-emerald-600" />
                    </div>
                    <div
                        class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                    >
                        {{
                            user_summary.total_messages_sent > 0
                                ? 'Tích cực'
                                : 'Chờ ca trực'
                        }}
                    </div>
                    <div class="text-[11px] font-medium text-muted-foreground">
                        {{
                            filters.date_range === 'today'
                                ? 'Hôm nay'
                                : 'Theo kỳ lọc'
                        }}
                    </div>
                </div>
            </div>

            <!-- Productivity Charts Grid -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- 1. Productivity Trend (Daily or Intervals for Today) -->
                <div
                    class="space-y-4 rounded-2xl border-2 border-foreground bg-card p-5 shadow-2xs"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-foreground/10 pb-3"
                    >
                        <div>
                            <h3 class="text-base font-bold text-foreground">
                                {{
                                    filters.is_single_day
                                        ? 'Năng suất xử lý theo khung giờ hôm nay'
                                        : 'Năng suất xử lý theo ngày'
                                }}
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    filters.is_single_day
                                        ? 'Khối lượng trao đổi trong các khoảng thời gian 2 tiếng.'
                                        : 'Số lượng hội thoại và tin nhắn phản hồi mỗi ngày.'
                                }}
                            </p>
                        </div>

                        <div
                            class="flex items-center gap-3 text-xs font-semibold"
                        >
                            <span class="flex items-center gap-1 text-primary">
                                <span
                                    class="size-2 rounded-full bg-primary"
                                ></span>
                                Hội thoại ({{ totalTrendConversations }})
                            </span>
                            <span
                                class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400"
                            >
                                <span
                                    class="size-2 rounded-full bg-emerald-500"
                                ></span>
                                Tin nhắn ({{ totalTrendMessages }})
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="productivity_trend.length > 0"
                        class="h-64 w-full pt-2"
                    >
                        <VisXYContainer
                            :data="productivity_trend"
                            class="h-full w-full"
                        >
                            <VisArea
                                :x="x"
                                :y="yConv"
                                color="var(--color-primary)"
                                :opacity="0.1"
                            />
                            <VisLine
                                :x="x"
                                :y="yConv"
                                color="var(--color-primary)"
                                :stroke-width="2.5"
                            />
                            <VisLine
                                :x="x"
                                :y="yMsg"
                                color="#10b981"
                                :stroke-width="2"
                            />
                            <VisAxis
                                type="x"
                                :tick-format="xTickFormat"
                                :grid-line="false"
                            />
                            <VisAxis type="y" :grid-line="true" />
                        </VisXYContainer>
                    </div>
                    <div
                        v-else
                        class="py-12 text-center text-xs text-muted-foreground"
                    >
                        Chưa có dữ liệu hội thoại trong khoảng thời gian này.
                    </div>
                </div>

                <!-- 2. Hourly Distribution (8:00 - 22:00) -->
                <div
                    class="space-y-4 rounded-2xl border-2 border-foreground bg-card p-5 shadow-2xs"
                >
                    <div class="border-b border-foreground/10 pb-3">
                        <h3 class="text-base font-bold text-foreground">
                            Phân bố lượng tin nhắn theo giờ (8:00 - 22:00)
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Khung giờ cao điểm nhân viên này hoạt động và phản
                            hồi tin nhắn cho khách hàng.
                        </p>
                    </div>

                    <div
                        v-if="hourly_distribution.length > 0"
                        class="h-64 w-full pt-2"
                    >
                        <VisXYContainer
                            :data="hourly_distribution"
                            class="h-full w-full"
                        >
                            <VisGroupedBar
                                :x="xHour"
                                :y="yHour"
                                color="var(--color-primary)"
                            />
                            <VisAxis
                                type="x"
                                :tick-format="xHourTickFormat"
                                :grid-line="false"
                            />
                            <VisAxis type="y" :grid-line="true" />
                        </VisXYContainer>
                    </div>
                    <div
                        v-else
                        class="py-12 text-center text-xs text-muted-foreground"
                    >
                        Chưa có dữ liệu tin nhắn gửi theo giờ.
                    </div>
                </div>
            </div>

            <!-- Assigned Customers Table with Full Search, Filter & Pagination -->
            <div
                class="space-y-4 rounded-2xl border-2 border-foreground bg-card p-5 shadow-2xs"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-foreground/10 pb-4"
                >
                    <div>
                        <h3 class="text-base font-bold text-foreground">
                            Danh sách khách hàng do {{ user.name }} phụ trách
                            ({{ assigned_customers.total }})
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Lọc và xem chi tiết khách hàng trực thuộc nhân viên
                            này.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Search Box -->
                        <div class="relative w-64">
                            <IconSearch
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="searchKeyword"
                                placeholder="Tìm theo tên, SĐT, email..."
                                class="h-8.5 pr-7 pl-8 text-xs"
                                @input="onSearchInput"
                            />
                            <button
                                v-if="searchKeyword"
                                type="button"
                                class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                @click="
                                    searchKeyword = '';
                                    applyFilters();
                                "
                            >
                                <IconX class="size-3" />
                            </button>
                        </div>

                        <!-- Status Filter -->
                        <select
                            v-model="statusFilter"
                            class="h-8.5 rounded-lg border border-foreground/20 bg-background px-2.5 text-xs font-semibold"
                            @change="applyFilters"
                        >
                            <option value="all">Tất cả trạng thái</option>
                            <option value="open">Đang mở (Open)</option>
                            <option value="pending">
                                Chờ phản hồi (Pending)
                            </option>
                            <option value="resolved">
                                Đã xử lý (Resolved)
                            </option>
                            <option value="closed">Đã đóng (Closed)</option>
                        </select>

                        <!-- Items Per Page -->
                        <select
                            v-model="perPageSelect"
                            class="h-8.5 rounded-lg border border-foreground/20 bg-background px-2.5 text-xs font-semibold"
                            @change="applyFilters"
                        >
                            <option :value="10">10 / trang</option>
                            <option :value="20">20 / trang</option>
                            <option :value="50">50 / trang</option>
                        </select>

                        <Button
                            v-if="searchKeyword || statusFilter !== 'all'"
                            variant="ghost"
                            size="sm"
                            class="h-8.5 text-xs text-muted-foreground"
                            @click="resetAllFilters"
                        >
                            <IconRefresh class="size-3.5" />
                            Đặt lại
                        </Button>
                    </div>
                </div>

                <!-- Customers Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-foreground/10 bg-muted/40 text-xs font-bold text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3">Khách hàng</th>
                                <th class="px-4 py-3">Kênh / Page</th>
                                <th class="px-4 py-3">Trạng thái</th>
                                <th class="px-4 py-3">Phân loại Lead</th>
                                <th class="px-4 py-3">Trạng thái AI</th>
                                <th class="px-4 py-3">Tin nhắn gần nhất</th>
                                <th class="px-4 py-3">Thời gian</th>
                                <th class="px-4 py-3 text-right">Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-foreground/10">
                            <tr
                                v-for="customer in assigned_customers.data"
                                :key="customer.id"
                                class="cursor-pointer transition-colors hover:bg-muted/40"
                                @click="openCustomerModal(customer)"
                            >
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <Avatar
                                            :src="customer.avatar_url"
                                            :name="customer.name"
                                            class="size-8 rounded-full border border-foreground/20"
                                        />
                                        <div>
                                            <div
                                                class="font-bold text-foreground"
                                            >
                                                {{ customer.name }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    customer.phone ||
                                                    customer.email ||
                                                    '—'
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td
                                    class="px-4 py-3.5 text-xs font-semibold text-foreground"
                                >
                                    {{ customer.page_name }}
                                    <div
                                        class="text-[11px] text-muted-foreground capitalize"
                                    >
                                        {{ customer.platform }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-xs font-semibold',
                                            getStatusBadge(customer.status)
                                                .class,
                                        ]"
                                    >
                                        {{
                                            getStatusBadge(customer.status)
                                                .label
                                        }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-xs font-semibold',
                                            getLeadBadge(customer.lead_status)
                                                .class,
                                        ]"
                                    >
                                        {{
                                            getLeadBadge(customer.lead_status)
                                                .label
                                        }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-xs font-semibold',
                                            getAiBadge(customer.ai_status)
                                                .class,
                                        ]"
                                    >
                                        {{
                                            getAiBadge(customer.ai_status).label
                                        }}
                                    </span>
                                </td>

                                <td
                                    class="max-w-xs truncate px-4 py-3.5 text-xs text-muted-foreground"
                                >
                                    {{ customer.last_message }}
                                </td>

                                <td
                                    class="px-4 py-3.5 text-xs text-muted-foreground"
                                >
                                    {{ customer.last_message_display }}
                                </td>

                                <td class="px-4 py-3.5 text-right">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 text-xs font-semibold"
                                        @click.stop="
                                            openCustomerModal(customer)
                                        "
                                    >
                                        Xem hội thoại
                                    </Button>
                                </td>
                            </tr>

                            <tr v-if="assigned_customers.data.length === 0">
                                <td
                                    colspan="8"
                                    class="py-12 text-center text-sm text-muted-foreground"
                                >
                                    Không tìm thấy khách hàng nào gán cho nhân
                                    viên này với bộ lọc hiện tại.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION CONTROLS -->
                <div
                    v-if="
                        assigned_customers.links &&
                        assigned_customers.links.length > 3
                    "
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-foreground/10 pt-4 text-xs"
                >
                    <span class="text-muted-foreground">
                        Hiển thị trang
                        <span class="font-bold text-foreground">{{
                            assigned_customers.current_page
                        }}</span>
                        /
                        <span class="font-bold text-foreground">{{
                            assigned_customers.last_page
                        }}</span>
                        (Tổng số {{ assigned_customers.total }} khách hàng)
                    </span>

                    <div class="flex flex-wrap items-center gap-1">
                        <Button
                            v-for="link in assigned_customers.links"
                            :key="link.label"
                            size="sm"
                            :variant="link.active ? 'default' : 'ghost'"
                            :disabled="!link.url"
                            class="h-8 min-w-8 px-2.5 text-xs font-semibold"
                            @click="visitPagination(link.url)"
                        >
                            <span v-html="link.label"></span>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Customer Details Modal -->
            <OmnichatCustomerDetailDialog
                :open="customerDialogOpen"
                :customer="selectedCustomer"
                @update:open="customerDialogOpen = $event"
            />
        </div>
    </AppLayout>
</template>
