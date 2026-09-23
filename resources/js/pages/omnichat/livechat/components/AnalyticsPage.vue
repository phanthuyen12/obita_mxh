<script setup lang="ts">
import { IonIcon } from '@ionic/vue';
import axios from 'axios';
import {
    chatbubblesOutline,
    flashOutline,
    peopleOutline,
    trendingUpOutline,
} from 'ionicons/icons';
import { onMounted, ref, watch } from 'vue';

type LiveChatStats = {
    messages: number;
    conversations: number;
    new_customers: number;
    avg_response_display: string;
    ai_handled_rate: number;
    ai_enabled: boolean;
    top_channels: Array<{
        name: string;
        platform: string;
        conversations: number;
        messages: number;
        value: number;
        color: string;
    }>;
};

const timePeriod = ref<'today' | 'week' | 'month'>('today');
const stats = ref<LiveChatStats | null>(null);
const isLoading = ref(false);
const hasError = ref(false);

const loadStats = async (): Promise<void> => {
    isLoading.value = true;
    hasError.value = false;
    try {
        const { data } = await axios.get('/omnichat/livechat/analytics', {
            params: { period: timePeriod.value },
        });
        stats.value = data as LiveChatStats;
    } catch {
        hasError.value = true;
    } finally {
        isLoading.value = false;
    }
};

watch(timePeriod, loadStats);
onMounted(loadStats);

const formatNumber = (value: number): string =>
    new Intl.NumberFormat('vi-VN').format(value);

const topChannels = () => stats.value?.top_channels ?? [];
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
                        :class="[
                            'period-btn',
                            { active: timePeriod === 'today' },
                        ]"
                        @click="timePeriod = 'today'"
                    >
                        Hôm nay
                    </button>
                    <button
                        :class="[
                            'period-btn',
                            { active: timePeriod === 'week' },
                        ]"
                        @click="timePeriod = 'week'"
                    >
                        Tuần này
                    </button>
                    <button
                        :class="[
                            'period-btn',
                            { active: timePeriod === 'month' },
                        ]"
                        @click="timePeriod = 'month'"
                    >
                        Tháng này
                    </button>
                </div>
            </header>
        </div>

        <main class="analytics-scroll">
            <!-- Loading -->
            <div v-if="isLoading && !stats" class="empty-state">
                Đang tải số liệu...
            </div>
            <div v-else-if="hasError && !stats" class="empty-state">
                Không tải được số liệu. Vuốt xuống để thử lại.
            </div>

            <template v-else-if="stats">
                <!-- 4 Thẻ KPI chính -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon-row">
                            <span class="kpi-title">Hội thoại</span>
                            <div class="kpi-badge chat-bg">
                                <ion-icon :icon="chatbubblesOutline"></ion-icon>
                            </div>
                        </div>
                        <span class="kpi-number">{{
                            formatNumber(stats.conversations)
                        }}</span>
                        <span class="kpi-subtext"
                            >{{ formatNumber(stats.messages) }} tin nhắn</span
                        >
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-row">
                            <span class="kpi-title">Khách hàng mới</span>
                            <div class="kpi-badge customer-bg">
                                <ion-icon :icon="peopleOutline"></ion-icon>
                            </div>
                        </div>
                        <span class="kpi-number success-color">{{
                            formatNumber(stats.new_customers)
                        }}</span>
                        <span class="kpi-subtext">Trong kỳ thống kê</span>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-row">
                            <span class="kpi-title">Tốc độ phản hồi</span>
                            <div class="kpi-badge rate-bg">
                                <ion-icon :icon="flashOutline"></ion-icon>
                            </div>
                        </div>
                        <span class="kpi-number">{{
                            stats.avg_response_display || '—'
                        }}</span>
                        <span class="kpi-subtext"
                            >Trung bình mỗi hội thoại</span
                        >
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-icon-row">
                            <span class="kpi-title">Tỷ lệ AI xử lý</span>
                            <div class="kpi-badge ai-bg">
                                <ion-icon :icon="flashOutline"></ion-icon>
                            </div>
                        </div>
                        <span class="kpi-number purple-color"
                            >{{ stats.ai_handled_rate }}%</span
                        >
                        <span class="kpi-subtext">{{
                            stats.ai_enabled
                                ? 'AI Bot đang hoạt động'
                                : 'AI Bot đang tắt'
                        }}</span>
                    </div>
                </div>

                <!-- Biểu đồ phân bổ kênh khách hàng -->
                <div class="chart-section-card">
                    <div class="section-title-row">
                        <h2 class="section-title">
                            Top Kênh Sinh Đơn & Khách Hàng
                        </h2>
                        <ion-icon
                            :icon="trendingUpOutline"
                            class="trend-icon"
                        ></ion-icon>
                    </div>

                    <div v-if="topChannels().length > 0" class="channels-list">
                        <div
                            v-for="channel in topChannels()"
                            :key="channel.name"
                            class="channel-item"
                        >
                            <div class="channel-info-row">
                                <span class="channel-name">{{
                                    channel.name
                                }}</span>
                                <span class="channel-leads"
                                    >{{ formatNumber(channel.messages) }} tin
                                    nhắn</span
                                >
                            </div>
                            <div class="progress-bar-bg">
                                <div
                                    class="progress-bar-fill"
                                    :style="{
                                        width: `${channel.value}%`,
                                        backgroundColor: channel.color,
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="empty-state">
                        Chưa có dữ liệu kênh trong kỳ này
                    </div>
                </div>

                <!-- Khung tư vấn AI & Bot tự động -->
                <div class="bot-status-card">
                    <div class="bot-header">
                        <span
                            :class="[
                                'bot-indicator',
                                { offline: !stats.ai_enabled },
                            ]"
                        ></span>
                        <span class="bot-title">
                            {{
                                stats.ai_enabled
                                    ? 'Trợ lý AI đang hoạt động'
                                    : 'Trợ lý AI đang tắt'
                            }}
                        </span>
                    </div>
                    <p class="bot-desc">
                        {{
                            stats.ai_enabled
                                ? `Trợ lý AI đang tự động hỗ trợ các kênh kết nối (xử lý ${stats.ai_handled_rate}% tin nhắn gửi đi).`
                                : 'Bật "Tự động trả lời qua AI" trong tab Profile để bot hỗ trợ khách hàng tự động.'
                        }}
                    </p>
                </div>
            </template>
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

.empty-state {
    text-align: center;
    color: #8e8e93;
    padding: 40px 10px;
    font-size: 14px;
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

.chat-bg {
    background: rgba(56, 189, 248, 0.15);
    color: #38bdf8;
}
.customer-bg {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}
.rate-bg {
    background: rgba(22, 163, 74, 0.15);
    color: #22c55e;
}
.ai-bg {
    background: rgba(168, 85, 247, 0.15);
    color: #c084fc;
}

.kpi-number {
    font-size: 18px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.3px;
    margin-top: 4px;
}

.success-color {
    color: #f59e0b;
}
.purple-color {
    color: #c084fc;
}

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

.bot-indicator.offline {
    background-color: #71717a;
    box-shadow: none;
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
