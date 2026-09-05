<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    IconChartBar,
    IconCheck,
    IconClock,
    IconFlame,
    IconMessageCircle,
    IconMessages,
    IconRobot,
    IconStar,
    IconUserCheck,
    IconUsers,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import OmnichatCustomerDetailDialog from '@/components/omnichat/OmnichatCustomerDetailDialog.vue';
import OmnichatFilterBar from '@/components/omnichat/OmnichatFilterBar.vue';
import OmnichatPageChart from '@/components/omnichat/OmnichatPageChart.vue';
import OmnichatTimeTrendChart from '@/components/omnichat/OmnichatTimeTrendChart.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
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
    inbound?: number;
    outbound?: number;
    ai_handled_rate: number;
    resolved_rate: number;
    avg_response_seconds: number;
}

interface UserSummary {
    id: string;
    name: string;
    email: string;
    avatar_url: string | null;
    assigned_conversations: number;
    messages_sent: number;
    resolved_conversations: number;
    resolution_rate: number;
    avg_response_minutes: number;
    csat_avg: number;
    ai_collaboration_rate: number;
}

interface Props {
    workspace: { id: string; name: string };
    filters: {
        search?: string;
        channel_id?: string;
        status?: string;
        lead_status?: string;
        ai_mode?: string;
        assignee_id?: string;
        period?: string;
        page?: number;
        per_page?: number;
    };
    summary: {
        messages: number;
        conversations: number;
        contacts: number;
        inbound: number;
        outbound: number;
        ai_handled_rate?: number;
        ai_enabled?: boolean;
        resolved_rate?: number;
        avg_response_display?: string;
        hot_leads_count?: number;
        connected_pages_count?: number;
    };
    channels: PageMetric[];
    contacts: {
        data: Customer[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        per_page?: number;
        total: number;
    };
    sales: UserSummary[];
    trend: {
        date: string;
        display_date?: string;
        messages: number;
        conversations?: number;
        inbound: number;
        outbound: number;
    }[];
    channelsOptions: {
        id: string;
        name: string;
        platform: string;
        avatar_url: string | null;
        ai_enabled?: boolean;
    }[];
    salesOptions: { id: string; name: string; avatar_url: string | null }[];
}

const props = defineProps<Props>();

const activeTab = ref<'overview' | 'customers' | 'users'>('overview');
const selectedCustomer = ref<Customer | null>(null);
const customerDialogOpen = ref(false);

const localFilters = ref({
    search: props.filters.search || '',
    channel_id: props.filters.channel_id || 'all',
    status: props.filters.status || 'all',
    lead_status: props.filters.lead_status || 'all',
    ai_mode: props.filters.ai_mode || 'all',
    assignee_id: props.filters.assignee_id || 'all',
    period: props.filters.period || '7d',
    page: props.filters.page || 1,
    per_page: props.filters.per_page || 10,
});

const exportUrl = computed(() => {
    const q = new URLSearchParams();
    if (localFilters.value.search) q.set('search', localFilters.value.search);
    if (localFilters.value.channel_id !== 'all')
        q.set('channel_id', localFilters.value.channel_id);
    if (localFilters.value.status !== 'all')
        q.set('status', localFilters.value.status);
    if (localFilters.value.lead_status !== 'all')
        q.set('lead_status', localFilters.value.lead_status);
    if (localFilters.value.ai_mode !== 'all')
        q.set('ai_mode', localFilters.value.ai_mode);
    if (localFilters.value.assignee_id !== 'all')
        q.set('assignee_id', localFilters.value.assignee_id);
    if (localFilters.value.period) q.set('period', localFilters.value.period);
    return `/omnichat/analytics/export?${q.toString()}`;
});

const applyFilters = () => {
    router.get(
        '/omnichat/analytics',
        {
            search: localFilters.value.search || undefined,
            channel_id:
                localFilters.value.channel_id !== 'all'
                    ? localFilters.value.channel_id
                    : undefined,
            status:
                localFilters.value.status !== 'all'
                    ? localFilters.value.status
                    : undefined,
            lead_status:
                localFilters.value.lead_status !== 'all'
                    ? localFilters.value.lead_status
                    : undefined,
            ai_mode:
                localFilters.value.ai_mode !== 'all'
                    ? localFilters.value.ai_mode
                    : undefined,
            assignee_id:
                localFilters.value.assignee_id !== 'all'
                    ? localFilters.value.assignee_id
                    : undefined,
            period: localFilters.value.period,
            per_page:
                localFilters.value.per_page !== 10
                    ? localFilters.value.per_page
                    : undefined,
            page: 1,
        },
        { preserveState: true, replace: true },
    );
};

let searchTimer: ReturnType<typeof setTimeout> | undefined;
const updateFilter = (key: string, value: string | number) => {
    (localFilters.value as Record<string, any>)[key] = value;
    if (key === 'search') {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 350);
    } else {
        applyFilters();
    }
};

