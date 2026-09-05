<script setup lang="ts">
import {
    IconAlertTriangle,
    IconAlt,
    IconCheck,
    IconFileTypePdf,
    IconGripVertical,
    IconHash,
    IconLibraryPhoto,
    IconMoodSmile,
    IconPlus,
    IconRefresh,
    IconSparkles,
    IconTrash,
    IconVideo,
    IconWriting,
    IconX,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, nextTick, ref, watch } from 'vue';

import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import AltTextDialog from '@/components/posts/editor/AltTextDialog.vue';
import WordPressClassicEditor from '@/components/posts/editor/WordPressClassicEditor.vue';
import EmojiPicker from '@/components/posts/EmojiPicker.vue';
import MediaPickerDialog from '@/components/posts/MediaPickerDialog.vue';
import SignaturesModal from '@/components/posts/SignaturesModal.vue';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
} from '@/components/ui/popover';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import date from '@/date';
import {
    classify,
    isDocument,
    isImage,
    isVideo,
    MediaType,
} from '@/lib/mediaType';
import type { MediaItem } from '@/types/media';

interface Signature {
    id: string;
    name: string;
    content: string;
}

interface PlatformLimit {
    platform: string;
    maxLength: number;
}

interface MediaIssue {
    platform: string;
    reason: string;
}

interface WorkspacePostTag {
    id: string;
    name: string;
    color?: string;
}

interface PostPlatformItem {
    id: string;
    social_account_id: string | null;
    enabled: boolean;
    platform: string;
    platform_name: string | null;
    platform_username: string | null;
    platform_avatar: string | null;
    content_type: string | null;
    status: string;
    social_account: {
        id: string;
        platform: string;
        platform_user_id?: string;
        display_name: string;
        username: string;
        display_label?: string;
        avatar_url: string | null;
        meta?: Record<string, any>;
    } | null;
    meta?: Record<string, any>;
}

const props = withDefaults(
    defineProps<{
        signatures: Signature[];
        platformLimits: PlatformLimit[];
        mediaIssues: Record<string, MediaIssue[]>;
        postTags?: WorkspacePostTag[];
        allowAiRegenerate?: boolean;
        readOnly?: boolean;
        postPlatforms?: PostPlatformItem[];
        selectedPlatformIds?: string[];
        platformMeta?: Record<string, Record<string, any>>;
        wordPressSites?: Array<{
            id: string;
            name: string;
            url: string;
            username: string;
            categories_cache?: any[];
            tags_cache?: any[];
        }>;
        canPublish?: boolean;
    }>(),
    {
        postTags: () => [],
        allowAiRegenerate: true,
        readOnly: false,
        postPlatforms: () => [],
        selectedPlatformIds: () => [],
        platformMeta: () => ({}),
        wordPressSites: () => [],
        canPublish: true,
    },
);

const content = defineModel<string>('content', { required: true });
const media = defineModel<MediaItem[]>('media', { required: true });
const isCeoContent = defineModel<boolean>('isCeoContent', { default: false });
const topicTags = defineModel<string[]>('topicTags', { default: () => [] });

const emit = defineEmits<{
    (e: 'open-ai-generate'): void;
    (e: 'open-ai-review'): void;
    (e: 'open-ai-regenerate-image', mediaId: string): void;
    (
        e: 'update:platformMeta',
        platformId: string,
        meta: Record<string, any>,
    ): void;
    (e: 'toggle-platform', platformId: string): void;
    (e: 'open-wordpress-connect'): void;
    (e: 'publish-now'): void;
    (e: 'save-draft'): void;
}>();

const showWpAdvanced = ref(false);
const isSyncingWp = ref(false);
const liveCategories = ref<
    Array<{ id: number; name: string; slug: string; count?: number }>
>([]);
const liveTags = ref<Array<{ id: number; name: string; slug: string }>>([]);

const availableWpPlatforms = computed(
    () =>
        props.postPlatforms?.filter((pp) => pp.platform === 'wordpress') ?? [],
);

const activeWordPressPlatforms = computed(
    () =>
        props.postPlatforms?.filter(
            (pp) =>
                pp.platform === 'wordpress' &&
                props.selectedPlatformIds?.includes(pp.id),
        ) ?? [],
);

const focusedWpPlatformId = ref<string | null>(
    activeWordPressPlatforms.value[0]?.id ??
        availableWpPlatforms.value[0]?.id ??
        null,
);

const primaryWpPlatform = computed(
    () =>
        availableWpPlatforms.value.find(
            (platform) => platform.id === focusedWpPlatformId.value,
        ) ??
        activeWordPressPlatforms.value[0] ??
        availableWpPlatforms.value[0],
);

const selectWordPressWebsite = (event: Event) => {
    const platformId = (event.target as HTMLSelectElement).value;
    if (!platformId) return;

    for (const platform of activeWordPressPlatforms.value) {
        if (platform.id !== platformId) {
            emit('toggle-platform', platform.id);
        }
    }

    if (!props.selectedPlatformIds?.includes(platformId)) {
        emit('toggle-platform', platformId);
    }

    focusedWpPlatformId.value = platformId;
    liveCategories.value = [];
    liveTags.value = [];
};

const normalizeWordPressUrl = (url?: string | null): string =>
    (url ?? '').replace(/\/+$/, '');

const wordPressSiteForPlatform = (platform: PostPlatformItem) => {
    const siteId = platform.social_account?.meta?.site_id;
    const platformUrls = [
        platform.social_account?.platform_user_id,
        platform.platform_username,
        platform.social_account?.username,
    ].map(normalizeWordPressUrl);

    return props.wordPressSites.find(
        (site) =>
            site.id === siteId ||
            platformUrls.includes(normalizeWordPressUrl(site.url)),
    );
};

const currentWpSite = computed(() => {
    if (props.wordPressSites?.length) {
        if (primaryWpPlatform.value) {
            const found = wordPressSiteForPlatform(primaryWpPlatform.value);
            if (found) return found;
        }
        return props.wordPressSites[0];
    }
    return null;
});

const availableWpCategories = computed(() => {
    if (liveCategories.value.length > 0) return liveCategories.value;
    if (currentWpSite.value?.categories_cache?.length)
        return currentWpSite.value.categories_cache;
    if (primaryWpPlatform.value?.social_account?.meta?.categories?.length) {
        return primaryWpPlatform.value.social_account.meta.categories;
    }
    return [];
});

