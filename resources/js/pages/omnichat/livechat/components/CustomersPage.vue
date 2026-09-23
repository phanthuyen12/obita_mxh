<script setup lang="ts">
import { IonIcon } from '@ionic/vue';
import axios from 'axios';
import {
    callOutline,
    chatbubbleEllipsesOutline,
    closeOutline,
    personAddOutline,
    searchOutline,
} from 'ionicons/icons';
import { computed, onMounted, ref, watch } from 'vue';

import dayjs from '@/dayjs';

import CustomerProfileModal from './CustomerProfileModal.vue';

export interface Customer {
    id: string;
    name: string;
    phone: string;
    email?: string;
    avatarText: string;
    avatarBg: string;
    tag: 'VIP' | 'Đã mua' | 'Tiềm năng' | 'Chưa liên hệ';
    tags?: string[];
    tagIds?: string[];
    latestConversationId?: string | null;
    totalSpent: string;
    lastActive: string;
    notes?: string;
}

type ContactPayload = {
    id: string;
    display_name: string;
    avatar_url: string | null;
    phone: string | null;
    email: string | null;
    notes: string | null;
    lead_stage: string | null;
    last_seen_at: string | null;
    phone_detected_at: string | null;
    conversation_count: number;
    latest_conversation_id: string | null;
    last_message_at: string | null;
    last_message_preview: string | null;
    provider: string | null;
    tags: Array<{ id: string; name: string; color: string | null }>;
};

const emit = defineEmits<{
    (e: 'chat-with', customer: Customer): void;
}>();

const selectedCustomerForProfile = ref<Customer | null>(null);

const searchQuery = ref('');
const selectedFilter = ref<
    'all' | 'has_phone' | 'vip' | 'bought' | 'potential'
>('all');

const filterOptions = [
    { label: 'Tất cả', value: 'all' },
    { label: 'Có SĐT', value: 'has_phone' },
    { label: 'VIP ⭐', value: 'vip' },
    { label: 'Đã mua', value: 'bought' },
    { label: 'Tiềm năng', value: 'potential' },
];

const AVATAR_COLORS = [
    '#0866ff',
    '#0088cc',
    '#0068ff',
    '#db2777',
    '#059669',
    '#78350f',
    '#0284c7',
    '#7c3aed',
];

const avatarColorFor = (id: string): string => {
    let hash = 0;
    for (let i = 0; i < id.length; i++) {
        hash = id.charCodeAt(i) + ((hash << 5) - hash);
    }
    return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
};

const relativeTime = (iso: string | null): string => {
    if (!iso) return 'Chưa hoạt động';
    return dayjs(iso).fromNow();
};

const STAGE_LABEL: Record<string, Customer['tag']> = {
    converted: 'Đã mua',
    qualified: 'Tiềm năng',
    contacted: 'Tiềm năng',
    new: 'Chưa liên hệ',
    lost: 'Chưa liên hệ',
};

const toCustomer = (contact: ContactPayload): Customer => {
    const name = contact.display_name || 'Khách hàng';
    const tagNames = contact.tags.map((t) => t.name);

    return {
        id: contact.id,
        name,
        phone: contact.phone ?? '',
        email: contact.email ?? undefined,
        avatarText: name.charAt(0).toUpperCase(),
        avatarBg: avatarColorFor(contact.id),
        tag:
            STAGE_LABEL[contact.lead_stage ?? 'new'] ??
            (tagNames.some((t) => t.startsWith('VIP'))
                ? 'VIP'
                : 'Chưa liên hệ'),
        tags: tagNames,
        tagIds: contact.tags.map((t) => t.id),
        latestConversationId: contact.latest_conversation_id,
        totalSpent:
            contact.conversation_count > 0
                ? `${contact.conversation_count} hội thoại`
                : '0 hội thoại',
        lastActive: relativeTime(
            contact.last_seen_at ?? contact.last_message_at,
        ),
        notes: contact.notes ?? undefined,
    };
};

const customers = ref<Customer[]>([]);
const isLoading = ref(false);
const isLoadingMore = ref(false);
const currentPage = ref(1);
const hasNextPage = ref(false);

