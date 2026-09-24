<script setup lang="ts">
import { IonIcon } from '@ionic/vue';
import axios from 'axios';
import {
    addOutline,
    attachOutline,
    checkmark,
    checkmarkDone,
    chevronBack,
    closeCircle,
    closeOutline,
    documentAttachOutline,
    happyOutline,
    micOutline,
    personCircleOutline,
    pricetagOutline,
    send,
    sparklesOutline,
    trashOutline,
} from 'ionicons/icons';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

import dayjs from '@/dayjs';

import type { Attachment, ChatItem, Message } from '../types/chat';

const props = defineProps<{
    chat: ChatItem;
    sendError?: string;
    allTags?: Array<{ id: string; name: string; color: string | null }>;
}>();

const emit = defineEmits<{
    (e: 'back'): void;
    (e: 'open-profile'): void;
    (
        e: 'send',
        payload: {
            id: string;
            text: string;
            attachment: Attachment | null;
            clientId: string;
        },
    ): void;
    (
        e: 'update-last-message',
        payload: { id: string; text: string; time: string },
    ): void;
    (
        e: 'tag-created',
        tag: { id: string; name: string; color: string | null },
    ): void;
}>();

const messages = ref<Message[]>(
    props.chat.messages ? [...props.chat.messages] : [],
);

// AI auto-reply state for this conversation (sales can pause/resume per chat).
const aiPaused = ref(props.chat.aiPaused ?? false);
const isTogglingAi = ref(false);
const aiToast = ref('');

const toggleAi = async (): Promise<void> => {
    if (isTogglingAi.value || props.chat.id.startsWith('contact_')) return;
    isTogglingAi.value = true;
    try {
        const { data } = await axios.post(
            `/omnichat/conversations/${props.chat.id}/ai-toggle`,
            null,
            {
                headers: { Accept: 'application/json' },
            },
        );
        aiPaused.value = (data as { ai_paused: boolean }).ai_paused;
        aiToast.value = (data as { message: string }).message;
    } catch {
        aiToast.value = 'Không đổi được trạng thái AI. Thử lại sau.';
    } finally {
        isTogglingAi.value = false;
        setTimeout(() => {
            aiToast.value = '';
        }, 2500);
    }
};

// Keep the room in sync when the parent loads the real message history.
watch(
    () => props.chat.messages,
    (next) => {
        messages.value = next ? [...next] : [];
        scrollToBottom();
    },
);

const inputText = ref('');
const selectedAttachment = ref<Attachment | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const messagesContainerRef = ref<HTMLElement | null>(null);

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainerRef.value) {
            messagesContainerRef.value.scrollTop =
                messagesContainerRef.value.scrollHeight;
        }
    });
};

onMounted(() => {
    scrollToBottom();
});

const triggerAttach = () => {
    if (fileInputRef.value) {
        fileInputRef.value.click();
    }
};

const onFileSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
        const sizeFormatted =
            file.size > 1024 * 1024
                ? `${sizeMb} MB`
                : `${(file.size / 1024).toFixed(0)} KB`;

        if (file.type.startsWith('image/')) {
            const previewUrl = URL.createObjectURL(file);
            selectedAttachment.value = {
                name: file.name,
                size: sizeFormatted,
                type: 'image',
                url: previewUrl,
            };
        } else if (file.size <= 50 * 1024 * 1024) {
            // Tệp không phải ảnh: chỉ cho phép ≤ 50 MB
            selectedAttachment.value = {
                name: file.name,
                size: sizeFormatted,
                type: 'file',
            };
        } else {
            alert('Tệp quá lớn. Vui lòng chọn tệp nhỏ hơn 50 MB.');
        }
    }
};

const removeAttachment = (revokeUrl = true) => {
    if (revokeUrl && selectedAttachment.value?.url) {
        URL.revokeObjectURL(selectedAttachment.value.url);
    }
    selectedAttachment.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
};

const sendMessage = () => {
    const text = inputText.value.trim();
    if (!text && !selectedAttachment.value) return;

    const currentTime = dayjs().format('HH:mm');
    const attachment = selectedAttachment.value
        ? { ...selectedAttachment.value }
        : null;

    // Optimistic UI: show the outgoing message immediately, the parent
    // persists it through the Omnichat API and reconciles on refresh.
    const clientId = crypto.randomUUID();
    const newMsg: Message = {
        id: 'msg_' + clientId.slice(0, 8),
        sender: 'me',
        text: text || undefined,
        time: currentTime,
        isRead: false,
        clientId,
        attachment: attachment ?? undefined,
    };

    messages.value.push(newMsg);
    emit('send', { id: props.chat.id, text, attachment, clientId });
    emit('update-last-message', {
        id: props.chat.id,
        text:
            text ||
            (attachment?.type === 'image'
                ? '[Hình ảnh]'
                : `[Tệp] ${attachment?.name}`),
        time: currentTime,
    });

    inputText.value = '';
    // Không revoke URL ngay: parent handleSend() cần fetch() nó bất đồng bộ.
    // Sử dụng revokeUrl=false để chỉ xóa reference, URL sẽ tự giải phóng sau.
    removeAttachment(false);
    if (fileInputRef.value) fileInputRef.value.value = '';

    scrollToBottom();
};

// Logic Gắn & Tạo Tag Cuộc Hội Thoại — đồng bộ với backend qua API
const showTagModal = ref(false);
const availableConvTags = ref<Array<{ id: string; name: string; color?: string | null }>>([]);
const isSavingTags = ref(false);
const isCreatingTag = ref(false);
const newTagName = ref('');
const PRESET_TAG_COLORS = [
    '#6366f1', // Indigo
    '#0ea5e9', // Sky blue
    '#10b981', // Emerald
    '#f59e0b', // Amber
    '#ec4899', // Pink
    '#8b5cf6', // Purple
    '#ef4444', // Rose Red
    '#14b8a6', // Teal
];
const newTagColor = ref(PRESET_TAG_COLORS[0]);

