<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { IonApp, IonIcon } from '@ionic/vue';
import { useEcho } from '@laravel/echo-vue';
import axios from 'axios';
import {
    chatbubbles,
    checkmarkDoneOutline,
    closeOutline,
    createOutline,
    logOutOutline,
    notificationsOutline,
    people,
    pin,
    searchOutline,
    statsChart,
    volumeMute,
} from 'ionicons/icons';
import { computed, onMounted, ref } from 'vue';

import ConversationReadController from '@/actions/App/Http/Controllers/App/Omnichat/ConversationReadController';
import { store as storeMessage } from '@/actions/App/Http/Controllers/App/Omnichat/MessageController';
import dayjs from '@/dayjs';
import { logout } from '@/routes';
import {
    archiveAll,
    read as notificationRead,
    index as notificationsIndex,
    readAll as notificationsReadAll,
} from '@/routes/app/notifications';
import {
    show as livechatConversation,
    index as livechatConversations,
} from '@/routes/app/omnichat/livechat/conversations';

const page = usePage();
const authUser = computed(
    () => page.props.auth?.user ?? { name: '', email: '' },
);
const currentWorkspaceId = computed(
    () => page.props.auth?.currentWorkspace?.id ?? null,
);

import AnalyticsPage from './livechat/components/AnalyticsPage.vue';
import ChatRoom from './livechat/components/ChatRoom.vue';
import ChatSidebarPanel from './livechat/components/ChatSidebarPanel.vue';
import CustomersPage from './livechat/components/CustomersPage.vue';
import ProfilePage from './livechat/components/ProfilePage.vue';
import type {
    AssignedUser,
    Attachment,
    ChannelSource,
    ChatItem,
    Message,
} from './livechat/types/chat';

type ConnectedChannel = {
    id: string;
    provider: string;
    name: string;
    avatar_url: string | null;
    status: string;
    is_active: boolean;
};

type Props = {
    workspaceId: string;
    connectedChannels: ConnectedChannel[];
    assignees: AssignedUser[];
    labels: Array<{ id: string; name: string; color: string | null }>;
    permissions: {
        manageChannels: boolean;
        assignConversations: boolean;
        sendMessages: boolean;
        editContacts: boolean;
        manageAiSettings?: boolean;
    };
    currentUser: { id: string; name: string; avatar_url: string | null };
};

const props = defineProps<Props>();

// Map backend provider → applivechat channel source badge.
const PROVIDER_SOURCE: Record<string, ChannelSource> = {
    facebook: 'facebook',
    instagram: 'facebook',
    telegram: 'telegram',
    'zalo-oa': 'zalo',
    zalo: 'zalo',
    website: 'website',
};

const AVATAR_COLORS = [
    '#0866ff',
    '#0088cc',
    '#0068ff',
    '#db2777',
    '#059669',
    '#78350f',
    '#0284c7',
    '#09090b',
];

const activeFolder = ref('Tất cả');
const folders = [
    'Tất cả',
    '🔵 Facebook Page',
    '✈️ Telegram',
    '💬 Zalo OA',
    '🌐 Website',
    'VIP ⭐',
];
const activeTab = ref('chat');
const selectedChat = ref<ChatItem | null>(null);
const showSidebarPanel = ref(false);
const searchQuery = ref('');
const chats = ref<ChatItem[]>([]);
const isLoading = ref(false);

const avatarColorFor = (id: string): string => {
    let hash = 0;
    for (let i = 0; i < id.length; i++) {
        hash = id.charCodeAt(i) + ((hash << 5) - hash);
    }
    return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
};

const formatTime = (iso: string | null): string => {
    if (!iso) {
        return '';
    }
    const value = dayjs(iso);
    return value.isSame(dayjs(), 'day')
        ? value.format('HH:mm')
        : value.format('DD/MM');
};

