<script setup lang="ts">
import { VisArea, VisAxis, VisLine, VisXYContainer } from '@unovis/vue';
import { computed } from 'vue';

interface TrendPoint {
    date: string;
    display_date: string;
    messages: number;
    conversations: number;
    inbound: number;
    outbound: number;
    ai_replies?: number;
    human_replies?: number;
}

const props = defineProps<{
    trends: TrendPoint[];
}>();

const x = (_d: TrendPoint, i: number) => i;
const yConversations = (d: TrendPoint) => d.conversations || d.messages;
const yInbound = (d: TrendPoint) => d.inbound;
const yOutbound = (d: TrendPoint) => d.outbound;

const xTickFormat = (i: number) => {
    return props.trends[i]?.display_date || props.trends[i]?.date || '';
};

const totalConv = computed(() =>
    props.trends.reduce(
        (acc, curr) => acc + (curr.conversations || curr.messages),
        0,
    ),
);
const totalInbound = computed(() =>
    props.trends.reduce((acc, curr) => acc + curr.inbound, 0),
);
const totalOutbound = computed(() =>
    props.trends.reduce((acc, curr) => acc + curr.outbound, 0),
);
</script>

<template>
    <div
        class="space-y-4 rounded-2xl border-2 border-foreground bg-card p-5 shadow-2xs"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-foreground/10 pb-3"
        >
            <div>
                <h3 class="text-base font-bold text-foreground">
                    Xu hướng tin nhắn & Lưu lượng theo thời gian
                </h3>
                <p class="text-xs text-muted-foreground">
                    Biểu đồ thể hiện khối lượng cuộc trò chuyện, tin nhắn khách
                    gửi (inbound) và phản hồi từ AI/Admin (outbound).
                </p>
            </div>

            <!-- Legend summary -->
            <div
                class="flex flex-wrap items-center gap-4 text-xs font-semibold"
            >
                <div class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-primary"></span>
                    <span class="text-foreground"
                        >Hội thoại ({{ totalConv }})</span
                    >
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-indigo-500"></span>
                    <span class="text-indigo-700 dark:text-indigo-400"
                        >Khách gửi ({{ totalInbound }})</span
                    >
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-emerald-700 dark:text-emerald-400"
                        >Phản hồi ({{ totalOutbound }})</span
                    >
                </div>
            </div>
        </div>

        <div v-if="trends.length > 1" class="h-64 w-full pt-2">
            <VisXYContainer :data="trends" class="h-full w-full">
                <VisArea
                    :x="x"
                    :y="yConversations"
                    color="var(--color-primary)"
                    :opacity="0.08"
                />
                <VisLine
                    :x="x"
                    :y="yConversations"
                    color="var(--color-primary)"
                    :stroke-width="2.5"
                />
                <VisLine
                    :x="x"
                    :y="yInbound"
                    color="#6366f1"
                    :stroke-width="2"
                />
                <VisLine
                    :x="x"
                    :y="yOutbound"
                    color="#10b981"
                    :stroke-width="2"
                />
                <VisAxis
                    type="x"
                    :tick-format="xTickFormat"
                    :grid-line="false"
                />
                <VisAxis type="y" :grid-line="true" />
            </VisXYContainer>
        </div>

        <div v-else class="py-10 text-center text-xs text-muted-foreground">
            Chưa có đủ dữ liệu theo thời gian để vẽ biểu đồ xu hướng.
        </div>
    </div>
</template>
