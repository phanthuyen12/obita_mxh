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
    background: rgba(9, 9, 11, 0.92);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    flex-shrink: 0;
    width: 100%;
    padding-top: max(env(safe-area-inset-top, 0px), 44px);
}

.profile-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px 14px;
}

.page-title {
    font-size: 21px;
    font-weight: 800;
    color: #fafafa;
    margin: 0;
    letter-spacing: -0.5px;
}

.save-header-btn {
    background: #fafafa;
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #09090b;
    font-size: 13px;
    font-weight: 700;
    padding: 7px 16px;
    border-radius: 999px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    transition:
        transform 0.15s,
        background 0.2s,
        opacity 0.2s;
}
.save-header-btn:active {
    transform: scale(0.96);
}
.save-header-btn.saved {
    background: #10b981;
    color: #ffffff;
    border-color: transparent;
}
.save-header-btn:disabled {
    opacity: 0.5;
    cursor: default;
}

.profile-scroll {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
    padding: 14px 12px 140px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    overscroll-behavior-y: contain;
    position: relative;
    -webkit-overflow-scrolling: touch;
}

.user-hero-card {
    position: relative;
    background: #18181b;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 20px;
    padding: 22px 16px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    overflow: hidden;
}

.hero-glow {
    position: absolute;
    top: -60px;
    left: 50%;
    transform: translateX(-50%);
    width: 260px;
    height: 140px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.18) 0%, transparent 70%);
    pointer-events: none;
}

.avatar-large-wrap {
    position: relative;
    padding: 3px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6, #06b6d4);
}

.avatar-large {
    width: 74px;
    height: 74px;
    border-radius: 50%;
    background: #27272a;
    border: 3px solid #18181b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    font-weight: 800;
    color: #fafafa;
    letter-spacing: 0.5px;
}

.online-dot {
    position: absolute;
    right: 1px;
    bottom: 3px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #22c55e;
    border: 2.5px solid #18181b;
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.18);
}

.user-meta-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.user-full-name {
    font-size: 17px;
    font-weight: 700;
    color: #fafafa;
    margin: 2px 0 0;
    letter-spacing: -0.3px;
}

.user-role {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: #a1a1aa;
}

.user-role ion-icon {
    font-size: 12px;
    color: #71717a;
}

.profile-quick-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    width: 100%;
    background: #27272a;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 14px;
    padding: 10px 6px;
    margin-top: 6px;
}

.p-stat-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    border-right: 1px solid rgba(255, 255, 255, 0.06);
}
.p-stat-box:last-child {
    border-right: none;
}

.p-stat-val {
    font-size: 16px;
    font-weight: 800;
    color: #fafafa;
    line-height: 1;
}

.live-text {
    color: #22c55e;
}

.p-stat-lbl {
    font-size: 10px;
    font-weight: 600;
    color: #71717a;
    letter-spacing: 0.2px;
    text-transform: uppercase;
}

.settings-group {
    display: flex;
    flex-direction: column;
    gap: 0;
    background: #18181b;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 16px;
    overflow: hidden;
}

.group-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 10.5px;
    font-weight: 700;
    color: #71717a;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    padding: 11px 14px 9px;
    background: #1a1a1e;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.group-header-icon {
    font-size: 13px;
    color: #71717a;
}

.group-count {
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: #27272a;
    color: #a1a1aa;
    font-size: 10px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.setting-item {
    display: flex;
    align-items: center;
    padding: 13px 14px;
    gap: 12px;
    background: #18181b;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}
.setting-item:last-child {
    border-bottom: none;
}

.setting-item.clickable:active {
    background: #27272a;
    cursor: pointer;
}

.setting-icon-box {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}

.ai-bg {
    background: #27272a;
    color: #a1a1aa;
}
.logout-bg {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}

.channel-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

.fb-channel-bg {
    background: #1877f2;
    color: #ffffff;
    font-size: 16px;
}
.tg-channel-bg {
    background: #229ed9;
    color: #ffffff;
    font-size: 15px;
}
.zalo-channel-bg {
    background: #0068ff;
    color: #ffffff;
}
.web-channel-bg {
    background: #27272a;
    color: #a1a1aa;
}

.fb-letter {
    font-weight: 800;
    font-size: 17px;
    color: #ffffff;
}

.zalo-letter {
    font-weight: 900;
    font-size: 14px;
}

.tg-mini-plane {
    font-size: 15px;
}

.channel-item:active {
    background: #27272a;
}

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
    font-weight: 600;
    color: #22c55e;
    background: rgba(34, 197, 94, 0.12);
    border: 1px solid rgba(34, 197, 94, 0.18);
    padding: 2px 8px;
    border-radius: 999px;
}

.status-off-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    font-weight: 600;
    color: #71717a;
    background: #27272a;
    border: 1px solid rgba(255, 255, 255, 0.06);
    padding: 2px 8px;
    border-radius: 999px;
}

.status-pill-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
}

.status-pill-dot.live {
    background: #22c55e;
}

.status-pill-dot.off {
    background: #71717a;
}

.empty-channels {
    text-align: center;
    color: #71717a;
    padding: 24px 10px;
    font-size: 13px;
}

.ai-readonly-note {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #18181b;
    border: 1px dashed rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 12px 14px;
    font-size: 12px;
    color: #a1a1aa;
}

.ai-readonly-note ion-icon {
    font-size: 15px;
    color: #71717a;
    flex-shrink: 0;
}

