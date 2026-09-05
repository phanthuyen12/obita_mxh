<script setup lang="ts">
import {
    IconAlertCircle,
    IconCheck,
    IconCircleCheck,
    IconSearch,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DiscordSettings from '@/components/posts/editor/DiscordSettings.vue';
import FacebookSettings from '@/components/posts/editor/FacebookSettings.vue';
import InstagramSettings from '@/components/posts/editor/InstagramSettings.vue';
import LinkedInSettings from '@/components/posts/editor/LinkedInSettings.vue';
import PinterestSettings from '@/components/posts/editor/PinterestSettings.vue';
import TikTokSettings from '@/components/posts/editor/TikTokSettings.vue';
import WordPressSettings from '@/components/posts/editor/WordPressSettings.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import type { Channel } from '@/types/channel';
import type { MediaItem } from '@/types/media';
import { Platform } from '@/types/platform';
import { PostPlatformStatus } from '@/types/post';

const props = withDefaults(
    defineProps<{
        channels: Channel[];
        selectedIds: string[];
        media?: MediaItem[];
        videoDurationSec?: number | null;
        disabled?: boolean;
        previewOnly?: boolean;
        groups?: { id: string; name: string }[];
        browserUrl?: string;
    }>(),
    {
        media: () => [],
        videoDurationSec: null,
        disabled: false,
        previewOnly: false,
        groups: () => [],
        browserUrl: undefined,
    },
);

const emit = defineEmits<{
    toggle: [id: string];
    'update:contentType': [id: string, value: string];
    'update:meta': [id: string, value: Record<string, any>];
}>();

const isSelected = (id: string): boolean => props.selectedIds.includes(id);

const selectedChannels = computed(() =>
    props.channels.filter((channel) => isSelected(channel.id)),
);

const search = ref('');
const platform = ref('');
const group = ref('');
const page = ref(1);
const perPage = ref(24);
const remoteChannels = ref<Channel[] | null>(null);
const remoteLastPage = ref<number | null>(null);
const loading = ref(false);

const platformOptions = computed(() => {
    const counts = new Map<string, number>();

    for (const channel of props.channels) {
        counts.set(channel.platform, (counts.get(channel.platform) ?? 0) + 1);
    }

    return [...counts.entries()]
        .map(([value, count]) => ({
            value,
            count,
            label: getPlatformLabel(value),
        }))
        .sort((first, second) => first.label.localeCompare(second.label));
});

const filteredChannels = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();

    return props.channels.filter((channel) => {
        const matchesSearch =
            query === '' ||
            channel.displayName.toLocaleLowerCase().includes(query) ||
            channel.username?.toLocaleLowerCase().includes(query);
        const matchesPlatform =
            platform.value === '' || channel.platform === platform.value;
        const matchesGroup =
            group.value === '' || channel.groupIds?.includes(group.value);

        return matchesSearch && matchesPlatform && matchesGroup;
    });
});

const lastPage = computed(
    () =>
        remoteLastPage.value ??
        Math.max(1, Math.ceil(filteredChannels.value.length / perPage.value)),
);
const paginatedChannels = computed(() => {
    if (remoteChannels.value) {
        return remoteChannels.value;
    }

    const offset = (page.value - 1) * perPage.value;

    return filteredChannels.value.slice(offset, offset + perPage.value);
});

const loadPage = async (targetPage: number): Promise<void> => {
    if (!props.browserUrl) {
        page.value = targetPage;
        return;
    }

    loading.value = true;
    try {
        const url = new URL(props.browserUrl, window.location.origin);
        url.searchParams.set('page', String(targetPage));
        url.searchParams.set('per_page', String(perPage.value));
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            throw new Error('Không thể tải danh sách Page.');
        }
        const payload = await response.json();
        remoteChannels.value = payload.data.map((item: any) => ({
            id: item.id,
            platform: item.platform,
            displayName:
                item.social_account?.display_label ??
                item.platform_name ??
                item.platform,
            username:
                item.social_account?.username ?? item.platform_username ?? null,
            avatarUrl:
                item.social_account?.avatar_url ?? item.platform_avatar ?? null,
            socialAccount: item.social_account,
            contentType: item.content_type ?? '',
            meta: item.meta ?? {},
            status: item.status,
            groupIds:
                item.social_account?.groups?.map(
                    (itemGroup: { id: string }) => itemGroup.id,
                ) ?? [],
        }));
        remoteLastPage.value = payload.last_page;
        page.value = targetPage;
    } finally {
        loading.value = false;
    }
};
const numberedPages = computed<(number | 'ellipsis')[]>(() => {
    if (lastPage.value <= 7) {
        return Array.from({ length: lastPage.value }, (_, index) => index + 1);
    }

    const pages = new Set([
        1,
        lastPage.value,
        page.value - 1,
        page.value,
        page.value + 1,
    ]);
    const sorted = [...pages]
        .filter((value) => value >= 1 && value <= lastPage.value)
        .sort((first, second) => first - second);
    const result: (number | 'ellipsis')[] = [];

    sorted.forEach((value, index) => {
        if (index > 0 && value - sorted[index - 1] > 1) {
            result.push('ellipsis');
        }
        result.push(value);
    });

    return result;
});
const selectedFilteredCount = computed(
    () =>
        filteredChannels.value.filter((channel) => isSelected(channel.id))
            .length,
);
const allFilteredSelected = computed(
    () =>
        filteredChannels.value.length > 0 &&
        selectedFilteredCount.value === filteredChannels.value.length,
);

