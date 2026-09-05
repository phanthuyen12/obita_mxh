<script setup lang="ts">
import {
    IconCheck,
    IconChecks,
    IconClock,
    IconExclamationCircle,
} from '@tabler/icons-vue';
import { computed } from 'vue';

import { Avatar } from '@/components/ui/avatar';
import date from '@/date';
import type { OmnichatMessage } from '@/types/omnichat';

type Props = {
    message: OmnichatMessage;
    showSender?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showSender: true,
});

const isInbound = computed(() => props.message.direction === 'inbound');
const isInternal = computed(() => props.message.direction === 'internal');

type BodySegment = {
    isPhone: boolean;
    text: string;
};

const phonePattern =
    /(?:\+?84|0)[\s.-]?(?:\d[\s.-]?){8,10}|\+[1-9](?:[\s.-]?\d){7,14}/gu;

const bodySegments = computed<BodySegment[]>(() => {
    const body = props.message.body ?? '';
    const segments: BodySegment[] = [];
    let lastIndex = 0;

    for (const match of body.matchAll(phonePattern)) {
        const index = match.index;

        if (index > lastIndex) {
            segments.push({
                isPhone: false,
                text: body.slice(lastIndex, index),
            });
        }

        segments.push({ isPhone: true, text: match[0] });
        lastIndex = index + match[0].length;
    }

    if (lastIndex < body.length) {
        segments.push({ isPhone: false, text: body.slice(lastIndex) });
    }

    return segments.length > 0 ? segments : [{ isPhone: false, text: body }];
});

const phoneHref = (phone: string): string =>
    `tel:${phone.replace(/[^\d+]/g, '')}`;

const statusIcon = computed(() => {
    if (props.message.status === 'failed') return IconExclamationCircle;
    if (props.message.status === 'pending') return IconClock;
    if (props.message.status === 'read') return IconChecks;
    if (props.message.status === 'delivered') return IconChecks;
    if (props.message.status === 'sent') return IconCheck;
    return null;
});

const statusColor = computed(() => {
    if (props.message.status === 'failed') return 'text-destructive';
    if (props.message.status === 'read') return 'text-blue-500';
    return 'text-muted-foreground';
});

const formatTime = (value: string | null): string => {
    if (!value) return '';
    return date.formatTime(value);
};
</script>

