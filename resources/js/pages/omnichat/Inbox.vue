<script setup lang="ts">
import { Head, router, useHttp, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import ConversationAssignmentController from '@/actions/App/Http/Controllers/App/Omnichat/ConversationAssignmentController';
import ConversationReadController from '@/actions/App/Http/Controllers/App/Omnichat/ConversationReadController';
import { store as storeMessage } from '@/actions/App/Http/Controllers/App/Omnichat/MessageController';
import ShopeeSyncController from '@/actions/App/Http/Controllers/App/Omnichat/ShopeeSyncController';
import ViewController from '@/actions/App/Http/Controllers/App/Omnichat/ViewController';
import ChannelSidebar from '@/components/omnichat/channels/ChannelSidebar.vue';
import ContactProfilePanel from '@/components/omnichat/contacts/ContactProfilePanel.vue';
import ConversationHeader from '@/components/omnichat/inbox/ConversationHeader.vue';
import ConversationList from '@/components/omnichat/inbox/ConversationList.vue';
import ConversationTagsBar from '@/components/omnichat/inbox/ConversationTagsBar.vue';
import MessageComposer from '@/components/omnichat/inbox/MessageComposer.vue';
import MessageTimeline from '@/components/omnichat/inbox/MessageTimeline.vue';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as omnichatInbox } from '@/routes/app/omnichat';
import type { User } from '@/types';
import type {
    ConversationFilters,
    OmnichatConversation,
    OmnichatMessage,
} from '@/types/omnichat';

type Props = {
    workspaceId: string;
    selectedChannelIds: string[];
    focusedChannelId: string | null;
    conversations: {
        data: Array<{
            id: string;
            contact: {
                display_name: string;
                avatar_url: string | null;
                phone?: string | null;
                notes?: string | null;
            };
            channel: {
                provider: string;
            };
            last_message_preview: string | null;
            last_message_at: string | null;
            unread_count: number;
            status: 'open' | 'pending' | 'resolved' | 'spam';
            assigned_user: {
                id: string;
                name: string;
                avatar_url: string | null;
            } | null;
            labels: Array<{
                id: string;
                name: string;
                color: string;
            }>;
        }>;
        meta: {
            hasNextPage: boolean;
        };
    };
    selectedConversation: OmnichatConversation | null;
    messages: {
        data: OmnichatMessage[];
        meta: {
            hasNextPage: boolean;
        };
    } | null;
    counts?: {
        unread: number;
        mentions: number;
    };
    filters: ConversationFilters & { tab?: 'all' | 'unread' | 'mentions' };
    filterOptions: {
        channels: Array<{
            id: string;
            provider: string;
            name: string;
        }>;
        assignees: Array<{
            id: string;
            name: string;
            avatar_url: string | null;
        }>;
        labels: Array<{
            id: string;
            name: string;
            color: string;
        }>;
    };
    connectedChannels: Array<{
        id: string;
        provider: string;
        name: string;
        avatar_url: string | null;
        status: 'connected' | 'disconnected' | 'token_expired';
        is_active: boolean;
    }>;
    permissions: {
        manageChannels: boolean;
        assignConversations: boolean;
        sendMessages: boolean;
        editContacts: boolean;
    };
};

const props = defineProps<Props>();
const page = usePage();
const user = computed(() => page.props.auth.user as User);

const showChannelPanel = ref(false);
const showContactPanel = ref(false);
const showContactSheet = ref(false);
const activeTab = ref<'all' | 'unread' | 'mentions'>(
    props.filters.tab || 'all',
);

const readConversation = useHttp<
    Record<string, any>,
    { success: boolean; unread_count: number }
>({});
const syncShopee = useHttp<
    Record<string, never>,
    { success: boolean; conversations: number }
>({});
const selectPageRequest = useHttp<
    { channel_ids: string[] },
    { selected_channel_ids: string[]; focused_channel_id: string }
>({ channel_ids: [] });
const assigningConversation = ref(false);
const assignConversation = useHttp<
    { user_id: string | null },
    {
        assigned_user: {
            id: string;
            name: string;
            avatar_url: string | null;
        } | null;
    }
>({ user_id: null });

const localMessages = ref(props.messages);
const localConversations = ref({
    data: [...props.conversations.data],
    meta: { ...props.conversations.meta },
});

watch(
    () => props.conversations,
    (newData) => {
        localConversations.value = {
            data: [...newData.data],
            meta: { ...newData.meta },
        };
    },
);

