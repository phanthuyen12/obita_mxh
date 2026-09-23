<script setup lang="ts">
import { ref } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  keyOutline,
  shieldCheckmarkOutline,
  notificationsOutline,
  colorPaletteOutline,
  cloudDownloadOutline,
  logOutOutline,
  chevronForward,
  cameraOutline,
  checkmarkCircle,
  eyeOutline,
  eyeOffOutline,
  globeOutline,
  logoFacebook
} from 'ionicons/icons'

const emit = defineEmits<{
  (e: 'logout'): void
}>()

const geminiKey = ref('AIzaSyD98x7_example_key')
const showApiKey = ref(false)
const isOpenAiEnabled = ref(true)
const isSaved = ref(false)

// Kênh kết nối đa nền tảng (Omnichannel)
const fbConnected = ref(true)
const tgConnected = ref(true)
const zaloConnected = ref(true)
const webConnected = ref(true)

const toggleShowApiKey = () => {
  showApiKey.value = !showApiKey.value
}

const saveSettings = () => {
  isSaved.value = true
  setTimeout(() => {
    isSaved.value = false
  }, 2000)
}
</script>

<template>
  <div class="profile-page">
    <!-- Header -->
    <div class="profile-sticky-top">
      <header class="profile-header">
        <h1 class="page-title">Cài đặt & Profile</h1>
        <button class="save-header-btn" @click="saveSettings">
          {{ isSaved ? 'Đã lưu' : 'Lưu' }}
        </button>
      </header>
    </div>

    <main class="profile-scroll">
      <!-- User Info Card Siêu Đẹp -->
      <div class="user-hero-card">
        <div class="avatar-large-wrap">
          <div class="avatar-large">
            <span>PT</span>
          </div>
          <button class="change-avatar-btn" title="Đổi ảnh đại diện">
            <ion-icon :icon="cameraOutline"></ion-icon>
          </button>
        </div>

        <div class="user-meta-center">
          <div class="name-verify-row">
            <h2 class="user-full-name">Phan Thuyên</h2>
            <span class="verified-check" title="Đã xác minh">✓</span>
          </div>
          <span class="user-handle">@phanthuyen_mmo</span>
          <span class="user-phone-badge">📱 +84 988 123 456</span>
          
          <div class="premium-pills-row">
            <span class="premium-badge">⭐ Telegram Premium</span>
            <span class="bot-master-badge">⚡ Bot AI Master</span>
          </div>
        </div>

        <!-- 3 Thẻ thống kê tài khoản nhanh -->
        <div class="profile-quick-stats">
          <div class="p-stat-box">
            <span class="p-stat-val">12</span>
            <span class="p-stat-lbl">Kênh quản lý</span>
          </div>
          <div class="p-stat-box">
            <span class="p-stat-val text-emerald">98.5%</span>
            <span class="p-stat-lbl">Tỷ lệ rep AI</span>
          </div>
          <div class="p-stat-box">
            <span class="p-stat-val text-amber">VIP 3</span>
            <span class="p-stat-lbl">Cấp tài khoản</span>
          </div>
        </div>
      </div>

      <!-- Cấu hình API Bot & AI (Cực kỳ quan trọng cho ứng dụng chat) -->
      <div class="settings-group">
        <div class="group-header">KẾT NỐI API & TỰ ĐỘNG HÓA</div>

        <div class="setting-item api-item">
          <div class="setting-icon-box api-bg">
            <ion-icon :icon="keyOutline"></ion-icon>
          </div>
          <div class="setting-content">
            <span class="setting-label">Gemini API Key</span>
            <div class="api-input-wrap">
              <input
                v-model="geminiKey"
                :type="showApiKey ? 'text' : 'password'"
                class="api-input-field"
                placeholder="Nhập Google Gemini API Key"
              />
              <button
                class="toggle-eye-btn"
                type="button"
                @click="toggleShowApiKey"
                title="Hiện/Ẩn API Key"
              >
                <ion-icon :icon="showApiKey ? eyeOffOutline : eyeOutline"></ion-icon>
              </button>
            </div>
          </div>
        </div>

        <div class="setting-item">
          <div class="setting-icon-box ai-bg">
            <ion-icon :icon="shieldCheckmarkOutline"></ion-icon>
          </div>
          <div class="setting-content">
            <span class="setting-label">Tự động trả lời qua AI</span>
            <span class="setting-hint">Tự động chốt đơn khi khách nhắn SĐT</span>
          </div>
          <label class="toggle-switch">
            <input v-model="isOpenAiEnabled" type="checkbox" />
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>

      <!-- Quản Lý Kênh Kết Nối Đa Kênh (OmiChat / Omnichannel) -->
      <div class="settings-group">
        <div class="group-header">KÊNH HỖ TRỢ ĐA NỀN TẢNG (OMICHAT)</div>

        <!-- Facebook Page -->
        <div class="setting-item">
          <div class="setting-icon-box fb-channel-bg">
            <ion-icon :icon="logoFacebook"></ion-icon>
          </div>
          <div class="setting-content">
            <div class="channel-name-line">
              <span class="setting-label">Facebook Fanpage & Ads</span>
              <span class="status-live-pill">Đang kết nối</span>
            </div>
            <span class="setting-hint">3 Page Facebook đang hoạt động</span>
          </div>
          <label class="toggle-switch">
            <input v-model="fbConnected" type="checkbox" />
            <span class="toggle-slider"></span>
          </label>
        </div>

        <!-- Telegram -->
        <div class="setting-item">
          <div class="setting-icon-box tg-channel-bg">
            <span class="tg-mini-plane">✈️</span>
          </div>
          <div class="setting-content">
            <div class="channel-name-line">
              <span class="setting-label">Telegram Bot & Groups</span>
              <span class="status-live-pill">Đang kết nối</span>
            </div>
            <span class="setting-hint">Webhook Bot AI Master MTProto</span>
          </div>
          <label class="toggle-switch">
            <input v-model="tgConnected" type="checkbox" />
            <span class="toggle-slider"></span>
          </label>
        </div>

        <!-- Zalo OA -->
        <div class="setting-item">
          <div class="setting-icon-box zalo-channel-bg">
            <span class="zalo-letter">Z</span>
          </div>
          <div class="setting-content">
            <div class="channel-name-line">
              <span class="setting-label">Zalo Official Account (OA)</span>
              <span class="status-live-pill">Đang kết nối</span>
            </div>
            <span class="setting-hint">Tự động rep tin nhắn Zalo khách VIP</span>
          </div>
          <label class="toggle-switch">
            <input v-model="zaloConnected" type="checkbox" />
            <span class="toggle-slider"></span>
          </label>
        </div>

        <!-- Web LiveChat Widget -->
        <div class="setting-item">
          <div class="setting-icon-box web-channel-bg">
            <ion-icon :icon="globeOutline"></ion-icon>
          </div>
          <div class="setting-content">
            <div class="channel-name-line">
              <span class="setting-label">Website LiveChat Widget</span>
              <span class="status-live-pill">Đang kết nối</span>
            </div>
            <span class="setting-hint">Widget nhúng trực tiếp trên Website</span>
          </div>
          <label class="toggle-switch">
            <input v-model="webConnected" type="checkbox" />
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>

      <!-- Cài đặt Ứng dụng & Dữ liệu -->
      <div class="settings-group">
        <div class="group-header">ỨNG DỤNG & THÔNG BÁO</div>

        <div class="setting-item clickable">
          <div class="setting-icon-box noti-bg">
            <ion-icon :icon="notificationsOutline"></ion-icon>
          </div>
          <div class="setting-content">
            <span class="setting-label">Thông báo & Âm thanh</span>
            <span class="setting-hint">Bật chuông tin nhắn mới, rung iOS</span>
          </div>
          <ion-icon :icon="chevronForward" class="forward-icon"></ion-icon>
        </div>

        <div class="setting-item clickable">
          <div class="setting-icon-box theme-bg">
            <ion-icon :icon="colorPaletteOutline"></ion-icon>
          </div>
          <div class="setting-content">
            <span class="setting-label">Giao diện & Chủ đề</span>
            <span class="setting-hint">Telegram iOS Dark Mode (AMOLED)</span>
          </div>
          <ion-icon :icon="chevronForward" class="forward-icon"></ion-icon>
        </div>

        <div class="setting-item clickable">
          <div class="setting-icon-box data-bg">
            <ion-icon :icon="cloudDownloadOutline"></ion-icon>
          </div>
          <div class="setting-content">
            <span class="setting-label">Dữ liệu & Bộ nhớ tạm</span>
            <span class="setting-hint">Đã dùng 142 MB cache ảnh/tệp</span>
          </div>
          <ion-icon :icon="chevronForward" class="forward-icon"></ion-icon>
        </div>
      </div>

      <!-- Đăng xuất / Chuyển tài khoản -->
      <div class="settings-group">
        <div class="setting-item logout-item clickable" @click="emit('logout')">
          <div class="setting-icon-box logout-bg">
            <ion-icon :icon="logOutOutline"></ion-icon>
          </div>
          <div class="setting-content">
            <span class="setting-label logout-text">Đăng xuất tài khoản</span>
          </div>
        </div>
      </div>

      <!-- Toast thông báo lưu -->
      <div v-if="isSaved" class="save-toast">
        <ion-icon :icon="checkmarkCircle"></ion-icon>
        <span>Đã lưu cấu hình thành công!</span>
      </div>
    </main>
  </div>
