<script setup lang="ts">
import { ref, computed } from 'vue'
import {
  IonApp,
  IonIcon
} from '@ionic/vue'
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
  statsChart
} from 'ionicons/icons'
import type { ChatItem } from './types/chat'
import ChatRoom from './components/ChatRoom.vue'
import CustomersPage, { type Customer } from './components/CustomersPage.vue'
import AnalyticsPage from './components/AnalyticsPage.vue'
import ProfilePage from './components/ProfilePage.vue'
import LoginPage from './components/LoginPage.vue'

// Auth State (Đăng nhập)
const isAuthenticated = ref(true)

const handleLoginSuccess = () => {
  isAuthenticated.value = true
}

const handleLogout = () => {
  isAuthenticated.value = false
  activeTab.value = 'chat'
}

// Tabs & Nguồn Kênh Hỗ Trợ Đa Nền Tảng (OmiChat / Omnichannel LiveChat)
const activeFolder = ref('Tất cả')
const folders = ['Tất cả', '🔵 Facebook Page', '✈️ Telegram', '💬 Zalo OA', '🌐 Website', 'VIP ⭐']

// Active Bottom Tab
const activeTab = ref('chat')

// Selected Chat for Room View
const selectedChat = ref<ChatItem | null>(null)

// Search text
const searchQuery = ref('')