type ConversationSummary = {
    id: string;
    contact: {
        display_name: string;
        avatar_url: string | null;
        phone?: string | null;
        email?: string | null;
        notes?: string | null;
    };
    channel: { provider: string; name?: string };
    last_message_preview: string | null;
    last_message_at: string | null;
    unread_count: number;
    ai_paused?: boolean;
    assigned_user?: AssignedUser | null;
    labels: Array<{ id: string; name: string; color: string | null }>;
};

const toChatItem = (conversation: ConversationSummary): ChatItem => {
    const source = PROVIDER_SOURCE[conversation.channel.provider] ?? 'website';
    const name = conversation.contact.display_name || 'Khách hàng';

    return {
        id: conversation.id,
        name,
        channelSource: source,
        phone: conversation.contact.phone ?? undefined,
        contactEmail: conversation.contact.email ?? undefined,
        contactNotes: conversation.contact.notes ?? undefined,
        assignedUser: conversation.assigned_user ?? null,
        tags: conversation.labels.map(
            (label) => label.name,
        ) as ChatItem['tags'],
        tagIds: conversation.labels.map((label) => label.id),
        unreadCount:
            conversation.unread_count > 0
                ? conversation.unread_count
                : undefined,
        unreadType: 'blue',
        aiPaused: conversation.ai_paused ?? false,
        lastMessage: { text: conversation.last_message_preview ?? '' },
    };
};

const loadConversations = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const { data } = await axios.get(
            livechatConversations.url({ query: { search: searchQuery.value } }),
        );
        chats.value = (data.data as ConversationSummary[]).map(toChatItem);
    } finally {
        isLoading.value = false;
    }
};

onMounted(loadConversations);

// WebSocket (Laravel Reverb / Echo): nhận tin nhắn mới realtime cho mọi kênh đang chọn.
type BroadcastAttachment = {
    id: string;
    type: string;
    url?: string;
    original_name?: string;
};

type BroadcastMessage = {
    id: string;
    conversation_id: string;
    direction: 'inbound' | 'outbound' | 'internal';
    type: string;
    body: string | null;
    status: string;
    client_id: string | null;
    sender: { id: string; name: string; avatar_url: string | null } | null;
    attachments: BroadcastAttachment[];
    sent_at: string | null;
    created_at: string;
};

type MessageCreatedPayload = { message: BroadcastMessage };

const applyIncomingMessage = (message: BroadcastMessage): void => {
    const conversation = chats.value.find(
        (chat) => chat.id === message.conversation_id,
    );

    // Cập nhật phòng chat đang mở.
    if (
        selectedChat.value &&
        selectedChat.value.id === message.conversation_id
    ) {
        // Bỏ qua echo của tin nhắn mình vừa gửi tối ưu (đã có trong phòng chat).
        const duplicate = selectedChat.value.messages.some(
            (m) =>
                m.id === message.id ||
                (message.client_id !== null &&
                    m.clientId === message.client_id),
        );
        if (!duplicate) {
            const payload: MessagePayload = {
                id: message.id,
                direction:
                    message.direction === 'outbound' ? 'outbound' : 'inbound',
                body: message.body,
                sent_at: message.sent_at,
                created_at: message.created_at,
                read_at:
                    message.direction === 'outbound' ? message.sent_at : null,
                attachments: message.attachments ?? [],
            };
            selectedChat.value = {
                ...selectedChat.value,
                messages: [...selectedChat.value.messages, toMessage(payload)],
            };
        }

        // Browser push notification khi có tin nhắn đến và tab không focused.
        if (
            message.direction === 'inbound' &&
            document.visibilityState === 'hidden' &&
            Notification.permission === 'granted'
        ) {
            const senderName =
                message.sender?.name ??
                chats.value.find((c) => c.id === message.conversation_id)
                    ?.name ??
                'Tin nhắn mới';
            new Notification(senderName, {
                body: message.body ?? '[Hình ảnh]',
                icon: '/apple-touch-icon.png',
                tag: `omnichat-${message.conversation_id}`,
            });
        }
    }

    // Cập nhật danh sách hội thoại: đưa lên đầu + badge chưa đọc.
    if (conversation) {
        conversation.lastMessage.text = message.body ?? `[${message.type}]`;
        conversation.time = formatTime(message.sent_at ?? message.created_at);

        if (
            message.direction === 'inbound' &&
            message.conversation_id !== selectedChat.value?.id
        ) {
            conversation.unreadCount =
                (typeof conversation.unreadCount === 'number'
                    ? conversation.unreadCount
                    : 0) + 1;
            conversation.unreadType = 'blue';
        }
        chats.value = [
            conversation,
            ...chats.value.filter((chat) => chat.id !== conversation.id),
        ];
    } else {
        // Hội thoại mới chưa có trong danh sách → tải lại.
        loadConversations();
    }
};