<template>
    <div
        :class="[
            'flex gap-2.5',
            isInbound ? 'justify-start' : 'justify-end',
            isInternal && 'mx-auto max-w-2xl',
        ]"
    >
        <div v-if="isInbound" class="w-8 shrink-0" aria-hidden="true">
            <Avatar
                v-if="showSender && message.sender"
                :src="message.sender.avatar_url"
                :name="message.sender.name"
                class="size-8"
                fallback-class="bg-violet-100 text-violet-700 text-xs font-bold"
            />
        </div>

        <div
            :class="[
                'flex max-w-[78%] flex-col gap-1',
                isInbound ? 'items-start' : 'items-end',
            ]"
        >
            <div
                v-if="showSender && message.sender"
                class="px-1 text-xs font-medium text-foreground/70"
            >
                {{ message.sender.name }}
            </div>

            <div
                :class="[
                    'rounded-2xl px-4 py-2.5 shadow-sm',
                    isInternal
                        ? 'border border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/30'
                        : isInbound
                          ? 'rounded-tl-md border border-border bg-card'
                          : 'rounded-tr-md bg-amber-100 text-amber-950 dark:bg-amber-900/50 dark:text-amber-50',
                ]"
            >
                <div
                    v-if="isInternal"
                    class="mb-1 text-xs font-semibold text-yellow-700 dark:text-yellow-300"
                >
                    {{ $t('omnichat.message.internal_note') }}
                </div>

                <!-- Ticket Support Header Badge if message is a Website Ticket -->
                <div
                    v-if="
                        message.metadata?.ticket_type || message.metadata?.title
                    "
                    class="-mx-2 -mt-1 mb-2.5 rounded-xl border border-amber-500/20 bg-amber-500/10 p-2.5 dark:border-amber-400/20 dark:bg-amber-400/10"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-amber-500/20 px-2 py-0.5 text-[11px] font-bold tracking-wider text-amber-700 uppercase dark:text-amber-300"
                        >
                            🎫
                            {{
                                message.metadata.ticket_type || 'Ticket Hỗ Trợ'
                            }}
                        </span>
                        <span
                            class="text-[10px] font-medium text-muted-foreground"
                            >Website Ticket</span
                        >
                    </div>
                    <div
                        v-if="message.metadata.title"
                        class="mt-1 text-xs font-semibold text-foreground"
                    >
                        {{ message.metadata.title }}
                    </div>
                </div>

                <div
                    v-if="message.attachments?.length"
                    :class="[
                        'mb-2 grid max-w-sm gap-2',
                        message.attachments.length > 1
                            ? 'grid-cols-2 sm:grid-cols-3'
                            : 'grid-cols-1',
                    ]"
                >
                    <template
                        v-for="attachment in message.attachments"
                        :key="attachment.id"
                    >
                        <template v-if="attachment.url">
                            <a
                                v-if="
                                    attachment.type === 'image' ||
                                    attachment.type === 'sticker'
                                "
                                :href="attachment.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="group relative block aspect-square overflow-hidden rounded-lg border border-border/60 bg-muted/30"
                            >
                                <img
                                    :src="attachment.url"
                                    :alt="
                                        attachment.file_name ||
                                        attachment.original_name ||
                                        ''
                                    "
                                    class="size-full object-cover transition-transform duration-200 group-hover:scale-105"
                                    loading="lazy"
                                />
                            </a>
                            <video
                                v-else-if="attachment.type === 'video'"
                                :src="attachment.url"
                                :aria-label="
                                    attachment.file_name ||
                                    attachment.original_name ||
                                    'Video attachment'
                                "
                                class="max-h-80 w-full rounded-lg object-contain"
                                controls
                                preload="metadata"
                                @click.stop
                            />
                            <audio
                                v-else-if="attachment.type === 'audio'"
                                :src="attachment.url"
                                :aria-label="
                                    attachment.original_name || 'Voice message'
                                "
                                class="w-full min-w-64"
                                controls
                                preload="metadata"
                                @click.stop
                            />
                            <a
                                v-else
                                :href="attachment.url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <span
                                    class="block rounded-md bg-muted px-3 py-2 text-sm"
                                >
                                    {{
                                        attachment.original_name ||
                                        attachment.type
                                    }}
                                </span>
                            </a>
                        </template>
                        <span
                            v-else
                            class="block rounded-md bg-muted px-3 py-2 text-sm"
                        >
                            {{ attachment.original_name || attachment.type }}
                        </span>
                    </template>
                </div>
                <p
                    v-if="message.body"
                    class="text-sm break-words whitespace-pre-wrap"
                >
                    <template
                        v-for="(segment, index) in bodySegments"
                        :key="`${index}-${segment.text}`"
                    >
                        <a
                            v-if="segment.isPhone"
                            :href="phoneHref(segment.text)"
                            class="rounded bg-emerald-100 px-1 font-bold text-emerald-800 underline decoration-emerald-500 underline-offset-2 dark:bg-emerald-900/60 dark:text-emerald-200"
                            title="Gọi số điện thoại này"
                            @click.stop
                            >{{ segment.text }}</a
                        >
                        <template v-else>{{ segment.text }}</template>
                    </template>
                </p>
            </div>

            <div
                class="flex items-center gap-1 px-1 text-xs text-muted-foreground"
            >
                <span>{{ formatTime(message.created_at) }}</span>
                <component
                    :is="statusIcon"
                    v-if="!isInbound && statusIcon"
                    :class="['size-3', statusColor]"
                />
                <span
                    v-if="message.status === 'failed'"
                    class="text-destructive"
                >
                    {{ $t('omnichat.message.failed') }}
                </span>
            </div>
        </div>

        <div
            v-if="!isInbound && !isInternal"
            class="w-8 shrink-0"
            aria-hidden="true"
        >
            <Avatar
                v-if="showSender && message.sender"
                :src="message.sender.avatar_url"
                :name="message.sender.name"
                class="size-8"
                fallback-class="bg-blue-100 text-blue-700 text-xs font-bold"
            />
        </div>
    </div>
</template>