type TagPayload = { id: string; name: string; color?: string | null };

const loadTags = async (): Promise<void> => {
    try {
        const { data } = await axios.get('/omnichat/livechat/tags');
        availableConvTags.value = (data.data as TagPayload[]).map((t) => ({
            id: t.id,
            name: t.name,
            color: t.color || '#6366f1',
        }));
    } catch {
        if (props.allTags && props.allTags.length > 0) {
            availableConvTags.value = props.allTags.map((t) => ({
                id: t.id,
                name: t.name,
                color: t.color || '#6366f1',
            }));
        }
    }
};

watch(
    () => props.allTags,
    (tags) => {
        if (tags && tags.length > 0 && availableConvTags.value.length === 0) {
            availableConvTags.value = tags.map((t) => ({
                id: t.id,
                name: t.name,
                color: t.color || '#6366f1',
            }));
        }
    },
    { immediate: true },
);

onMounted(loadTags);

const getTagColor = (tagName: string): string => {
    const found = availableConvTags.value.find((t) => t.name === tagName);
    return found?.color || '#6366f1';
};

const handleCreateTag = async (): Promise<void> => {
    const name = newTagName.value.trim();
    if (!name || isCreatingTag.value) return;
    isCreatingTag.value = true;
    try {
        const { data } = await axios.post('/omnichat/livechat/tags', {
            name,
            color: newTagColor.value,
        });
        const created = data.tag as TagPayload;
        const exists = availableConvTags.value.find((t) => t.id === created.id);
        if (!exists) {
            availableConvTags.value.push({
                id: created.id,
                name: created.name,
                color: created.color || newTagColor.value,
            });
        }
        // Auto-assign to current conversation if not already assigned
        if (!props.chat.tags?.includes(created.name)) {
            toggleConversationTag({ id: created.id, name: created.name });
        }
        emit('tag-created', created);
        newTagName.value = '';
    } catch (e: any) {
        alert(e.response?.data?.message || 'Không tạo được thẻ. Thử lại sau.');
    } finally {
        isCreatingTag.value = false;
    }
};

const handleDeleteTag = async (tag: { id: string; name: string }): Promise<void> => {
    if (!confirm(`Bạn có chắc muốn xóa thẻ "${tag.name}" khỏi hệ thống?`)) return;
    try {
        await axios.delete(`/omnichat/livechat/tags/${tag.id}`);
        availableConvTags.value = availableConvTags.value.filter((t) => t.id !== tag.id);
        if (props.chat.tags?.includes(tag.name)) {
            const nameIdx = props.chat.tags.indexOf(tag.name);
            if (nameIdx > -1) props.chat.tags.splice(nameIdx, 1);
            const idIdx = props.chat.tagIds?.indexOf(tag.id) ?? -1;
            if (idIdx > -1 && props.chat.tagIds) props.chat.tagIds.splice(idIdx, 1);
            persistConversationTags();
        }
    } catch {
        alert('Không xóa được thẻ');
    }
};

const toggleConversationTag = (tag: { id: string; name: string }) => {
    if (!props.chat.tags) props.chat.tags = [];
    if (!props.chat.tagIds) props.chat.tagIds = [];

    const nameIdx = props.chat.tags.indexOf(tag.name);
    const idIdx = props.chat.tagIds.indexOf(tag.id);

    if (nameIdx > -1) {
        props.chat.tags.splice(nameIdx, 1);
    } else {
        props.chat.tags.push(tag.name);
    }
    if (idIdx > -1) {
        props.chat.tagIds.splice(idIdx, 1);
    } else if (tag.id) {
        props.chat.tagIds.push(tag.id);
    }

    persistConversationTags();
};

const persistConversationTags = async (): Promise<void> => {
    isSavingTags.value = true;
    try {
        let serverTags: TagPayload[] = [];
        if (props.chat.contactId) {
            const { data } = await axios.put(
                `/omnichat/livechat/contacts/${props.chat.contactId}/conversation-tags`,
                {
                    conversation_id: props.chat.id,
                    tag_ids: props.chat.tagIds ?? [],
                },
            );
            serverTags = data.tags as TagPayload[];
        } else if (!props.chat.id.startsWith('contact_')) {
            const { data } = await axios.put(
                `/omnichat/conversations/${props.chat.id}/tags`,
                {
                    tag_ids: props.chat.tagIds ?? [],
                },
            );
            serverTags = data.tags as TagPayload[];
        }
        if (serverTags && serverTags.length >= 0) {
            props.chat.tags = serverTags.map((t) => t.name) as ChatItem['tags'];
            props.chat.tagIds = serverTags.map((t) => t.id);
        }
    } catch {
        // Keep optimistic state; reload restores server truth on next open.
    } finally {
        isSavingTags.value = false;
    }
};
</script>