</template>

<style scoped>
.profile-page {
  flex: 1;
  display: flex;
  flex-direction: column;
  background-color: #000000;
  overflow: hidden;
  min-height: 0;
  width: 100%;
}

.profile-sticky-top {
  background-color: #000000;
  border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
  flex-shrink: 0;
  width: 100%;
  padding-top: max(env(safe-area-inset-top, 0px), 44px);
}

.profile-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  color: #ffffff;
  margin: 0;
  letter-spacing: -0.4px;
}

.save-header-btn {
  background: #2a8bf2;
  border: none;
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 700;
  padding: 6px 16px;
  border-radius: 18px;
  cursor: pointer;
  transition: all 0.2s;
}
.save-header-btn:active {
  opacity: 0.8;
  transform: scale(0.96);
}

/* Scroll Area: Thêm padding bottom lớn (140px) để cuộn thoải mái không bị che */
.profile-scroll {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
  padding: 12px 14px 140px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  overscroll-behavior-y: contain;
  position: relative;
  -webkit-overflow-scrolling: touch;
}

/* User Hero Card */
.user-hero-card {
  background: #121418;
  border: 0.5px solid rgba(255, 255, 255, 0.08);
  border-radius: 18px;
  padding: 14px 14px 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.avatar-large-wrap {
  position: relative;
}

.avatar-large {
  width: 76px;
  height: 76px;
  border-radius: 50%;
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  font-weight: 800;
  color: #ffffff;
  box-shadow: 0 4px 16px rgba(124, 58, 237, 0.35);
}

.change-avatar-btn {
  position: absolute;
  bottom: -2px;
  right: -2px;
  background: #1c1c1e;
  border: 1px solid rgba(255, 255, 255, 0.2);
  width: 26px;
  height: 26px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #2a8bf2;
  font-size: 14px;
  cursor: pointer;
}

.user-meta-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
}

