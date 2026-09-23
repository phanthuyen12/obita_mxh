<script setup lang="ts">
import { IonIcon } from '@ionic/vue';
import axios from 'axios';
import {
    addOutline,
    callOutline,
    cardOutline,
    chatbubbleEllipsesOutline,
    chatbubblesOutline,
    closeOutline,
    documentTextOutline,
    pricetagOutline,
} from 'ionicons/icons';
import { onMounted, ref } from 'vue';

import dayjs from '@/dayjs';

import type { Customer } from './CustomersPage.vue';

const props = defineProps<{
    customer: Customer;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'update-customer', updated: Customer): void;
    (e: 'chat-with', customer: Customer): void;
}>();

const editMode = ref(false);
const editedCustomer = ref<Customer>({ ...props.customer });
const newTagInput = ref('');
const availableTags = ref<Array<{ id: string; name: string }>>([]);
const isSaving = ref(false);

type ConversationEntry = {
    id: string;
    channel: { provider: string };
    last_message_preview: string | null;
    last_message_at: string | null;
    unread_count: number;
    labels: Array<{ id: string; name: string; color: string | null }>;
};

const conversations = ref<ConversationEntry[]>([]);
const isLoadingConversations = ref(false);

const loadContactDetail = async (): Promise<void> => {
    isLoadingConversations.value = true;
    try {
        const { data } = await axios.get(
            `/omnichat/livechat/contacts/${props.customer.id}`,
        );
        const payload = data as {
            contact: { email?: string | null };
            conversations: ConversationEntry[];
        };
        if (payload.contact.email && !editedCustomer.value.email) {
            editedCustomer.value.email = payload.contact.email;
        }
        conversations.value = payload.conversations;
    } catch {
        conversations.value = [];
    } finally {
        isLoadingConversations.value = false;
    }
};

const providerIcon = (provider: string): string => {
    if (provider === 'telegram') return '✈️';
    if (provider === 'zalo' || provider === 'zalo-oa') return '💬';
    if (provider === 'website') return '🌐';
    return '🔵';
};

const conversationTime = (iso: string | null): string => {
    if (!iso) return '';
    return dayjs(iso).isSame(dayjs(), 'day')
        ? dayjs(iso).format('HH:mm')
        : dayjs(iso).format('DD/MM');
};

const openConversation = (conversation: ConversationEntry): void => {
    emit('chat-with', {
        ...editedCustomer.value,
        latestConversationId: conversation.id,
    });
};

type TagPayload = { id: string; name: string; color?: string | null };

const loadTags = async (): Promise<void> => {
    try {
        const { data } = await axios.get('/omnichat/livechat/tags');
        availableTags.value = (data.data as TagPayload[]).map((t) => ({
            id: t.id,
            name: t.name,
        }));
    } catch {
        availableTags.value = [];
    }
};

onMounted(loadTags);
onMounted(loadContactDetail);

const tagIdByName = (name: string): string | undefined =>
    availableTags.value.find((t) => t.name === name)?.id;

const persistTags = async (tagNames: string[]): Promise<void> => {
    isSaving.value = true;
    try {
        const tagIds = tagNames
            .map((name) => tagIdByName(name))
            .filter((id): id is string => Boolean(id));

        // Create unknown tags on the fly so custom tags survive reloads.
        for (const name of tagNames) {
            if (!tagIdByName(name) && name.trim()) {
                const { data } = await axios.post('/omnichat/livechat/tags', {
                    name: name.trim(),
                });
                const tag = data.tag as TagPayload;
                availableTags.value.push({ id: tag.id, name: tag.name });
                tagIds.push(tag.id);
            }
        }

        const { data } = await axios.put(
            `/omnichat/livechat/contacts/${props.customer.id}`,
            {
                display_name: editedCustomer.value.name,
                phone: editedCustomer.value.phone || null,
                email: editedCustomer.value.email || null,
                notes: editedCustomer.value.notes ?? null,
                tag_ids: tagIds,
            },
        );

        emit('update-customer', {
            ...editedCustomer.value,
            tags: (data.tags as TagPayload[]).map((t) => t.name),
            tagIds: (data.tags as TagPayload[]).map((t) => t.id),
        });
    } finally {
        isSaving.value = false;
    }
};

const toggleTag = (t: string) => {
    if (!editedCustomer.value.tags) editedCustomer.value.tags = [];
    const idx = editedCustomer.value.tags.indexOf(t);
    if (idx > -1) {
        editedCustomer.value.tags.splice(idx, 1);
    } else {
        editedCustomer.value.tags.push(t);
    }
};

const addCustomTag = () => {
    const t = newTagInput.value.trim();
    if (!t) return;
    if (!editedCustomer.value.tags) editedCustomer.value.tags = [];
    if (!editedCustomer.value.tags.includes(t)) {
        editedCustomer.value.tags.push(t);
    }
    newTagInput.value = '';
};

