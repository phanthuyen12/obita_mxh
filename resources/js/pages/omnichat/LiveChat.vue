<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { useEcho } from '@laravel/echo-vue'
import { IonApp, IonIcon } from '@ionic/vue'
import axios from 'axios'
import {
  createOutline,
  searchOutline,
  volumeMute,
  pin,
  people,
  chatbubbles,
  powerOutline,
  addCircleOutline,
  closeOutline,
  statsChart,
} from 'ionicons/icons'
import { computed, onMounted, ref } from 'vue'

const page = usePage()
const authUser = computed(() => page.props.auth?.user ?? { name: '', email: '' })

import { store as storeMessage } from '@/actions/App/Http/Controllers/App/Omnichat/MessageController'
import ConversationReadController from '@/actions/App/Http/Controllers/App/Omnichat/ConversationReadController'
import dayjs from '@/dayjs'
import { logout } from '@/routes'
import { index as livechatConversations, show as livechatConversation } from '@/routes/app/omnichat/livechat/conversations'

import ChatRoom from './livechat/components/ChatRoom.vue'
import CustomersPage from './livechat/components/CustomersPage.vue'
import AnalyticsPage from './livechat/components/AnalyticsPage.vue'
import ProfilePage from './livechat/components/ProfilePage.vue'
import type { Attachment, ChannelSource, ChatItem, Message } from './livechat/types/chat'

type ConnectedChannel = {
  id: string
  provider: string
  name: string
  avatar_url: string | null
  status: string
  is_active: boolean
}

type Props = {
  workspaceId: string
  connectedChannels: ConnectedChannel[]
  labels: Array<{ id: string; name: string; color: string | null }>
  permissions: {
    manageChannels: boolean
    assignConversations: boolean
    sendMessages: boolean
    editContacts: boolean
  }
  currentUser: { id: string; name: string; avatar_url: string | null }
}

const props = defineProps<Props>()

// Map backend provider → applivechat channel source badge.
const PROVIDER_SOURCE: Record<string, ChannelSource> = {
  facebook: 'facebook',
  instagram: 'facebook',
  telegram: 'telegram',
  'zalo-oa': 'zalo',
  zalo: 'zalo',
  website: 'website',
}

const AVATAR_COLORS = ['#0866ff', '#0088cc', '#0068ff', '#db2777', '#059669', '#78350f', '#0284c7', '#09090b']

const activeFolder = ref('Tất cả')
const folders = ['Tất cả', '🔵 Facebook Page', '✈️ Telegram', '💬 Zalo OA', '🌐 Website', 'VIP ⭐']
const activeTab = ref('chat')
const selectedChat = ref<ChatItem | null>(null)
const searchQuery = ref('')
const chats = ref<ChatItem[]>([])
const isLoading = ref(false)

const avatarColorFor = (id: string): string => {
  let hash = 0
  for (let i = 0; i < id.length; i++) {
    hash = id.charCodeAt(i) + ((hash << 5) - hash)
  }
  return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length]
}

const formatTime = (iso: string | null): string => {
  if (!iso) {
    return ''
  }
  const value = dayjs(iso)
  return value.isSame(dayjs(), 'day') ? value.format('HH:mm') : value.format('DD/MM')
}

type ConversationSummary = {
  id: string
  contact: { display_name: string; avatar_url: string | null; phone?: string | null; notes?: string | null }
  channel: { provider: string }
  last_message_preview: string | null
  last_message_at: string | null
  unread_count: number
  labels: Array<{ id: string; name: string; color: string | null }>
}

const toChatItem = (conversation: ConversationSummary): ChatItem => {
  const source = PROVIDER_SOURCE[conversation.channel.provider] ?? 'website'
  const name = conversation.contact.display_name || 'Khách hàng'

  return {
    id: conversation.id,
    name,
    channelSource: source,
    phone: conversation.contact.phone ?? undefined,
    tags: conversation.labels.map((label) => label.name) as ChatItem['tags'],
    tagIds: conversation.labels.map((label) => label.id),
    unreadCount: conversation.unread_count > 0 ? conversation.unread_count : undefined,
    unreadType: 'blue',
    lastMessage: { text: conversation.last_message_preview ?? '' },
  }
}

