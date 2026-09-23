<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  chevronBack,
  attachOutline,
  send,
  micOutline,
  happyOutline,
  checkmarkDone,
  closeCircle,
  documentAttachOutline,
  pricetagOutline
} from 'ionicons/icons'
import type { ChatItem, Message, Attachment } from '../types/chat'

const props = defineProps<{
  chat: ChatItem
}>()

const emit = defineEmits<{
  (e: 'back'): void
  (e: 'update-last-message', payload: { id: string; text: string; time: string }): void
}>()

// Dữ liệu tin nhắn sao chép chính xác 100% từng câu từ ảnh chụp màn hình KHOA LOL
const defaultKhoaLolMessages: Message[] = [
  {
    id: 'k1',
    sender: 'other',
    text: 'có gì hot trong con này',
    time: '16:25'
  },
  {
    id: 'k2',
    sender: 'me',
    text: 't muốn export ra ấy',
    time: '16:58',
    isRead: true
  },
  {
    id: 'k3',
    sender: 'other',
    text: 'dấu ... gần chữ done',
    time: '16:59'
  },
  {
    id: 'k4',
    sender: 'me',
    text: 'có cách nào mọc được api nó ra tính điểm token ko ta @@',
    time: '17:05',
    isRead: true
  },
  {
    id: 'k5',
    sender: 'me',
    text: 'chứ t thấy nó kêu nhập key đó m',
    time: '17:05',
    isRead: true
  },
  {
    id: 'k6',
    sender: 'me',
    text: 'dùng key tốn tiền á',
    time: '17:05',
    isRead: true
  },
  {
    id: 'k7',
    sender: 'other',
    text: 'là code này của m làm hay người ta',
    time: '17:05'
  },
  {
    id: 'k8',
    sender: 'other',
    text: 'của m backup nó ra vscode xong tự thay key gemini m vô',
    time: '17:06'
  },
  {
    id: 'k9',
    sender: 'other',
    text: 't thấy m có con gehihi utral thì thiếu gì key tốn tiền',
    time: '17:09'
  },
  {
    id: 'divider_today',
    sender: 'other',
    time: '',
    text: '__DIVIDER_TODAY__'
  },
  {
    id: 'k10',
    sender: 'me',
    text: 'má t thấy nso kêu tốnt eienf m',
    time: '00:36',
    isRead: true
  },
  {
    id: 'divider_unread',
    sender: 'other',
    time: '',
    text: '__DIVIDER_UNREAD__'
  },
  {
    id: 'k11',
    sender: 'other',
    text: '??',
    time: '08:36'
  },
  {
    id: 'k12',
    sender: 'other',
    text: 'nó suy luận vs tạo video bằng con gì',
    time: '08:36'
  }
]

const messages = ref<Message[]>(
  props.chat.id === '7' || props.chat.name.includes('KHOA LOL')
    ? [...defaultKhoaLolMessages]
    : props.chat.messages && props.chat.messages.length > 0
    ? [...props.chat.messages]
    : [
        {
          id: 'm1',
          sender: 'other',
          text: props.chat.lastMessage.text || 'Xin chào bạn!',
          time: props.chat.time || '08:30'
        }
      ]
)

const inputText = ref('')
const selectedAttachment = ref<Attachment | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const messagesContainerRef = ref<HTMLElement | null>(null)

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainerRef.value) {
      messagesContainerRef.value.scrollTop = messagesContainerRef.value.scrollHeight
    }
  })
}

onMounted(() => {
  scrollToBottom()
})

const triggerAttach = () => {
  if (fileInputRef.value) {
    fileInputRef.value.click()
  }
}

const onFileSelected = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    const sizeMb = (file.size / (1024 * 1024)).toFixed(2)
    const sizeFormatted = file.size > 1024 * 1024 ? `${sizeMb} MB` : `${(file.size / 1024).toFixed(0)} KB`

    if (file.type.startsWith('image/')) {
      const previewUrl = URL.createObjectURL(file)
      selectedAttachment.value = {
        name: file.name,
        size: sizeFormatted,
        type: 'image',
        url: previewUrl
      }
    } else {
      selectedAttachment.value = {
        name: file.name,
        size: sizeFormatted,
        type: 'file'
      }
    }
  }
}

const removeAttachment = () => {
  if (selectedAttachment.value?.url) {
    URL.revokeObjectURL(selectedAttachment.value.url)
  }
  selectedAttachment.value = null
  if (fileInputRef.value) fileInputRef.value.value = ''
}