const availableWpTags = computed(() => {
    if (liveTags.value.length > 0) return liveTags.value;
    if (currentWpSite.value?.tags_cache?.length)
        return currentWpSite.value.tags_cache;
    if (primaryWpPlatform.value?.social_account?.meta?.tags?.length) {
        return primaryWpPlatform.value.social_account.meta.tags;
    }
    return [];
});

const selectedCategoryList = computed(() => {
    if (!wpCategories.value) return [];
    return wpCategories.value
        .split(',')
        .map((s: string) => s.trim())
        .filter(Boolean);
});

const selectedTagList = computed(() => {
    if (!wpTags.value) return [];
    return wpTags.value
        .split(',')
        .map((s: string) => s.trim())
        .filter(Boolean);
});

const toggleCategory = (catName: string) => {
    const list = [...selectedCategoryList.value];
    const index = list.indexOf(catName);
    if (index >= 0) {
        list.splice(index, 1);
    } else {
        list.push(catName);
    }
    wpCategories.value = list.join(', ');
};

const toggleTag = (tagName: string) => {
    const list = [...selectedTagList.value];
    const index = list.indexOf(tagName);
    if (index >= 0) {
        list.splice(index, 1);
    } else {
        list.push(tagName);
    }
    wpTags.value = list.join(', ');
};

const generateSlugFromTitle = () => {
    if (!wpTitle.value) return;
    const str = wpTitle.value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[đĐ]/g, 'd')
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-');
    wpSlug.value = str;
};

const syncWpTaxonomies = async () => {
    if (!currentWpSite.value?.id) return;
    isSyncingWp.value = true;
    try {
        const response = await fetch(
            `/wordpress/sites/${currentWpSite.value.id}/sync`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
            },
        );
        if (response.ok) {
            const data = await response.json();
            if (data.categories) liveCategories.value = data.categories;
            if (data.tags) liveTags.value = data.tags;
        }
    } catch (e) {
        console.error('Failed to sync taxonomies', e);
    } finally {
        isSyncingWp.value = false;
    }
};

const wpTitle = computed({
    get: () => {
        if (!primaryWpPlatform.value) return '';
        return props.platformMeta?.[primaryWpPlatform.value.id]?.title ?? '';
    },
    set: (val: string) => {
        if (primaryWpPlatform.value) {
            emit('update:platformMeta', primaryWpPlatform.value.id, {
                ...(props.platformMeta?.[primaryWpPlatform.value.id] ?? {}),
                title: val,
            });
        }
    },
});

const wpStatus = computed({
    get: () => {
        if (!primaryWpPlatform.value) return 'publish';
        return (
            props.platformMeta?.[primaryWpPlatform.value.id]?.status ??
            'publish'
        );
    },
    set: (val: string) => {
        if (primaryWpPlatform.value) {
            emit('update:platformMeta', primaryWpPlatform.value.id, {
                ...(props.platformMeta?.[primaryWpPlatform.value.id] ?? {}),
                status: val,
            });
        }
    },
});

const wpSlug = computed({
    get: () => {
        if (!primaryWpPlatform.value) return '';
        return props.platformMeta?.[primaryWpPlatform.value.id]?.slug ?? '';
    },
    set: (val: string) => {
        if (primaryWpPlatform.value) {
            emit('update:platformMeta', primaryWpPlatform.value.id, {
                ...(props.platformMeta?.[primaryWpPlatform.value.id] ?? {}),
                slug: val,
            });
        }
    },
});

const wpCategories = computed({
    get: () => {
        if (!primaryWpPlatform.value) return '';
        return (
            props.platformMeta?.[primaryWpPlatform.value.id]?.categories ?? ''
        );
    },
    set: (val: string) => {
        if (primaryWpPlatform.value) {
            emit('update:platformMeta', primaryWpPlatform.value.id, {
                ...(props.platformMeta?.[primaryWpPlatform.value.id] ?? {}),
                categories: val,
            });
        }
    },
});

const wpTags = computed({
    get: () => {
        if (!primaryWpPlatform.value) return '';
        return props.platformMeta?.[primaryWpPlatform.value.id]?.tags ?? '';
    },
    set: (val: string) => {
        if (primaryWpPlatform.value) {
            emit('update:platformMeta', primaryWpPlatform.value.id, {
                ...(props.platformMeta?.[primaryWpPlatform.value.id] ?? {}),
                tags: val,
            });
        }
    },
});

const wpExcerpt = computed({
    get: () => {
        if (!primaryWpPlatform.value) return '';
        return props.platformMeta?.[primaryWpPlatform.value.id]?.excerpt ?? '';
    },
    set: (val: string) => {
        if (primaryWpPlatform.value) {
            emit('update:platformMeta', primaryWpPlatform.value.id, {
                ...(props.platformMeta?.[primaryWpPlatform.value.id] ?? {}),
                excerpt: val,
            });
        }
    },
});

const editorMode = defineModel<'social' | 'website'>('editorMode', {
    default: 'social',
});

watch(
    () => activeWordPressPlatforms.value.length,
    (count) => {
        if (
            count > 0 &&
            props.selectedPlatformIds &&
            props.selectedPlatformIds.length === count
        ) {
            editorMode.value = 'website';
        }
    },
    { immediate: true },
);

const setEditorMode = (mode: 'social' | 'website') => {
    editorMode.value = mode;
    if (mode === 'website') {
        if (
            availableWpPlatforms.value.length > 0 &&
            activeWordPressPlatforms.value.length === 0
        ) {
            emit('toggle-platform', availableWpPlatforms.value[0].id);
        }
    }
};

const handlePublishWp = () => {
    if (
        availableWpPlatforms.value.length > 0 &&
        activeWordPressPlatforms.value.length === 0
    ) {
        emit(
            'toggle-platform',
            primaryWpPlatform.value?.id ?? availableWpPlatforms.value[0].id,
        );
    }
    emit('publish-now');
};

const handleSaveDraftWp = () => {
    if (
        availableWpPlatforms.value.length > 0 &&
        activeWordPressPlatforms.value.length === 0
    ) {
        emit(
            'toggle-platform',
            primaryWpPlatform.value?.id ?? availableWpPlatforms.value[0].id,
        );
    }
    emit('save-draft');
};