const removeTag = (t: string) => {
    if (editedCustomer.value.tags) {
        const idx = editedCustomer.value.tags.indexOf(t);
        if (idx > -1) editedCustomer.value.tags.splice(idx, 1);
    }
};

const saveChanges = async (): Promise<void> => {
    await persistTags(editedCustomer.value.tags ?? []);
    editMode.value = false;
};
</script>

<template>
    <div class="customer-profile-backdrop" @click.self="emit('close')">
        <div class="customer-profile-sheet">
            <!-- Sheet Header -->
            <div class="sheet-drag-handle"></div>
            <div class="sheet-top-row">
                <button class="sheet-close-btn" @click="emit('close')">
                    <ion-icon :icon="closeOutline"></ion-icon>
                </button>
                <span class="sheet-header-title">Hồ sơ khách hàng</span>
                <button
                    class="sheet-edit-btn"
                    :disabled="isSaving"
                    @click="editMode ? saveChanges() : (editMode = true)"
                >
                    {{ isSaving ? 'Đang lưu...' : editMode ? 'Lưu' : 'Sửa' }}
                </button>
            </div>

            <div class="sheet-body-scroll">
                <!-- Avatar & Quick Info -->
                <div class="profile-hero-section">
                    <div
                        class="profile-avatar-big"
                        :style="{ backgroundColor: customer.avatarBg }"
                    >
                        <span>{{ customer.avatarText }}</span>
                    </div>

                    <div v-if="!editMode" class="hero-name-col">
                        <h2 class="hero-name">{{ customer.name }}</h2>
                        <span class="hero-phone">{{ customer.phone }}</span>
                        <span v-if="editedCustomer.email" class="hero-email"
                            >✉️ {{ editedCustomer.email }}</span
                        >
                        <span class="hero-status"
                            >Hoạt động: {{ customer.lastActive }}</span
                        >
                    </div>
                    <div v-else class="hero-edit-col">
                        <input
                            v-model="editedCustomer.name"
                            class="edit-input-field"
                            placeholder="Tên khách hàng"
                        />
                        <input
                            v-model="editedCustomer.phone"
                            class="edit-input-field font-mono"
                            placeholder="Số điện thoại"
                        />
                        <input
                            v-model="editedCustomer.email"
                            type="email"
                            class="edit-input-field"
                            placeholder="Email"
                        />
                    </div>

                    <!-- Quick Action Buttons: Call, Chat -->
                    <div class="profile-action-bar">
                        <a
                            :href="'tel:' + customer.phone"
                            class="quick-act-btn call-act"
                        >
                            <ion-icon :icon="callOutline"></ion-icon>
                            <span>Gọi điện</span>
                        </a>
                        <button
                            class="quick-act-btn chat-act"
                            @click="emit('chat-with', customer)"
                        >
                            <ion-icon
                                :icon="chatbubbleEllipsesOutline"
                            ></ion-icon>
                            <span>Nhắn tin</span>
                        </button>
                    </div>
                </div>

                <!-- 1. GẮN TAG KHÁCH HÀNG (TAGS SECTION) -->
                <div class="info-card-group">
                    <div class="group-title-row">
                        <div class="title-with-icon">
                            <ion-icon
                                :icon="pricetagOutline"
                                class="title-icon text-amber"
                            ></ion-icon>
                            <span class="group-title"
                                >Tags phân loại khách hàng</span
                            >
                        </div>
                        <span class="tag-counter"
                            >{{ editedCustomer.tags?.length || 0 }} thẻ</span
                        >
                    </div>

                    <!-- Active Tags -->
                    <div class="active-tags-cloud">
                        <span
                            v-for="t in editedCustomer.tags || []"
                            :key="t"
                            class="active-pill-tag"
                        >
                            {{ t }}
                            <ion-icon
                                v-if="editMode"
                                :icon="closeOutline"
                                class="remove-tag-icon"
                                @click="removeTag(t)"
                            ></ion-icon>
                        </span>
                        <span
                            v-if="!editedCustomer.tags?.length"
                            class="empty-tag-hint"
                        >
                            Chưa gắn thẻ phân loại
                        </span>
                    </div>

                    <!-- Selectable Suggestions in Edit Mode -->
                    <div v-if="editMode" class="tag-selection-box">
                        <span class="sub-hint">Chọn nhanh tag gợi ý:</span>
                        <div class="suggest-tags-wrap">
                            <button
                                v-for="st in availableTags"
                                :key="st.id"
                                :class="[
                                    'suggest-pill',
                                    {
                                        selected: editedCustomer.tags?.includes(
                                            st.name,
                                        ),
                                    },
                                ]"
                                @click="toggleTag(st.name)"
                            >
                                {{ st.name }}
                            </button>
                        </div>

                        <!-- Custom tag input -->
                        <div class="custom-tag-add-row">
                            <input
                                v-model="newTagInput"
                                type="text"
                                placeholder="Nhập thẻ mới (ví dụ: Chờ chuyển khoản)..."
                                class="custom-tag-input"
                                @keydown.enter.prevent="addCustomTag"
                            />
                            <button class="add-tag-btn" @click="addCustomTag">
                                <ion-icon :icon="addOutline"></ion-icon>
                                <span>Thêm</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 2. DANH SÁCH HỘI THOẠI CỦA KHÁCH -->
                <div class="info-card-group">
                    <div class="title-with-icon">
                        <ion-icon
                            :icon="chatbubblesOutline"
                            class="title-icon text-sky"
                        ></ion-icon>
                        <span class="group-title"
                            >Hội thoại ({{ conversations.length }})</span
                        >
                    </div>

                    <div
                        v-if="isLoadingConversations"
                        class="conversations-empty"
                    >
                        Đang tải hội thoại...
                    </div>
                    <div
                        v-else-if="conversations.length === 0"
                        class="conversations-empty"
                    >
                        Khách hàng chưa có hội thoại nào
                    </div>

                    <div v-else class="conversation-list">
                        <div
                            v-for="conversation in conversations"
                            :key="conversation.id"
                            class="conversation-row"
                            @click="openConversation(conversation)"
                        >
                            <span class="conversation-provider">{{
                                providerIcon(conversation.channel.provider)
                            }}</span>
                            <div class="conversation-info">
                                <span class="conversation-preview">
                                    {{
                                        conversation.last_message_preview ||
                                        'Chưa có tin nhắn'
                                    }}
                                </span>
                                <span class="conversation-meta">
                                    {{ conversation.labels.length }} thẻ
                                    <template
                                        v-if="conversation.unread_count > 0"
                                    >
                                        • {{ conversation.unread_count }} chưa
                                        đọc</template
                                    >
                                </span>
                            </div>
                            <span class="conversation-time">{{
                                conversationTime(conversation.last_message_at)
                            }}</span>
                        </div>
                    </div>
                </div>

                <!-- 3. THÔNG TIN GIAO DỊCH & DOANH THU -->
                <div class="info-card-group">
                    <div class="title-with-icon">
                        <ion-icon
                            :icon="cardOutline"
                            class="title-icon text-emerald"
                        ></ion-icon>
                        <span class="group-title">Lịch sử giao dịch</span>
                    </div>

                    <div class="meta-data-grid">
                        <div class="meta-item">
                            <span class="meta-label">Tổng chi tiêu</span>
                            <span class="meta-value highlight-money">{{
                                customer.totalSpent
                            }}</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Trạng thái</span>
                            <span class="meta-value">{{ customer.tag }}</span>
                        </div>
                    </div>
                </div>

                <!-- 4. GHI CHÚ NHU CẦU & CHĂM SÓC -->
                <div class="info-card-group">
                    <div class="title-with-icon">
                        <ion-icon
                            :icon="documentTextOutline"
                            class="title-icon text-sky"
                        ></ion-icon>
                        <span class="group-title">Ghi chú nhu cầu</span>
                    </div>

                    <div v-if="!editMode" class="notes-display-box">
                        <p v-if="customer.notes" class="notes-text">
                            {{ customer.notes }}
                        </p>
                        <p v-else class="notes-empty">
                            Chưa có ghi chú nào cho khách hàng này.
                        </p>
                    </div>
                    <div v-else class="notes-edit-box">
                        <textarea
                            v-model="editedCustomer.notes"
                            rows="3"
                            class="notes-textarea"
                            placeholder="Nhập ghi chú quan trọng về khách hàng này..."
                        ></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.customer-profile-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    z-index: 3000;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.customer-profile-sheet {
    width: 100%;
    max-width: 500px;
    max-height: 88vh;
    background: #121418;
    border-top-left-radius: 24px;
    border-top-right-radius: 24px;
    border: 0.5px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.9);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}