const sendMessage = () => {
  const text = inputText.value.trim()
  if (!text && !selectedAttachment.value) return

  const now = new Date()
  const hours = String(now.getHours()).padStart(2, '0')
  const minutes = String(now.getMinutes()).padStart(2, '0')
  const currentTime = `${hours}:${minutes}`

  const newMsg: Message = {
    id: 'msg_' + Date.now(),
    sender: 'me',
    text: text || undefined,
    time: currentTime,
    isRead: false,
    attachment: selectedAttachment.value ? { ...selectedAttachment.value } : undefined
  }

  messages.value.push(newMsg)
  emit('update-last-message', {
    id: props.chat.id,
    text: text || (selectedAttachment.value?.type === 'image' ? '[Hình ảnh]' : `[Tệp] ${selectedAttachment.value?.name}`),
    time: currentTime
  })

  inputText.value = ''
  selectedAttachment.value = null
  if (fileInputRef.value) fileInputRef.value.value = ''

  scrollToBottom()

  // Giả lập đối phương rep sau 1s
  setTimeout(() => {
    messages.value.push({
      id: 'reply_' + Date.now(),
      sender: 'other',
      text: 'đang test thử nè',
      time: currentTime
    })
    scrollToBottom()
  }, 1200)
}

// Logic Gắn Tag Cuộc Hội Thoại
const showTagModal = ref(false)
const availableConvTags = ['VIP', 'Chốt đơn', 'Đang tư vấn', 'Cần hỗ trợ', 'Khách mới', 'Tiềm năng'] as const

const toggleConversationTag = (tag: any) => {
  if (!props.chat.tags) props.chat.tags = []
  const idx = props.chat.tags.indexOf(tag)
  if (idx > -1) {
    props.chat.tags.splice(idx, 1)
  } else {
    props.chat.tags.push(tag)
  }
}
</script>

