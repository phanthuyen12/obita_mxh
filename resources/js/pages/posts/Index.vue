<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import {
    IconAdjustmentsHorizontal,
    IconChevronDown,
    IconChevronLeft,
    IconChevronRight,
    IconCopy,
    IconCopyPlus,
    IconDots,
    IconFileText,
    IconFolder,
    IconSearch,
    IconTrash,
    IconX,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

import {
    create as createPost,
    destroy as destroyPost,
    duplicate as duplicatePost,
    edit as editPost,
    index as postsIndex,
    show as showPost,
} from '@/actions/App/Http/Controllers/App/PostController';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import LabelBadge from '@/components/labels/LabelBadge.vue';
import LabelFilter from '@/components/labels/LabelFilter.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableLoadMore,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useWorkspaceEcho } from '@/composables/echo/useWorkspaceEcho';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import { getPostStatusConfig } from '@/composables/usePostStatus';
import { useWorkspaceRole } from '@/composables/useWorkspaceRole';
import date from '@/date';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { copyToClipboard } from '@/lib/utils';
import { PostStatus } from '@/types/post';
interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    display_label: string;
    avatar_url: string | null;
}

interface PostPlatform {
    id: string;
    social_account_id: string;
    enabled: boolean;
    platform: string;
    status: string;
    social_account: SocialAccount | null;
}

interface Label {
    id: string;
    name: string;
    color: string;
}

interface Post {
    id: string;
    content: string | null;
    status: string;
    is_ceo_content?: boolean;
    topic_tags?: string[];
    scheduled_at: string | null;
    published_at: string | null;
    post_platforms: PostPlatform[];
    labels: Label[];
    folder?: { id: string; name: string } | null;
}

interface ScrollPosts {
    data: Post[];
    links?: { url: string | null; label: string; active: boolean }[];
    current_page?: number;
    last_page?: number;
    from?: number | null;
    to?: number | null;
    total?: number;
    meta?: {
        hasNextPage: boolean;
    };
}

interface Workspace {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    posts: ScrollPosts;
    currentStatus: string | null;
    labels: Label[];
    topicTags?: {
        id: string;
        name: string;
        type: 'topic' | 'tag';
        color: string;
    }[];
    folders: { id: string; name: string }[];
    contentWorkflows: { id: string; name: string }[];
    platformOptions: { value: string; label: string }[];
    filters: {
        search: string;
        labels: string[];
        from?: string;
        to?: string;
        status?: string;
        platform?: string;
        folder_id?: string;
        workflow_id?: string;
        workflow_status?: string;
        topic_tag?: string;
    };
}

const props = defineProps<Props>();
const availableTopicTags = computed(() => props.topicTags ?? []);

