<script setup lang="ts">
import { IonIcon } from '@ionic/vue';
import axios from 'axios';
import {
    alertCircleOutline,
    checkmarkCircle,
    chevronForward,
    earthOutline,
    eyeOffOutline,
    eyeOutline,
    flashOffOutline,
    flashOutline,
    globeOutline,
    keyOutline,
    logOutOutline,
    notificationsOutline,
    sparklesOutline,
    starOutline,
    trashOutline,
} from 'ionicons/icons';
import { computed, onMounted, ref } from 'vue';

type ConnectedChannel = {
    id: string;
    provider: string;
    name: string;
    avatar_url: string | null;
    status: string;
    is_active: boolean;
};

const props = defineProps<{
    user?: { name: string; avatar_url?: string | null };
    channels?: ConnectedChannel[];
    canManageAiSettings?: boolean;
}>();

const emit = defineEmits<{
    (e: 'logout'): void;
}>();

const userName = computed(() => props.user?.name || 'Người dùng');
const initials = computed(() =>
    userName.value
        .split(/\s+/)
        .map((part) => part.charAt(0).toUpperCase())
        .slice(0, 2)
        .join(''),
);
const connectedChannels = computed(() => props.channels ?? []);
const activeChannelCount = computed(
    () => connectedChannels.value.filter((c) => c.is_active).length,
);

const channelHint = (provider: string): string => {
    const hints: Record<string, string> = {
        facebook: 'Facebook Messenger & Pages',
        instagram: 'Instagram Direct Messages',
        telegram: 'Telegram Bot & Channels',
        zalo: 'Zalo Official Account (OA)',
        website: 'Website LiveChat Widget',
    };
    return hints[provider] ?? 'Kênh nhắn tin đa nền tảng';
};

const apiKey = ref('');
const apiKeyHasBeenSet = ref(false);
const showApiKey = ref(false);
const isAiEnabled = ref(false);
const isSaving = ref(false);
const isSaved = ref(false);
const saveError = ref(false);
const pushSupported = ref(false);
const pushEnabled = ref(false);
const pushLoading = ref(false);
const pushStatus = ref<'idle' | 'loading' | 'success' | 'error'>('idle');
const pushErrorMessage = ref('');

const enablePush = async (): Promise<void> => {
    if (!pushSupported.value) return;
    pushLoading.value = true;
    pushStatus.value = 'loading';
    pushErrorMessage.value = '';
    try {
        const registration = await navigator.serviceWorker.register('/sw.js');
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') return;

        const vapidKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;
        if (!vapidKey) throw new Error('Missing VAPID public key');
        let subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
            const padded = vapidKey.replace(/-/g, '+').replace(/_/g, '/');
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: Uint8Array.from(atob(padded), (char) => char.charCodeAt(0)),
            });
        }
        const json = subscription.toJSON();
        const response = await fetch('/push-subscriptions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                endpoint: json.endpoint,
                publicKey: json.keys?.p256dh,
                authToken: json.keys?.auth,
                contentEncoding: 'aes128gcm',
            }),
        });
        if (!response.ok) throw new Error(`Push registration failed: ${response.status}`);
        pushEnabled.value = true;
        pushStatus.value = 'success';
    } catch {
        pushStatus.value = 'error';
        pushErrorMessage.value = 'Không đăng ký được. Hãy thử lại.';
        saveError.value = true;
        setTimeout(() => { saveError.value = false; }, 3000);
    } finally {
        pushLoading.value = false;
    }
};

const toggleShowApiKey = () => {
    showApiKey.value = !showApiKey.value;
};

const loadSettings = async (): Promise<void> => {
    try {
        const { data } = await axios.get('/omnichat/livechat/settings');
        isAiEnabled.value = (data as { ai_enabled: boolean }).ai_enabled;
        apiKeyHasBeenSet.value = (data as { api_key_set: boolean }).api_key_set;
    } catch {
        // Non-blocking: keep local defaults when the endpoint is unreachable.
    }
};

onMounted(loadSettings);

// ── Quản lý danh sách Bot Dify (admin) ──────────────────────
type AiBot = {
    id: string;
    name: string;
    dify_base_url: string | null;
    key_set: boolean;
    is_active: boolean;
    is_default: boolean;
};