const resetFilters = () => {
    localFilters.value = {
        search: '',
        channel_id: 'all',
        status: 'all',
        lead_status: 'all',
        ai_mode: 'all',
        assignee_id: 'all',
        period: '7d',
        page: 1,
        per_page: 10,
    };
    applyFilters();
};

const onSelectPage = (pageId: string) => {
    updateFilter(
        'channel_id',
        pageId === localFilters.value.channel_id ? 'all' : pageId,
    );
    activeTab.value = 'customers';
};

const openCustomerModal = (customer: Customer) => {
    selectedCustomer.value = customer;
    customerDialogOpen.value = true;
};

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
    <Head title="Omnichat Analytics" />

    <AppLayout>
        <div
            class="mx-auto flex h-full w-full max-w-7xl flex-col gap-6 px-6 py-8"
        >
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <PageHeader
                    title="Omnichat Analytics"
                    description="Thống kê toàn diện tin nhắn đa kênh, hiệu suất chăm sóc khách hàng tự động bằng AI và năng suất nhân viên."
                />

                <div class="flex items-center gap-2">
                    <Link href="/settings/account/ai">
                        <Button variant="outline" class="gap-2 font-medium">
                            <IconRobot class="size-4 text-emerald-600" />
                            Cấu hình AI theo Page
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- KPI Cards Overview -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div
                    class="space-y-2 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div
                        class="flex items-center justify-between text-muted-foreground"
                    >
                        <span class="text-xs font-semibold"
                            >Tổng hội thoại</span
                        >
                        <IconMessages class="size-4 text-primary" />
                    </div>
                    <div class="text-2xl font-black text-foreground">
                        {{ summary.conversations }}
                    </div>
                    <div class="text-[11px] font-medium text-muted-foreground">
                        {{ summary.connected_pages_count || channels.length }}
                        Page kết nối
                    </div>
                </div>

                <div
                    class="space-y-2 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div
                        class="flex items-center justify-between text-muted-foreground"
                    >
                        <span class="text-xs font-semibold"
                            >Tin nhắn trao đổi</span
                        >
                        <IconMessageCircle class="size-4 text-blue-500" />
                    </div>
                    <div class="text-2xl font-black text-foreground">
                        {{ summary.messages }}
                    </div>
                    <div
                        class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400"
                    >
                        {{ summary.inbound }} khách /
                        {{ summary.outbound }} phản hồi
                    </div>
                </div>

                <div
                    class="space-y-2 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div
                        class="flex items-center justify-between text-muted-foreground"
                    >
                        <span class="text-xs font-semibold"
                            >Tỷ lệ AI xử lý</span
                        >
                        <IconRobot class="size-4 text-emerald-500" />
                    </div>
                    <div
                        class="text-2xl font-black"
                        :class="[
                            summary.ai_enabled &&
                            (summary.ai_handled_rate ?? 0) > 0
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-muted-foreground',
                        ]"
                    >
                        {{ summary.ai_handled_rate ?? 0 }}%
                    </div>
                    <div class="text-[11px] font-medium text-muted-foreground">
                        {{
                            summary.ai_enabled &&
                            (summary.ai_handled_rate ?? 0) > 0
                                ? 'Tự động trực tin'
                                : 'Chưa áp dụng AI'
                        }}
                    </div>
                </div>

                <div
                    class="space-y-2 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div
                        class="flex items-center justify-between text-muted-foreground"
                    >
                        <span class="text-xs font-semibold"
                            >Tỷ lệ giải quyết</span
                        >
                        <IconCheck class="size-4 text-emerald-600" />
                    </div>
                    <div class="text-2xl font-black text-foreground">
                        {{ summary.resolved_rate ?? 0 }}%
                    </div>
                    <div class="text-[11px] font-medium text-muted-foreground">
                        {{
                            (summary.resolved_rate ?? 0) > 0
                                ? 'Đã xử lý / Đóng'
                                : 'Chưa có hội thoại xử lý'
                        }}
                    </div>
                </div>

                <div
                    class="space-y-2 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
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
                        {{ summary.avg_response_display || '0s' }}
                    </div>
                    <div class="text-[11px] font-medium text-muted-foreground">
                        Thời gian trung bình
                    </div>
                </div>

                <div
                    class="space-y-2 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
                >
                    <div
                        class="flex items-center justify-between text-muted-foreground"
                    >
                        <span class="text-xs font-semibold"
                            >Lead tiềm năng</span
                        >
                        <IconFlame class="size-4 text-red-500" />
                    </div>
                    <div
                        class="text-2xl font-black text-red-600 dark:text-red-400"
                    >
                        {{ summary.hot_leads_count ?? 0 }}
                    </div>
                    <div class="text-[11px] font-medium text-muted-foreground">
                        Khách hàng tiềm năng
                    </div>
                </div>
            </div>

            <!-- Tab Switcher -->
            <div
                class="flex flex-wrap gap-2 border-b border-foreground/15 pb-2"
            >
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition-colors"
                    :class="[
                        activeTab === 'overview'
                            ? 'bg-foreground text-background shadow-xs'
                            : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                    @click="activeTab = 'overview'"
                >
                    <IconChartBar class="size-4" />
                    Tổng quan & Biểu đồ từng Page
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition-colors"
                    :class="[
                        activeTab === 'customers'
                            ? 'bg-foreground text-background shadow-xs'
                            : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                    @click="activeTab = 'customers'"
                >
                    <IconUsers class="size-4" />
                    Tìm kiếm & Thống kê Khách hàng ({{ contacts.total }})
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold transition-colors"
                    :class="[
                        activeTab === 'users'
                            ? 'bg-foreground text-background shadow-xs'
                            : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                    @click="activeTab = 'users'"
                >
                    <IconUserCheck class="size-4" />
                    Thống kê Nhân viên / Users ({{ sales.length }})
                </button>
            </div>

            <!-- Filter Bar Component -->
            <OmnichatFilterBar
                :filters="localFilters"
                :connected-pages="channelsOptions"
                :workspace-users="salesOptions"
                :export-url="exportUrl"
                @update:filter="updateFilter"
                @reset="resetFilters"
            />

            <!-- TAB 1: OVERVIEW & CHARTS PER PAGE -->
            <div v-if="activeTab === 'overview'" class="space-y-6">
                <!-- Page Metrics Grid & Charts -->
                <OmnichatPageChart
                    :page-metrics="channels"
                    :selected-page-id="localFilters.channel_id"
                    @select-page="onSelectPage"
                />

                <!-- Time Trend Chart -->
                <OmnichatTimeTrendChart :trends="trend" />
            </div>

            <!-- TAB 2: CUSTOMERS LIST & DETAIL SEARCH -->
            <div v-if="activeTab === 'customers'" class="space-y-4">
                <div
                    class="overflow-hidden rounded-2xl border-2 border-foreground bg-card shadow-2xs"
                >
                    <div
                        class="flex items-center justify-between border-b border-foreground/10 px-5 py-3.5"
                    >
                        <h3 class="text-sm font-bold text-foreground">
                            Danh sách khách hàng tương tác ({{
                                contacts.total
                            }})
                        </h3>
                        <span class="text-xs text-muted-foreground">
                            Click vào hàng để xem lịch sử tương tác chi tiết
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="border-b border-foreground/10 bg-muted/40 text-xs font-bold text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-4 py-3">Khách hàng</th>
                                    <th class="px-4 py-3">Page / Kênh</th>
                                    <th class="px-4 py-3">Trạng thái</th>
                                    <th class="px-4 py-3">Phân loại</th>
                                    <th class="px-4 py-3">Chế độ AI</th>
                                    <th class="px-4 py-3">
                                        Nhân viên phụ trách
                                    </th>
                                    <th class="px-4 py-3">Thời gian</th>
                                    <th class="px-4 py-3 text-right">
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-foreground/10">
                                <tr
                                    v-for="customer in contacts.data"
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
                                            class="text-[11px] font-normal text-muted-foreground capitalize"
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
                                                getLeadBadge(
                                                    customer.lead_status,
                                                ).class,
                                            ]"
                                        >
                                            {{
                                                getLeadBadge(
                                                    customer.lead_status,
                                                ).label
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
                                                getAiBadge(customer.ai_status)
                                                    .label
                                            }}
                                        </span>
                                    </td>

                                    <td
                                        class="px-4 py-3.5 text-xs font-medium text-foreground"
                                    >
                                        <div
                                            v-if="customer.assigned_user"
                                            class="flex items-center gap-1.5"
                                        >
                                            <Avatar
                                                :src="
                                                    customer.assigned_user
                                                        .avatar_url
                                                "
                                                :name="
                                                    customer.assigned_user.name
                                                "
                                                class="size-5 rounded-full border"
                                            />
                                            <span>{{
                                                customer.assigned_user.name
                                            }}</span>
                                        </div>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
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
                                            Chi tiết
                                        </Button>
                                    </td>
                                </tr>

                                <tr v-if="contacts.data.length === 0">
                                    <td
                                        colspan="8"
                                        class="py-12 text-center text-sm text-muted-foreground"
                                    >
                                        Không tìm thấy khách hàng nào khớp với
                                        điều kiện lọc hiện tại.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links -->
                    <div
                        v-if="contacts.links && contacts.links.length > 3"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-foreground/10 px-4 py-3 text-xs"
                    >
                        <span class="text-muted-foreground">
                            Hiển thị trang
                            <span class="font-bold text-foreground">{{
                                contacts.current_page
                            }}</span>
                            /
                            <span class="font-bold text-foreground">{{
                                contacts.last_page
                            }}</span>
                            (Tổng số {{ contacts.total }} khách hàng)
                        </span>

                        <div class="flex flex-wrap items-center gap-1">
                            <Button
                                v-for="link in contacts.links"
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
            </div>

            <!-- TAB 3: USERS / STAFF PERFORMANCE SUMMARY -->
            <div v-if="activeTab === 'users'" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="u in sales"
                        :key="u.id"
                        class="group flex flex-col justify-between rounded-2xl border-2 border-foreground/15 bg-card p-5 shadow-2xs transition-all hover:border-foreground hover:shadow-xs"
                    >
                        <div class="space-y-4">
                            <!-- User Header -->
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-3">
                                    <Avatar
                                        :src="u.avatar_url"
                                        :name="u.name"
                                        class="size-11 rounded-full border-2 border-foreground"
                                    />
                                    <div>
                                        <h4 class="font-bold text-foreground">
                                            {{ u.name }}
                                        </h4>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ u.email || 'Nhân viên hỗ trợ' }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center gap-1 rounded-md bg-amber-500/10 px-2 py-1 text-xs font-bold text-amber-600 dark:text-amber-400"
                                >
                                    <IconStar class="size-3.5 fill-amber-500" />
                                    <span>{{ u.csat_avg || 5.0 }}/5</span>
                                </div>
                            </div>

                            <!-- Performance Stats Grid -->
                            <div
                                class="grid grid-cols-3 gap-2 rounded-xl border border-foreground/10 bg-muted/30 p-3 text-center"
                            >
                                <div>
                                    <div
                                        class="text-[11px] font-semibold text-muted-foreground"
                                    >
                                        Phụ trách
                                    </div>
                                    <div
                                        class="text-sm font-black text-foreground"
                                    >
                                        {{ u.assigned_conversations }}
                                    </div>
                                </div>
                                <div class="border-x border-foreground/10 px-1">
                                    <div
                                        class="text-[11px] font-semibold text-muted-foreground"
                                    >
                                        Giải quyết
                                    </div>
                                    <div
                                        class="text-sm font-black text-emerald-600 dark:text-emerald-400"
                                    >
                                        {{ u.resolution_rate }}%
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-[11px] font-semibold text-muted-foreground"
                                    >
                                        Tốc độ
                                    </div>
                                    <div
                                        class="text-sm font-black text-foreground"
                                    >
                                        {{ u.avg_response_minutes }}m
                                    </div>
                                </div>
                            </div>

                            <!-- AI collaboration badge -->
                            <div
                                class="flex items-center justify-between rounded-lg border border-foreground/10 bg-background/60 p-2.5 text-xs"
                            >
                                <span
                                    class="flex items-center gap-1.5 text-muted-foreground"
                                >
                                    <IconRobot class="size-4 text-primary" />
                                    Tỷ lệ phối hợp AI
                                </span>
                                <span
                                    class="font-bold"
                                    :class="
                                        u.ai_collaboration_rate > 0
                                            ? 'text-primary'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        u.ai_collaboration_rate > 0
                                            ? `${u.ai_collaboration_rate}%`
                                            : 'Chưa dùng AI'
                                    }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 border-t border-foreground/10 pt-3">
                            <Link
                                :href="`/omnichat/analytics/users/${u.id}?date_range=today`"
                                class="block w-full"
                            >
                                <Button
                                    class="w-full gap-1.5 font-bold"
                                    size="sm"
                                >
                                    Xem thống kê chi tiết user &rarr;
                                </Button>
                            </Link>
                        </div>
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
