<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as analyticsIndex } from '@/routes/app/post-analytics';
import { Head, Link } from '@inertiajs/vue3';
import {
    IconArrowLeft,
    IconChartLine,
    IconExternalLink,
} from '@tabler/icons-vue';

interface Props {
    post: {
        content: string;
        media_preview: string | null;
        published_at: string | null;
        views: number;
        interactions: number;
        likes: number;
        comments: number;
        shares: number;
    };
    platforms: {
        id: string;
        platform: string;
        account: string;
        url: string | null;
        metrics: { label: string; value: number }[];
        growth: { date: string; value: number }[];
    }[];
}
defineProps<Props>();
</script>
<template>
    <Head title="Chi tiết phân tích bài viết" />
    <AppLayout
        ><div
            class="mx-auto flex h-full w-full max-w-5xl flex-col gap-6 px-6 py-8"
        >
            <Link
                :href="analyticsIndex.url()"
                class="flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                ><IconArrowLeft class="size-4" /> Quay lại phân tích bài
                viết</Link
            ><PageHeader title="Chi tiết bài viết" />
            <div class="rounded-xl border bg-card p-5">
                <img
                    v-if="post.media_preview"
                    :src="post.media_preview"
                    alt="Hình ảnh bài viết"
                    class="mb-5 max-h-[28rem] w-full rounded-lg border bg-muted/20 object-contain"
                />
                <div
                    v-else
                    class="mb-5 flex h-40 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground"
                >
                    Bài viết không có hình ảnh
                </div>
                <p class="text-base leading-7 whitespace-pre-wrap">
                    {{ post.content || 'Bài viết không có nội dung văn bản.' }}
                </p>
                <p class="mt-3 text-sm text-muted-foreground">
                    Đã đăng:
                    {{
                        post.published_at
                            ? new Date(post.published_at).toLocaleString(
                                  'vi-VN',
                              )
                            : '—'
                    }}
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                <div
                    v-for="item in [
                        { label: 'Lượt xem', value: post.views },
                        { label: 'Tương tác', value: post.interactions },
                        { label: 'Lượt thích', value: post.likes },
                        { label: 'Bình luận', value: post.comments },
                        { label: 'Chia sẻ', value: post.shares },
                    ]"
                    :key="item.label"
                    class="rounded-xl border bg-card p-4"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ item.label }}
                    </p>
                    <p class="mt-1 text-xl font-bold">
                        {{ item.value.toLocaleString('vi-VN') }}
                    </p>
                </div>
            </div>
            <div class="space-y-4">
                <h2 class="flex items-center gap-2 font-semibold">
                    <IconChartLine class="size-5" /> Hiệu quả theo nền tảng
                </h2>
                <div
                    v-for="platform in platforms"
                    :key="platform.id"
                    class="rounded-xl border bg-card p-5"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold">{{ platform.platform }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ platform.account }}
                            </p>
                        </div>
                        <a
                            v-if="platform.url"
                            :href="platform.url"
                            target="_blank"
                            class="text-muted-foreground hover:text-foreground"
                            ><IconExternalLink class="size-4"
                        /></a>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-4">
                        <div
                            v-for="metric in platform.metrics"
                            :key="metric.label"
                            class="rounded-lg bg-muted/50 p-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ metric.label }}
                            </p>
                            <p class="font-semibold">
                                {{ metric.value.toLocaleString('vi-VN') }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="platform.growth.length < 2"
                        class="mt-4 text-xs text-muted-foreground"
                    >
                        Chưa có đủ dữ liệu để hiển thị xu hướng tăng trưởng.
                    </p>
                </div>
            </div>
        </div></AppLayout
    >
</template>
