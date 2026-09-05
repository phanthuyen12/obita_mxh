<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    IconAffiliate,
    IconAlertTriangle,
    IconBell,
    IconCalendar,
    IconChartBar,
    IconChevronRight,
    IconCopy,
    IconFileText,
    IconFolder,
    IconGitPullRequest,
    IconHash,
    IconLayoutSidebarLeftCollapse,
    IconLayoutSidebarLeftExpand,
    IconLifebuoy,
    IconMessages,
    IconPencil,
    IconPhoto,
    IconPlugConnected,
    IconSelector,
    IconSettingsAutomation,
    IconTag,
    IconTrendingUp,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import { index as omnichatLeads } from '@/actions/App/Http/Controllers/App/Omnichat/LeadController';
import {
    create as createPost,
    index as postsIndex,
} from '@/actions/App/Http/Controllers/App/PostController';
import NavMain from '@/components/NavMain.vue';
import NavSupport from '@/components/NavSupport.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import SidebarOnboarding from '@/components/onboarding/SidebarOnboarding.vue';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    useSidebar,
} from '@/components/ui/sidebar';
import WorkspaceMenuContent from '@/components/WorkspaceMenuContent.vue';
import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import { accounts, analytics, calendar } from '@/routes/app';
import { edit as aiSettings } from '@/routes/app/ai-settings';
import { index as assets } from '@/routes/app/assets';
import { portal } from '@/routes/app/billing';
import { index as contentClones } from '@/routes/app/content-clones';
import { index as contentWorkflows } from '@/routes/app/content-workflows';
import { manage as manageFolders } from '@/routes/app/folders';
import { index as labels } from '@/routes/app/labels';
import { index as mcp } from '@/routes/app/mcp';
import { index as notifications } from '@/routes/app/notifications';
import {
    index as omnichat,
    analytics as omnichatAnalytics,
} from '@/routes/app/omnichat';
import { index as postAnalytics } from '@/routes/app/post-analytics';
import { index as postTags } from '@/routes/app/post-tags';
import { index as signatures } from '@/routes/app/signatures';
import type { NavItem, User } from '@/types';

interface Workspace {
    id: string;
    name: string;
    logo_url: string | null;
}

const page = usePage();
const user = computed(() => page.props.auth.user as User);
const currentWorkspace = computed<Workspace | null>(
    () => page.props.auth.currentWorkspace as Workspace | null,
);
const workspaces = computed<Workspace[]>(
    () => page.props.auth.workspaces as Workspace[],
);
const subscriptionPastDue = computed<boolean>(() =>
    Boolean(page.props.auth.subscriptionPastDue),
);

const {
    canCreatePost,
    canManageAccounts,
    canViewAccounts,
    canManageAutomations,
    canCreateWorkspace,
    canManageTeam,
    canManageBilling,
} = useWorkspaceRole();
const { isMobile, state, toggleSidebar } = useSidebar();

const omnichatNavItems = computed<NavItem[]>(() => [
    {
        title: trans('omnichat.inbox.title'),
        href: omnichat.url(),
        icon: IconMessages,
        badge: trans('common.beta'),
        children: [
            {
                title: trans('omnichat.navigation.customers'),
                href: omnichatLeads.url(),
            },
            {
                title: trans('omnichat.navigation.interactions'),
                href: omnichat({ query: { section: 'interactions' } }).url,
                activeQuery: 'interactions',
            },
            {
                title: trans('omnichat.navigation.inbox'),
                href: omnichat({ query: { section: 'inbox' } }).url,
                activeQuery: 'inbox',
            },
            {
                title: 'Thống kê Omnichat',
                href: omnichatAnalytics.url(),
            },
        ],
    },
]);