.sheet-drag-handle {
    width: 38px;
    height: 4px;
    background: rgba(255, 255, 255, 0.25);
    border-radius: 2px;
    margin: 10px auto 4px;
}

.sheet-top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 16px 10px;
    border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
}

.sheet-close-btn {
    background: #1c1c1e;
    border: none;
    color: #8e8e93;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    cursor: pointer;
}

.sheet-header-title {
    font-size: 15.5px;
    font-weight: 700;
    color: #ffffff;
}

.sheet-edit-btn {
    background: #2a8bf2;
    border: none;
    color: #ffffff;
    font-size: 13.5px;
    font-weight: 700;
    padding: 4px 14px;
    border-radius: 14px;
    cursor: pointer;
}

.sheet-body-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* Hero Section */
.profile-hero-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding-bottom: 6px;
}

.profile-avatar-big {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 800;
    color: #ffffff;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
    border: 2px solid rgba(255, 255, 255, 0.15);
}

.hero-name-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.hero-name {
    font-size: 18px;
    font-weight: 800;
    color: #ffffff;
    margin: 0;
    text-align: center;
}

.hero-phone {
    font-size: 14px;
    color: #38bdf8;
    font-family: monospace;
    font-weight: 600;
}

.hero-email {
    font-size: 12px;
    color: #9ca3af;
}

