<script setup lang="ts">
import {
    IconAlignCenter,
    IconAlignJustified,
    IconAlignLeft,
    IconAlignRight,
    IconArrowBackUp,
    IconArrowForwardUp,
    IconBlockquote,
    IconBold,
    IconChevronDown,
    IconClearFormatting,
    IconCode,
    IconEye,
    IconLink,
    IconList,
    IconListNumbers,
    IconMaximize,
    IconMinimize,
    IconPhoto,
    IconStrikethrough,
    IconUnderline,
} from '@tabler/icons-vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

import MediaPickerDialog from '@/components/posts/MediaPickerDialog.vue';

interface PickedImage {
    url: string;
    mime_type: string;
    original_filename?: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string;
        readOnly?: boolean;
    }>(),
    { readOnly: false },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();

const editor = ref<HTMLDivElement | null>(null);
const editorWrapper = ref<HTMLDivElement | null>(null);
const code = ref(props.modelValue || '');
const mode = ref<'visual' | 'code'>('visual');
const isFullscreen = ref(false);
const isUpdatingInternally = ref(false);
const mediaPickerDialog = ref<InstanceType<typeof MediaPickerDialog> | null>(
    null,
);
let savedSelection: Range | null = null;
const selectedImage = ref<HTMLImageElement | null>(null);
const resizeHandle = ref({ left: 0, top: 0, visible: false });
let resizeStartX = 0;
let resizeStartWidth = 0;

const plainText = computed(() => {
    const document = new DOMParser().parseFromString(
        code.value || '',
        'text/html',
    );

    return document.body.textContent?.replace(/\s+/g, ' ').trim() || '';
});

const wordCount = computed(() =>
    plainText.value ? plainText.value.split(' ').length : 0,
);
const characterCount = computed(() => plainText.value.length);

const syncVisualEditor = async (): Promise<void> => {
    await nextTick();

    if (editor.value && editor.value.innerHTML !== code.value) {
        editor.value.innerHTML = code.value;
    }
};

const emitContent = (value: string): void => {
    isUpdatingInternally.value = true;
    code.value = value;
    emit('update:modelValue', value);

    nextTick(() => {
        isUpdatingInternally.value = false;
    });
};

const onVisualInput = (): void => {
    if (!editor.value) {
        emitContent('');

        return;
    }

    const content = editor.value.cloneNode(true) as HTMLDivElement;
    content.querySelectorAll('.wp-image-selected').forEach((image) => {
        image.classList.remove('wp-image-selected');
    });

    emitContent(content.innerHTML);
};

const updateResizeHandle = (): void => {
    if (!selectedImage.value || !editorWrapper.value) {
        resizeHandle.value.visible = false;

        return;
    }

    const imageRect = selectedImage.value.getBoundingClientRect();
    const wrapperRect = editorWrapper.value.getBoundingClientRect();

    resizeHandle.value = {
        left: imageRect.right - wrapperRect.left - 7,
        top: imageRect.bottom - wrapperRect.top - 7,
        visible: true,
    };
};

const selectImage = (event: MouseEvent): void => {
    const target = event.target;

    if (!(target instanceof HTMLImageElement)) {
        selectedImage.value?.classList.remove('wp-image-selected');
        selectedImage.value = null;
        updateResizeHandle();

        return;
    }

    selectedImage.value?.classList.remove('wp-image-selected');
    selectedImage.value = target;
    selectedImage.value.classList.add('wp-image-selected');
    updateResizeHandle();
};

const stopImageResize = (): void => {
    document.removeEventListener('pointermove', resizeImage);
    document.removeEventListener('pointerup', stopImageResize);
    onVisualInput();
};

const resizeImage = (event: PointerEvent): void => {
    if (!selectedImage.value || !editor.value) {
        return;
    }

    const maximumWidth = Math.max(120, editor.value.clientWidth - 56);
    const nextWidth = Math.min(
        maximumWidth,
        Math.max(80, resizeStartWidth + event.clientX - resizeStartX),
    );

    selectedImage.value.style.width = `${Math.round(nextWidth)}px`;
    selectedImage.value.style.height = 'auto';
    selectedImage.value.style.maxWidth = '100%';
    updateResizeHandle();
};

const startImageResize = (event: PointerEvent): void => {
    if (!selectedImage.value || props.readOnly) {
        return;
    }

    resizeStartX = event.clientX;
    resizeStartWidth = selectedImage.value.getBoundingClientRect().width;
    document.addEventListener('pointermove', resizeImage);
    document.addEventListener('pointerup', stopImageResize, { once: true });
};

