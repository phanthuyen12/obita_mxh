<script setup lang="ts">
import { ref } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  callOutline,
  lockClosedOutline,
  eyeOutline,
  eyeOffOutline,
  shieldCheckmarkOutline,
  arrowForwardOutline,
  flashOutline
} from 'ionicons/icons'

const emit = defineEmits<{
  (e: 'login-success'): void
}>()

const loginMode = ref<'phone' | 'password'>('phone')
const phoneNumber = ref('+84 988 123 456')
const otpCode = ref('')
const password = ref('')
const showPassword = ref(false)
const rememberMe = ref(true)
const isSubmitting = ref(false)
const errorMessage = ref('')
const step = ref<'input' | 'otp'>('input')

const handleContinue = () => {
  errorMessage.value = ''
  if (loginMode.value === 'phone') {
    if (!phoneNumber.value.trim() || phoneNumber.value.length < 8) {
      errorMessage.value = 'Vui lòng nhập số điện thoại hợp lệ'
      return
    }
    isSubmitting.value = true
    setTimeout(() => {
      isSubmitting.value = false
      step.value = 'otp'
    }, 600)
  } else {
    if (!password.value) {
      errorMessage.value = 'Vui lòng nhập mật khẩu tài khoản'
      return
    }
    submitLogin()
  }
}

const submitLogin = () => {
  isSubmitting.value = true
  errorMessage.value = ''
  setTimeout(() => {
    isSubmitting.value = false
    emit('login-success')
  }, 700)
}

const quickLoginDemo = () => {
  emit('login-success')
}
</script>