/* Danh sách hội thoại của khách */
.conversation-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.conversation-row {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #232730;
    border-radius: 10px;
    padding: 9px 10px;
    cursor: pointer;
    transition: background 0.15s;
}

.conversation-row:active {
    background: #2a3442;
}

.conversation-provider {
    font-size: 15px;
    flex-shrink: 0;
}

.conversation-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.conversation-preview {
    font-size: 12.5px;
    color: #e4e4e7;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-meta {
    font-size: 10.5px;
    color: #71717a;
}

.conversation-time {
    font-size: 10.5px;
    color: #8e8e93;
    flex-shrink: 0;
}

.conversations-empty {
    font-size: 12px;
    color: #71717a;
    font-style: italic;
}

.hero-status {
    font-size: 11.5px;
    color: #8e8e93;
}

.hero-edit-col {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
    max-width: 280px;
}

.edit-input-field {
    background: #1c1c1e;
    border: 0.5px solid rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    padding: 6px 12px;
    color: #ffffff;
    font-size: 14px;
    outline: none;
    text-align: center;
}

/* Profile Action Bar */
.profile-action-bar {
    display: flex;
    gap: 12px;
    width: 100%;
    margin-top: 4px;
}

.quick-act-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: transform 0.15s;
}
.quick-act-btn:active {
    transform: scale(0.97);
}

.call-act {
    background: #064e3b;
    color: #34d399;
}

.chat-act {
    background: #1e3a8a;
    color: #60a5fa;
}

/* Info Card Group */
.info-card-group {
    background: #181b22;
    border: 0.5px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.group-title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.title-with-icon {
    display: flex;
    align-items: center;
    gap: 6px;
}

.title-icon {
    font-size: 16px;
}

.text-amber {
    color: #f59e0b;
}
.text-emerald {
    color: #10b981;
}
.text-sky {
    color: #38bdf8;
}

.group-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #ffffff;
}

.tag-counter {
    font-size: 11px;
    color: #8e8e93;
}

/* Tags Cloud */
.active-tags-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.active-pill-tag {
    background: #2a3442;
    color: #38bdf8;
    border: 0.5px solid rgba(56, 189, 248, 0.3);
    font-size: 12px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.remove-tag-icon {
    font-size: 14px;
    cursor: pointer;
    color: #ef4444;
}

.empty-tag-hint {
    font-size: 12px;
    color: #71717a;
    font-style: italic;
}

.tag-selection-box {
    border-top: 0.5px solid rgba(255, 255, 255, 0.08);
    padding-top: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sub-hint {
    font-size: 11px;
    color: #8e8e93;
}

.suggest-tags-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.suggest-pill {
    background: #232730;
    border: 0.5px solid rgba(255, 255, 255, 0.1);
    color: #d1d5db;
    font-size: 11.5px;
    padding: 4px 8px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.15s;
}

.suggest-pill.selected {
    background: #38bdf8;
    color: #000000;
    font-weight: 700;
}

.custom-tag-add-row {
    display: flex;
    gap: 6px;
    margin-top: 4px;
}

.custom-tag-input {
    flex: 1;
    background: #1c1c1e;
    border: 0.5px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 6px 10px;
    color: #ffffff;
    font-size: 12px;
    outline: none;
}

.add-tag-btn {
    background: #2a8bf2;
    border: none;
    color: #ffffff;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 2px;
    cursor: pointer;
}

/* Meta Data Grid */
.meta-data-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
}

.meta-item {
    background: #232730;
    padding: 8px 10px;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.meta-label {
    font-size: 11px;
    color: #8e8e93;
}

.meta-value {
    font-size: 13.5px;
    font-weight: 700;
    color: #ffffff;
}

.highlight-money {
    color: #10b981;
    font-family: monospace;
}

/* Notes Box */
.notes-display-box {
    background: #232730;
    border-radius: 8px;
    padding: 8px 12px;
}

.notes-text {
    font-size: 13px;
    color: #e4e4e7;
    line-height: 1.4;
    margin: 0;
}

.notes-empty {
    font-size: 12px;
    color: #71717a;
    font-style: italic;
    margin: 0;
}

.notes-textarea {
    width: 100%;
    background: #1c1c1e;
    border: 0.5px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 8px 10px;
    color: #ffffff;
    font-size: 13px;
    outline: none;
    resize: none;
}
</style>