<template>
    <div class="chat-room-page">
        <!-- Nền chat Ambient Mesh + Doodle Vector cố định 100% không trôi khi cuộn -->
        <div class="chat-wallpaper-fixed" aria-hidden="true">
            <div class="doodle-pattern-overlay"></div>
        </div>

        <!-- 1. Header CỐ ĐỊNH CỨNG chuẩn ảnh: Nút pill "< 69067", Title pill "KHOA LOL / hoạt động 1 phút trước", Avatar tròn -->
        <header class="chat-header-ios">
            <!-- Nút Back kiểu iOS Capsule: < [badge] -->
            <button class="back-pill-btn" @click="emit('back')">
                <ion-icon :icon="chevronBack" class="back-chevron"></ion-icon>
                <span class="back-pill-badge">69067</span>
            </button>

            <!-- Center Title Capsule (Tên khách + Tên Page/Kênh + Trạng thái) -->
            <div class="header-center-pill" @click="showTagModal = true">
                <div class="header-title-flex">
                    <span class="header-user-name">{{ chat.name }}</span>
                    <span
                        v-if="chat.channelSource"
                        :class="[
                            'room-channel-pill',
                            `pill-${chat.channelSource}`,
                        ]"
                    >
                        <template v-if="chat.channelSource === 'facebook'"
                            >🔵 FB Page</template
                        >
                        <template v-else-if="chat.channelSource === 'telegram'"
                            >✈️ Telegram</template
                        >
                        <template v-else-if="chat.channelSource === 'zalo'"
                            >💬 Zalo OA</template
                        >
                        <template v-else-if="chat.channelSource === 'website'"
                            >🌐 Website</template
                        >
                    </span>
                </div>
                <span class="header-user-status">
                    {{ chat.channelName ? `${chat.channelName} • ` : '' }}hoạt
                    động 1 phút trước • 🏷️ {{ chat.tags?.length || 0 }} thẻ
                </span>
            </div>

            <div class="header-right-actions">
                <!-- Nút bật/tắt AI Bot cho riêng hội thoại này -->
                <button
                    :class="['ai-toggle-btn', { paused: aiPaused }]"
                    :disabled="isTogglingAi"
                    :title="
                        aiPaused
                            ? 'AI đang tạm dừng — chạm để bật lại'
                            : 'AI đang trả lời tự động — chạm để tạm dừng'
                    "
                    @click="toggleAi"
                >
                    <ion-icon :icon="sparklesOutline"></ion-icon>
                </button>

                <!-- Nút gắn thẻ nhanh -->
                <button
                    class="tag-quick-btn"
                    title="Gắn tag cuộc hội thoại"
                    @click="showTagModal = true"
                >
                    <ion-icon :icon="pricetagOutline"></ion-icon>
                </button>

                <!-- Avatar tròn góc phải: chạm để mở thông tin khách hàng -->
                <div
                    class="header-avatar-circle"
                    title="Thông tin khách hàng"
                    role="button"
                    tabindex="0"
                    @click="emit('open-profile')"
                >
                    <div
                        v-if="chat.avatarType === 'text'"
                        class="avatar-text-fill"
                        :style="{ backgroundColor: chat.avatarBg }"
                    >
                        <span class="avatar-letter-small">{{
                            chat.avatarText
                        }}</span>
                    </div>
                    <div
                        v-else-if="
                            chat.id === '7' || chat.name.includes('KHOA LOL')
                        "
                        class="avatar-orange-bg"
                    >
                        <span class="orange-emoji">🍊</span>
                    </div>
                    <div
                        v-else
                        class="avatar-icon-fill"
                        :style="{ backgroundColor: chat.avatarBg }"
                    >
                        <span class="emoji-fill">👤</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Báo lỗi gửi tin nhắn/ảnh -->
        <div v-if="sendError" class="ai-status-bar ai-status-error">
            <span class="ai-status-dot error"></span>
            <span>{{ sendError }}</span>
        </div>

        <!-- Trạng thái AI hội thoại -->
        <div v-else-if="aiToast || aiPaused" class="ai-status-bar">
            <span class="ai-status-dot" :class="{ paused: aiPaused }"></span>
            <span>{{
                aiToast ||
                (aiPaused
                    ? 'AI Bot đang tạm dừng — nhân viên đang tiếp quản'
                    : 'AI Bot đang trả lời tự động')
            }}</span>
        </div>

        <!-- Dải hiển thị Tags cuộc hội thoại đang gắn -->
        <div v-if="chat.tags && chat.tags.length > 0" class="chat-tags-subbar">
            <span class="tags-subbar-label">Thẻ:</span>
            <div class="tags-pill-scroll">
                <span
                    v-for="t in chat.tags"
                    :key="t"
                    class="conv-tag-pill"
                    :style="{
                        backgroundColor: getTagColor(t) + '22',
                        borderColor: getTagColor(t) + '55',
                        color: getTagColor(t),
                    }"
                >
                    <span
                        class="conv-tag-dot"
                        :style="{ backgroundColor: getTagColor(t) }"
                    ></span>
                    {{ t }}
                    <ion-icon
                        :icon="closeCircle"
                        class="remove-conv-tag"
                        @click="
                            toggleConversationTag({
                                id:
                                    chat.tagIds?.[
                                        chat.tags?.indexOf(t) ?? -1
                                    ] ?? '',
                                name: t,
                            })
                        "
                    ></ion-icon>
                </span>
            </div>
            <button class="add-more-tag-btn" @click="showTagModal = true">
                + Gắn thêm
            </button>
        </div>

        <!-- 2. Phần Tin Nhắn Cuộn Tự Nhiên (Scrollable Area) -->
        <div ref="messagesContainerRef" class="chat-scroll-area">
            <div class="messages-inner-wrapper">
                <template v-for="msg in messages" :key="msg.id">
                    <!-- Divider "Hôm nay" -->
                    <div
                        v-if="msg.text === '__DIVIDER_TODAY__'"
                        class="system-divider-row"
                    >
                        <span class="system-pill-badge">Hôm nay</span>
                    </div>

                    <!-- Divider "Tin nhắn chưa đọc" -->
                    <div
                        v-else-if="msg.text === '__DIVIDER_UNREAD__'"
                        class="system-divider-unread-row"
                    >
                        <span class="unread-banner-text"
                            >Tin nhắn chưa đọc</span
                        >
                    </div>

                    <!-- Normal Message Row -->
                    <div
                        v-else
                        :class="[
                            'msg-row',
                            msg.sender === 'me'
                                ? 'msg-row-me'
                                : 'msg-row-other',
                        ]"
                    >
                        <div
                            :class="[
                                'msg-bubble',
                                msg.sender === 'me'
                                    ? 'bubble-purple'
                                    : 'bubble-dark',
                            ]"
                        >
                            <!-- Attached Image preview -->
                            <div
                                v-if="
                                    msg.attachment &&
                                    msg.attachment.type === 'image'
                                "
                                class="msg-attachment-img"
                            >
                                <img
                                    :src="msg.attachment.url"
                                    :alt="msg.attachment.name"
                                />
                            </div>

                            <!-- Attached Document preview -->
                            <div
                                v-else-if="
                                    msg.attachment &&
                                    msg.attachment.type === 'file'
                                "
                                class="msg-attachment-doc"
                            >
                                <ion-icon
                                    :icon="documentAttachOutline"
                                    class="file-icon"
                                ></ion-icon>
                                <div class="file-meta">
                                    <span class="file-name">{{
                                        msg.attachment.name
                                    }}</span>
                                    <span class="file-size">{{
                                        msg.attachment.size
                                    }}</span>
                                </div>
                            </div>

                            <!-- Message Text -->
                            <div v-if="msg.text" class="bubble-content-text">
                                {{ msg.text }}
                            </div>

                            <!-- Bubble Time & Read double ticks -->
                            <div class="bubble-meta">
                                <span class="bubble-time">{{ msg.time }}</span>
                                <span
                                    v-if="msg.sender === 'me'"
                                    class="bubble-ticks"
                                >
                                    <ion-icon :icon="checkmarkDone"></ion-icon>
                                </span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- 3. Preview tệp khi chọn file chuẩn bị gửi -->
        <div v-if="selectedAttachment" class="attachment-preview-drawer">
            <div class="preview-drawer-item">
                <img
                    v-if="selectedAttachment.type === 'image'"
                    :src="selectedAttachment.url"
                    class="preview-thumb-img"
                />
                <div v-else class="preview-thumb-file">📄</div>
                <div class="preview-info-col">
                    <span class="preview-file-name">{{
                        selectedAttachment.name
                    }}</span>
                    <span class="preview-file-size">{{
                        selectedAttachment.size
                    }}</span>
                </div>
                <button class="remove-attachment-btn" @click="removeAttachment">
                    <ion-icon :icon="closeCircle"></ion-icon>
                </button>
            </div>
        </div>

        <!-- 4. Input Bar CỐ ĐỊNH CỨNG ở đáy chuẩn ảnh mẫu: [Clip] [Input: "Tin nhắn" + Smiley] [Mic] -->
        <footer class="chat-bottom-bar">
            <!-- Hidden file input -->
            <input
                ref="fileInputRef"
                type="file"
                class="hidden-file-input"
                accept="image/*,application/pdf,video/mp4,video/quicktime"
                @change="onFileSelected"
            />

            <!-- Clip Icon (Kẹp ghim đính kèm) -->
            <button
                class="bar-icon-button"
                title="Đính kèm tệp / ảnh"
                @click="triggerAttach"
            >
                <ion-icon :icon="attachOutline"></ion-icon>
            </button>

            <!-- Input capsule bo tròn chứa Text Input + Icon mặt cười Smiley -->
            <div class="input-capsule-box">
                <input
                    v-model="inputText"
                    type="text"
                    class="real-text-input"
                    placeholder="Tin nhắn"
                    @keydown.enter.prevent="sendMessage"
                />
                <button class="emoji-button" title="Emoji">
                    <ion-icon :icon="happyOutline"></ion-icon>
                </button>
            </div>

            <!-- Action: Mic Icon tròn hoặc Nút Send xanh/tím -->
            <button
                v-if="inputText.trim().length > 0 || selectedAttachment"
                class="send-message-btn"
                title="Gửi tin nhắn"
                @click="sendMessage"
            >
                <ion-icon :icon="send"></ion-icon>
            </button>
            <button v-else class="bar-icon-button" title="Ghi âm giọng nói">
                <ion-icon :icon="micOutline"></ion-icon>
            </button>
        </footer>

        <!-- MODAL GẮN & TẠO THẺ CUỘC HỘI THOẠI (BOTTOM SHEET) -->
        <div
            v-if="showTagModal"
            class="conv-tag-backdrop"
            @click.self="showTagModal = false"
        >
            <div class="conv-tag-sheet">
                <div class="sheet-drag-handle"></div>
                <div class="conv-sheet-header">
                    <div class="conv-sheet-title-col">
                        <span class="sheet-main-title">Gắn & Tạo Thẻ Phân Loại</span>
                        <span class="sheet-sub-title">{{ chat.name }}</span>
                    </div>
                    <button
                        class="sheet-done-btn"
                        @click="showTagModal = false"
                    >
                        Xong
                    </button>
                </div>

                <div class="conv-sheet-body">
                    <!-- KHU VỰC TẠO THẺ MỚI TRỰC TIẾP -->
                    <div class="create-tag-box">
                        <span class="create-tag-box-title">Tạo thẻ mới:</span>
                        <div class="create-tag-input-row">
                            <span
                                class="tag-color-preview-badge"
                                :style="{ backgroundColor: newTagColor }"
                            ></span>
                            <input
                                v-model="newTagName"
                                type="text"
                                class="new-tag-input"
                                placeholder="Tên thẻ mới (vd: Chốt đơn, Khách VIP...)"
                                maxlength="40"
                                @keyup.enter="handleCreateTag"
                            />
                            <button
                                class="create-tag-action-btn"
                                :disabled="!newTagName.trim() || isCreatingTag"
                                @click="handleCreateTag"
                            >
                                <ion-icon v-if="!isCreatingTag" :icon="addOutline"></ion-icon>
                                <span>{{ isCreatingTag ? '...' : '+ Thêm thẻ' }}</span>
                            </button>
                        </div>

                        <!-- Bảng chọn màu sắc cho thẻ mới -->
                        <div class="color-palette-bar">
                            <span class="color-palette-label">Màu thẻ:</span>
                            <div class="color-palette-dots">
                                <button
                                    v-for="c in PRESET_TAG_COLORS"
                                    :key="c"
                                    type="button"
                                    class="palette-dot-btn"
                                    :class="{ active: newTagColor === c }"
                                    :style="{ backgroundColor: c }"
                                    @click="newTagColor = c"
                                ></button>
                            </div>
                        </div>
                    </div>

                    <div class="tags-list-section-header">
                        <span class="select-label">Chạm để gắn/bỏ gắn thẻ cho khách hàng này:</span>
                        <span class="tags-count-badge">{{ availableConvTags.length }} thẻ</span>
                    </div>

                    <div v-if="availableConvTags.length === 0" class="no-tags-prompt">
                        Chưa có thẻ nào. Hãy nhập tên ở trên và nhấn "+ Thêm thẻ" để tạo thẻ đầu tiên!
                    </div>

                    <div v-else class="conv-tags-grid">
                        <div
                            v-for="tag in availableConvTags"
                            :key="tag.id"
                            :class="[
                                'tag-select-pill',
                                { active: chat.tags?.includes(tag.name) },
                            ]"
                            :style="chat.tags?.includes(tag.name) ? {
                                backgroundColor: (tag.color || '#6366f1') + '2a',
                                borderColor: tag.color || '#6366f1',
                                color: '#ffffff',
                                boxShadow: '0 2px 10px ' + (tag.color || '#6366f1') + '40'
                            } : {}"
                            @click="toggleConversationTag(tag)"
                        >
                            <span
                                class="tag-bullet"
                                :style="{ backgroundColor: tag.color || '#6366f1' }"
                            ></span>
                            <span class="tag-text">{{ tag.name }}</span>
                            <ion-icon
                                v-if="chat.tags?.includes(tag.name)"
                                :icon="checkmark"
                                class="tag-check-icon"
                            ></ion-icon>
                            <button
                                class="delete-tag-btn"
                                title="Xóa thẻ khỏi hệ thống"
                                @click.stop="handleDeleteTag(tag)"
                            >
                                <ion-icon :icon="closeOutline"></ion-icon>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.chat-room-page {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    max-width: 100%;
    background-color: #07080c;
    display: flex;
    flex-direction: column;
    z-index: 2000;
    overflow: hidden;
    overflow-x: hidden;
    touch-action: pan-y;
    min-height: 0;
    overscroll-behavior-x: none;
    box-sizing: border-box;
    font-family:
        -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue',
        sans-serif;
    color: #ffffff;
}