const desktopGridClass = computed(() => {
    if (showChannelPanel.value && showContactPanel.value) {
        return 'grid-cols-[210px_280px_minmax(0,1fr)_260px] 2xl:grid-cols-[220px_300px_minmax(0,1fr)_280px]';
    }

    if (showChannelPanel.value) {
        return 'grid-cols-[210px_280px_minmax(0,1fr)] 2xl:grid-cols-[220px_300px_minmax(0,1fr)]';
    }

    if (showContactPanel.value) {
        return 'grid-cols-[280px_minmax(0,1fr)_260px] 2xl:grid-cols-[300px_minmax(0,1fr)_280px]';
    }

    return 'grid-cols-[280px_minmax(0,1fr)] 2xl:grid-cols-[300px_minmax(0,1fr)]';
});

watch(
    () => props.messages,
    (messages) => {
        localMessages.value = messages
            ? { data: [...messages.data], meta: { ...messages.meta } }
            : null;
    },
);

const selectedConversationId = computed(
    () => props.selectedConversation?.id ?? null,
);

const isCurrentConversationUnread = computed(() => {
    if (!selectedConversationId.value) return false;
    const item = localConversations.value.data.find(
        (c) => c.id === selectedConversationId.value,
    );
    return (item?.unread_count ?? 0) > 0;
});

const handleSelectConversation = (conversationId: string) => {
    if (conversationId) {
        // 1. Mark unread_count = 0 locally immediately for instant feedback
        const item = localConversations.value.data.find(
            (c) => c.id === conversationId,
        );
        if (item) {
            item.unread_count = 0;
        }

        void readConversation
            .post(ConversationReadController.url(conversationId), {
                unread: false,
            })
            .catch(() => undefined);
    }

    router.get(
        omnichatInbox.url(),
        {
            conversation: conversationId,
            tab: activeTab.value !== 'all' ? activeTab.value : undefined,
            search: props.filters.search || undefined,
            status: props.filters.status || undefined,
            label: props.filters.label || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['selectedConversation', 'messages', 'counts'],
        },
    );
};

const handleToggleRead = async () => {
    if (!props.selectedConversation) return;

    const convId = props.selectedConversation.id;
    const item = localConversations.value.data.find((c) => c.id === convId);
    const currentlyUnread = (item?.unread_count ?? 0) > 0;
    const targetUnread = !currentlyUnread;

    // Immediately toggle locally
    if (item) {
        item.unread_count = targetUnread ? 1 : 0;
    }

    if (targetUnread) {
        toast.info('Đã đánh dấu là chưa đọc');
    } else {
        toast.success('Đã đánh dấu là đã đọc');
    }

    try {
        await readConversation.post(ConversationReadController.url(convId), {
            unread: targetUnread,
        });
        router.reload({ only: ['counts'] });
    } catch {
        if (item) {
            item.unread_count = currentlyUnread ? 1 : 0;
        }
    }
};

const handleTabUpdate = (tab: 'all' | 'unread' | 'mentions') => {
    activeTab.value = tab;
    router.get(
        omnichatInbox.url(),
        {
            ...props.filters,
            tab: tab !== 'all' ? tab : undefined,
            conversation: selectedConversationId.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['conversations', 'counts'],
        },
    );
};

const handleSearchUpdate = (value: string) => {
    router.get(
        omnichatInbox.url(),
        {
            ...props.filters,
            search: value || undefined,
            tab: activeTab.value !== 'all' ? activeTab.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['conversations'],
        },
    );
};

const handleSelectPage = async (channelId: string) => {
    if (selectPageRequest.processing) return;

    const selectedChannelIds = props.selectedChannelIds.includes(channelId)
        ? props.selectedChannelIds.filter((id) => id !== channelId)
        : [...props.selectedChannelIds, channelId];

    if (selectedChannelIds.length === 0) return;

    selectPageRequest.channel_ids = selectedChannelIds;
    await selectPageRequest.put(ViewController.url());
    router.get(
        omnichatInbox.url(),
        {
            search: props.filters.search,
            status: props.filters.status,
            label: props.filters.label,
            tab: activeTab.value !== 'all' ? activeTab.value : undefined,
        },
        { preserveState: false, preserveScroll: true, replace: true },
    );
};

