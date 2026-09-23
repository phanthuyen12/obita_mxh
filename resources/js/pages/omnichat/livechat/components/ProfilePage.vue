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
        <!-- Header -->
        <div class="profile-sticky-top">
            <header class="profile-header">
                <h1 class="page-title">Cài đặt</h1>
                <button
                    class="save-header-btn"
                    :class="{ saved: isSaved }"
                    :disabled="isSaving"
                    @click="saveSettings"
                >
                    <ion-icon v-if="isSaved" :icon="checkmarkCircle"></ion-icon>
                    {{ isSaving ? 'Đang lưu' : isSaved ? 'Đã lưu' : 'Lưu' }}
                </button>
            </header>
        </div>

        <main class="profile-scroll">
            <!-- Thẻ người dùng -->
            <div class="user-hero-card">
                <div class="hero-glow"></div>

                <div class="avatar-large-wrap">
                    <div class="avatar-large">
                        <span>{{ initials }}</span>
                    </div>
                    <span class="online-dot"></span>
                </div>

                <div class="user-meta-center">
                    <h2 class="user-full-name">{{ userName }}</h2>
                    <span class="user-role">
                        <ion-icon :icon="earthOutline"></ion-icon>
                        Tài khoản quản trị Omnichat
                    </span>
                </div>

                <!-- 3 Thẻ thống kê tài khoản nhanh -->
                <div class="profile-quick-stats">
                    <div class="p-stat-box">
                        <span class="p-stat-val">{{ activeChannelCount }}</span>
                        <span class="p-stat-lbl">Đang hoạt động</span>
                    </div>
                    <div class="p-stat-box">
                        <span class="p-stat-val">{{
                            connectedChannels.length
                        }}</span>
                        <span class="p-stat-lbl">Kênh kết nối</span>
                    </div>
                    <div class="p-stat-box">
                        <span class="p-stat-val live-text">Live</span>
                        <span class="p-stat-lbl">Trạng thái</span>
                    </div>
                </div>
            </div>

            <!-- Cấu hình API Bot & AI -->
            <div v-if="canManageAiSettings" class="settings-group">
                <div class="group-header">
                    <ion-icon
                        :icon="sparklesOutline"
                        class="group-header-icon"
                    ></ion-icon>
                    <span>Trợ lý AI tự động</span>
                </div>

                <div class="setting-item api-item">
                    <div class="api-input-wrap">
                        <span
                            class="api-input-icon"
                            :class="{ ok: apiKeyHasBeenSet }"
                        >
                            <ion-icon :icon="keyOutline"></ion-icon>
                        </span>
                        <input
                            v-model="apiKey"
                            :type="showApiKey ? 'text' : 'password'"
                            class="api-input-field"
                            :placeholder="
                                apiKeyHasBeenSet
                                    ? 'API Key đã cấu hình — nhập key mới để thay đổi'
                                    : 'Nhập Dify API Key'
                            "
                        />
                        <button
                            class="toggle-eye-btn"
                            type="button"
                            :title="showApiKey ? 'Ẩn API Key' : 'Hiện API Key'"
                            @click="toggleShowApiKey"
                        >
                            <ion-icon
                                :icon="showApiKey ? eyeOffOutline : eyeOutline"
                            ></ion-icon>
                        </button>
                    </div>
                    <span class="api-item-hint"
                        >Dùng chung cho mọi kênh đã kết nối. Key chỉ lưu trên
                        server, không hiển thị lại.</span
                    >
                </div>

                <div class="setting-item">
                    <div class="setting-icon-box ai-bg">
                        <ion-icon :icon="sparklesOutline"></ion-icon>
                    </div>
                    <div class="setting-content">
                        <span class="setting-label"
                            >Tự động trả lời qua AI</span
                        >
                        <span class="setting-hint"
                            >Bot trả lời khách 24/7 trên các kênh đang bật</span
                        >
                    </div>
                    <label class="toggle-switch">
                        <input v-model="isAiEnabled" type="checkbox" />
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <!-- Danh sách Bot Dify (admin tạo nhiều bot, chọn bot mặc định) -->
            <div v-if="canManageAiSettings" class="settings-group">
                <div class="group-header">
                    <span class="group-count">{{ bots.length }}</span>
                    <span>Bot Dify</span>
                </div>

                <div v-if="isLoadingBots" class="bots-empty">
                    Đang tải bot...
                </div>
                <div v-else-if="bots.length === 0" class="bots-empty">
                    Chưa có bot nào — thêm bot đầu tiên để AI trả lời tự động
                </div>

                <div v-for="bot in bots" :key="bot.id" class="bot-row">
                    <div class="bot-info">
                        <div class="bot-name-line">
                            <span class="bot-name">{{ bot.name }}</span>
                            <span v-if="bot.is_default" class="bot-default-pill"
                                >Mặc định</span
                            >
                        </div>
                        <span class="bot-hint">
                            {{ bot.key_set ? 'Đã có API key' : 'Chưa có key' }}
                            <template v-if="bot.dify_base_url">
                                • {{ bot.dify_base_url }}</template
                            >
                        </span>
                    </div>
                    <div class="bot-actions">
                        <button
                            :class="['bot-icon-btn', { active: bot.is_active }]"
                            :title="
                                bot.is_active
                                    ? 'Bot đang bật — chạm để tắt'
                                    : 'Bot đang tắt — chạm để bật'
                            "
                            @click="toggleBotActive(bot)"
                        >
                            <ion-icon
                                :icon="
                                    bot.is_active
                                        ? flashOutline
                                        : flashOffOutline
                                "
                            ></ion-icon>
                        </button>
                        <button
                            :class="[
                                'bot-icon-btn',
                                { active: bot.is_default },
                            ]"
                            :disabled="bot.is_default"
                            title="Đặt làm bot mặc định cho workspace"
                            @click="setDefaultBot(bot)"
                        >
                            <ion-icon :icon="starOutline"></ion-icon>
                        </button>
                        <button
                            class="bot-icon-btn danger"
                            title="Xóa bot"
                            @click="deleteBot(bot)"
                        >
                            <ion-icon :icon="trashOutline"></ion-icon>
                        </button>
                    </div>
                </div>

                <!-- Form thêm bot mới -->
                <div class="bot-add-form">
                    <input
                        v-model="newBotName"
                        class="bot-input"
                        placeholder="Tên bot (ví dụ: Bot Tư Vấn)"
                    />
                    <input
                        v-model="newBotKey"
                        type="password"
                        class="bot-input"
                        placeholder="Dify API Key"
                    />
                    <input
                        v-model="newBotBaseUrl"
                        class="bot-input"
                        placeholder="Dify Base URL (tùy chọn)"
                    />
                    <button
                        class="bot-add-btn"
                        :disabled="isCreatingBot"
                        @click="addBot"
                    >
                        {{ isCreatingBot ? 'Đang thêm...' : 'Thêm bot' }}
                    </button>
                    <span v-if="botError" class="bot-error">{{
                        botError
                    }}</span>
                </div>
            </div>

            <div v-else class="ai-readonly-note">
                <ion-icon :icon="globeOutline"></ion-icon>
                <span
                    >Chỉ quản trị viên workspace mới có thể chỉnh cấu hình
                    AI.</span
                >
            </div>

            <!-- Kênh kết nối đa nền tảng -->
            <div class="settings-group">
                <div class="group-header">
                    <span class="group-count">{{
                        connectedChannels.length
                    }}</span>
                    <span>Kênh hỗ trợ đa nền tảng</span>
                </div>

                <div
                    v-for="channel in connectedChannels"
                    :key="channel.id"
                    class="setting-item channel-item"
                >
                    <div
                        class="channel-avatar"
                        :class="
                            channel.provider === 'telegram'
                                ? 'tg-channel-bg'
                                : channel.provider === 'zalo'
                                  ? 'zalo-channel-bg'
                                  : channel.provider === 'website'
                                    ? 'web-channel-bg'
                                    : 'fb-channel-bg'
                        "
                    >
                        <template v-if="channel.provider === 'telegram'">
                            <span class="tg-mini-plane">✈️</span>
                        </template>
                        <template v-else-if="channel.provider === 'zalo'">
                            <span class="zalo-letter">Z</span>
                        </template>
                        <template v-else-if="channel.provider === 'website'">
                            <ion-icon :icon="globeOutline"></ion-icon>
                        </template>
                        <template v-else>
                            <span class="fb-letter">f</span>
                        </template>
                    </div>
                    <div class="setting-content">
                        <div class="channel-name-line">
                            <span class="setting-label">{{
                                channel.name
                            }}</span>
                            <span
                                :class="
                                    channel.is_active
                                        ? 'status-live-pill'
                                        : 'status-off-pill'
                                "
                            >
                                <span
                                    class="status-pill-dot"
                                    :class="channel.is_active ? 'live' : 'off'"
                                ></span>
                                {{
                                    channel.is_active
                                        ? 'Đang kết nối'
                                        : 'Mất kết nối'
                                }}
                            </span>
                        </div>
                        <span class="setting-hint">{{
                            channelHint(channel.provider)
                        }}</span>

                        <!-- Admin: gán bot Dify cho từng kênh -->
                        <select
                            v-if="canManageAiSettings && channelAiByld[channel.id]"
                            class="channel-bot-select"
                            :value="channelAiByld[channel.id]?.bot_id ?? ''"
                            title="Chọn bot Dify phụ trách kênh này"
                            @change="
                                setChannelBot(
                                    channelAiByld[channel.id],
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="">— Bot mặc định workspace —</option>
                            <option v-for="bot in bots" :key="bot.id" :value="bot.id">
                                {{ bot.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Sale & admin: bật/tắt AI cho kênh này -->
                    <label
                        v-if="channelAiByld[channel.id]"
                        class="toggle-switch"
                        title="AI tự động trả lời trên kênh này"
                    >
                        <input
                            :checked="channelAiByld[channel.id]?.ai_enabled"
                            type="checkbox"
                            @change="toggleChannelAi(channelAiByld[channel.id])"
                        />
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div
                    v-if="connectedChannels.length === 0"
                    class="empty-channels"
                >
                    Chưa có kênh nào được kết nối
                </div>
            </div>

            <!-- Đăng xuất / Chuyển tài khoản -->
            <div class="settings-group">
                <div
                    class="setting-item logout-item clickable"
                    @click="emit('logout')"
                >
                    <div class="setting-icon-box logout-bg">
                        <ion-icon :icon="logOutOutline"></ion-icon>
                    </div>
                    <div class="setting-content">
                        <span class="setting-label logout-text"
                            >Đăng xuất tài khoản</span
                        >
                    </div>
                    <ion-icon
                        :icon="chevronForward"
                        class="forward-icon"
                    ></ion-icon>
                </div>
            </div>

            <span class="version-label">Omnichat LiveChat v1.0</span>

            <!-- Toast thông báo lưu -->
            <transition name="toast-fade">
                <div v-if="isSaved" class="save-toast">
                    <ion-icon :icon="checkmarkCircle"></ion-icon>
                    <span>Đã lưu cấu hình!</span>
                </div>
            </transition>

            <transition name="toast-fade">
                <div v-if="saveError" class="save-toast save-toast-error">
                    <ion-icon :icon="alertCircleOutline"></ion-icon>
                    <span>Lưu thất bại, thử lại sau.</span>
                </div>
            </transition>
        </main>
    </div>
</template>

<style scoped>
/* ═══════════════════════════════════════════════════════
   PROFILE PAGE — Premium Dark Design
   ═══════════════════════════════════════════════════════ */

.profile-page {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #09090b;
    overflow: hidden;
    min-height: 0;
    width: 100%;
}

.profile-sticky-top {
    background: rgba(9, 9, 11, 0.9);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    flex-shrink: 0;
    width: 100%;
    padding-top: max(env(safe-area-inset-top, 0px), 44px);
}

.profile-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px 16px;
}

.page-title {
    font-size: 22px;
    font-weight: 800;
    color: #fafafa;
    margin: 0;
    letter-spacing: -0.6px;
}

.save-header-btn {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    padding: 8px 20px;
    border-radius: 999px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: transform 0.15s, opacity 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
}
.save-header-btn:active { transform: scale(0.95); box-shadow: none; }
.save-header-btn.saved {
    background: linear-gradient(135deg, #10b981, #059669);
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
}
.save-header-btn:disabled { opacity: 0.5; cursor: default; box-shadow: none; }

.profile-scroll {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
    padding: 16px 14px 140px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    overscroll-behavior-y: contain;
    position: relative;
    -webkit-overflow-scrolling: touch;
}

/* Hero Card */
.user-hero-card {
    position: relative;
    background: linear-gradient(160deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 24px;
    padding: 28px 18px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.hero-glow {
    position: absolute;
    top: -40px;
    left: 50%;
    transform: translateX(-50%);
    width: 300px;
    height: 180px;
    background: radial-gradient(ellipse, rgba(99, 102, 241, 0.28) 0%, rgba(139, 92, 246, 0.12) 40%, transparent 70%);
    pointer-events: none;
}

.avatar-large-wrap {
    position: relative;
    padding: 3px;
    border-radius: 50%;
    background: conic-gradient(from 0deg, #6366f1, #8b5cf6, #06b6d4, #10b981, #6366f1);
    box-shadow: 0 0 28px rgba(99, 102, 241, 0.45);
}

.avatar-large {
    width: 82px;
    height: 82px;
    border-radius: 50%;
    background: #1a1a2e;
    border: 3px solid #0f1020;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 800;
    color: #fafafa;
    letter-spacing: 1px;
}

.online-dot {
    position: absolute;
    right: 2px;
    bottom: 4px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #22c55e;
    border: 3px solid #0f1020;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.25), 0 0 10px rgba(34, 197, 94, 0.5);
}

.user-meta-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}

.user-full-name {
    font-size: 19px;
    font-weight: 800;
    color: #ffffff;
    margin: 0;
    letter-spacing: -0.4px;
}

.user-role {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 500;
    color: rgba(165, 180, 252, 0.8);
    background: rgba(99, 102, 241, 0.12);
    border: 1px solid rgba(99, 102, 241, 0.2);
    padding: 3px 10px;
    border-radius: 999px;
}
.user-role ion-icon { font-size: 12px; color: #818cf8; }

/* Quick stats */
.profile-quick-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    width: 100%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 12px 6px;
    margin-top: 4px;
}

.p-stat-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    border-right: 1px solid rgba(255, 255, 255, 0.07);
}
.p-stat-box:last-child { border-right: none; }

.p-stat-val {
    font-size: 18px;
    font-weight: 800;
    color: #ffffff;
    line-height: 1;
}

.live-text {
    color: #4ade80;
    font-size: 14px;
    text-shadow: 0 0 12px rgba(74, 222, 128, 0.6);
}

.p-stat-lbl {
    font-size: 9.5px;
    font-weight: 600;
    color: rgba(161, 161, 170, 0.7);
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

/* Settings groups */
.settings-group {
    display: flex;
    flex-direction: column;
    background: #111116;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
}

.group-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 10px;
    font-weight: 800;
    color: #71717a;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    padding: 12px 16px 10px;
    background: rgba(255, 255, 255, 0.02);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.group-header-icon { font-size: 14px; color: #818cf8; }

.group-count {
    min-width: 22px;
    height: 22px;
    padding: 0 7px;
    border-radius: 999px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #ffffff;
    font-size: 10px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}

.setting-item {
    display: flex;
    align-items: center;
    padding: 14px 16px;
    gap: 13px;
    background: #111116;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    transition: background 0.15s;
}
.setting-item:last-child { border-bottom: none; }
.setting-item.clickable { cursor: pointer; }
.setting-item.clickable:active { background: rgba(255, 255, 255, 0.04); }

.setting-icon-box {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.ai-bg { background: rgba(99, 102, 241, 0.15); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.2); }
.logout-bg { background: rgba(239, 68, 68, 0.12); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }

/* Channel rows */
.channel-avatar {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
}

.fb-channel-bg { background: linear-gradient(135deg, #1877f2, #0d5bc9); color: #ffffff; font-size: 16px; }
.tg-channel-bg { background: linear-gradient(135deg, #229ed9, #0d7ab8); color: #ffffff; font-size: 15px; }
.zalo-channel-bg { background: linear-gradient(135deg, #0068ff, #0050cc); color: #ffffff; }
.web-channel-bg { background: linear-gradient(135deg, #27272a, #3f3f46); color: #a1a1aa; border: 1px solid rgba(255,255,255,0.06); }

.fb-letter { font-weight: 900; font-size: 19px; color: #ffffff; font-family: Georgia, serif; }
.zalo-letter { font-weight: 900; font-size: 15px; color: #ffffff; }
.tg-mini-plane { font-size: 17px; }

.channel-item:active { background: rgba(255, 255, 255, 0.03); }

.channel-name-line {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.status-live-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    font-weight: 700;
    color: #4ade80;
    background: rgba(74, 222, 128, 0.1);
    border: 1px solid rgba(74, 222, 128, 0.2);
    padding: 2px 8px;
    border-radius: 999px;
}

.status-off-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    font-weight: 700;
    color: #71717a;
    background: rgba(113, 113, 122, 0.1);
    border: 1px solid rgba(113, 113, 122, 0.15);
    padding: 2px 8px;
    border-radius: 999px;
}

.status-pill-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
.status-pill-dot.live { background: #4ade80; box-shadow: 0 0 6px rgba(74, 222, 128, 0.7); }
.status-pill-dot.off { background: #52525b; }

.empty-channels { text-align: center; color: #52525b; padding: 28px 14px; font-size: 13px; }

.setting-content { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.setting-label { font-size: 14.5px; font-weight: 600; color: #fafafa; letter-spacing: -0.2px; }
.setting-hint { font-size: 11.5px; color: #71717a; line-height: 1.4; }

/* API Input */
.api-item { flex-direction: column; align-items: stretch; gap: 10px; padding: 14px 16px; }

.api-input-wrap { position: relative; display: flex; align-items: center; width: 100%; }

.api-input-icon {
    position: absolute;
    left: 12px;
    font-size: 15px;
    color: #52525b;
    display: flex;
    align-items: center;
    pointer-events: none;
    transition: color 0.2s;
}
.api-input-icon.ok { color: #4ade80; }

.api-input-field {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 11px 40px 11px 36px;
    color: #fafafa;
    font-size: 13px;
    outline: none;
    width: 100%;
    box-sizing: border-box;
    font-family: inherit;
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
}
.api-input-field::placeholder { color: #52525b; }
.api-input-field:focus {
    border-color: rgba(99, 102, 241, 0.4);
    background: rgba(99, 102, 241, 0.05);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.08);
}

.api-item-hint { font-size: 10.5px; color: #52525b; line-height: 1.5; padding: 0 2px; }

.toggle-eye-btn {
    position: absolute;
    right: 8px;
    background: none;
    border: none;
    color: #52525b;
    font-size: 17px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 5px;
    transition: color 0.15s;
}
.toggle-eye-btn:hover { color: #a1a1aa; }

.forward-icon { font-size: 14px; color: #3f3f46; flex-shrink: 0; }
.logout-item { transition: background 0.15s; }
.logout-text { color: #f87171; font-weight: 700; }

/* Toggle Switch */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 26px;
    flex-shrink: 0;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background: #3f3f46;
    border-radius: 999px;
    transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.06);
}
.toggle-slider:before {
    position: absolute;
    content: '';
    height: 20px;
    width: 20px;
    left: 2px;
    bottom: 2px;
    background: #a1a1aa;
    border-radius: 50%;
    transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
}
input:checked + .toggle-slider {
    background: linear-gradient(135deg, #4ade80, #22c55e);
    border-color: transparent;
    box-shadow: 0 0 12px rgba(74, 222, 128, 0.3);
}
input:checked + .toggle-slider:before {
    transform: translateX(20px);
    background: #ffffff;
}

.ai-readonly-note {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(99, 102, 241, 0.06);
    border: 1px dashed rgba(99, 102, 241, 0.2);
    border-radius: 14px;
    padding: 13px 16px;
    font-size: 12.5px;
    color: #a1a1aa;
}
.ai-readonly-note ion-icon { font-size: 16px; color: #818cf8; flex-shrink: 0; }

/* Bot Rows */
.bot-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    transition: background 0.15s;
}
.bot-row:active { background: rgba(255, 255, 255, 0.03); }
.bot-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.bot-name-line { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.bot-name { font-size: 14px; font-weight: 600; color: #fafafa; }
.bot-default-pill {
    font-size: 9px;
    font-weight: 800;
    color: #818cf8;
    background: rgba(99, 102, 241, 0.12);
    border: 1px solid rgba(99, 102, 241, 0.25);
    padding: 2px 8px;
    border-radius: 999px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}
.bot-hint { font-size: 11px; color: #52525b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bot-actions { display: flex; gap: 7px; flex-shrink: 0; }

.bot-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.07);
    background: rgba(255, 255, 255, 0.04);
    color: #71717a;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
}
.bot-icon-btn:active { transform: scale(0.92); }
.bot-icon-btn.active { color: #fbbf24; background: rgba(251, 191, 36, 0.1); border-color: rgba(251, 191, 36, 0.2); }
.bot-icon-btn.danger:active { color: #f87171; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); }
.bot-icon-btn:disabled { opacity: 0.3; cursor: default; }

.bot-add-form {
    display: flex;
    flex-direction: column;
    gap: 9px;
    padding: 14px 16px 16px;
    background: rgba(255, 255, 255, 0.015);
    border-top: 1px solid rgba(255, 255, 255, 0.04);
}

.bot-input {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 11px 13px;
    color: #fafafa;
    font-size: 13px;
    outline: none;
    width: 100%;
    box-sizing: border-box;
    font-family: inherit;
    transition: border-color 0.2s, background 0.2s;
}
.bot-input::placeholder { color: #52525b; }
.bot-input:focus { border-color: rgba(99, 102, 241, 0.35); background: rgba(99, 102, 241, 0.04); }

.bot-add-btn {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none;
    color: #ffffff;
    font-size: 13.5px;
    font-weight: 700;
    padding: 11px 0;
    border-radius: 12px;
    cursor: pointer;
    transition: transform 0.15s, opacity 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
}
.bot-add-btn:active { transform: scale(0.98); box-shadow: none; }
.bot-add-btn:disabled { opacity: 0.45; cursor: default; box-shadow: none; }

.bot-error { font-size: 11.5px; color: #f87171; padding: 0 2px; }
.bots-empty { text-align: center; color: #52525b; padding: 24px 14px; font-size: 13px; line-height: 1.5; }

.channel-bot-select {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 9px;
    padding: 5px 10px;
    color: #818cf8;
    font-size: 11.5px;
    outline: none;
    width: fit-content;
    max-width: 100%;
    margin-top: 4px;
    font-family: inherit;
    appearance: none;
    cursor: pointer;
}

.save-toast {
    position: absolute;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(30, 30, 36, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #fafafa;
    font-size: 13px;
    font-weight: 600;
    padding: 9px 18px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    gap: 7px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    z-index: 100;
    white-space: nowrap;
    backdrop-filter: blur(20px);
}
.save-toast ion-icon { color: #4ade80; font-size: 16px; }
.save-toast-error ion-icon { color: #f87171; }

.toast-fade-enter-active,
.toast-fade-leave-active { transition: opacity 0.25s, transform 0.25s; }
.toast-fade-enter-from,
.toast-fade-leave-to { opacity: 0; transform: translateX(-50%) translateY(-8px); }

.version-label { text-align: center; font-size: 10px; color: #3f3f46; letter-spacing: 0.4px; padding-top: 4px; }
</style>