/* 1. Header Cố Định (Chuẩn 100% theo ảnh & an toàn trên iPhone Notch/Dynamic Island) */
.chat-header-ios {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: max(env(safe-area-inset-top, 0px), 8px) 10px 7px;
    background: rgba(18, 20, 26, 0.96);
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
    min-height: 58px;
    box-sizing: border-box;
    width: 100%;
    max-width: 100%;
    overflow: hidden;
    flex-shrink: 0;
    z-index: 100;
}

/* Nút back hình viên thuốc: < [69067] */
.back-pill-btn {
    background: rgba(40, 42, 48, 0.9);
    border: none;
    border-radius: 20px;
    display: flex;
    align-items: center;
    padding: 4px 10px 4px 6px;
    gap: 2px;
    cursor: pointer;
    color: #ffffff;
    transition: opacity 0.2s;
    flex-shrink: 0;
}
.back-pill-btn:active {
    opacity: 0.7;
}

.back-chevron {
    font-size: 20px;
    color: #ffffff;
}

.back-pill-badge {
    font-size: 13px;
    font-weight: 600;
    color: #ffffff;
    letter-spacing: -0.2px;
}

/* Header Center Capsule: Tên + hoạt động 1 phút trước */
.header-center-pill {
    background: rgba(40, 42, 48, 0.85);
    border-radius: 22px;
    padding: 4px 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
    min-width: 0;
    margin: 0 8px;
    overflow: hidden;
}