<template>
  <div class="chat-room-page">
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
          <span v-if="chat.channelSource" :class="['room-channel-pill', `pill-${chat.channelSource}`]">
            <template v-if="chat.channelSource === 'facebook'">🔵 FB Page</template>
            <template v-else-if="chat.channelSource === 'telegram'">✈️ Telegram</template>
            <template v-else-if="chat.channelSource === 'zalo'">💬 Zalo OA</template>
            <template v-else-if="chat.channelSource === 'website'">🌐 Website</template>
          </span>
        </div>
        <span class="header-user-status">
          {{ chat.channelName ? `${chat.channelName} • ` : '' }}hoạt động 1 phút trước • 🏷️ {{ chat.tags?.length || 0 }} thẻ
        </span>
      </div>

      <div class="header-right-actions">
        <!-- Nút gắn thẻ nhanh -->
        <button class="tag-quick-btn" title="Gắn tag cuộc hội thoại" @click="showTagModal = true">
          <ion-icon :icon="pricetagOutline"></ion-icon>
        </button>

        <!-- Avatar tròn góc phải có ảnh cam hoặc theo chat -->
        <div class="header-avatar-circle" @click="showTagModal = true">
          <div
            v-if="chat.avatarType === 'text'"
            class="avatar-text-fill"
            :style="{ backgroundColor: chat.avatarBg }"
          >
            <span class="avatar-letter-small">{{ chat.avatarText }}</span>
          </div>
          <div
            v-else-if="chat.id === '7' || chat.name.includes('KHOA LOL')"
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

    <!-- Dải hiển thị Tags cuộc hội thoại đang gắn -->
    <div v-if="chat.tags && chat.tags.length > 0" class="chat-tags-subbar">
      <span class="tags-subbar-label">Tags:</span>
      <div class="tags-pill-scroll">
        <span v-for="t in chat.tags" :key="t" class="conv-tag-pill">
          🏷️ {{ t }}
          <ion-icon :icon="closeCircle" class="remove-conv-tag" @click="toggleConversationTag(t)"></ion-icon>
        </span>
      </div>
      <button class="add-more-tag-btn" @click="showTagModal = true">
        + Gắn thêm
      </button>
    </div>

    <!-- 2. Phần Tin Nhắn Cuộn Tự Nhiên (Scrollable Area với Telegram Doodle Pattern) -->
    <div ref="messagesContainerRef" class="chat-scroll-area">
      <div class="doodle-pattern-overlay"></div>

      <div class="messages-inner-wrapper">
        <template v-for="msg in messages" :key="msg.id">
          <!-- Divider "Hôm nay" -->
          <div v-if="msg.text === '__DIVIDER_TODAY__'" class="system-divider-row">
            <span class="system-pill-badge">Hôm nay</span>
          </div>

          <!-- Divider "Tin nhắn chưa đọc" -->
          <div v-else-if="msg.text === '__DIVIDER_UNREAD__'" class="system-divider-unread-row">
            <span class="unread-banner-text">Tin nhắn chưa đọc</span>
          </div>

          <!-- Normal Message Row -->
          <div
            v-else
            :class="['msg-row', msg.sender === 'me' ? 'msg-row-me' : 'msg-row-other']"
          >
            <div :class="['msg-bubble', msg.sender === 'me' ? 'bubble-purple' : 'bubble-dark']">
              <!-- Attached Image preview -->
              <div v-if="msg.attachment && msg.attachment.type === 'image'" class="msg-attachment-img">
                <img :src="msg.attachment.url" :alt="msg.attachment.name" />
              </div>

              <!-- Attached Document preview -->
              <div v-else-if="msg.attachment && msg.attachment.type === 'file'" class="msg-attachment-doc">
                <ion-icon :icon="documentAttachOutline" class="file-icon"></ion-icon>
                <div class="file-meta">
                  <span class="file-name">{{ msg.attachment.name }}</span>
                  <span class="file-size">{{ msg.attachment.size }}</span>
                </div>
              </div>

              <!-- Message Text -->
              <div v-if="msg.text" class="bubble-content-text">
                {{ msg.text }}
              </div>

              <!-- Bubble Time & Read double ticks -->
              <div class="bubble-meta">
                <span class="bubble-time">{{ msg.time }}</span>
                <span v-if="msg.sender === 'me'" class="bubble-ticks">
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
        <div v-else class="preview-thumb-file">
          📄
        </div>
        <div class="preview-info-col">
          <span class="preview-file-name">{{ selectedAttachment.name }}</span>
          <span class="preview-file-size">{{ selectedAttachment.size }}</span>
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
        @change="onFileSelected"
      />

      <!-- Clip Icon (Kẹp ghim đính kèm) -->
      <button class="bar-icon-button" title="Đính kèm tệp / ảnh" @click="triggerAttach">
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
      <button
        v-else
        class="bar-icon-button"
        title="Ghi âm giọng nói"
      >
        <ion-icon :icon="micOutline"></ion-icon>
      </button>
    </footer>

    <!-- MODAL GẮN TAG CUỘC HỘI THOẠI (BOTTOM SHEET) -->
    <div v-if="showTagModal" class="conv-tag-backdrop" @click.self="showTagModal = false">
      <div class="conv-tag-sheet">
        <div class="sheet-drag-handle"></div>
        <div class="conv-sheet-header">
          <div class="conv-sheet-title-col">
            <span class="sheet-main-title">Gắn Tag Hội Thoại</span>
            <span class="sheet-sub-title">{{ chat.name }}</span>
          </div>
          <button class="sheet-done-btn" @click="showTagModal = false">
            Xong
          </button>
        </div>

        <div class="conv-sheet-body">
          <span class="select-label">Chạm để chọn / bỏ chọn tag phân loại:</span>
          <div class="conv-tags-grid">
            <button
              v-for="tag in availableConvTags"
              :key="tag"
              :class="['tag-select-pill', { active: chat.tags?.includes(tag) }]"
              @click="toggleConversationTag(tag)"
            >
              <span class="tag-bullet">•</span>
              {{ tag }}
            </button>
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
  background-color: #0b0e14;
  display: flex;
  flex-direction: column;
  z-index: 2000;
  overflow: hidden;
  font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", sans-serif;
  color: #ffffff;
}

/* 1. Header Cố Định (Chuẩn 100% theo ảnh & an toàn trên iPhone Notch/Dynamic Island) */
.chat-header-ios {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: calc(8px + max(env(safe-area-inset-top, 0px), 44px)) 12px 8px;
  background: rgba(18, 20, 26, 0.95);
  backdrop-filter: blur(25px);
  -webkit-backdrop-filter: blur(25px);
  border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
  min-height: 52px;
  box-sizing: content-box;
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
  gap: 6px;
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
}

/* Avatar tròn góc phải */
.header-avatar-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  background-color: #2c2c2e;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1.5px solid rgba(255, 255, 255, 0.15);
  flex-shrink: 0;
  cursor: pointer;
}

/* Dải hiển thị Tags cuộc hội thoại */
.chat-tags-subbar {
  background: rgba(18, 20, 26, 0.98);
  border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
  padding: 6px 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  z-index: 90;
  flex-shrink: 0;
}

.tags-subbar-label {
  font-size: 11.5px;
  font-weight: 700;
  color: #71717a;
}