const toggleFiltered = (): void => {
    const shouldSelect = !allFilteredSelected.value;

    for (const channel of filteredChannels.value) {
        if (isSelected(channel.id) !== shouldSelect) {
            emit('toggle', channel.id);
        }
    }
};

watch([search, platform, group, perPage], () => {
    page.value = 1;
    remoteChannels.value = null;
    remoteLastPage.value = null;
});
watch(lastPage, (value) => {
    page.value = Math.min(page.value, value);
});
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-3 rounded-xl border bg-muted/20 p-3">
            <div class="grid gap-2 sm:grid-cols-2">
                <label class="relative sm:col-span-2">
                    <IconSearch
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Tìm Page, tên hoặc username..."
                        class="h-10 w-full rounded-lg border bg-background pr-3 pl-9 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                </label>
                <select
                    v-model="platform"
                    class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    aria-label="Lọc theo nền tảng"
                >
                    <option value="">
                        Tất cả nền tảng ({{ channels.length }})
                    </option>
                    <option
                        v-for="option in platformOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }} ({{ option.count }})
                    </option>
                </select>
                <select
                    v-model="group"
                    class="h-10 rounded-lg border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    aria-label="Lọc theo nhóm Page"
                >
                    <option value="">Tất cả nhóm</option>
                    <option
                        v-for="option in groups"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.name }}
                    </option>
                </select>
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-background px-3 py-2"
            >
                <button
                    type="button"
                    class="inline-flex items-center gap-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="disabled || filteredChannels.length === 0"
                    @click="toggleFiltered"
                >
                    <span
                        class="inline-flex size-5 items-center justify-center rounded border"
                        :class="
                            allFilteredSelected
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'bg-background'
                        "
                    >
                        <IconCheck
                            v-if="allFilteredSelected"
                            class="size-3.5"
                        />
                    </span>
                    {{
                        allFilteredSelected
                            ? 'Bỏ chọn kết quả lọc'
                            : 'Chọn tất cả ' +
                              filteredChannels.length +
                              ' kết quả'
                    }}
                </button>
                <span class="text-xs text-muted-foreground">
                    Đã chọn {{ selectedIds.length }}/{{ channels.length }}
                </span>
            </div>
        </div>

        <div
            v-if="paginatedChannels.length"
            class="relative overflow-hidden rounded-xl border bg-card"
        >
            <div
                v-if="loading"
                class="absolute inset-0 z-10 grid place-items-center bg-background/70 text-sm font-medium backdrop-blur-[1px]"
            >
                Đang tải Page…
            </div>
            <div
                class="hidden grid-cols-[auto_auto_minmax(0,2fr)_minmax(8rem,1fr)_minmax(7rem,1fr)_7rem] gap-3 border-b bg-muted/40 px-4 py-2 text-[11px] font-semibold text-muted-foreground md:grid"
            >
                <span class="col-span-2"></span>
                <span>Kênh / Page</span>
                <span>Nền tảng</span>
                <span>Nhóm</span>
                <span>Trạng thái</span>
            </div>
            <TooltipProvider
                v-for="channel in paginatedChannels"
                :key="channel.id"
                :delay-duration="200"
            >
                <Tooltip>
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            class="grid w-full min-w-0 cursor-pointer grid-cols-[auto_minmax(0,1fr)] items-center gap-3 border-b px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-muted/40 md:grid-cols-[auto_auto_minmax(0,2fr)_minmax(8rem,1fr)_minmax(7rem,1fr)_7rem]"
                            :class="[
                                channel.issue && !isSelected(channel.id)
                                    ? 'cursor-not-allowed opacity-40'
                                    : '',
                                channel.issue && isSelected(channel.id)
                                    ? 'opacity-100'
                                    : '',
                                !channel.issue
                                    ? 'opacity-100 hover:opacity-90'
                                    : '',
                            ]"
                            :disabled="
                                disabled ||
                                (Boolean(channel.issue) &&
                                    !isSelected(channel.id))
                            "
                            @click="emit('toggle', channel.id)"
                        >
                            <span
                                class="inline-flex size-5 items-center justify-center rounded border"
                                :class="
                                    isSelected(channel.id)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-background'
                                "
                            >
                                <IconCheck
                                    v-if="isSelected(channel.id)"
                                    class="size-3.5"
                                />
                            </span>
                            <div class="relative hidden md:block">
                                <Avatar
                                    :src="channel.avatarUrl"
                                    :name="channel.displayName"
                                    class="size-10 shrink-0 rounded-full border-2"
                                    :class="[
                                        channel.issue && isSelected(channel.id)
                                            ? 'border-rose-500 shadow-2xs'
                                            : '',
                                        !channel.issue && isSelected(channel.id)
                                            ? 'border-foreground shadow-2xs'
                                            : '',
                                        !isSelected(channel.id)
                                            ? 'border-foreground/20'
                                            : '',
                                    ]"
                                />
                                <span
                                    class="absolute -right-1 -bottom-1 inline-flex size-5 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-card shadow-2xs"
                                >
                                    <img
                                        :src="getPlatformLogo(channel.platform)"
                                        :alt="channel.platform"
                                        class="size-full object-cover"
                                    />
                                </span>
                                <Badge
                                    v-if="
                                        channel.issue && isSelected(channel.id)
                                    "
                                    variant="destructive"
                                    class="absolute -top-1 -right-1 h-4 w-4 p-0"
                                >
                                    <IconAlertCircle class="h-2.5 w-2.5" />
                                </Badge>
                                <Badge
                                    v-else-if="
                                        channel.status ===
                                        PostPlatformStatus.Published
                                    "
                                    variant="success"
                                    class="absolute -top-1 -right-1 h-4 w-4 p-0"
                                >
                                    <IconCircleCheck class="h-2.5 w-2.5" />
                                </Badge>
                                <Badge
                                    v-else-if="
                                        channel.status ===
                                        PostPlatformStatus.Failed
                                    "
                                    variant="destructive"
                                    class="absolute -top-1 -right-1 h-4 w-4 p-0 text-[9px]"
                                    >!</Badge
                                >
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ channel.displayName }}
                                </p>
                                <p
                                    v-if="channel.username"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    @{{ channel.username }}
                                </p>
                            </div>
                            <div
                                class="hidden items-center gap-2 text-sm md:flex"
                            >
                                <img
                                    :src="getPlatformLogo(channel.platform)"
                                    :alt="channel.platform"
                                    class="size-4"
                                />
                                <span class="truncate">{{
                                    getPlatformLabel(channel.platform)
                                }}</span>
                            </div>
                            <span
                                class="hidden truncate text-xs text-muted-foreground md:block"
                            >
                                {{
                                    groups
                                        .filter((item) =>
                                            channel.groupIds?.includes(item.id),
                                        )
                                        .map((item) => item.name)
                                        .join(', ') || '—'
                                }}
                            </span>
                            <Badge
                                class="hidden w-fit md:inline-flex"
                                :variant="
                                    channel.issue ? 'destructive' : 'success'
                                "
                            >
                                {{
                                    channel.issue
                                        ? 'Cần kiểm tra'
                                        : 'Đang hoạt động'
                                }}
                            </Badge>
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <div class="space-y-0.5 text-xs">
                            <p class="font-semibold">
                                {{ channel.displayName
                                }}<span
                                    v-if="channel.username"
                                    class="font-normal opacity-80"
                                    >&nbsp;·&nbsp;@{{ channel.username }}</span
                                >
                            </p>
                            <p class="opacity-70">
                                {{ getPlatformLabel(channel.platform) }}
                            </p>
                            <p
                                v-if="channel.issue"
                                class="mt-1 max-w-xs text-destructive-foreground/90"
                            >
                                {{ channel.issue }}
                            </p>
                        </div>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>
        <div
            v-else
            class="rounded-xl border border-dashed px-4 py-8 text-center text-sm text-muted-foreground"
        >
            Không tìm thấy Page phù hợp với bộ lọc.
        </div>

        <div
            v-if="lastPage > 1"
            class="flex flex-wrap items-center justify-center gap-1.5"
        >
            <template
                v-for="(item, index) in numberedPages"
                :key="item + '-' + index"
            >
                <span
                    v-if="item === 'ellipsis'"
                    class="inline-flex size-8 items-center justify-center text-sm text-muted-foreground"
                >
                    …
                </span>
                <button
                    v-else
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-md border text-sm font-medium transition-colors"
                    :class="
                        item === page
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'bg-background hover:bg-muted'
                    "
                    :aria-current="item === page ? 'page' : undefined"
                    :disabled="loading"
                    @click="loadPage(item)"
                >
                    {{ item }}
                </button>
            </template>
            <select
                v-model="perPage"
                class="ml-2 h-8 rounded-md border bg-background px-2 text-xs"
                aria-label="Số Page mỗi trang"
            >
                <option :value="12">12 / trang</option>
                <option :value="24">24 / trang</option>
                <option :value="48">48 / trang</option>
            </select>
        </div>
        <slot />

        <template v-for="channel in selectedChannels" :key="channel.id">
            <InstagramSettings
                v-if="
                    channel.platform === Platform.Instagram ||
                    channel.platform === Platform.InstagramFacebook
                "
                :social-account="channel.socialAccount"
                :content-type="channel.contentType"
                :media="media"
                :meta="channel.meta"
                :disabled="disabled"
                :preview-only="previewOnly"
                @update:content-type="
                    emit('update:contentType', channel.id, $event)
                "
                @update:meta="emit('update:meta', channel.id, $event)"
            />
            <FacebookSettings
                v-else-if="channel.platform === Platform.Facebook"
                :social-account="channel.socialAccount"
                :content-type="channel.contentType"
                :media="media"
                :meta="channel.meta"
                :disabled="disabled"
                :preview-only="previewOnly"
                @update:content-type="
                    emit('update:contentType', channel.id, $event)
                "
                @update:meta="emit('update:meta', channel.id, $event)"
            />
            <TikTokSettings
                v-else-if="channel.platform === Platform.TikTok"
                :social-account="channel.socialAccount"
                :publish-config="channel.publishConfig ?? null"
                :creator-info="channel.creatorInfo ?? null"
                :video-duration-sec="videoDurationSec"
                :content-type="channel.contentType"
                :content-type-error="channel.contentTypeError"
                :meta="channel.meta"
                :disabled="disabled"
                :preview-only="previewOnly"
                @update:content-type="
                    emit('update:contentType', channel.id, $event)
                "
                @update:meta="emit('update:meta', channel.id, $event)"
            />
            <PinterestSettings
                v-else-if="channel.platform === Platform.Pinterest"
                :social-account="channel.socialAccount"
                :content-type="channel.contentType"
                :media="media"
                :boards="channel.boards ?? []"
                :boards-truncated="channel.boardsTruncated ?? false"
                :meta="channel.meta"
                :disabled="disabled"
                :preview-only="previewOnly"
                @update:content-type="
                    emit('update:contentType', channel.id, $event)
                "
                @update:meta="emit('update:meta', channel.id, $event)"
            />
            <LinkedInSettings
                v-else-if="
                    channel.platform === Platform.LinkedIn ||
                    channel.platform === Platform.LinkedInPage
                "
                :social-account="channel.socialAccount"
                :platform="channel.platform"
                :media="media"
                :meta="channel.meta"
                :disabled="disabled"
                :preview-only="previewOnly"
                @update:meta="emit('update:meta', channel.id, $event)"
            />
            <DiscordSettings
                v-else-if="channel.platform === Platform.Discord"
                :social-account="channel.socialAccount"
                :meta="channel.meta"
                :disabled="disabled"
                :preview-only="previewOnly"
                @update:meta="emit('update:meta', channel.id, $event)"
            />
            <WordPressSettings
                v-else-if="
                    channel.platform === Platform.WordPress ||
                    channel.platform === 'wordpress'
                "
                :social-account="channel.socialAccount"
                :content-type="channel.contentType"
                :media="media"
                :meta="channel.meta"
                :disabled="disabled"
                :preview-only="previewOnly"
                @update:meta="emit('update:meta', channel.id, $event)"
            />
        </template>
    </div>
</template>
