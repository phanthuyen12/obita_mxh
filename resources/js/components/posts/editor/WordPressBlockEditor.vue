<script setup lang="ts">
import MediaPickerDialog from '@/components/posts/MediaPickerDialog.vue';
import { storeChunked as assetsStoreChunked } from '@/routes/app/assets';
import { uploadChunked } from '@/utils/chunkedUpload';
import {
    IconArrowDown,
    IconArrowUp,
    IconCode,
    IconCopy,
    IconEye,
    IconFolder,
    IconLink,
    IconList,
    IconListNumbers,
    IconLoader2,
    IconPhoto,
    IconPilcrow,
    IconPlus,
    IconQuote,
    IconTrash,
    IconUpload,
} from '@tabler/icons-vue';
import { nextTick, onUnmounted, ref, watch } from 'vue';

export type BlockType =
    | 'paragraph'
    | 'h2'
    | 'h3'
    | 'quote'
    | 'list'
    | 'image'
    | 'cta';

export interface EditorBlock {
    id: string;
    type: BlockType;
    content: string;
    meta?: {
        listType?: 'unordered' | 'ordered';
        caption?: string;
        alt?: string;
        buttonText?: string;
        buttonUrl?: string;
        title?: string;
        description?: string;
        align?: 'left' | 'center' | 'right';
        isUploading?: boolean;
        uploadProgress?: number;
        showUrlInput?: boolean;
    };
}

const props = withDefaults(
    defineProps<{
        modelValue: string;
        readOnly?: boolean;
    }>(),
    {
        readOnly: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'ai-assist', prompt: string): void;
}>();

const activeBlockId = ref<string | null>(null);
const hoverBlockId = ref<string | null>(null);
const showInserterMenuIndex = ref<number | null>(null);
const showCodeView = ref(false);
const rawCodeText = ref(props.modelValue || '');

const blockInputRefs = ref<
    Record<string, HTMLTextAreaElement | HTMLInputElement | null>
>({});
const mediaPickerDialog = ref<InstanceType<typeof MediaPickerDialog> | null>(
    null,
);
const pickingMediaForBlockId = ref<string | null>(null);
const fileInputRefs = ref<Record<string, HTMLInputElement | null>>({});

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
const isSyncingInternal = ref(false);

const generateId = () => 'wp_' + Math.random().toString(36).substring(2, 9);

// Parse incoming HTML/Markdown into clean Gutenberg blocks
const parseContentToBlocks = (raw: string): EditorBlock[] => {
    if (!raw || !raw.trim()) {
        return [{ id: generateId(), type: 'paragraph', content: '' }];
    }

    const trimmed = raw.trim();

    if (/<(h[1-6]|p|blockquote|ul|ol|figure|div|img|br)/i.test(trimmed)) {
        const blocks: EditorBlock[] = [];
        const parser = new DOMParser();
        const doc = parser.parseFromString(
            `<div>${trimmed}</div>`,
            'text/html',
        );
        const container = doc.body.firstElementChild;

        if (container) {
            Array.from(container.childNodes).forEach((node) => {
                if (node.nodeType === Node.TEXT_NODE) {
                    const text = node.textContent?.trim() || '';
                    if (text) {
                        blocks.push({
                            id: generateId(),
                            type: 'paragraph',
                            content: text,
                        });
                    }
                    return;
                }

                if (node.nodeType === Node.ELEMENT_NODE) {
                    const el = node as HTMLElement;
                    const tag = el.tagName.toLowerCase();

                    if (tag === 'h1' || tag === 'h2') {
                        blocks.push({
                            id: generateId(),
                            type: 'h2',
                            content: el.textContent?.trim() || '',
                        });
                    } else if (
                        tag === 'h3' ||
                        tag === 'h4' ||
                        tag === 'h5' ||
                        tag === 'h6'
                    ) {
                        blocks.push({
                            id: generateId(),
                            type: 'h3',
                            content: el.textContent?.trim() || '',
                        });
                    } else if (tag === 'blockquote') {
                        blocks.push({
                            id: generateId(),
                            type: 'quote',
                            content: el.textContent?.trim() || '',
                        });
                    } else if (tag === 'ul' || tag === 'ol') {
                        const items = Array.from(el.querySelectorAll('li'))
                            .map((li) => li.textContent?.trim() || '')
                            .filter(Boolean);
                        blocks.push({
                            id: generateId(),
                            type: 'list',
                            content: items.join('\n'),
                            meta: {
                                listType:
                                    tag === 'ol' ? 'ordered' : 'unordered',
                            },
                        });
                    } else if (tag === 'figure' || tag === 'img') {
                        const img =
                            tag === 'img'
                                ? (el as HTMLImageElement)
                                : el.querySelector('img');
                        const figcaption = el.querySelector('figcaption');
                        if (img) {
                            blocks.push({
                                id: generateId(),
                                type: 'image',
                                content: img.getAttribute('src') || '',
                                meta: {
                                    caption:
                                        figcaption?.textContent?.trim() || '',
                                    alt: img.getAttribute('alt') || '',
                                },
                            });
                        }
                    } else if (
                        el.classList.contains('wp-block-cta') ||
                        el.classList.contains('cta-box')
                    ) {
                        const title =
                            el
                                .querySelector('h4, .cta-title')
                                ?.textContent?.trim() || '';
                        const desc =
                            el
                                .querySelector('p, .cta-desc')
                                ?.textContent?.trim() || '';
                        const btn = el.querySelector(
                            'a, .cta-btn, .wp-block-button__link',
                        );
                        blocks.push({
                            id: generateId(),
                            type: 'cta',
                            content: title,
                            meta: {
                                description: desc,
                                buttonText:
                                    btn?.textContent?.trim() || 'Xem chi tiết',
                                buttonUrl: btn?.getAttribute('href') || '#',
                            },
                        });
                    } else {
                        const text = el.textContent?.trim() || '';
                        if (text) {
                            blocks.push({
                                id: generateId(),
                                type: 'paragraph',
                                content: text,
                            });
                        }
                    }
                }
            });
        }

        return blocks.length > 0
            ? blocks
            : [{ id: generateId(), type: 'paragraph', content: raw }];
    }

    // Fallback: parse plain text / Markdown paragraphs
    const paragraphs = raw.split(/\n\n+/);
    const parsed = paragraphs
        .map((p) => {
            const text = p.trim();
            if (text.startsWith('### ')) {
                return {
                    id: generateId(),
                    type: 'h3' as BlockType,
                    content: text.substring(4),
                };
            }
            if (text.startsWith('## ')) {
                return {
                    id: generateId(),
                    type: 'h2' as BlockType,
                    content: text.substring(3),
                };
            }
            if (text.startsWith('> ')) {
                return {
                    id: generateId(),
                    type: 'quote' as BlockType,
                    content: text.substring(2),
                };
            }
            if (text.startsWith('- ') || text.startsWith('* ')) {
                const lines = text
                    .split('\n')
                    .map((l) => l.replace(/^[-*]\s+/, ''))
                    .filter(Boolean);
                return {
                    id: generateId(),
                    type: 'list' as BlockType,
                    content: lines.join('\n'),
                    meta: { listType: 'unordered' as const },
                };
            }
            return {
                id: generateId(),
                type: 'paragraph' as BlockType,
                content: text,
            };
        })
        .filter((b) => b.content);

    return parsed.length > 0
        ? parsed
        : [{ id: generateId(), type: 'paragraph', content: raw }];
};