const loadCustomers = async (): Promise<void> => {
    isLoading.value = true;
    currentPage.value = 1;
    try {
        const { data } = await axios.get('/omnichat/livechat/contacts', {
            params: {
                search: searchQuery.value,
                filter:
                    selectedFilter.value === 'all' ? '' : selectedFilter.value,
                page: currentPage.value,
            },
        });
        customers.value = (data.data as ContactPayload[]).map(toCustomer);
        hasNextPage.value = (
            data.meta as { has_next_page: boolean }
        ).has_next_page;
    } finally {
        isLoading.value = false;
    }
};

const loadMoreCustomers = async (): Promise<void> => {
    if (isLoadingMore.value || !hasNextPage.value) return;
    isLoadingMore.value = true;
    try {
        const { data } = await axios.get('/omnichat/livechat/contacts', {
            params: {
                search: searchQuery.value,
                filter:
                    selectedFilter.value === 'all' ? '' : selectedFilter.value,
                page: currentPage.value + 1,
            },
        });
        currentPage.value = (
            data.meta as { current_page: number }
        ).current_page;
        customers.value = [
            ...customers.value,
            ...(data.data as ContactPayload[]).map(toCustomer),
        ];
        hasNextPage.value = (
            data.meta as { has_next_page: boolean }
        ).has_next_page;
    } finally {
        isLoadingMore.value = false;
    }
};

let searchDebounce: ReturnType<typeof setTimeout> | undefined;
watch(searchQuery, () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(loadCustomers, 300);
});
watch(selectedFilter, loadCustomers);
onMounted(loadCustomers);

const handleUpdateCustomer = (updated: Customer) => {
    const index = customers.value.findIndex((c) => c.id === updated.id);
    if (index !== -1) {
        customers.value[index] = updated;
    }
    selectedCustomerForProfile.value = updated;
};

// Lọc khách hàng theo SĐT, Tên và Thẻ
const filteredCustomers = computed(() => {
    return customers.value.filter((c) => {
        // 1. Lọc theo search input
        const query = searchQuery.value.trim().toLowerCase();
        const matchQuery =
            !query ||
            c.name.toLowerCase().includes(query) ||
            c.phone.replace(/\s+/g, '').includes(query.replace(/\s+/g, '')) ||
            (c.notes && c.notes.toLowerCase().includes(query));

        // 2. Lọc theo filter tag
        let matchFilter = true;
        if (selectedFilter.value === 'has_phone') {
            matchFilter = !!c.phone && c.phone.trim().length > 0;
        } else if (selectedFilter.value === 'vip') {
            matchFilter = c.tag === 'VIP';
        } else if (selectedFilter.value === 'bought') {
            matchFilter = c.tag === 'Đã mua' || c.tag === 'VIP';
        } else if (selectedFilter.value === 'potential') {
            matchFilter = c.tag === 'Tiềm năng';
        }

        return matchQuery && matchFilter;
    });
});

const getTagColor = (tag: Customer['tag']) => {
    switch (tag) {
        case 'VIP':
            return { bg: '#3b2100', color: '#f59e0b', border: '#78350f' };
        case 'Đã mua':
            return { bg: '#062d1d', color: '#10b981', border: '#065f46' };
        case 'Tiềm năng':
            return { bg: '#1e1e38', color: '#818cf8', border: '#3730a3' };
        default:
            return { bg: '#27272a', color: '#9ca3af', border: '#3f3f46' };
    }
};
</script>

