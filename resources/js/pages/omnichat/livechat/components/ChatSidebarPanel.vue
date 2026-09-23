<script setup lang="ts">
import { IonIcon } from '@ionic/vue';
import axios from 'axios';
import {
    addOutline,
    alertCircleOutline,
    callOutline,
    checkmarkCircle,
    closeOutline,
    createOutline,
    mailOutline,
    personOutline,
    pricetagOutline,
    saveOutline,
} from 'ionicons/icons';
import { onMounted, ref, watch } from 'vue';

import type { AssignedUser, ChatItem } from '../types/chat';

const props = defineProps<{
    chat: ChatItem;
    assignees: AssignedUser[];
    canAssign: boolean;
    allTags?: Array<{ id: string; name: string; color: string | null }>;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'profile-updated', data: { name: string; phone: string; email: string; notes: string }): void;
    (e: 'assign-updated', assignedUser: AssignedUser | null): void;
    (e: 'tag-created', tag: { id: string; name: string; color: string | null }): void;
}>();

// ── Edit state ────────────────────────────────────────────
const isEditing = ref(false);
const isSaving = ref(false);
const editName = ref(props.chat.name ?? '');
const editPhone = ref(props.chat.phone ?? '');
const editEmail = ref(props.chat.contactEmail ?? '');
const editNotes = ref(props.chat.contactNotes ?? '');

watch(() => props.chat, (c) => {
    if (!isEditing.value) {
        editName.value = c.name ?? '';
        editPhone.value = c.phone ?? '';
        editEmail.value = c.contactEmail ?? '';
        editNotes.value = c.contactNotes ?? '';
    }
}, { immediate: true });

// ── Toast ─────────────────────────────────────────────────
type ToastType = 'ok' | 'err';
const toastMsg = ref('');
const toastType = ref<ToastType>('ok');
let toastTimer: ReturnType<typeof setTimeout> | undefined;

const showToast = (msg: string, type: ToastType = 'ok') => {
    clearTimeout(toastTimer);
    toastMsg.value = msg;
    toastType.value = type;
    toastTimer = setTimeout(() => { toastMsg.value = ''; }, 2800);
};

// ── Save profile ──────────────────────────────────────────
const saveProfile = async (): Promise<void> => {
    if (!props.chat.contactId || isSaving.value) return;
    isSaving.value = true;
    try {
        await axios.put(`/omnichat/livechat/contacts/${props.chat.contactId}`, {
            display_name: editName.value.trim() || props.chat.name,
            phone: editPhone.value.trim() || null,
            email: editEmail.value.trim() || null,
            notes: editNotes.value.trim() || null,
            tag_ids: props.chat.tagIds ?? [],
        });
        isEditing.value = false;
        emit('profile-updated', {
            name:  editName.value.trim() || props.chat.name,
            phone: editPhone.value.trim(),
            email: editEmail.value.trim(),
            notes: editNotes.value.trim(),
        });
        showToast('Đã cập nhật thông tin khách hàng!', 'ok');
    } catch {
        showToast('Cập nhật thất bại, thử lại sau.', 'err');
    } finally {
        isSaving.value = false;
    }
};

// ── Assign ────────────────────────────────────────────────
const currentAssigneeId = ref(props.chat.assignedUser?.id ?? '');
const isAssigning = ref(false);

watch(() => props.chat.assignedUser, (u) => {
    currentAssigneeId.value = u?.id ?? '';
});

const assignConversation = async (userId: string): Promise<void> => {
    if (isAssigning.value || props.chat.id.startsWith('contact_')) return;
    isAssigning.value = true;
    currentAssigneeId.value = userId;
    try {
        const { data } = await axios.put(
            `/omnichat/conversations/${props.chat.id}/assignment`,
            { user_id: userId || null },
        );
        const updated = (data as { assigned_user: AssignedUser | null }).assigned_user;
        emit('assign-updated', updated);
        showToast(updated ? `Đã giao cho ${updated.name}` : 'Đã bỏ phân công', 'ok');
    } catch {
        currentAssigneeId.value = props.chat.assignedUser?.id ?? '';
        showToast('Phân công thất bại, thử lại.', 'err');
    } finally {
        isAssigning.value = false;
    }
};