const loadConversations = async (): Promise<void> => {
  isLoading.value = true
  try {
    const { data } = await axios.get(livechatConversations.url({ query: { search: searchQuery.value } }))
    chats.value = (data.data as ConversationSummary[]).map(toChatItem)
  } finally {
    isLoading.value = false
  }
}

onMounted(loadConversations)

// WebSocket (Laravel Reverb / Echo): nhận tin nhắn mới realtime cho mọi kênh đang chọn.
type BroadcastMessage = {
  id: string
  conversation_id: string
  direction: 'inbound' | 'outbound' | 'internal'
  type: string
  body: string | null
  status: string
  client_id: string | null
  sender: { id: string; name: string; avatar_url: string | null } | null
  attachments: Array<{ id: string; type: string; url?: string; original_name?: string }>
  sent_at: string | null
  created_at: string
}

type MessageCreatedPayload = { message: BroadcastMessage }

const applyIncomingMessage = (message: BroadcastMessage): void => {
  const conversation = chats.value.find((chat) => chat.id === message.conversation_id)

  // Cập nhật phòng chat đang mở.
  if (selectedChat.value && selectedChat.value.id === message.conversation_id) {
    // Bỏ qua echo của tin nhắn mình vừa gửi tối ưu (đã có trong phòng chat).
    const duplicate = selectedChat.value.messages.some(
      (m) => m.id === message.id || (message.client_id !== null && m.clientId === message.client_id),
    )
    if (!duplicate) {
      const payload: MessagePayload = {
        id: message.id,
        direction: message.direction === 'outbound' ? 'outbound' : 'inbound',
        body: message.body,
        sent_at: message.sent_at,
        created_at: message.created_at,
        read_at: message.direction === 'outbound' ? message.sent_at : null,
      }
      selectedChat.value = {
        ...selectedChat.value,
        messages: [...selectedChat.value.messages, toMessage(payload)],
      }
    }
  }

  // Cập nhật danh sách hội thoại: đưa lên đầu + badge chưa đọc.
  if (conversation) {
    conversation.lastMessage.text = message.body ?? `[${message.type}]`
    conversation.time = formatTime(message.sent_at ?? message.created_at)

    if (message.direction === 'inbound' && message.conversation_id !== selectedChat.value?.id) {
      conversation.unreadCount = (typeof conversation.unreadCount === 'number' ? conversation.unreadCount : 0) + 1
      conversation.unreadType = 'blue'
    }
    chats.value = [conversation, ...chats.value.filter((chat) => chat.id !== conversation.id)]
  } else {
    // Hội thoại mới chưa có trong danh sách → tải lại.
    loadConversations()
  }
}

for (const channel of props.connectedChannels) {
  useEcho<MessageCreatedPayload>(
    `omnichat.channel.${channel.id}`,
    '.omnichat.message.created',
    ({ message }) => {
      applyIncomingMessage(message)
    },
  )
}

const filteredChats = computed(() =>
  chats.value.filter((chat) => {
    const matchFolder =
      activeFolder.value === 'Tất cả' ||
      (chat.folderCategory && chat.folderCategory.includes(activeFolder.value))
    const matchQuery =
      !searchQuery.value ||
      chat.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      chat.lastMessage.text.toLowerCase().includes(searchQuery.value.toLowerCase())

    return matchFolder && matchQuery
  }),
)

type MessagePayload = {
  id: string
  direction: 'inbound' | 'outbound'
  body: string | null
  sent_at: string | null
  created_at: string
  read_at: string | null
}

const toMessage = (message: MessagePayload): Message => ({
  id: message.id,
  sender: message.direction === 'outbound' ? 'me' : 'other',
  text: message.body ?? undefined,
  time: formatTime(message.sent_at ?? message.created_at),
  isRead: message.read_at !== null,
})

const openChat = async (chat: ChatItem): Promise<void> => {
  chat.unreadCount = undefined
  selectedChat.value = { ...chat, messages: [] }

  const { data } = await axios.get(livechatConversation.url(chat.id))
  selectedChat.value = {
    ...chat,
    contactId: data.conversation.contact.id as string,
    messages: (data.messages.data as MessagePayload[]).map(toMessage),
  }

  // Mark the conversation read once opened.
  try {
    await axios.post(ConversationReadController.url(chat.id))
  } catch {
    // Non-blocking: read receipts should never break opening a chat.
  }
}