const emojiOpen = ref(false);
const mediaPickerDialog = ref<InstanceType<typeof MediaPickerDialog> | null>(
    null,
);
const signaturesModal = ref<InstanceType<typeof SignaturesModal> | null>(null);

const dragMediaIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);
const mediaThumbRefs = ref<HTMLElement[]>([]);
const lightbox = ref<InstanceType<typeof ImagePreviewDialog> | null>(null);

const openPreview = (item: MediaItem) => {
    const idx = media.value.findIndex((m) => m.id === item.id);
    if (idx < 0) return;
    lightbox.value?.openCollection(
        media.value.map((m) => ({
            url: m.url,
            type: classify(m) ?? MediaType.Image,
            altText: isImage(m) ? m.meta?.alt_text : undefined,
        })),
        idx,
    );
};

const limitsWithUsage = computed(() =>
    props.platformLimits.map((p) => {
        const used = content.value.length;
        const ratio = p.maxLength > 0 ? used / p.maxLength : 0;
        const state = ratio > 1 ? 'over' : ratio >= 0.9 ? 'warn' : 'ok';
        return { ...p, used, state };
    }),
);

const limitClass = (state: string): string => {
    if (state === 'over') return 'border-foreground bg-rose-100 text-rose-700';
    if (state === 'warn')
        return 'border-foreground bg-amber-100 text-amber-800';
    return 'border-foreground bg-card text-foreground';
};

const smallestLimit = computed(() => {
    if (props.platformLimits.length === 0) return null;
    return Math.min(...props.platformLimits.map((p) => p.maxLength));
});

const overflowParts = computed(() => {
    const limit = smallestLimit.value;
    if (limit === null || content.value.length <= limit) {
        return { fits: content.value, overflow: '' };
    }
    return {
        fits: content.value.slice(0, limit),
        overflow: content.value.slice(limit),
    };
});

const removeMedia = (mediaId: string) => {
    media.value = media.value.filter((m) => m.id !== mediaId);
};

const addMediaFromGallery = (picked: MediaItem[]) => {
    const existingIds = new Set(media.value.map((m) => m.id));
    const additions = picked.filter((m) => !existingIds.has(m.id));
    if (additions.length === 0) return;
    media.value = [...media.value, ...additions];
};

const appendSignature = (signature: Signature) => {
    const separator = content.value.trim() ? '\n\n' : '';
    content.value += separator + signature.content;
};

const appendEmoji = (emoji: string) => {
    content.value += emoji;
    emojiOpen.value = false;
};

const moveMediaItem = (from: number, to: number) => {
    if (from === to || to < 0 || to >= media.value.length) return;
    const next = [...media.value];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    media.value = next;
};

const onMediaDragStart = (event: DragEvent, index: number) => {
    dragMediaIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
};

const onMediaDragOver = (event: DragEvent, index: number) => {
    if (dragMediaIndex.value === null) return;
    event.preventDefault();
    event.stopPropagation();
    dragOverIndex.value = index;
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
};

const onMediaDrop = (event: DragEvent, index: number) => {
    if (dragMediaIndex.value === null) return;
    event.preventDefault();
    event.stopPropagation();
    const from = dragMediaIndex.value;
    dragMediaIndex.value = null;
    dragOverIndex.value = null;
    moveMediaItem(from, index);
};

const onMediaDragEnd = () => {
    dragMediaIndex.value = null;
    dragOverIndex.value = null;
};

const GRID_COLS = 4;

const onMediaKeydown = async (event: KeyboardEvent, index: number) => {
    if (props.readOnly) return;
    if (media.value.length < 2) return;
    if (!event.altKey) return;

    const deltas: Record<string, number> = {
        ArrowLeft: -1,
        ArrowRight: 1,
        ArrowUp: -GRID_COLS,
        ArrowDown: GRID_COLS,
    };
    const delta = deltas[event.key];
    if (delta === undefined) return;

    event.preventDefault();
    const target = index + delta;
    if (target < 0 || target >= media.value.length) return;
    moveMediaItem(index, target);

    await nextTick();
    mediaThumbRefs.value[target]?.focus();
};

const issueLabel = (reason: string): string =>
    trans(`posts.form.warnings.${reason}`);
const canRegenerateWithAi = (item: MediaItem): boolean =>
    props.allowAiRegenerate && item.source === 'ai';

const altDialogOpen = ref(false);
const altDialogIndex = ref<number | null>(null);
const altDialogItem = computed<MediaItem | null>(() =>
    altDialogIndex.value !== null
        ? (media.value[altDialogIndex.value] ?? null)
        : null,
);

const openAltText = (index: number) => {
    altDialogIndex.value = index;
    altDialogOpen.value = true;
};

const onAltTextSave = (alt: string): void => {
    if (altDialogIndex.value === null) return;

    const next = [...media.value];
    const trimmed = alt.trim();
    next[altDialogIndex.value] = {
        ...next[altDialogIndex.value],
        meta: {
            ...(next[altDialogIndex.value].meta ?? {}),
            alt_text: trimmed || undefined,
        },
    };
    media.value = next;
};

// ----------------------------------------------------
// Custom Topic Tags Management
// ----------------------------------------------------
const DEFAULT_TOPIC_TAGS = [
    'Thương hiệu',
    'Sản phẩm',
    'Khuyến mãi',
    'Sự kiện',
    'Tầm nhìn CEO',
];

const loadCustomTags = (): string[] => {
    try {
        const saved = localStorage.getItem('post_custom_topic_tags');
        return saved ? JSON.parse(saved) : [];
    } catch {
        return [];
    }
};

const customTopicTags = ref<string[]>(loadCustomTags());
const newTagInput = ref('');
const showAddTagInput = ref(false);

const allAvailableTags = computed(() => {
    const list: string[] = [];

    // Add shared workspace post tags from the database.
    if (props.postTags && props.postTags.length > 0) {
        for (const t of props.postTags) {
            if (t.name && !list.includes(t.name)) {
                list.push(t.name);
            }
        }
    } else {
        for (const tag of DEFAULT_TOPIC_TAGS) {
            if (!list.includes(tag)) {
                list.push(tag);
            }
        }
    }

    // 2. Add local custom tags
    for (const tag of customTopicTags.value) {
        if (!list.includes(tag)) {
            list.push(tag);
        }
    }

    // 3. Add selected topic tags
    for (const tag of topicTags.value || []) {
        if (!list.includes(tag)) {
            list.push(tag);
        }
    }
    return list;
});

