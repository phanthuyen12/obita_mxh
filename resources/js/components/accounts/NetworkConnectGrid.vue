<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { IconAlertTriangle, IconCheck } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { browser as browseAccounts } from '@/actions/App/Http/Controllers/Auth/SocialController';
import InstagramConnectDialog from '@/components/accounts/InstagramConnectDialog.vue';
import TelegramConnectDialog from '@/components/accounts/TelegramConnectDialog.vue';
import WordPressConnectDialog from '@/components/accounts/WordPressConnectDialog.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useOAuthPopup } from '@/composables/useOAuthPopup';
import { getPlatformCapabilities } from '@/composables/usePlatformCapabilities';
import { getPlatformLogo } from '@/composables/usePlatformLogo';
import debounce from '@/debounce';
import { disconnect } from '@/routes/app/accounts';
import { Platform } from '@/types/platform';

export interface AvailablePlatform {
    value: string;
    label: string;
    color: string;
    network: string;
    connect_methods?: string[];
}

export interface ConnectedAccount {
    id: string;
    platform: string;
    network: string;
    username: string;
    display_name: string;
    display_label: string;
    handle_label: string;
    avatar_url: string | null;
    status: 'connected' | 'disconnected' | 'token_expired' | null;
    ownership_type: 'owned' | 'shared';
    can_disconnect: boolean;
    can_share: boolean;
    shared_user_ids: string[];
    shared_user_permissions: Record<string, Record<string, boolean>>;
}

const props = withDefaults(
    defineProps<{
        platforms: AvailablePlatform[];
        connectedAccounts?: ConnectedAccount[];
        networkAccountCounts?: Record<string, number>;
        gridClass?: string;
    }>(),
    {
        connectedAccounts: () => [],
        networkAccountCounts: () => ({}),
        gridClass: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
    },
);

const getPlatformDescription = (platform: string): string =>
    trans(`accounts.descriptions.${platform}`);

// Mirrors `NetworksGrid.vue` from the marketing site — pastel tile bg
// + ink 2px border + slight rotation per platform, real PNG logo inside.
// `instagram-facebook` falls back to the base brand image and same color
// since it's a variant of the same network.
const platformTheme: Record<
    string,
    { bg: string; rotate: string; image: string }
> = {
    linkedin: {
        bg: 'bg-blue-100',
        rotate: 'rotate-1',
        image: '/images/accounts/linkedin.png',
    },
    'linkedin-page': {
        bg: 'bg-blue-100',
        rotate: 'rotate-1',
        image: '/images/accounts/linkedin.png',
    },
    x: {
        bg: 'bg-zinc-200',
        rotate: '-rotate-1',
        image: '/images/accounts/x.png',
    },
    tiktok: {
        bg: 'bg-fuchsia-200',
        rotate: '-rotate-1',
        image: '/images/accounts/tiktok.png',
    },
    instagram: {
        bg: 'bg-pink-200',
        rotate: '-rotate-2',
        image: '/images/accounts/instagram.png',
    },
    'instagram-facebook': {
        bg: 'bg-pink-200',
        rotate: '-rotate-2',
        image: '/images/accounts/instagram.png',
    },
    facebook: {
        bg: 'bg-sky-200',
        rotate: 'rotate-1',
        image: '/images/accounts/facebook.png',
    },
    youtube: {
        bg: 'bg-red-200',
        rotate: 'rotate-1',
        image: '/images/accounts/youtube.png',
    },
    threads: {
        bg: 'bg-stone-200',
        rotate: 'rotate-1',
        image: '/images/accounts/threads.png',
    },
    pinterest: {
        bg: 'bg-rose-200',
        rotate: '-rotate-1',
        image: '/images/accounts/pinterest.png',
    },
    bluesky: {
        bg: 'bg-sky-100',
        rotate: 'rotate-2',
        image: '/images/accounts/bluesky.png',
    },
    mastodon: {
        bg: 'bg-indigo-100',
        rotate: '-rotate-2',
        image: '/images/accounts/mastodon.png',
    },
    telegram: {
        bg: 'bg-blue-200',
        rotate: 'rotate-1',
        image: '/images/accounts/telegram.png',
    },
    discord: {
        bg: 'bg-indigo-200',
        rotate: '-rotate-1',
        image: '/images/accounts/discord.png',
    },
    'zalo-oa': {
        bg: 'bg-blue-200',
        rotate: '-rotate-1',
        image: '/images/accounts/zalo-oa.svg',
    },
    lazada: {
        bg: 'bg-indigo-200',
        rotate: 'rotate-1',
        image: '/images/accounts/lazada.png',
    },
    shopee: {
        bg: 'bg-orange-200',
        rotate: '-rotate-1',
        image: '/images/accounts/shopee.svg',
    },
    wordpress: {
        bg: 'bg-cyan-100',
        rotate: '-rotate-1',
        image: '/images/accounts/wordpress.svg',
    },
};