for (const channel of props.connectedChannels) {
    useEcho<MessageCreatedPayload>(
        `omnichat.channel.${channel.id}`,
        '.omnichat.message.created',
        ({ message }) => {
            applyIncomingMessage(message);
        },
    );
}

// ── Thông báo realtime (Notification) ────────────────────────────────
type AppNotification = {
    id: string;
    title: string;
    body: string | null;
    type: string;
    read_at: string | null;
    created_at: string;
};

const notifications = ref<AppNotification[]>([]);
const unreadNotificationCount = ref(0);
const showNotificationPanel = ref(false);

const notificationChannelName = computed(() =>
    currentWorkspaceId.value && authUser.value?.id
        ? `workspace.${currentWorkspaceId.value}.user.${authUser.value.id}`
        : null,
);

const loadNotifications = async (): Promise<void> => {
    try {
        const response = await axios.get(notificationsIndex.url());
        notifications.value = (response.data.data ??
            response.data.notifications ??
            []) as AppNotification[];
        unreadNotificationCount.value = notifications.value.filter(
            (n) => !n.read_at,
        ).length;
    } catch {
        // Non-blocking: notifications should never break the page.
    }
};

const markNotificationRead = async (
    notification: AppNotification,
): Promise<void> => {
    if (notification.read_at) return;
    try {
        await axios.put(notificationRead.url(notification.id));
        notification.read_at = new Date().toISOString();
        unreadNotificationCount.value = Math.max(
            0,
            unreadNotificationCount.value - 1,
        );
    } catch {
        // Ignore.
    }
};

const markAllNotificationsRead = async (): Promise<void> => {
    try {
        await axios.post(notificationsReadAll.url());
        notifications.value = notifications.value.map((n) => ({
            ...n,
            read_at: n.read_at ?? new Date().toISOString(),
        }));
        unreadNotificationCount.value = 0;
    } catch {
        // Ignore.
    }
};

const archiveAllNotifications = async (): Promise<void> => {
    try {
        await axios.post(archiveAll.url());
        notifications.value = [];
        unreadNotificationCount.value = 0;
    } catch {
        // Ignore.
    }
};

const notificationTime = (iso: string): string => {
    const value = dayjs(iso);
    return value.isSame(dayjs(), 'day')
        ? value.format('HH:mm')
        : value.format('DD/MM');
};

onMounted(loadNotifications);

// Realtime: nhận thông báo mới qua WebSocket.
if (notificationChannelName.value) {
    useEcho<{ notification: AppNotification }>(
        notificationChannelName.value,
        '.notification.created',
        ({ notification }) => {
            const exists = notifications.value.some(
                (n) => n.id === notification.id,
            );
            if (exists) return;

            notifications.value = [notification, ...notifications.value];
            if (!notification.read_at) {
                unreadNotificationCount.value += 1;
            }
        },
    );
}

// Đóng panel khi chạm ngoài.
const closeNotificationPanel = (event: MouseEvent): void => {
    const target = event.target as HTMLElement;
    if (
        !target.closest('.notification-panel') &&
        !target.closest('.notification-bell-btn')
    ) {
        showNotificationPanel.value = false;
    }
};