<template>
    <div class="customers-page">
        <!-- Top Sticky Header -->
        <div class="customers-sticky-top">
            <header class="customers-header">
                <div>
                    <h1 class="page-title">Khách hàng</h1>
                    <span class="customer-count"
                        >{{ filteredCustomers.length }} liên hệ</span
                    >
                </div>
                <button class="add-customer-btn" title="Thêm khách hàng">
                    <ion-icon :icon="personAddOutline"></ion-icon>
                </button>
            </header>

            <!-- Bộ lọc SĐT & Tìm kiếm -->
            <div class="search-box-wrap">
                <div class="search-inner">
                    <ion-icon
                        :icon="searchOutline"
                        class="search-icon"
                    ></ion-icon>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Tìm theo tên hoặc số điện thoại..."
                        class="customer-search-input"
                    />
                    <button
                        v-if="searchQuery"
                        class="clear-btn"
                        @click="searchQuery = ''"
                    >
                        <ion-icon :icon="closeOutline"></ion-icon>
                    </button>
                </div>
            </div>

            <!-- Bộ lọc phân loại nhanh -->
            <div class="filter-pills-row">
                <div
                    v-for="opt in filterOptions"
                    :key="opt.value"
                    :class="[
                        'filter-pill',
                        { active: selectedFilter === opt.value },
                    ]"
                    @click="selectedFilter = opt.value as any"
                >
                    {{ opt.label }}
                </div>
            </div>
        </div>

        <!-- Danh sách khách hàng cuộn -->
        <main class="customer-list-scroll">
            <div
                v-for="customer in filteredCustomers"
                :key="customer.id"
                class="customer-card"
                @click="selectedCustomerForProfile = customer"
            >
                <!-- Avatar -->
                <div
                    class="customer-avatar"
                    :style="{ backgroundColor: customer.avatarBg }"
                >
                    <span>{{ customer.avatarText }}</span>
                </div>

                <!-- Thông tin khách hàng -->
                <div class="customer-info-col">
                    <div class="customer-name-row">
                        <span class="customer-name">{{ customer.name }}</span>
                        <span
                            class="customer-tag"
                            :style="{
                                backgroundColor: getTagColor(customer.tag).bg,
                                color: getTagColor(customer.tag).color,
                                borderColor: getTagColor(customer.tag).border,
                            }"
                        >
                            {{ customer.tag }}
                        </span>
                    </div>

                    <!-- SĐT khách hàng -->
                    <div class="customer-phone-row">
                        <span class="phone-number"
                            >📞 {{ customer.phone }}</span
                        >
                        <span class="spent-value">{{
                            customer.totalSpent
                        }}</span>
                    </div>

                    <!-- Hiển thị các Tags phân loại của khách hàng -->
                    <div
                        v-if="customer.tags && customer.tags.length > 0"
                        class="customer-tags-preview"
                    >
                        <span
                            v-for="t in customer.tags"
                            :key="t"
                            class="mini-preview-tag"
                        >
                            🏷️ {{ t }}
                        </span>
                    </div>

                    <!-- Ghi chú nếu có -->
                    <p v-if="customer.notes" class="customer-notes">
                        {{ customer.notes }}
                    </p>
                </div>

                <!-- Action Nhanh: Chat hoặc Gọi -->
                <div class="customer-actions" @click.stop>
                    <a
                        :href="'tel:' + customer.phone"
                        class="action-btn call-action"
                        title="Gọi điện"
                    >
                        <ion-icon :icon="callOutline"></ion-icon>
                    </a>
                    <button
                        class="action-btn chat-action"
                        title="Nhắn tin"
                        @click="emit('chat-with', customer)"
                    >
                        <ion-icon :icon="chatbubbleEllipsesOutline"></ion-icon>
                    </button>
                </div>
            </div>

            <div v-if="isLoading" class="empty-customers">
                <p>Đang tải khách hàng...</p>
            </div>

            <button
                v-else-if="hasNextPage"
                class="load-more-btn"
                :disabled="isLoadingMore"
                @click="loadMoreCustomers"
            >
                {{ isLoadingMore ? 'Đang tải...' : 'Tải thêm khách hàng' }}
            </button>

            <div
                v-else-if="filteredCustomers.length === 0"
                class="empty-customers"
            >
                <p>Không tìm thấy khách hàng nào khớp với tìm kiếm</p>
            </div>
        </main>

        <!-- Modal Xem & Gắn Tag Hồ Sơ Khách Hàng -->
        <CustomerProfileModal
            v-if="selectedCustomerForProfile"
            :customer="selectedCustomerForProfile"
            @close="selectedCustomerForProfile = null"
            @update-customer="handleUpdateCustomer"
            @chat-with="
                (c) => {
                    selectedCustomerForProfile = null;
                    emit('chat-with', c);
                }
            "
        />
    </div>
