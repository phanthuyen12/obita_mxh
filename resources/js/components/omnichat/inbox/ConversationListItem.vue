<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { IconMail, IconPhone, IconTag } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import ConversationTagController from '@/actions/App/Http/Controllers/App/Omnichat/ConversationTagController';
import LabelBadge from '@/components/labels/LabelBadge.vue';
import ProviderIcon from '@/components/omnichat/shared/ProviderIcon.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import date from '@/date';
import type { ConversationListItem } from '@/types/omnichat';

type Tag = { id: string; name: string; color: string };

type Props = {
    conversation: ConversationListItem;
    availableTags: Tag[];
    active?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    active: false,
});

const selectedTagIds = ref(props.conversation.labels.map((tag) => tag.id));
const tagRequest = useHttp<{ tag_ids: string[] }, { tags: Tag[] }>({
    tag_ids: [],
});

watch(
    () => props.conversation.labels,
    (labels) => {
        selectedTagIds.value = labels.map((tag) => tag.id);
    },
);

const formatTime = (value: string | null): string => {
    if (!value) return '';
    return date.diffForHumans(value);
};

const hasUnread = computed(() => (props.conversation.unread_count ?? 0) > 0);

const toggleTag = async (tagId: string) => {
    if (tagRequest.processing) return;

    selectedTagIds.value = selectedTagIds.value.includes(tagId)
        ? selectedTagIds.value.filter((id) => id !== tagId)
        : [...selectedTagIds.value, tagId];

    tagRequest.tag_ids = selectedTagIds.value;
    await tagRequest.put(ConversationTagController.url(props.conversation.id));
    router.reload({
        only: ['conversations', 'selectedConversation', 'filterOptions'],
    });
};
</script>

<template>
    <div
        :class="[
            'group relative flex cursor-pointer items-start gap-3 border-l-2 px-3 py-3 transition-colors hover:bg-muted/70',
            active
                ? 'border-primary bg-primary/8 font-medium'
                : hasUnread
                  ? 'border-blue-500 bg-blue-500/5 dark:bg-blue-950/20'
                  : 'border-transparent bg-card',
        ]"
    >
        <div class="relative shrink-0">
            <Avatar
                :src="conversation.contact.avatar_url"
                :name="conversation.contact.display_name"
                class="size-10"
                fallback-class="bg-violet-100 text-violet-700 font-bold"
            />
            <!-- Unread Dot on Avatar -->
            <span
                v-if="hasUnread"
                class="absolute -top-0.5 -right-0.5 size-3 rounded-full border-2 border-background bg-blue-600 dark:bg-blue-500"
            />
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
                <div class="flex min-w-0 items-center gap-2">
                    <h3
                        :class="[
                            'truncate text-sm',
                            hasUnread
                                ? 'font-black text-foreground'
                                : 'font-medium text-foreground/90',
                        ]"
                    >
                        {{ conversation.contact.display_name }}
                    </h3>
                    <ProviderIcon
                        :provider="conversation.channel.provider"
                        class="size-3.5 shrink-0 text-muted-foreground"
                    />
                </div>
                <span
                    :class="[
                        'shrink-0 text-xs',
                        hasUnread
                            ? 'font-bold text-blue-600 dark:text-blue-400'
                            : 'text-muted-foreground',
                    ]"
                >
                    {{ formatTime(conversation.last_message_at) }}
                </span>
            </div>

            <p
                :class="[
                    'mt-1 line-clamp-1 text-xs',
                    hasUnread
                        ? 'font-bold text-foreground'
                        : 'font-normal text-muted-foreground',
                ]"
            >
                {{ conversation.last_message_preview || '—' }}
            </p>

            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                <!-- Unread Mailbox Badge (Visible when unread, completely hidden when read) -->
                <span
                    v-if="hasUnread"
                    class="inline-flex items-center gap-1 rounded-full bg-blue-600 px-2 py-0.5 text-[10px] font-bold text-white shadow-xs dark:bg-blue-500"
                >
                    <IconMail class="size-3" />
                    {{
                        conversation.unread_count > 1
                            ? `${conversation.unread_count} chưa đọc`
                            : 'Chưa đọc'
                    }}
                </span>

                <Badge
                    v-if="conversation.contact.phone"
                    variant="outline"
                    class="h-5 gap-1 px-1.5 text-[10px] text-emerald-700 dark:text-emerald-300"
                    :title="conversation.contact.phone"
                >
                    <IconPhone class="size-3" /> Có SĐT
                </Badge>

                <LabelBadge
                    v-for="label in conversation.labels.slice(0, 2)"
                    :key="label.id"
                    :label="label"
                    class="text-xs"
                />

                <span
                    v-if="conversation.labels.length > 2"
                    class="text-xs font-bold text-foreground/60"
                >
                    +{{ conversation.labels.length - 2 }}
                </span>

                <Popover>
                    <PopoverTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="size-6 rounded-full"
                            title="Gắn thẻ hội thoại"
                            @click.stop
                        >
                            <IconTag class="size-3.5" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent align="start" class="w-64 p-2" @click.stop>
                        <p class="px-2 py-1 text-xs font-semibold">
                            Gắn thẻ hội thoại
                        </p>
                        <div class="mt-1 max-h-56 space-y-1 overflow-y-auto">
                            <button
                                v-for="tag in availableTags"
                                :key="tag.id"
                                type="button"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-xs hover:bg-muted"
                                @click.stop="toggleTag(tag.id)"
                            >
                                <span
                                    class="size-3 rounded-full"
                                    :style="{ backgroundColor: tag.color }"
                                />
                                <span class="flex-1 truncate">{{
                                    tag.name
                                }}</span>
                                <span
                                    v-if="selectedTagIds.includes(tag.id)"
                                    class="text-primary"
                                >
                                    ✓
                                </span>
                            </button>
                            <p
                                v-if="availableTags.length === 0"
                                class="px-2 py-3 text-xs text-muted-foreground"
                            >
                                Chưa có thẻ. Tạo thẻ tại trang Khách hàng tiềm
                                năng.
                            </p>
                        </div>
                    </PopoverContent>
                </Popover>

                <Avatar
                    v-if="conversation.assigned_user"
                    :src="conversation.assigned_user.avatar_url"
                    :name="conversation.assigned_user.name"
                    class="ml-auto size-5"
                    fallback-class="bg-blue-100 text-blue-700 text-[10px] font-bold"
                />
            </div>
        </div>
    </div>
</template>