const themeFor = (value: string) =>
    platformTheme[value] ?? {
        bg: 'bg-muted',
        rotate: '',
        image: getPlatformLogo(value),
    };

// One account per network: map each connected network to its account so every
// platform card belonging to that network reflects the connection.
const connectedByNetwork = computed((): Record<string, ConnectedAccount[]> => {
    const map: Record<string, ConnectedAccount[]> = {};

    for (const account of props.connectedAccounts) {
        map[account.network] ??= [];
        map[account.network].push(account);
    }

    return map;
});

const telegramOpen = ref(false);
const instagramOpen = ref(false);
const wordPressOpen = ref(false);
const accountBrowserOpen = ref(false);
const selectedNetwork = ref<string | null>(null);
const accountSearch = ref('');
const browsedAccounts = ref<ConnectedAccount[]>([]);
const accountBrowserLoading = ref(false);
const disconnectModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(
    null,
);

const { openOAuthPopup } = useOAuthPopup((result) => {
    if (result.success) {
        toast.success(result.message);
        router.reload();
        return;
    }

    toast.error(result.message);
});

const disconnectAccount = (account: ConnectedAccount) => {
    const url =
        account.platform === 'wordpress' || account.network === 'wordpress'
            ? `/wordpress/sites/${account.id}`
            : disconnect.url(account.id);

    disconnectModal.value?.open({
        url,
        confirmText: account.handle_label,
    });
};

const accountsForNetwork = (network: string): ConnectedAccount[] =>
    connectedByNetwork.value[network] ?? [];

const visibleAccountsForNetwork = (network: string): ConnectedAccount[] =>
    accountsForNetwork(network).slice(0, 3);

const loadBrowsedAccounts = async (): Promise<void> => {
    if (!selectedNetwork.value) return;
    accountBrowserLoading.value = true;

    try {
        const response = await fetch(
            browseAccounts.url({
                query: {
                    network: selectedNetwork.value,
                    search: accountSearch.value || undefined,
                    per_page: 50,
                },
            }),
            { headers: { Accept: 'application/json' } },
        );
        const payload = await response.json();
        browsedAccounts.value = payload.data ?? [];
    } finally {
        accountBrowserLoading.value = false;
    }
};

const openAccountBrowser = async (network: string): Promise<void> => {
    selectedNetwork.value = network;
    accountSearch.value = '';
    accountBrowserOpen.value = true;
    await loadBrowsedAccounts();
};

watch(accountSearch, debounce(loadBrowsedAccounts, 300));

const selectedNetworkLabel = computed(
    () =>
        props.platforms.find(
            (platform) => platform.network === selectedNetwork.value,
        )?.label ?? 'Profiles',
);

const needsReconnect = (account: ConnectedAccount): boolean =>
    account.status === 'disconnected' || account.status === 'token_expired';

const instagramMethods = computed((): string[] => {
    const instagram = props.platforms.find(
        (platform) => platform.value === Platform.Instagram,
    );

    return (
        instagram?.connect_methods ?? [
            Platform.Instagram,
            Platform.InstagramFacebook,
        ]
    );
});

const openConnect = (platformValue: string) => {
    if (platformValue === 'wordpress' || platformValue === Platform.WordPress) {
        wordPressOpen.value = true;
        return;
    }

    if (platformValue === Platform.Telegram) {
        telegramOpen.value = true;
        return;
    }

    if (platformValue === Platform.Instagram) {
        instagramOpen.value = true;
        return;
    }

    openOAuthPopup(platformValue);
};

const connectPlatform = (platformValue: string) => {
    openConnect(platformValue);
};

const CardState = {
    Connect: 'connect',
    Connected: 'connected',
    Reconnect: 'reconnect',
} as const;

type CardStateValue = (typeof CardState)[keyof typeof CardState];

