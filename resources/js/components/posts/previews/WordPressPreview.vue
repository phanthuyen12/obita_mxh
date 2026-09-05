<script setup lang="ts">
import {
    IconCalendar,
    IconClock,
    IconEye,
    IconMessageCircle,
    IconTag,
    IconWorld,
} from '@tabler/icons-vue';
import { computed } from 'vue';

import { Badge } from '@/components/ui/badge';
import { getInitials } from '@/composables/useInitials';
import type { MediaItem } from '@/types/media';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    display_label: string;
    avatar_url: string | null;
}

interface Props {
    socialAccount: SocialAccount;
    content: string;
    media: MediaItem[];
    contentType?: string;
    meta?: Record<string, any>;
    charCount?: number;
    maxLength?: number;
    isValid?: boolean;
    validationMessage?: string;
    postedAt?: string | null;
}

const props = defineProps<Props>();

// Derive title: from meta.title or first non-empty line of content
const displayTitle = computed(() => {
    if (props.meta?.title?.trim()) {
        return props.meta.title.trim();
    }
    const firstLine = props.content
        .split('\n')
        .map((l) => l.trim())
        .find((l) => l.length > 0);
    return firstLine || 'Tiêu đề bài viết WordPress';
});

// Derive body: content without first line if title was derived from it
const displayBody = computed(() => {
    if (props.meta?.title?.trim()) {
        return props.content;
    }
    const lines = props.content.split('\n');
    if (lines.length <= 1) return '';
    return lines.slice(1).join('\n').trim();
});

const displayExcerpt = computed(() => props.meta?.excerpt?.trim() || '');

const displayCategories = computed(() => {
    if (!props.meta?.categories) return ['Chưa phân loại'];
    if (Array.isArray(props.meta.categories)) {
        return props.meta.categories.length
            ? props.meta.categories
            : ['Chưa phân loại'];
    }
    return String(props.meta.categories)
        .split(',')
        .map((c) => c.trim())
        .filter(Boolean);
});

const displayTags = computed(() => {
    if (!props.meta?.tags) return [];
    if (Array.isArray(props.meta.tags)) return props.meta.tags;
    return String(props.meta.tags)
        .split(',')
        .map((t) => t.trim())
        .filter(Boolean);
});

const readingTime = computed(() => {
    const wordCount = (props.content || '').trim().split(/\s+/).length;
    const minutes = Math.max(1, Math.ceil(wordCount / 200));
    return `${minutes} phút đọc`;
});