<template>
  <div class="login-page">
    <!-- Nền trang trí & hiệu ứng ánh sáng Telegram -->
    <div class="glow-orb orb-1"></div>
    <div class="glow-orb orb-2"></div>

    <div class="login-card-container">
      <!-- Logo & Brand Header -->
      <div class="login-brand-header">
        <div class="tg-logo-ring">
          <div class="tg-paper-plane-big">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM16.64 8.8L15.01 16.48C14.89 17.02 14.57 17.15 14.12 16.9L11.64 15.07L10.44 16.22C10.31 16.35 10.2 16.46 9.94 16.46L10.12 13.91L14.76 9.72C14.96 9.54 14.71 9.44 14.44 9.62L8.71 13.23L6.24 12.46C5.7 12.29 5.69 11.92 6.35 11.66L16.03 7.93C16.48 7.76 16.87 8.03 16.64 8.8Z" fill="white"/>
            </svg>
          </div>
        </div>

        <h1 class="brand-title">Telegram LiveChat</h1>
        <p class="brand-sub">Hệ thống quản lý tin nhắn & CSKH đa kênh AI</p>

        <!-- Mode Switch: Số điện thoại / Mật khẩu -->
        <div class="mode-tabs">
          <button
            :class="['mode-tab-btn', { active: loginMode === 'phone' }]"
            @click="loginMode = 'phone'; step = 'input'; errorMessage = ''"
          >
            Số điện thoại
          </button>
          <button
            :class="['mode-tab-btn', { active: loginMode === 'password' }]"
            @click="loginMode = 'password'; step = 'input'; errorMessage = ''"
          >
            Mật khẩu / 2FA
          </button>
        </div>
      </div>

      <!-- Form nhập -->
      <form class="login-form" @submit.prevent="handleContinue">
        <!-- Error Alert -->
        <div v-if="errorMessage" class="error-banner">
          {{ errorMessage }}
        </div>

        <!-- 1. BƯỚC 1: NHẬP SĐT HOẶC MẬT KHẨU -->
        <template v-if="step === 'input'">
          <!-- Nhập SĐT -->
          <template v-if="loginMode === 'phone'">
            <div class="input-field-group">
              <label class="field-label">Quốc gia & Số điện thoại</label>
              <div class="phone-input-wrap">
                <span class="country-code-pill">🇻🇳 +84</span>
                <input
                  v-model="phoneNumber"
                  type="tel"
                  placeholder="0988 123 456"
                  class="real-text-input phone-input"
                  autofocus
                />
              </div>
              <span class="input-hint">Mã xác thực Telegram (OTP) sẽ được gửi về máy</span>
            </div>
          </template>

          <!-- Nhập Mật khẩu / 2FA -->
          <template v-else>
            <div class="input-field-group">
              <label class="field-label">Tài khoản hoặc Số điện thoại</label>
              <div class="input-box-wrap">
                <ion-icon :icon="callOutline" class="input-box-icon"></ion-icon>
                <input
                  v-model="phoneNumber"
                  type="text"
                  placeholder="@username hoặc SĐT"
                  class="real-text-input"
                />
              </div>
            </div>

            <div class="input-field-group">
              <label class="field-label">Mật khẩu cấp 2 (Cloud Password)</label>
              <div class="input-box-wrap">
                <ion-icon :icon="lockClosedOutline" class="input-box-icon"></ion-icon>
                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Nhập mật khẩu của bạn"
                  class="real-text-input"
                />
                <button
                  type="button"
                  class="eye-toggle-btn"
                  @click="showPassword = !showPassword"
                >
                  <ion-icon :icon="showPassword ? eyeOffOutline : eyeOutline"></ion-icon>
                </button>
              </div>
            </div>
          </template>
        </template>

        <!-- 2. BƯỚC 2: NHẬP MÃ OTP NẾU CHỌN SĐT -->
        <template v-else>
          <div class="otp-step-box">
            <div class="otp-subtext">
              Đã gửi mã xác nhận 5 chữ số đến:
              <strong class="phone-highlight">{{ phoneNumber }}</strong>
              <button type="button" class="change-phone-btn" @click="step = 'input'">Đổi số</button>
            </div>

            <div class="input-field-group">
              <label class="field-label">Mã xác thực OTP (Telegram Code)</label>
              <div class="input-box-wrap otp-wrap">
                <ion-icon :icon="shieldCheckmarkOutline" class="input-box-icon"></ion-icon>
                <input
                  v-model="otpCode"
                  type="text"
                  maxlength="6"
                  placeholder="• • • • •"
                  class="real-text-input otp-input"
                  autofocus
                />
              </div>
              <span class="input-hint">Gợi ý: Bấm tiếp tục bên dưới để đăng nhập trực tiếp demo</span>
            </div>
          </div>
        </template>

        <!-- Remember me & biometric -->
        <div class="login-options-row">
          <label class="remember-checkbox-label">
            <input v-model="rememberMe" type="checkbox" class="real-checkbox" />
            <span>Ghi nhớ đăng nhập</span>
          </label>
          <a href="#" class="forgot-link" @click.prevent>Quên mật khẩu?</a>
        </div>

        <!-- Main CTA Button -->
        <button
          type="button"
          class="primary-login-btn"
          :disabled="isSubmitting"
          @click="step === 'otp' || loginMode === 'password' ? submitLogin() : handleContinue()"
        >
          <span v-if="isSubmitting" class="spinner-dot">Đang xác thực...</span>
          <span v-else class="btn-inner-row">
            <span>{{ step === 'otp' ? 'Xác thực & Vào Chat' : 'Tiếp tục' }}</span>
            <ion-icon :icon="arrowForwardOutline"></ion-icon>
          </span>
        </button>

        <!-- Nút Đăng nhập Nhanh Demo 1-Click -->
        <button
          type="button"
          class="demo-quick-login-btn"
          @click="quickLoginDemo"
        >
          <ion-icon :icon="flashOutline" class="demo-flash-icon"></ion-icon>
          <span>Vào nhanh với tài khoản Demo (Admin)</span>
        </button>
      </form>

      <!-- Security Guarantee footer -->
      <footer class="login-footer">
        <div class="security-badge">
          <ion-icon :icon="shieldCheckmarkOutline"></ion-icon>
          <span>Mã hóa đầu cuối End-to-End Encryption theo chuẩn MTProto</span>
        </div>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 100vh;
  background-color: #000000;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 30px 20px;
  box-sizing: border-box;
  font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", sans-serif;
  color: #ffffff;
}

/* Hiệu ứng mờ ánh sáng Telegram (Glow Orbs) */
.glow-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
  opacity: 0.35;
}
.orb-1 {
  width: 280px;
  height: 280px;
  background: #2aabee;
  top: -60px;
  right: -40px;
}
.orb-2 {
  width: 240px;
  height: 240px;
  background: #7c3aed;
  bottom: 40px;
  left: -50px;
}

.login-card-container {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 420px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* Brand Header */
.login-brand-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  margin-bottom: 24px;
}

.tg-logo-ring {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, #2aabee 0%, #229ed9 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 10px 28px rgba(42, 171, 238, 0.45);
  margin-bottom: 14px;
  border: 2px solid rgba(255, 255, 255, 0.2);
  transition: transform 0.3s;
}
.tg-logo-ring:hover {
  transform: scale(1.04);
}

