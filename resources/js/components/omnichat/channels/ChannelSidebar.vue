<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    IconChevronDown,
    IconInbox,
    IconPlus,
    IconSettings,
    IconStar,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import ProviderIcon from '@/components/omnichat/shared/ProviderIcon.vue';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { accounts } from '@/routes/app';

type Channel = {
    id: string;
    provider: string;
    name: string;
    avatar_url: string | null;
    status: 'connected' | 'disconnected' | 'token_expired';
    is_active: boolean;
};

const props = defineProps<{
    channels: Channel[];
    canManageChannels: boolean;
    selectedChannelIds: string[];
}>();

const emit = defineEmits<{
    (e: 'select-page', channelId: string): void;
}>();

const enabledChannelIds = computed(() => new Set(props.selectedChannelIds));
const activeTab = ref<'channels' | 'pages'>('channels');
const enabledCount = computed(() => enabledChannelIds.value.size);
</script>

<template>
    <aside
        class="flex h-full min-h-0 flex-col overflow-hidden border-r border-border bg-card"
    >
        <div
            class="flex h-16 shrink-0 items-center justify-between border-b border-border px-4"
        >
            <div class="flex items-center gap-2">
                <div
                    class="flex size-8 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <IconInbox class="size-4" />
                </div>
                <span class="font-semibold">{{
                    $t('omnichat.inbox.title')
                }}</span>
                <span
                    class="rounded-full bg-amber-400 px-2 py-0.5 text-xs font-bold text-amber-950"
                >
                    {{ enabledCount }}
                </span>
            </div>
            <Button
                v-if="canManageChannels"
                as-child
                variant="ghost"
                size="icon"
                class="size-8"
            >
                <Link :href="accounts.url()">
                    <IconSettings class="size-4" />
                </Link>
            </Button>
        </div>

        <div class="shrink-0 p-3">
            <div
                class="grid grid-cols-2 rounded-lg bg-muted p-1 text-xs font-medium"
            >
                <button
                    class="rounded-md px-3 py-2 transition-colors"
                    :class="
                        activeTab === 'channels'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground'
                    "
                    @click="activeTab = 'channels'"
                >
                    {{ $t('omnichat.channel.channels') }}
                </button>
                <button
                    class="rounded-md px-3 py-2 transition-colors"
                    :class="
                        activeTab === 'pages'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground'
                    "
                    @click="activeTab = 'pages'"
                >
                    {{ $t('omnichat.channel.pages') }}
                </button>
            </div>
        </div>

        <ScrollArea class="min-h-0 flex-1">
            <div v-if="activeTab === 'channels'" class="space-y-5 px-3 pb-4">
                <section>
                    <div class="mb-2 flex items-center justify-between px-2">
                        <p
                            class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            {{ $t('omnichat.channel.groups') }}
                        </p>
                        <Button variant="ghost" size="icon" class="size-7"
                            ><IconPlus class="size-3.5"
                        /></Button>
                    </div>
                    <div class="space-y-1">
                        <button
                            class="flex w-full items-center gap-2 rounded-lg bg-primary/10 px-3 py-2 text-left text-sm font-medium text-primary"
                        >
                            <IconInbox class="size-4" />
                            <span class="min-w-0 flex-1 truncate">{{
                                $t('omnichat.channel.all')
                            }}</span>
                            <span class="text-xs">{{ channels.length }}</span>
                        </button>
                        <button
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-muted-foreground hover:bg-muted"
                        >
                            <IconStar class="size-4" />
                            <span class="min-w-0 flex-1 truncate">{{
                                $t('omnichat.channel.favorites')
                            }}</span>
                            <span class="text-xs">2</span>
                        </button>
                        <button
                            v-for="channel in channels"
                            :key="`group-${channel.id}`"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-muted-foreground hover:bg-muted"
                        >
                            <ProviderIcon
                                :provider="channel.provider"
                                class="size-4"
                            />
                            <span class="min-w-0 flex-1 truncate capitalize">{{
                                channel.provider
                            }}</span>
                            <span class="text-xs">{{
                                Math.max(1, channels.length)
                            }}</span>
                        </button>
                    </div>
                </section>

                <section>
                    <button
                        class="mb-2 flex w-full items-center justify-between px-2 text-left"
                    >
                        <span
                            class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >{{ $t('omnichat.channel.visible') }}</span
                        >
                        <IconChevronDown
                            class="size-3.5 text-muted-foreground"
                        />
                    </button>
                    <div class="space-y-1">
                        <div
                            v-for="channel in channels"
                            :key="channel.id"
                            class="flex items-center gap-2 rounded-lg px-2 py-2 hover:bg-muted/70"
                        >
                            <button
                                class="flex size-4 shrink-0 items-center justify-center rounded border"
                                :class="
                                    enabledChannelIds.has(channel.id)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border'
                                "
                                @click="emit('select-page', channel.id)"
                            >
                                <span
                                    v-if="enabledChannelIds.has(channel.id)"
                                    class="text-[10px]"
                                    >✓</span
                                >
                            </button>
                            <ProviderIcon
                                :provider="channel.provider"
                                class="size-4 shrink-0"
                            />
                            <span
                                class="min-w-0 flex-1 truncate text-xs font-medium"
                                >{{ channel.name }}</span
                            >
                            <button
                                class="relative h-5 w-9 shrink-0 rounded-full transition-colors"
                                :class="
                                    enabledChannelIds.has(channel.id)
                                        ? 'bg-emerald-500'
                                        : 'bg-muted-foreground/30'
                                "
                                @click="emit('select-page', channel.id)"
                            >
                                <span
                                    class="absolute top-0.5 size-4 rounded-full bg-white shadow-sm transition-transform"
                                    :class="
                                        enabledChannelIds.has(channel.id)
                                            ? 'translate-x-4'
                                            : 'translate-x-0.5'
                                    "
                                />
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            <div v-else class="space-y-2 px-3 pb-4">
                <div class="px-2 pb-2">
                    <p
                        class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ $t('omnichat.channel.connected_pages') }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ $t('omnichat.channel.select_page') }}
                    </p>
                </div>

                <button
                    v-for="channel in channels"
                    :key="`page-${channel.id}`"
                    class="flex w-full items-center gap-2.5 rounded-xl border p-2.5 text-left transition-colors hover:border-border hover:bg-muted/60"
                    :class="
                        enabledChannelIds.has(channel.id)
                            ? 'border-primary bg-primary/10'
                            : 'border-transparent'
                    "
                    @click="emit('select-page', channel.id)"
                >
                    <div
                        class="relative flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted"
                    >
                        <ProviderIcon
                            :provider="channel.provider"
                            class="size-5"
                        />
                        <span
                            class="absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full border-2 border-card"
                            :class="
                                channel.status === 'connected'
                                    ? 'bg-emerald-500'
                                    : 'bg-destructive'
                            "
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold">
                            {{ channel.name }}
                        </p>
                        <p
                            class="mt-0.5 truncate text-[11px] text-muted-foreground capitalize"
                        >
                            {{ channel.provider }} · {{ channel.status }}
                        </p>
                    </div>
                    <span
                        v-if="enabledChannelIds.has(channel.id)"
                        class="rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground"
                        >✓</span
                    >
                </button>

                <div
                    v-if="channels.length === 0"
                    class="rounded-xl border border-dashed border-border p-4 text-center text-xs text-muted-foreground"
                >
                    {{ $t('omnichat.channel.no_connected_pages') }}
                </div>
            </div>
        </ScrollArea>

        <div
            v-if="canManageChannels"
            class="shrink-0 border-t border-border p-3"
        >
            <Button as-child variant="outline" size="sm" class="w-full">
                <Link :href="accounts.url()">
                    <IconPlus class="size-4" />
                    {{ $t('omnichat.channel.manage') }}
                </Link>
            </Button>
        </div>
    </aside>
</template>