const handleRefresh = async () => {
    const shopeeChannels = props.connectedChannels.filter(
        (channel) => channel.provider === 'shopee',
    );
    for (const channel of shopeeChannels) {
        await syncShopee.post(ShopeeSyncController.url(channel.id));
    }

    router.reload({
        only: ['conversations', 'selectedConversation', 'messages', 'counts'],
    });
};

const handleAssignConversation = async (userId: string | null) => {
    if (
        !props.selectedConversation ||
        !props.permissions.assignConversations ||
        assigningConversation.value
    )
        return;

    assigningConversation.value = true;
    assignConversation.user_id = userId;
    assignConversation
        .put(
            ConversationAssignmentController.update.url(
                props.selectedConversation.id,
            ),
        )
        .then(() => {
            toast.success('Đã phân công cuộc trò chuyện thành công');
            router.reload({ only: ['conversations', 'selectedConversation'] });
        })
        .catch(() => toast.error('Không thể phân công cuộc trò chuyện'))
        .finally(() => {
            assigningConversation.value = false;
        });
};

type MessageCreatedPayload = { message: OmnichatMessage };

const sendMessage = useHttp<
    {
        body: string;
        client_id: string;
        mode: 'reply' | 'internal';
        attachment: File | null;
    },
    MessageCreatedPayload
>({ body: '', client_id: '', mode: 'reply', attachment: null });

const appendMessage = (message: OmnichatMessage) => {
    if (
        !localMessages.value ||
        message.conversation_id !== selectedConversationId.value
    )
        return;
    if (
        localMessages.value.data.some(
            (item) =>
                item.id === message.id ||
                (message.client_id && item.client_id === message.client_id),
        )
    )
        return;
    localMessages.value.data.push(message);
};

const onIncomingMessage = (message: OmnichatMessage) => {
    appendMessage(message);

    // Push newest message conversation to the very top (index 0) of the list
    const index = localConversations.value.data.findIndex(
        (c) => c.id === message.conversation_id,
    );
    if (index !== -1) {
        const conv = localConversations.value.data[index];
        conv.last_message_preview = message.body || `[${message.type}]`;
        conv.last_message_at = message.sent_at || message.created_at;

        if (
            message.conversation_id !== selectedConversationId.value &&
            message.direction === 'inbound'
        ) {
            conv.unread_count = (conv.unread_count || 0) + 1;
        }

        localConversations.value.data.splice(index, 1);
        localConversations.value.data.unshift(conv);
    } else {
        router.reload({ only: ['conversations', 'counts'] });
    }
};

for (const channelId of props.selectedChannelIds) {
    useEcho<MessageCreatedPayload>(
        `omnichat.channel.${channelId}`,
        '.omnichat.message.created',
        ({ message }) => {
            onIncomingMessage(message);
        },
    );
}

const handleSendMessage = async (
    body: string,
    isInternal: boolean,
    attachment: File | null,
) => {
    if (
        !props.selectedConversation ||
        !localMessages.value ||
        sendMessage.processing
    )
        return;

    const clientId = crypto.randomUUID();
    const timestamp = new Date().toISOString();
    const optimisticId = `pending-${clientId}`;
    localMessages.value.data.push({
        id: optimisticId,
        workspace_id: props.selectedConversation.workspace_id,
        conversation_id: props.selectedConversation.id,
        sender_contact_id: null,
        sender_user_id: user.value.id,
        external_id: null,
        client_id: clientId,
        direction: isInternal ? 'internal' : 'outbound',
        type: attachment?.type.startsWith('image/')
            ? 'image'
            : attachment?.type.startsWith('video/')
              ? 'video'
              : attachment
                ? 'document'
                : 'text',
        body,
        status: 'pending',
        sender: {
            id: user.value.id,
            name: user.value.name,
            avatar_url: user.value.photo_url,
        },
        attachments: attachment
            ? [
                  {
                      id: clientId,
                      type: attachment.type.startsWith('image/')
                          ? 'image'
                          : attachment.type.startsWith('video/')
                            ? 'video'
                            : 'document',
                      url: attachment.type.startsWith('image/')
                          ? URL.createObjectURL(attachment)
                          : '#',
                      original_name: attachment.name,
                      mime_type: attachment.type,
                      size: attachment.size,
                  },
              ]
            : [],
        sent_at: timestamp,
        delivered_at: null,
        read_at: null,
        failed_at: null,
        error_message: null,
        created_at: timestamp,
    });

    sendMessage.body = body;
    sendMessage.client_id = clientId;
    sendMessage.mode = isInternal ? 'internal' : 'reply';
    sendMessage.attachment = attachment;

    try {
        const payload = await sendMessage.post(
            storeMessage.url(props.selectedConversation.id),
        );
        localMessages.value.data = localMessages.value.data.filter(
            (message) => message.id !== optimisticId,
        );
        appendMessage(payload.message);

        // Update local conversation preview and push to top
        const index = localConversations.value.data.findIndex(
            (c) => c.id === props.selectedConversation?.id,
        );
        if (index !== -1) {
            const conv = localConversations.value.data[index];
            conv.last_message_preview = body || `[${payload.message.type}]`;
            conv.last_message_at = timestamp;
            localConversations.value.data.splice(index, 1);
            localConversations.value.data.unshift(conv);
        }
    } catch {
        const pendingMessage = localMessages.value.data.find(
            (message) => message.id === optimisticId,
        );
        if (pendingMessage) {
            pendingMessage.status = 'failed';
            pendingMessage.failed_at = new Date().toISOString();
            pendingMessage.error_message = 'Không thể gửi tin nhắn.';
        }
    }
};

