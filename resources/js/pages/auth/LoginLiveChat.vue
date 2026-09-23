<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3'
import { IonIcon } from '@ionic/vue'
import {
  callOutline,
  lockClosedOutline,
  eyeOutline,
  eyeOffOutline,
  shieldCheckmarkOutline,
  arrowForwardOutline,
  alertCircleOutline,
} from 'ionicons/icons'
import { computed, ref } from 'vue'

import { store } from '@/routes/login'

defineProps<{
  status?: string
  redirect?: string | null
}>()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const rememberMe = ref(true)

const page = usePage()
const isSelfHosted = computed(() => Boolean(page.props.selfHosted))
</script>

<template>
  <Head title="Đăng nhập LiveChat" />

  <div class="login-page">
    <!-- Nền trang trí & hiệu ứng ánh sáng -->
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

        <h1 class="brand-title">LiveChat Omnichannel</h1>
        <p class="brand-sub">Hệ thống quản lý tin nhắn &amp; CSKH đa kênh AI</p>
      </div>

      <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing, wasSuccessful }"
        class="login-form"
      >
        <input type="hidden" name="redirect" :value="redirect ?? '/omnichat/livechat'" />
        <label class="real-checkbox-hidden">
          <input type="checkbox" name="remember" :checked="rememberMe" />
        </label>

        <!-- Success banner -->
        <div v-if="status || wasSuccessful" class="success-banner">
          {{ status || 'Xác thực thành công! Đang chuyển tới LiveChat...' }}
        </div>

        <!-- Email -->
        <div class="input-field-group">
          <label class="field-label" for="email">Email hoặc tài khoản</label>
          <div class="input-box-wrap" :class="{ 'has-error': errors.email }">
            <ion-icon :icon="callOutline" class="input-box-icon"></ion-icon>
            <input
              id="email"
              v-model="email"
              type="email"
              name="email"
              placeholder="email@example.com"
              class="real-text-input"
              autocomplete="email"
              autofocus
            />
          </div>
          <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
        </div>

        <!-- Mật khẩu -->
        <div class="input-field-group">
          <label class="field-label" for="password">Mật khẩu</label>
          <div class="input-box-wrap" :class="{ 'has-error': errors.password }">
            <ion-icon :icon="lockClosedOutline" class="input-box-icon"></ion-icon>
            <input
              id="password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              name="password"
              placeholder="Nhập mật khẩu của bạn"
              class="real-text-input"
              autocomplete="current-password"
            />
            <button
              type="button"
              class="eye-toggle-btn"
              @click="showPassword = !showPassword"
            >
              <ion-icon :icon="showPassword ? eyeOffOutline : eyeOutline"></ion-icon>
            </button>
          </div>
          <span v-if="errors.password" class="field-error">{{ errors.password }}</span>
        </div>

        <!-- Remember me -->
        <label class="remember-checkbox-label">
          <input v-model="rememberMe" type="checkbox" class="real-checkbox" />
          <span>Ghi nhớ đăng nhập</span>
        </label>

        <!-- Main CTA Button -->
        <button
          type="submit"
          class="primary-login-btn"
          :disabled="processing"
          data-test="login-button"
        >
          <span v-if="processing" class="spinner-dot">Đang xác thực...</span>
          <span v-else class="btn-inner-row">
            <span>Đăng nhập &amp; Vào Chat</span>
            <ion-icon :icon="arrowForwardOutline"></ion-icon>
          </span>
        </button>
      </Form>

      <!-- Security Guarantee footer -->
      <footer class="login-footer">
        <div class="security-badge">
          <ion-icon :icon="shieldCheckmarkOutline"></ion-icon>
          <span v-if="!isSelfHosted">Kết nối được bảo vệ theo chuẩn mã hóa TLS</span>
          <span v-else>Máy chủ riêng — kết nối được bảo vệ theo chuẩn mã hóa TLS</span>
        </div>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  position: relative;
  width: 100%;
  min-height: 100vh;
  background-color: #000000;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  overflow-y: auto;
  overflow-x: hidden;
  padding: max(env(safe-area-inset-top, 0px), 30px) 20px max(env(safe-area-inset-bottom, 0px), 30px);
  box-sizing: border-box;
  font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", sans-serif;
  color: #ffffff;
}

/* Hiệu ứng mờ ánh sáng (Glow Orbs) */
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
}

.tg-paper-plane-big svg {
  width: 42px;
  height: 42px;
  transform: translate(-2px, 1px);
}

.brand-title {
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -0.5px;
  margin: 0 0 6px;
}

.brand-sub {
  font-size: 13.5px;
  color: #8e8e93;
  margin: 0;
  max-width: 280px;
  line-height: 1.4;
}

/* Form */
.login-form {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: rgba(18, 20, 26, 0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 0.5px solid rgba(255, 255, 255, 0.1);
  border-radius: 22px;
  padding: 22px 20px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
}

.success-banner {
  background: rgba(16, 185, 129, 0.15);
  border: 0.5px solid rgba(16, 185, 129, 0.35);
  color: #34d399;
  font-size: 13px;
  font-weight: 600;
  padding: 10px 12px;
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
  font-weight: 700;
  color: #a1a1aa;
  letter-spacing: 0.2px;
}

.input-box-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #1c1c1e;
  border: 0.5px solid rgba(255, 255, 255, 0.12);
  border-radius: 14px;
  padding: 0 12px;
  height: 50px;
  transition: border-color 0.2s;
}
.input-box-wrap:focus-within {
  border-color: #2aabee;
}
.input-box-wrap.has-error {
  border-color: #ef4444;
}

.input-box-icon {
  font-size: 19px;
  color: #8e8e93;
  flex-shrink: 0;
}

.real-text-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  color: #ffffff;
  font-size: 15px;
  min-width: 0;
}
.real-text-input::placeholder {
  color: #6b6b70;
}

.eye-toggle-btn {
  background: transparent;
  border: none;
  color: #8e8e93;
  font-size: 19px;
  display: flex;
  align-items: center;
  cursor: pointer;
  padding: 4px;
}

.field-error {
  font-size: 12px;
  color: #f87171;
  font-weight: 500;
}

/* Remember me */
.remember-checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  color: #a1a1aa;
  cursor: pointer;
  user-select: none;
}

.real-checkbox {
  appearance: none;
  -webkit-appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 6px;
  background: #1c1c1e;
  border: 1px solid rgba(255, 255, 255, 0.25);
  cursor: pointer;
  position: relative;
  flex-shrink: 0;
}
.real-checkbox:checked {
  background: #2aabee;
  border-color: #2aabee;
}
.real-checkbox:checked::after {
  content: '✓';
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 900;
  color: #ffffff;
}

.real-checkbox-hidden {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

/* CTA */
.primary-login-btn {
  width: 100%;
  height: 52px;
  border: none;
  border-radius: 16px;
  background: linear-gradient(135deg, #2aabee 0%, #229ed9 100%);
  color: #ffffff;
  font-size: 16px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 8px 22px rgba(42, 171, 238, 0.4);
  transition: transform 0.15s, opacity 0.2s;
}
.primary-login-btn:active {
  transform: scale(0.98);
}
.primary-login-btn:disabled {
  opacity: 0.6;
  cursor: wait;
}

.btn-inner-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.spinner-dot {
  font-size: 14.5px;
  opacity: 0.9;
}

/* Footer */
.login-footer {
  margin-top: 20px;
}

.security-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: #6b6b70;
  max-width: 300px;
  text-align: center;
}

.security-badge ion-icon {
  flex-shrink: 0;
  font-size: 15px;
  color: #34d399;
}
</style>