// Compile blocks to semantic WordPress HTML
const compileBlocksToHtml = (blockList: EditorBlock[]): string => {
    return blockList
        .map((b) => {
            const content = (b.content || '').trim();
            if (b.type === 'h2') {
                return content
                    ? `<!-- wp:heading {"level":2} -->\n<h2>${escapeHtml(content)}</h2>\n<!-- /wp:heading -->`
                    : '';
            }
            if (b.type === 'h3') {
                return content
                    ? `<!-- wp:heading {"level":3} -->\n<h3>${escapeHtml(content)}</h3>\n<!-- /wp:heading -->`
                    : '';
            }
            if (b.type === 'quote') {
                return content
                    ? `<!-- wp:quote -->\n<blockquote class="wp-block-quote"><p>${nl2br(escapeHtml(content))}</p></blockquote>\n<!-- /wp:quote -->`
                    : '';
            }
            if (b.type === 'list') {
                if (!content) return '';
                const items = content
                    .split('\n')
                    .map((item) => item.trim())
                    .filter(Boolean);
                const tag = b.meta?.listType === 'ordered' ? 'ol' : 'ul';
                return (
                    `<!-- wp:list {"ordered":${b.meta?.listType === 'ordered'}} -->\n<${tag}>\n` +
                    items.map((i) => `  <li>${escapeHtml(i)}</li>`).join('\n') +
                    `\n</${tag}>\n<!-- /wp:list -->`
                );
            }
            if (b.type === 'image') {
                if (!content) return '';
                const caption = b.meta?.caption
                    ? `<figcaption class="wp-element-caption">${escapeHtml(b.meta.caption)}</figcaption>`
                    : '';
                return `<!-- wp:image -->\n<figure class="wp-block-image size-large"><img src="${escapeHtml(content)}" alt="${escapeHtml(b.meta?.alt || '')}" />${caption}</figure>\n<!-- /wp:image -->`;
            }
            if (b.type === 'cta') {
                const title = escapeHtml(b.content || '');
                const desc = b.meta?.description
                    ? `<p class="cta-desc">${escapeHtml(b.meta.description)}</p>`
                    : '';
                const btn = b.meta?.buttonText
                    ? `<div class="wp-block-button"><a href="${escapeHtml(b.meta.buttonUrl || '#')}" class="wp-block-button__link wp-element-button">${escapeHtml(b.meta.buttonText)}</a></div>`
                    : '';
                return `<!-- wp:group {"className":"wp-block-cta cta-box"} -->\n<div class="wp-block-cta cta-box"><h4 class="cta-title">${title}</h4>${desc}${btn}</div>\n<!-- /wp:group -->`;
            }
            return content
                ? `<!-- wp:paragraph -->\n<p>${nl2br(content)}</p>\n<!-- /wp:paragraph -->`
                : '';
        })
        .filter(Boolean)
        .join('\n\n');
};