const chats = ref<ChatItem[]>([
  {
    id: 'fb_1',
    name: 'BÁO CÁO ADS| AETRADING',
    channelSource: 'facebook',
    channelName: 'Page: AE Trading Media',
    folderCategory: ['Tất cả', '🔵 Facebook Page'],
    tags: ['Chốt đơn', 'VIP'],
    avatarType: 'text',
    avatarText: 'B',
    avatarBg: '#0866ff',
    time: '26/08',
    statusText: 'Trang Facebook • Khách hàng nhắn qua Messenger',
    isPinned: true,
    lastMessage: {
      text: 'Xin chào! 👋 Mình là Trợ lý AI chuyên gia Facebook Ads. Hiện tại...'
    },
    messages: [
      {
        id: 'msg_1',
        sender: 'other',
        text: 'Xin chào! 👋 Mình là Trợ lý AI chuyên gia Facebook Ads. Bạn cần xem báo cáo chiến dịch nào hôm nay?',
        time: '26/08'
      }
    ]
  },
  {
    id: 'tg_1',
    name: 'Cộng Đồng MMO | API Automation',
    channelSource: 'telegram',
    channelName: 'Telegram Group (VIP)',
    folderCategory: ['Tất cả', '✈️ Telegram'],
    tags: ['Đang tư vấn'],
    avatarType: 'icon',
    avatarBg: '#0088cc',
    time: '08:42',
    statusText: '12.450 thành viên',
    isMuted: true,
    unreadCount: '535',
    unreadType: 'gray',
    lastMessage: {
      sender: 'Doan Dung',
      prefixIcon: 'photo',
      text: 'Ảnh'
    },
    messages: [
      {
        id: 'msg_mmo_1',
        sender: 'other',
        text: 'Doan Dung: Chia sẻ anh em bộ code API automation mới nhất test chạy mượt lắm.',
        time: '08:40'
      },
      {
        id: 'msg_mmo_2',
        sender: 'other',
        text: 'Ảnh đính kèm tài liệu demo',
        time: '08:42'
      }
    ]
  },
  {
    id: 'zl_1',
    name: 'Anh Hoàng (Zalo OA Khách sỉ)',
    channelSource: 'zalo',
    channelName: 'Zalo Official Account: Kho Sỉ MMO',
    folderCategory: ['Tất cả', '💬 Zalo OA', 'VIP ⭐'],
    tags: ['VIP', 'Chốt đơn'],
    avatarType: 'text',
    avatarText: 'H',
    avatarBg: '#0068ff',
    time: '08:41',
    statusText: 'Zalo OA • Khách quan tâm số lượng lớn',
    unreadCount: '3',
    unreadType: 'blue',
    lastMessage: {
      text: 'Bên em còn slot tài khoản Gemini Pro với ChatGPT 50 acc không em?'
    },
    messages: [
      {
        id: 'zl_msg_1',
        sender: 'other',
        text: 'Bên em còn slot tài khoản Gemini Pro với ChatGPT 50 acc không em?',
        time: '08:41'
      }
    ]
  },
  {
    id: 'fb_2',
    name: 'Page Mỹ Phẩm & Spa Thuỳ Linh',
    channelSource: 'facebook',
    channelName: 'Page: Spa Thuỳ Linh Beauty',
    folderCategory: ['Tất cả', '🔵 Facebook Page'],
    tags: ['Đang tư vấn'],
    avatarType: 'text',
    avatarText: 'TL',
    avatarBg: '#db2777',
    time: '08:40',
    statusText: 'Facebook Messenger Inbox',
    unreadCount: '12',
    unreadType: 'blue',
    lastMessage: {
      text: 'Cho mình xin bảng giá dịch vụ chăm sóc da mụn tuần này nha shop'
    },
    messages: [
      {
        id: 'fb_tl_1',
        sender: 'other',
        text: 'Cho mình xin bảng giá dịch vụ chăm sóc da mụn tuần này nha shop',
        time: '08:40'
      }
    ]
  },
  {
    id: 'web_1',
    name: 'Khách vãng lai #4892 (Web LiveChat)',
    channelSource: 'website',
    channelName: 'Website: applivechat.com',
    folderCategory: ['Tất cả', '🌐 Website'],
    tags: ['Khách mới', 'Tiềm năng'],
    avatarType: 'icon',
    avatarBg: '#059669',
    time: '08:39',
    statusText: 'Đang xem trang: Bảng giá dịch vụ API',
    unreadCount: '1',
    unreadType: 'blue',
    lastMessage: {
      text: 'Tôi muốn tư vấn tích hợp API OmiChat vào phần mềm bán hàng'
    },
    messages: [
      {
        id: 'web_msg_1',
        sender: 'other',
        text: 'Tôi muốn tư vấn tích hợp API OmiChat vào phần mềm bán hàng',
        time: '08:39'
      }
    ]
  },
  {
    id: 'tg_2',
    name: 'KHOA LOL (Telegram Direct)',
    channelSource: 'telegram',
    channelName: 'Telegram Direct @khoalol',
    folderCategory: ['Tất cả', '✈️ Telegram', 'VIP ⭐'],
    tags: ['VIP', 'Chốt đơn'],
    avatarType: 'icon',
    avatarBg: '#78350f',
    time: '08:36',
    statusText: 'online vừa xong',
    unreadCount: '2',
    unreadType: 'blue',
    lastMessage: {
      text: 'nó suy luận vs tạo video bằng con'
    }
  },
  {
    id: 'zl_2',
    name: 'Chị Mai (Zalo CSKH VIP)',
    channelSource: 'zalo',
    channelName: 'Zalo OA Chăm Sóc',
    folderCategory: ['Tất cả', '💬 Zalo OA'],
    tags: ['Cần hỗ trợ'],
    avatarType: 'text',
    avatarText: 'M',
    avatarBg: '#0284c7',
    time: '08:35',
    statusText: 'Khách hàng VIP qua Zalo OA',
    unreadCount: '1',
    unreadType: 'blue',
    lastMessage: {
      text: 'Gửi giúp chị hóa đơn VAT đơn hôm qua qua Zalo nhé'
    }
  },
  {
    id: 'fb_3',
    name: 'DungMediaShop (Fanpage)',
    channelSource: 'facebook',
    channelName: 'Page: Dung Media Store',
    folderCategory: ['Tất cả', '🔵 Facebook Page'],
    tags: ['Khách mới'],
    avatarType: 'text',
    avatarText: 'D',
    avatarBg: '#09090b',
    time: '08:38',
    statusText: 'online • Facebook Messenger',
    unreadCount: '218',
    unreadType: 'blue',
    lastMessage: {
      text: '🔥 CHATGPT PLUS 1 THÁNG — SẴN HÀNG 🔥 💸 Giá cực tốt: 2...'
    }
  }
])

