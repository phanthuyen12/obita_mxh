<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    IconArrowRight,
    IconChevronDown,
    IconMessageCircle,
    IconPlugConnected,
    IconSparkles,
} from '@tabler/icons-vue';
import { computed } from 'vue';

import MessageBubble from '@/components/omnichat/inbox/MessageBubble.vue';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import date from '@/date';
import { accounts } from '@/routes/app';
import type { OmnichatMessage } from '@/types/omnichat';

type Props = {
    messages: {
        data: OmnichatMessage[];
        meta: { hasNextPage: boolean };
    } | null;
};

const props = defineProps<Props>();

const groupedMessages = computed(() => {
    if (!props.messages?.data) return [];

    const groups: Array<{ date: string; messages: OmnichatMessage[] }> = [];
    let currentGroup: { date: string; messages: OmnichatMessage[] } | null =
        null;

    props.messages.data.forEach((message) => {
        const messageDate = date.formatDate(message.created_at);

        if (!currentGroup || currentGroup.date !== messageDate) {
            currentGroup = { date: messageDate, messages: [] };
            groups.push(currentGroup);
        }

        currentGroup.messages.push(message);
    });

    return groups;
});

const shouldShowSender = (
    message: OmnichatMessage,
    index: number,
    messages: OmnichatMessage[],
): boolean => {
    if (index === 0) return true;

    const previousMessage = messages[index - 1];
    if (!previousMessage) return true;
    if (message.direction !== previousMessage.direction) return true;
    if (message.sender_contact_id !== previousMessage.sender_contact_id)
        return true;
    if (message.sender_user_id !== previousMessage.sender_user_id) return true;

    return (
        new Date(message.created_at).getTime() -
            new Date(previousMessage.created_at).getTime() >
        5 * 60 * 1000
    );
};
</script>

<template>
    <ScrollArea class="min-h-0 flex-1 bg-muted/20">
        <div
            v-if="!messages"
            class="relative flex min-h-[calc(100vh-8rem)] items-center justify-center overflow-hidden px-6 py-12"
        >
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_42%,color-mix(in_oklab,var(--primary)_10%,transparent),transparent_34%)]"
            />
            <div
                class="relative mx-auto flex max-w-lg flex-col items-center text-center"
            >
                <div class="relative mb-7">
                    <div
                        class="absolute inset-0 scale-150 rounded-full bg-primary/10 blur-2xl"
                    />
                    <div
                        class="relative flex size-24 items-center justify-center rounded-3xl border border-primary/15 bg-background shadow-xl shadow-primary/10"
                    >
                        <IconMessageCircle
                            class="size-11 text-primary"
                            :stroke="1.5"
                        />
                        <span
                            class="absolute -top-2 -right-2 flex size-8 items-center justify-center rounded-full border-4 border-background bg-emerald-500 text-white shadow-sm"
                        >
                            <IconSparkles class="size-3.5" />
                        </span>
                    </div>
                </div>

                <div
                    class="mb-3 inline-flex items-center gap-2 rounded-full border border-border bg-background/80 px-3 py-1 text-xs font-medium text-muted-foreground shadow-sm backdrop-blur"
                >
                    <span class="size-1.5 rounded-full bg-emerald-500" />
                    {{ $t('omnichat.inbox.empty_workspace.badge') }}
                </div>
                <h2
                    class="text-2xl font-semibold tracking-tight text-balance text-foreground sm:text-3xl"
                >
                    {{ $t('omnichat.inbox.empty_workspace.title') }}
                </h2>
                <p
                    class="mt-3 max-w-md text-sm leading-6 text-pretty text-muted-foreground sm:text-base"
                >
                    {{ $t('omnichat.inbox.empty_workspace.description') }}
                </p>

                <div class="mt-7 flex flex-col items-center gap-3 sm:flex-row">
                    <Button
                        as-child
                        class="h-10 gap-2 rounded-xl px-5 shadow-sm"
                    >
                        <Link :href="accounts()" view-transition>
                            <IconPlugConnected class="size-4" />
                            {{ $t('omnichat.inbox.empty_workspace.connect') }}
                            <IconArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <p class="text-xs text-muted-foreground">
                        {{ $t('omnichat.inbox.empty_workspace.hint') }}
                    </p>
                </div>
            </div>
        </div>

        <div
            v-else-if="messages.data.length === 0"
            class="flex min-h-[calc(100vh-12rem)] items-center justify-center px-6 py-12"
        >
            <div class="max-w-sm text-center">
                <div
                    class="mx-auto flex size-16 items-center justify-center rounded-2xl border border-border bg-background shadow-sm"
                >
                    <IconMessageCircle
                        class="size-8 text-primary"
                        :stroke="1.5"
                    />
                </div>
                <h3 class="mt-5 text-lg font-semibold">
                    {{ $t('omnichat.inbox.empty_conversation.title') }}
                </h3>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    {{ $t('omnichat.inbox.empty_conversation.description') }}
                </p>
            </div>
        </div>

        <div v-else class="mx-auto w-full max-w-3xl space-y-5 px-5 py-6">
            <div v-if="messages.meta.hasNextPage" class="flex justify-center">
                <Button variant="outline" size="sm">
                    <IconChevronDown class="size-4" />
                    {{ $t('omnichat.inbox.load_older') }}
                </Button>
            </div>

            <div
                v-for="group in groupedMessages"
                :key="group.date"
                class="space-y-3"
            >
                <div class="flex items-center justify-center">
                    <div
                        class="rounded-full border border-border bg-background px-3 py-1 text-[11px] font-medium text-muted-foreground shadow-sm"
                    >
                        {{ group.date }}
                    </div>
                </div>

                <MessageBubble
                    v-for="(message, index) in group.messages"
                    :key="message.id"
                    :message="message"
                    :show-sender="
                        shouldShowSender(message, index, group.messages)
                    "
                />
            </div>
        </div>
    </ScrollArea>
</template>