.tg-paper-plane-big {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tg-paper-plane-big svg {
  width: 100%;
  height: 100%;
  transform: translate(-2px, 1px);
}

.brand-title {
  font-size: 24px;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 6px;
  letter-spacing: -0.4px;
}

.brand-sub {
  font-size: 13px;
  color: #8e8e93;
  margin: 0 0 18px;
  max-width: 280px;
  line-height: 1.4;
}

/* Mode Tabs */
.mode-tabs {
  display: flex;
  background: #1c1c1e;
  border-radius: 12px;
  padding: 3px;
  width: 100%;
  box-sizing: border-box;
}

.mode-tab-btn {
  flex: 1;
  background: transparent;
  border: none;
  color: #8e8e93;
  font-size: 13px;
  font-weight: 600;
  padding: 8px 12px;
  border-radius: 9px;
  cursor: pointer;
  transition: all 0.2s;
}
.mode-tab-btn.active {
  background: #2a3442;
  color: #2a8bf2;
}

/* Form Styles */
.login-form {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.error-banner {
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.35);
  color: #f87171;
  font-size: 13px;
  padding: 10px 14px;
  border-radius: 12px;
  text-align: center;
}

.input-field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  font-size: 12.5px;
  font-weight: 600;
  color: #a1a1aa;
}

.phone-input-wrap,
.input-box-wrap {
  display: flex;
  align-items: center;
  background: #121418;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 14px;
  padding: 0 14px;
  height: 48px;
  box-sizing: border-box;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.phone-input-wrap:focus-within,
.input-box-wrap:focus-within {
  border-color: #2a8bf2;
  box-shadow: 0 0 0 3px rgba(42, 139, 242, 0.15);
}

.country-code-pill {
  font-size: 14px;
  font-weight: 700;
  color: #ffffff;
  padding-right: 10px;
  border-right: 1px solid rgba(255, 255, 255, 0.1);
  margin-right: 10px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.input-box-icon {
  font-size: 18px;
  color: #8e8e93;
  margin-right: 10px;
}

.real-text-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  color: #ffffff;
  font-size: 15px;
  font-family: inherit;
}
.real-text-input::placeholder {
  color: #52525b;
}

.eye-toggle-btn {
  background: none;
  border: none;
  color: #8e8e93;
  font-size: 18px;
  padding: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
}
.eye-toggle-btn:hover {
  color: #ffffff;
}

.input-hint {
  font-size: 11.5px;
  color: #71717a;
}

/* OTP Specifics */
.otp-step-box {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.otp-subtext {
  font-size: 13px;
  color: #a1a1aa;
  line-height: 1.5;
  background: #121418;
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}
.phone-highlight {
  color: #ffffff;
  margin-left: 4px;
}
.change-phone-btn {
  background: none;
  border: none;
  color: #2a8bf2;
  font-size: 12px;
  font-weight: 600;
  margin-left: 8px;
  cursor: pointer;
}

.otp-input {
  letter-spacing: 6px;
  font-size: 18px;
  font-weight: 700;
  text-align: center;
}

/* Remember me row */
.login-options-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12.5px;
}

.remember-checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #a1a1aa;
  cursor: pointer;
}

.real-checkbox {
  accent-color: #2a8bf2;
  width: 16px;
  height: 16px;
}

.forgot-link {
  color: #2a8bf2;
  text-decoration: none;
  font-weight: 500;
}
.forgot-link:hover {
  text-decoration: underline;
}

/* Primary Button */
.primary-login-btn {
  background: linear-gradient(135deg, #2a8bf2 0%, #0070e0 100%);
  border: none;
  color: #ffffff;
  font-size: 15px;
  font-weight: 700;
  border-radius: 14px;
  height: 48px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 18px rgba(42, 139, 242, 0.4);
  transition: all 0.2s;
  margin-top: 6px;
}
.primary-login-btn:hover {
  opacity: 0.95;
  transform: translateY(-1px);
}
.primary-login-btn:active {
  transform: scale(0.98);
}

.btn-inner-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Demo Quick Login Button */
.demo-quick-login-btn {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: #e4e4e7;
  font-size: 13.5px;
  font-weight: 600;
  border-radius: 14px;
  height: 44px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.2s;
}
.demo-quick-login-btn:hover {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.2);
}
.demo-quick-login-btn:active {
  transform: scale(0.98);
}

.demo-flash-icon {
  color: #f59e0b;
  font-size: 17px;
}

/* Footer */
.login-footer {
  margin-top: 32px;
}

.security-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: #71717a;
  text-align: center;
}
</style>