.header-title-flex {
    display: flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
}

.header-user-name {
    font-size: 14px;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: -0.2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
}

.room-channel-pill {
    font-size: 9.5px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 8px;
    white-space: nowrap;
    flex-shrink: 0;
}
.pill-facebook {
    background: rgba(8, 102, 255, 0.2);
    color: #60a5fa;
    border: 0.5px solid rgba(8, 102, 255, 0.4);
}
.pill-telegram {
    background: rgba(0, 136, 204, 0.2);
    color: #38bdf8;
    border: 0.5px solid rgba(0, 136, 204, 0.4);
}
.pill-zalo {
    background: rgba(0, 104, 255, 0.2);
    color: #38bdf8;
    border: 0.5px solid rgba(0, 104, 255, 0.4);
}
.pill-website {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
    border: 0.5px solid rgba(16, 185, 129, 0.4);
}

.header-user-status {
    font-size: 11px;
    color: #8e8e93;
    margin-top: 1px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.header-right-actions {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
}

.tag-quick-btn {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
    border: 0.5px solid rgba(245, 158, 11, 0.3);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
    flex-shrink: 0;
}

/* Nút bật/tắt AI Bot từng hội thoại */
.ai-toggle-btn {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 0.5px solid rgba(16, 185, 129, 0.35);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
    flex-shrink: 0;
    transition:
        background 0.2s,
        color 0.2s,
        opacity 0.2s;
}

.ai-toggle-btn.paused {
    background: rgba(113, 113, 122, 0.15);
    color: #8e8e93;
    border-color: rgba(113, 113, 122, 0.35);
}

.ai-toggle-btn:disabled {
    opacity: 0.55;
    cursor: default;
}

.ai-toggle-btn:active {
    transform: scale(0.92);
}

/* Thanh trạng thái AI dưới header */
.ai-status-bar {
    display: flex;
    align-items: center;
    gap: 7px;
    background: rgba(56, 189, 248, 0.08);
    border-bottom: 0.5px solid rgba(255, 255, 255, 0.06);
    padding: 5px 16px;
    font-size: 11px;
    color: #94a3b8;
}

.ai-status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #34d399;
    box-shadow: 0 0 6px rgba(52, 211, 153, 0.7);
    flex-shrink: 0;
}