const handleLogout = (): void => {
    router.post(logout.url());
};

const filteredChats = computed(() =>
    chats.value.filter((chat) => {
        const matchFolder =
            activeFolder.value === 'Tất cả' ||
            (chat.folderCategory &&
                chat.folderCategory.includes(activeFolder.value));
        const matchQuery =
            !searchQuery.value ||
            chat.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            chat.lastMessage.text
                .toLowerCase()
                .includes(searchQuery.value.toLowerCase());

        return matchFolder && matchQuery;
    }),
);

type MessagePayload = {
    id: string;
    direction: 'inbound' | 'outbound';
    body: string | null;
    sent_at: string | null;
    created_at: string;
    read_at: string | null;
    attachments?: BroadcastAttachment[];
};

const toMessage = (message: MessagePayload): Message => {
    // Lấy attachment đầu tiên (Telegram gửi ảnh, tài liệu, v.v.)
    const firstAttachment = message.attachments?.[0];
    const attachment: Attachment | undefined = firstAttachment
        ? {
              name: firstAttachment.original_name ?? 'file',
              type:
                  firstAttachment.type === 'image' ||
                  firstAttachment.type === 'photo'
                      ? 'image'
                      : 'file',
              url: firstAttachment.url,
          }
        : undefined;

    return {
        id: message.id,
        sender: message.direction === 'outbound' ? 'me' : 'other',
        text: message.body ?? undefined,
        time: formatTime(message.sent_at ?? message.created_at),
        isRead: message.read_at !== null,
        attachment,
    };
};

const openChat = async (chat: ChatItem): Promise<void> => {
    chat.unreadCount = undefined;
    selectedChat.value = { ...chat, messages: [] };
    showSidebarPanel.value = false;

    // Xin quyền notification lần đầu khi user mở chat (gesture-triggered).
    requestNotificationPermission();

    // Placeholder từ tab Khách hàng chưa có hội thoại thật — bỏ qua API.
    if (chat.id.startsWith('contact_')) {
        return;
    }

    try {
        const { data } = await axios.get(livechatConversation.url(chat.id));
        const conv = data.conversation as {
            ai_paused?: boolean;
            contact: {
                id: string;
                email?: string | null;
                phone?: string | null;
                notes?: string | null;
            };
            channel: { name?: string };
            assigned_user?: AssignedUser | null;
        };
        selectedChat.value = {
            ...chat,
            contactId: conv.contact.id,
            contactEmail: conv.contact.email ?? undefined,
            contactNotes: conv.contact.notes ?? undefined,
            assignedUser: conv.assigned_user ?? null,
            channelName: conv.channel?.name ?? chat.channelName,
            aiPaused: conv.ai_paused ?? false,
            messages: (data.messages.data as MessagePayload[]).map(toMessage),
        };

        // Mark the conversation read once opened.
        try {
            await axios.post(ConversationReadController.url(chat.id));
        } catch {
            // Non-blocking: read receipts should never break opening a chat.
        }
    } catch {
        // Conversation may have been deleted; keep the optimistic room open.
    }
};

const handleProfileUpdated = (data: {
    name: string;
    phone: string;
    email: string;
    notes: string;
}): void => {
    if (!selectedChat.value) return;
    selectedChat.value = {
        ...selectedChat.value,
        name: data.name || selectedChat.value.name,
        phone: data.phone || selectedChat.value.phone,
        contactEmail: data.email,
        contactNotes: data.notes,
    };
    // Sync tên trong danh sách hội thoại
    const inList = chats.value.find((c) => c.id === selectedChat.value?.id);
    if (inList && data.name) {
        inList.name = data.name;
    }
};

const handleAssignUpdated = (assignedUser: AssignedUser | null): void => {
    if (!selectedChat.value) return;
    selectedChat.value = { ...selectedChat.value, assignedUser };
};


const sendErrorMessage = ref('');
let sendErrorTimer: ReturnType<typeof setTimeout> | undefined;