.setting-content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.setting-label {
    font-size: 14px;
    font-weight: 600;
    color: #fafafa;
}

.setting-hint {
    font-size: 11.5px;
    color: #71717a;
    line-height: 1.3;
}

.api-item {
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    padding-top: 12px;
    padding-bottom: 12px;
}

.api-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.api-input-icon {
    position: absolute;
    left: 11px;
    font-size: 14px;
    color: #52525b;
    display: flex;
    align-items: center;
    pointer-events: none;
    transition: color 0.2s;
}

.api-input-icon.ok {
    color: #22c55e;
}

.api-input-field {
    background: #27272a;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    padding: 10px 38px 10px 34px;
    color: #fafafa;
    font-size: 13px;
    outline: none;
    width: 100%;
    box-sizing: border-box;
    font-family: inherit;
    transition:
        border-color 0.2s,
        background 0.2s;
}
.api-input-field::placeholder {
    color: #71717a;
}
.api-input-field:focus {
    border-color: rgba(255, 255, 255, 0.14);
    background: #27272a;
}

.api-item-hint {
    font-size: 10.5px;
    color: #71717a;
    line-height: 1.4;
}

.toggle-eye-btn {
    position: absolute;
    right: 8px;
    background: none;
    border: none;
    color: #71717a;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 4px;
}
.toggle-eye-btn:hover {
    color: #fafafa;
}

.forward-icon {
    font-size: 14px;
    color: #52525b;
}

.logout-item {
    border-radius: 12px;
    transition: background 0.15s;
}

.logout-text {
    color: #ef4444;
    font-weight: 600;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 42px;
    height: 24px;
    flex-shrink: 0;
}
.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #3f3f46;
    border-radius: 999px;
    transition: 0.2s;
}
.toggle-slider:before {
    position: absolute;
    content: '';
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    border-radius: 50%;
    transition: 0.2s;
}
input:checked + .toggle-slider {
    background-color: #09090b;
    border: 1px solid rgba(255, 255, 255, 0.12);
}
input:checked + .toggle-slider:before {
    transform: translateX(17px);
    background: #fafafa;
}

.save-toast {
    position: absolute;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    background: #fafafa;
    color: #09090b;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    z-index: 100;
}

.save-toast-error {
    background: #ef4444;
    color: #ffffff;
}

.toast-fade-enter-active,
.toast-fade-leave-active {
    transition:
        opacity 0.25s,
        transform 0.25s;
}

.toast-fade-enter-from,
.toast-fade-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(-6px);
}

.version-label {
    text-align: center;
    font-size: 10.5px;
    color: #52525b;
    letter-spacing: 0.3px;
    padding-top: 4px;
}

.bot-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.bot-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.bot-name-line {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-wrap: wrap;
}

.bot-name {
    font-size: 13.5px;
    font-weight: 600;
    color: #fafafa;
}

.bot-default-pill {
    font-size: 9px;
    font-weight: 700;
    color: #a1a1aa;
    background: #27272a;
    border: 1px solid rgba(255, 255, 255, 0.06);
    padding: 2px 7px;
    border-radius: 999px;
    letter-spacing: 0.2px;
}

.bot-hint {
    font-size: 11px;
    color: #71717a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bot-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.bot-icon-btn {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: #27272a;
    color: #a1a1aa;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition:
        background 0.15s,
        color 0.15s,
        border-color 0.15s;
}

.bot-icon-btn.active {
    color: #fafafa;
    background: #09090b;
    border-color: rgba(255, 255, 255, 0.12);
}

.bot-icon-btn.danger {
    color: #a1a1aa;
    background: #27272a;
    border-color: rgba(255, 255, 255, 0.06);
}
.bot-icon-btn.danger:active {
    color: #ef4444;
}

.bot-icon-btn:disabled {
    opacity: 0.4;
    cursor: default;
}

.bot-icon-btn:active {
    transform: scale(0.94);
}

.bot-add-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px 14px 14px;
    background: #1a1a1e;
    border-top: 1px solid rgba(255, 255, 255, 0.04);
}

.bot-input {
    background: #27272a;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    padding: 10px 12px;
    color: #fafafa;
    font-size: 13px;
    outline: none;
    width: 100%;
    box-sizing: border-box;
    font-family: inherit;
    transition: border-color 0.2s;
}
.bot-input::placeholder {
    color: #71717a;
}

.bot-input:focus {
    border-color: rgba(255, 255, 255, 0.12);
}

.bot-add-btn {
    background: #fafafa;
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #09090b;
    font-size: 13px;
    font-weight: 700;
    padding: 10px 0;
    border-radius: 10px;
    cursor: pointer;
    transition:
        transform 0.15s,
        opacity 0.2s;
}

.bot-add-btn:active {
    transform: scale(0.98);
}

.bot-add-btn:disabled {
    opacity: 0.5;
    cursor: default;
}

.bot-error {
    font-size: 11px;
    color: #ef4444;
}

.bots-empty {
    text-align: center;
    color: #71717a;
    padding: 20px 10px;
    font-size: 12.5px;
}

/* Chọn bot Dify cho từng kênh */
.channel-bot-select {
    background: #1c1e24;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    padding: 5px 8px;
    color: #38bdf8;
    font-size: 11.5px;
    outline: none;
    width: fit-content;
    max-width: 100%;
    margin-top: 3px;
    font-family: inherit;
    appearance: none;
}
</style>