.ai-status-dot.paused {
    background: #8e8e93;
    box-shadow: none;
}

.ai-status-error {
    background: rgba(239, 68, 68, 0.08);
    border-bottom: 0.5px solid rgba(239, 68, 68, 0.25);
}

.ai-status-dot.error {
    background: #ef4444;
    box-shadow: 0 0 6px rgba(239, 68, 68, 0.7);
}

/* Avatar tròn góc phải */
.header-avatar-circle {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    overflow: hidden;
    background-color: #2c2c2e;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255, 255, 255, 0.26);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    flex-shrink: 0;
    cursor: pointer;
    transition: transform 0.15s, border-color 0.15s;
}
.header-avatar-circle:active {
    transform: scale(0.92);
    border-color: rgba(99, 102, 241, 0.6);
}

/* Dải hiển thị Tags cuộc hội thoại */
.chat-tags-subbar {
    background: rgba(18, 20, 26, 0.98);
    border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
    padding: 6px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    z-index: 90;
    flex-shrink: 0;
    box-sizing: border-box;
    width: 100%;
    max-width: 100%;
    overflow: hidden;
}

.tags-subbar-label {
    font-size: 11.5px;
    font-weight: 700;
    color: #71717a;
    flex-shrink: 0;
}

.tags-pill-scroll {
    display: flex;
    align-items: center;
    gap: 6px;
    overflow-x: auto;
    scrollbar-width: none;
    flex: 1;
    min-width: 0;
    -webkit-overflow-scrolling: touch;
}
.tags-pill-scroll::-webkit-scrollbar {
    display: none;
}

.conv-tag-pill {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 10px;
    border-width: 0.5px;
    border-style: solid;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    transition: all 0.15s;
}

.conv-tag-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.remove-conv-tag {
    font-size: 13px;
    cursor: pointer;
    color: #ef4444;
    transition: opacity 0.15s;
}
.remove-conv-tag:hover {
    opacity: 0.75;
}

.add-more-tag-btn {
    background: rgba(99, 102, 241, 0.15);
    border: 0.5px solid rgba(99, 102, 241, 0.35);
    color: #a5b4fc;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    padding: 3px 10px;
    border-radius: 10px;
    transition: all 0.15s;
}
.add-more-tag-btn:hover {
    background: rgba(99, 102, 241, 0.25);
    color: #ffffff;
}

/* Bottom Sheet Modal Gắn Tag */
.conv-tag-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    z-index: 2500;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.conv-tag-sheet {
    width: 100%;
    max-height: 85vh;
    background: #141720;
    border-top-left-radius: 24px;
    border-top-right-radius: 24px;
    border: 0.5px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.6);
    padding: 10px 16px 28px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    animation: slideUp 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    overflow-y: auto;
}

.sheet-drag-handle {
    width: 36px;
    height: 4px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
    align-self: center;
    margin-bottom: 2px;
}

.conv-sheet-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 10px;
}

.conv-sheet-title-col {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.sheet-main-title {
    font-size: 16px;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: -0.2px;
}

.sheet-sub-title {
    font-size: 12px;
    color: #94a3b8;
}

.sheet-done-btn {
    background: #6366f1;
    border: none;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    padding: 6px 16px;
    border-radius: 16px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.4);
    transition: all 0.15s;
}
.sheet-done-btn:active {
    transform: scale(0.96);
}

.conv-sheet-body {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* Khu vực tạo thẻ mới trực tiếp */
.create-tag-box {
    background: rgba(26, 30, 42, 0.7);
    border: 0.5px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.create-tag-box-title {
    font-size: 11.5px;
    font-weight: 700;
    color: #818cf8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.create-tag-input-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.tag-color-preview-badge {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: 0 0 8px currentColor;
    border: 1px solid rgba(255, 255, 255, 0.4);
}

.new-tag-input {
    flex: 1;
    background: rgba(15, 17, 24, 0.8);
    border: 0.5px solid rgba(255, 255, 255, 0.14);
    border-radius: 12px;
    padding: 8px 12px;
    color: #ffffff;
    font-size: 13.5px;
    outline: none;
    transition: all 0.15s;
}
.new-tag-input:focus {
    border-color: #6366f1;
    background: rgba(15, 17, 24, 1);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25);
}
.new-tag-input::placeholder {
    color: #64748b;
}

.create-tag-action-btn {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border: none;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    padding: 8px 14px;
    border-radius: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
    transition: all 0.15s;
}
.create-tag-action-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    box-shadow: none;
}
.create-tag-action-btn:not(:disabled):active {
    transform: scale(0.96);
}