const cardState = computed((): Record<string, CardStateValue> => {
    const map: Record<string, CardStateValue> = {};

    for (const platform of props.platforms) {
        const accounts = connectedByNetwork.value[platform.network] ?? [];
        map[platform.value] =
            accounts.length === 0
                ? CardState.Connect
                : accounts.some(needsReconnect)
                  ? CardState.Reconnect
                  : CardState.Connected;
    }

    return map;
});
</script>

<template>
    <div>
        <div :class="['grid gap-4', gridClass]">
            <div
                v-for="platform in platforms"
                :key="platform.value"
                :id="'platform-' + platform.value"
                :class="[
                    'group relative flex flex-col items-center gap-3 rounded-xl border-2 border-foreground p-4 text-center shadow-xs transition-shadow',
                    cardState[platform.value] === CardState.Connected
                        ? 'bg-emerald-50'
                        : cardState[platform.value] === CardState.Reconnect
                          ? 'bg-amber-50'
                          : 'bg-card hover:shadow-md',
                ]"
            >
                <span
                    v-if="cardState[platform.value] === CardState.Connected"
                    class="absolute -top-2 -right-2 inline-flex size-6 items-center justify-center rounded-full border-2 border-foreground bg-emerald-200 text-emerald-700 shadow-2xs"
                    aria-hidden="true"
                >
                    <IconCheck class="size-3.5" stroke-width="3" />
                </span>
                <span
                    v-else-if="
                        cardState[platform.value] === CardState.Reconnect
                    "
                    class="absolute -top-2 -right-2 inline-flex size-6 items-center justify-center rounded-full border-2 border-foreground bg-amber-200 text-amber-700 shadow-2xs"
                    aria-hidden="true"
                >
                    <IconAlertTriangle class="size-3.5" stroke-width="2.5" />
                </span>

                <div
                    :class="[
                        themeFor(platform.value).rotate,
                        'inline-flex size-16 items-center justify-center rounded-2xl border-2 border-foreground shadow-sm transition-transform group-hover:!rotate-0',
                    ]"
                    :style="{ backgroundColor: platform.color }"
                >
                    <img
                        :src="themeFor(platform.value).image"
                        :alt="platform.label"
                        class="size-9 rounded-lg object-contain"
                        loading="lazy"
                    />
                </div>

                <div class="w-full min-w-0 flex-1">
                    <span
                        class="block truncate text-sm font-semibold text-foreground"
                    >
                        <template v-if="platform.label.includes('(')">
                            {{ platform.label.split('(')[0].trim() }}
                        </template>
                        <template v-else>{{ platform.label }}</template>
                    </span>
                    <p
                        v-if="cardState[platform.value] === CardState.Connect"
                        class="mt-0.5 line-clamp-2 text-xs leading-tight text-foreground/60"
                    >
                        {{ getPlatformDescription(platform.value) }}
                    </p>
                    <div class="mt-2 flex flex-wrap justify-center gap-1">
                        <span
                            v-for="capability in getPlatformCapabilities(
                                platform.value,
                            )"
                            :key="capability"
                            class="rounded-full border px-2 py-0.5 text-[10px] font-medium"
                            :class="
                                capability === 'omnichat'
                                    ? 'border-violet-300 bg-violet-50 text-violet-700 dark:border-violet-800 dark:bg-violet-950/30 dark:text-violet-300'
                                    : 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300'
                            "
                        >
                            {{
                                capability === 'omnichat'
                                    ? 'Omnichat'
                                    : 'Đăng bài'
                            }}
                        </span>
                    </div>
                </div>

                <div
                    v-if="(networkAccountCounts[platform.network] ?? 0) > 0"
                    class="w-full space-y-2"
                >
                    <div
                        v-for="account in visibleAccountsForNetwork(
                            platform.network,
                        )"
                        :key="account.id"
                        class="flex items-center gap-2 rounded-lg border bg-background/70 p-2 text-left"
                    >
                        <img
                            :src="
                                account.avatar_url ||
                                getPlatformLogo(account.platform)
                            "
                            :alt="account.display_label"
                            class="size-7 shrink-0 rounded-full object-cover"
                        />
                        <span
                            class="min-w-0 flex-1 truncate text-xs font-medium"
                        >
                            {{ account.display_label }}
                        </span>
                        <span
                            v-for="capability in getPlatformCapabilities(
                                account.platform,
                            )"
                            :key="capability"
                            class="hidden rounded-full border px-1.5 py-0.5 text-[9px] font-medium sm:inline-flex"
                            :class="
                                capability === 'omnichat'
                                    ? 'border-violet-300 text-violet-700'
                                    : 'border-emerald-300 text-emerald-700'
                            "
                        >
                            {{ capability === 'omnichat' ? 'Chat' : 'Post' }}
                        </span>
                        <Button
                            v-if="account.can_disconnect"
                            :id="'disconnect-' + account.id"
                            variant="destructive"
                            size="sm"
                            class="h-7 px-2 text-[11px]"
                            @click="disconnectAccount(account)"
                        >
                            Xóa
                        </Button>
                        <span
                            v-else
                            class="text-[11px] font-medium text-blue-700 dark:text-blue-300"
                            >Admin chia sẻ</span
                        >
                    </div>
                    <button
                        v-if="(networkAccountCounts[platform.network] ?? 0) > 3"
                        type="button"
                        class="w-full rounded-lg border border-dashed px-3 py-2 text-xs font-semibold text-muted-foreground transition-colors hover:bg-background hover:text-foreground"
                        @click="openAccountBrowser(platform.network)"
                    >
                        Xem tất cả
                        {{ networkAccountCounts[platform.network] ?? 0 }}
                        profile/channel
                    </button>
                </div>

                <Button
                    :id="'connect-' + platform.value"
                    size="sm"
                    class="mt-auto w-full"
                    @click="connectPlatform(platform.value)"
                >
                    {{
                        (networkAccountCounts[platform.network] ?? 0) > 0
                            ? 'Kết nối thêm'
                            : $t('accounts.connect_cta')
                    }}
                </Button>
            </div>
        </div>

        <Dialog v-model:open="accountBrowserOpen">
            <DialogScrollContent class="max-h-[85vh] sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ selectedNetworkLabel }}</DialogTitle>
                    <DialogDescription>
                        Quản lý danh sách profile/channel đã kết nối trên nền
                        tảng này.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <Input
                        v-model="accountSearch"
                        placeholder="Tìm theo tên hoặc username..."
                    />
                    <div class="max-h-[55vh] space-y-2 overflow-y-auto pr-1">
                        <p
                            v-if="accountBrowserLoading"
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            Đang tải tài khoản...
                        </p>
                        <div
                            v-for="account in browsedAccounts"
                            :key="account.id"
                            class="flex items-center gap-3 rounded-lg border bg-card p-3"
                        >
                            <img
                                :src="
                                    account.avatar_url ||
                                    getPlatformLogo(account.platform)
                                "
                                :alt="account.display_label"
                                class="size-10 shrink-0 rounded-full object-cover"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">
                                    {{ account.display_label }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    @{{ account.handle_label }}
                                </p>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <span
                                        v-for="capability in getPlatformCapabilities(
                                            account.platform,
                                        )"
                                        :key="capability"
                                        class="rounded-full border px-2 py-0.5 text-[10px] font-medium"
                                        :class="
                                            capability === 'omnichat'
                                                ? 'border-violet-300 text-violet-700'
                                                : 'border-emerald-300 text-emerald-700'
                                        "
                                    >
                                        {{
                                            capability === 'omnichat'
                                                ? 'Omnichat'
                                                : 'Đăng bài'
                                        }}
                                    </span>
                                </div>
                            </div>
                            <Button
                                v-if="account.can_disconnect"
                                variant="destructive"
                                size="sm"
                                class="shrink-0"
                                @click="disconnectAccount(account)"
                            >
                                Xóa
                            </Button>
                            <span
                                v-else
                                class="shrink-0 text-xs text-blue-700 dark:text-blue-300"
                            >
                                Admin chia sẻ
                            </span>
                        </div>
                        <p
                            v-if="
                                !accountBrowserLoading &&
                                browsedAccounts.length === 0
                            "
                            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                        >
                            Không tìm thấy profile/channel.
                        </p>
                    </div>
                </div>
            </DialogScrollContent>
        </Dialog>

        <TelegramConnectDialog v-model:open="telegramOpen" />

        <WordPressConnectDialog
            :open="wordPressOpen"
            @update:open="(v: boolean) => (wordPressOpen = v)"
        />

        <InstagramConnectDialog
            v-model:open="instagramOpen"
            :methods="instagramMethods"
            @select="openOAuthPopup"
        />

        <ConfirmDeleteModal
            ref="disconnectModal"
            :title="$t('accounts.disconnect_modal.title')"
            :description="$t('accounts.disconnect_modal.description')"
            :action="$t('accounts.disconnect_modal.confirm')"
            :cancel="$t('accounts.disconnect_modal.cancel')"
        />
    </div>
</template>
