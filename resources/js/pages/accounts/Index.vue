<script setup lang="ts">
import WordPressConnectDialog from '@/components/accounts/WordPressConnectDialog.vue';
import { useWorkspaceEcho } from '@/composables/echo/useWorkspaceEcho';
import { Form, Head, router, useForm } from '@inertiajs/vue3';
import {
    IconCopy,
    IconExternalLink,
    IconFolder,
    IconPencil,
    IconPlus,
    IconRefresh,
    IconSearch,
    IconShare3,
    IconTrash,
    IconUser,
    IconWorldWww,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import { update as updateWebsiteChatConfig } from '@/actions/App/Http/Controllers/App/Omnichat/WebsiteChatController';
import {
    destroy as destroyGroup,
    show as showGroup,
    store as storeGroup,
    update as updateGroup,
} from '@/actions/App/Http/Controllers/App/SocialAccountGroupController';
import {
    destroy as destroyWordPressSite,
    testConnection as testWordPressConnection,
} from '@/actions/App/Http/Controllers/App/WordPressSiteController';
import {
    index as accountsIndex,
    batchDisconnect as batchDisconnectAccounts,
    browser as browseAccounts,
    disconnect as disconnectAccount,
} from '@/actions/App/Http/Controllers/Auth/SocialController';
import { update as updateWebsiteChannelAccess } from '@/actions/App/Http/Controllers/OmnichatChannelAccessController';
import { update as updateAccess } from '@/actions/App/Http/Controllers/SocialAccountAccessController';
import NetworkConnectGrid, {
    type AvailablePlatform,
    type ConnectedAccount,
} from '@/components/accounts/NetworkConnectGrid.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { getPlatformLogo } from '@/composables/usePlatformLogo';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as websiteChat } from '@/routes/app/omnichat/website-chat';

interface WorkspaceMember {
    id: string;
    name: string;
    email: string;
}

interface PageGroupAccount {
    id: string;
    platform: string;
    display_label: string;
    handle_label: string;
    avatar_url: string | null;
}

interface PageGroup {
    id: string;
    name: string;
    social_accounts_count: number;
    social_accounts: PageGroupAccount[];
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface AccountFilters {
    search: string;
    platform: string;
    group: string;
    ownership: 'all' | 'owned' | 'shared';
    group_search: string;
}

interface WebsiteChatChannel {
    id: string;
    name: string;
    status: string;
    authorized_origins: string[];
    public_key: string;
    settings: {
        authorized_origins?: string[];
        welcome_message?: string;
        offline_message?: string;
        primary_color?: string;
        position?: 'left' | 'right';
        privacy_url?: string | null;
    };
    can_share: boolean;
    shared_user_ids: string[];
    shared_user_permissions: Record<
        string,
        {
            can_view_omnichat: boolean;
            can_reply_omnichat: boolean;
            can_assign_conversations: boolean;
        }
    >;
}

export interface WordPressSiteItem {
    id: string;
    name: string;
    url: string;
    username: string;
    status: 'connected' | 'error' | 'disconnected';
    error_message: string | null;
    wp_user_name: string | null;
    categories_count: number;
    tags_count: number;
    last_synced_at: string | null;
}

const props = defineProps<{
    platforms: AvailablePlatform[];
    connectedAccounts: ConnectedAccount[];
    canManageAccounts: boolean;
    members: WorkspaceMember[];
    pageGroups: Paginated<PageGroup> | [];
    groupOptions: { id: string; name: string }[];
    accountPage: Paginated<ConnectedAccount>;
    accountTotals: { all: number; owned: number; shared: number };
    networkAccountCounts: Record<string, number>;
    accountFilters: AccountFilters;
    websiteChatChannels: WebsiteChatChannel[];
    wordPressSites?: WordPressSiteItem[];
}>();

const wordPressOpen = ref(false);
const editingWordPressSite = ref<WordPressSiteItem | null>(null);

const selectedAccount = ref<ConnectedAccount | null>(null);
type PagePermission =
    | 'can_view_omnichat'
    | 'can_reply_omnichat'
    | 'can_assign_conversations'
    | 'can_access_content';
type PagePermissionValues = Record<PagePermission, boolean>;
const pageMemberSearch = ref('');
const pageMemberFilter = ref<'all' | 'shared' | 'unshared'>('all');
const pageMemberPage = ref(1);
const membersPerPermissionPage = 20;
const pageAccessForm = useForm<{
    user_ids: string[];
    permissions: Record<string, PagePermissionValues>;
}>({ user_ids: [], permissions: {} });
const selectedWebsiteChannel = ref<WebsiteChatChannel | null>(null);
const selectedWebsiteConfigChannel = ref<WebsiteChatChannel | null>(null);
const websiteConfigOriginsText = ref('');
const websiteConfigForm = useForm({
    name: '',
    authorized_origins: [] as string[],
    welcome_message: '',
    offline_message: '',
    primary_color: '#2563EB',
    position: 'right' as 'left' | 'right',
    privacy_url: '',
});
type WebsitePermission =
    | 'can_view_omnichat'
    | 'can_reply_omnichat'
    | 'can_assign_conversations';
type WebsitePermissionValues = Record<WebsitePermission, boolean>;
const websiteMemberSearch = ref('');
const websiteMemberFilter = ref<'all' | 'shared' | 'unshared'>('all');
const websiteMemberPage = ref(1);
const websiteMembersPerPage = 20;
const websiteAccessForm = useForm<{
    user_ids: string[];
    permissions: Record<string, WebsitePermissionValues>;
}>({ user_ids: [], permissions: {} });

const syncingAccountIds = ref<string[]>(
    props.connectedAccounts?.filter((a) => a.is_syncing).map((a) => a.id) || [],
);

useWorkspaceEcho<{ account_id: string; name: string; platform: string }>(
    '.account.sync.started',
    (e) => {
        if (!syncingAccountIds.value.includes(e.account_id)) {
            syncingAccountIds.value.push(e.account_id);
        }
    },
);

useWorkspaceEcho<{ account_id: string; name: string; platform: string }>(
    '.account.sync.completed',
    (e) => {
        syncingAccountIds.value = syncingAccountIds.value.filter(
            (id) => id !== e.account_id,
        );
    },
);

useWorkspaceEcho<{
    account_id: string;
    name: string;
    platform: string;
    error_message: string;
}>('.account.sync.failed', (e) => {
    syncingAccountIds.value = syncingAccountIds.value.filter(
        (id) => id !== e.account_id,
    );
});

const syncingAccounts = computed(() => {
    return (
        props.connectedAccounts?.filter((a) =>
            syncingAccountIds.value.includes(a.id),
        ) || []
    );
});

const selectedGroup = ref<PageGroup | null>(null);
const isGroupDialogOpen = ref(false);
const groupName = ref('');
const groupSocialAccountIds = ref<string[]>([]);
const groupMemberSearch = ref('');
const groupAccounts = ref<ConnectedAccount[]>([]);
const groupAccountsLoading = ref(false);
const pageSearch = ref(props.accountFilters.search);
const platformFilter = ref(props.accountFilters.platform);
const groupFilter = ref(props.accountFilters.group);
const ownershipFilter = ref<AccountFilters['ownership']>(
    props.accountFilters.ownership,
);
const groupListSearch = ref(props.accountFilters.group_search);
const selectedAccountIds = ref<string[]>([]);
const isBatchDisconnecting = ref(false);

const isAllSelected = computed(() => {
    if (!props.accountPage?.data?.length) return false;
    return props.accountPage.data.every((a) =>
        selectedAccountIds.value.includes(a.id),
    );
});

const handleSelectAllChange = (event: Event) => {
    const isChecked = (event.target as HTMLInputElement).checked;
    if (isChecked) {
        selectedAccountIds.value = props.accountPage.data.map((a) => a.id);
    } else {
        selectedAccountIds.value = [];
    }
};

const clearSelection = () => {
    selectedAccountIds.value = [];
};

const toggleSelectAccount = (id: string) => {
    if (selectedAccountIds.value.includes(id)) {
        selectedAccountIds.value = selectedAccountIds.value.filter(
            (accountId) => accountId !== id,
        );
    } else {
        selectedAccountIds.value = [...selectedAccountIds.value, id];
    }
};

const executeBatchDisconnect = () => {
    if (selectedAccountIds.value.length === 0) return;
    if (
        confirm(
            `Bạn có chắc chắn muốn ngắt kết nối và XÓA ${selectedAccountIds.value.length} trang đã chọn khỏi hệ thống không?`,
        )
    ) {
        isBatchDisconnecting.value = true;
        router.delete(batchDisconnectAccounts.url(), {
            data: { accounts: selectedAccountIds.value },
            preserveScroll: true,
            onFinish: () => {
                isBatchDisconnecting.value = false;
                selectedAccountIds.value = [];
            },
        });
    }
};

const executeDisconnect = (account: any) => {
    if (
        confirm(
            `Bạn có chắc chắn muốn ngắt kết nối và XÓA trang "${account.display_label}" không?`,
        )
    ) {
        router.delete(disconnectAccount.url(account.id), {
            preserveScroll: true,
        });
    }
};

const openCreateWordPress = (): void => {
    editingWordPressSite.value = null;
    wordPressOpen.value = true;
};

const openEditWordPress = (site: WordPressSiteItem): void => {
    editingWordPressSite.value = site;
    wordPressOpen.value = true;
};

const pageGroupsData = computed(() =>
    Array.isArray(props.pageGroups) ? [] : props.pageGroups.data,
);

const loadGroupAccounts = async (): Promise<void> => {
    groupAccountsLoading.value = true;

    try {
        const response = await fetch(
            browseAccounts.url({
                query: {
                    search: groupMemberSearch.value || undefined,
                    per_page: 50,
                },
            }),
            { headers: { Accept: 'application/json' } },
        );
        const payload = await response.json();
        groupAccounts.value = payload.data ?? [];
    } finally {
        groupAccountsLoading.value = false;
    }
};

const reloadLists = debounce(() => {
    selectedAccountIds.value = [];
    router.get(
        accountsIndex.url(),
        {
            search: pageSearch.value || undefined,
            platform: platformFilter.value || undefined,
            group: groupFilter.value || undefined,
            ownership: ownershipFilter.value,
            group_search: groupListSearch.value || undefined,
        },
        {
            only: ['accountPage', 'pageGroups', 'accountFilters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}, 350);

const openCreateGroup = async (): Promise<void> => {
    selectedGroup.value = null;
    groupName.value = '';
    groupSocialAccountIds.value = [];
    groupMemberSearch.value = '';
    isGroupDialogOpen.value = true;
    await loadGroupAccounts();
};

const openEditGroup = async (group: PageGroup): Promise<void> => {
    selectedGroup.value = group;
    groupName.value = group.name;
    groupMemberSearch.value = '';
    isGroupDialogOpen.value = true;

    const [groupResponse] = await Promise.all([
        fetch(showGroup.url(group.id), {
            headers: { Accept: 'application/json' },
        }),
        loadGroupAccounts(),
    ]);
    const payload = await groupResponse.json();
    groupSocialAccountIds.value = payload.social_account_ids ?? [];
};

const closeGroupDialog = (): void => {
    isGroupDialogOpen.value = false;
    selectedGroup.value = null;
};

const toggleGroupAccount = (accountId: string): void => {
    groupSocialAccountIds.value = groupSocialAccountIds.value.includes(
        accountId,
    )
        ? groupSocialAccountIds.value.filter((id) => id !== accountId)
        : [...groupSocialAccountIds.value, accountId];
};

watch(
    [pageSearch, platformFilter, groupFilter, ownershipFilter, groupListSearch],
    reloadLists,
);
watch(groupMemberSearch, debounce(loadGroupAccounts, 300));

const deleteGroup = (group: PageGroup): void => {
    if (
        !window.confirm(`Xóa nhóm “${group.name}”? Các Page sẽ không bị xóa.`)
    ) {
        return;
    }

    router.delete(destroyGroup.url(group.id), { preserveScroll: true });
};

const pagePermissions = [
    ['can_view_omnichat', 'Xem Omnichat'],
    ['can_reply_omnichat', 'Trả lời khách'],
    ['can_assign_conversations', 'Phân công hội thoại'],
    ['can_access_content', 'Sử dụng trong Content'],
] as const;

const filteredPageMembers = computed(() => {
    const search = pageMemberSearch.value.trim().toLocaleLowerCase();

    return props.members.filter((member) => {
        const isShared = pageAccessForm.user_ids.includes(member.id);
        const matchesFilter =
            pageMemberFilter.value === 'all' ||
            (pageMemberFilter.value === 'shared' && isShared) ||
            (pageMemberFilter.value === 'unshared' && !isShared);

        return (
            matchesFilter &&
            (search === '' ||
                member.name.toLocaleLowerCase().includes(search) ||
                member.email.toLocaleLowerCase().includes(search))
        );
    });
});
const pageMemberLastPage = computed(() =>
    Math.max(
        1,
        Math.ceil(filteredPageMembers.value.length / membersPerPermissionPage),
    ),
);
const visiblePageMembers = computed(() => {
    const offset = (pageMemberPage.value - 1) * membersPerPermissionPage;

    return filteredPageMembers.value.slice(
        offset,
        offset + membersPerPermissionPage,
    );
});

watch([pageMemberSearch, pageMemberFilter], () => {
    pageMemberPage.value = 1;
});
watch(pageMemberLastPage, (lastPage) => {
    pageMemberPage.value = Math.min(pageMemberPage.value, lastPage);
});

const openPageSharing = (account: ConnectedAccount): void => {
    selectedAccount.value = account;
    pageMemberSearch.value = '';
    pageMemberFilter.value = 'all';
    pageMemberPage.value = 1;
    pageAccessForm.user_ids = [...account.shared_user_ids];
    pageAccessForm.permissions = Object.fromEntries(
        account.shared_user_ids.map((userId) => [
            userId,
            {
                can_view_omnichat:
                    account.shared_user_permissions[userId]
                        ?.can_view_omnichat ?? true,
                can_reply_omnichat:
                    account.shared_user_permissions[userId]
                        ?.can_reply_omnichat ?? true,
                can_assign_conversations:
                    account.shared_user_permissions[userId]
                        ?.can_assign_conversations ?? false,
                can_access_content:
                    account.shared_user_permissions[userId]
                        ?.can_access_content ?? true,
            },
        ]),
    );
    pageAccessForm.clearErrors();
};

const togglePageMember = (memberId: string, checked: boolean): void => {
    if (checked) {
        pageAccessForm.user_ids = [
            ...new Set([...pageAccessForm.user_ids, memberId]),
        ];
        pageAccessForm.permissions[memberId] ??= {
            can_view_omnichat: true,
            can_reply_omnichat: true,
            can_assign_conversations: false,
            can_access_content: true,
        };

        return;
    }

    pageAccessForm.user_ids = pageAccessForm.user_ids.filter(
        (id) => id !== memberId,
    );
    delete pageAccessForm.permissions[memberId];
};

const togglePagePermission = (
    memberId: string,
    permission: PagePermission,
    checked: boolean,
): void => {
    pageAccessForm.permissions[memberId] ??= {
        can_view_omnichat: true,
        can_reply_omnichat: true,
        can_assign_conversations: false,
        can_access_content: true,
    };
    pageAccessForm.permissions[memberId][permission] = checked;
};

const submitPageAccess = (): void => {
    if (!selectedAccount.value) {
        return;
    }

    pageAccessForm.put(updateAccess.url(selectedAccount.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedAccount.value = null;
        },
    });
};

const websiteChannelPermissions = [
    ['can_view_omnichat', 'Xem hội thoại'],
    ['can_reply_omnichat', 'Trả lời khách'],
    ['can_assign_conversations', 'Phân công hội thoại'],
] as const;

const filteredWebsiteMembers = computed(() => {
    const search = websiteMemberSearch.value.trim().toLocaleLowerCase();

    return props.members.filter((member) => {
        const isShared = websiteAccessForm.user_ids.includes(member.id);
        const matchesFilter =
            websiteMemberFilter.value === 'all' ||
            (websiteMemberFilter.value === 'shared' && isShared) ||
            (websiteMemberFilter.value === 'unshared' && !isShared);
        const matchesSearch =
            search === '' ||
            member.name.toLocaleLowerCase().includes(search) ||
            member.email.toLocaleLowerCase().includes(search);

        return matchesFilter && matchesSearch;
    });
});
const websiteMemberLastPage = computed(() =>
    Math.max(
        1,
        Math.ceil(filteredWebsiteMembers.value.length / websiteMembersPerPage),
    ),
);
const visibleWebsiteMembers = computed(() => {
    const offset = (websiteMemberPage.value - 1) * websiteMembersPerPage;

    return filteredWebsiteMembers.value.slice(
        offset,
        offset + websiteMembersPerPage,
    );
});

watch([websiteMemberSearch, websiteMemberFilter], () => {
    websiteMemberPage.value = 1;
});
watch(websiteMemberLastPage, (lastPage) => {
    websiteMemberPage.value = Math.min(websiteMemberPage.value, lastPage);
});

const openWebsiteSharing = (channel: WebsiteChatChannel): void => {
    selectedWebsiteChannel.value = channel;
    websiteMemberSearch.value = '';
    websiteMemberFilter.value = 'all';
    websiteMemberPage.value = 1;
    websiteAccessForm.user_ids = [...channel.shared_user_ids];
    websiteAccessForm.permissions = Object.fromEntries(
        channel.shared_user_ids.map((userId) => [
            userId,
            {
                can_view_omnichat:
                    channel.shared_user_permissions[userId]
                        ?.can_view_omnichat ?? true,
                can_reply_omnichat:
                    channel.shared_user_permissions[userId]
                        ?.can_reply_omnichat ?? true,
                can_assign_conversations:
                    channel.shared_user_permissions[userId]
                        ?.can_assign_conversations ?? false,
            },
        ]),
    );
    websiteAccessForm.clearErrors();
};

const toggleWebsiteMember = (memberId: string, checked: boolean): void => {
    if (checked) {
        websiteAccessForm.user_ids = [
            ...new Set([...websiteAccessForm.user_ids, memberId]),
        ];
        websiteAccessForm.permissions[memberId] ??= {
            can_view_omnichat: true,
            can_reply_omnichat: true,
            can_assign_conversations: false,
        };

        return;
    }

    websiteAccessForm.user_ids = websiteAccessForm.user_ids.filter(
        (id) => id !== memberId,
    );
    delete websiteAccessForm.permissions[memberId];
};

const toggleWebsitePermission = (
    memberId: string,
    permission: WebsitePermission,
    checked: boolean,
): void => {
    websiteAccessForm.permissions[memberId] ??= {
        can_view_omnichat: true,
        can_reply_omnichat: true,
        can_assign_conversations: false,
    };
    websiteAccessForm.permissions[memberId][permission] = checked;
};

const submitWebsiteAccess = (): void => {
    if (!selectedWebsiteChannel.value) {
        return;
    }

    websiteAccessForm.put(
        updateWebsiteChannelAccess.url(selectedWebsiteChannel.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedWebsiteChannel.value = null;
            },
        },
    );
};

const openWebsiteConfig = (channel: WebsiteChatChannel): void => {
    selectedWebsiteConfigChannel.value = channel;
    websiteConfigForm.name = channel.name;
    websiteConfigForm.authorized_origins =
        channel.settings.authorized_origins ?? channel.authorized_origins;
    websiteConfigForm.welcome_message =
        channel.settings.welcome_message ??
        'Xin chào! Chúng tôi có thể giúp gì cho bạn?';
    websiteConfigForm.offline_message =
        channel.settings.offline_message ??
        'Hiện chúng tôi đang ngoài giờ làm việc. Vui lòng để lại lời nhắn.';
    websiteConfigForm.primary_color =
        channel.settings.primary_color ?? '#2563EB';
    websiteConfigForm.position = channel.settings.position ?? 'right';
    websiteConfigForm.privacy_url = channel.settings.privacy_url ?? '';
    websiteConfigOriginsText.value =
        websiteConfigForm.authorized_origins.join('\n');
    websiteConfigForm.clearErrors();
};

const submitWebsiteConfig = (): void => {
    if (!selectedWebsiteConfigChannel.value) {
        return;
    }

    websiteConfigForm.authorized_origins = websiteConfigOriginsText.value
        .split('\n')
        .map((origin) => origin.trim())
        .filter(Boolean);

    websiteConfigForm.put(
        updateWebsiteChatConfig.url(selectedWebsiteConfigChannel.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedWebsiteConfigChannel.value = null;
            },
        },
    );
};

const copyWebsiteSnippet = async (
    channel: WebsiteChatChannel,
): Promise<void> => {
    const snippet = `<script src="${window.location.origin}/website-chat/widget.js" data-public-key="${channel.public_key}" async><\/script>`;
    await navigator.clipboard.writeText(snippet);
};
</script>

<template>
    <Head :title="$t('accounts.page_title')" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 px-6 py-8">
            <PageHeader
                :title="$t('accounts.page_title')"
                :description="$t('accounts.description')"
            />

            <section v-if="syncingAccounts.length > 0" class="grid gap-3">
                <div class="flex items-center gap-2">
                    <IconRefresh class="size-5 animate-spin text-primary" />
                    <h2 class="text-lg font-semibold text-primary">
                        Tiến trình đồng bộ
                    </h2>
                    <Badge variant="secondary">{{
                        syncingAccounts.length
                    }}</Badge>
                </div>

                <div
                    class="overflow-hidden rounded-xl border bg-card text-card-foreground shadow-sm"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b bg-muted/50 text-left text-muted-foreground"
                            >
                                <th class="px-4 py-2 font-medium">Tài khoản</th>
                                <th class="px-4 py-2 font-medium">Nền tảng</th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Trạng thái
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="account in syncingAccounts"
                                :key="account.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3 font-medium">
                                    <div class="flex items-center gap-2">
                                        <img
                                            :src="
                                                account.avatar_url ||
                                                getPlatformLogo(
                                                    account.platform,
                                                )
                                            "
                                            class="size-6 rounded-full object-cover"
                                        />
                                        {{ account.display_label }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 text-muted-foreground capitalize"
                                >
                                    {{ account.platform }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <IconRefresh
                                            class="size-4 animate-spin"
                                        />
                                        Đang xử lý...
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <IconWorldWww class="size-5" />
                        <h2 class="text-lg font-semibold">Website Live Chat</h2>
                        <Badge variant="secondary">{{
                            websiteChatChannels.length
                        }}</Badge>
                    </div>
                    <Button
                        v-if="canManageAccounts"
                        size="sm"
                        @click="router.visit(websiteChat.url())"
                    >
                        <IconPlus class="size-4" />
                        Tạo hoặc cấu hình kênh
                    </Button>
                </div>
                <p class="text-sm text-muted-foreground">
                    Kênh chat website được quản lý và chia sẻ quyền giống như
                    Facebook, Zalo hoặc các profile mạng xã hội.
                </p>

                <div
                    v-if="websiteChatChannels.length"
                    class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <article
                        v-for="channel in websiteChatChannels"
                        :key="channel.id"
                        class="flex items-center gap-3 rounded-xl border bg-card p-4"
                    >
                        <div
                            class="grid size-11 shrink-0 place-items-center rounded-full bg-primary/10 text-primary"
                        >
                            <IconWorldWww class="size-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                {{ channel.name }}
                            </p>
                            <p class="truncate text-sm text-muted-foreground">
                                {{
                                    channel.authorized_origins.join(', ') ||
                                    'Chưa cấu hình domain'
                                }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <Button
                                v-if="canManageAccounts"
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="`Sửa cấu hình ${channel.name}`"
                                @click="openWebsiteConfig(channel)"
                            >
                                <IconPencil class="size-4" />
                            </Button>
                            <Button
                                v-if="channel.can_share"
                                variant="outline"
                                size="sm"
                                @click="openWebsiteSharing(channel)"
                            >
                                <IconShare3 class="size-4" /> Chia sẻ
                            </Button>
                        </div>
                    </article>
                </div>
                <button
                    v-else-if="canManageAccounts"
                    type="button"
                    class="rounded-xl border border-dashed p-5 text-left text-sm text-muted-foreground transition-colors hover:bg-muted/50"
                    @click="router.visit(websiteChat.url())"
                >
                    Chưa có Website Live Chat. Nhấn để tạo kênh đầu tiên.
                </button>
            </section>

            <section class="grid gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-6 items-center justify-center rounded-md bg-[#21759B] text-white"
                        >
                            <img
                                src="/images/accounts/wordpress.svg"
                                class="size-4 brightness-0 invert"
                                alt="WP"
                            />
                        </div>
                        <h2 class="text-lg font-semibold">Website WordPress</h2>
                        <Badge variant="secondary">{{
                            wordPressSites?.length || 0
                        }}</Badge>
                    </div>
                    <Button
                        v-if="canManageAccounts"
                        size="sm"
                        class="bg-[#21759B] text-white hover:bg-[#1b6282]"
                        @click="openCreateWordPress"
                    >
                        <IconPlus class="size-4" />
                        Kết nối Website mới
                    </Button>
                </div>
                <p class="text-sm text-muted-foreground">
                    Quản lý và xuất bản bài viết tự động lên các website
                    WordPress đã kết nối.
                </p>

                <div
                    v-if="wordPressSites?.length"
                    class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <article
                        v-for="site in wordPressSites"
                        :key="site.id"
                        class="flex items-center justify-between gap-3 rounded-xl border bg-card p-4 shadow-xs"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <div
                                class="grid size-11 shrink-0 place-items-center rounded-xl bg-cyan-100 p-2 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400"
                            >
                                <img
                                    src="/images/accounts/wordpress.svg"
                                    class="size-6"
                                    alt="WordPress"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm font-semibold">
                                        {{ site.name }}
                                    </p>
                                    <Badge
                                        :variant="
                                            site.status === 'connected'
                                                ? 'secondary'
                                                : 'destructive'
                                        "
                                        class="px-1.5 py-0 text-[10px]"
                                    >
                                        {{
                                            site.status === 'connected'
                                                ? 'Hoạt động'
                                                : 'Lỗi kết nối'
                                        }}
                                    </Badge>
                                </div>
                                <a
                                    :href="site.url"
                                    target="_blank"
                                    class="flex items-center gap-1 truncate text-xs text-muted-foreground hover:underline"
                                >
                                    {{ site.url }}
                                    <IconExternalLink class="size-3" />
                                </a>
                                <p
                                    class="mt-0.5 text-[11px] text-muted-foreground"
                                >
                                    User:
                                    <span class="font-medium text-foreground">{{
                                        site.username
                                    }}</span>
                                    &bull; {{ site.categories_count }} danh mục
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex shrink-0 items-center gap-1.5"
                            v-if="canManageAccounts"
                        >
                            <Button
                                variant="ghost"
                                size="sm"
                                title="Chỉnh sửa cấu hình"
                                @click="openEditWordPress(site)"
                            >
                                <IconPencil
                                    class="size-4 text-muted-foreground"
                                />
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                title="Kiểm tra & Đồng bộ lại"
                                @click="
                                    router.post(
                                        testWordPressConnection.url(site.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                "
                            >
                                <IconRefresh
                                    class="size-4 text-muted-foreground"
                                />
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:bg-destructive/10"
                                title="Xóa kết nối"
                                @click="
                                    router.delete(
                                        destroyWordPressSite.url(site.id),
                                        { preserveScroll: true },
                                    )
                                "
                            >
                                <IconTrash class="size-4" />
                            </Button>
                        </div>
                    </article>
                </div>
                <button
                    v-else-if="canManageAccounts"
                    type="button"
                    class="flex items-center justify-between rounded-xl border border-dashed p-5 text-left text-sm text-muted-foreground transition-colors hover:bg-muted/50"
                    @click="openCreateWordPress"
                >
                    <span
                        >Chưa có Website WordPress nào. Nhấn để kết nối trang
                        web đầu tiên.</span
                    >
                    <Button size="sm" variant="outline">Kết nối ngay</Button>
                </button>
            </section>

            <section v-if="canManageAccounts" class="grid gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <IconFolder class="size-5" />
                        <h2 class="text-lg font-semibold">Nhóm Page</h2>
                        <Badge variant="secondary">{{
                            Array.isArray(pageGroups) ? 0 : pageGroups.total
                        }}</Badge>
                    </div>
                    <Button size="sm" @click="openCreateGroup">
                        <IconPlus class="size-4" />
                        Tạo nhóm
                    </Button>
                </div>
                <p class="text-sm text-muted-foreground">
                    Gom các Page theo khách hàng, thương hiệu hoặc chiến dịch để
                    quản lý và chọn nhanh khi đăng bài.
                </p>

                <label
                    v-if="!Array.isArray(pageGroups)"
                    class="relative max-w-md"
                >
                    <IconSearch
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="groupListSearch"
                        type="search"
                        placeholder="Tìm tên nhóm Page..."
                        class="h-10 w-full rounded-md border bg-background pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-ring"
                    />
                </label>

                <div
                    v-if="pageGroupsData.length"
                    class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <article
                        v-for="group in pageGroupsData"
                        :key="group.id"
                        class="grid gap-3 rounded-xl border bg-card p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold">
                                    {{ group.name }}
                                </h3>
                                <p class="text-sm text-muted-foreground">
                                    {{ group.social_accounts_count }} Page
                                </p>
                            </div>
                            <div class="flex gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    :aria-label="`Sửa nhóm ${group.name}`"
                                    @click="openEditGroup(group)"
                                >
                                    <IconPencil class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    :aria-label="`Xóa nhóm ${group.name}`"
                                    @click="deleteGroup(group)"
                                >
                                    <IconTrash
                                        class="size-4 text-destructive"
                                    />
                                </Button>
                            </div>
                        </div>

                        <div
                            v-if="group.social_accounts.length"
                            class="flex -space-x-2"
                        >
                            <img
                                v-for="account in group.social_accounts.slice(
                                    0,
                                    6,
                                )"
                                :key="account.id"
                                :src="
                                    account.avatar_url ||
                                    getPlatformLogo(account.platform)
                                "
                                :alt="account.display_label"
                                :title="account.display_label"
                                class="size-9 rounded-full border-2 border-card bg-card object-cover"
                            />
                            <span
                                v-if="group.social_accounts_count > 6"
                                class="inline-flex size-9 items-center justify-center rounded-full border-2 border-card bg-muted text-xs font-semibold"
                            >
                                +{{ group.social_accounts_count - 6 }}
                            </span>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            Nhóm chưa có Page.
                        </p>
                    </article>
                </div>
                <button
                    v-else
                    type="button"
                    class="rounded-xl border border-dashed p-5 text-left text-sm text-muted-foreground transition-colors hover:bg-muted/50"
                    @click="openCreateGroup"
                >
                    Chưa có nhóm nào. Nhấn để tạo nhóm Page đầu tiên.
                </button>

                <div
                    v-if="
                        !Array.isArray(pageGroups) && pageGroups.last_page > 1
                    "
                    class="flex flex-wrap items-center justify-center gap-1.5"
                >
                    <Button
                        v-for="(link, index) in pageGroups.links.slice(1, -1)"
                        :key="link.label + '-' + index"
                        :variant="link.active ? 'default' : 'outline'"
                        size="icon-sm"
                        :disabled="!link.url"
                        :aria-current="link.active ? 'page' : undefined"
                        @click="
                            link.url &&
                            router.visit(link.url, {
                                only: ['pageGroups'],
                                preserveState: true,
                                preserveScroll: true,
                            })
                        "
                    >
                        {{ link.label }}
                    </Button>
                </div>
            </section>

            <section class="grid gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <IconUser class="size-5" />
                        <h2 class="text-lg font-semibold">Quản lý Page</h2>
                        <Badge variant="secondary">{{
                            accountPage.total
                        }}</Badge>
                    </div>
                    <div class="flex rounded-lg border p-1">
                        <button
                            v-for="option in [
                                { value: 'all', label: 'Tất cả' },
                                { value: 'owned', label: 'Page của tôi' },
                                { value: 'shared', label: 'Được chia sẻ' },
                            ]"
                            :key="option.value"
                            type="button"
                            class="rounded-md px-3 py-1.5 text-xs font-medium"
                            :class="
                                ownershipFilter === option.value
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-muted'
                            "
                            @click="
                                ownershipFilter =
                                    option.value as AccountFilters['ownership']
                            "
                        >
                            {{ option.label }} ({{
                                accountTotals[
                                    option.value as keyof typeof accountTotals
                                ]
                            }})
                        </button>
                    </div>
                </div>

                <div
                    class="grid gap-2 md:grid-cols-[minmax(0,1fr)_180px_220px]"
                >
                    <label class="relative">
                        <IconSearch
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="pageSearch"
                            type="search"
                            placeholder="Tìm tên Page hoặc username..."
                            class="h-10 w-full rounded-md border bg-background pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-ring"
                        />
                    </label>
                    <select
                        v-model="platformFilter"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Tất cả nền tảng</option>
                        <option
                            v-for="platform in platforms"
                            :key="platform.value"
                            :value="platform.value"
                        >
                            {{ platform.label }}
                        </option>
                    </select>
                    <select
                        v-if="canManageAccounts"
                        v-model="groupFilter"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Tất cả nhóm</option>
                        <option
                            v-for="group in groupOptions"
                            :key="group.id"
                            :value="group.id"
                        >
                            {{ group.name }}
                        </option>
                    </select>
                </div>

                <div v-if="accountPage.data.length" class="space-y-4">
                    <!-- Select All & Batch Action Toolbar -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border-2 border-foreground/20 bg-muted/60 p-3.5 shadow-2xs"
                    >
                        <div class="flex items-center gap-3">
                            <label
                                class="flex cursor-pointer items-center gap-2.5 select-none"
                            >
                                <input
                                    id="select-all-accounts"
                                    type="checkbox"
                                    class="size-5 cursor-pointer rounded border-2 border-foreground/40 accent-primary"
                                    :checked="isAllSelected"
                                    @change="handleSelectAllChange"
                                />
                                <span class="text-xs font-bold text-foreground">
                                    Chọn tất cả trang trên trang này ({{
                                        accountPage.data.length
                                    }}
                                    trang)
                                </span>
                            </label>
                            <Badge
                                v-if="selectedAccountIds.length > 0"
                                class="bg-primary px-2.5 py-0.5 text-xs font-black text-primary-foreground"
                            >
                                Đã chọn {{ selectedAccountIds.length }} /
                                {{ accountPage.data.length }}
                            </Badge>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                v-if="selectedAccountIds.length > 0"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 cursor-pointer text-xs font-semibold"
                                @click="clearSelection"
                            >
                                Bỏ chọn
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                :disabled="
                                    selectedAccountIds.length === 0 ||
                                    isBatchDisconnecting
                                "
                                class="h-8 cursor-pointer gap-1.5 text-xs font-bold shadow-xs"
                                :class="{
                                    'cursor-not-allowed opacity-50':
                                        selectedAccountIds.length === 0,
                                }"
                                @click="executeBatchDisconnect"
                            >
                                <IconTrash class="size-3.5" />
                                <span>{{
                                    isBatchDisconnecting
                                        ? 'Đang xóa...'
                                        : `HỦY KẾT NỐI & XÓA (${selectedAccountIds.length})`
                                }}</span>
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div
                            v-for="account in accountPage.data"
                            :key="account.id"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border-2 bg-card p-3.5 transition-all select-none hover:border-primary/50"
                            :class="
                                selectedAccountIds.includes(account.id)
                                    ? 'border-primary bg-primary/10 shadow-xs'
                                    : 'border-foreground/10'
                            "
                            @click="toggleSelectAccount(account.id)"
                        >
                            <input
                                type="checkbox"
                                class="size-4.5 shrink-0 cursor-pointer rounded border-2 border-foreground/40 accent-primary"
                                :checked="
                                    selectedAccountIds.includes(account.id)
                                "
                                @click.stop
                                @change="toggleSelectAccount(account.id)"
                            />
                            <img
                                :src="
                                    account.avatar_url ||
                                    getPlatformLogo(account.platform)
                                "
                                :alt="account.display_label"
                                class="size-11 shrink-0 rounded-full border border-foreground/10 object-cover"
                                loading="lazy"
                            />
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-bold text-foreground"
                                >
                                    {{ account.display_label }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    @{{ account.handle_label }}
                                </p>
                            </div>
                            <div
                                class="flex shrink-0 items-center gap-1.5"
                                @click.stop
                            >
                                <Button
                                    v-if="account.can_share"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-8 text-xs font-medium"
                                    @click="openPageSharing(account)"
                                >
                                    <IconShare3 class="size-3.5" /> Chia sẻ
                                </Button>
                                <Button
                                    v-if="canManageAccounts"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 cursor-pointer px-2 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    title="Ngắt kết nối / Xóa trang này"
                                    @click="executeDisconnect(account)"
                                >
                                    <IconTrash class="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="rounded-xl border border-dashed p-5 text-sm text-muted-foreground"
                >
                    Không tìm thấy Page phù hợp với bộ lọc.
                </div>

                <div
                    v-if="accountPage.last_page > 1"
                    class="grid justify-items-center gap-2"
                >
                    <div
                        class="flex flex-wrap items-center justify-center gap-1.5"
                    >
                        <Button
                            v-for="(link, index) in accountPage.links.slice(
                                1,
                                -1,
                            )"
                            :key="link.label + '-' + index"
                            :variant="link.active ? 'default' : 'outline'"
                            size="icon-sm"
                            :disabled="!link.url"
                            :aria-current="link.active ? 'page' : undefined"
                            @click="
                                link.url &&
                                router.visit(link.url, {
                                    only: ['accountPage'],
                                    preserveState: true,
                                    preserveScroll: true,
                                })
                            "
                        >
                            {{ link.label }}
                        </Button>
                    </div>
                    <span class="text-sm text-muted-foreground">
                        {{ accountPage.total }} Page
                    </span>
                </div>
            </section>

            <div class="grid gap-2">
                <h2 class="text-lg font-semibold">Kết nối thêm</h2>
                <p class="text-sm text-muted-foreground">
                    Kết nối Page/Profile/Channel thuộc quyền quản lý của bạn.
                </p>
            </div>

            <NetworkConnectGrid
                :platforms="platforms"
                :connected-accounts="connectedAccounts"
                :network-account-counts="networkAccountCounts"
            />
        </div>

        <Dialog
            :open="selectedWebsiteConfigChannel !== null"
            @update:open="
                (open) => !open && (selectedWebsiteConfigChannel = null)
            "
        >
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Chỉnh sửa Website Live Chat</DialogTitle>
                    <DialogDescription>
                        Cập nhật cấu hình widget chat nhúng trên website.
                    </DialogDescription>
                </DialogHeader>
                <form
                    v-if="selectedWebsiteConfigChannel"
                    class="grid gap-4"
                    @submit.prevent="submitWebsiteConfig"
                >
                    <div class="grid gap-2">
                        <Label for="website-chat-name">Tên kênh</Label>
                        <Input
                            id="website-chat-name"
                            v-model="websiteConfigForm.name"
                        />
                        <p
                            v-if="websiteConfigForm.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ websiteConfigForm.errors.name }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="website-chat-origins"
                            >Domain được phép, mỗi dòng một origin</Label
                        >
                        <Textarea
                            id="website-chat-origins"
                            v-model="websiteConfigOriginsText"
                            rows="3"
                        />
                        <p
                            v-if="websiteConfigForm.errors.authorized_origins"
                            class="text-sm text-destructive"
                        >
                            {{ websiteConfigForm.errors.authorized_origins }}
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="website-chat-welcome">Lời chào</Label>
                            <Textarea
                                id="website-chat-welcome"
                                v-model="websiteConfigForm.welcome_message"
                                rows="3"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="website-chat-offline"
                                >Tin ngoài giờ</Label
                            >
                            <Textarea
                                id="website-chat-offline"
                                v-model="websiteConfigForm.offline_message"
                                rows="3"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="website-chat-color">Màu chính</Label>
                            <Input
                                id="website-chat-color"
                                v-model="websiteConfigForm.primary_color"
                                type="color"
                                class="h-10"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="website-chat-position">Vị trí</Label>
                            <select
                                id="website-chat-position"
                                v-model="websiteConfigForm.position"
                                class="h-10 rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="right">Bên phải</option>
                                <option value="left">Bên trái</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="website-chat-privacy"
                                >URL quyền riêng tư</Label
                            >
                            <Input
                                id="website-chat-privacy"
                                v-model="websiteConfigForm.privacy_url"
                                type="url"
                            />
                        </div>
                    </div>

                    <div class="rounded-lg border bg-muted/40 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="truncate font-mono text-xs">
                                {{ selectedWebsiteConfigChannel.public_key }}
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="
                                    copyWebsiteSnippet(
                                        selectedWebsiteConfigChannel,
                                    )
                                "
                            >
                                <IconCopy class="size-4" /> Copy mã nhúng
                            </Button>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="selectedWebsiteConfigChannel = null"
                            >Hủy</Button
                        >
                        <Button
                            type="submit"
                            :disabled="websiteConfigForm.processing"
                            >Lưu cấu hình</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="selectedAccount !== null"
            @update:open="(open) => !open && (selectedAccount = null)"
        >
            <DialogContent
                class="flex !w-[calc(100vw-2rem)] !max-w-7xl flex-col overflow-x-hidden sm:!w-[calc(100vw-3rem)]"
            >
                <DialogHeader>
                    <DialogTitle
                        >Chia sẻ
                        {{ selectedAccount?.display_label }}</DialogTitle
                    >
                    <DialogDescription
                        >Chọn thành viên được phép vận hành Page và sử dụng các
                        module trên Page này.</DialogDescription
                    >
                </DialogHeader>
                <form
                    v-if="selectedAccount"
                    class="flex min-h-0 flex-1 flex-col gap-4"
                    @submit.prevent="submitPageAccess"
                >
                    <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                        <div class="relative">
                            <IconSearch
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="pageMemberSearch"
                                class="pl-9"
                                placeholder="Tìm theo tên hoặc email..."
                            />
                        </div>
                        <div
                            class="flex rounded-lg border bg-muted/30 p-1 text-sm"
                        >
                            <button
                                v-for="option in [
                                    ['all', 'Tất cả'],
                                    ['shared', 'Đã chia sẻ'],
                                    ['unshared', 'Chưa chia sẻ'],
                                ] as const"
                                :key="option[0]"
                                type="button"
                                class="rounded-md px-3 py-1.5 transition-colors"
                                :class="
                                    pageMemberFilter === option[0]
                                        ? 'bg-background font-medium shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="pageMemberFilter = option[0]"
                            >
                                {{ option[1] }}
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">
                            {{ filteredPageMembers.length }} thành viên
                        </span>
                        <Badge variant="secondary">
                            {{ pageAccessForm.user_ids.length }} được chia sẻ
                        </Badge>
                    </div>

                    <div class="min-h-0 overflow-y-auto rounded-lg border">
                        <div
                            class="sticky top-0 z-10 hidden grid-cols-[minmax(12rem,1fr)_repeat(4,9rem)] gap-2 border-b bg-muted/95 px-3 py-2 text-xs font-medium backdrop-blur lg:grid"
                        >
                            <span>Thành viên</span>
                            <span
                                v-for="[, label] in pagePermissions"
                                :key="label"
                                class="text-center"
                                >{{ label }}</span
                            >
                        </div>
                        <div
                            v-for="member in visiblePageMembers"
                            :key="member.id"
                            class="grid gap-3 border-b p-3 last:border-b-0 hover:bg-muted/50 lg:grid-cols-[minmax(12rem,1fr)_repeat(4,9rem)] lg:items-center"
                        >
                            <label
                                class="flex cursor-pointer items-center gap-3"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        pageAccessForm.user_ids.includes(
                                            member.id,
                                        )
                                    "
                                    class="size-4 rounded border-input"
                                    @change="
                                        togglePageMember(
                                            member.id,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                />
                                <span class="min-w-0">
                                    <span
                                        class="block truncate text-sm font-medium"
                                        >{{ member.name }}</span
                                    >
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{ member.email }}</span
                                    >
                                </span>
                            </label>
                            <div
                                class="grid grid-cols-2 gap-2 pl-7 text-xs lg:col-span-4 lg:contents"
                            >
                                <label
                                    v-for="[
                                        permission,
                                        label,
                                    ] in pagePermissions"
                                    :key="permission"
                                    class="flex items-center gap-2 lg:justify-center"
                                    :class="{
                                        'opacity-40':
                                            !pageAccessForm.user_ids.includes(
                                                member.id,
                                            ),
                                    }"
                                >
                                    <input
                                        type="checkbox"
                                        :disabled="
                                            !pageAccessForm.user_ids.includes(
                                                member.id,
                                            )
                                        "
                                        :checked="
                                            pageAccessForm.permissions[
                                                member.id
                                            ]?.[permission] ?? false
                                        "
                                        class="size-3.5 rounded border-input"
                                        @change="
                                            togglePagePermission(
                                                member.id,
                                                permission,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                    <span class="lg:hidden">{{ label }}</span>
                                </label>
                            </div>
                        </div>
                        <p
                            v-if="visiblePageMembers.length === 0"
                            class="p-8 text-center text-sm text-muted-foreground"
                        >
                            Không tìm thấy thành viên phù hợp.
                        </p>
                    </div>
                    <div
                        v-if="pageMemberLastPage > 1"
                        class="flex items-center justify-between gap-3"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="pageMemberPage === 1"
                            @click="pageMemberPage--"
                            >Trang trước</Button
                        >
                        <span class="text-sm text-muted-foreground"
                            >Trang {{ pageMemberPage }} /
                            {{ pageMemberLastPage }}</span
                        >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="pageMemberPage === pageMemberLastPage"
                            @click="pageMemberPage++"
                            >Trang sau</Button
                        >
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="selectedAccount = null"
                            >Huỷ</Button
                        >
                        <Button
                            type="submit"
                            :disabled="pageAccessForm.processing"
                            >{{
                                pageAccessForm.processing
                                    ? 'Đang lưu...'
                                    : 'Lưu phân quyền'
                            }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="selectedWebsiteChannel !== null"
            @update:open="(open) => !open && (selectedWebsiteChannel = null)"
        >
            <DialogContent
                class="flex !w-[calc(100vw-2rem)] !max-w-6xl flex-col overflow-x-hidden sm:!w-[calc(100vw-3rem)]"
            >
                <DialogHeader>
                    <DialogTitle>
                        Chia sẻ {{ selectedWebsiteChannel?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Chọn thành viên được xem, trả lời hoặc phân công hội
                        thoại của kênh Website Live Chat này.
                    </DialogDescription>
                </DialogHeader>
                <form
                    v-if="selectedWebsiteChannel"
                    class="flex min-h-0 flex-1 flex-col gap-4"
                    @submit.prevent="submitWebsiteAccess"
                >
                    <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                        <div class="relative">
                            <IconSearch
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="websiteMemberSearch"
                                class="pl-9"
                                placeholder="Tìm theo tên hoặc email..."
                            />
                        </div>
                        <div
                            class="flex rounded-lg border bg-muted/30 p-1 text-sm"
                        >
                            <button
                                v-for="option in [
                                    ['all', 'Tất cả'],
                                    ['shared', 'Đã chia sẻ'],
                                    ['unshared', 'Chưa chia sẻ'],
                                ] as const"
                                :key="option[0]"
                                type="button"
                                class="rounded-md px-3 py-1.5 transition-colors"
                                :class="
                                    websiteMemberFilter === option[0]
                                        ? 'bg-background font-medium shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="websiteMemberFilter = option[0]"
                            >
                                {{ option[1] }}
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">
                            {{ filteredWebsiteMembers.length }} thành viên
                        </span>
                        <Badge variant="secondary">
                            {{ websiteAccessForm.user_ids.length }} được chia sẻ
                        </Badge>
                    </div>

                    <div class="min-h-0 overflow-y-auto rounded-lg border">
                        <div
                            class="sticky top-0 z-10 hidden grid-cols-[minmax(12rem,1fr)_repeat(3,8rem)] gap-2 border-b bg-muted/95 px-3 py-2 text-xs font-medium backdrop-blur sm:grid"
                        >
                            <span>Thành viên</span>
                            <span
                                v-for="[, label] in websiteChannelPermissions"
                                :key="label"
                                class="text-center"
                                >{{ label }}</span
                            >
                        </div>
                        <div
                            v-for="member in visibleWebsiteMembers"
                            :key="member.id"
                            class="grid gap-3 border-b p-3 last:border-b-0 hover:bg-muted/50 sm:grid-cols-[minmax(12rem,1fr)_repeat(3,8rem)] sm:items-center"
                        >
                            <label
                                class="flex cursor-pointer items-center gap-3"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        websiteAccessForm.user_ids.includes(
                                            member.id,
                                        )
                                    "
                                    class="size-4 rounded border-input"
                                    @change="
                                        toggleWebsiteMember(
                                            member.id,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                />
                                <span class="min-w-0">
                                    <span
                                        class="block truncate text-sm font-medium"
                                        >{{ member.name }}</span
                                    >
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{ member.email }}</span
                                    >
                                </span>
                            </label>
                            <div
                                class="grid grid-cols-2 gap-2 pl-7 text-xs sm:col-span-3 sm:contents"
                            >
                                <label
                                    v-for="[
                                        permission,
                                        label,
                                    ] in websiteChannelPermissions"
                                    :key="permission"
                                    class="flex items-center gap-2 sm:justify-center"
                                    :class="{
                                        'opacity-40':
                                            !websiteAccessForm.user_ids.includes(
                                                member.id,
                                            ),
                                    }"
                                >
                                    <input
                                        type="checkbox"
                                        :disabled="
                                            !websiteAccessForm.user_ids.includes(
                                                member.id,
                                            )
                                        "
                                        :checked="
                                            websiteAccessForm.permissions[
                                                member.id
                                            ]?.[permission] ?? false
                                        "
                                        class="size-3.5 rounded border-input"
                                        @change="
                                            toggleWebsitePermission(
                                                member.id,
                                                permission,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                    <span class="sm:hidden">{{ label }}</span>
                                </label>
                            </div>
                        </div>
                        <p
                            v-if="visibleWebsiteMembers.length === 0"
                            class="p-8 text-center text-sm text-muted-foreground"
                        >
                            Không tìm thấy thành viên phù hợp.
                        </p>
                    </div>
                    <div
                        v-if="websiteMemberLastPage > 1"
                        class="flex items-center justify-between gap-3"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="websiteMemberPage === 1"
                            @click="websiteMemberPage--"
                            >Trang trước</Button
                        >
                        <span class="text-sm text-muted-foreground">
                            Trang {{ websiteMemberPage }} /
                            {{ websiteMemberLastPage }}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="
                                websiteMemberPage === websiteMemberLastPage
                            "
                            @click="websiteMemberPage++"
                            >Trang sau</Button
                        >
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="selectedWebsiteChannel = null"
                            >Huỷ</Button
                        >
                        <Button
                            type="submit"
                            :disabled="websiteAccessForm.processing"
                            >{{
                                websiteAccessForm.processing
                                    ? 'Đang lưu...'
                                    : 'Lưu phân quyền'
                            }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="isGroupDialogOpen"
            @update:open="(open) => !open && closeGroupDialog()"
        >
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            selectedGroup
                                ? 'Chỉnh sửa nhóm Page'
                                : 'Tạo nhóm Page'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Đặt tên nhóm rồi chọn các Page muốn đưa vào nhóm. Một
                        Page có thể thuộc nhiều nhóm.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    :action="
                        selectedGroup
                            ? updateGroup(selectedGroup.id)
                            : storeGroup()
                    "
                    class="grid gap-4"
                    #default="{ errors, processing }"
                    @success="closeGroupDialog"
                >
                    <input
                        v-for="accountId in groupSocialAccountIds"
                        :key="accountId"
                        type="hidden"
                        name="social_account_ids[]"
                        :value="accountId"
                    />
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium">Tên nhóm</span>
                        <input
                            v-model="groupName"
                            name="name"
                            maxlength="100"
                            required
                            placeholder="Ví dụ: Khách hàng F&B miền Nam"
                            class="h-10 rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring"
                        />
                        <span
                            v-if="errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ errors.name }}
                        </span>
                    </label>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium">
                                Page trong nhóm
                            </span>
                            <Badge variant="secondary">
                                {{ groupSocialAccountIds.length }} đã chọn
                            </Badge>
                        </div>
                        <label class="relative">
                            <IconSearch
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <input
                                v-model="groupMemberSearch"
                                type="search"
                                placeholder="Tìm theo tên Page, username hoặc nền tảng..."
                                class="h-10 w-full rounded-md border bg-background pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-ring"
                            />
                        </label>

                        <div
                            class="max-h-80 space-y-1 overflow-y-auto rounded-lg border p-2"
                        >
                            <p
                                v-if="groupAccountsLoading"
                                class="p-4 text-center text-sm text-muted-foreground"
                            >
                                Đang tải Page...
                            </p>
                            <label
                                v-for="account in groupAccounts"
                                :key="account.id"
                                class="flex cursor-pointer items-center gap-3 rounded-md p-2 hover:bg-muted"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        groupSocialAccountIds.includes(
                                            account.id,
                                        )
                                    "
                                    class="size-4 rounded border-input"
                                    @change="toggleGroupAccount(account.id)"
                                />
                                <img
                                    :src="
                                        account.avatar_url ||
                                        getPlatformLogo(account.platform)
                                    "
                                    :alt="account.display_label"
                                    class="size-9 rounded-full object-cover"
                                />
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-sm font-medium"
                                    >
                                        {{ account.display_label }}
                                    </span>
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                    >
                                        @{{ account.handle_label }} ·
                                        {{ account.platform }}
                                    </span>
                                </span>
                            </label>
                            <p
                                v-if="
                                    !groupAccountsLoading &&
                                    groupAccounts.length === 0
                                "
                                class="p-4 text-center text-sm text-muted-foreground"
                            >
                                Không tìm thấy Page phù hợp.
                            </p>
                        </div>
                        <span
                            v-if="errors.social_account_ids"
                            class="text-sm text-destructive"
                        >
                            {{ errors.social_account_ids }}
                        </span>
                        <p class="text-xs text-muted-foreground">
                            API trả tối đa 50 kết quả mỗi lần. Hãy tìm theo tên
                            hoặc username để thêm Page khác.
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeGroupDialog"
                        >
                            Hủy
                        </Button>
                        <Button type="submit" :disabled="processing">
                            {{ selectedGroup ? 'Lưu thay đổi' : 'Tạo nhóm' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <WordPressConnectDialog
            :open="wordPressOpen"
            :site="editingWordPressSite"
            @update:open="(v: boolean) => (wordPressOpen = v)"
        />
    </AppLayout>
</template>