const escapeHtml = (str: string) => {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

const nl2br = (str: string) => {
    return str.replace(/\n/g, '<br />');
};

const blocks = ref<EditorBlock[]>(parseContentToBlocks(props.modelValue));

// Sync when external modelValue changes
watch(
    () => props.modelValue,
    (newVal) => {
        if (isSyncingInternal.value) return;
        rawCodeText.value = newVal || '';
        blocks.value = parseContentToBlocks(newVal || '');
    },
    { immediate: true },
);

const emitSync = (immediate = false) => {
    if (debounceTimer) clearTimeout(debounceTimer);

    const apply = () => {
        isSyncingInternal.value = true;
        const html = compileBlocksToHtml(blocks.value);
        rawCodeText.value = html;
        emit('update:modelValue', html);
        setTimeout(() => {
            isSyncingInternal.value = false;
        }, 50);
    };

    if (immediate) {
        apply();
    } else {
        debounceTimer = setTimeout(apply, 150);
    }
};

// Focus management
const focusBlock = (id: string) => {
    activeBlockId.value = id;
    showInserterMenuIndex.value = null;
};

const focusBlockInput = async (id: string) => {
    await nextTick();
    const el = blockInputRefs.value[id];
    if (el) {
        el.focus();
        if (
            el instanceof HTMLTextAreaElement ||
            el instanceof HTMLInputElement
        ) {
            const len = el.value.length;
            el.setSelectionRange(len, len);
        }
    }
};

// Auto resize textarea height smoothly
const autoResizeTextarea = (e: Event) => {
    const el = e.target as HTMLTextAreaElement;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
};

// Natural Gutenberg Keyboard Actions
const handleBlockKeydown = (
    e: KeyboardEvent,
    index: number,
    block: EditorBlock,
) => {
    // 1. Enter key in Paragraph, Heading, or Quote -> Create next paragraph block
    if (
        e.key === 'Enter' &&
        !e.shiftKey &&
        (block.type === 'paragraph' ||
            block.type === 'h2' ||
            block.type === 'h3')
    ) {
        e.preventDefault();
        const newBlock: EditorBlock = {
            id: generateId(),
            type: 'paragraph',
            content: '',
        };
        blocks.value.splice(index + 1, 0, newBlock);
        emitSync(true);
        focusBlock(newBlock.id);
        focusBlockInput(newBlock.id);
        return;
    }

    // 2. Backspace on empty block -> delete block and focus previous
    if (
        e.key === 'Backspace' &&
        block.content === '' &&
        blocks.value.length > 1
    ) {
        e.preventDefault();
        const prevIndex = Math.max(0, index - 1);
        const prevBlock = blocks.value[prevIndex];
        blocks.value.splice(index, 1);
        emitSync(true);
        if (prevBlock) {
            focusBlock(prevBlock.id);
            focusBlockInput(prevBlock.id);
        }
        return;
    }

    // 3. ArrowUp on top line of block -> focus previous block
    if (e.key === 'ArrowUp' && index > 0) {
        const el = e.target as HTMLTextAreaElement | HTMLInputElement;
        if (el && el.selectionStart === 0 && el.selectionEnd === 0) {
            const prevBlock = blocks.value[index - 1];
            if (prevBlock) {
                e.preventDefault();
                focusBlock(prevBlock.id);
                focusBlockInput(prevBlock.id);
            }
        }
    }

    // 4. ArrowDown on bottom of block -> focus next block
    if (e.key === 'ArrowDown' && index < blocks.value.length - 1) {
        const el = e.target as HTMLTextAreaElement | HTMLInputElement;
        if (el && el.selectionStart === el.value.length) {
            const nextBlock = blocks.value[index + 1];
            if (nextBlock) {
                e.preventDefault();
                focusBlock(nextBlock.id);
                focusBlockInput(nextBlock.id);
            }
        }
    }
};

// Add block at specific index
const insertBlock = (type: BlockType, afterIndex?: number) => {
    const newBlock: EditorBlock = {
        id: generateId(),
        type,
        content: '',
        meta: type === 'list' ? { listType: 'unordered' } : {},
    };

    const targetIdx =
        afterIndex !== undefined ? afterIndex + 1 : blocks.value.length;
    blocks.value.splice(targetIdx, 0, newBlock);
    showInserterMenuIndex.value = null;
    emitSync(true);
    focusBlock(newBlock.id);
    focusBlockInput(newBlock.id);
};

// Convert block type directly
const convertBlockType = (block: EditorBlock, newType: BlockType) => {
    block.type = newType;
    if (newType === 'list' && !block.meta?.listType) {
        block.meta = { ...block.meta, listType: 'unordered' };
    }
    emitSync(true);
    focusBlockInput(block.id);
};

// Move block
const moveBlock = (index: number, direction: 'up' | 'down') => {
    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= blocks.value.length) return;
    const item = blocks.value[index];
    blocks.value[index] = blocks.value[target];
    blocks.value[target] = item;
    emitSync(true);
    focusBlock(item.id);
};

// Duplicate block
const duplicateBlock = (index: number) => {
    const orig = blocks.value[index];
    const clone: EditorBlock = {
        id: generateId(),
        type: orig.type,
        content: orig.content,
        meta: orig.meta ? JSON.parse(JSON.stringify(orig.meta)) : {},
    };
    blocks.value.splice(index + 1, 0, clone);
    emitSync(true);
    focusBlock(clone.id);
};

// Remove block
const removeBlock = (index: number) => {
    if (blocks.value.length === 1) {
        blocks.value[0].content = '';
        blocks.value[0].type = 'paragraph';
    } else {
        blocks.value.splice(index, 1);
    }
    emitSync(true);
};

// Direct Image Upload Handlers
const triggerFileInput = (blockId: string) => {
    fileInputRefs.value[blockId]?.click();
};

const handleImageFileUpload = async (block: EditorBlock, event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    await uploadImageFile(block, file);
    target.value = '';
};

const handleImageDrop = async (block: EditorBlock, event: DragEvent) => {
    const file = event.dataTransfer?.files?.[0];
    if (file && file.type.startsWith('image/')) {
        await uploadImageFile(block, file);
    }
};