.color-palette-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-top: 2px;
}

.color-palette-label {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
}

.color-palette-dots {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.palette-dot-btn {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid transparent;
    cursor: pointer;
    padding: 0;
    transition: all 0.15s;
}
.palette-dot-btn.active {
    transform: scale(1.18);
    border-color: #ffffff;
    box-shadow: 0 0 10px rgba(255, 255, 255, 0.6);
}

.tags-list-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 4px;
}

.select-label {
    font-size: 12px;
    font-weight: 600;
    color: #94a3b8;
}

.tags-count-badge {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    background: rgba(255, 255, 255, 0.06);
    padding: 2px 8px;
    border-radius: 10px;
}

.no-tags-prompt {
    font-size: 13px;
    color: #64748b;
    text-align: center;
    padding: 18px 12px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    border: 0.5px dashed rgba(255, 255, 255, 0.1);
}

.conv-tags-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    max-height: 220px;
    overflow-y: auto;
    padding-right: 2px;
}

.tag-select-pill {
    background: rgba(26, 30, 42, 0.85);
    border: 0.5px solid rgba(255, 255, 255, 0.12);
    color: #e2e8f0;
    font-size: 13px;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 16px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
    user-select: none;
}
.tag-select-pill:active {
    transform: scale(0.97);
}

