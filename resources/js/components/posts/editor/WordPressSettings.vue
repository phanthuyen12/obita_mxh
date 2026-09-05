<script setup lang="ts">
import {
    IconChevronDown,
    IconChevronUp,
    IconFileText,
    IconFolder,
    IconLink,
    IconSparkles,
    IconTag,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { getPlatformLogo } from '@/composables/usePlatformLogo';
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
    socialAccount: SocialAccount | null;
    contentType?: string;
    media?: MediaItem[];
    meta?: Record<string, any>;
    disabled?: boolean;
    previewOnly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    previewOnly: false,
    meta: () => ({}),
    media: () => [],
});

const emit = defineEmits<{
    'update:meta': [meta: Record<string, any>];
}>();

const open = ref(true);

const statusOptions = [
    {
        value: 'publish',
        label: 'Xuất bản (Publish)',
        desc: 'Đăng công khai lên website',
    },
    {
        value: 'draft',
        label: 'Bản nháp (Draft)',
        desc: 'Lưu nháp trên WordPress',
    },
    {
        value: 'pending',
        label: 'Chờ duyệt (Pending)',
        desc: 'Chờ admin website duyệt',
    },
    {
        value: 'private',
        label: 'Riêng tư (Private)',
        desc: 'Chỉ admin xem được',
    },
];

const updateField = (key: string, value: any) => {
    if (props.disabled) return;
    emit('update:meta', {
        ...props.meta,
        [key]: value,
    });
};

const currentStatus = computed(() => props.meta?.status ?? 'publish');
const currentTitle = computed(() => props.meta?.title ?? '');
const currentSlug = computed(() => props.meta?.slug ?? '');
const currentExcerpt = computed(() => props.meta?.excerpt ?? '');
const currentCategories = computed(() => props.meta?.categories ?? '');
const currentTags = computed(() => props.meta?.tags ?? '');
const allowComments = computed(() => props.meta?.comment_status !== 'closed');
</script>