const onCodeInput = (event: Event): void => {
    emitContent((event.target as HTMLTextAreaElement).value);
};

const focusEditor = (): void => {
    editor.value?.focus();
};

const runCommand = (command: string, value?: string): void => {
    if (props.readOnly) {
        return;
    }

    focusEditor();
    document.execCommand(command, false, value);
    onVisualInput();
};

const changeBlock = (event: Event): void => {
    const value = (event.target as HTMLSelectElement).value;

    if (value) {
        runCommand('formatBlock', value);
    }
};

const changeFontSize = (event: Event): void => {
    const value = (event.target as HTMLSelectElement).value;

    if (value) {
        runCommand('fontSize', value);
    }
};

const createLink = (): void => {
    const url = window.prompt('Nhập đường dẫn liên kết (URL):', 'https://');

    if (url && url !== 'https://') {
        runCommand('createLink', url);
    }
};

const rememberSelection = (): void => {
    const selection = window.getSelection();
    const range = selection?.rangeCount ? selection.getRangeAt(0) : null;

    if (range && editor.value?.contains(range.commonAncestorContainer)) {
        savedSelection = range.cloneRange();
    }
};

const openMediaPicker = (): void => {
    if (props.readOnly) {
        return;
    }

    rememberSelection();
    mediaPickerDialog.value?.open();
};

const insertPickedImages = (media: PickedImage[]): void => {
    const images = media.filter((item) => item.mime_type.startsWith('image/'));

    if (images.length === 0) {
        return;
    }

    focusEditor();

    if (savedSelection) {
        const selection = window.getSelection();
        selection?.removeAllRanges();
        selection?.addRange(savedSelection);
    }

    for (const image of images) {
        const figure = document.createElement('figure');
        const imageElement = document.createElement('img');

        figure.className = 'wp-block-image size-large';
        imageElement.src = image.url;
        imageElement.alt = image.original_filename || '';
        figure.append(imageElement);

        document.execCommand('insertHTML', false, figure.outerHTML);
    }

    savedSelection = null;
    onVisualInput();
};

const toggleMode = async (nextMode: 'visual' | 'code'): Promise<void> => {
    if (mode.value === nextMode) {
        return;
    }

    if (mode.value === 'visual') {
        code.value = editor.value?.innerHTML || code.value;
    }

    mode.value = nextMode;

    if (nextMode === 'visual') {
        await syncVisualEditor();
    }
};

watch(
    () => props.modelValue,
    async (value) => {
        if (isUpdatingInternally.value || value === code.value) {
            return;
        }

        code.value = value || '';

        if (mode.value === 'visual') {
            await syncVisualEditor();
        }
    },
);

onMounted(syncVisualEditor);
onUnmounted(() => {
    document.removeEventListener('pointermove', resizeImage);
    document.removeEventListener('pointerup', stopImageResize);
});
</script>