const featuredMedia = computed(() => props.media?.[0] || null);
const secondaryMedia = computed(() => props.media?.slice(1) || []);
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-border bg-card text-foreground shadow-sm"
    >
        <!-- 1. Browser mockup address bar -->
        <div
            class="flex items-center gap-2 border-b border-border/80 bg-muted/40 px-3.5 py-2 text-xs text-muted-foreground"
        >
            <div class="flex items-center gap-1.5">
                <div class="size-2.5 rounded-full bg-red-400"></div>
                <div class="size-2.5 rounded-full bg-amber-400"></div>
                <div class="size-2.5 rounded-full bg-emerald-400"></div>
            </div>
            <div class="flex min-w-0 flex-1 items-center justify-center">
                <div
                    class="flex max-w-sm items-center gap-1.5 truncate rounded-md bg-background px-2.5 py-0.5 font-mono text-[11px] shadow-2xs"
                >
                    <IconWorld class="size-3 shrink-0 text-[#21759B]" />
                    <span class="truncate"
                        >{{
                            socialAccount.username ||
                            'https://your-wordpress-site.com'
                        }}/{{ meta?.slug || 'bai-viet-moi' }}</span
                    >
                </div>
            </div>
            <div class="w-8"></div>
        </div>

        <!-- 2. Blog Post Article Container -->
        <article class="space-y-4 p-5 sm:p-6">
            <!-- Categories -->
            <div class="flex flex-wrap items-center gap-1.5">
                <Badge
                    v-for="cat in displayCategories"
                    :key="cat"
                    class="rounded-md bg-[#21759B]/10 px-2.5 py-0.5 text-xs font-semibold text-[#21759B] hover:bg-[#21759B]/20 dark:bg-[#21759B]/20 dark:text-cyan-300"
                >
                    {{ cat }}
                </Badge>
                <Badge
                    v-if="meta?.status && meta?.status !== 'publish'"
                    variant="outline"
                    class="text-[10px] uppercase"
                >
                    {{ meta.status }}
                </Badge>
            </div>

            <!-- Title -->
            <h1
                class="text-xl leading-snug font-bold tracking-tight text-foreground sm:text-2xl"
            >
                {{ displayTitle }}
            </h1>

            <!-- Meta: Author, Date, Reading Time -->
            <div
                class="flex flex-wrap items-center gap-4 border-b border-border/60 pb-3.5 text-xs text-muted-foreground"
            >
                <div class="flex items-center gap-2">
                    <div
                        class="flex size-6 shrink-0 items-center justify-center rounded-full bg-[#21759B] text-[10px] font-bold text-white uppercase"
                    >
                        {{
                            getInitials(
                                socialAccount.display_name ||
                                    socialAccount.username ||
                                    'Admin',
                            )
                        }}
                    </div>
                    <span class="font-medium text-foreground">{{
                        socialAccount.display_name ||
                        socialAccount.username ||
                        'Tác giả'
                    }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <IconCalendar class="size-3.5" />
                    <span>Hôm nay</span>
                </div>
                <div class="flex items-center gap-1">
                    <IconClock class="size-3.5" />
                    <span>{{ readingTime }}</span>
                </div>
            </div>

            <!-- Featured Image -->
            <div
                v-if="featuredMedia"
                class="overflow-hidden rounded-xl border border-border shadow-xs"
            >
                <img
                    v-if="featuredMedia.url && !featuredMedia.is_video"
                    :src="featuredMedia.url"
                    alt="Featured Image"
                    class="max-h-[380px] w-full object-cover"
                />
                <video
                    v-else-if="featuredMedia.url && featuredMedia.is_video"
                    :src="featuredMedia.url"
                    controls
                    class="max-h-[380px] w-full bg-black"
                />
            </div>

            <!-- Excerpt Blockquote -->
            <div
                v-if="displayExcerpt"
                class="rounded-lg border-l-4 border-[#21759B] bg-muted/40 p-3 text-xs text-foreground/80 italic"
            >
                {{ displayExcerpt }}
            </div>

            <!-- Body Content -->
            <div
                class="prose prose-sm max-w-none text-xs leading-relaxed whitespace-pre-line text-foreground/90 sm:text-sm dark:prose-invert"
            >
                {{ displayBody || displayTitle }}
            </div>

            <!-- Secondary Attached Images -->
            <div
                v-if="secondaryMedia.length"
                class="grid grid-cols-2 gap-2 pt-2"
            >
                <div
                    v-for="item in secondaryMedia"
                    :key="item.id || item.url"
                    class="overflow-hidden rounded-lg border border-border"
                >
                    <img
                        :src="item.url"
                        class="h-36 w-full object-cover"
                        alt="Media"
                    />
                </div>
            </div>

            <!-- Tags -->
            <div
                v-if="displayTags.length"
                class="flex flex-wrap items-center gap-1.5 border-t border-border/60 pt-3"
            >
                <span
                    class="flex items-center gap-1 text-xs font-medium text-muted-foreground"
                >
                    <IconTag class="size-3" /> Thẻ:
                </span>
                <span
                    v-for="tag in displayTags"
                    :key="tag"
                    class="rounded-md bg-muted px-2 py-0.5 text-[11px] text-muted-foreground hover:text-foreground"
                >
                    #{{ tag }}
                </span>
            </div>

            <!-- Comments Preview Footer -->
            <div
                class="flex items-center justify-between border-t border-border/60 pt-3 text-xs text-muted-foreground"
            >
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1">
                        <IconMessageCircle class="size-3.5 text-[#21759B]" />
                        0 bình luận
                    </span>
                    <span class="flex items-center gap-1">
                        <IconEye class="size-3.5" />
                        1 lượt xem
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-medium text-[#21759B]"
                        >Đăng bởi King Hub WordPress Connector</span
                    >
                </div>
            </div>
        </article>
    </div>
</template>