const bots = ref<AiBot[]>([]);
const isLoadingBots = ref(false);
const newBotName = ref('');
const newBotKey = ref('');
const newBotBaseUrl = ref('');
const isCreatingBot = ref(false);
const botError = ref('');

const loadBots = async (): Promise<void> => {
    isLoadingBots.value = true;
    try {
        const { data } = await axios.get('/omnichat/livechat/ai-bots');
        bots.value = data.data as AiBot[];
    } catch {
        bots.value = [];
    } finally {
        isLoadingBots.value = false;
    }
};

onMounted(() => {
    pushSupported.value = 'serviceWorker' in navigator && 'PushManager' in window;
    if (props.canManageAiSettings) {
        loadBots();
    }
});

const addBot = async (): Promise<void> => {
    if (isCreatingBot.value) return;
    if (!newBotName.value.trim() || !newBotKey.value.trim()) {
        botError.value = 'Vui lòng nhập tên bot và API key.';
        return;
    }
    isCreatingBot.value = true;
    botError.value = '';
    try {
        await axios.post('/omnichat/livechat/ai-bots', {
            name: newBotName.value.trim(),
            dify_api_key: newBotKey.value.trim(),
            dify_base_url: newBotBaseUrl.value.trim() || null,
            is_active: true,
        });
        newBotName.value = '';
        newBotKey.value = '';
        newBotBaseUrl.value = '';
        await loadBots();
    } catch {
        botError.value = 'Không tạo được bot. Kiểm tra lại thông tin.';
    } finally {
        isCreatingBot.value = false;
    }
};

const updateBot = async (
    bot: AiBot,
    payload: Record<string, unknown>,
): Promise<void> => {
    try {
        const { data } = await axios.put(
            `/omnichat/livechat/ai-bots/${bot.id}`,
            payload,
        );
        const updated = (data as { bot: AiBot }).bot;
        const index = bots.value.findIndex((b) => b.id === bot.id);
        if (index !== -1) {
            bots.value[index] = updated;
        }
    } catch {
        // Non-blocking: reload to reconcile on next open.
        loadBots();
    }
};

const toggleBotActive = (bot: AiBot): void => {
    updateBot(bot, { is_active: !bot.is_active });
};

const setDefaultBot = (bot: AiBot): void => {
    if (bot.is_default) return;
    updateBot(bot, { is_default: true, is_active: true });
    bots.value = bots.value.map((b) => ({ ...b, is_default: b.id === bot.id }));
};

const deleteBot = async (bot: AiBot): Promise<void> => {
    try {
        await axios.delete(`/omnichat/livechat/ai-bots/${bot.id}`);
        bots.value = bots.value.filter((b) => b.id !== bot.id);
    } catch {
        // Ignore: bot remains listed.
    }
};

// ── AI từng kênh: admin gán bot, sale bật/tắt ─────────────────
type ChannelAiState = {
    id: string;
    name: string;
    provider: string;
    type: string;
    ai_enabled: boolean;
    bot_id: string | null;
};

const channelAiStates = ref<ChannelAiState[]>([]);

const channelAiByld = computed(() => {
    const map: Record<string, ChannelAiState> = {};
    for (const state of channelAiStates.value) {
        map[state.id] = state;
    }
    return map;
});

const loadChannelAi = async (): Promise<void> => {
    try {
        const { data } = await axios.get('/omnichat/livechat/channel-ai');
        channelAiStates.value = data.data as ChannelAiState[];
    } catch {
        channelAiStates.value = [];
    }
};

onMounted(loadChannelAi);

const updateChannelAi = async (
    state: ChannelAiState,
    payload: Record<string, unknown>,
): Promise<void> => {
    try {
        const { data } = await axios.put(
            `/omnichat/livechat/channel-ai/${state.id}`,
            payload,
        );
        const updated = data as { ai_enabled: boolean; bot_id: string | null };
        state.ai_enabled = updated.ai_enabled;
        state.bot_id = updated.bot_id;
    } catch {
        // Non-blocking: keep the previous state on failure.
    }
};

const toggleChannelAi = (state: ChannelAiState): void => {
    state.ai_enabled = !state.ai_enabled;
    updateChannelAi(state, { ai_enabled: state.ai_enabled });
};

const setChannelBot = (state: ChannelAiState, botId: string): void => {
    state.bot_id = botId || null;
    updateChannelAi(state, { ai_enabled: state.ai_enabled, bot_id: state.bot_id });
};

