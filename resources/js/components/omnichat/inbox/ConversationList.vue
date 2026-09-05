<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    IconAdjustmentsHorizontal,
    IconAt,
    IconCheck,
    IconChevronDown,
    IconInbox,
    IconMail,
    IconMessages,
    IconRefresh,
    IconSearch,
    IconSettings,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import ConversationListItem from '@/components/omnichat/inbox/ConversationListItem.vue';
import ProviderIcon from '@/components/omnichat/shared/ProviderIcon.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { accounts } from '@/routes/app';
import type { ConversationListItem as ConversationItem } from '@/types/omnichat';

type Props = {
    conversations: {
        data: ConversationItem[];
        meta: {
            hasNextPage: boolean;
        };
    };
    selectedConversationId: string | null;
    selectedChannelIds: string[];
    search: string;
    activeTab?: 'all' | 'unread' | 'mentions';
    currentUserId?: string;
    counts?: {
        unread: number;
        mentions: number;
    };
    canManagePages?: boolean;
    availableTags: Array<{ id: string; name: string; color: string }>;
    channels: Array<{
        id: string;
        provider: string;
        name: string;
        status: 'connected' | 'disconnected' | 'token_expired';
        is_active: boolean;
    }>;
};

const props = withDefaults(defineProps<Props>(), {
    activeTab: 'all',
    currentUserId: '',
    counts: () => ({ unread: 0, mentions: 0 }),
});

const emit = defineEmits<{
    (e: 'select', conversationId: string): void;
    (e: 'update:search', value: string): void;
    (e: 'update:tab', tab: 'all' | 'unread' | 'mentions'): void;
    (e: 'refresh'): void;
    (e: 'select-page', channelId: string): void;
}>();

const currentTab = ref<'all' | 'unread' | 'mentions'>(props.activeTab);
const searchQuery = ref(props.search);

watch(
    () => props.activeTab,
    (newTab) => {
        if (newTab) currentTab.value = newTab;
    },
);

const handleSearchInput = (event: Event) => {
    const value = (event.target as HTMLInputElement).value;
    searchQuery.value = value;
    emit('update:search', value);
};

const setTab = (tab: 'all' | 'unread' | 'mentions') => {
    currentTab.value = tab;
    emit('update:tab', tab);
};

const localUnreadCount = computed(() => {
    const inList = props.conversations.data.filter(
        (c) => (c.unread_count ?? 0) > 0,
    ).length;
    return Math.max(props.counts.unread, inList);
});

const localMentionsCount = computed(() => {
    const inList = props.currentUserId
        ? props.conversations.data.filter(
              (c) => c.assigned_user?.id === props.currentUserId,
          ).length
        : 0;
    return Math.max(props.counts.mentions, inList);
});

const filteredAndSortedConversations = computed(() => {
    let list = [...props.conversations.data];

    // Filter by tab
    if (currentTab.value === 'unread') {
        list = list.filter((c) => (c.unread_count ?? 0) > 0);
    } else if (currentTab.value === 'mentions') {
        list = list.filter(
            (c) =>
                c.assigned_user?.id === props.currentUserId ||
                (c.last_message_preview &&
                    c.last_message_preview.includes('@')),
        );
    }

    // Filter by search query
    const query = searchQuery.value.trim().toLocaleLowerCase();
    if (query) {
        list = list.filter((conversation) =>
            [
                conversation.contact.display_name,
                conversation.contact.phone,
                conversation.last_message_preview,
            ]
                .filter(Boolean)
                .some((value) => value?.toLocaleLowerCase().includes(query)),
        );
    }

    // Sort by latest message first (push newest to top)
    return list.sort((a, b) => {
        const timeA = a.last_message_at
            ? new Date(a.last_message_at).getTime()
            : 0;
        const timeB = b.last_message_at
            ? new Date(b.last_message_at).getTime()
            : 0;
        return timeB - timeA;
    });
});
</script>