.tags-pill-scroll {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
  scrollbar-width: none;
  flex: 1;
}
.tags-pill-scroll::-webkit-scrollbar {
  display: none;
}

.conv-tag-pill {
  background: rgba(56, 189, 248, 0.15);
  color: #38bdf8;
  border: 0.5px solid rgba(56, 189, 248, 0.3);
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  gap: 4px;
  white-space: nowrap;
}

.remove-conv-tag {
  font-size: 13px;
  cursor: pointer;
  color: #ef4444;
}

.add-more-tag-btn {
  background: transparent;
  border: none;
  color: #2a8bf2;
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

/* Bottom Sheet Modal Gắn Tag */
.conv-tag-backdrop {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
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
  background: #181b22;
  border-top-left-radius: 22px;
  border-top-right-radius: 22px;
  border: 0.5px solid rgba(255, 255, 255, 0.12);
  padding: 10px 16px 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  animation: slideUp 0.2s ease-out;
}

.conv-sheet-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
  padding-bottom: 8px;
}

.conv-sheet-title-col {
  display: flex;
  flex-direction: column;
}

.sheet-main-title {
  font-size: 15px;
  font-weight: 700;
  color: #ffffff;
}

.sheet-sub-title {
  font-size: 12px;
  color: #8e8e93;
}

.sheet-done-btn {
  background: #2a8bf2;
  border: none;
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  padding: 4px 14px;
  border-radius: 14px;
  cursor: pointer;
}

.conv-sheet-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.select-label {
  font-size: 12px;
  color: #a1a1aa;
}

.conv-tags-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag-select-pill {
  background: #232730;
  border: 0.5px solid rgba(255, 255, 255, 0.12);
  color: #d1d5db;
  font-size: 13px;
  font-weight: 600;
  padding: 6px 14px;
  border-radius: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s;
}

.tag-select-pill.active {
  background: #2563eb;
  color: #ffffff;
  border-color: #3b82f6;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4);
}

.tag-bullet {
  font-size: 16px;
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

/* 2. Phần Tin Nhắn Cuộn Tự Nhiên (Scrollable Area) */
.chat-scroll-area {
  flex: 1;
  overflow-y: auto;
  position: relative;
  overscroll-behavior-y: contain;
  background-color: #0b0c10;
}

/* Hình nền hoa văn Telegram (Doodle pattern) chuẩn ảnh */
.doodle-pattern-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  opacity: 0.16;
  background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.4' fill-rule='evenodd'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
  pointer-events: none;
}

.messages-inner-wrapper {
  position: relative;
  z-index: 2;
  padding: 14px 12px 24px;
  display: flex;
  flex-direction: column;
  gap: 7px;
}

/* Dividers */
.system-divider-row {
  display: flex;
  justify-content: center;
  margin: 6px 0;
}
.system-pill-badge {
  background: rgba(30, 32, 38, 0.85);
  color: #ffffff;
  font-size: 13px;
  font-weight: 500;
  padding: 3px 14px;
  border-radius: 14px;
  backdrop-filter: blur(10px);
}

.system-divider-unread-row {
  width: 100%;
  background: rgba(22, 24, 30, 0.88);
  border-top: 0.5px solid rgba(255, 255, 255, 0.08);
  border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
  text-align: center;
  padding: 4px 0;
  margin: 8px 0;
}
.unread-banner-text {
  font-size: 12px;
  color: #8e8e93;
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

/* Message Bubbles */
.msg-bubble {
  max-width: 82%;
  border-radius: 16px;
  padding: 7px 12px 6px;
  position: relative;
  font-size: 15px;
  line-height: 1.32;
  word-break: break-word;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

/* Tin nhắn đối phương: màu nâu xám tối chuẩn ảnh */
.bubble-dark {
  background-color: #232225;
  color: #ffffff;
  border-bottom-left-radius: 4px;
}

/* Tin nhắn của mình: màu tím gradient chuẩn ảnh */
.bubble-purple {
  background: #8b3aed;
  color: #ffffff;
  border-bottom-right-radius: 4px;
}

.bubble-content-text {
  display: inline;
}

.bubble-meta {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  float: right;
  margin-left: 8px;
  margin-top: 4px;
}

.bubble-time {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.55);
}

.bubble-ticks {
  display: inline-flex;
  align-items: center;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.85);
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
  padding: 0 10px env(safe-area-inset-bottom, 0px);
  gap: 8px;
  flex-shrink: 0;
  position: relative;
  z-index: 100;
  box-sizing: content-box;
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
  background-color: #1c1c1e;
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