// ── Avatar initials ───────────────────────────────────────
const initials = (name: string): string =>
    name.split(/\s+/).map(p => p[0]?.toUpperCase() ?? '').slice(0, 2).join('');

const COLORS = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'];
const avatarColor = (id: string): string => {
    let h = 0;
    for (let i = 0; i < id.length; i++) h = id.charCodeAt(i) + ((h << 5) - h);
    return COLORS[Math.abs(h) % COLORS.length];
};
// ── Tags logic ──────────────────────────────────────────
const availableTags = ref<Array<{ id: string; name: string; color?: string | null }>>([]);
const isManagingTags = ref(false);
const newTagName = ref('');
const newTagColor = ref('#6366f1');
const isCreatingTag = ref(false);
const PRESET_TAG_COLORS = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#ef4444', '#14b8a6'];

const loadTags = async () => {
    try {
        const { data } = await axios.get('/omnichat/livechat/tags');
        availableTags.value = data.data;
    } catch {
        if (props.allTags) availableTags.value = props.allTags;
    }
};

watch(() => props.allTags, (tags) => {
    if (tags && tags.length > 0) availableTags.value = tags;
}, { immediate: true });

onMounted(loadTags);

const getTagColor = (tagName: string): string => {
    return availableTags.value.find(t => t.name === tagName)?.color || '#6366f1';
};

const toggleTag = async (tag: { id: string; name: string }) => {
    if (!props.chat.tags) props.chat.tags = [];
    if (!props.chat.tagIds) props.chat.tagIds = [];
    const nIdx = props.chat.tags.indexOf(tag.name as any);
    const iIdx = props.chat.tagIds.indexOf(tag.id);
    if (nIdx > -1) {
        props.chat.tags.splice(nIdx, 1);
        if (iIdx > -1) props.chat.tagIds.splice(iIdx, 1);
    } else {
        props.chat.tags.push(tag.name as any);
        if (iIdx === -1 && tag.id) props.chat.tagIds.push(tag.id);
    }
    try {
        if (props.chat.contactId) {
            await axios.put(`/omnichat/livechat/contacts/${props.chat.contactId}/conversation-tags`, {
                conversation_id: props.chat.id,
                tag_ids: props.chat.tagIds ?? [],
            });
        } else if (!props.chat.id.startsWith('contact_')) {
            await axios.put(`/omnichat/conversations/${props.chat.id}/tags`, {
                tag_ids: props.chat.tagIds ?? [],
            });
        }
    } catch {
        // optimistic
    }
};

const createTag = async () => {
    const name = newTagName.value.trim();
    if (!name || isCreatingTag.value) return;
    isCreatingTag.value = true;
    try {
        const { data } = await axios.post('/omnichat/livechat/tags', {
            name,
            color: newTagColor.value,
        });
        const created = data.tag;
        if (!availableTags.value.some(t => t.id === created.id)) {
            availableTags.value.push(created);
        }
        await toggleTag(created);
        emit('tag-created', created);
        newTagName.value = '';
        showToast('Đã tạo và gắn thẻ!', 'ok');
    } catch {
        showToast('Không tạo được thẻ', 'err');
    } finally {
        isCreatingTag.value = false;
    }
};
</script>