const uploadImageFile = async (block: EditorBlock, file: File) => {
    if (!block.meta) block.meta = {};
    block.meta.isUploading = true;
    block.meta.uploadProgress = 0;

    try {
        const result = await uploadChunked({
            file,
            url: assetsStoreChunked.url(),
            collection: 'assets',
            onProgress: (p) => {
                if (block.meta) block.meta.uploadProgress = p;
            },
        });

        block.content = result.url;
        block.meta.isUploading = false;
        block.meta.uploadProgress = 100;
        emitSync(true);
    } catch (err) {
        block.meta.isUploading = false;
        // Fallback: create local object URL so user still sees the preview
        block.content = URL.createObjectURL(file);
        emitSync(true);
    }
};

// Open Media Library Picker for Block
const openMediaPickerForBlock = (blockId: string) => {
    pickingMediaForBlockId.value = blockId;
    mediaPickerDialog.value?.open();
};

const handleMediaPicked = (selected: Array<{ url: string }>) => {
    if (pickingMediaForBlockId.value && selected.length > 0) {
        const targetBlock = blocks.value.find(
            (b) => b.id === pickingMediaForBlockId.value,
        );
        if (targetBlock) {
            targetBlock.content = selected[0].url;
            emitSync(true);
        }
    }
    pickingMediaForBlockId.value = null;
};

// Raw code mode input handler
const onRawCodeInput = (e: Event) => {
    const val = (e.target as HTMLTextAreaElement).value;
    rawCodeText.value = val;
    isSyncingInternal.value = true;
    emit('update:modelValue', val);
    blocks.value = parseContentToBlocks(val);
    setTimeout(() => {
        isSyncingInternal.value = false;
    }, 50);
};

const toggleCodeView = () => {
    if (!showCodeView.value) {
        rawCodeText.value = compileBlocksToHtml(blocks.value);
    } else {
        blocks.value = parseContentToBlocks(rawCodeText.value);
    }
    showCodeView.value = !showCodeView.value;
};

onUnmounted(() => {
    if (debounceTimer) clearTimeout(debounceTimer);
});
</script>