.tag-bullet {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.tag-text {
    flex: 1;
}

.tag-check-icon {
    font-size: 14px;
    color: #38bdf8;
    margin-left: 2px;
}

.delete-tag-btn {
    background: transparent;
    border: none;
    color: #64748b;
    font-size: 13px;
    padding: 2px;
    margin-left: 2px;
    cursor: pointer;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s;
}
.delete-tag-btn:hover {
    color: #f87171;
}

.avatar-orange-bg {
    width: 100%;
    height: 100%;
    background: #1c1c1e;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-text-fill,
.avatar-icon-fill {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-letter-small {
    font-size: 15px;
    font-weight: 700;
    color: #ffffff;
}

.emoji-fill {
    font-size: 18px;
}

.orange-emoji {
    font-size: 20px;
}

/* Nền chat cố định toàn khung nhìn — không bị mất/trôi khi cuộn tin nhắn */
.chat-wallpaper-fixed {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background-color: #07080c;
    background-image:
        radial-gradient(ellipse 70% 60% at 15% 10%, rgba(99, 102, 241, 0.16) 0%, transparent 65%),
        radial-gradient(ellipse 65% 55% at 85% 85%, rgba(168, 85, 247, 0.14) 0%, transparent 65%),
        radial-gradient(ellipse 55% 45% at 50% 50%, rgba(14, 165, 233, 0.08) 0%, transparent 60%),
        linear-gradient(180deg, #07080c 0%, #0d0f18 50%, #08090e 100%);
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

/* Hình nền hoa văn Vector Telegram cao cấp (Doodle pattern tinh xảo) */
.doodle-pattern-overlay {
    position: absolute;
    inset: 0;
    opacity: 0.055;
    background-image: url("data:image/svg+xml,%3Csvg width='120' height='120' viewBox='0 0 120 120' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23ffffff' stroke-width='1.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M15 22h18a4 4 0 0 1 4 4v12a4 4 0 0 1-4 4h-8l-6 5v-5h-4a4 4 0 0 1-4-4V26a4 4 0 0 1 4-4z'/%3E%3Ccircle cx='20' cy='32' r='1' fill='%23ffffff'/%3E%3Ccircle cx='24' cy='32' r='1' fill='%23ffffff'/%3E%3Ccircle cx='28' cy='32' r='1' fill='%23ffffff'/%3E%3Cpath d='M85 20l24 9-24 9 5-9-5-9z'/%3E%3Cpath d='M85 29l10 0'/%3E%3Cpath d='M25 80l2 4 4 2-4 2-2 4-2-4-4-2 4-2 2-4z'/%3E%3Cpath d='M95 75c-3-4-8-2-8 2 0 4 7 8 8 9 1-1 8-5 8-9 0-4-5-6-8-2z'/%3E%3Ccircle cx='55' cy='18' r='1.5' fill='%23ffffff'/%3E%3Ccircle cx='62' cy='25' r='1' fill='%23ffffff'/%3E%3Ccircle cx='105' cy='48' r='1.5' fill='%23ffffff'/%3E%3Ccircle cx='45' cy='95' r='1.5' fill='%23ffffff'/%3E%3Ccircle cx='80' cy='105' r='1' fill='%23ffffff'/%3E%3Ccircle cx='12' cy='105' r='1' fill='%23ffffff'/%3E%3Ccircle cx='60' cy='70' r='9'/%3E%3Ccircle cx='57' cy='68' r='1' fill='%23ffffff'/%3E%3Ccircle cx='63' cy='68' r='1' fill='%23ffffff'/%3E%3Cpath d='M56 73c1 2 3 2 4 2s3 0 4-2'/%3E%3C/g%3E%3C/svg%3E");
    background-repeat: repeat;
    background-size: 140px 140px;
    pointer-events: none;
}

/* 2. Phần Tin Nhắn Cuộn Tự Nhiên (Scrollable Area) */
.chat-scroll-area {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    position: relative;
    z-index: 2;
    overscroll-behavior-y: contain;
    background: transparent;
    -webkit-overflow-scrolling: touch;
}

.messages-inner-wrapper {
    position: relative;
    z-index: 2;
    padding: 8px 12px 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow-x: hidden;
}

/* Dividers */
.system-divider-row {
    display: flex;
    justify-content: center;
    margin: 8px 0;
}
.system-pill-badge {
    background: rgba(22, 25, 35, 0.85);
    color: #e4e4e7;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 16px;
    border-radius: 16px;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 0.5px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    letter-spacing: 0.2px;
}

.system-divider-unread-row {
    width: 100%;
    background: rgba(22, 25, 34, 0.88);
    border-top: 0.5px solid rgba(255, 255, 255, 0.08);
    border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
    text-align: center;
    padding: 5px 0;
    margin: 10px 0;
}
.unread-banner-text {
    font-size: 12px;
    font-weight: 600;
    color: #94a3b8;
    letter-spacing: 0.3px;
}

/* Message Rows */
.msg-row {
    display: flex;
    width: 100%;
}
.msg-row-other {
    justify-content: flex-start;
}
.msg-row-me {
    justify-content: flex-end;
}

/* Message Bubbles - Thiết kế Glassmorphism Tinh Xảo */
.msg-bubble {
    max-width: 82%;
    border-radius: 18px;
    padding: 8px 14px 7px;
    position: relative;
    font-size: 14.5px;
    line-height: 1.4;
    word-break: break-word;
    letter-spacing: -0.01em;
}

/* Tin nhắn đối phương: Dark Glass sang trọng */
.bubble-dark {
    background: rgba(26, 29, 39, 0.92);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    color: #f4f4f5;
    border: 0.5px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.06);
    border-bottom-left-radius: 4px;
}

/* Tin nhắn của mình: Gradient Tím - Indigo rực rỡ với ánh phát quang */
.bubble-purple {
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 45%, #7c3aed 100%);
    color: #ffffff;
    border: 0.5px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.38), inset 0 1px 0 rgba(255, 255, 255, 0.25);
    border-bottom-right-radius: 4px;
}

.bubble-content-text {
    display: inline;
}

.bubble-meta {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    float: right;
    margin-left: 10px;
    margin-top: 4px;
}

.bubble-time {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.65);
    font-variant-numeric: tabular-nums;
}

.bubble-ticks {
    display: inline-flex;
    align-items: center;
    font-size: 14px;
    color: #38bdf8;
}

/* Attachments */
.msg-attachment-img {
    margin: -3px -8px 6px;
    border-radius: 12px;
    overflow: hidden;
}
.msg-attachment-img img {
    width: 100%;
    max-height: 240px;
    object-fit: cover;
    display: block;
}

.msg-attachment-doc {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(0, 0, 0, 0.25);
    padding: 6px 10px;
    border-radius: 10px;
    margin-bottom: 4px;
}
.file-icon {
    font-size: 22px;
}
.file-meta {
    display: flex;
    flex-direction: column;
}
.file-name {
    font-size: 13px;
    font-weight: 600;
}
.file-size {
    font-size: 11px;
    opacity: 0.7;
}

/* 3. Drawer Preview Attachment */
.attachment-preview-drawer {
    background: #1c1c1e;
    padding: 8px 12px;
    border-top: 0.5px solid rgba(255, 255, 255, 0.1);
}
.preview-drawer-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #2c2c2e;
    padding: 6px 10px;
    border-radius: 10px;
}
.preview-thumb-img {
    width: 38px;
    height: 38px;
    border-radius: 6px;
    object-fit: cover;
}
.preview-thumb-file {
    font-size: 24px;
}
.preview-info-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.preview-file-name {
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.preview-file-size {
    font-size: 11px;
    color: #8e8e93;
}
.remove-attachment-btn {
    background: transparent;
    border: none;
    color: #8e8e93;
    font-size: 20px;
    cursor: pointer;
}

/* 4. Input Bar CỐ ĐỊNH CỨNG ở đáy (Chuẩn 100% theo ảnh & an toàn Home bar iPhone) */
.chat-bottom-bar {
    background: rgba(18, 20, 26, 0.95);
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    border-top: 0.5px solid rgba(255, 255, 255, 0.08);
    min-height: 54px;
    display: flex;
    align-items: center;
    padding: 7px 10px max(env(safe-area-inset-bottom, 0px), 7px);
    gap: 8px;
    flex-shrink: 0;
    position: relative;
    z-index: 100;
    box-sizing: border-box;
    width: 100%;
    max-width: 100%;
}

.hidden-file-input {
    display: none;
}

.bar-icon-button {
    background: transparent;
    border: none;
    color: #8e8e93;
    font-size: 26px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    transition: color 0.15s;
}
.bar-icon-button:hover,
.bar-icon-button:active {
    color: #ffffff;
}

.input-capsule-box {
    flex: 1;
    height: 38px;
    background-color: #22252d;
    border-radius: 20px;
    display: flex;
    align-items: center;
    padding: 0 12px;
    border: 0.5px solid rgba(255, 255, 255, 0.08);
}

.real-text-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: #ffffff;
    font-size: 15.5px;
}
.real-text-input::placeholder {
    color: #636366;
}

.emoji-button {
    background: transparent;
    border: none;
    color: #8e8e93;
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    padding: 2px;
}

.send-message-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #8b3aed;
    border: none;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    cursor: pointer;
    transition: transform 0.15s;
}
.send-message-btn:active {
    transform: scale(0.92);
}

/* iOS Home Indicator Bar */
.ios-home-indicator {
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 136px;
    height: 4.5px;
    background-color: #ffffff;
    border-radius: 3px;
    z-index: 1100;
    pointer-events: none;
}
</style>