<template>
    <div class="contents">
        <div
            class="overflow-hidden border border-[#c3c4c7] bg-white text-[#1d2327] shadow-sm dark:border-border dark:bg-card dark:text-foreground"
            :class="
                isFullscreen
                    ? 'fixed inset-3 z-50 flex flex-col shadow-2xl'
                    : 'rounded-sm'
            "
        >
            <div
                class="flex min-h-10 items-end justify-between border-b border-[#c3c4c7] bg-[#f6f7f7] px-2 dark:border-border dark:bg-muted"
            >
                <div
                    class="flex items-center gap-1 self-center text-xs font-semibold text-[#50575e] dark:text-muted-foreground"
                >
                    <span class="hidden sm:inline"
                        >Trình soạn thảo WordPress</span
                    >
                </div>
                <div class="flex self-stretch">
                    <button
                        type="button"
                        class="border-x border-transparent px-3 text-xs font-medium transition"
                        :class="
                            mode === 'visual'
                                ? '-mb-px border-[#c3c4c7] bg-white text-[#1d2327] dark:border-border dark:bg-card dark:text-foreground'
                                : 'text-[#50575e] hover:text-[#135e96] dark:text-muted-foreground'
                        "
                        @click="toggleMode('visual')"
                    >
                        <IconEye class="mr-1 inline size-3.5" />
                        Trực quan
                    </button>
                    <button
                        type="button"
                        class="border-x border-transparent px-3 text-xs font-medium transition"
                        :class="
                            mode === 'code'
                                ? '-mb-px border-[#c3c4c7] bg-white text-[#1d2327] dark:border-border dark:bg-card dark:text-foreground'
                                : 'text-[#50575e] hover:text-[#135e96] dark:text-muted-foreground'
                        "
                        @click="toggleMode('code')"
                    >
                        <IconCode class="mr-1 inline size-3.5" />
                        Mã
                    </button>
                </div>
            </div>

            <div
                v-if="mode === 'visual'"
                class="flex min-h-11 flex-wrap items-center gap-0.5 border-b border-[#dcdcde] bg-[#f6f7f7] px-2 py-1.5 dark:border-border dark:bg-muted"
            >
                <label class="relative">
                    <select
                        class="h-8 appearance-none border border-[#c3c4c7] bg-white py-0 pr-7 pl-2 text-xs dark:border-border dark:bg-card"
                        :disabled="readOnly"
                        title="Định dạng"
                        @change="changeBlock"
                    >
                        <option value="">Định dạng</option>
                        <option value="p">Đoạn văn</option>
                        <option value="h2">Tiêu đề 2</option>
                        <option value="h3">Tiêu đề 3</option>
                        <option value="h4">Tiêu đề 4</option>
                        <option value="pre">Định dạng sẵn</option>
                    </select>
                    <IconChevronDown
                        class="pointer-events-none absolute top-2 right-1.5 size-3.5"
                    />
                </label>

                <button
                    type="button"
                    class="wp-tool"
                    title="Đậm"
                    @mousedown.prevent="runCommand('bold')"
                >
                    <IconBold />
                </button>
                <button
                    type="button"
                    class="wp-tool"
                    title="Gạch chân"
                    @mousedown.prevent="runCommand('underline')"
                >
                    <IconUnderline />
                </button>
                <button
                    type="button"
                    class="wp-tool"
                    title="Gạch ngang"
                    @mousedown.prevent="runCommand('strikeThrough')"
                >
                    <IconStrikethrough />
                </button>
                <span class="mx-1 h-6 w-px bg-[#c3c4c7] dark:bg-border" />
                <button
                    type="button"
                    class="wp-tool"
                    title="Danh sách dấu đầu dòng"
                    @mousedown.prevent="runCommand('insertUnorderedList')"
                >
                    <IconList />
                </button>
                <button
                    type="button"
                    class="wp-tool"
                    title="Danh sách đánh số"
                    @mousedown.prevent="runCommand('insertOrderedList')"
                >
                    <IconListNumbers />
                </button>
                <button
                    type="button"
                    class="wp-tool"
                    title="Trích dẫn"
                    @mousedown.prevent="runCommand('formatBlock', 'blockquote')"
                >
                    <IconBlockquote />
                </button>
                <span class="mx-1 h-6 w-px bg-[#c3c4c7] dark:bg-border" />
                <button
                    type="button"
                    class="wp-tool"
                    title="Căn trái"
                    @mousedown.prevent="runCommand('justifyLeft')"
                >
                    <IconAlignLeft />
                </button>
                <button
                    type="button"
                    class="wp-tool"
                    title="Căn giữa"
                    @mousedown.prevent="runCommand('justifyCenter')"
                >
                    <IconAlignCenter />
                </button>
                <button
                    type="button"
                    class="wp-tool"
                    title="Căn phải"
                    @mousedown.prevent="runCommand('justifyRight')"
                >
                    <IconAlignRight />
                </button>
                <button
                    type="button"
                    class="wp-tool"
                    title="Căn đều"
                    @mousedown.prevent="runCommand('justifyFull')"
                >
                    <IconAlignJustified />
                </button>
                <span class="mx-1 h-6 w-px bg-[#c3c4c7] dark:bg-border" />
                <button
                    type="button"
                    class="wp-tool"
                    title="Chèn liên kết"
                    @mousedown.prevent="createLink"
                >
                    <IconLink />
                </button>
                <button
                    type="button"
                    class="wp-tool"
                    title="Thêm media"
                    @mousedown.prevent="openMediaPicker"
                >
                    <IconPhoto />
                </button>
                <button
                    type="button"
                    class="wp-tool"
                    title="Đường phân cách"
                    @mousedown.prevent="runCommand('insertHorizontalRule')"
                >
                    <span class="text-base">—</span>
                </button>
                <button
                    type="button"
                    class="wp-tool"
                    title="Xóa định dạng"
                    @mousedown.prevent="runCommand('removeFormat')"
                >
                    <IconClearFormatting />
                </button>
                <button
                    type="button"
                    class="wp-tool"
                    title="Hoàn tác"
                    @mousedown.prevent="runCommand('undo')"
                >
                    <IconArrowBackUp />
                </button>
                <button
                    type="button"
                    class="wp-tool"
                    title="Làm lại"
                    @mousedown.prevent="runCommand('redo')"
                >
                    <IconArrowForwardUp />
                </button>

                <label class="relative ml-1">
                    <select
                        class="h-8 appearance-none border border-[#c3c4c7] bg-white py-0 pr-7 pl-2 text-xs dark:border-border dark:bg-card"
                        :disabled="readOnly"
                        title="Cỡ chữ"
                        @change="changeFontSize"
                    >
                        <option value="">Cỡ chữ</option>
                        <option value="2">10pt</option>
                        <option value="3">12pt</option>
                        <option value="4">14pt</option>
                        <option value="5">18pt</option>
                        <option value="6">24pt</option>
                    </select>
                    <IconChevronDown
                        class="pointer-events-none absolute top-2 right-1.5 size-3.5"
                    />
                </label>

                <button
                    type="button"
                    class="wp-tool ml-auto"
                    :title="isFullscreen ? 'Thu nhỏ' : 'Toàn màn hình'"
                    @click="isFullscreen = !isFullscreen"
                >
                    <IconMinimize v-if="isFullscreen" />
                    <IconMaximize v-else />
                </button>
            </div>

            <div
                v-if="mode === 'visual'"
                ref="editorWrapper"
                class="relative min-h-[520px] flex-1 overflow-hidden"
            >
                <div
                    ref="editor"
                    class="prose min-h-[520px] max-w-none overflow-y-auto bg-white px-7 py-6 text-[15px] leading-7 prose-slate outline-none focus:ring-1 focus:ring-[#2271b1] focus:ring-inset dark:bg-card dark:prose-invert"
                    :class="readOnly ? 'cursor-default' : 'cursor-text'"
                    :contenteditable="!readOnly"
                    role="textbox"
                    aria-label="Nội dung bài viết WordPress"
                    data-placeholder="Bắt đầu viết nội dung bài viết..."
                    @click="selectImage"
                    @input="onVisualInput"
                    @scroll="updateResizeHandle"
                />

                <button
                    v-if="resizeHandle.visible && !readOnly"
                    type="button"
                    class="absolute z-10 size-4 cursor-nwse-resize touch-none rounded-sm border-2 border-white bg-[#2271b1] shadow-md"
                    :style="{
                        left: `${resizeHandle.left}px`,
                        top: `${resizeHandle.top}px`,
                    }"
                    title="Kéo để thay đổi kích thước ảnh"
                    aria-label="Thay đổi kích thước ảnh"
                    @pointerdown.stop.prevent="startImageResize"
                />
            </div>

            <textarea
                v-else
                :value="code"
                :readonly="readOnly"
                class="min-h-[520px] flex-1 resize-y bg-[#fff] p-5 font-mono text-sm leading-6 text-[#1d2327] outline-none focus:ring-1 focus:ring-[#2271b1] focus:ring-inset dark:bg-card dark:text-foreground"
                spellcheck="false"
                aria-label="Mã HTML bài viết WordPress"
                @input="onCodeInput"
            />

            <div
                class="flex min-h-7 items-center justify-between border-t border-[#c3c4c7] bg-[#f6f7f7] px-3 text-[11px] text-[#50575e] dark:border-border dark:bg-muted dark:text-muted-foreground"
            >
                <span>Số từ: {{ wordCount }}</span>
                <span
                    >{{ characterCount.toLocaleString('vi-VN') }} ký tự · HTML
                    WordPress</span
                >
            </div>
        </div>

        <MediaPickerDialog
            ref="mediaPickerDialog"
            @select="insertPickedImages"
        />
    </div>
</template>

<style scoped>
.wp-tool {
    display: inline-flex;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    color: #50575e;
    transition:
        background-color 120ms ease,
        color 120ms ease;
}

.wp-tool:hover {
    background: #dcdcde;
    color: #1d2327;
}

.wp-tool:disabled {
    cursor: not-allowed;
    opacity: 0.45;
}

.wp-tool :deep(svg) {
    width: 1rem;
    height: 1rem;
    stroke-width: 2;
}

:deep(.wp-image-selected) {
    outline: 2px solid #2271b1;
    outline-offset: 2px;
}

[contenteditable='true']:empty::before {
    color: #8c8f94;
    content: attr(data-placeholder);
    pointer-events: none;
}
</style>