<template>
    <div
        class="flex h-full min-h-0 flex-col overflow-hidden border-r border-border bg-card"
    >
        <!-- Header -->
        <div class="shrink-0 border-b border-border bg-background p-3">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">
                        {{ $t('omnichat.inbox.title') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t('omnichat.inbox.conversation_count', {
                                count: conversations.data.length,
                            })
                        }}
                    </p>
                </div>

                <Popover>
                    <PopoverTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-8 shrink-0 gap-1.5 px-2.5 text-xs"
                        >
                            {{
                                $t('omnichat.inbox.pages_count', {
                                    enabled: selectedChannelIds.length,
                                    total: channels.length,
                                })
                            }}
                            <IconChevronDown class="size-3.5" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent align="end" class="w-72 p-0">
                        <div class="border-b border-border px-3 py-3">
                            <p class="text-sm font-semibold">
                                {{ $t('omnichat.inbox.pages_receiving') }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ $t('omnichat.inbox.select_pages') }}
                            </p>
                        </div>

                        <div class="max-h-72 space-y-1 overflow-y-auto p-2">
                            <button
                                v-for="channel in channels"
                                :key="channel.id"
                                class="flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left hover:bg-muted"
                                @click="emit('select-page', channel.id)"
                            >
                                <span
                                    class="flex size-4 shrink-0 items-center justify-center rounded border"
                                    :class="
                                        selectedChannelIds.includes(channel.id)
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border'
                                    "
                                >
                                    <IconCheck
                                        v-if="
                                            selectedChannelIds.includes(
                                                channel.id,
                                            )
                                        "
                                        class="size-3"
                                    />
                                </span>
                                <ProviderIcon
                                    :provider="channel.provider"
                                    class="size-4 shrink-0"
                                />
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-xs font-medium"
                                        >{{ channel.name }}</span
                                    >
                                    <span
                                        class="block truncate text-[11px] text-muted-foreground capitalize"
                                    >
                                        {{ channel.provider }} ·
                                        {{ channel.status }}
                                    </span>
                                </span>
                            </button>

                            <p
                                v-if="channels.length === 0"
                                class="px-2 py-4 text-center text-xs text-muted-foreground"
                            >
                                {{ $t('omnichat.inbox.no_pages') }}
                            </p>
                        </div>

                        <div
                            v-if="canManagePages"
                            class="border-t border-border p-2"
                        >
                            <Button
                                as-child
                                variant="ghost"
                                size="sm"
                                class="w-full justify-start text-xs"
                            >
                                <Link :href="accounts.url()">
                                    <IconSettings class="size-3.5" />
                                    {{ $t('omnichat.channel.manage') }}
                                </Link>
                            </Button>
                        </div>
                    </PopoverContent>
                </Popover>
            </div>

            <!-- Search -->
            <div class="relative">
                <IconSearch
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground/60"
                />
                <Input
                    :value="searchQuery"
                    :placeholder="$t('omnichat.inbox.search_placeholder')"
                    class="h-10 rounded-xl border-border bg-card pr-10 pl-9 shadow-none"
                    @input="handleSearchInput"
                />
                <IconAdjustmentsHorizontal
                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
            </div>

            <!-- Tabs: Tất cả / Chưa đọc / Đề cập -->
            <div
                class="mt-3 flex items-center gap-1.5 overflow-x-auto pb-0.5 text-xs"
            >
                <!-- Tab Tất cả -->
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 font-bold transition-colors"
                    :class="[
                        currentTab === 'all'
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                    @click="setTab('all')"
                >
                    <IconMessages class="size-3.5" />
                    {{ $t('omnichat.inbox.all') }}
                    <span
                        class="rounded-full px-1.5 text-[10px]"
                        :class="
                            currentTab === 'all'
                                ? 'bg-primary-foreground/20'
                                : 'bg-muted-foreground/20'
                        "
                    >
                        {{ conversations.data.length }}
                    </span>
                </button>

                <!-- Tab Chưa đọc -->
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 font-bold transition-colors"
                    :class="[
                        currentTab === 'unread'
                            ? 'bg-blue-600 text-white shadow-xs dark:bg-blue-500'
                            : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                    @click="setTab('unread')"
                >
                    <IconMail class="size-3.5" />
                    Chưa đọc
                    <span
                        v-if="localUnreadCount > 0"
                        class="rounded-full bg-red-500 px-1.5 text-[10px] font-black text-white"
                    >
                        {{ localUnreadCount }}
                    </span>
                </button>

                <!-- Tab Đề cập / Tôi phụ trách -->
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 font-bold transition-colors"
                    :class="[
                        currentTab === 'mentions'
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'bg-muted/70 text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                    @click="setTab('mentions')"
                >
                    <IconAt class="size-3.5" />
                    Đề cập
                    <span
                        v-if="localMentionsCount > 0"
                        class="rounded-full px-1.5 text-[10px]"
                        :class="
                            currentTab === 'mentions'
                                ? 'bg-primary-foreground/20'
                                : 'bg-muted-foreground/20'
                        "
                    >
                        {{ localMentionsCount }}
                    </span>
                </button>

                <Button
                    variant="ghost"
                    size="icon"
                    class="ml-auto size-7 shrink-0"
                    title="Làm mới danh sách"
                    @click="emit('refresh')"
                >
                    <IconRefresh class="size-3.5" />
                </Button>
            </div>
        </div>

        <!-- Conversation List (Sorted with newest message on top) -->
        <ScrollArea class="min-h-0 flex-1">
            <div class="space-y-1 p-2">
                <ConversationListItem
                    v-for="conversation in filteredAndSortedConversations"
                    :key="conversation.id"
                    :conversation="conversation"
                    :available-tags="availableTags"
                    :active="conversation.id === selectedConversationId"
                    @click="emit('select', conversation.id)"
                />
            </div>

            <div
                v-if="filteredAndSortedConversations.length === 0"
                class="flex flex-col items-center justify-center p-8 text-center"
            >
                <IconInbox class="mb-3 size-12 text-muted-foreground/50" />
                <p class="text-sm font-medium text-foreground">
                    {{
                        currentTab === 'unread'
                            ? 'Không có tin nhắn chưa đọc'
                            : currentTab === 'mentions'
                              ? 'Không có hội thoại nào được đề cập'
                              : $t('omnichat.inbox.empty.title')
                    }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{
                        currentTab === 'unread'
                            ? 'Tất cả tin nhắn đã được xử lý hoặc đọc.'
                            : $t('omnichat.inbox.empty.description')
                    }}
                </p>
            </div>
        </ScrollArea>
    </div>
</template>