<template>
    <div class="rounded-xl border-2 border-foreground bg-card shadow-2xs">
        <button
            type="button"
            class="flex w-full cursor-pointer items-center justify-between gap-3 p-4 text-sm"
            @click="open = !open"
        >
            <span class="flex min-w-0 items-center gap-2">
                <span
                    class="inline-flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-cyan-100 p-0.5 shadow-2xs"
                >
                    <img
                        :src="getPlatformLogo('wordpress')"
                        alt="WordPress"
                        class="size-full object-contain"
                    />
                </span>
                <span class="truncate font-bold text-foreground"
                    >Cấu hình bài viết WordPress</span
                >
                <span
                    v-if="socialAccount?.display_name"
                    class="truncate font-medium text-foreground/60"
                    >·&nbsp;{{ socialAccount.display_name }}</span
                >
            </span>
            <IconChevronUp
                v-if="open"
                class="size-4 shrink-0 text-foreground/60"
            />
            <IconChevronDown
                v-else
                class="size-4 shrink-0 text-foreground/60"
            />
        </button>

        <div
            v-show="open"
            class="space-y-4 border-t-2 border-foreground/10 p-4"
        >
            <!-- 1. Trạng thái xuất bản trên WordPress -->
            <div class="space-y-2">
                <Label
                    class="flex items-center gap-1.5 text-xs font-semibold text-foreground/80"
                >
                    <IconFileText class="size-3.5 text-muted-foreground" />
                    Trạng thái trên WordPress
                </Label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <button
                        v-for="opt in statusOptions"
                        :key="opt.value"
                        type="button"
                        class="flex flex-col items-start rounded-lg border p-2.5 text-left transition-all"
                        :class="
                            currentStatus === opt.value
                                ? 'border-[#21759B] bg-cyan-50/50 font-semibold text-[#21759B] dark:border-cyan-500 dark:bg-cyan-950/40'
                                : 'border-border text-foreground/70 hover:bg-muted/50'
                        "
                        :disabled="disabled"
                        @click="updateField('status', opt.value)"
                    >
                        <span class="text-xs font-medium">{{ opt.label }}</span>
                        <span
                            class="mt-0.5 line-clamp-1 text-[10px] text-muted-foreground"
                            >{{ opt.desc }}</span
                        >
                    </button>
                </div>
            </div>

            <!-- 2. Tiêu đề bài viết (Post Title) -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <Label
                        for="wp-title"
                        class="flex items-center gap-1.5 text-xs font-semibold text-foreground/80"
                    >
                        <IconSparkles class="size-3.5 text-amber-500" />
                        Tiêu đề bài viết (WordPress Title)
                    </Label>
                    <span class="text-[11px] text-muted-foreground"
                        >Tùy chọn (Tự động lấy dòng đầu nếu để trống)</span
                    >
                </div>
                <Input
                    id="wp-title"
                    :model-value="currentTitle"
                    placeholder="Nhập tiêu đề bài viết chuẩn SEO..."
                    class="h-9 text-sm"
                    :disabled="disabled"
                    @update:model-value="updateField('title', $event)"
                />
            </div>

            <!-- 3. Đường dẫn tĩnh (Slug / Permalink) -->
            <div class="space-y-1.5">
                <Label
                    for="wp-slug"
                    class="flex items-center gap-1.5 text-xs font-semibold text-foreground/80"
                >
                    <IconLink class="size-3.5 text-muted-foreground" />
                    Đường dẫn tĩnh (Slug URL)
                </Label>
                <Input
                    id="wp-slug"
                    :model-value="currentSlug"
                    placeholder="vd: tin-tuc-khuyen-mai-king-coffee-2026"
                    class="h-9 font-mono text-sm text-xs"
                    :disabled="disabled"
                    @update:model-value="updateField('slug', $event)"
                />
            </div>

            <!-- 4. Danh mục (Categories) & Thẻ (Tags) -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <Label
                        for="wp-cats"
                        class="flex items-center gap-1.5 text-xs font-semibold text-foreground/80"
                    >
                        <IconFolder class="size-3.5 text-muted-foreground" />
                        Danh mục (Categories)
                    </Label>
                    <Input
                        id="wp-cats"
                        :model-value="currentCategories"
                        placeholder="VD: Tin tức, Nông nghiệp, Khuyến mãi..."
                        class="h-9 text-sm"
                        :disabled="disabled"
                        @update:model-value="updateField('categories', $event)"
                    />
                    <p class="text-[10px] text-muted-foreground">
                        Nhập tên hoặc ID danh mục (cách nhau bằng dấu phẩy)
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label
                        for="wp-tags"
                        class="flex items-center gap-1.5 text-xs font-semibold text-foreground/80"
                    >
                        <IconTag class="size-3.5 text-muted-foreground" />
                        Thẻ bài viết (Tags)
                    </Label>
                    <Input
                        id="wp-tags"
                        :model-value="currentTags"
                        placeholder="VD: king coffee, cà phê ngon, ưu đãi..."
                        class="h-9 text-sm"
                        :disabled="disabled"
                        @update:model-value="updateField('tags', $event)"
                    />
                    <p class="text-[10px] text-muted-foreground">
                        Nhập các từ khóa tag cách nhau bằng dấu phẩy
                    </p>
                </div>
            </div>

            <!-- 5. Trích dẫn ngắn (Excerpt) -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <Label
                        for="wp-excerpt"
                        class="text-xs font-semibold text-foreground/80"
                    >
                        Đoạn trích tóm tắt (Excerpt)
                    </Label>
                    <span class="text-[11px] text-muted-foreground"
                        >Hiển thị ở trang chủ & Meta Description</span
                    >
                </div>
                <Textarea
                    id="wp-excerpt"
                    :model-value="currentExcerpt"
                    placeholder="Viết đoạn trích dẫn tóm tắt ngắn cho bài viết trên website..."
                    rows="2"
                    class="resize-none text-xs"
                    :disabled="disabled"
                    @update:model-value="updateField('excerpt', $event)"
                />
            </div>

            <!-- 6. Thảo luận / Bình luận -->
            <div class="flex items-center justify-between pt-1">
                <label
                    class="flex cursor-pointer items-center gap-2 text-xs font-medium text-foreground/80"
                >
                    <input
                        type="checkbox"
                        :checked="allowComments"
                        class="rounded border-input text-[#21759B] focus:ring-[#21759B]"
                        :disabled="disabled"
                        @change="
                            updateField(
                                'comment_status',
                                ($event.target as HTMLInputElement).checked
                                    ? 'open'
                                    : 'closed',
                            )
                        "
                    />
                    <span>Cho phép độc giả bình luận (Allow Comments)</span>
                </label>
            </div>
        </div>
    </div>
</template>
