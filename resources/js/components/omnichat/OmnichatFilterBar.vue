<script setup lang="ts">
import {
    IconCalendar,
    IconDownload,
    IconRefresh,
    IconSearch,
    IconX,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface PageOption {
    id: string;
    name: string;
    platform: string;
    avatar_url: string | null;
    ai_enabled?: boolean;
}

interface UserOption {
    id: string;
    name: string;
    avatar_url: string | null;
}

const props = defineProps<{
    filters: {
        search: string;
        channel_id: string;
        status: string;
        lead_status: string;
        ai_mode: string;
        assignee_id: string;
        period: string;
        from?: string;
        to?: string;
    };
    connectedPages: PageOption[];
    workspaceUsers: UserOption[];
    exportUrl: string;
}>();

const emit = defineEmits<{
    (e: 'update:filter', key: string, value: string): void;
    (e: 'apply-custom-dates', from: string, to: string): void;
    (e: 'reset'): void;
}>();

const customFrom = ref(
    props.filters.from ? props.filters.from.split(' ')[0] : '',
);
const customTo = ref(props.filters.to ? props.filters.to.split(' ')[0] : '');
const showCustomDates = computed(() => props.filters.period === 'custom');

watch(
    () => props.filters.period,
    (newVal) => {
        if (newVal === 'custom' && (!customFrom.value || !customTo.value)) {
            const today = new Date().toISOString().split('T')[0];
            const past = new Date(Date.now() - 7 * 86400000)
                .toISOString()
                .split('T')[0];
            customFrom.value = customFrom.value || past;
            customTo.value = customTo.value || today;
        }
    },
);

const onPeriodChange = (val: string) => {
    emit('update:filter', 'period', val);
};

const applyCustomDates = () => {
    if (customFrom.value && customTo.value) {
        emit('apply-custom-dates', customFrom.value, customTo.value);
    }
};

const hasActiveFilters = computed(() => {
    return (
        props.filters.search !== '' ||
        props.filters.channel_id !== 'all' ||
        props.filters.status !== 'all' ||
        props.filters.lead_status !== 'all' ||
        props.filters.ai_mode !== 'all' ||
        props.filters.assignee_id !== 'all' ||
        props.filters.period !== '7d'
    );
});

const onSearchInput = (e: Event) => {
    const val = (e.target as HTMLInputElement).value;
    emit('update:filter', 'search', val);
};
</script>

<template>
    <div
        class="space-y-3 rounded-2xl border-2 border-foreground bg-card p-4 shadow-2xs"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-1 items-center gap-2">
                <div class="relative w-full max-w-md">
                    <IconSearch
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        :model-value="filters.search"
                        placeholder="Tìm kiếm khách hàng theo tên, SĐT, email, nội dung chat..."
                        class="h-9 pr-8 pl-9 text-sm"
                        @input="onSearchInput"
                    />
                    <button
                        v-if="filters.search"
                        type="button"
                        class="absolute top-1/2 right-2.5 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        @click="emit('update:filter', 'search', '')"
                    >
                        <IconX class="size-3.5" />
                    </button>
                </div>

                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    size="sm"
                    class="h-9 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                    @click="emit('reset')"
                >
                    <IconRefresh class="size-3.5" />
                    Đặt lại lọc
                </Button>
            </div>

            <div class="flex items-center gap-2">
                <a :href="exportUrl" target="_blank">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 gap-1.5 border-foreground/20 font-medium"
                    >
                        <IconDownload class="size-4" />
                        Xuất báo cáo
                    </Button>
                </a>
            </div>
        </div>

        <!-- Filter Selects Grid -->
        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-5">
            <!-- Lọc theo Page / Kênh -->
            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-muted-foreground"
                    >Kênh / Page</label
                >
                <select
                    :value="filters.channel_id"
                    class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs font-medium text-foreground focus:ring-1 focus:ring-ring focus:outline-hidden"
                    @change="
                        emit(
                            'update:filter',
                            'channel_id',
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="all">Tất cả Kênh / Page</option>
                    <option
                        v-for="page in connectedPages"
                        :key="page.id"
                        :value="page.id"
                    >
                        {{ page.name }} ({{ page.platform }})
                    </option>
                </select>
            </div>

            <!-- Lọc theo Trạng thái hội thoại -->
            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-muted-foreground"
                    >Trạng thái chat</label
                >
                <select
                    :value="filters.status"
                    class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs font-medium text-foreground focus:ring-1 focus:ring-ring focus:outline-hidden"
                    @change="
                        emit(
                            'update:filter',
                            'status',
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="all">Tất cả trạng thái</option>
                    <option value="open">Đang mở (Open)</option>
                    <option value="pending">Chờ phản hồi (Pending)</option>
                    <option value="resolved">Đã giải quyết (Resolved)</option>
                    <option value="closed">Đã đóng (Closed)</option>
                </select>
            </div>

            <!-- Lọc theo Phân loại Lead -->
            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-muted-foreground"
                    >Phân loại Lead</label
                >
                <select
                    :value="filters.lead_status"
                    class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs font-medium text-foreground focus:ring-1 focus:ring-ring focus:outline-hidden"
                    @change="
                        emit(
                            'update:filter',
                            'lead_status',
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="all">Tất cả phân loại</option>
                    <option value="hot">🔥 Nóng / Tiềm năng cao</option>
                    <option value="warm">⚡ Ấm / Quan tâm</option>
                    <option value="cold">❄️ Lạnh / Hỏi thông tin</option>
                    <option value="converted">✅ Đã chốt đơn</option>
                </select>
            </div>

            <!-- Lọc theo Chế độ AI -->
            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-muted-foreground"
                    >Chế độ chăm sóc</label
                >
                <select
                    :value="filters.ai_mode"
                    class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs font-medium text-foreground focus:ring-1 focus:ring-ring focus:outline-hidden"
                    @change="
                        emit(
                            'update:filter',
                            'ai_mode',
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="all">Tất cả chế độ</option>
                    <option value="ai">🤖 AI tự động trực</option>
                    <option value="human">👤 Nhân viên trực tiếp</option>
                    <option value="disabled">⚪ Chưa bật AI</option>
                </select>
            </div>

            <!-- Khoảng thời gian -->
            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-muted-foreground"
                    >Thời gian</label
                >
                <select
                    :value="filters.period"
                    class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs font-medium text-foreground focus:ring-1 focus:ring-ring focus:outline-hidden"
                    @change="
                        onPeriodChange(
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="today">Hôm nay</option>
                    <option value="yesterday">Hôm qua</option>
                    <option value="7d">7 ngày qua</option>
                    <option value="30d">30 ngày qua</option>
                    <option value="90d">90 ngày qua</option>
                    <option value="custom">Tùy chỉnh khoảng ngày</option>
                </select>
            </div>
        </div>

        <!-- Custom Date Range Row if selected -->
        <div
            v-if="showCustomDates"
            class="flex flex-wrap items-center gap-2 rounded-xl border border-foreground/15 bg-muted/30 p-2.5"
        >
            <span
                class="flex items-center gap-1 text-xs font-bold text-muted-foreground"
            >
                <IconCalendar class="size-4 text-primary" />
                Khoảng thời gian:
            </span>
            <div class="flex items-center gap-2">
                <Input
                    v-model="customFrom"
                    type="date"
                    class="h-8 text-xs font-medium"
                />
                <span class="text-xs text-muted-foreground">đến</span>
                <Input
                    v-model="customTo"
                    type="date"
                    class="h-8 text-xs font-medium"
                />
                <Button
                    size="sm"
                    class="h-8 text-xs font-bold"
                    @click="applyCustomDates"
                >
                    Áp dụng ngày
                </Button>
            </div>
        </div>
    </div>
</template>