// Filtered chat by folder tab & search
const filteredChats = computed(() => {
  return chats.value.filter(chat => {
    // Check folder
    const matchFolder =
      activeFolder.value === 'Tất cả' ||
      (chat.folderCategory && chat.folderCategory.includes(activeFolder.value))

    // Check search query
    const matchQuery =
      !searchQuery.value ||
      chat.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      chat.lastMessage.text.toLowerCase().includes(searchQuery.value.toLowerCase())

    return matchFolder && matchQuery
  })
})

const openChat = (chat: ChatItem) => {
  // Clear unread count on open
  chat.unreadCount = undefined
  selectedChat.value = chat
}

const handleUpdateLastMessage = ({ id, text, time }: { id: string; text: string; time: string }) => {
  const target = chats.value.find(c => c.id === id)
  if (target) {
    target.lastMessage.text = text
    target.time = time
  }
}

const handleChatWithCustomer = (customer: Customer) => {
  // Tìm cuộc chat tương ứng hoặc tạo mới
  let chat = chats.value.find(c => c.name.includes(customer.name) || customer.name.includes(c.name))
  if (!chat) {
    chat = {
      id: 'chat_' + customer.id,
      name: customer.name,
      avatarType: 'text',
      avatarText: customer.avatarText,
      avatarBg: customer.avatarBg,
      statusText: customer.phone,
      time: 'Vừa xong',
      lastMessage: {
        text: customer.notes || 'Khách hàng liên hệ qua SĐT'
      }
    }
    chats.value.unshift(chat)
  }
  activeTab.value = 'chat'
  openChat(chat)
}
</script>