const saveSettings = async (): Promise<void> => {
    if (isSaving.value) return;
    isSaving.value = true;
    saveError.value = false;
    try {
        const { data } = await axios.put('/omnichat/livechat/settings', {
            ai_enabled: isAiEnabled.value,
            dify_api_key: apiKey.value || undefined,
        });
        apiKeyHasBeenSet.value = (data as { api_key_set: boolean }).api_key_set;
        apiKey.value = '';
        isSaved.value = true;
        setTimeout(() => {
            isSaved.value = false;
        }, 2000);
    } catch {
        saveError.value = true;
        setTimeout(() => {
            saveError.value = false;
        }, 3000);
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <div class="profile-page">

        <!-- ── Sticky Header ── -->
        <div class="pf-topbar">
            <h1 class="pf-title">Cài đặt</h1>
            <button
                class="pf-save-btn"
                :class="{ saved: isSaved }"
                :disabled="isSaving"
                @click="saveSettings"
            >
                <ion-icon v-if="isSaved" :icon="checkmarkCircle" />
                <ion-icon v-else :icon="isSaving ? sparklesOutline : keyOutline" />
                <span>{{ isSaving ? 'Đang lưu…' : isSaved ? 'Đã lưu' : 'Lưu' }}</span>
            </button>
        </div>

        <div class="pf-scroll">

            <!-- ── Profile Banner ── -->
            <div class="pf-banner">
                <div class="pf-banner-bg" />
                <div class="pf-avatar-ring">
                    <div class="pf-avatar">{{ initials }}</div>
                </div>
                <div class="pf-banner-info">
                    <p class="pf-name">{{ userName }}</p>
                    <span class="pf-badge">
                        <ion-icon :icon="earthOutline" />
                        Omnichat Admin
                    </span>
                </div>
                <div class="pf-stats-row">
                    <div class="pf-stat">
                        <strong>{{ activeChannelCount }}</strong>
                        <span>Hoạt động</span>
                    </div>
                    <div class="pf-stat-div" />
                    <div class="pf-stat">
                        <strong>{{ connectedChannels.length }}</strong>
                        <span>Kênh</span>
                    </div>
                    <div class="pf-stat-div" />
                    <div class="pf-stat">
                        <strong class="pf-live">●</strong>
                        <span>Online</span>
                    </div>
                </div>
            </div>

            <!-- ── AI Configuration ── -->
            <div v-if="canManageAiSettings">

                <!-- Dify API Key -->
                <p class="pf-section-label">
                    <ion-icon :icon="sparklesOutline" />
                    Trợ lý AI
                </p>

                <div class="pf-card">
                    <!-- Toggle AI -->
                    <div class="pf-row">
                        <div class="pf-row-icon pf-icon-purple">
                            <ion-icon :icon="sparklesOutline" />
                        </div>
                        <div class="pf-row-body">
                            <p class="pf-row-title">Tự động trả lời AI</p>
                            <p class="pf-row-sub">Trả lời khách 24/7 trên các kênh đang bật</p>
                        </div>
                        <label class="pf-toggle">
                            <input v-model="isAiEnabled" type="checkbox" />
                            <span class="pf-track"><span class="pf-thumb" /></span>
                        </label>
                    </div>

                    <div class="pf-divider" />

                    <!-- Dify API Key input -->
                    <div class="pf-row pf-row-col">
                        <div class="pf-key-row">
                            <div class="pf-row-icon pf-icon-slate">
                                <ion-icon :icon="keyOutline" :class="{ 'pf-icon-ok': apiKeyHasBeenSet }" />
                            </div>
                            <p class="pf-row-title">Dify API Key</p>
                        </div>
                        <div class="pf-input-wrap">
                            <input
                                v-model="apiKey"
                                :type="showApiKey ? 'text' : 'password'"
                                class="pf-input"
                                :placeholder="apiKeyHasBeenSet ? 'Key đã cấu hình — nhập key mới để thay' : 'Nhập Dify API Key…'"
                            />
                            <button class="pf-eye-btn" type="button" @click="toggleShowApiKey">
                                <ion-icon :icon="showApiKey ? eyeOffOutline : eyeOutline" />
                            </button>
                        </div>
                        <p class="pf-input-hint">Key lưu trên server, không hiển thị lại sau khi lưu.</p>
                    </div>
                </div>

                <!-- Danh sách Bot -->
                <p class="pf-section-label">
                    <ion-icon :icon="starOutline" />
                    Bot Dify
                    <span class="pf-count">{{ bots.length }}</span>
                </p>

                <div class="pf-card">
                    <div v-if="isLoadingBots" class="pf-empty">Đang tải bot…</div>
                    <div v-else-if="bots.length === 0" class="pf-empty">Chưa có bot — thêm bot đầu tiên bên dưới</div>

                    <template v-for="(bot, idx) in bots" :key="bot.id">
                        <div class="pf-bot-row">
                            <div class="pf-bot-dot" :class="bot.is_active ? 'dot-on' : 'dot-off'" />
                            <div class="pf-bot-body">
                                <div class="pf-bot-name-line">
                                    <span class="pf-bot-name">{{ bot.name }}</span>
                                    <span v-if="bot.is_default" class="pf-default-chip">Mặc định</span>
                                </div>
                                <p class="pf-bot-sub">{{ bot.key_set ? "API key đã cấu hình" : "Chưa có key" }}<template v-if="bot.dify_base_url"> · {{ bot.dify_base_url }}</template></p>
                            </div>
                            <div class="pf-bot-actions">
                                <button
                                    class="pf-icon-btn"
                                    :class="bot.is_active ? 'btn-green' : ''"
                                    :title="bot.is_active ? 'Tắt bot' : 'Bật bot'"
                                    @click="toggleBotActive(bot)"
                                >
                                    <ion-icon :icon="bot.is_active ? flashOutline : flashOffOutline" />
                                </button>
                                <button
                                    class="pf-icon-btn"
                                    :class="bot.is_default ? 'btn-yellow' : ''"
                                    :disabled="bot.is_default"
                                    title="Đặt làm mặc định"
                                    @click="setDefaultBot(bot)"
                                >
                                    <ion-icon :icon="starOutline" />
                                </button>
                                <button
                                    class="pf-icon-btn btn-danger"
                                    title="Xóa bot"
                                    @click="deleteBot(bot)"
                                >
                                    <ion-icon :icon="trashOutline" />
                                </button>
                            </div>
                        </div>
                        <div v-if="idx < bots.length - 1" class="pf-divider" />
                    </template>

                    <!-- Add bot form -->
                    <div class="pf-divider" />
                    <div class="pf-add-bot">
                        <p class="pf-add-label">Thêm bot mới</p>
                        <input v-model="newBotName" class="pf-input" placeholder="Tên bot" />
                        <input v-model="newBotKey" type="password" class="pf-input" placeholder="Dify API Key" />
                        <input v-model="newBotBaseUrl" class="pf-input" placeholder="Dify Base URL (tùy chọn)" />
                        <button class="pf-add-btn" :disabled="isCreatingBot" @click="addBot">
                            <ion-icon :icon="sparklesOutline" />
                            {{ isCreatingBot ? "Đang thêm…" : "Thêm bot" }}
                        </button>
                        <p v-if="botError" class="pf-error">{{ botError }}</p>
                    </div>
                </div>
            </div>

            <!-- Non-admin AI note -->
            <div v-else class="pf-card pf-info-note">
                <ion-icon :icon="globeOutline" />
                <span>Chỉ quản trị viên workspace mới chỉnh được cấu hình AI.</span>
            </div>

            <!-- ── Channels ── -->
            <p class="pf-section-label">
                <ion-icon :icon="globeOutline" />
                Kênh kết nối
                <span class="pf-count">{{ connectedChannels.length }}</span>
            </p>

            <div v-if="pushSupported" class="pf-card pf-push-card">
                <div class="pf-row-icon pf-icon-purple">
                    <ion-icon :icon="notificationsOutline" />
                </div>
                <div class="pf-row-body">
                    <p class="pf-row-title">Thông báo trên điện thoại</p>
                    <p class="pf-row-sub">Nhận tin nhắn Website ngay cả khi đã đóng trình duyệt.</p>
                </div>
                <button class="pf-push-btn" :disabled="pushLoading || pushEnabled" @click="enablePush">
                    {{ pushEnabled ? 'Đã bật' : pushLoading ? '...' : 'Bật' }}
                </button>
                <span v-if="pushStatus === 'success'" class="pf-push-status pf-push-success">Đăng ký thành công</span>
                <span v-else-if="pushStatus === 'error'" class="pf-push-status pf-push-error">{{ pushErrorMessage }}</span>
            </div>

            <div class="pf-card">
                <div v-if="connectedChannels.length === 0" class="pf-empty">Chưa có kênh nào được kết nối</div>

                <template v-for="(channel, idx) in connectedChannels" :key="channel.id">
                    <div class="pf-row pf-row-channel">
                        <div class="pf-ch-icon" :class="`ch-${channel.provider}`">
                            <template v-if="channel.provider === 'telegram'">✈️</template>
                            <template v-else-if="channel.provider === 'zalo'"><span class="zl">Z</span></template>
                            <template v-else-if="channel.provider === 'website'"><ion-icon :icon="globeOutline" /></template>
                            <template v-else><span class="fb">f</span></template>
                        </div>
                        <div class="pf-row-body">
                            <div class="pf-ch-name-row">
                                <p class="pf-row-title">{{ channel.name }}</p>
                                <span class="pf-status-dot" :class="channel.is_active ? 's-live' : 's-off'">
                                    {{ channel.is_active ? "Live" : "Off" }}
                                </span>
                            </div>
                            <p class="pf-row-sub">{{ channelHint(channel.provider) }}</p>
                            <select
                                v-if="canManageAiSettings && channelAiByld[channel.id]"
                                class="pf-bot-select"
                                :value="channelAiByld[channel.id]?.bot_id ?? ''"
                                @change="setChannelBot(channelAiByld[channel.id], ($event.target as HTMLSelectElement).value)"
                            >
                                <option value="">— Bot mặc định workspace —</option>
                                <option v-for="bot in bots" :key="bot.id" :value="bot.id">{{ bot.name }}</option>
                            </select>
                        </div>
                        <label v-if="channelAiByld[channel.id]" class="pf-toggle" title="AI trên kênh này">
                            <input
                                :checked="channelAiByld[channel.id]?.ai_enabled"
                                type="checkbox"
                                @change="toggleChannelAi(channelAiByld[channel.id])"
                            />
                            <span class="pf-track"><span class="pf-thumb" /></span>
                        </label>
                    </div>
                    <div v-if="idx < connectedChannels.length - 1" class="pf-divider" />
                </template>
            </div>

            <!-- ── Logout ── -->
            <div class="pf-card pf-logout-card" @click="emit('logout')">
                <div class="pf-row-icon pf-icon-red">
                    <ion-icon :icon="logOutOutline" />
                </div>
                <span class="pf-logout-label">Đăng xuất</span>
                <ion-icon :icon="chevronForward" class="pf-chevron" />
            </div>

            <p class="pf-version">Omnichat LiveChat v1.0</p>

            <!-- ── Toasts ── -->
            <transition name="pf-toast-anim">
                <div v-if="isSaved" class="pf-toast pf-toast-ok">
                    <ion-icon :icon="checkmarkCircle" />
                    Đã lưu cấu hình!
                </div>
            </transition>
            <transition name="pf-toast-anim">
                <div v-if="saveError" class="pf-toast pf-toast-err">
                    <ion-icon :icon="alertCircleOutline" />
                    Lưu thất bại, thử lại sau.
                </div>
            </transition>
        </div>
    </div>
</template>

<style scoped>
.pf-push-card {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 78px;
    padding: 14px !important;
}

.pf-push-btn {
    flex-shrink: 0;
    border: 0;
    border-radius: 999px;
    appearance: none;
    background: #2f8cff !important;
    color: #fff;
    min-width: 74px;
    min-height: 38px;
    padding: 9px 16px;
    font-size: 13px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(47, 140, 255, 0.35);
}

.pf-push-btn:disabled {
    opacity: 0.6;
}

.pf-push-btn:not(:disabled):active {
    transform: scale(0.96);
}

.pf-push-status {
    flex-basis: 100%;
    font-size: 11px;
}

.pf-push-success { color: #63d987; }
.pf-push-error { color: #ff7b72; }

/* ═══════════════════════════════════════════════════════
   Profile Page — Clean Card Design
   ═══════════════════════════════════════════════════════ */

.profile-page {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #0c0c0f;
    overflow: hidden;
    min-height: 0;
    height: 100%;
    max-height: 100%;
    width: 100%;
}

/* ── Topbar ─────────────────────────────────────────── */
.pf-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px 12px;
    padding-top: max(env(safe-area-inset-top, 0px), 52px);
    background: #0c0c0f;
    border-bottom: 0.5px solid rgba(255,255,255,0.07);
    flex-shrink: 0;
}

.pf-title {
    font-size: 20px;
    font-weight: 700;
    color: #f4f4f5;
    margin: 0;
    letter-spacing: -0.5px;
}

.pf-save-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: 20px;
    border: none;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    background: #6366f1;
    color: #fff;
    box-shadow: 0 4px 14px rgba(99,102,241,0.3);
}
.pf-save-btn:not(:disabled):active {
    background: #4f46e5;
    transform: scale(0.96);
}
.pf-save-btn.saved {
    background: rgba(34,197,94,0.18);
    color: #4ade80;
    box-shadow: none;
    border: 1px solid rgba(74,222,128,0.25);
}
.pf-save-btn:disabled { opacity: 0.5; cursor: default; box-shadow: none; }
.pf-save-btn ion-icon { font-size: 14px; }

/* ── Scroll ─────────────────────────────────────────── */
.pf-scroll {
    flex: 1;
    min-height: 0;
    height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    padding: 20px 14px max(120px, calc(env(safe-area-inset-bottom, 0px) + 100px));
    display: flex;
    flex-direction: column;
    gap: 6px;              /* breathing room giữa các label + card groups */
    overscroll-behavior-y: contain;
    touch-action: pan-y;
}

/* ── Banner ─────────────────────────────────────────── */
.pf-banner {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 28px;   /* gap rõ hơn xuống section đầu tiên */
    padding: 0 16px 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.pf-banner-bg {
    position: absolute;
    inset: 0;
    background: linear-gradient(145deg, #18181b 0%, #1a1040 50%, #0f1628 100%);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 18px;
}

.pf-avatar-ring {
    position: relative;
    z-index: 1;
    margin-top: 20px;
    width: 76px;
    height: 76px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #06b6d4 100%);
    padding: 2.5px;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.15), 0 8px 24px rgba(0,0,0,0.4);
}

.pf-avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #1c1c24;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 800;
    color: #e4e4e7;
    letter-spacing: 1px;
}

.pf-banner-info {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}

.pf-name {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #fafafa;
    letter-spacing: -0.3px;
}

.pf-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 500;
    color: #a78bfa;
    background: rgba(139,92,246,0.12);
    border: 1px solid rgba(139,92,246,0.2);
    padding: 3px 10px;
    border-radius: 999px;
}
.pf-badge ion-icon { font-size: 11px; }

.pf-stats-row {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0;
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 12px;
    padding: 10px 0;
    margin-top: 2px;
}

.pf-stat {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}
.pf-stat strong {
    font-size: 17px;
    font-weight: 800;
    color: #fafafa;
    line-height: 1;
}
.pf-stat span {
    font-size: 9px;
    font-weight: 600;
    color: #71717a;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.pf-live { color: #4ade80 !important; text-shadow: 0 0 10px rgba(74,222,128,0.5); }
.pf-stat-div { width: 1px; height: 28px; background: rgba(255,255,255,0.07); flex-shrink: 0; }

/* ── Section Labels ─────────────────────────────────── */
.pf-section-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10.5px;
    font-weight: 700;
    color: #52525b;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    margin: 14px 0 8px 4px;   /* top gap từ card trước, bottom gap vào card */
    padding-top: 0;
}
/* Label đầu tiên ngay sau banner không cần top margin */
.pf-scroll > .pf-section-label:first-of-type { margin-top: 4px; }
.pf-section-label ion-icon { font-size: 12px; color: #6366f1; }
.pf-count {
    margin-left: auto;
    font-size: 10px;
    font-weight: 700;
    color: #6366f1;
    background: rgba(99,102,241,0.1);
    border: 1px solid rgba(99,102,241,0.2);
    padding: 1px 7px;
    border-radius: 999px;
}

/* ── Card ─────────────────────────────────────────────  */
.pf-card {
    background: #111115;
    border: 0.5px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 4px;   /* gap nhỏ — section label phía trên đã có margin 14px */
}

.pf-divider { height: 1px; background: rgba(255,255,255,0.05); margin: 0; }

/* ── Row ─────────────────────────────────────────────── */
.pf-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 14px;
}
.pf-row-col { flex-direction: column; align-items: stretch; gap: 10px; padding: 14px; }
.pf-row-channel { align-items: flex-start; }

.pf-row-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}
.pf-icon-purple { background: rgba(99,102,241,0.15); color: #818cf8; border: 1px solid rgba(99,102,241,0.18); }
.pf-icon-slate  { background: rgba(63,63,70,0.6);    color: #71717a;  border: 1px solid rgba(255,255,255,0.07); }
.pf-icon-red    { background: rgba(239,68,68,0.1);   color: #f87171;  border: 1px solid rgba(239,68,68,0.18); }
.pf-icon-ok { color: #4ade80 !important; }

.pf-row-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.pf-row-title { font-size: 14px; font-weight: 600; color: #f4f4f5; margin: 0; }
.pf-row-sub   { font-size: 11.5px; color: #52525b; margin: 0; line-height: 1.4; }

/* ── Toggle ─────────────────────────────────────────── */
.pf-toggle { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; cursor: pointer; }
.pf-toggle input { opacity: 0; width: 0; height: 0; }
.pf-track {
    position: absolute;
    inset: 0;
    background: #27272a;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.06);
    transition: 0.22s ease;
    display: flex;
    align-items: center;
    padding: 0 2px;
}
.pf-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #71717a;
    transition: 0.22s ease;
    box-shadow: 0 1px 4px rgba(0,0,0,0.3);
    flex-shrink: 0;
}
.pf-toggle input:checked ~ .pf-track {
    background: #22c55e;
    border-color: transparent;
    box-shadow: 0 0 10px rgba(34,197,94,0.25);
}
.pf-toggle input:checked ~ .pf-track .pf-thumb {
    transform: translateX(20px);
    background: #fff;
}

/* ── Key row ─────────────────────────────────────────── */
.pf-key-row { display: flex; align-items: center; gap: 10px; }

/* ── Input ─────────────────────────────────────────── */
.pf-input-wrap { position: relative; }
.pf-input {
    width: 100%;
    box-sizing: border-box;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    padding: 10px 38px 10px 12px;
    font-size: 13px;
    color: #f4f4f5;
    outline: none;
    font-family: inherit;
    transition: border-color 0.18s, background 0.18s;
}
.pf-input::placeholder { color: #3f3f46; }
.pf-input:focus {
    border-color: rgba(99,102,241,0.4);
    background: rgba(99,102,241,0.04);
}
.pf-input-hint { font-size: 10.5px; color: #3f3f46; margin: 0; line-height: 1.5; }
.pf-eye-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #52525b;
    font-size: 16px;
    display: flex;
    align-items: center;
    cursor: pointer;
    padding: 4px;
    transition: color 0.15s;
}
.pf-eye-btn:hover { color: #a1a1aa; }

/* ── Bot rows ─────────────────────────────────────────  */
.pf-bot-row { display: flex; align-items: center; gap: 10px; padding: 12px 14px; }
.pf-bot-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.dot-on  { background: #4ade80; box-shadow: 0 0 6px rgba(74,222,128,0.5); }
.dot-off { background: #3f3f46; }

.pf-bot-body { flex: 1; min-width: 0; }
.pf-bot-name-line { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.pf-bot-name { font-size: 13.5px; font-weight: 600; color: #f4f4f5; }
.pf-default-chip {
    font-size: 9px; font-weight: 700; color: #818cf8;
    background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);
    padding: 2px 7px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.3px;
}
.pf-bot-sub { font-size: 11px; color: #52525b; margin: 2px 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.pf-bot-actions { display: flex; gap: 6px; flex-shrink: 0; }
.pf-icon-btn {
    width: 30px; height: 30px; border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.07);
    background: rgba(255,255,255,0.03);
    color: #71717a; font-size: 13px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.15s;
}
.pf-icon-btn:active { transform: scale(0.9); }
.pf-icon-btn:disabled { opacity: 0.3; cursor: default; }
.btn-green { color: #4ade80; background: rgba(74,222,128,0.08); border-color: rgba(74,222,128,0.18); }
.btn-yellow { color: #fbbf24; background: rgba(251,191,36,0.08); border-color: rgba(251,191,36,0.18); }
.btn-danger:active { color: #f87171; background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.2); }

/* ── Add Bot Form ─────────────────────────────────────── */
.pf-add-bot { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 8px; }
.pf-add-label { font-size: 10.5px; font-weight: 700; color: #52525b; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 2px; }
.pf-add-btn {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    background: #6366f1; border: none; color: #fff;
    font-size: 13px; font-weight: 700;
    padding: 10px; border-radius: 10px;
    cursor: pointer; transition: all 0.15s;
    box-shadow: 0 4px 14px rgba(99,102,241,0.25);
}
.pf-add-btn:active { transform: scale(0.98); }
.pf-add-btn:disabled { opacity: 0.45; cursor: default; box-shadow: none; }
.pf-add-btn ion-icon { font-size: 14px; }
.pf-error { font-size: 11.5px; color: #f87171; margin: 0; }

/* ── Channel Icon ─────────────────────────────────────── */
.pf-ch-icon {
    width: 40px; height: 40px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.25);
}
.ch-telegram { background: linear-gradient(135deg, #229ed9, #0d7ab8); color: #fff; }
.ch-zalo     { background: linear-gradient(135deg, #0068ff, #0050cc); color: #fff; }
.ch-facebook { background: linear-gradient(135deg, #1877f2, #0d5bc9); color: #fff; }
.ch-website  { background: #1c1c24; color: #71717a; border: 1px solid rgba(255,255,255,0.07); }
.ch-instagram { background: linear-gradient(135deg, #e1306c, #833ab4); color: #fff; }
.fb { font-weight: 900; font-size: 18px; color: #fff; font-family: Georgia,serif; }
.zl { font-weight: 900; font-size: 15px; color: #fff; }

.pf-ch-name-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 2px; }
.pf-status-dot {
    font-size: 9px; font-weight: 700; padding: 2px 8px; border-radius: 999px;
}
.s-live { color: #4ade80; background: rgba(74,222,128,0.1); border: 1px solid rgba(74,222,128,0.2); }
.s-off  { color: #52525b; background: rgba(63,63,70,0.5);   border: 1px solid rgba(63,63,70,0.5); }

.pf-bot-select {
    margin-top: 6px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(99,102,241,0.2);
    border-radius: 8px;
    padding: 4px 10px;
    color: #818cf8;
    font-size: 11px;
    outline: none;
    font-family: inherit;
    width: fit-content;
    max-width: 100%;
    cursor: pointer;
    appearance: none;
}

/* ── Info note ────────────────────────────────────────── */
.pf-info-note {
    display: flex !important;
    align-items: center;
    gap: 10px;
    padding: 14px;
    font-size: 12.5px;
    color: #71717a;
}
.pf-info-note ion-icon { color: #6366f1; font-size: 16px; flex-shrink: 0; }

/* ── Empty ───────────────────────────────────────────── */
.pf-empty { text-align: center; color: #3f3f46; padding: 20px 14px; font-size: 12.5px; }

/* ── Logout Card ─────────────────────────────────────── */
.pf-logout-card {
    display: flex !important;
    align-items: center;
    gap: 12px;
    padding: 13px 14px;
    cursor: pointer;
    transition: background 0.15s;
    margin-bottom: 12px;
}
.pf-logout-card:active { background: rgba(239,68,68,0.05); }
.pf-logout-label { flex: 1; font-size: 14px; font-weight: 600; color: #f87171; }
.pf-chevron { font-size: 14px; color: #3f3f46; }

/* ── Version ──────────────────────────────────────────── */
.pf-version { text-align: center; font-size: 10px; color: #27272a; letter-spacing: 0.3px; margin: 8px 0 0; }

/* ── Toast ───────────────────────────────────────────── */
.pf-toast {
    position: fixed;
    bottom: 90px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    z-index: 200;
    backdrop-filter: blur(20px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.4);
}
.pf-toast ion-icon { font-size: 15px; }
.pf-toast-ok  { background: rgba(20,30,20,0.95); color: #4ade80; border: 1px solid rgba(74,222,128,0.2); }
.pf-toast-err { background: rgba(30,15,15,0.95); color: #f87171; border: 1px solid rgba(239,68,68,0.2); }

.pf-toast-anim-enter-active,
.pf-toast-anim-leave-active { transition: opacity 0.25s, transform 0.25s; }
.pf-toast-anim-enter-from,
.pf-toast-anim-leave-to { opacity: 0; transform: translateX(-50%) translateY(12px); }
</style>
