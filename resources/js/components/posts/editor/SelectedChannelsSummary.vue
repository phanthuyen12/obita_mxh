<script setup lang="ts">
import { IconFolder, IconX } from '@tabler/icons-vue';
import { computed } from 'vue';

import { Avatar } from '@/components/ui/avatar';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';

interface SocialAccount {
    avatar_url: string | null;
    display_label: string;
    groups?: { id: string; name: string }[];
}

interface PostPlatform {
    id: string;
    platform: string;
    platform_name: string | null;
    social_account: SocialAccount | null;
}

const props = defineProps<{
    postPlatforms: PostPlatform[];
    selectedPlatformIds: string[];
    groups: { id: string; name: string }[];
    disabled: boolean;
}>();

const emit = defineEmits<{
    toggle: [id: string];
}>();

const selectedChannels = computed(() =>
    props.postPlatforms.filter((channel) =>
        props.selectedPlatformIds.includes(channel.id),
    ),
);
const platformCounts = computed(() => {
    const counts = new Map<string, number>();

    for (const channel of selectedChannels.value) {
        counts.set(channel.platform, (counts.get(channel.platform) ?? 0) + 1);
    }

    return [...counts.entries()]
        .map(([platform, count]) => ({ platform, count }))
        .sort((first, second) => second.count - first.count);
});
const groupCounts = computed(() =>
    props.groups.map((group) => ({
        ...group,
        count: props.postPlatforms.filter((channel) =>
            channel.social_account?.groups?.some(
                (accountGroup) => accountGroup.id === group.id,
            ),
        ).length,
    })),
);
</script>

<template>
    <div class="space-y-4 p-4">
        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="flex items-center justify-between gap-3 border-b p-4">
                <h2 class="text-sm font-semibold">
                    Kênh đã chọn ({{ selectedChannels.length }})
                </h2>
                <button
                    v-if="selectedChannels.length"
                    type="button"
                    class="text-xs font-medium text-primary hover:underline disabled:opacity-50"
                    :disabled="disabled"
                    @click="
                        selectedChannels.forEach((channel) =>
                            emit('toggle', channel.id),
                        )
                    "
                >
                    Bỏ chọn tất cả
                </button>
            </div>

            <div
                v-if="platformCounts.length"
                class="grid grid-cols-2 border-b sm:grid-cols-4 lg:grid-cols-2 xl:grid-cols-4"
            >
                <div
                    v-for="item in platformCounts.slice(0, 4)"
                    :key="item.platform"
                    class="grid justify-items-center gap-1 border-r p-3 last:border-r-0"
                >
                    <img
                        :src="getPlatformLogo(item.platform)"
                        :alt="item.platform"
                        class="size-5"
                    />
                    <span class="text-lg font-bold text-primary">{{
                        item.count
                    }}</span>
                    <span class="truncate text-[10px] text-muted-foreground">
                        {{ getPlatformLabel(item.platform) }}
                    </span>
                </div>
            </div>

            <div v-if="selectedChannels.length" class="divide-y">
                <div
                    v-for="channel in selectedChannels.slice(0, 6)"
                    :key="channel.id"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <Avatar
                        :src="channel.social_account?.avatar_url"
                        :name="
                            channel.social_account?.display_label ??
                            channel.platform_name ??
                            channel.platform
                        "
                        class="size-8 shrink-0"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{
                                channel.social_account?.display_label ??
                                channel.platform_name ??
                                channel.platform
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ getPlatformLabel(channel.platform) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-50"
                        :disabled="disabled"
                        aria-label="Bỏ chọn Page"
                        @click="emit('toggle', channel.id)"
                    >
                        <IconX class="size-4" />
                    </button>
                </div>
                <p
                    v-if="selectedChannels.length > 6"
                    class="bg-muted/30 px-4 py-3 text-center text-xs text-muted-foreground"
                >
                    +{{ selectedChannels.length - 6 }} kênh khác
                </p>
            </div>
            <p
                v-else
                class="px-4 py-8 text-center text-sm text-muted-foreground"
            >
                Chưa chọn Page nào để đăng.
            </p>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card">
            <div class="border-b p-4">
                <h2 class="text-sm font-semibold">Nhóm kênh</h2>
            </div>
            <div v-if="groupCounts.length" class="divide-y">
                <div
                    v-for="group in groupCounts"
                    :key="group.id"
                    class="flex items-center gap-2 px-4 py-3 text-sm"
                >
                    <IconFolder class="size-4 text-muted-foreground" />
                    <span class="min-w-0 flex-1 truncate">{{
                        group.name
                    }}</span>
                    <span class="text-xs text-muted-foreground tabular-nums">{{
                        group.count
                    }}</span>
                </div>
            </div>
            <p v-else class="p-4 text-xs text-muted-foreground">
                Chưa có nhóm Page.
            </p>
        </section>

        <aside
            class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-xs leading-relaxed text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300"
        >
            Mẹo: dùng bộ lọc nhóm để chọn nhanh nhiều Page cho các chiến dịch
            thường xuyên.
        </aside>
    </div>
</template>