const handleSend = async ({ text, attachment, clientId }: { id: string; text: string; attachment: Attachment | null; clientId: string }): Promise<void> => {
  const payload = new FormData()
  payload.append('body', text)
  payload.append('mode', 'reply')
  payload.append('client_id', clientId)

  if (attachment?.url && attachment.type === 'image') {
    const blob = await (await fetch(attachment.url)).blob()
    payload.append('image', blob, attachment.name)
  }

  await axios.post(storeMessage.url(id), payload)
}

const handleUpdateLastMessage = ({ id, text, time }: { id: string; text: string; time: string }): void => {
  const target = chats.value.find((chat) => chat.id === id)
  if (target) {
    target.lastMessage.text = text
    target.time = time
  }
}

const handleLogout = (): void => {
  router.post(logout.url())
}

type CustomerLike = {
  id: string
  name: string
  phone: string
  avatarText: string
  avatarBg: string
  tags?: string[]
  notes?: string
  lastActive?: string
}

const handleChatWithCustomer = (customer: CustomerLike): void => {
  activeTab.value = 'chat'

  const existing = chats.value.find((chat) => chat.phone === customer.phone && customer.phone !== '')
  if (existing) {
    openChat(existing)
    return
  }

  const placeholder: ChatItem = {
    id: `contact_${customer.id}`,
    name: customer.name,
    phone: customer.phone,
    avatarType: 'text',
    avatarText: customer.avatarText,
    avatarBg: customer.avatarBg,
    time: 'Vừa xong',
    tags: (customer.tags ?? []) as ChatItem['tags'],
    lastMessage: { text: customer.notes || 'Khách hàng chưa có hội thoại gần đây' },
  }
  chats.value.unshift(placeholder)
  selectedChat.value = placeholder
}
</script>