const handleSend = async ({
    id,
    text,
    attachment,
    clientId,
}: {
    id: string;
    text: string;
    attachment: Attachment | null;
    clientId: string;
}): Promise<void> => {
    // Placeholder chưa có hội thoại thật trên server — không gửi được.
    if (id.startsWith('contact_')) {
        return;
    }

    const payload = new FormData();
    payload.append('body', text);
    payload.append('mode', 'reply');
    payload.append('client_id', clientId);

    if (attachment?.url && attachment.type === 'image') {
        try {
            const response = await fetch(attachment.url);
            if (!response.ok) {
                throw new Error(`fetch failed: ${response.status}`);
            }
            const blob = await response.blob();
            payload.append('image', blob, attachment.name);
        } catch {
            sendErrorMessage.value =
                'Không đọc được file ảnh. Vui lòng chọn lại.';
            clearTimeout(sendErrorTimer);
            sendErrorTimer = setTimeout(() => {
                sendErrorMessage.value = '';
            }, 4000);
            return;
        }
    }

    try {
        await axios.post(storeMessage.url(id), payload);
    } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : null;
        sendErrorMessage.value =
            status === 422
                ? 'Gửi thất bại: ảnh vượt 10MB hoặc sai định dạng (JPG/PNG/GIF).'
                : status === 413
                  ? 'Gửi thất bại: tệp quá lớn.'
                  : 'Gửi tin nhắn thất bại. Vui lòng thử lại.';
        clearTimeout(sendErrorTimer);
        sendErrorTimer = setTimeout(() => {
            sendErrorMessage.value = '';
        }, 4000);
    }
};

// Xin quyền push notification (chạy 1 lần sau khi user tương tác).
const requestNotificationPermission = (): void => {
    if (
        'Notification' in window &&
        Notification.permission === 'default'
    ) {
        Notification.requestPermission();
    }
};

const handleUpdateLastMessage = ({
    id,
    text,
    time,
}: {
    id: string;
    text: string;
    time: string;
}): void => {
    const target = chats.value.find((chat) => chat.id === id);
    if (target) {
        target.lastMessage.text = text;
        target.time = time;
    }
};

type CustomerLike = {
    id: string;
    name: string;
    phone: string;
    avatarText: string;
    avatarBg: string;
    tags?: string[];
    latestConversationId?: string | null;
    notes?: string;
    lastActive?: string;
};

const handleChatWithCustomer = (customer: CustomerLike): void => {
    activeTab.value = 'chat';

    const existing = chats.value.find(
        (chat) => chat.phone === customer.phone && customer.phone !== '',
    );
    if (existing) {
        openChat(existing);
        return;
    }

    const placeholder: ChatItem = {
        id: customer.latestConversationId ?? `contact_${customer.id}`,
        name: customer.name,
        phone: customer.phone,
        contactId: customer.id,
        avatarType: 'text',
        avatarText: customer.avatarText,
        avatarBg: customer.avatarBg,
        time: 'Vừa xong',
        tags: (customer.tags ?? []) as ChatItem['tags'],
        lastMessage: {
            text: customer.notes || 'Khách hàng chưa có hội thoại gần đây',
        },
    };
    chats.value.unshift(placeholder);
    selectedChat.value = placeholder;
};
</script>

