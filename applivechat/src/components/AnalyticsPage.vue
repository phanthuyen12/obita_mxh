<script setup lang="ts">
import { ref } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  trendingUpOutline,
  chatbubblesOutline,
  peopleOutline,
  walletOutline,
  flashOutline
} from 'ionicons/icons'

const timePeriod = ref<'today' | 'week' | 'month'>('today')

const stats = {
  today: {
    messagesCount: '1.482',
    responseRate: '99.2%',
    avgResponseTime: '45 giây',
    newCustomers: '+38',
    revenue: '14.850.000 đ',
    conversionRate: '32.4%'
  },
  week: {
    messagesCount: '9.820',
    responseRate: '98.5%',
    avgResponseTime: '52 giây',
    newCustomers: '+215',
    revenue: '89.400.000 đ',
    conversionRate: '35.1%'
  },
  month: {
    messagesCount: '41.200',
    responseRate: '98.0%',
    avgResponseTime: '1 phút',
    newCustomers: '+890',
    revenue: '342.000.000 đ',
    conversionRate: '33.8%'
  }
}

const currentStat = () => stats[timePeriod.value]

const topChannels = [
  { name: 'BÁO CÁO ADS | AETRADING', leads: '412 khách', value: '38%', color: '#ff6259' },
  { name: 'Cộng Đồng Black MMO', leads: '289 khách', value: '26%', color: '#38bdf8' },
  { name: 'Wells Gold & Forex', leads: '185 khách', value: '18%', color: '#1e3a8a' },
  { name: 'DungMediaShop (ChatGPT)', leads: '124 khách', value: '12%', color: '#a855f7' },
  { name: 'Trực tiếp Số Điện Thoại', leads: '70 khách', value: '6%', color: '#22c55e' }
]
</script>

<template>
  <div class="analytics-page">
    <!-- Header -->
    <div class="analytics-sticky-top">
      <header class="analytics-header">
        <div>
          <h1 class="page-title">Thống kê</h1>
          <span class="sub-title">Hiệu suất chat & kinh doanh</span>
        </div>

        <!-- Period Segment -->
        <div class="period-toggle">
          <button
            :class="['period-btn', { active: timePeriod === 'today' }]"
            @click="timePeriod = 'today'"
          >
            Hôm nay
          </button>
          <button
            :class="['period-btn', { active: timePeriod === 'week' }]"
            @click="timePeriod = 'week'"
          >
            Tuần này
          </button>
          <button
            :class="['period-btn', { active: timePeriod === 'month' }]"
            @click="timePeriod = 'month'"
          >
            Tháng này
          </button>
        </div>
      </header>
    </div>

    <main class="analytics-scroll">
      <!-- 4 Thẻ KPI chính -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-icon-row">
            <span class="kpi-title">Doanh thu chốt đơn</span>
            <div class="kpi-badge revenue-bg">
              <ion-icon :icon="walletOutline"></ion-icon>
            </div>
          </div>
          <span class="kpi-number revenue-color">{{ currentStat().revenue }}</span>
          <span class="kpi-subtext">Tỷ lệ chốt: {{ currentStat().conversionRate }}</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-row">
            <span class="kpi-title">Tin nhắn đã xử lý</span>
            <div class="kpi-badge chat-bg">
              <ion-icon :icon="chatbubblesOutline"></ion-icon>
            </div>
          </div>
          <span class="kpi-number">{{ currentStat().messagesCount }}</span>
          <span class="kpi-subtext">Tốc độ rep: {{ currentStat().avgResponseTime }}</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-row">
            <span class="kpi-title">Khách hàng mới</span>
            <div class="kpi-badge customer-bg">
              <ion-icon :icon="peopleOutline"></ion-icon>
            </div>
          </div>
          <span class="kpi-number success-color">{{ currentStat().newCustomers }}</span>
          <span class="kpi-subtext">Tăng trưởng ổn định</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-row">
            <span class="kpi-title">Tỷ lệ phản hồi</span>
            <div class="kpi-badge rate-bg">
              <ion-icon :icon="flashOutline"></ion-icon>
            </div>
          </div>
          <span class="kpi-number purple-color">{{ currentStat().responseRate }}</span>
          <span class="kpi-subtext">AI Bot trực 24/7</span>
        </div>
      </div>

      <!-- Biểu đồ phân bổ kênh khách hàng -->
      <div class="chart-section-card">
        <div class="section-title-row">
          <h2 class="section-title">Top Kênh Sinh Đơn & Khách Hàng</h2>
          <ion-icon :icon="trendingUpOutline" class="trend-icon"></ion-icon>
        </div>

        <div class="channels-list">
          <div
            v-for="channel in topChannels"
            :key="channel.name"
            class="channel-item"
          >
            <div class="channel-info-row">
              <span class="channel-name">{{ channel.name }}</span>
              <span class="channel-leads">{{ channel.leads }}</span>
            </div>
            <div class="progress-bar-bg">
              <div
                class="progress-bar-fill"
                :style="{ width: channel.value, backgroundColor: channel.color }"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Khung tư vấn AI & Bot tự động -->
      <div class="bot-status-card">
        <div class="bot-header">
          <span class="bot-indicator"></span>
          <span class="bot-title">Hệ thống Trợ lý AI đang hoạt động</span>
        </div>
        <p class="bot-desc">
          Trợ lý AI Facebook Ads & Gemini API đang tự động hỗ trợ 8 kênh và chốt đơn tự động qua số điện thoại.
        </p>
      </div>
    </main>
  </div>