const searchQuery = ref(props.filters.search);
const selectedLabelIds = ref<string[]>(props.filters.labels ?? []);
const fromDate = ref(props.filters.from ?? '');
const toDate = ref(props.filters.to ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const selectedPlatform = ref(props.filters.platform ?? '');
const selectedFolderId = ref(props.filters.folder_id ?? '');
const selectedWorkflowId = ref(props.filters.workflow_id ?? '');
const selectedWorkflowStatus = ref(props.filters.workflow_status ?? '');
const selectedTopicTag = ref(props.filters.topic_tag ?? '');
const filtersOpen = ref(false);

const buildFilterUrl = () => {
    const url = postsIndex.url();
    router.get(
        url,
        {
            search: searchQuery.value || undefined,
            labels: selectedLabelIds.value.length
                ? selectedLabelIds.value
                : undefined,
            from: fromDate.value || undefined,
            to: toDate.value || undefined,
            status: selectedStatus.value || undefined,
            platform: selectedPlatform.value || undefined,
            folder_id: selectedFolderId.value || undefined,
            workflow_id: selectedWorkflowId.value || undefined,
            workflow_status: selectedWorkflowStatus.value || undefined,
            topic_tag: selectedTopicTag.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const search = debounce(buildFilterUrl, 300);

watch(searchQuery, () => search());
watch(selectedLabelIds, () => buildFilterUrl(), { deep: true });
watch([fromDate, toDate], () => buildFilterUrl());
watch(
    [
        selectedStatus,
        selectedPlatform,
        selectedFolderId,
        selectedWorkflowId,
        selectedWorkflowStatus,
        selectedTopicTag,
    ],
    () => buildFilterUrl(),
);

const pageTitle = computed(() => {
    if (props.currentStatus) {
        return trans(`posts.status.${props.currentStatus}`);
    }
    return trans('posts.all_posts');
});

const formatDateTime = (value: string | null): string => {
    if (!value) return '—';
    return date.formatDateTime(value);
};

const getEnabledPlatforms = (post: Post) =>
    post.post_platforms.filter((pp) => pp.enabled);

const getPostPreview = (post: Post): string =>
    post.content?.trim() || trans('calendar.no_content');

const EDITABLE_STATUSES: readonly string[] = [
    PostStatus.Draft,
    PostStatus.Scheduled,
];
const DELETABLE_STATUSES: readonly string[] = [
    PostStatus.Draft,
    PostStatus.Scheduled,
    PostStatus.Failed,
];
const canEdit = (post: Post): boolean =>
    EDITABLE_STATUSES.includes(post.status);
const canDelete = (post: Post): boolean =>
    DELETABLE_STATUSES.includes(post.status);

const { canCreatePost } = useWorkspaceRole();

const postUrl = (post: Post): string =>
    canEdit(post) ? editPost.url(post.id) : showPost.url(post.id);

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const handleDelete = (post: Post) => {
    deleteModal.value?.open({
        url: destroyPost.url(post.id),
        confirmText: trans('common.confirm_modal.delete_keyword'),
    });
};

const handleDuplicate = (post: Post) => {
    router.post(duplicatePost.url(post.id));
};

const handleCopyId = (post: Post) =>
    copyToClipboard(post.id, trans('posts.actions.copied'));

const hasActiveSearch = computed(() => Boolean(searchQuery.value?.trim()));

const activeFilterCount = computed(
    () =>
        [
            selectedStatus.value,
            selectedPlatform.value,
            selectedFolderId.value,
            selectedWorkflowId.value,
            selectedWorkflowStatus.value,
            selectedTopicTag.value,
            fromDate.value || toDate.value,
            selectedLabelIds.value.length ? 'labels' : '',
        ].filter(Boolean).length,
);
const hasActiveFilters = computed(
    () => hasActiveSearch.value || activeFilterCount.value > 0,
);
const clearFilters = () => {
    searchQuery.value = '';
    selectedLabelIds.value = [];
    fromDate.value = '';
    toDate.value = '';
    selectedStatus.value = '';
    selectedPlatform.value = '';
    selectedFolderId.value = '';
    selectedWorkflowId.value = '';
    selectedWorkflowStatus.value = '';
    selectedTopicTag.value = '';
};

const visitPage = (url: string | null) => {
    if (url) {
        router.visit(url, { preserveScroll: true, preserveState: true });
    }
};

const pageLabel = (label: string): string =>
    label.replace('&laquo;', '').replace('&raquo;', '').trim();

const refreshPosts = () => router.reload({ only: ['posts'], reset: ['posts'] });

useWorkspaceEcho(
    ['.post.created', '.post.deleted', '.post.platform.status.updated'],
    refreshPosts,
);
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 px-6 py-8">
            <PageHeader :title="pageTitle" />

            <section class="rounded-xl border bg-card shadow-sm">
                <div
                    class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="relative min-w-0 flex-1 lg:max-w-xl">
                        <IconSearch
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground/60"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Tìm theo nội dung bài viết…"
                            class="w-full pl-9"
                        />
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            class="flex-1 lg:flex-none"
                            @click="filtersOpen = !filtersOpen"
                        >
                            <IconAdjustmentsHorizontal class="size-4" />
                            Bộ lọc
                            <Badge
                                v-if="activeFilterCount"
                                class="ml-1 min-w-5 justify-center px-1.5"
                                >{{ activeFilterCount }}</Badge
                            >
                            <IconChevronDown
                                class="size-4 transition-transform"
                                :class="filtersOpen ? 'rotate-180' : ''"
                            />
                        </Button>
                        <Button
                            v-if="hasActiveFilters"
                            variant="ghost"
                            class="text-muted-foreground"
                            @click="clearFilters"
                        >
                            <IconX class="size-4" /> Xóa lọc
                        </Button>
                        <Link v-if="canCreatePost" :href="createPost.url()">
                            <Button>{{ $t('posts.new_post') }}</Button>
                        </Link>
                    </div>
                </div>
                <div
                    v-if="filtersOpen || activeFilterCount"
                    class="border-t bg-muted/20 p-4"
                >
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <label class="space-y-1.5">
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                                >Trạng thái đăng</span
                            >
                            <select
                                v-model="selectedStatus"
                                class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">Tất cả trạng thái</option>
                                <option value="draft">Bản nháp</option>
                                <option value="scheduled">Đã lên lịch</option>
                                <option value="publishing">Đang đăng</option>
                                <option value="published">Đã đăng</option>
                                <option value="partially_published">
                                    Đăng một phần
                                </option>
                                <option value="failed">Thất bại</option>
                            </select>
                        </label>
                        <label class="space-y-1.5">
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                                >Nền tảng</span
                            >
                            <select
                                v-model="selectedPlatform"
                                class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">Tất cả nền tảng</option>
                                <option
                                    v-for="option in platformOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                        <label class="space-y-1.5">
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                                >Thư mục</span
                            >
                            <select
                                v-model="selectedFolderId"
                                class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">Tất cả thư mục</option>
                                <option value="unfiled">
                                    Chưa xếp thư mục
                                </option>
                                <option
                                    v-for="folder in folders"
                                    :key="folder.id"
                                    :value="folder.id"
                                >
                                    {{ folder.name }}
                                </option>
                            </select>
                        </label>
                        <label class="space-y-1.5">
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                                >Workflow</span
                            >
                            <select
                                v-model="selectedWorkflowId"
                                class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">Tất cả workflow</option>
                                <option value="none">Không có workflow</option>
                                <option
                                    v-for="workflow in contentWorkflows"
                                    :key="workflow.id"
                                    :value="workflow.id"
                                >
                                    {{ workflow.name }}
                                </option>
                            </select>
                        </label>
                        <label class="space-y-1.5">
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                                >Trạng thái duyệt</span
                            >
                            <select
                                v-model="selectedWorkflowStatus"
                                class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option value="">
                                    Tất cả trạng thái duyệt
                                </option>
                                <option value="draft">Đang soạn</option>
                                <option value="pending_review">
                                    Chờ duyệt
                                </option>
                                <option value="approved">Đã duyệt</option>
                                <option value="rejected">Yêu cầu sửa</option>
                            </select>
                        </label>
                        <div
                            v-if="availableTopicTags.length"
                            class="space-y-2 sm:col-span-2 xl:col-span-4"
                        >
                            <span
                                class="block text-xs font-semibold text-muted-foreground"
                                >Thẻ bài viết</span
                            >
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                                    :class="
                                        selectedTopicTag === ''
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'bg-background hover:bg-muted'
                                    "
                                    @click="selectedTopicTag = ''"
                                >
                                    Tất cả thẻ
                                </button>
                                <button
                                    v-for="tag in availableTopicTags"
                                    :key="tag.id"
                                    type="button"
                                    class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                                    :class="
                                        selectedTopicTag === tag.name
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'bg-background hover:bg-muted'
                                    "
                                    @click="selectedTopicTag = tag.name"
                                >
                                    #{{ tag.name }}
                                </button>
                            </div>
                        </div>
                        <div
                            v-else
                            class="rounded-md border border-dashed p-3 text-xs text-muted-foreground sm:col-span-2 xl:col-span-4"
                        >
                            Chưa có thẻ nào. Hãy tạo thẻ trong mục “Thẻ bài
                            viết” CEO”.
                        </div>
                        <label class="space-y-1.5">
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                                >Tạo từ ngày</span
                            >
                            <Input
                                v-model="fromDate"
                                type="date"
                                class="h-10"
                            />
                        </label>
                        <label class="space-y-1.5">
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                                >Đến ngày</span
                            >
                            <Input v-model="toDate" type="date" class="h-10" />
                        </label>
                        <div v-if="labels.length" class="space-y-1.5">
                            <span
                                class="block text-xs font-semibold text-muted-foreground"
                                >Nhãn bài viết</span
                            >
                            <LabelFilter
                                v-model="selectedLabelIds"
                                :labels="labels"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <EmptyState
                v-if="posts.data.length === 0"
                :icon="IconFileText"
                :title="
                    hasActiveFilters
                        ? $t('posts.no_search_results')
                        : $t('posts.no_posts')
                "
                :description="
                    hasActiveFilters
                        ? $t('posts.try_different_search')
                        : $t('posts.start_creating')
                "
            />

            <div v-if="posts.data.length">
                <InfiniteScroll
                    data="posts"
                    items-element="#posts-body"
                    preserve-url
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{
                                    $t('posts.table.post')
                                }}</TableHead>
                                <TableHead>{{
                                    $t('posts.table.status')
                                }}</TableHead>
                                <TableHead>{{
                                    $t('posts.table.scheduled_at')
                                }}</TableHead>
                                <TableHead class="text-right">{{
                                    $t('posts.table.actions')
                                }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody id="posts-body">
                            <TableRow
                                v-for="post in posts.data"
                                :key="post.id"
                                class="cursor-pointer"
                                draggable="true"
                                @dragstart="
                                    $event.dataTransfer?.setData(
                                        'application/x-post-id',
                                        post.id,
                                    )
                                "
                                @click="router.visit(postUrl(post))"
                            >
                                <TableCell class="max-w-md py-3">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <div
                                                v-if="
                                                    getEnabledPlatforms(post)
                                                        .length
                                                "
                                                class="flex -space-x-1.5"
                                            >
                                                <TooltipProvider
                                                    v-for="pp in getEnabledPlatforms(
                                                        post,
                                                    ).slice(0, 4)"
                                                    :key="pp.id"
                                                    :delay-duration="200"
                                                >
                                                    <Tooltip>
                                                        <TooltipTrigger
                                                            as-child
                                                        >
                                                            <span
                                                                class="inline-flex size-6 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-card shadow-2xs"
                                                            >
                                                                <img
                                                                    :src="
                                                                        getPlatformLogo(
                                                                            pp.platform,
                                                                        )
                                                                    "
                                                                    :alt="
                                                                        pp.platform
                                                                    "
                                                                    class="size-full object-cover"
                                                                />
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <div
                                                                class="space-y-0.5 text-xs"
                                                            >
                                                                <p
                                                                    class="font-semibold"
                                                                >
                                                                    {{
                                                                        pp
                                                                            .social_account
                                                                            ?.display_label ??
                                                                        pp.platform
                                                                    }}<span
                                                                        v-if="
                                                                            pp
                                                                                .social_account
                                                                                ?.username
                                                                        "
                                                                        class="font-normal opacity-80"
                                                                        >&nbsp;·&nbsp;@{{
                                                                            pp
                                                                                .social_account
                                                                                .username
                                                                        }}</span
                                                                    >
                                                                </p>
                                                                <p
                                                                    class="opacity-70"
                                                                >
                                                                    {{
                                                                        getPlatformLabel(
                                                                            pp.platform,
                                                                        )
                                                                    }}
                                                                </p>
                                                            </div>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            </div>
                                            <span
                                                v-if="
                                                    getEnabledPlatforms(post)
                                                        .length > 4
                                                "
                                                class="text-xs font-bold text-foreground/60"
                                                >+{{
                                                    getEnabledPlatforms(post)
                                                        .length - 4
                                                }}</span
                                            >
                                            <div
                                                v-if="
                                                    post.is_ceo_content ||
                                                    post.labels?.length ||
                                                    post.topic_tags?.length
                                                "
                                                class="ml-1 flex flex-wrap items-center gap-1"
                                            >
                                                <Badge
                                                    v-if="post.is_ceo_content"
                                                    class="border-amber-300 bg-amber-100 text-[10px] text-amber-900"
                                                >
                                                    👑 CEO
                                                </Badge>
                                                <Badge
                                                    v-for="tag in post.topic_tags ||
                                                    []"
                                                    :key="tag"
                                                    variant="outline"
                                                    class="text-[10px]"
                                                >
                                                    #{{ tag }}
                                                </Badge>
                                                <LabelBadge
                                                    v-for="label in post.labels.slice(
                                                        0,
                                                        3,
                                                    )"
                                                    :key="label.id"
                                                    :label="label"
                                                />
                                                <span
                                                    v-if="
                                                        post.labels.length > 3
                                                    "
                                                    class="text-xs font-bold text-foreground/60"
                                                >
                                                    +{{
                                                        post.labels.length - 3
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                        <p class="truncate text-foreground/80">
                                            {{ getPostPreview(post) }}
                                        </p>
                                        <span
                                            v-if="post.folder"
                                            class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                                            ><IconFolder class="size-3.5" />
                                            {{ post.folder.name }}</span
                                        >
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            getPostStatusConfig(post.status)
                                                .variant
                                        "
                                    >
                                        <component
                                            :is="
                                                getPostStatusConfig(post.status)
                                                    .icon
                                            "
                                            class="size-3"
                                        />
                                        {{
                                            getPostStatusConfig(post.status)
                                                .label
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    {{
                                        formatDateTime(
                                            post.scheduled_at ??
                                                post.published_at,
                                        )
                                    }}
                                </TableCell>
                                <TableCell class="text-right" @click.stop>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                class="size-8"
                                                @click.stop
                                            >
                                                <IconDots class="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                v-if="canCreatePost"
                                                @click="handleDuplicate(post)"
                                            >
                                                <IconCopyPlus class="size-4" />
                                                {{
                                                    $t(
                                                        'posts.actions.duplicate',
                                                    )
                                                }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="handleCopyId(post)"
                                            >
                                                <IconCopy class="size-4" />
                                                {{
                                                    $t('posts.actions.copy_id')
                                                }}
                                            </DropdownMenuItem>
                                            <template
                                                v-if="
                                                    canCreatePost &&
                                                    canDelete(post)
                                                "
                                            >
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    @click="handleDelete(post)"
                                                >
                                                    <IconTrash class="size-4" />
                                                    {{
                                                        $t(
                                                            'posts.actions.delete',
                                                        )
                                                    }}
                                                </DropdownMenuItem>
                                            </template>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <template #next="{ loading }">
                        <TableLoadMore v-if="loading" />
                    </template>
                </InfiniteScroll>

                <!-- Phân trang trang (Pagination controls) -->
                <div
                    v-if="posts.last_page && posts.last_page > 1"
                    class="flex flex-wrap items-center justify-between gap-3 border-t pt-4"
                >
                    <p class="text-xs text-muted-foreground">
                        Hiển thị {{ posts.from }}–{{ posts.to }} trong tổng số
                        {{ posts.total }} bài viết
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            v-for="link in posts.links"
                            :key="link.label"
                            type="button"
                            :disabled="!link.url || link.active"
                            :class="[
                                'min-w-9 rounded-md border px-2 py-1.5 text-xs font-semibold transition-colors',
                                link.active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'bg-background text-foreground hover:bg-muted',
                                !link.url
                                    ? 'cursor-not-allowed opacity-40'
                                    : '',
                            ]"
                            @click="visitPage(link.url)"
                        >
                            <IconChevronLeft
                                v-if="link.label.includes('laquo')"
                                class="mx-auto size-4"
                            />
                            <IconChevronRight
                                v-else-if="link.label.includes('raquo')"
                                class="mx-auto size-4"
                            />
                            <span v-else>{{ pageLabel(link.label) }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <ConfirmDeleteModal
        ref="deleteModal"
        :title="$t('posts.edit.delete_modal.title')"
        :description="$t('posts.edit.delete_modal.description')"
        :action="$t('posts.edit.delete_modal.action')"
        :cancel="$t('posts.edit.delete_modal.cancel')"
    />
</template>