<template>
    <div class="csp-backdrop" @click.self="emit('close')">
        <div class="csp-panel">

            <!-- ── Header ── -->
            <div class="csp-header">
                <span class="csp-title">Thông tin khách</span>
                <button class="csp-close-btn" @click="emit('close')">
                    <ion-icon :icon="closeOutline" />
                </button>
            </div>

            <!-- ── Avatar + Name ── -->
            <div class="csp-hero">
                <div
                    class="csp-avatar"
                    :style="{ background: avatarColor(chat.contactId ?? chat.id) }"
                >
                    {{ initials(chat.name) }}
                </div>
                <div class="csp-hero-info">
                    <p class="csp-hero-name">{{ chat.name }}</p>
                    <span v-if="chat.channelSource" class="csp-channel-pill" :class="`ch-${chat.channelSource}`">
                        <template v-if="chat.channelSource === 'telegram'">✈️ Telegram</template>
                        <template v-else-if="chat.channelSource === 'zalo'">💬 Zalo OA</template>
                        <template v-else-if="chat.channelSource === 'website'">🌐 Website</template>
                        <template v-else>🔵 Facebook</template>
                    </span>
                </div>
                <button
                    class="csp-edit-btn"
                    :class="{ active: isEditing }"
                    @click="isEditing = !isEditing"
                >
                    <ion-icon :icon="createOutline" />
                </button>
            </div>

            <!-- ── Profile fields ── -->
            <div class="csp-section">
                <p class="csp-section-label">Thông tin liên hệ</p>

                <!-- Tên -->
                <div class="csp-field">
                    <span class="csp-field-icon"><ion-icon :icon="personOutline" /></span>
                    <div class="csp-field-body">
                        <span class="csp-field-lbl">Tên hiển thị</span>
                        <input v-if="isEditing" v-model="editName" class="csp-input" placeholder="Nhập tên..." />
                        <span v-else class="csp-field-val">{{ chat.name || '—' }}</span>
                    </div>
                </div>

                <!-- SĐT -->
                <div class="csp-field">
                    <span class="csp-field-icon"><ion-icon :icon="callOutline" /></span>
                    <div class="csp-field-body">
                        <span class="csp-field-lbl">Số điện thoại</span>
                        <input v-if="isEditing" v-model="editPhone" class="csp-input" placeholder="Nhập SĐT..." />
                        <span v-else class="csp-field-val">{{ chat.phone || '—' }}</span>
                    </div>
                </div>

                <!-- Email -->
                <div class="csp-field">
                    <span class="csp-field-icon"><ion-icon :icon="mailOutline" /></span>
                    <div class="csp-field-body">
                        <span class="csp-field-lbl">Email</span>
                        <input v-if="isEditing" v-model="editEmail" class="csp-input" placeholder="Nhập email..." />
                        <span v-else class="csp-field-val">{{ chat.contactEmail || '—' }}</span>
                    </div>
                </div>

                <!-- Ghi chú -->
                <div class="csp-field csp-field-col">
                    <div class="csp-field-body">
                        <span class="csp-field-lbl">Ghi chú</span>
                        <textarea
                            v-if="isEditing"
                            v-model="editNotes"
                            class="csp-input csp-textarea"
                            placeholder="Thêm ghi chú..."
                            rows="3"
                        />
                        <span v-else class="csp-field-val csp-notes">{{ chat.contactNotes || '—' }}</span>
                    </div>
                </div>

                <!-- Save button -->
                <button
                    v-if="isEditing"
                    class="csp-save-btn"
                    :disabled="isSaving"
                    @click="saveProfile"
                >
                    <ion-icon :icon="saveOutline" />
                    {{ isSaving ? 'Đang lưu…' : 'Lưu thay đổi' }}
                </button>
            </div>

            <!-- ── Tags / Thẻ phân loại ── -->
            <div class="csp-section">
                <div class="csp-section-hdr">
                    <p class="csp-section-label">Thẻ phân loại</p>
                    <button class="csp-tag-toggle-btn" @click="isManagingTags = !isManagingTags">
                        {{ isManagingTags ? 'Thu gọn' : '+ Gắn / Tạo thẻ' }}
                    </button>
                </div>

                <!-- Active tags chips -->
                <div class="csp-tags-list">
                    <span
                        v-for="t in chat.tags"
                        :key="t"
                        class="csp-tag-chip"
                        :style="{
                            backgroundColor: getTagColor(t) + '22',
                            borderColor: getTagColor(t) + '55',
                            color: getTagColor(t),
                        }"
                    >
                        <span class="csp-tag-dot" :style="{ backgroundColor: getTagColor(t) }"></span>
                        {{ t }}
                        <ion-icon
                            :icon="closeOutline"
                            class="csp-tag-del"
                            @click="toggleTag({ id: chat.tagIds?.[chat.tags?.indexOf(t) ?? -1] ?? '', name: t })"
                        />
                    </span>
                    <span v-if="!chat.tags || chat.tags.length === 0" class="csp-no-tags">
                        Chưa có thẻ phân loại
                    </span>
                </div>

                <!-- Tag Management & Quick Create Drawer -->
                <div v-if="isManagingTags" class="csp-tag-manager">
                    <!-- Inline create form -->
                    <div class="csp-create-tag-row">
                        <span class="csp-create-dot" :style="{ backgroundColor: newTagColor }"></span>
                        <input
                            v-model="newTagName"
                            type="text"
                            class="csp-tag-input"
                            placeholder="Tên thẻ mới..."
                            maxlength="40"
                            @keyup.enter="createTag"
                        />
                        <button
                            class="csp-tag-add-btn"
                            :disabled="!newTagName.trim() || isCreatingTag"
                            @click="createTag"
                        >
                            {{ isCreatingTag ? '...' : '+ Thêm' }}
                        </button>
                    </div>

                    <!-- Colors -->
                    <div class="csp-palette-row">
                        <button
                            v-for="c in PRESET_TAG_COLORS"
                            :key="c"
                            type="button"
                            class="csp-palette-dot"
                            :class="{ active: newTagColor === c }"
                            :style="{ backgroundColor: c }"
                            @click="newTagColor = c"
                        ></button>
                    </div>

                    <!-- Available tags to toggle -->
                    <div class="csp-available-tags">
                        <span class="csp-avail-lbl">Chạm để chọn thẻ:</span>
                        <div class="csp-avail-chips">
                            <button
                                v-for="tag in availableTags"
                                :key="tag.id"
                                type="button"
                                class="csp-avail-chip"
                                :class="{ active: chat.tags?.includes(tag.name as any) }"
                                :style="chat.tags?.includes(tag.name as any) ? {
                                    backgroundColor: (tag.color || '#6366f1') + '33',
                                    borderColor: tag.color || '#6366f1',
                                    color: '#ffffff',
                                } : {}"
                                @click="toggleTag(tag)"
                            >
                                <span class="csp-tag-dot" :style="{ backgroundColor: tag.color || '#6366f1' }"></span>
                                {{ tag.name }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Assign ── -->
            <div v-if="canAssign" class="csp-section">
                <p class="csp-section-label">Phân công nhân viên</p>
                <div class="csp-assign-wrap">
                    <div
                        v-if="chat.assignedUser || currentAssigneeId"
                        class="csp-assignee-chip"
                    >
                        <div
                            class="csp-assignee-av"
                            :style="{ background: avatarColor(currentAssigneeId) }"
                        >
                            {{ initials(assignees.find(a => a.id === currentAssigneeId)?.name ?? '?') }}
                        </div>
                        <span>{{ assignees.find(a => a.id === currentAssigneeId)?.name ?? 'Đã giao' }}</span>
                    </div>
                    <select
                        class="csp-assign-select"
                        :value="currentAssigneeId"
                        :disabled="isAssigning"
                        @change="assignConversation(($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">— Chưa phân công —</option>
                        <option v-for="a in assignees" :key="a.id" :value="a.id">
                            {{ a.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- ── Toast ── -->
            <transition name="csp-toast">
                <div
                    v-if="toastMsg"
                    class="csp-toast"
                    :class="toastType === 'ok' ? 'toast-ok' : 'toast-err'"
                >
                    <ion-icon :icon="toastType === 'ok' ? checkmarkCircle : alertCircleOutline" />
                    {{ toastMsg }}
                </div>
            </transition>
        </div>
    </div>
</template>

<style scoped>
/* ═══════════════════════════════════════════════════════
   ChatSidebarPanel — slide-up bottom sheet
   ═══════════════════════════════════════════════════════ */

.csp-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    z-index: 300;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    backdrop-filter: blur(4px);
    animation: csp-fade-in 0.18s ease;
}

@keyframes csp-fade-in {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.csp-panel {
    width: 100%;
    max-width: 480px;
    max-height: 88vh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    background: #111115;
    border-radius: 24px 24px 0 0;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding: 0 0 env(safe-area-inset-bottom, 24px);
    animation: csp-slide-up 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes csp-slide-up {
    from { transform: translateY(100%); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}

/* ── Header ── */
.csp-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 16px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    position: sticky;
    top: 0;
    background: #111115;
    z-index: 1;
}

.csp-title {
    font-size: 16px;
    font-weight: 700;
    color: #f4f4f5;
    letter-spacing: -0.3px;
}

.csp-close-btn {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    border: none;
    color: #71717a;
    font-size: 16px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: background 0.15s;
}
.csp-close-btn:active { background: rgba(255, 255, 255, 0.12); }

/* ── Hero ── */
.csp-hero {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
}

.csp-avatar {
    width: 52px; height: 52px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; font-weight: 800;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.csp-hero-info { flex: 1; min-width: 0; }

.csp-hero-name {
    font-size: 16px; font-weight: 700; color: #f4f4f5;
    margin: 0 0 4px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.csp-channel-pill {
    display: inline-flex;
    align-items: center;
    font-size: 10px; font-weight: 600;
    padding: 2px 8px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.07);
    color: #a1a1aa;
    border: 1px solid rgba(255, 255, 255, 0.07);
}

.csp-edit-btn {
    width: 34px; height: 34px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.07);
    color: #71717a;
    font-size: 15px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    flex-shrink: 0;
}
.csp-edit-btn.active,
.csp-edit-btn:active {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.3);
    color: #818cf8;
}

/* ── Section ── */
.csp-section {
    padding: 12px 16px 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.csp-section-label {
    font-size: 10.5px; font-weight: 700;
    color: #52525b;
    text-transform: uppercase; letter-spacing: 0.6px;
    margin: 0 0 10px;
}

/* ── Fields ── */
.csp-field {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}
.csp-field:last-of-type { border-bottom: none; }
.csp-field-col { flex-direction: column; }

.csp-field-icon {
    width: 28px; height: 28px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.04);
    display: flex; align-items: center; justify-content: center;
    color: #52525b; font-size: 13px;
    flex-shrink: 0;
    margin-top: 2px;
}

.csp-field-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.csp-field-lbl { font-size: 10px; font-weight: 700; color: #52525b; text-transform: uppercase; letter-spacing: 0.4px; }
.csp-field-val { font-size: 13.5px; color: #d4d4d8; line-height: 1.4; word-break: break-word; }
.csp-notes { white-space: pre-wrap; color: #a1a1aa; font-size: 12.5px; }

.csp-input {
    width: 100%;
    box-sizing: border-box;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 9px;
    padding: 8px 10px;
    font-size: 13px;
    color: #f4f4f5;
    outline: none;
    font-family: inherit;
    transition: border-color 0.18s;
}
.csp-input:focus { border-color: rgba(99, 102, 241, 0.6); }
.csp-input::placeholder { color: #3f3f46; }
.csp-textarea { resize: none; }

.csp-save-btn {
    display: flex; align-items: center; justify-content: center; gap: 7px;
    margin-top: 8px;
    padding: 11px;
    background: #6366f1;
    border: none;
    border-radius: 12px;
    color: #fff;
    font-size: 13.5px; font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
}
.csp-save-btn:active { transform: scale(0.98); }
.csp-save-btn:disabled { opacity: 0.45; cursor: default; box-shadow: none; }
.csp-save-btn ion-icon { font-size: 15px; }

/* ── Assign ── */
.csp-assign-wrap { display: flex; flex-direction: column; gap: 10px; }

.csp-assignee-chip {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.18);
    border-radius: 10px;
}
.csp-assignee-av {
    width: 26px; height: 26px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 800; color: #fff;
    flex-shrink: 0;
}
.csp-assignee-chip span { font-size: 13px; font-weight: 600; color: #818cf8; }

.csp-assign-select {
    width: 100%;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 13px;
    color: #d4d4d8;
    outline: none;
    font-family: inherit;
    appearance: none;
    cursor: pointer;
    transition: border-color 0.18s;
}
.csp-assign-select:focus { border-color: rgba(99, 102, 241, 0.4); }
.csp-assign-select:disabled { opacity: 0.5; cursor: default; }

/* ── Tags in sidebar ── */
.csp-section-hdr {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}
.csp-tag-toggle-btn {
    background: transparent;
    border: none;
    color: #818cf8;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
}
.csp-tags-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    min-height: 28px;
    align-items: center;
}
.csp-tag-chip {
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 10px;
    border-width: 0.5px;
    border-style: solid;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.csp-tag-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.csp-tag-del {
    font-size: 13px;
    cursor: pointer;
    color: #ef4444;
}
.csp-tag-del:hover {
    opacity: 0.8;
}
.csp-no-tags {
    font-size: 12px;
    color: #52525b;
    font-style: italic;
}
.csp-tag-manager {
    margin-top: 10px;
    padding: 10px;
    background: rgba(255, 255, 255, 0.03);
    border: 0.5px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.csp-create-tag-row {
    display: flex;
    align-items: center;
    gap: 6px;
}
.csp-create-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.csp-tag-input {
    flex: 1;
    background: rgba(0, 0, 0, 0.4);
    border: 0.5px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    padding: 6px 8px;
    color: #ffffff;
    font-size: 12px;
    outline: none;
}
.csp-tag-input:focus {
    border-color: #6366f1;
}
.csp-tag-add-btn {
    background: #6366f1;
    border: none;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    padding: 6px 10px;
    border-radius: 8px;
    cursor: pointer;
}
.csp-tag-add-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.csp-palette-row {
    display: flex;
    gap: 6px;
    align-items: center;
}
.csp-palette-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 1.5px solid transparent;
    cursor: pointer;
    padding: 0;
}
.csp-palette-dot.active {
    border-color: #ffffff;
    transform: scale(1.2);
}
.csp-available-tags {
    margin-top: 4px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.csp-avail-lbl {
    font-size: 10.5px;
    font-weight: 700;
    color: #71717a;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.csp-avail-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    max-height: 120px;
    overflow-y: auto;
}
.csp-avail-chip {
    background: rgba(255, 255, 255, 0.05);
    border: 0.5px solid rgba(255, 255, 255, 0.1);
    color: #a1a1aa;
    font-size: 11.5px;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s;
}
.csp-avail-chip.active {
    border-color: #6366f1;
    color: #ffffff;
}

/* ── Toast ── */
.csp-toast {
    position: fixed;
    bottom: 100px;
    left: 50%;
    transform: translateX(-50%);
    display: flex; align-items: center; gap: 7px;
    padding: 9px 18px;
    border-radius: 999px;
    font-size: 13px; font-weight: 600;
    white-space: nowrap;
    z-index: 400;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
}
.csp-toast ion-icon { font-size: 15px; }
.toast-ok { background: rgba(15, 25, 15, 0.96); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.25); }
.toast-err { background: rgba(25, 10, 10, 0.96); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.25); }

.csp-toast-enter-active, .csp-toast-leave-active { transition: opacity 0.22s, transform 0.22s; }
.csp-toast-enter-from, .csp-toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(10px); }
</style>