const isDbTopic = (tag: string): boolean => {
    return Boolean(props.postTags?.some((t) => t.name === tag));
};

const isDefaultTag = (tag: string): boolean =>
    DEFAULT_TOPIC_TAGS.includes(tag) || isDbTopic(tag);

const toggleTopicTag = (tag: string) => {
    const list = [...(topicTags.value || [])];
    const idx = list.indexOf(tag);
    if (idx >= 0) {
        list.splice(idx, 1);
        if (tag === 'CEO' && isCeoContent.value) {
            isCeoContent.value = false;
        }
    } else {
        list.push(tag);
        if (tag === 'CEO' && !isCeoContent.value) {
            isCeoContent.value = true;
        }
    }
    topicTags.value = list;
};

// Sync CEO toggle and CEO tag
watch(isCeoContent, (val) => {
    const list = [...(topicTags.value || [])];
    if (val) {
        if (!list.includes('CEO')) {
            topicTags.value = [...list, 'CEO'];
        }
    } else {
        if (list.includes('CEO')) {
            topicTags.value = list.filter((t) => t !== 'CEO');
        }
    }
});

const addCustomTag = () => {
    let tag = newTagInput.value.trim();
    if (!tag) return;
    if (tag.startsWith('#')) tag = tag.slice(1).trim();
    if (!tag) return;

    if (
        !customTopicTags.value.includes(tag) &&
        !DEFAULT_TOPIC_TAGS.includes(tag)
    ) {
        customTopicTags.value.push(tag);
        try {
            localStorage.setItem(
                'post_custom_topic_tags',
                JSON.stringify(customTopicTags.value),
            );
        } catch {}
    }

    if (!topicTags.value.includes(tag)) {
        topicTags.value = [...topicTags.value, tag];
    }

    newTagInput.value = '';
    showAddTagInput.value = false;
};