<template>
    <div
        class="gutenberg-editor overflow-hidden rounded-2xl border border-border/80 bg-background shadow-xs"
    >
        <!-- Top Sticky Gutenberg Bar -->
        <div
            class="sticky top-0 z-20 flex items-center justify-between border-b border-border/60 bg-card/90 px-5 py-2.5 backdrop-blur-sm"
        >
            <div class="flex items-center gap-2">
                <!-- Add Block Quick Button (WordPress Blue Inserter) -->
                <button
                    v-if="!readOnly && !showCodeView"
                    type="button"
                    class="inline-flex size-8 cursor-pointer items-center justify-center rounded-lg bg-[#2271b1] text-white shadow-xs transition hover:scale-105 hover:bg-[#135e96]"
                    title="Thêm khối mới"
                    @click="insertBlock('paragraph')"
                >
                    <IconPlus class="size-4 stroke-[2.5]" />
                </button>

                <div class="mx-1 h-4 w-px bg-border/60"></div>

                <span class="text-xs font-bold text-foreground/80">
                    Trình Soạn Thảo WordPress Gutenberg
                </span>
                <span
                    class="hidden text-[11px] text-muted-foreground sm:inline"
                >
                    · {{ blocks.length }} khối
                </span>
            </div>

            <!-- View Mode switch (Visual Gutenberg / HTML Code) -->
            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1 text-xs font-semibold transition"
                    :class="
                        !showCodeView
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-border bg-background text-muted-foreground hover:text-foreground'
                    "
                    @click="showCodeView = false"
                >
                    <IconEye class="size-3.5" />
                    <span>Giao diện Trực quan</span>
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1 text-xs font-semibold transition"
                    :class="
                        showCodeView
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-border bg-background text-muted-foreground hover:text-foreground'
                    "
                    @click="toggleCodeView"
                >
                    <IconCode class="size-3.5" />
                    <span>Mã Code HTML</span>
                </button>
            </div>
        </div>

        <!-- 1. Code View Mode -->
        <div v-if="showCodeView" class="bg-muted/20 p-4">
            <textarea
                :value="rawCodeText"
                :readonly="readOnly"
                class="min-h-[500px] w-full resize-y rounded-xl border border-border bg-background p-4 font-mono text-xs leading-relaxed text-foreground outline-none focus:border-primary"
                placeholder="Mã HTML bài viết chuẩn WordPress..."
                @input="onRawCodeInput"
            />
        </div>

        <!-- 2. Pure Gutenberg Document Canvas (Seamless, No Ugly Frame Boxes) -->
        <div
            v-else
            class="gutenberg-canvas min-h-[520px] space-y-2 px-6 py-8 focus:outline-none sm:px-12"
            @click="
                if (
                    $event.target === $event.currentTarget &&
                    blocks.length === 0
                )
                    insertBlock('paragraph');
            "
        >
            <div
                v-for="(block, index) in blocks"
                :key="block.id"
                class="gutenberg-block group relative py-1 transition-all"
                :class="[activeBlockId === block.id ? 'active-block' : '']"
                @mouseenter="hoverBlockId = block.id"
                @mouseleave="hoverBlockId = null"
                @click="focusBlock(block.id)"
            >
                <!-- FLOATING GUTENBERG BLOCK TOOLBAR (Only shown on active block) -->
                <div
                    v-if="activeBlockId === block.id && !readOnly"
                    class="absolute -top-11 left-0 z-30 flex animate-in items-center gap-0.5 rounded-lg border border-border bg-background/95 p-1 shadow-md backdrop-blur-md duration-100 zoom-in-95 fade-in"
                >
                    <!-- Type Switcher Dropdown (Gutenberg style) -->
                    <div
                        class="flex items-center gap-0.5 border-r border-border pr-1"
                    >
                        <button
                            type="button"
                            class="inline-flex size-7 items-center justify-center rounded-md text-xs font-bold text-foreground hover:bg-muted"
                            :title="block.type"
                        >
                            <IconPilcrow
                                v-if="block.type === 'paragraph'"
                                class="size-4 text-[#2271b1]"
                            />
                            <span
                                v-else-if="block.type === 'h2'"
                                class="text-xs font-black text-[#2271b1]"
                                >H2</span
                            >
                            <span
                                v-else-if="block.type === 'h3'"
                                class="text-xs font-bold text-[#2271b1]"
                                >H3</span
                            >
                            <IconQuote
                                v-else-if="block.type === 'quote'"
                                class="size-4 text-[#2271b1]"
                            />
                            <IconList
                                v-else-if="block.type === 'list'"
                                class="size-4 text-[#2271b1]"
                            />
                            <IconPhoto
                                v-else-if="block.type === 'image'"
                                class="size-4 text-[#2271b1]"
                            />
                            <span
                                v-else
                                class="text-[10px] font-black text-[#2271b1] uppercase"
                                >CTA</span
                            >
                        </button>

                        <!-- Fast type switch buttons -->
                        <button
                            type="button"
                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                            :class="
                                block.type === 'paragraph'
                                    ? 'bg-muted text-foreground'
                                    : ''
                            "
                            title="Đoạn văn"
                            @click.stop="convertBlockType(block, 'paragraph')"
                        >
                            <IconPilcrow class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-xs font-black text-muted-foreground hover:bg-muted hover:text-foreground"
                            :class="
                                block.type === 'h2'
                                    ? 'bg-muted text-foreground'
                                    : ''
                            "
                            title="Tiêu đề H2"
                            @click.stop="convertBlockType(block, 'h2')"
                        >
                            H2
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-xs font-bold text-muted-foreground hover:bg-muted hover:text-foreground"
                            :class="
                                block.type === 'h3'
                                    ? 'bg-muted text-foreground'
                                    : ''
                            "
                            title="Tiêu đề H3"
                            @click.stop="convertBlockType(block, 'h3')"
                        >
                            H3
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                            :class="
                                block.type === 'quote'
                                    ? 'bg-muted text-foreground'
                                    : ''
                            "
                            title="Trích dẫn"
                            @click.stop="convertBlockType(block, 'quote')"
                        >
                            <IconQuote class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                            :class="
                                block.type === 'list'
                                    ? 'bg-muted text-foreground'
                                    : ''
                            "
                            title="Danh sách"
                            @click.stop="convertBlockType(block, 'list')"
                        >
                            <IconList class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                            :class="
                                block.type === 'image'
                                    ? 'bg-muted text-foreground'
                                    : ''
                            "
                            title="Ảnh minh họa"
                            @click.stop="convertBlockType(block, 'image')"
                        >
                            <IconPhoto class="size-3.5" />
                        </button>
                    </div>

                    <!-- List sub-type toggle -->
                    <div
                        v-if="block.type === 'list'"
                        class="flex items-center border-r border-border px-1"
                    >
                        <button
                            type="button"
                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md hover:bg-muted"
                            :class="
                                block.meta?.listType !== 'ordered'
                                    ? 'bg-muted text-foreground'
                                    : 'text-muted-foreground'
                            "
                            title="Gạch đầu dòng"
                            @click.stop="
                                block.meta = {
                                    ...block.meta,
                                    listType: 'unordered',
                                };
                                emitSync(true);
                            "
                        >
                            <IconList class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md hover:bg-muted"
                            :class="
                                block.meta?.listType === 'ordered'
                                    ? 'bg-muted text-foreground'
                                    : 'text-muted-foreground'
                            "
                            title="Đánh số thứ tự"
                            @click.stop="
                                block.meta = {
                                    ...block.meta,
                                    listType: 'ordered',
                                };
                                emitSync(true);
                            "
                        >
                            <IconListNumbers class="size-3.5" />
                        </button>
                    </div>

                    <!-- Block Movers & Actions -->
                    <div class="flex items-center pl-1">
                        <button
                            type="button"
                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30"
                            :disabled="index === 0"
                            title="Di chuyển lên"
                            @click.stop="moveBlock(index, 'up')"
                        >
                            <IconArrowUp class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30"
                            :disabled="index === blocks.length - 1"
                            title="Di chuyển xuống"
                            @click.stop="moveBlock(index, 'down')"
                        >
                            <IconArrowDown class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                            title="Nhân bản khối"
                            @click.stop="duplicateBlock(index)"
                        >
                            <IconCopy class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-7 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            title="Xóa khối"
                            @click.stop="removeBlock(index)"
                        >
                            <IconTrash class="size-3.5" />
                        </button>
                    </div>
                </div>

                <!-- BLOCK CONTENT CANVAS (True Gutenberg Style: Borderless, Seamless Typography) -->
                <div
                    class="block-inner relative rounded-lg transition-all"
                    :class="[
                        activeBlockId === block.id
                            ? 'ring-1.5 -mx-3 bg-[#2271b1]/[0.02] px-3 py-2 ring-[#2271b1]/40'
                            : '-mx-3 px-3 py-2 hover:bg-muted/30',
                    ]"
                >
                    <!-- 1. Heading 2 Block -->
                    <div v-if="block.type === 'h2'" class="py-1">
                        <textarea
                            :ref="
                                (el) => {
                                    if (el)
                                        blockInputRefs[block.id] =
                                            el as HTMLTextAreaElement;
                                }
                            "
                            v-model="block.content"
                            :readonly="readOnly"
                            rows="1"
                            placeholder="Tiêu đề chính (Heading 2)..."
                            class="w-full resize-none border-0 bg-transparent p-0 text-2xl leading-snug font-extrabold tracking-tight text-foreground placeholder:text-muted-foreground/30 focus:outline-none sm:text-3xl"
                            @input="
                                autoResizeTextarea($event);
                                emitSync(false);
                            "
                            @keydown="handleBlockKeydown($event, index, block)"
                        />
                    </div>

                    <!-- 2. Heading 3 Block -->
                    <div v-else-if="block.type === 'h3'" class="py-1">
                        <textarea
                            :ref="
                                (el) => {
                                    if (el)
                                        blockInputRefs[block.id] =
                                            el as HTMLTextAreaElement;
                                }
                            "
                            v-model="block.content"
                            :readonly="readOnly"
                            rows="1"
                            placeholder="Tiêu đề phụ (Heading 3)..."
                            class="w-full resize-none border-0 bg-transparent p-0 text-xl leading-snug font-bold text-foreground placeholder:text-muted-foreground/30 focus:outline-none"
                            @input="
                                autoResizeTextarea($event);
                                emitSync(false);
                            "
                            @keydown="handleBlockKeydown($event, index, block)"
                        />
                    </div>

                    <!-- 3. Quote Block (Classic WordPress Gutenberg Quote) -->
                    <div
                        v-else-if="block.type === 'quote'"
                        class="my-2 rounded-r-lg border-l-4 border-[#2271b1] bg-muted/20 py-2 pl-4"
                    >
                        <textarea
                            :ref="
                                (el) => {
                                    if (el)
                                        blockInputRefs[block.id] =
                                            el as HTMLTextAreaElement;
                                }
                            "
                            v-model="block.content"
                            :readonly="readOnly"
                            rows="2"
                            placeholder="Nhập câu trích dẫn hoặc thông điệp nổi bật..."
                            class="w-full resize-none border-0 bg-transparent p-0 text-base leading-relaxed font-medium text-foreground italic placeholder:text-muted-foreground/30 focus:outline-none"
                            @input="
                                autoResizeTextarea($event);
                                emitSync(false);
                            "
                            @keydown="handleBlockKeydown($event, index, block)"
                        />
                    </div>

                    <!-- 4. List Block -->
                    <div v-else-if="block.type === 'list'" class="py-1">
                        <div class="flex items-start gap-2">
                            <span
                                class="pt-0.5 font-mono text-base text-muted-foreground select-none"
                            >
                                {{
                                    block.meta?.listType === 'ordered'
                                        ? '1.'
                                        : '•'
                                }}
                            </span>
                            <textarea
                                :ref="
                                    (el) => {
                                        if (el)
                                            blockInputRefs[block.id] =
                                                el as HTMLTextAreaElement;
                                    }
                                "
                                v-model="block.content"
                                :readonly="readOnly"
                                rows="3"
                                placeholder="Mỗi dòng là một mục danh sách (nhấn Enter xuống dòng)..."
                                class="flex-1 resize-none border-0 bg-transparent p-0 text-base leading-relaxed font-normal text-foreground placeholder:text-muted-foreground/30 focus:outline-none"
                                @input="
                                    autoResizeTextarea($event);
                                    emitSync(false);
                                "
                                @keydown="
                                    handleBlockKeydown($event, index, block)
                                "
                            />
                        </div>
                    </div>

                    <!-- 5. Image Block (Gutenberg Media Block with Upload & Drag & Drop) -->
                    <div v-else-if="block.type === 'image'" class="my-4">
                        <!-- Hidden File Input for Direct Upload -->
                        <input
                            :ref="
                                (el) => {
                                    if (el)
                                        fileInputRefs[block.id] =
                                            el as HTMLInputElement;
                                }
                            "
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handleImageFileUpload(block, $event)"
                        />

                        <!-- State A: Uploading Spinner -->
                        <div
                            v-if="block.meta?.isUploading"
                            class="flex flex-col items-center justify-center space-y-3 rounded-2xl border-2 border-dashed border-[#2271b1] bg-[#2271b1]/5 p-8"
                        >
                            <IconLoader2
                                class="size-8 animate-spin text-[#2271b1]"
                            />
                            <div class="text-center">
                                <p class="text-xs font-bold text-foreground">
                                    Đang tải ảnh lên máy chủ...
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ block.meta?.uploadProgress || 0 }}% hoàn
                                    thành
                                </p>
                            </div>
                        </div>

                        <!-- State B: Image Loaded & Displayed -->
                        <div v-else-if="block.content" class="space-y-2">
                            <div
                                class="group/img relative max-w-2xl overflow-hidden rounded-2xl border border-border/80 bg-muted/40 shadow-xs"
                            >
                                <img
                                    :src="block.content"
                                    class="h-auto max-h-96 w-full object-cover"
                                />

                                <!-- Hover Action Overlay -->
                                <div
                                    class="absolute top-3 right-3 flex items-center gap-1.5 rounded-xl bg-black/70 p-1 opacity-0 backdrop-blur-sm transition-all group-hover/img:opacity-100"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-bold text-white transition hover:bg-white/20"
                                        @click="triggerFileInput(block.id)"
                                    >
                                        <IconUpload class="size-3" />
                                        <span>Tải ảnh khác</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-bold text-white transition hover:bg-white/20"
                                        @click="
                                            openMediaPickerForBlock(block.id)
                                        "
                                    >
                                        <IconFolder class="size-3" />
                                        <span>Thư viện Media</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex size-6 cursor-pointer items-center justify-center rounded-lg text-rose-300 transition hover:bg-rose-500 hover:text-white"
                                        title="Xóa ảnh"
                                        @click="
                                            block.content = '';
                                            emitSync(true);
                                        "
                                    >
                                        <IconTrash class="size-3" />
                                    </button>
                                </div>
                            </div>

                            <!-- Caption line -->
                            <input
                                type="text"
                                v-model="block.meta!.caption"
                                :readonly="readOnly"
                                placeholder="Thêm chú thích ảnh (Caption)..."
                                class="w-full border-0 bg-transparent p-0 text-center text-xs text-muted-foreground italic placeholder:text-muted-foreground/30 focus:outline-none"
                                @input="emitSync(false)"
                            />
                        </div>

                        <!-- State C: Gutenberg Image Placeholder (Upload / Media / URL) -->
                        <div
                            v-else
                            class="space-y-4 rounded-2xl border-2 border-dashed border-border bg-muted/15 p-8 text-center transition-all hover:border-[#2271b1]/60"
                            @dragover.prevent
                            @drop.prevent="handleImageDrop(block, $event)"
                        >
                            <div
                                class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-[#2271b1]/10 text-[#2271b1]"
                            >
                                <IconPhoto class="size-6 stroke-[1.5]" />
                            </div>

                            <div class="space-y-1">
                                <h4 class="text-sm font-bold text-foreground">
                                    Khối Hình Ảnh (Image Block)
                                </h4>
                                <p class="text-xs text-muted-foreground">
                                    Kéo thả tệp ảnh vào đây hoặc chọn một trong
                                    các phương thức:
                                </p>
                            </div>

                            <!-- Gutenberg Upload Action Buttons -->
                            <div
                                class="flex flex-wrap items-center justify-center gap-2 pt-1"
                            >
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-[#2271b1] px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:scale-105 hover:bg-[#135e96]"
                                    @click="triggerFileInput(block.id)"
                                >
                                    <IconUpload class="size-3.5" />
                                    <span>Tải Ảnh Từ Máy Tính</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border bg-background px-4 py-2 text-xs font-bold text-foreground transition hover:bg-muted"
                                    @click="openMediaPickerForBlock(block.id)"
                                >
                                    <IconFolder
                                        class="size-3.5 text-amber-600"
                                    />
                                    <span>Chọn Từ Thư Viện Media</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                    @click="
                                        block.meta = {
                                            ...block.meta,
                                            showUrlInput:
                                                !block.meta?.showUrlInput,
                                        }
                                    "
                                >
                                    <IconLink class="size-3.5" />
                                    <span>Chèn link URL</span>
                                </button>
                            </div>

                            <!-- Direct URL input if toggled -->
                            <div
                                v-if="block.meta?.showUrlInput"
                                class="mx-auto flex max-w-md items-center gap-1.5 pt-2"
                            >
                                <input
                                    type="text"
                                    v-model="block.content"
                                    placeholder="Dán đường dẫn URL hình ảnh (https://...)..."
                                    class="h-9 flex-1 rounded-xl border border-border bg-background px-3 text-xs text-foreground outline-none focus:border-[#2271b1]"
                                    @keyup.enter="emitSync(true)"
                                />
                                <button
                                    type="button"
                                    class="h-9 cursor-pointer rounded-xl bg-[#2271b1] px-3 text-xs font-bold text-white"
                                    @click="emitSync(true)"
                                >
                                    Áp dụng
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Call-to-Action (CTA Box) -->
                    <div
                        v-else-if="block.type === 'cta'"
                        class="my-4 space-y-3 rounded-xl border-2 border-[#2271b1]/30 bg-[#2271b1]/5 p-5"
                    >
                        <input
                            type="text"
                            v-model="block.content"
                            :readonly="readOnly"
                            placeholder="Tiêu đề kêu gọi hành động (VD: Trải Nghiệm King Coffee Ngay Hôm Nay)..."
                            class="w-full border-0 bg-transparent p-0 text-base font-extrabold text-foreground focus:outline-none"
                            @input="emitSync(false)"
                        />
                        <textarea
                            v-model="block.meta!.description"
                            :readonly="readOnly"
                            rows="2"
                            placeholder="Mô tả ngắn gọn ưu đãi hoặc thông điệp..."
                            class="w-full resize-none border-0 bg-transparent p-0 text-xs text-muted-foreground focus:outline-none"
                            @input="
                                autoResizeTextarea($event);
                                emitSync(false);
                            "
                        />
                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            <input
                                type="text"
                                v-model="block.meta!.buttonText"
                                :readonly="readOnly"
                                placeholder="Nhãn nút (VD: Xem Chi Tiết)"
                                class="h-8 w-40 rounded-lg border border-border bg-background px-2.5 text-xs font-bold text-foreground outline-none focus:border-[#2271b1]"
                                @input="emitSync(false)"
                            />
                            <input
                                type="text"
                                v-model="block.meta!.buttonUrl"
                                :readonly="readOnly"
                                placeholder="Link liên kết (https://...)"
                                class="h-8 min-w-[200px] flex-1 rounded-lg border border-border bg-background px-2.5 font-mono text-xs text-foreground outline-none focus:border-[#2271b1]"
                                @input="emitSync(false)"
                            />
                        </div>
                    </div>

                    <!-- 7. Paragraph (Default Clean Gutenberg Text) -->
                    <div v-else class="py-1">
                        <textarea
                            :ref="
                                (el) => {
                                    if (el)
                                        blockInputRefs[block.id] =
                                            el as HTMLTextAreaElement;
                                }
                            "
                            v-model="block.content"
                            :readonly="readOnly"
                            rows="1"
                            placeholder="Nhập nội dung hoặc gõ Enter để sang đoạn mới..."
                            class="w-full resize-none border-0 bg-transparent p-0 text-base leading-relaxed font-normal text-foreground placeholder:text-muted-foreground/30 focus:outline-none"
                            @input="
                                autoResizeTextarea($event);
                                emitSync(false);
                            "
                            @keydown="handleBlockKeydown($event, index, block)"
                        />
                    </div>
                </div>

                <!-- INLINE GUTENBERG '+' INSERTER (Appears between blocks on hover) -->
                <div
                    v-if="
                        !readOnly &&
                        (hoverBlockId === block.id ||
                            activeBlockId === block.id)
                    "
                    class="relative my-1 flex h-4 items-center justify-center opacity-60 transition hover:opacity-100"
                >
                    <div class="absolute inset-x-0 h-px bg-border/60"></div>
                    <button
                        type="button"
                        class="relative z-10 inline-flex size-5 cursor-pointer items-center justify-center rounded-full bg-[#2271b1] text-white shadow-xs transition hover:scale-110"
                        title="Chèn khối vào đây"
                        @click.stop="
                            showInserterMenuIndex =
                                showInserterMenuIndex === index ? null : index
                        "
                    >
                        <IconPlus class="size-3 stroke-[3]" />
                    </button>

                    <!-- Quick Inserter Popover Menu -->
                    <div
                        v-if="showInserterMenuIndex === index"
                        class="absolute top-6 z-40 flex animate-in items-center gap-1 rounded-xl border border-border bg-background p-1.5 shadow-lg backdrop-blur-md zoom-in-95 fade-in"
                    >
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted"
                            @click="insertBlock('paragraph', index)"
                        >
                            <IconPilcrow class="size-3 text-[#2271b1]" />
                            <span>Đoạn văn</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold text-foreground hover:bg-muted"
                            @click="insertBlock('h2', index)"
                        >
                            <span class="text-[11px] font-black text-[#2271b1]"
                                >H2</span
                            >
                            <span>Tiêu đề H2</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold text-foreground hover:bg-muted"
                            @click="insertBlock('h3', index)"
                        >
                            <span class="text-[11px] font-black text-[#2271b1]"
                                >H3</span
                            >
                            <span>Tiêu đề H3</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted"
                            @click="insertBlock('quote', index)"
                        >
                            <IconQuote class="size-3 text-[#2271b1]" />
                            <span>Trích dẫn</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted"
                            @click="insertBlock('list', index)"
                        >
                            <IconList class="size-3 text-[#2271b1]" />
                            <span>Danh sách</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted"
                            @click="insertBlock('image', index)"
                        >
                            <IconPhoto class="size-3 text-[#2271b1]" />
                            <span>Hình ảnh</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold text-[#2271b1] hover:bg-muted"
                            @click="insertBlock('cta', index)"
                        >
                            <span>Hộp CTA</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bottom Canvas Quick Inserter Bar -->
            <div
                v-if="!readOnly"
                class="flex flex-wrap items-center justify-center gap-1.5 pt-4 text-xs"
            >
                <span class="mr-1 text-[11px] text-muted-foreground"
                    >+ Thêm khối:</span
                >
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/80 bg-muted/30 px-2.5 py-1 text-xs font-medium text-foreground transition hover:bg-muted"
                    @click="insertBlock('paragraph')"
                >
                    <IconPilcrow class="size-3 text-[#2271b1]" />
                    <span>Đoạn văn</span>
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/80 bg-muted/30 px-2.5 py-1 text-xs font-bold text-foreground transition hover:bg-muted"
                    @click="insertBlock('h2')"
                >
                    <span class="text-[11px] font-black text-[#2271b1]"
                        >H2</span
                    >
                    <span>Tiêu đề H2</span>
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/80 bg-muted/30 px-2.5 py-1 text-xs font-bold text-foreground transition hover:bg-muted"
                    @click="insertBlock('h3')"
                >
                    <span class="text-[11px] font-black text-[#2271b1]"
                        >H3</span
                    >
                    <span>Tiêu đề H3</span>
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/80 bg-muted/30 px-2.5 py-1 text-xs font-medium text-foreground transition hover:bg-muted"
                    @click="insertBlock('quote')"
                >
                    <IconQuote class="size-3 text-[#2271b1]" />
                    <span>Trích dẫn</span>
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/80 bg-muted/30 px-2.5 py-1 text-xs font-medium text-foreground transition hover:bg-muted"
                    @click="insertBlock('list')"
                >
                    <IconList class="size-3 text-[#2271b1]" />
                    <span>Danh sách</span>
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/80 bg-muted/30 px-2.5 py-1 text-xs font-medium text-foreground transition hover:bg-muted"
                    @click="insertBlock('image')"
                >
                    <IconPhoto class="size-3 text-[#2271b1]" />
                    <span>Ảnh minh họa</span>
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border/80 bg-muted/30 px-2.5 py-1 text-xs font-bold text-[#2271b1] transition hover:bg-muted"
                    @click="insertBlock('cta')"
                >
                    <span>Hộp CTA</span>
                </button>
            </div>
        </div>

        <!-- Global Media Picker Dialog for Image Blocks -->
        <MediaPickerDialog
            ref="mediaPickerDialog"
            @select="handleMediaPicked"
        />
    </div>
</template>

<style scoped>
.gutenberg-canvas textarea {
    field-sizing: content;
}
</style>