.user-full-name {
  font-size: 18px;
  font-weight: 700;
  color: #ffffff;
  margin: 0;
}

.user-handle {
  font-size: 13px;
  color: #2a8bf2;
}

.user-phone-badge {
  font-size: 12px;
  color: #8e8e93;
}

.name-verify-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.verified-check {
  background: #3b82f6;
  color: #ffffff;
  font-size: 11px;
  font-weight: 900;
  width: 17px;
  height: 17px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.premium-pills-row {
  display: flex;
  gap: 6px;
  margin-top: 4px;
}

.premium-badge {
  font-size: 11px;
  font-weight: 600;
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
  border: 0.5px solid rgba(245, 158, 11, 0.3);
  padding: 3px 10px;
  border-radius: 12px;
}

.bot-master-badge {
  font-size: 11px;
  font-weight: 600;
  background: rgba(56, 189, 248, 0.15);
  color: #38bdf8;
  border: 0.5px solid rgba(56, 189, 248, 0.3);
  padding: 3px 10px;
  border-radius: 12px;
}

.profile-quick-stats {
  display: flex;
  width: 100%;
  background: rgba(255, 255, 255, 0.04);
  border: 0.5px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  padding: 10px;
  margin-top: 6px;
}

.p-stat-box {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  border-right: 0.5px solid rgba(255, 255, 255, 0.08);
}
.p-stat-box:last-child {
  border-right: none;
}

.p-stat-val {
  font-size: 16px;
  font-weight: 800;
  color: #ffffff;
}

.text-emerald { color: #10b981; }
.text-amber { color: #f59e0b; }

.p-stat-lbl {
  font-size: 10.5px;
  color: #8e8e93;
}

/* Settings Group */
.settings-group {
  display: flex;
  flex-direction: column;
  gap: 1px;
  background: #121418;
  border: 0.5px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  overflow: hidden;
}

.group-header {
  font-size: 11px;
  font-weight: 700;
  color: #71717a;
  letter-spacing: 0.5px;
  padding: 10px 14px 4px;
}

.setting-item {
  display: flex;
  align-items: center;
  padding: 11px 14px;
  gap: 12px;
  background: #121418;
}

.setting-item.clickable:active {
  background: #1c1e24;
  cursor: pointer;
}

.setting-icon-box {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 17px;
  flex-shrink: 0;
}

.api-bg { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
.ai-bg { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.noti-bg { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.theme-bg { background: rgba(168, 85, 247, 0.15); color: #a855f7; }
.data-bg { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.logout-bg { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

/* Kênh Đa Nền Tảng (OmiChat) */
.fb-channel-bg { background: rgba(8, 102, 255, 0.15); color: #0866ff; font-size: 19px; }
.tg-channel-bg { background: rgba(0, 136, 204, 0.15); color: #0088cc; font-size: 16px; }
.zalo-channel-bg { background: rgba(0, 104, 255, 0.15); color: #0068ff; }
.web-channel-bg { background: rgba(16, 185, 129, 0.15); color: #10b981; }

.zalo-letter {
  font-weight: 900;
  font-size: 15px;
}
.tg-mini-plane {
  font-size: 16px;
}

.channel-name-line {
  display: flex;
  align-items: center;
  gap: 8px;
}

.status-live-pill {
  font-size: 9.5px;
  font-weight: 700;
  color: #34d399;
  background: rgba(16, 185, 129, 0.15);
  border: 0.5px solid rgba(16, 185, 129, 0.3);
  padding: 1px 6px;
  border-radius: 8px;
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
  font-weight: 500;
  color: #ffffff;
}

.setting-hint {
  font-size: 11px;
  color: #71717a;
}

.api-item {
  align-items: flex-start;
  padding-top: 13px;
  padding-bottom: 13px;
}

.api-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  margin-top: 6px;
  width: 100%;
}

.api-input-field {
  background: #1c1e24;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 10px;
  padding: 8px 36px 8px 12px;
  color: #ffffff;
  font-size: 13px;
  outline: none;
  width: 100%;
  box-sizing: border-box;
  font-family: inherit;
  letter-spacing: 0.3px;
  transition: border-color 0.2s;
}
.api-input-field:focus {
  border-color: #38bdf8;
}

.toggle-eye-btn {
  position: absolute;
  right: 8px;
  background: none;
  border: none;
  color: #8e8e93;
  font-size: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 4px;
}
.toggle-eye-btn:hover {
  color: #ffffff;
}

.forward-icon {
  font-size: 16px;
  color: #52525b;
}

.logout-item {
  border-radius: 14px;
  transition: background 0.15s;
}

.logout-text {
  color: #ef4444;
  font-weight: 600;
}

/* Toggle Switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 26px;
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
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: #3f3f46;
  border-radius: 26px;
  transition: 0.2s;
}
.toggle-slider:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  border-radius: 50%;
  transition: 0.2s;
}
input:checked + .toggle-slider {
  background-color: #34c759;
}
input:checked + .toggle-slider:before {
  transform: translateX(18px);
}

.save-toast {
  position: absolute;
  top: 14px;
  left: 50%;
  transform: translateX(-50%);
  background: #10b981;
  color: #000000;
  font-size: 13px;
  font-weight: 700;
  padding: 6px 16px;
  border-radius: 20px;
  display: flex;
  align-items: center;
  gap: 6px;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
  z-index: 100;
}
</style>
