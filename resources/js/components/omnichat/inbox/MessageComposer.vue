<script setup lang="ts">
import {
    IconMoodSmile,
    IconPaperclip,
    IconSend,
    IconSticker,
    IconX,
} from '@tabler/icons-vue';
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    disabled?: boolean;
    channelProvider?: string;
};

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    channelProvider: '',
});

const emit = defineEmits<{
    (
        e: 'send',
        body: string,
        isInternal: boolean,
        attachment: File | null,
    ): void;
}>();

const messageBody = ref('');
const mode = ref<'reply' | 'internal'>('reply');
const attachment = ref<File | null>(null);
const imageInput = ref<HTMLInputElement | null>(null);
const imagePreviewUrl = ref<string | null>(null);
const showQuickIcons = ref(false);
const quickIcons = [
    '👍',
    '❤️',
    '😂',
    '😮',
    '😢',
    '😡',
    '🎉',
    '🙏',
    '👏',
    '🔥',
    '✅',
    '👋',
];

const handleSend = () => {
    if (!messageBody.value.trim() && !attachment.value) return;

    emit(
        'send',
        messageBody.value.trim(),
        mode.value === 'internal',
        attachment.value,
    );
    messageBody.value = '';
    clearImage();
};

const sendQuickIcon = (icon: string) => {
    if (props.disabled || mode.value === 'internal') return;

    emit('send', icon, false, null);
    showQuickIcons.value = false;
};

const selectImage = () => {
    if (mode.value === 'reply' && !props.disabled) imageInput.value?.click();
};

const handleAttachmentChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (!file) return;
    if (file.size === 0) {
        window.alert('Please select a non-empty file.');
        if (imageInput.value) imageInput.value.value = '';
        return;
    }

    const maxSize =
        props.channelProvider === 'facebook' ||
        props.channelProvider === 'website' ||
        !props.channelProvider
            ? 25 * 1024 * 1024
            : 1024 * 1024;
    if (file.size > maxSize) {
        window.alert(
            `Attachments must not exceed ${maxSize / 1024 / 1024} MB for this channel.`,
        );
        return;
    }

    if (imagePreviewUrl.value) URL.revokeObjectURL(imagePreviewUrl.value);
    attachment.value = file;
    imagePreviewUrl.value = file.type.startsWith('image/')
        ? URL.createObjectURL(file)
        : null;
};

const clearImage = () => {
    if (imagePreviewUrl.value) URL.revokeObjectURL(imagePreviewUrl.value);
    attachment.value = null;
    imagePreviewUrl.value = null;
    if (imageInput.value) imageInput.value.value = '';
};

const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSend();
    }
};
</script>

<template>
    <div
        :class="[
            'shrink-0 border-t border-border bg-card px-4 py-3',
            mode === 'internal'
                ? 'bg-yellow-50 dark:bg-yellow-950/30'
                : 'bg-card',
        ]"
    >
        <div class="mx-auto mb-2 flex max-w-3xl items-center justify-between">
            <div class="flex items-center gap-1" role="group">
                <Button
                    type="button"
                    size="sm"
                    :variant="mode === 'reply' ? 'secondary' : 'ghost'"
                    @click="mode = 'reply'"
                >
                    {{ $t('omnichat.composer.reply') }}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="mode === 'internal' ? 'secondary' : 'ghost'"
                    @click="mode = 'internal'"
                >
                    {{ $t('omnichat.composer.internal_note') }}
                </Button>
            </div>

            <div class="flex items-center gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :disabled="disabled"
                    aria-label="Quick icons"
                    @click="showQuickIcons = !showQuickIcons"
                >
                    <IconMoodSmile class="size-4" />
                </Button>
                <div
                    v-if="showQuickIcons"
                    class="absolute right-4 bottom-28 z-20 grid w-52 grid-cols-6 gap-1 rounded-lg border border-border bg-popover p-2 shadow-lg"
                >
                    <button
                        v-for="icon in quickIcons"
                        :key="icon"
                        type="button"
                        class="rounded p-1 text-xl transition hover:bg-muted"
                        :aria-label="`Send ${icon}`"
                        @click="sendQuickIcon(icon)"
                    >
                        {{ icon }}
                    </button>
                </div>
                <input
                    ref="imageInput"
                    type="file"
                    accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                    class="hidden"
                    @change="handleAttachmentChange"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :disabled="disabled || mode === 'internal'"
                    :aria-label="$t('omnichat.composer.attach_image')"
                    @click="selectImage"
                >
                    <IconPaperclip class="size-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :disabled="disabled"
                >
                    <IconSticker class="size-4" />
                </Button>
            </div>
        </div>

        <div
            class="relative mx-auto max-w-3xl rounded-xl border border-border bg-background shadow-sm focus-within:ring-2 focus-within:ring-primary/20"
        >
            <div
                v-if="attachment"
                class="flex items-start gap-2 border-b border-border p-2"
            >
                <img
                    v-if="imagePreviewUrl"
                    :src="imagePreviewUrl"
                    alt=""
                    class="h-20 max-w-32 rounded-lg object-cover"
                />
                <span
                    v-else
                    class="max-w-xs truncate px-2 py-5 text-sm text-muted-foreground"
                >
                    {{ attachment.name }}
                </span>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-7"
                    @click="clearImage"
                >
                    <IconX class="size-4" />
                </Button>
            </div>
            <Textarea
                v-model="messageBody"
                :placeholder="
                    mode === 'internal'
                        ? $t('omnichat.composer.internal_placeholder')
                        : $t('omnichat.composer.reply_placeholder')
                "
                :disabled="disabled"
                class="min-h-[72px] resize-none border-0 bg-transparent pr-12 shadow-none focus-visible:ring-0"
                @keydown="handleKeyDown"
            />
            <Button
                size="icon"
                class="absolute right-2 bottom-2 size-8"
                :disabled="disabled || (!messageBody.trim() && !attachment)"
                @click="handleSend"
            >
                <IconSend class="size-4" />
            </Button>
        </div>

        <p class="mx-auto mt-2 max-w-3xl text-[11px] text-muted-foreground">
            <kbd class="rounded border border-border bg-muted px-1.5 py-0.5"
                >Enter</kbd
            >
            {{ $t('omnichat.composer.send_hint') }}
            <kbd class="rounded border border-border bg-muted px-1.5 py-0.5"
                >Shift+Enter</kbd
            >
            {{ $t('omnichat.composer.newline_hint') }}
        </p>
    </div>
</template>