</template>

<style scoped>
.analytics-page {
  flex: 1;
  display: flex;
  flex-direction: column;
  background-color: #000000;
  overflow: hidden;
  height: 100%;
}

.analytics-sticky-top {
  background-color: #000000;
  border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
  flex-shrink: 0;
  padding-top: max(env(safe-area-inset-top, 0px), 44px);
}

.analytics-header {
  padding: 10px 16px 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  color: #ffffff;
  margin: 0;
  letter-spacing: -0.4px;
}

.sub-title {
  font-size: 12px;
  color: #8e8e93;
}

.period-toggle {
  display: flex;
  background: #1c1c1e;
  border-radius: 10px;
  padding: 3px;
}

.period-btn {
  flex: 1;
  background: transparent;
  border: none;
  color: #8e8e93;
  font-size: 13px;
  font-weight: 600;
  padding: 6px 0;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.period-btn.active {
  background: #2c2c2e;
  color: #ffffff;
}

/* Scroll Area */
.analytics-scroll {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
  padding: 14px 14px 140px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  overscroll-behavior-y: contain;
  -webkit-overflow-scrolling: touch;
}

/* KPI Grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.kpi-card {
  background: #121418;
  border: 0.5px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.kpi-icon-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kpi-title {
  font-size: 11.5px;
  color: #8e8e93;
  font-weight: 500;
}

.kpi-badge {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
}

.revenue-bg { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.chat-bg { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
.customer-bg { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.rate-bg { background: rgba(168, 85, 247, 0.15); color: #c084fc; }

.kpi-number {
  font-size: 18px;
  font-weight: 800;
  color: #ffffff;
  letter-spacing: -0.3px;
  margin-top: 4px;
}

.revenue-color { color: #10b981; }
.success-color { color: #f59e0b; }
.purple-color { color: #c084fc; }

.kpi-subtext {
  font-size: 10.5px;
  color: #71717a;
}

/* Channel section */
.chart-section-card {
  background: #121418;
  border: 0.5px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-title {
  font-size: 14px;
  font-weight: 700;
  color: #ffffff;
  margin: 0;
}

.trend-icon {
  font-size: 18px;
  color: #10b981;
}

.channels-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.channel-info-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  margin-bottom: 4px;
}

.channel-name {
  color: #e4e4e7;
  font-weight: 500;
}

.channel-leads {
  color: #8e8e93;
}

.progress-bar-bg {
  width: 100%;
  height: 6px;
  background: #27272a;
  border-radius: 3px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.3s ease;
}

/* Bot Status */
.bot-status-card {
  background: rgba(30, 41, 59, 0.5);
  border: 0.5px solid rgba(56, 189, 248, 0.2);
  border-radius: 14px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.bot-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.bot-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #22c55e;
  box-shadow: 0 0 8px #22c55e;
}

.bot-title {
  font-size: 13px;
  font-weight: 700;
  color: #ffffff;
}

.bot-desc {
  font-size: 11.5px;
  color: #94a3b8;
  margin: 0;
  line-height: 1.4;
}
</style>