<template>
  <ion-app>
    <div class="ios-device-container">
      <!-- 0. Màn hình Đăng nhập (nếu chưa đăng nhập) -->
      <template v-if="!isAuthenticated">
        <LoginPage @login-success="handleLoginSuccess" />
      </template>

      <!-- 1. Khi đang mở phòng chat chi tiết -->
      <template v-else-if="selectedChat">
        <ChatRoom
          :chat="selectedChat"
          @back="selectedChat = null"
          @update-last-message="handleUpdateLastMessage"
        />
      </template>

      <!-- 2. Khi ở màn hình chính đa tab (Chat, Khách hàng, Thống kê, Profile) -->
      <template v-else>
        <!-- Tab 1: Khách hàng (Danh bạ) -->
        <template v-if="activeTab === 'contacts'">
          <CustomersPage @chat-with="handleChatWithCustomer" />
        </template>

        <!-- Tab 2: Thống kê (Cuộc gọi / Hiệu suất) -->
        <template v-else-if="activeTab === 'calls'">
          <AnalyticsPage />
        </template>

        <!-- Tab 3: Cài đặt & Profile -->
        <template v-else-if="activeTab === 'settings'">
          <ProfilePage @logout="handleLogout" />
        </template>

        <!-- Tab 4: Danh sách Trò Chuyện (Chat - Default) -->
        <template v-else>
          <!-- Top Fixed/Sticky Section: Header, Search & Folder Tabs -->
          <div class="top-sticky-wrapper">
            <!-- Top Header Navigation -->
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
                <button class="action-circle-btn" title="Trạng thái / Nguồn">
                  <ion-icon :icon="powerOutline" class="action-icon-small"></ion-icon>
                </button>
                <button class="action-circle-btn" title="Thêm / Tạo">
                  <ion-icon :icon="addCircleOutline" class="action-icon-small"></ion-icon>
                </button>
                <button class="action-icon-btn" title="Soạn tin">
                  <ion-icon :icon="createOutline"></ion-icon>
                </button>
              </div>
            </header>

            <!-- Search Bar Thực Tế -->
            <div class="search-section">
              <div class="search-input-box">
                <ion-icon :icon="searchOutline" class="search-icon"></ion-icon>
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Tìm kiếm"
                  class="search-real-input"
                />
                <button
                  v-if="searchQuery"
                  class="clear-search-btn"
                  @click="searchQuery = ''"
                >
                  <ion-icon :icon="closeOutline"></ion-icon>
                </button>
              </div>
            </div>

            <!-- Folder Pills (Tất cả, PHỞ BÒ, Crypto...) -->
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

          <!-- CUỘN MƯỢT MÀ: Danh sách Chat (Scrollable Area) -->
          <main class="chat-list-scrollable">
            <div
              v-for="item in filteredChats"
              :key="item.id"
              class="chat-item"
              @click="openChat(item)"
            >
              <!-- Avatar có huy hiệu nguồn kênh (Facebook / Telegram / Zalo / Website) -->
              <div class="chat-avatar-wrapper">
                <!-- Text Avatar -->
                <div
                  v-if="item.avatarType === 'text'"
                  class="chat-avatar text-avatar"
                  :style="{ backgroundColor: item.avatarBg }"
                >
                  <span class="avatar-letter">{{ item.avatarText }}</span>
                </div>

                <!-- Icon / Channel Avatar -->
                <div
                  v-else
                  class="chat-avatar icon-avatar"
                  :style="{ backgroundColor: item.avatarBg }"
                >
                  <template v-if="item.channelSource === 'telegram'">
                    <div class="tg-avatar-art">✈️</div>
                  </template>
                  <template v-else-if="item.channelSource === 'facebook'">
                    <div class="fb-avatar-art">👤</div>
                  </template>
                  <template v-else-if="item.channelSource === 'website'">
                    <div class="web-avatar-art">🌐</div>
                  </template>
                  <template v-else>
                    <div class="default-avatar-art">💬</div>
                  </template>
                </div>

                <!-- Huy hiệu Kênh góc Avatar (Facebook / Telegram / Zalo / Web) -->
                <div
                  v-if="item.channelSource"
                  :class="['channel-source-badge', `badge-${item.channelSource}`]"
                  :title="item.channelName || item.channelSource"
                >
                  <span v-if="item.channelSource === 'facebook'">f</span>
                  <span v-else-if="item.channelSource === 'telegram'">✈</span>
                  <span v-else-if="item.channelSource === 'zalo'">Z</span>
                  <span v-else-if="item.channelSource === 'website'">🌐</span>
                </div>
              </div>

              <!-- Main Info Column -->
              <div class="chat-main-content">
                <div class="chat-top-row">
                  <div class="chat-name-row">
                    <span class="chat-name">{{ item.name }}</span>
                    <!-- Nhãn tên kênh / Page -->
                    <span v-if="item.channelName" class="channel-page-label">
                      {{ item.channelName }}
                    </span>
                    <ion-icon
                      v-if="item.isMuted"
                      :icon="volumeMute"
                      class="chat-mute-icon"
                    ></ion-icon>
                  </div>
                  <span class="chat-time">{{ item.time }}</span>
                </div>

                <!-- Hiển thị tags cuộc hội thoại nếu có -->
                <div v-if="item.tags && item.tags.length > 0" class="chat-tags-row">
                  <span v-for="tag in item.tags" :key="tag" class="conv-tag-badge">
                    🏷️ {{ tag }}
                  </span>
                </div>

                <div class="chat-bottom-row">
                  <div class="chat-message-preview">
                    <span v-if="item.lastMessage.sender" class="preview-sender">
                      {{ item.lastMessage.sender }}
                    </span>
                    
                    <span v-if="item.lastMessage.prefixIcon === 'photo'" class="preview-media-thumb">
                      <span class="mini-thumb">🖼️</span>
                      <span class="media-type-label">Ảnh</span>
                    </span>
                    <span v-else-if="item.lastMessage.prefixIcon === 'reply'" class="preview-media-thumb">
                      <span class="forward-arrow">↪</span>
                      <span class="mini-forward-box">📊</span>
                      <span class="preview-text">{{ item.lastMessage.text }}</span>
                    </span>
                    <span v-else-if="item.lastMessage.prefixIcon === 'doc'" class="preview-media-thumb">
                      <span class="mini-doc">📁</span>
                      <span class="preview-text">{{ item.lastMessage.text }}</span>
                    </span>
                    <span v-else class="preview-text">
                      {{ item.lastMessage.text }}
                    </span>
                  </div>

                  <div class="chat-badges">
                    <!-- Pin icon -->
                    <ion-icon
                      v-if="item.isPinned"
                      :icon="pin"
                      class="pin-icon"
                    ></ion-icon>

                    <!-- Unread Counter Badge -->
                    <span
                      v-if="item.unreadCount"
                      :class="['unread-badge', item.unreadType === 'blue' ? 'badge-blue' : 'badge-gray']"
                    >
                      {{ item.unreadCount }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Empty State if no results -->
            <div v-if="filteredChats.length === 0" class="empty-state">
              <p>Không tìm thấy cuộc trò chuyện nào trong thư mục "{{ activeFolder }}"</p>
            </div>
          </main>
        </template>

        <!-- Floating Bottom Island (CỐ ĐỊNH CỨNG ở tất cả các tab) -->
        <div class="floating-dock-container">
          <nav class="floating-dock">
            <!-- 1. Danh bạ (Khách hàng) -->
            <div
              :class="['dock-item', { active: activeTab === 'contacts' }]"
              @click="activeTab = 'contacts'"
            >
              <div class="dock-icon-box">
                <ion-icon :icon="people" class="dock-icon"></ion-icon>
              </div>
              <span class="dock-label">Khách hàng</span>
            </div>

            <!-- 2. Thống kê (Analytics) -->
            <div
              :class="['dock-item', { active: activeTab === 'calls' }]"
              @click="activeTab = 'calls'"
            >
              <div class="dock-icon-box">
                <ion-icon :icon="statsChart" class="dock-icon"></ion-icon>
              </div>
              <span class="dock-label">Thống kê</span>
            </div>

            <!-- 3. Chat (Active with 69K Badge) -->
            <div
              :class="['dock-item', { active: activeTab === 'chat' }]"
              @click="activeTab = 'chat'"
            >
              <div class="dock-icon-box relative">
                <ion-icon :icon="chatbubbles" class="dock-icon"></ion-icon>
                <span class="dock-badge-red">69K</span>
              </div>
              <span class="dock-label">Chat</span>
            </div>

            <!-- 4. Cài đặt (Profile) -->
            <div
              :class="['dock-item', { active: activeTab === 'settings' }]"
              @click="activeTab = 'settings'"
            >
              <div class="dock-icon-box relative">
                <div class="settings-avatar-mini">
                  <span class="mini-profile-pic">👨‍💻</span>
                </div>
                <span class="dock-badge-dot">!</span>
              </div>
              <span class="dock-label">Profile</span>
            </div>
          </nav>
        </div>
      </template>
    </div>
  </ion-app>
</template>

<style scoped>
/* Device Container: Cân đối hoàn hảo cho cả Mobile & Desktop */
.ios-device-container {
  width: 100%;
  max-width: 500px;
  height: 100vh;
  height: 100dvh;
  margin: 0 auto;
  background-color: #000000;
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: hidden;
  font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", sans-serif;
  color: #ffffff;
  box-shadow: 0 0 50px rgba(0, 0, 0, 0.8);
}

/* 2. Top Sticky Header Wrapper (Đệm an toàn tuyệt đối tránh tai thỏ / Dynamic Island của iPhone) */
.top-sticky-wrapper {
  background-color: #000000;
  position: sticky;
  top: 0;
  z-index: 500;
  flex-shrink: 0;
  border-bottom: 0.5px solid rgba(255, 255, 255, 0.06);
  padding-top: max(env(safe-area-inset-top, 0px), 44px);
}

.telegram-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px 8px;
  height: 48px;
  box-sizing: border-box;
}