const contentNavItems = computed<NavItem[]>(() => [
    {
        title: trans('sidebar.posts.calendar'),
        href: calendar.url(),
        icon: IconCalendar,
    },
    {
        title: 'Thông báo',
        href: notifications.url(),
        icon: IconBell,
    },
    {
        title: trans('sidebar.posts.all'),
        href: postsIndex.url(),
        icon: IconFileText,
        excludeActive: [postsIndex.url('draft')],
    },
    {
        title: trans('sidebar.posts.drafts'),
        href: postsIndex.url('draft'),
        icon: IconPencil,
    },
    ...(canCreatePost.value
        ? [
              {
                  title: 'Clone nội dung',
                  href: contentClones.url(),
                  icon: IconCopy,
              },
          ]
        : []),
    ...(canManageTeam.value
        ? [
              {
                  title: 'Luồng nội dung',
                  href: contentWorkflows.url(),
                  icon: IconGitPullRequest,
              },
          ]
        : []),
    ...(canCreatePost.value
        ? [
              {
                  title: trans('sidebar.workspace.signatures'),
                  href: signatures.url(),
                  icon: IconHash,
              },
              {
                  title: trans('sidebar.workspace.labels'),
                  href: labels.url(),
                  icon: IconTag,
              },
              {
                  title: 'Thẻ bài viết',
                  href: postTags.url(),
                  icon: IconTag,
              },
          ]
        : []),
]);

const analyticsNavItems = computed<NavItem[]>(() => [
    ...(canManageAccounts.value
        ? [
              {
                  title: trans('sidebar.analytics'),
                  href: analytics.url(),
                  icon: IconChartBar,
              },
              {
                  title: 'Phân tích bài viết',
                  href: postAnalytics.url(),
                  icon: IconTrendingUp,
              },
          ]
        : []),
]);

const workspaceNavItems = computed<NavItem[]>(() => [
    ...(canViewAccounts.value
        ? [
              {
                  title: trans('sidebar.workspace.connections'),
                  href: accounts.url(),
                  icon: IconAffiliate,
              },
          ]
        : []),
    ...(canCreatePost.value
        ? [
              {
                  title: trans('sidebar.workspace.assets'),
                  href: assets.url(),
                  icon: IconPhoto,
              },
              {
                  title: 'Quản lý & chia sẻ thư mục',
                  href: manageFolders.url(),
                  icon: IconFolder,
              },
          ]
        : []),
    {
        title: trans('sidebar.workspace.mcp'),
        href: mcp.url(),
        icon: IconPlugConnected,
    },
    ...(canManageBilling.value
        ? [
              {
                  title: 'Cấu hình AI',
                  href: aiSettings.url(),
                  icon: IconSettingsAutomation,
              },
          ]
        : []),
]);