<template>
  <Head title="LiveChat" />

  <ion-app>
    <div class="ios-device-container">
      <!-- Phòng chat chi tiết -->
      <template v-if="selectedChat">
        <ChatRoom
          :chat="selectedChat"
          @back="selectedChat = null"
          @send="handleSend"
          @update-last-message="handleUpdateLastMessage"
        />
      </template>

      <!-- Màn hình chính đa tab -->
      <template v-else>
        <template v-if="activeTab === 'contacts'">
          <CustomersPage @chat-with="handleChatWithCustomer" />
        </template>

        <template v-else-if="activeTab === 'calls'">
          <AnalyticsPage />
        </template>

        <template v-else-if="activeTab === 'settings'">
          <ProfilePage
            :user="{ name: currentUser.name, avatar_url: currentUser.avatar_url }"
            :channels="props.connectedChannels"
            @logout="handleLogout"
          />
        </template>

        <template v-else>
          <div class="top-sticky-wrapper">
            <header class="telegram-header">
              <button class="header-btn-text">Sửa</button>
              <div class="header-title">
                <div class="tg-paper-plane-icon">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM16.64 8.8L15.01 16.48C14.89 17.02 14.57 17.15 14.12 16.9L11.64 15.07L10.44 16.22C10.31 16.35 10.2 16.46 9.94 16.46L10.12 13.91L14.76 9.72C14.96 9.54 14.71 9.44 14.44 9.62L8.71 13.23L6.24 12.46C5.7 12.29 5.69 11.92 6.35 11.66L16.03 7.93C16.48 7.76 16.87 8.03 16.64 8.8Z" fill="white"/>
                  </svg>
                </div>
                <span class="title-text">Chat</span>
              </div>
              <div class="header-actions">
                <button class="action-circle-btn" title="Trạng thái">
                  <ion-icon :icon="powerOutline" class="action-icon-small"></ion-icon>
                </button>
                <button class="action-circle-btn" title="Thêm">
                  <ion-icon :icon="addCircleOutline" class="action-icon-small"></ion-icon>
                </button>
                <button class="action-icon-btn" title="Soạn tin">
                  <ion-icon :icon="createOutline"></ion-icon>
                </button>
              </div>
            </header>

            <div class="search-section">
              <div class="search-input-box">
                <ion-icon :icon="searchOutline" class="search-icon"></ion-icon>
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Tìm kiếm"
                  class="search-real-input"
                  @keyup.enter="loadConversations"
                />
                <button v-if="searchQuery" class="clear-search-btn" @click="searchQuery = ''; loadConversations()">
                  <ion-icon :icon="closeOutline"></ion-icon>
                </button>
              </div>
            </div>

            <div class="folder-tabs-wrapper">
              <div class="folder-tabs">
                <div
                  v-for="folder in folders"
                  :key="folder"
                  :class="['folder-pill', { active: activeFolder === folder }]"
                  @click="activeFolder = folder"
                >
                  {{ folder }}
                </div>
              </div>
            </div>
          </div>

          <main class="chat-list-scrollable">
            <div v-if="isLoading" class="empty-state">Đang tải hội thoại...</div>

            <div
              v-for="item in filteredChats"
              :key="item.id"
              class="chat-item"
              @click="openChat(item)"
            >
              <div class="chat-avatar-wrapper">
                <div class="chat-avatar text-avatar" :style="{ backgroundColor: item.avatarBg }">
                  <span class="avatar-letter">{{ item.avatarText }}</span>
                </div>
                <div
                  v-if="item.channelSource"
                  :class="['channel-source-badge', `badge-${item.channelSource}`]"
                >
                  <span v-if="item.channelSource === 'facebook'">f</span>
                  <span v-else-if="item.channelSource === 'telegram'">✈</span>
                  <span v-else-if="item.channelSource === 'zalo'">Z</span>
                  <span v-else>🌐</span>
                </div>
              </div>

              <div class="chat-main-content">
                <div class="chat-top-row">
                  <div class="chat-name-row">
                    <span class="chat-name">{{ item.name }}</span>
                    <ion-icon v-if="item.isMuted" :icon="volumeMute" class="chat-mute-icon"></ion-icon>
                  </div>
                  <span class="chat-time">{{ item.time }}</span>
                </div>

                <div v-if="item.tags && item.tags.length > 0" class="chat-tags-row">
                  <span v-for="tag in item.tags" :key="tag" class="conv-tag-badge">🏷️ {{ tag }}</span>
                </div>

                <div class="chat-bottom-row">
                  <div class="chat-message-preview">
                    <span class="preview-text">{{ item.lastMessage.text }}</span>
                  </div>
                  <div class="chat-badges">
                    <ion-icon v-if="item.isPinned" :icon="pin" class="pin-icon"></ion-icon>
                    <span v-if="item.unreadCount" class="unread-badge badge-blue">{{ item.unreadCount }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="!isLoading && filteredChats.length === 0" class="empty-state">
              <p>Không có cuộc trò chuyện nào trong thư mục "{{ activeFolder }}"</p>
            </div>
          </main>
        </template>

        <div class="floating-dock-container">
          <nav class="floating-dock">
            <div :class="['dock-item', { active: activeTab === 'contacts' }]" @click="activeTab = 'contacts'">
              <div class="dock-icon-box"><ion-icon :icon="people" class="dock-icon"></ion-icon></div>
              <span class="dock-label">Khách hàng</span>
            </div>
            <div :class="['dock-item', { active: activeTab === 'calls' }]" @click="activeTab = 'calls'">
              <div class="dock-icon-box"><ion-icon :icon="statsChart" class="dock-icon"></ion-icon></div>
              <span class="dock-label">Thống kê</span>
            </div>
            <div :class="['dock-item', { active: activeTab === 'chat' }]" @click="activeTab = 'chat'">
              <div class="dock-icon-box relative"><ion-icon :icon="chatbubbles" class="dock-icon"></ion-icon></div>
              <span class="dock-label">Chat</span>
            </div>
            <div :class="['dock-item', { active: activeTab === 'settings' }]" @click="activeTab = 'settings'">
              <div class="dock-icon-box relative">
                <div class="settings-avatar-mini"><span class="mini-profile-pic">👤</span></div>
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
