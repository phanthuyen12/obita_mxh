<script setup lang="ts">
import {
    IconArrowLeft,
    IconCheck,
    IconChevronDown,
    IconDotsVertical,
    IconLayoutSidebar,
    IconLayoutSidebarRight,
    IconMail,
    IconMailOpened,
    IconPhone,
    IconStar,
    IconUserCheck,
} from '@tabler/icons-vue';

import ProviderIcon from '@/components/omnichat/shared/ProviderIcon.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type Props = {
    conversation: {
        contact: {
            display_name: string;
            avatar_url: string | null;
            phone?: string | null;
        };
        channel: {
            provider: string;
            name: string;
            status: string;
        };
        status: string;
        unread_count?: number;
        assigned_user_id: string | null;
        assigned_user?: {
            id: string;
            name: string;
            avatar_url: string | null;
        } | null;
        labels?: Array<{
            id: string;
            name: string;
            color: string;
        }>;
    } | null;
    assignees?: Array<{ id: string; name: string; avatar_url: string | null }>;
    canAssign?: boolean;
    currentUserId?: string;
    isUnread?: boolean;
    showChannelPanel?: boolean;
    showChannelToggle?: boolean;
    showContactPanel?: boolean;
    showBackButton?: boolean;
};

withDefaults(defineProps<Props>(), {
    showChannelPanel: false,
    showChannelToggle: false,
    showContactPanel: false,
    showBackButton: false,
    isUnread: false,
    assignees: () => [],
    canAssign: false,
    currentUserId: '',
});

const emit = defineEmits<{
    (e: 'back'): void;
    (e: 'toggle-channel-panel'): void;
    (e: 'toggle-contact-panel'): void;
    (e: 'toggle-read'): void;
    (e: 'assign', userId: string | null): void;
}>();

const assign = (userId: string | null): void => {
    emit('assign', userId);
};
</script>

<template>
    <div
        v-if="conversation"
        class="flex min-h-16 shrink-0 items-center justify-between gap-3 border-b border-border bg-card px-4 py-2.5"
    >
        <div class="flex min-w-0 items-center gap-3">
            <Button
                v-if="showBackButton"
                variant="ghost"
                size="icon"
                class="size-9 shrink-0"
                @click="emit('back')"
            >
                <IconArrowLeft class="size-4" />
            </Button>
            <Avatar
                :src="conversation.contact.avatar_url"
                :name="conversation.contact.display_name"
                class="size-10 shrink-0"
                fallback-class="bg-violet-100 text-violet-700 font-bold"
            />
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <h2 class="truncate text-sm font-semibold">
                        {{ conversation.contact.display_name }}
                    </h2>
                    <Badge
                        v-if="conversation.assigned_user"
                        variant="secondary"
                        class="max-w-44 truncate px-1.5 text-[10px]"
                    >
                        {{ conversation.assigned_user.name }}
                    </Badge>
                    <IconStar class="size-3.5 text-muted-foreground" />
                </div>
                <div
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <ProviderIcon
                        :provider="conversation.channel.provider"
                        class="size-3"
                    />
                    <span class="truncate">{{
                        conversation.channel.name
                    }}</span>
                    <span class="size-1.5 rounded-full bg-emerald-500" />
                    <Badge
                        v-if="conversation.channel.status === 'connected'"
                        variant="success"
                        class="h-4 border-0 bg-transparent px-0 text-[10px] text-emerald-600 shadow-none"
                    >
                        <IconCheck class="size-2.5" />
                        {{ $t('omnichat.conversation.online') }}
                    </Badge>
                </div>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <Button
                v-if="showChannelToggle"
                variant="ghost"
                size="icon"
                class="size-9"
                :title="$t('omnichat.conversation.channels')"
                @click="emit('toggle-channel-panel')"
            >
                <IconLayoutSidebar
                    class="size-4"
                    :class="showChannelPanel ? 'text-primary' : ''"
                />
            </Button>

            <DropdownMenu v-if="canAssign">
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="sm" class="h-8">
                        <IconUserCheck class="size-4" />
                        <span class="hidden 2xl:inline">{{
                            $t('omnichat.conversation.assign')
                        }}</span>
                        <IconChevronDown class="size-3" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        @select="assign(currentUserId)"
                        @click="assign(currentUserId)"
                    >
                        {{ $t('omnichat.conversation.assign_to_me') }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        v-for="assignee in assignees"
                        :key="assignee.id"
                        @select="assign(assignee.id)"
                        @click="assign(assignee.id)"
                    >
                        {{ assignee.name }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        @select="assign(null)"
                        @click="assign(null)"
                    >
                        {{ $t('omnichat.conversation.unassign') }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Button
                v-if="conversation.contact.phone"
                variant="ghost"
                size="icon"
                class="size-9"
                :title="`Gọi: ${conversation.contact.phone}`"
                as-child
            >
                <a :href="`tel:${conversation.contact.phone}`">
                    <IconPhone class="size-4" />
                </a>
            </Button>
            <Button
                v-else
                variant="ghost"
                size="icon"
                class="size-9 cursor-not-allowed text-muted-foreground opacity-50"
                title="Khách chưa có số điện thoại"
            >
                <IconPhone class="size-4" />
            </Button>

            <!-- Toggle Read / Unread Action Button -->
            <Button
                variant="ghost"
                size="icon"
                class="size-9 transition-colors"
                :class="[
                    isUnread
                        ? 'bg-blue-100 text-blue-600 hover:bg-blue-200 dark:bg-blue-950/60 dark:text-blue-400'
                        : 'text-muted-foreground hover:text-foreground',
                ]"
                :title="
                    isUnread ? 'Đánh dấu là đã đọc' : 'Đánh dấu là chưa đọc'
                "
                @click="emit('toggle-read')"
            >
                <IconMail v-if="isUnread" class="size-4.5" />
                <IconMailOpened v-else class="size-4.5" />
            </Button>

            <!-- More Actions Dropdown -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9"
                        title="Tùy chọn khác"
                    >
                        <IconDotsVertical class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-52">
                    <DropdownMenuItem @click="emit('toggle-read')">
                        <component
                            :is="isUnread ? IconMailOpened : IconMail"
                            class="mr-2 size-4"
                        />
                        {{
                            isUnread
                                ? 'Đánh dấu là đã đọc'
                                : 'Đánh dấu là chưa đọc'
                        }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Button
                variant="ghost"
                size="icon"
                class="size-9"
                title="Thông tin khách hàng"
                @click="emit('toggle-contact-panel')"
            >
                <IconLayoutSidebarRight
                    class="size-4"
                    :class="showContactPanel ? 'text-primary' : ''"
                />
            </Button>
        </div>
    </div>
</template>