<template>
    <Head title="LiveChat" />

    <ion-app>
        <div class="ios-device-container">
            <!-- Phòng chat chi tiết -->
            <template v-if="selectedChat">
                <ChatRoom
                    :chat="selectedChat"
                    :send-error="sendErrorMessage"
                    @back="selectedChat = null; showSidebarPanel = false"
                    @send="handleSend"
                    @update-last-message="handleUpdateLastMessage"
                    @open-profile="showSidebarPanel = true"
                />
                <!-- Sidebar panel thông tin khách -->
                <ChatSidebarPanel
                    v-if="showSidebarPanel && selectedChat"
                    :chat="selectedChat"
                    :assignees="props.assignees"
                    :can-assign="props.permissions.assignConversations"
                    @close="showSidebarPanel = false"
                    @profile-updated="handleProfileUpdated"
                    @assign-updated="handleAssignUpdated"
                />
            </template>

            <!-- Màn hình chính đa tab -->
            <template v-else>
                <div class="tab-area">
                <template v-if="activeTab === 'contacts'">
                    <CustomersPage @chat-with="handleChatWithCustomer" />
                </template>

                <template v-else-if="activeTab === 'calls'">
                    <AnalyticsPage />
                </template>

                <template v-else-if="activeTab === 'settings'">
                    <ProfilePage
                        :user="{
                            name: props.currentUser.name,
                            avatar_url: props.currentUser.avatar_url,
                        }"
                        :channels="props.connectedChannels"
                        :can-manage-ai-settings="
                            props.permissions.manageAiSettings ?? false
                        "
                        @logout="handleLogout"
                    />
                </template>

                <template v-else>
                    <div
                        class="top-sticky-wrapper"
                        @click="closeNotificationPanel"
                    >
                        <header class="telegram-header">
                            <button class="header-btn-text">Sửa</button>
                            <div class="header-title">
                                <div class="tg-paper-plane-icon">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path
                                            d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM16.64 8.8L15.01 16.48C14.89 17.02 14.57 17.15 14.12 16.9L11.64 15.07L10.44 16.22C10.31 16.35 10.2 16.46 9.94 16.46L10.12 13.91L14.76 9.72C14.96 9.54 14.71 9.44 14.44 9.62L8.71 13.23L6.24 12.46C5.7 12.29 5.69 11.92 6.35 11.66L16.03 7.93C16.48 7.76 16.87 8.03 16.64 8.8Z"
                                            fill="white"
                                        />
                                    </svg>
                                </div>
                                <span class="title-text">Chat</span>
                            </div>
                            <div class="header-actions">
                                <!-- Chuông thông báo realtime -->
                                <button
                                    class="action-circle-btn notification-bell-btn"
                                    title="Thông báo"
                                    @click.stop="
                                        showNotificationPanel =
                                            !showNotificationPanel
                                    "
                                >
                                    <ion-icon
                                        :icon="notificationsOutline"
                                        class="action-icon-small"
                                    ></ion-icon>
                                    <span
                                        v-if="unreadNotificationCount > 0"
                                        class="notification-badge"
                                        >{{
                                            unreadNotificationCount > 99
                                                ? '99+'
                                                : unreadNotificationCount
                                        }}</span
                                    >
                                </button>
                                <button
                                    class="action-circle-btn"
                                    title="Đăng xuất"
                                    @click.stop="handleLogout"
                                >
                                    <ion-icon
                                        :icon="logOutOutline"
                                        class="action-icon-small"
                                    ></ion-icon>
                                </button>
                                <button
                                    class="action-icon-btn"
                                    title="Soạn tin"
                                >
                                    <ion-icon :icon="createOutline"></ion-icon>
                                </button>
                            </div>

                            <!-- Panel thông báo -->
                            <div
                                v-if="showNotificationPanel"
                                class="notification-panel"
                                @click.stop
                            >
                                <div class="notification-panel-header">
                                    <span class="notification-panel-title"
                                        >Thông báo</span
                                    >
                                    <div class="notification-panel-actions">
                                        <button
                                            v-if="unreadNotificationCount > 0"
                                            class="notification-panel-btn"
                                            title="Đánh dấu tất cả đã đọc"
                                            @click="markAllNotificationsRead"
                                        >
                                            <ion-icon
                                                :icon="checkmarkDoneOutline"
                                            ></ion-icon>
                                        </button>
                                        <button
                                            class="notification-panel-btn"
                                            title="Xóa tất cả"
                                            @click="archiveAllNotifications"
                                        >
                                            <ion-icon
                                                :icon="closeOutline"
                                            ></ion-icon>
                                        </button>
                                    </div>
                                </div>

                                <div class="notification-list">
                                    <div
                                        v-for="notification in notifications.slice(
                                            0,
                                            30,
                                        )"
                                        :key="notification.id"
                                        :class="[
                                            'notification-item',
                                            { unread: !notification.read_at },
                                        ]"
                                        @click="
                                            markNotificationRead(notification)
                                        "
                                    >
                                        <div class="notification-item-top">
                                            <span
                                                class="notification-item-title"
                                                >{{ notification.title }}</span
                                            >
                                            <span
                                                class="notification-item-time"
                                                >{{
                                                    notificationTime(
                                                        notification.created_at,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <p
                                            v-if="notification.body"
                                            class="notification-item-body"
                                        >
                                            {{ notification.body }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="notifications.length === 0"
                                        class="notification-empty"
                                    >
                                        Chưa có thông báo nào
                                    </div>
                                </div>
                            </div>
                        </header>

                        <div class="search-section">
                            <div class="search-input-box">
                                <ion-icon
                                    :icon="searchOutline"
                                    class="search-icon"
                                ></ion-icon>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Tìm kiếm"
                                    class="search-real-input"
                                    @keyup.enter="loadConversations"
                                />
                                <button
                                    v-if="searchQuery"
                                    class="clear-search-btn"
                                    @click="
                                        searchQuery = '';
                                        loadConversations();
                                    "
                                >
                                    <ion-icon :icon="closeOutline"></ion-icon>
                                </button>
                            </div>
                        </div>

                        <div class="folder-tabs-wrapper">
                            <div class="folder-tabs">
                                <div
                                    v-for="folder in folders"
                                    :key="folder"
                                    :class="[
                                        'folder-pill',
                                        { active: activeFolder === folder },
                                    ]"
                                    @click="activeFolder = folder"
                                >
                                    {{ folder }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <main class="chat-list-scrollable">
                        <!-- Loading skeleton -->
                        <template v-if="isLoading">
                            <div v-for="i in 7" :key="i" class="skeleton-row">
                                <div class="skeleton-av"></div>
                                <div class="skeleton-lines">
                                    <div class="skeleton-line w-60"></div>
                                    <div class="skeleton-line w-80"></div>
                                    <div class="skeleton-line w-40"></div>
                                </div>
                            </div>
                        </template>

                        <div
                            v-for="item in filteredChats"
                            :key="item.id"
                            class="chat-item"
                            @click="openChat(item)"
                        >
                            <div class="chat-avatar-wrapper">
                                <div
                                    class="chat-avatar text-avatar"
                                    :style="{ backgroundColor: item.avatarBg }"
                                >
                                    <span class="avatar-letter">{{
                                        item.avatarText
                                    }}</span>
                                </div>
                                <div
                                    v-if="item.channelSource"
                                    :class="[
                                        'channel-source-badge',
                                        `badge-${item.channelSource}`,
                                    ]"
                                >
                                    <span
                                        v-if="item.channelSource === 'facebook'"
                                        >f</span
                                    >
                                    <span
                                        v-else-if="
                                            item.channelSource === 'telegram'
                                        "
                                        >✈</span
                                    >
                                    <span
                                        v-else-if="
                                            item.channelSource === 'zalo'
                                        "
                                        >Z</span
                                    >
                                    <span v-else>w</span>
                                </div>
                            </div>

                            <div class="chat-main-content">
                                <div class="chat-top-row">
                                    <div class="chat-name-row">
                                        <span class="chat-name">{{
                                            item.name
                                        }}</span>
                                        <ion-icon
                                            v-if="item.isMuted"
                                            :icon="volumeMute"
                                            class="chat-mute-icon"
                                        ></ion-icon>
                                    </div>
                                    <span class="chat-time">{{
                                        item.time
                                    }}</span>
                                </div>

                                <div
                                    v-if="item.tags && item.tags.length > 0"
                                    class="chat-tags-row"
                                >
                                    <span
                                        v-for="tag in item.tags.slice(0, 2)"
                                        :key="tag"
                                        class="conv-tag-badge"
                                        >{{ tag }}</span
                                    >
                                    <span v-if="item.tags.length > 2" class="conv-tag-badge">+{{ item.tags.length - 2 }}</span>
                                </div>

                                <div class="chat-bottom-row">
                                    <div class="chat-message-preview">
                                        <span class="preview-text">{{
                                            item.lastMessage.text
                                        }}</span>
                                    </div>
                                    <div class="chat-badges">
                                        <!-- Assignee micro-avatar: memorable moment -->
                                        <div
                                            v-if="item.assignedUser"
                                            class="chat-assignee-dot"
                                            :style="{ background: avatarColorFor(item.assignedUser.id) }"
                                            :title="item.assignedUser.name"
                                        >
                                            {{ item.assignedUser.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <ion-icon
                                            v-if="item.isPinned"
                                            :icon="pin"
                                            class="pin-icon"
                                        ></ion-icon>
                                        <span
                                            v-if="item.unreadCount"
                                            :class="['unread-badge', item.unreadType === 'gray' ? 'badge-gray' : 'badge-blue']"
                                            >{{ item.unreadCount }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty: search no results -->
                        <div
                            v-if="!isLoading && filteredChats.length === 0 && searchQuery"
                            class="empty-state"
                        >
                            <div class="empty-state-icon">🔍</div>
                            <p class="empty-state-title">Không tìm thấy kết quả</p>
                            <p class="empty-state-sub">Thử tìm bằng tên, số điện thoại hoặc nội dung</p>
                        </div>
                        <!-- Empty: no conversations -->
                        <div
                            v-else-if="!isLoading && filteredChats.length === 0"
                            class="empty-state"
                        >
                            <div class="empty-state-icon">💬</div>
                            <p class="empty-state-title">Chưa có hội thoại nào</p>
                            <p class="empty-state-sub">Hội thoại mới sẽ xuất hiện ở đây khi khách nhắn tin</p>
                        </div>
                    </main>
                </template>

                </div><!-- /.tab-area -->
                <!-- Floating dock: luôn hiển thị trên tất cả tabs -->
                <div class="floating-dock-container">
                    <nav class="floating-dock">
                        <div
                            :class="[
                                'dock-item',
                                { active: activeTab === 'contacts' },
                            ]"
                            @click="activeTab = 'contacts'"
                        >
                            <div class="dock-icon-box">
                                <ion-icon
                                    :icon="people"
                                    class="dock-icon"
                                ></ion-icon>
                            </div>
                            <span class="dock-label">Khách hàng</span>
                        </div>
                        <div
                            :class="[
                                'dock-item',
                                { active: activeTab === 'calls' },
                            ]"
                            @click="activeTab = 'calls'"
                        >
                            <div class="dock-icon-box">
                                <ion-icon
                                    :icon="statsChart"
                                    class="dock-icon"
                                ></ion-icon>
                            </div>
                            <span class="dock-label">Thống kê</span>
                        </div>
                        <div
                            :class="[
                                'dock-item',
                                { active: activeTab === 'chat' },
                            ]"
                            @click="activeTab = 'chat'"
                        >
                            <div class="dock-icon-box relative">
                                <ion-icon
                                    :icon="chatbubbles"
                                    class="dock-icon"
                                ></ion-icon>
                            </div>
                            <span class="dock-label">Chat</span>
                        </div>
                        <div
                            :class="[
                                'dock-item',
                                { active: activeTab === 'settings' },
                            ]"
                            @click="activeTab = 'settings'"
                        >
                            <div class="dock-icon-box relative">
                                <div class="settings-avatar-mini">
                                    <span class="mini-profile-pic">👤</span>
                                </div>
                            </div>
                            <span class="dock-label">Profile</span>
                        </div>
                    </nav>
                </div>
            </template>
        </div>
    </ion-app>
</template>

<style scoped src="./livechat/theme/livechat.css"></style>