const removeCustomTag = (tag: string) => {
    customTopicTags.value = customTopicTags.value.filter((t) => t !== tag);
    try {
        localStorage.setItem(
            'post_custom_topic_tags',
            JSON.stringify(customTopicTags.value),
        );
    } catch {}
    if (topicTags.value.includes(tag)) {
        topicTags.value = topicTags.value.filter((t) => t !== tag);
    }
};
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <!-- Top Mode Switcher: 2 Distinct Post Types (Social vs Website CMS) -->
        <div
            class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-foreground bg-card p-3 shadow-2xs"
        >
            <div
                class="inline-flex rounded-xl border-2 border-foreground/20 bg-muted/40 p-1"
            >
                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold transition-all select-none"
                    :class="
                        editorMode === 'social'
                            ? 'border border-foreground/15 bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="setEditorMode('social')"
                >
                    <span
                        class="size-2 rounded-full"
                        :class="
                            editorMode === 'social'
                                ? 'bg-primary'
                                : 'bg-transparent'
                        "
                    ></span>
                    <span>📱 Mạng Xã Hội (Social Posts)</span>
                </button>
                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold transition-all select-none"
                    :class="
                        editorMode === 'website'
                            ? 'bg-[#21759B] text-white shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="setEditorMode('website')"
                >
                    <img
                        :src="getPlatformLogo('wordpress')"
                        class="size-3.5 object-contain"
                        alt="WP"
                    />
                    <span>🌐 Website / WordPress (Bài Viết CMS)</span>
                </button>
            </div>

            <div class="flex items-center gap-2">
                <span
                    v-if="editorMode === 'website'"
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-[#21759B]"
                >
                    <span
                        class="size-2 animate-pulse rounded-full bg-emerald-500"
                    ></span>
                    Giao diện bài viết Blog / CMS chuẩn SEO
                </span>
                <span v-else class="text-xs text-muted-foreground">
                    Giao diện caption & đa phương tiện MXH
                </span>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- 1. GIAO DIỆN BÀI VIẾT WEBSITE WORDPRESS (CMS) -->
        <!-- ============================================== -->
        <div v-if="editorMode === 'website'" class="space-y-6">
            <label
                v-if="availableWpPlatforms.length > 1"
                class="flex flex-col gap-2 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center"
            >
                <span class="shrink-0 text-sm font-bold text-foreground">
                    Website đăng bài
                </span>
                <select
                    :value="primaryWpPlatform?.id"
                    :disabled="readOnly"
                    class="h-11 min-w-0 flex-1 rounded-lg border-2 border-[#21759B]/40 bg-background px-3 text-sm font-semibold text-foreground outline-none focus:border-[#21759B]"
                    @change="selectWordPressWebsite"
                >
                    <option
                        v-for="platform in availableWpPlatforms"
                        :key="platform.id"
                        :value="platform.id"
                    >
                        {{
                            wordPressSiteForPlatform(platform)?.name ||
                            platform.social_account?.display_name ||
                            'Website WordPress'
                        }}
                        —
                        {{
                            wordPressSiteForPlatform(platform)?.url ||
                            platform.platform_username ||
                            platform.social_account?.username
                        }}
                    </option>
                </select>
            </label>

            <!-- Website Status Bar (Minimalist Notion/Medium style) -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card/60 p-3.5 text-xs"
            >
                <div class="flex items-center gap-2.5">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    <span class="font-bold text-foreground">
                        {{
                            currentWpSite?.name ||
                            primaryWpPlatform?.social_account?.display_name ||
                            'Website WordPress'
                        }}
                    </span>
                    <span class="font-mono text-[11px] text-muted-foreground">
                        ({{
                            currentWpSite?.url ||
                            primaryWpPlatform?.social_account?.username ||
                            'https://kingcoffee.com'
                        }})
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-border bg-background px-2.5 py-1 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        :disabled="isSyncingWp"
                        @click="syncWpTaxonomies"
                    >
                        <IconRefresh
                            class="size-3"
                            :class="isSyncingWp ? 'animate-spin' : ''"
                        />
                        <span>{{
                            isSyncingWp ? 'Đang đồng bộ...' : 'Đồng bộ từ Web'
                        }}</span>
                    </button>
                    <button
                        v-if="availableWpPlatforms.length === 0"
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-md bg-primary px-2.5 py-1 text-xs font-medium text-primary-foreground"
                        @click="emit('open-wordpress-connect')"
                    >
                        <span>+ Kết nối Website</span>
                    </button>
                </div>
            </div>

            <!-- Post Title (Clean Medium/Notion H1 style) -->
            <div class="space-y-3 rounded-xl border border-border bg-card p-5">
                <div class="space-y-1">
                    <label
                        class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >Tiêu đề bài viết (Title / H1)</label
                    >
                    <input
                        type="text"
                        v-model="wpTitle"
                        :readonly="readOnly"
                        placeholder="Nhập tiêu đề bài viết chuẩn SEO..."
                        class="w-full border-0 bg-transparent p-0 text-xl font-black text-foreground outline-none placeholder:text-muted-foreground/35 sm:text-2xl"
                        @input="generateSlugFromTitle"
                    />
                </div>

                <!-- Slug / Permalink Bar -->
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-t border-border/40 pt-2 text-xs"
                >
                    <div
                        class="flex items-center gap-1 font-mono text-[11px] text-muted-foreground"
                    >
                        <span
                            >{{
                                currentWpSite?.url ||
                                primaryWpPlatform?.social_account?.username ||
                                'https://kingcoffee.com'
                            }}/</span
                        >
                        <input
                            type="text"
                            v-model="wpSlug"
                            :readonly="readOnly"
                            placeholder="slug-duong-dan-bai-viet"
                            class="border-0 bg-transparent p-0 font-mono font-bold text-foreground focus:outline-none"
                        />
                    </div>
                    <button
                        v-if="!readOnly"
                        type="button"
                        class="cursor-pointer text-[11px] font-semibold text-primary hover:underline"
                        @click="generateSlugFromTitle"
                    >
                        Tạo slug từ tiêu đề
                    </button>
                </div>
            </div>

            <!-- Featured Image / Thumbnail (Clean Minimalist) -->
            <div class="space-y-3 rounded-xl border border-border bg-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Ảnh đại diện (Featured Image)
                        </h3>
                        <p class="text-[11px] text-muted-foreground">
                            Hiển thị làm ảnh bìa thumbnail ngoài trang chủ và
                            thẻ OpenGraph khi chia sẻ link.
                        </p>
                    </div>
                    <button
                        v-if="!readOnly"
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border bg-muted/40 px-3 py-1.5 text-xs font-semibold text-foreground transition hover:bg-muted"
                        @click="mediaPickerDialog?.open()"
                    >
                        <span>+ Chọn ảnh từ thư viện</span>
                    </button>
                </div>

                <div
                    v-if="media.length > 0"
                    class="grid grid-cols-2 gap-3 pt-1 sm:grid-cols-4"
                >
                    <div
                        v-for="(item, idx) in media"
                        :key="item.id"
                        class="group relative aspect-video overflow-hidden rounded-lg border border-border bg-muted"
                    >
                        <img :src="item.url" class="size-full object-cover" />
                        <span
                            v-if="idx === 0"
                            class="absolute top-1.5 left-1.5 rounded bg-primary/90 px-1.5 py-0.5 text-[9px] font-bold text-primary-foreground"
                        >
                            Ảnh đại diện
                        </span>
                        <button
                            v-if="!readOnly"
                            type="button"
                            class="absolute top-1.5 right-1.5 flex size-5 cursor-pointer items-center justify-center rounded-full bg-black/70 text-white opacity-0 transition group-hover:opacity-100"
                            @click="removeMedia(item.id)"
                        >
                            <IconTrash class="size-2.5" />
                        </button>
                    </div>
                </div>
                <div
                    v-else
                    class="rounded-lg border border-dashed border-border/80 p-5 text-center text-xs text-muted-foreground"
                >
                    Chưa có ảnh đại diện. Nhấn
                    <strong>"Chọn ảnh từ thư viện"</strong> ở trên để thêm
                    thumbnail.
                </div>
            </div>

            <!-- WordPress Classic Editor -->
            <div class="space-y-3 rounded-xl border border-border bg-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Nội dung chi tiết bài viết
                        </h3>
                        <p class="text-[11px] text-muted-foreground">
                            Trình soạn thảo trực quan theo giao diện WordPress
                            Classic Editor.
                        </p>
                    </div>

                    <!-- AI Assist buttons -->
                    <div v-if="!readOnly" class="flex items-center gap-1.5">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-border bg-muted/30 px-2.5 py-1 text-xs font-semibold text-foreground transition hover:bg-muted"
                            @click="emit('open-ai-generate')"
                        >
                            <IconSparkles class="size-3 text-amber-600" />
                            <span>AI Viết Bài</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-border bg-muted/30 px-2.5 py-1 text-xs font-semibold text-foreground transition hover:bg-muted"
                            @click="emit('open-ai-review')"
                        >
                            <span>AI Tối Ưu SEO</span>
                        </button>
                    </div>
                </div>

                <WordPressClassicEditor
                    v-model="content"
                    :read-only="readOnly"
                />
            </div>

            <!-- Taxonomy & Publishing Options (Clean Grid) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <!-- Categories & Tags -->
                <div
                    class="space-y-4 rounded-xl border border-border bg-card p-5"
                >
                    <!-- Categories -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Chuyên mục (Categories)</label
                            >
                            <span
                                v-if="availableWpCategories.length > 0"
                                class="text-[11px] text-muted-foreground"
                            >
                                {{ availableWpCategories.length }} danh mục
                            </span>
                        </div>

                        <!-- Clickable category chips -->
                        <div
                            v-if="availableWpCategories.length > 0"
                            class="flex flex-wrap items-center gap-1.5"
                        >
                            <button
                                v-for="cat in availableWpCategories"
                                :key="cat.id || cat.name"
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium transition"
                                :class="
                                    selectedCategoryList.includes(cat.name)
                                        ? 'border-primary bg-primary font-semibold text-primary-foreground'
                                        : 'border-border bg-muted/30 text-foreground/80 hover:bg-muted'
                                "
                                @click="toggleCategory(cat.name)"
                            >
                                <IconCheck
                                    v-if="
                                        selectedCategoryList.includes(cat.name)
                                    "
                                    class="size-2.5 stroke-[3]"
                                />
                                <span>{{ cat.name }}</span>
                                <span
                                    v-if="cat.count !== undefined"
                                    class="text-[10px] opacity-70"
                                    >({{ cat.count }})</span
                                >
                            </button>
                        </div>

                        <input
                            type="text"
                            v-model="wpCategories"
                            :readonly="readOnly"
                            placeholder="Nhập chuyên mục cách nhau bằng dấu phẩy..."
                            class="h-9 w-full rounded-lg border border-border bg-background px-3 text-xs text-foreground outline-none focus:border-primary"
                        />
                    </div>

                    <!-- Tags -->
                    <div class="space-y-2 border-t border-border/40 pt-3">
                        <div class="flex items-center justify-between">
                            <label
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Thẻ bài viết (Tags)</label
                            >
                            <span
                                v-if="availableWpTags.length > 0"
                                class="text-[11px] text-muted-foreground"
                            >
                                {{ availableWpTags.length }} thẻ
                            </span>
                        </div>

                        <!-- Clickable tag chips -->
                        <div
                            v-if="availableWpTags.length > 0"
                            class="flex flex-wrap items-center gap-1.5"
                        >
                            <button
                                v-for="tag in availableWpTags"
                                :key="tag.id || tag.name"
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-medium transition"
                                :class="
                                    selectedTagList.includes(tag.name)
                                        ? 'border-primary bg-primary/10 font-semibold text-primary'
                                        : 'border-border bg-background text-muted-foreground hover:text-foreground'
                                "
                                @click="toggleTag(tag.name)"
                            >
                                <IconCheck
                                    v-if="selectedTagList.includes(tag.name)"
                                    class="size-2 stroke-[3]"
                                />
                                <span>#{{ tag.name }}</span>
                            </button>
                        </div>

                        <input
                            type="text"
                            v-model="wpTags"
                            :readonly="readOnly"
                            placeholder="Nhập từ khóa tag cách nhau bằng dấu phẩy..."
                            class="h-9 w-full rounded-lg border border-border bg-background px-3 text-xs text-foreground outline-none focus:border-primary"
                        />
                    </div>
                </div>

                <!-- Status & Excerpt -->
                <div
                    class="space-y-4 rounded-xl border border-border bg-card p-5"
                >
                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Trạng thái xuất bản</label
                        >
                        <select
                            v-model="wpStatus"
                            :disabled="readOnly"
                            class="h-9 w-full rounded-lg border border-border bg-background px-3 text-xs font-semibold text-foreground outline-none focus:border-primary"
                        >
                            <option value="publish">
                                Xuất bản công khai (Publish)
                            </option>
                            <option value="draft">Lưu bản nháp (Draft)</option>
                            <option value="pending">
                                Chờ biên tập viên duyệt (Pending Review)
                            </option>
                            <option value="private">
                                Riêng tư nội bộ (Private)
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5 pt-1">
                        <label
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Đoạn trích tóm tắt (Excerpt / Meta SEO)</label
                        >
                        <textarea
                            v-model="wpExcerpt"
                            :readonly="readOnly"
                            rows="4"
                            placeholder="Tóm tắt ngắn gọn 1-2 câu hiển thị ngoài trang chủ và Google Search snippet..."
                            class="w-full resize-none rounded-lg border border-border bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground/40 focus:border-primary"
                        />
                    </div>
                </div>
            </div>

            <!-- Publishing Action Bar (Clean & Professional) -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4"
            >
                <div
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    <span
                        >Sẵn sàng xuất bản lên
                        <strong>{{
                            currentWpSite?.name || 'Website WordPress'
                        }}</strong></span
                    >
                </div>

                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-border bg-background px-4 py-2 text-xs font-semibold text-foreground transition hover:bg-muted"
                        @click="handleSaveDraftWp"
                    >
                        <span>Lưu bản nháp</span>
                    </button>
                    <button
                        v-if="canPublish"
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-primary px-5 py-2 text-xs font-bold text-primary-foreground shadow-2xs transition hover:bg-primary/90"
                        @click="handlePublishWp"
                    >
                        <span>Đẩy bài viết lên WordPress</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- 2. GIAO DIỆN BÀI VIẾT MẠNG XÃ HỘI (SOCIAL)      -->
        <!-- ============================================== -->
        <div v-else class="relative">
            <!-- Media grid (top) — always shown so "Add" tile is discoverable -->
            <div v-if="!readOnly || media.length" class="mb-6">
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    <div
                        v-for="(item, index) in media"
                        :key="item.id"
                        :ref="
                            (el) => {
                                if (el)
                                    mediaThumbRefs[index] = el as HTMLElement;
                            }
                        "
                        data-testid="media-thumbnail"
                        class="group relative aspect-square cursor-zoom-in overflow-hidden rounded-xl border-2 border-foreground bg-muted shadow-2xs transition-all focus:ring-2 focus:ring-foreground focus:ring-offset-2 focus:outline-none"
                        :class="[
                            dragMediaIndex === index ? 'opacity-40' : '',
                            dragOverIndex === index && dragMediaIndex !== index
                                ? 'ring-2 ring-foreground ring-offset-2'
                                : '',
                            mediaIssues[item.id] ? '!border-rose-500' : '',
                        ]"
                        tabindex="0"
                        :draggable="!readOnly && media.length > 1"
                        @click="openPreview(item)"
                        @dragstart="onMediaDragStart($event, index)"
                        @dragover="onMediaDragOver($event, index)"
                        @drop="onMediaDrop($event, index)"
                        @dragend="onMediaDragEnd"
                        @keydown="onMediaKeydown($event, index)"
                    >
                        <video
                            v-if="isVideo(item)"
                            :src="item.url"
                            class="h-full w-full object-cover"
                            muted
                        />
                        <div
                            v-else-if="isDocument(item)"
                            class="flex h-full w-full flex-col items-center justify-center gap-1 bg-rose-50 p-2 text-center"
                        >
                            <IconFileTypePdf class="size-7 text-rose-600" />
                            <span
                                class="line-clamp-2 text-[10px] font-medium break-all text-foreground/70"
                                >{{ item.original_filename || 'PDF' }}</span
                            >
                        </div>
                        <img
                            v-else
                            :src="item.url"
                            :alt="item.original_filename"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />

                        <div
                            class="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-black/60 to-transparent opacity-0 transition-opacity group-hover:opacity-100"
                        />

                        <div
                            class="pointer-events-none absolute inset-x-1.5 bottom-1.5 flex flex-col items-start gap-1 text-[10px] font-medium text-white"
                        >
                            <span
                                v-if="isVideo(item) && item.meta?.duration"
                                class="inline-flex items-center gap-0.5 rounded-md bg-black/65 px-1.5 py-0.5 backdrop-blur-sm"
                            >
                                <IconVideo class="size-2.5" />
                                {{ date.formatClock(item.meta.duration) }}
                            </span>
                            <span
                                v-if="isImage(item) && item.meta?.alt_text"
                                class="inline-block max-w-full truncate rounded-md bg-black/65 px-1.5 py-0.5 backdrop-blur-sm"
                                data-testid="alt-text-badge"
                            >
                                {{ item.meta.alt_text }}
                            </span>
                        </div>

                        <TooltipProvider
                            v-if="mediaIssues[item.id]"
                            :delay-duration="100"
                        >
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <span
                                        class="absolute right-1.5 bottom-1.5 inline-flex h-5 items-center gap-0.5 rounded-full border-2 border-foreground bg-rose-100 px-1.5 text-[10px] font-bold text-rose-700 shadow-2xs"
                                    >
                                        <IconAlertTriangle class="size-2.5" />
                                        {{ mediaIssues[item.id].length }}
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <ul class="text-xs">
                                        <li
                                            v-for="issue in mediaIssues[
                                                item.id
                                            ]"
                                            :key="issue.platform"
                                        >
                                            <strong
                                                >{{
                                                    getPlatformLabel(
                                                        issue.platform,
                                                    )
                                                }}:</strong
                                            >
                                            {{ issue.reason }}
                                        </li>
                                    </ul>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <span
                            v-if="media.length > 1 && !readOnly"
                            class="absolute top-1.5 left-1.5 inline-flex size-6 cursor-grab items-center justify-center rounded-md border-2 border-foreground bg-card text-foreground opacity-100 shadow-2xs transition-opacity lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus:opacity-100"
                        >
                            <IconGripVertical class="size-3.5" />
                        </span>

                        <button
                            v-if="canRegenerateWithAi(item)"
                            type="button"
                            class="absolute bottom-1.5 left-1.5 inline-flex h-6 cursor-pointer items-center gap-1 rounded-md border-2 border-foreground bg-card px-1.5 text-[10px] font-semibold text-foreground opacity-100 shadow-2xs transition-all hover:bg-violet-100 lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus:opacity-100"
                            @click.stop="
                                emit('open-ai-regenerate-image', item.id)
                            "
                        >
                            <IconRefresh class="size-3" />
                            {{ $t('posts.ai.image_regenerate.button') }}
                        </button>

                        <button
                            v-if="!readOnly && isImage(item)"
                            type="button"
                            :title="$t('posts.edit.alt_text.edit')"
                            :aria-label="$t('posts.edit.alt_text.edit')"
                            class="absolute top-1.5 right-9 inline-flex size-6 cursor-pointer items-center justify-center rounded-md border-2 border-foreground bg-card text-foreground opacity-100 shadow-2xs transition-all hover:bg-violet-100 lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus:opacity-100"
                            data-testid="alt-text-button"
                            @click.stop="openAltText(index)"
                        >
                            <IconAlt class="size-3.5" />
                        </button>

                        <button
                            v-if="!readOnly"
                            type="button"
                            class="absolute top-1.5 right-1.5 inline-flex size-6 cursor-pointer items-center justify-center rounded-md border-2 border-foreground bg-card text-foreground opacity-100 shadow-2xs transition-all hover:bg-rose-100 hover:text-rose-700 lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus:opacity-100"
                            data-testid="media-remove"
                            @click.stop="removeMedia(item.id)"
                        >
                            <IconTrash class="size-3.5" />
                        </button>
                    </div>

                    <button
                        v-if="!readOnly"
                        type="button"
                        class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-foreground/25 text-foreground/60 transition-colors hover:border-foreground hover:bg-foreground/5 hover:text-foreground"
                        @click="mediaPickerDialog?.open()"
                    >
                        <IconLibraryPhoto class="size-5" />
                        <span
                            class="text-[10px] font-bold tracking-widest uppercase"
                            >{{ $t('posts.edit.add') }}</span
                        >
                    </button>
                </div>
            </div>

            <!-- Toolbar: between photos and textarea -->
            <div v-if="!readOnly" class="mb-4 flex items-center gap-2">
                <Popover v-model:open="emojiOpen">
                    <PopoverAnchor as-child>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <button
                                        type="button"
                                        class="inline-flex size-9 cursor-pointer items-center justify-center rounded-lg border-2 border-foreground bg-card text-foreground shadow-2xs transition-all hover:-translate-y-0.5 hover:bg-violet-100 hover:shadow-sm"
                                        @click="emojiOpen = !emojiOpen"
                                    >
                                        <IconMoodSmile class="size-4" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent>{{
                                    $t('posts.edit.emoji_picker.search')
                                }}</TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </PopoverAnchor>
                    <PopoverContent class="w-auto p-0" align="start">
                        <EmojiPicker @select="appendEmoji" />
                    </PopoverContent>
                </Popover>

                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                class="inline-flex size-9 cursor-pointer items-center justify-center rounded-lg border-2 border-foreground bg-card text-foreground shadow-2xs transition-all hover:-translate-y-0.5 hover:bg-violet-100 hover:shadow-sm"
                                @click="signaturesModal?.open()"
                            >
                                <IconHash class="size-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            $t('posts.edit.signatures')
                        }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                class="inline-flex size-9 cursor-pointer items-center justify-center rounded-lg border-2 border-foreground bg-card text-foreground shadow-2xs transition-all hover:-translate-y-0.5 hover:bg-violet-100 hover:shadow-sm"
                                @click="emit('open-ai-generate')"
                            >
                                <IconSparkles class="size-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            $t('posts.ai.generate.button_tooltip')
                        }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                class="inline-flex size-9 cursor-pointer items-center justify-center rounded-lg border-2 border-foreground bg-card text-foreground shadow-2xs transition-all hover:-translate-y-0.5 hover:bg-violet-100 hover:shadow-sm"
                                @click="emit('open-ai-review')"
                            >
                                <IconWriting class="size-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            $t('posts.ai.review.button_tooltip')
                        }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <!-- Per-platform counters (below menu, above textarea) -->
            <div
                v-if="limitsWithUsage.length > 0"
                class="mb-4 flex flex-wrap items-center gap-2"
            >
                <TooltipProvider
                    v-for="limit in limitsWithUsage"
                    :key="limit.platform"
                    :delay-duration="200"
                >
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border-2 px-2 py-1 text-[11px] leading-none font-bold tabular-nums shadow-2xs transition-colors"
                                :class="limitClass(limit.state)"
                            >
                                <span
                                    class="inline-flex size-3.5 shrink-0 items-center justify-center overflow-hidden rounded-full"
                                >
                                    <img
                                        :src="getPlatformLogo(limit.platform)"
                                        :alt="limit.platform"
                                        class="size-full object-cover"
                                    />
                                </span>
                                <span
                                    >{{ limit.used
                                    }}<span class="opacity-60"
                                        >/{{ limit.maxLength }}</span
                                    ></span
                                >
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            getPlatformLabel(limit.platform)
                        }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <!-- Textarea: borderless, sheet-of-paper feel.
                 Mirror div sits behind to highlight chars beyond the smallest platform limit.
                 Both share identical font/padding/leading/wrap so highlights align with the textarea text. -->
            <div class="relative font-sans text-base leading-[1.7]">
                <div
                    v-if="overflowParts.overflow"
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0 p-0 font-sans text-base leading-[1.7] break-words whitespace-pre-wrap text-transparent"
                >
                    <span>{{ overflowParts.fits }}</span
                    ><span class="rounded-sm bg-rose-100 text-rose-700">{{
                        overflowParts.overflow
                    }}</span>
                </div>
                <textarea
                    v-model="content"
                    :readonly="readOnly"
                    :placeholder="
                        readOnly ? '' : $t('posts.edit.caption_placeholder')
                    "
                    class="relative block w-full resize-none border-0 bg-transparent p-0 font-sans text-base leading-[1.7] shadow-none outline-none placeholder:text-foreground/40"
                    style="min-height: 240px; field-sizing: content"
                />
            </div>

            <!-- KPI & Tagging Classification Bar (CEO Tag & Content Topics) -->
            <div
                class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-foreground/10 pt-3 text-xs"
            >
                <!-- CEO Content Toggle -->
                <label
                    class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-2 px-2.5 py-1.5 font-bold transition-all select-none"
                    :class="
                        isCeoContent
                            ? 'border-amber-500 bg-amber-50 text-amber-900 shadow-xs dark:bg-amber-950 dark:text-amber-300'
                            : 'border-foreground/15 bg-background text-muted-foreground hover:text-foreground'
                    "
                >
                    <input
                        type="checkbox"
                        v-model="isCeoContent"
                        class="hidden"
                    />
                    <span>👑 Bài viết liên quan CEO</span>
                    <span
                        v-if="isCeoContent"
                        class="size-2 rounded-full bg-amber-500"
                    ></span>
                </label>

                <!-- Customizable Topic Tags -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <span
                        class="text-[11px] font-semibold text-muted-foreground"
                        >Thẻ bài viết:</span
                    >

                    <div
                        v-for="tag in allAvailableTags"
                        :key="tag"
                        class="group relative inline-flex items-center"
                    >
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold transition-all"
                            :class="
                                topicTags.includes(tag)
                                    ? 'border-primary bg-primary text-primary-foreground shadow-2xs'
                                    : 'border-foreground/15 bg-background text-muted-foreground hover:border-foreground/30 hover:text-foreground'
                            "
                            @click="toggleTopicTag(tag)"
                        >
                            <IconCheck
                                v-if="topicTags.includes(tag)"
                                class="size-3 stroke-[3]"
                            />
                            #{{ tag }}
                        </button>

                        <!-- Delete button for custom tags -->
                        <button
                            v-if="!isDefaultTag(tag)"
                            type="button"
                            class="ml-0.5 hidden size-4 cursor-pointer items-center justify-center rounded-full bg-destructive/10 text-destructive opacity-80 group-hover:inline-flex hover:opacity-100"
                            title="Xóa tag này"
                            @click.stop="removeCustomTag(tag)"
                        >
                            <IconX class="size-2.5" />
                        </button>
                    </div>

                    <!-- Add Custom Tag Input / Button -->
                    <div
                        v-if="showAddTagInput"
                        class="inline-flex items-center gap-1"
                    >
                        <input
                            v-model="newTagInput"
                            type="text"
                            placeholder="Nhập tên tag..."
                            class="h-6 w-28 rounded-md border border-input bg-background px-2 text-[11px] font-medium text-foreground outline-hidden focus:border-primary focus:ring-1 focus:ring-primary"
                            @keydown.enter.prevent="addCustomTag"
                            @keydown.esc="showAddTagInput = false"
                        />
                        <button
                            type="button"
                            class="inline-flex h-6 cursor-pointer items-center justify-center rounded-md bg-primary px-2 text-[10px] font-bold text-primary-foreground hover:bg-primary/90"
                            @click="addCustomTag"
                        >
                            Thêm
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-6 cursor-pointer items-center justify-center text-muted-foreground hover:text-foreground"
                            @click="showAddTagInput = false"
                        >
                            <IconX class="size-3.5" />
                        </button>
                    </div>

                    <button
                        v-else
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-dashed border-foreground/30 bg-background px-2 py-0.5 text-[11px] font-medium text-muted-foreground transition-colors hover:border-primary hover:text-primary"
                        @click="showAddTagInput = true"
                    >
                        <IconPlus class="size-3" />
                        <span>Thêm thẻ</span>
                    </button>
                </div>
            </div>
        </div>

        <SignaturesModal
            ref="signaturesModal"
            :signatures="signatures"
            @select="appendSignature"
        />
        <MediaPickerDialog
            ref="mediaPickerDialog"
            @select="addMediaFromGallery"
        />
        <ImagePreviewDialog ref="lightbox" />
        <AltTextDialog
            v-model:open="altDialogOpen"
            :media-item="altDialogItem"
            @save="onAltTextSave"
        />
    </div>
</template>