.header-btn-text {
  background: #1c1c1e;
  border: none;
  color: #ffffff;
  font-size: 15px;
  font-weight: 500;
  padding: 6px 14px;
  border-radius: 18px;
  cursor: pointer;
  transition: opacity 0.2s;
}
.header-btn-text:active {
  opacity: 0.7;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tg-paper-plane-icon {
  width: 30px;
  height: 30px;
  background: #2aabee;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(42, 171, 238, 0.4);
}
.tg-paper-plane-icon svg {
  width: 18px;
  height: 18px;
  transform: translate(-1px, 0.5px);
}

.title-text {
  font-size: 17px;
  font-weight: 700;
  letter-spacing: -0.4px;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.action-circle-btn {
  width: 34px;
  height: 34px;
  background: #1c1c1e;
  border: none;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  cursor: pointer;
}

.action-icon-small {
  font-size: 18px;
}

.action-icon-btn {
  width: 34px;
  height: 34px;
  background: #1c1c1e;
  border: none;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  cursor: pointer;
  font-size: 19px;
}

/* Search Bar */
.search-section {
  padding: 4px 14px 8px;
}

.search-input-box {
  background-color: #1c1c1e;
  height: 38px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  padding: 0 10px;
  gap: 8px;
  color: #8e8e93;
}

.search-real-input {
  background: transparent;
  border: none;
  outline: none;
  color: #ffffff;
  font-size: 15px;
  flex: 1;
}
.search-real-input::placeholder {
  color: #8e8e93;
}

.clear-search-btn {
  background: transparent;
  border: none;
  color: #8e8e93;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.search-icon {
  font-size: 16px;
  color: #8e8e93;
}

/* Folder Tabs (Tất cả, PHỞ BÒ, Crypto) */
.folder-tabs-wrapper {
  padding: 4px 14px 10px;
  overflow-x: auto;
  scrollbar-width: none;
}
.folder-tabs-wrapper::-webkit-scrollbar {
  display: none;
}

.folder-tabs {
  display: flex;
  gap: 10px;
  white-space: nowrap;
}

.folder-pill {
  background: #1c1c1e;
  color: #8e8e93;
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 14.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.folder-pill.active {
  background: #ffffff;
  color: #000000;
}

/* 3. CUỘN TỰ NHIÊN: Danh Sách Trò Chuyện (Scrollable Area) */
.chat-list-scrollable {
  flex: 1;
  overflow-y: auto;
  padding-bottom: 95px; /* Để trống cho Floating Dock */
  overscroll-behavior-y: contain;
}

.empty-state {
  text-align: center;
  color: #8e8e93;
  padding: 40px 20px;
  font-size: 14px;
}

.chat-item {
  display: flex;
  align-items: center;
  padding: 8px 14px;
  gap: 12px;
  position: relative;
  cursor: pointer;
  transition: background-color 0.15s;
}
.chat-item:active {
  background-color: #141416;
}

/* Avatar & Huy hiệu nguồn kênh (Facebook / Telegram / Zalo / Website) */
.chat-avatar-wrapper {
  flex-shrink: 0;
  position: relative;
}

.channel-source-badge {
  position: absolute;
  bottom: -2px;
  right: -2px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 800;
  color: #ffffff;
  border: 1.5px solid #000000;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
  z-index: 10;
}

.badge-facebook {
  background: #0866ff;
}
.badge-telegram {
  background: #0088cc;
  font-size: 9px;
}
.badge-zalo {
  background: #0068ff;
  font-weight: 900;
}
.badge-website {
  background: #10b981;
  font-size: 9px;
}

.channel-page-label {
  font-size: 10px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.08);
  color: #a1a1aa;
  white-space: nowrap;
  flex-shrink: 0;
}

.chat-avatar {
  width: 54px;
  height: 54px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.text-avatar .avatar-letter {
  font-size: 24px;
  font-weight: 700;
  color: #ffffff;
}

.icon-avatar {
  font-size: 24px;
}

.mmo-avatar-art {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.hacker-icon {
  font-size: 18px;
}
.sub-avatar-text {
  font-size: 6px;
  font-weight: 800;
  letter-spacing: 0.5px;
  color: #9ca3af;
}

.lion-avatar-art {
  font-size: 26px;
}

.banks-avatar-art {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.ball-icon {
  font-size: 16px;
}
.banks-text {
  font-size: 5px;
  font-weight: 900;
  color: #22c55e;
  letter-spacing: 0.2px;
}

.orange-avatar-art {
  font-size: 26px;
}

/* Main Content */
.chat-main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-width: 0;
  border-bottom: 0.5px solid #1c1c1e;
  padding-bottom: 10px;
}

.chat-top-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 3px;
}

.chat-name-row {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow: hidden;
}

.chat-name {
  font-size: 16px;
  font-weight: 600;
  color: #ffffff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: -0.2px;
}

.chat-mute-icon {
  font-size: 14px;
  color: #8e8e93;
  flex-shrink: 0;
}

.chat-time {
  font-size: 13px;
  color: #8e8e93;
  font-weight: 400;
  margin-left: 8px;
  flex-shrink: 0;
}

.chat-tags-row {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 4px;
}

.conv-tag-badge {
  background: rgba(56, 189, 248, 0.12);
  color: #38bdf8;
  border: 0.5px solid rgba(56, 189, 248, 0.25);
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 6px;
  white-space: nowrap;
}

.chat-bottom-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.chat-message-preview {
  display: flex;
  align-items: center;
  gap: 4px;
  color: #8e8e93;
  font-size: 14.5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
}

.preview-sender {
  color: #ffffff;
  font-weight: 500;
  margin-right: 2px;
}

.preview-media-thumb {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.media-type-label {
  color: #8e8e93;
}

.forward-arrow {
  color: #8e8e93;
  font-size: 12px;
}

.preview-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-badges {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.pin-icon {
  font-size: 16px;
  color: #8e8e93;
  transform: rotate(45deg);
}

.unread-badge {
  font-size: 13px;
  font-weight: 600;
  border-radius: 12px;
  padding: 1px 7px;
  min-width: 22px;
  text-align: center;
  color: #ffffff;
}

.badge-blue {
  background-color: #2a8bf2;
}

.badge-gray {
  background-color: #3a3a3c;
  color: #b0b0b5;
}

/* 4. Floating Bottom Dock (Thiết kế chuẩn thanh điều hướng iOS, căn đều, đẹp mắt) */
.floating-dock-container {
  position: absolute;
  bottom: calc(10px + env(safe-area-inset-bottom, 0px));
  left: 0;
  right: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 16px;
  z-index: 600;
  pointer-events: none;
}

.floating-dock {
  pointer-events: auto;
  background: rgba(26, 27, 32, 0.92);
  backdrop-filter: blur(30px);
  -webkit-backdrop-filter: blur(30px);
  border-radius: 40px;
  padding: 8px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border: 0.5px solid rgba(255, 255, 255, 0.14);
  box-shadow: 0 10px 36px rgba(0, 0, 0, 0.75);
  width: 90%;
  max-width: 360px;
}

.dock-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 2px 6px;
  border-radius: 14px;
  color: #8e8e93;
  transition: all 0.2s ease;
  flex: 1;
  user-select: none;
}

.dock-item:active {
  transform: scale(0.92);
}

.dock-item.active {
  color: #2a8bf2;
}

.dock-icon-box {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 28px;
  width: 28px;
}

.dock-icon {
  font-size: 23px;
}

.dock-label {
  font-size: 11px;
  font-weight: 500;
  margin-top: 3px;
  letter-spacing: -0.2px;
  white-space: nowrap;
}

.dock-item.active .dock-label {
  font-weight: 700;
  color: #2a8bf2;
}

.relative {
  position: relative;
}

.dock-badge-red {
  position: absolute;
  top: -6px;
  right: -12px;
  background-color: #ff3b30;
  color: #ffffff;
  font-size: 9px;
  font-weight: 800;
  border-radius: 9px;
  padding: 1px 4px;
  height: 14px;
  line-height: 13px;
  box-shadow: 0 2px 8px rgba(255, 59, 48, 0.5);
  border: 1px solid #1a1b20;
}

.dock-badge-dot {
  position: absolute;
  top: -3px;
  right: -4px;
  background-color: #ff3b30;
  color: #ffffff;
  font-size: 8.5px;
  font-weight: 900;
  border-radius: 50%;
  width: 13px;
  height: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1.5px solid #1a1b20;
}

.settings-avatar-mini {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background-color: #3f3f46;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.25);
}

.mini-profile-pic {
  font-size: 13px;
}
</style>