const bottomNavItems = computed(() => [
    {
        title: trans('sidebar.support.docs'),
        href: 'https://docs.trypost.it',
        icon: IconLifebuoy,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <div
                        class="flex items-center gap-1 group-data-[collapsible=icon]:justify-center"
                    >
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <SidebarMenuButton
                                    size="lg"
                                    class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                                    data-test="sidebar-menu-button"
                                    data-testid="sidebar-workspace-menu"
                                    dusk="sidebar-workspace-menu"
                                >
                                    <Avatar
                                        :src="currentWorkspace?.logo_url"
                                        :name="currentWorkspace?.name ?? '?'"
                                        class="h-8 w-8 shrink-0 rounded-md border border-border"
                                        fallback-class="bg-amber-500/20 text-amber-950 font-semibold"
                                    />
                                    <div
                                        class="grid min-w-0 flex-1 text-left text-sm leading-tight"
                                    >
                                        <span class="truncate font-semibold">
                                            {{
                                                currentWorkspace?.name ??
                                                $t('sidebar.select_workspace')
                                            }}
                                        </span>
                                    </div>
                                    <component
                                        :is="
                                            isMobile
                                                ? IconSelector
                                                : IconChevronRight
                                        "
                                        class="ml-auto size-4 group-data-[collapsible=icon]:hidden"
                                    />
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                class="w-(--reka-dropdown-menu-trigger-width) min-w-64"
                                align="start"
                                :side="isMobile ? 'bottom' : 'right'"
                                :side-offset="4"
                            >
                                <WorkspaceMenuContent
                                    :user="user"
                                    :current-workspace="currentWorkspace"
                                    :workspaces="workspaces"
                                    :can-create-workspace="canCreateWorkspace"
                                />
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <div class="group-data-[collapsible=icon]:hidden">
                            <NotificationBell v-if="currentWorkspace" />
                        </div>
                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-px">
            <div
                v-if="currentWorkspace && canCreatePost"
                class="px-2 py-2 group-data-[collapsible=icon]:px-2"
            >
                <Link :href="createPost.url()" class="block">
                    <Button
                        class="w-full group-data-[collapsible=icon]:size-8 group-data-[collapsible=icon]:p-0"
                        :title="$t('sidebar.create_post')"
                        :aria-label="$t('sidebar.create_post')"
                    >
                        <IconPencil class="size-4 shrink-0" />
                        <span class="group-data-[collapsible=icon]:hidden">
                            {{ $t('sidebar.create_post') }}
                        </span>
                    </Button>
                </Link>
            </div>

            <!-- Omnichat -->
            <NavMain
                v-if="currentWorkspace"
                :items="omnichatNavItems"
                label="Omnichat"
            />

            <!-- Content: Lịch, Bài viết, Clone, Luồng, Chữ ký, Nhãn -->
            <NavMain
                v-if="currentWorkspace"
                :items="contentNavItems"
                :label="$t('sidebar.groups.posts')"
            />

            <!-- Phân tích -->
            <NavMain
                v-if="currentWorkspace && analyticsNavItems.length"
                :items="analyticsNavItems"
                label="Phân tích"
            />

            <!-- Workspace: Kết nối, Tài nguyên, Thư mục, MCP, AI -->
            <NavMain
                v-if="currentWorkspace && workspaceNavItems.length"
                :items="workspaceNavItems"
                :label="$t('sidebar.groups.workspace')"
            />

            <div class="mt-auto">
                <NavSupport
                    v-if="currentWorkspace"
                    :items="bottomNavItems"
                    :label="$t('sidebar.groups.others')"
                />
            </div>
        </SidebarContent>
        <SidebarFooter>
            <SidebarOnboarding v-if="currentWorkspace" />

            <div
                v-if="subscriptionPastDue"
                dusk="past-due-notice"
                class="mx-1 mb-1 rounded-md border-2 border-destructive bg-destructive/10 p-3 group-data-[collapsible=icon]:hidden"
            >
                <div class="flex items-center gap-2 text-destructive">
                    <IconAlertTriangle class="size-4 shrink-0" />
                    <span class="text-sm font-semibold">{{
                        $t('billing.past_due_notice.title')
                    }}</span>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ $t('billing.past_due_notice.description') }}
                </p>
                <Button
                    as="a"
                    :href="portal.url()"
                    variant="destructive"
                    size="sm"
                    class="mt-2 w-full"
                    dusk="past-due-cta"
                >
                    {{ $t('billing.past_due_notice.cta') }}
                </Button>
            </div>
        </SidebarFooter>
        <SidebarRail />
        <Button
            variant="outline"
            size="icon"
            class="absolute top-5 -right-3 z-30 hidden size-6 rounded-full border-sidebar-border bg-card text-muted-foreground shadow-none hover:bg-muted hover:text-foreground md:inline-flex"
            :title="
                state === 'expanded' ? 'Thu gọn sidebar' : 'Mở rộng sidebar'
            "
            :aria-label="
                state === 'expanded' ? 'Thu gọn sidebar' : 'Mở rộng sidebar'
            "
            @click="toggleSidebar"
        >
            <IconLayoutSidebarLeftCollapse
                v-if="state === 'expanded'"
                class="size-3.5"
            />
            <IconLayoutSidebarLeftExpand v-else class="size-3.5" />
        </Button>
    </Sidebar>
</template>