const toggleContactPanel = () => {
    showContactPanel.value = !showContactPanel.value;
};

const toggleChannelPanel = () => {
    showChannelPanel.value = !showChannelPanel.value;
};

const openContactSheet = () => {
    showContactSheet.value = true;
};
</script>

<template>
    <Head :title="$t('omnichat.inbox.page_title')" />

    <AppLayout full-width>
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <!-- Desktop: channel filters, conversations, chat, customer profile -->
            <div
                class="hidden min-h-0 flex-1 overflow-hidden lg:grid"
                :class="desktopGridClass"
            >
                <ChannelSidebar
                    v-if="showChannelPanel"
                    class="flex"
                    :channels="connectedChannels"
                    :selected-channel-ids="selectedChannelIds"
                    :can-manage-channels="permissions.manageChannels"
                    @select-page="handleSelectPage"
                />

                <ConversationList
                    class="min-h-0 min-w-0"
                    :conversations="localConversations"
                    :channels="connectedChannels"
                    :selected-channel-ids="selectedChannelIds"
                    :selected-conversation-id="selectedConversationId"
                    :search="filters.search"
                    :active-tab="activeTab"
                    :current-user-id="user.id"
                    :counts="props.counts || { unread: 0, mentions: 0 }"
                    :can-manage-pages="permissions.manageChannels"
                    :available-tags="filterOptions.labels"
                    @select="handleSelectConversation"
                    @select-page="handleSelectPage"
                    @update:search="handleSearchUpdate"
                    @update:tab="handleTabUpdate"
                    @refresh="handleRefresh"
                />

                <div class="flex min-h-0 min-w-0 flex-col bg-background">
                    <ConversationHeader
                        v-if="selectedConversation"
                        :conversation="selectedConversation"
                        :show-channel-panel="showChannelPanel"
                        :show-channel-toggle="true"
                        :show-contact-panel="showContactPanel"
                        :assignees="filterOptions.assignees"
                        :can-assign="permissions.assignConversations"
                        :current-user-id="user.id"
                        :is-unread="isCurrentConversationUnread"
                        @toggle-channel-panel="toggleChannelPanel"
                        @toggle-contact-panel="toggleContactPanel"
                        @toggle-read="handleToggleRead"
                        @assign="handleAssignConversation"
                    />

                    <ConversationTagsBar
                        v-if="selectedConversation"
                        :conversation="selectedConversation"
                        :available-tags="filterOptions.labels"
                    />

                    <MessageTimeline
                        :messages="localMessages"
                        class="min-h-0 flex-1"
                    />

                    <MessageComposer
                        v-if="selectedConversation && permissions.sendMessages"
                        :channel-provider="
                            selectedConversation.channel.provider
                        "
                        @send="handleSendMessage"
                    />
                </div>

                <ContactProfilePanel
                    v-if="showContactPanel && selectedConversation"
                    class="flex min-h-0 min-w-0"
                    :conversation="selectedConversation"
                    :available-tags="filterOptions.labels"
                />
            </div>

            <!-- Tablet: Two-panel layout with contact sheet -->
            <div
                class="hidden min-h-0 flex-1 grid-cols-[300px_minmax(0,1fr)] overflow-hidden md:grid lg:hidden"
            >
                <ConversationList
                    class="min-h-0 min-w-0"
                    :conversations="localConversations"
                    :channels="connectedChannels"
                    :selected-channel-ids="selectedChannelIds"
                    :selected-conversation-id="selectedConversationId"
                    :search="filters.search"
                    :active-tab="activeTab"
                    :current-user-id="user.id"
                    :counts="props.counts || { unread: 0, mentions: 0 }"
                    :can-manage-pages="permissions.manageChannels"
                    :available-tags="filterOptions.labels"
                    @select="handleSelectConversation"
                    @select-page="handleSelectPage"
                    @update:search="handleSearchUpdate"
                    @update:tab="handleTabUpdate"
                    @refresh="handleRefresh"
                />

                <div class="flex min-h-0 min-w-0 flex-col bg-background">
                    <ConversationHeader
                        v-if="selectedConversation"
                        :conversation="selectedConversation"
                        :show-contact-panel="false"
                        :assignees="filterOptions.assignees"
                        :can-assign="permissions.assignConversations"
                        :current-user-id="user.id"
                        :is-unread="isCurrentConversationUnread"
                        @toggle-channel-panel="toggleChannelPanel"
                        @toggle-contact-panel="openContactSheet"
                        @toggle-read="handleToggleRead"
                        @assign="handleAssignConversation"
                    />

                    <ConversationTagsBar
                        v-if="selectedConversation"
                        :conversation="selectedConversation"
                        :available-tags="filterOptions.labels"
                    />

                    <MessageTimeline
                        :messages="localMessages"
                        class="min-h-0 flex-1"
                    />

                    <MessageComposer
                        v-if="selectedConversation && permissions.sendMessages"
                        :channel-provider="
                            selectedConversation.channel.provider
                        "
                        @send="handleSendMessage"
                    />
                </div>

                <!-- Contact Sheet for Tablet -->
                <Sheet v-model:open="showContactSheet">
                    <SheetContent side="right" class="w-full sm:max-w-md">
                        <ContactProfilePanel
                            v-if="selectedConversation"
                            :conversation="selectedConversation"
                            :available-tags="filterOptions.labels"
                        />
                    </SheetContent>
                </Sheet>
            </div>

            <!-- Mobile: Single panel at a time -->
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden md:hidden">
                <ConversationList
                    v-if="!selectedConversation"
                    :conversations="localConversations"
                    :channels="connectedChannels"
                    :selected-channel-ids="selectedChannelIds"
                    :selected-conversation-id="selectedConversationId"
                    :search="filters.search"
                    :active-tab="activeTab"
                    :current-user-id="user.id"
                    :counts="props.counts || { unread: 0, mentions: 0 }"
                    :can-manage-pages="permissions.manageChannels"
                    :available-tags="filterOptions.labels"
                    @select="handleSelectConversation"
                    @select-page="handleSelectPage"
                    @update:search="handleSearchUpdate"
                    @update:tab="handleTabUpdate"
                    @refresh="handleRefresh"
                />

                <div v-else class="flex min-h-0 flex-1 flex-col bg-background">
                    <ConversationHeader
                        :conversation="selectedConversation"
                        :show-contact-panel="false"
                        :assignees="filterOptions.assignees"
                        :can-assign="permissions.assignConversations"
                        :current-user-id="user.id"
                        :show-back-button="true"
                        :is-unread="isCurrentConversationUnread"
                        @toggle-contact-panel="openContactSheet"
                        @toggle-read="handleToggleRead"
                        @assign="handleAssignConversation"
                        @back="handleSelectConversation('')"
                    />

                    <ConversationTagsBar
                        v-if="selectedConversation"
                        :conversation="selectedConversation"
                        :available-tags="filterOptions.labels"
                    />

                    <MessageTimeline
                        :messages="localMessages"
                        class="min-h-0 flex-1"
                    />

                    <MessageComposer
                        v-if="permissions.sendMessages"
                        :channel-provider="
                            selectedConversation.channel.provider
                        "
                        @send="handleSendMessage"
                    />
                </div>

                <!-- Contact Sheet for Mobile -->
                <Sheet v-model:open="showContactSheet">
                    <SheetContent side="right" class="w-full">
                        <ContactProfilePanel
                            v-if="selectedConversation"
                            :conversation="selectedConversation"
                            :available-tags="filterOptions.labels"
                        />
                    </SheetContent>
                </Sheet>
            </div>
        </div>
    </AppLayout>
</template>