</template>

<style scoped>
.customers-page {
    flex: 1;
    display: flex;
    flex-direction: column;
    background-color: #000000;
    overflow: hidden;
    height: 100%;
}

.customers-sticky-top {
    background-color: #000000;
    border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
    flex-shrink: 0;
    padding-top: max(env(safe-area-inset-top, 0px), 44px);
}

.customers-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 16px 4px;
}

.page-title {
    font-size: 22px;
    font-weight: 800;
    color: #ffffff;
    margin: 0;
    letter-spacing: -0.4px;
}

.customer-count {
    font-size: 12px;
    color: #8e8e93;
}

.add-customer-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #1c1c1e;
    border: none;
    color: #2a8bf2;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform 0.15s;
}
.add-customer-btn:active {
    transform: scale(0.92);
}

.search-box-wrap {
    padding: 6px 14px 8px;
}

.search-inner {
    background-color: #1c1c1e;
    height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    padding: 0 10px;
    gap: 8px;
}

.search-icon {
    font-size: 17px;
    color: #8e8e93;
}

.customer-search-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: #ffffff;
    font-size: 14.5px;
}
.customer-search-input::placeholder {
    color: #8e8e93;
}

.clear-btn {
    background: transparent;
    border: none;
    color: #8e8e93;
    font-size: 18px;
    cursor: pointer;
}

.filter-pills-row {
    display: flex;
    gap: 8px;
    padding: 0 14px 10px;
    overflow-x: auto;
    scrollbar-width: none;
}
.filter-pills-row::-webkit-scrollbar {
    display: none;
}

.filter-pill {
    background: #1c1c1e;
    color: #8e8e93;
    font-size: 13.5px;
    font-weight: 600;
    padding: 5px 14px;
    border-radius: 18px;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s;
}

.filter-pill.active {
    background: #ffffff;
    color: #000000;
}

/* Danh sách khách hàng */
.customer-list-scroll {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
    padding: 10px 14px 140px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    overscroll-behavior-y: contain;
    -webkit-overflow-scrolling: touch;
}

.customer-card {
    background: #121418;
    border: 0.5px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: background 0.15s;
}
.customer-card:active {
    background: #1c1e24;
}

.customer-avatar {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 700;
    color: #ffffff;
    flex-shrink: 0;
}

.customer-info-col {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.customer-name-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
}

.customer-name {
    font-size: 15px;
    font-weight: 600;
    color: #ffffff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.customer-tag {
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 8px;
    border: 0.5px solid;
    white-space: nowrap;
}

.customer-phone-row {
    display: flex;
    justify-content: space-between;
    font-size: 12.5px;
}

.phone-number {
    color: #38bdf8;
    font-family: monospace;
}

.spent-value {
    color: #10b981;
    font-weight: 600;
}

.customer-tags-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin: 2px 0;
}

.mini-preview-tag {
    background: rgba(56, 189, 248, 0.12);
    color: #38bdf8;
    border: 0.5px solid rgba(56, 189, 248, 0.25);
    font-size: 10.5px;
    font-weight: 600;
    padding: 1px 6px;
    border-radius: 6px;
    white-space: nowrap;
}

.customer-notes {
    font-size: 11.5px;
    color: #8e8e93;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.customer-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.action-btn {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    font-size: 16px;
    cursor: pointer;
    text-decoration: none;
}

.call-action {
    background: #064e3b;
    color: #34d399;
}

.chat-action {
    background: #1e3a8a;
    color: #60a5fa;
}

.empty-customers {
    text-align: center;
    color: #8e8e93;
    padding: 40px 10px;
    font-size: 14px;
}

.load-more-btn {
    background: #1c1c1e;
    border: 0.5px solid rgba(255, 255, 255, 0.12);
    color: #2a8bf2;
    font-size: 13.5px;
    font-weight: 600;
    padding: 10px 0;
    border-radius: 12px;
    cursor: pointer;
}

.load-more-btn:disabled {
    opacity: 0.6;
    cursor: default;
}
</style>
