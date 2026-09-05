<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    IconCalendar,
    IconCheck,
    IconChevronLeft,
    IconChevronRight,
    IconCircleCheck,
    IconClock,
    IconFileText,
    IconPhoto,
    IconSearch,
    IconX,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import AppLayout from '@/layouts/AppLayout.vue';
import { read, readAll } from '@/routes/app/notifications';
import { edit as editPost } from '@/routes/app/posts';
import {
    approve as approveWorkflow,
    reject as rejectWorkflow,
} from '@/routes/app/posts/workflow';

interface Task {
    id: string;
    title: string;
    excerpt: string;
    thumbnail: string | null;
    platform: string | null;
    platform_name: string | null;
    author: string | null;
    created_at: string;
    read_at: string | null;
    post_id: string;
    role: string | null;
    workflow_status: string;
    workflow_note: string | null;
    post_status: string;
}

const props = defineProps<{
    notifications: { id: string; read_at: string | null }[];
    unreadCount: number;
    tasks: Task[];
    filters?: { from?: string; to?: string };
    stats: {
        pending_review: number;
        editing: number;
        approved: number;
        rejected: number;
        published: number;
    };
}>();

type Tab =
    | 'all'
    | 'pending_review'
    | 'editing'
    | 'approved'
    | 'rejected'
    | 'published';
const activeTab = ref<Tab>('all');
const search = ref('');
const sort = ref<'recent' | 'oldest'>('recent');
const fromDate = ref(props.filters?.from ?? '');
const toDate = ref(props.filters?.to ?? '');
const page = ref(1);
const pageSize = 8;
const failedImages = ref<Record<string, boolean>>({});
const processingTaskId = ref<string | null>(null);

const filterByDate = () => {
    router.get(
        window.location.pathname,
        {
            from: fromDate.value || undefined,
            to: toDate.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch([fromDate, toDate], () => filterByDate());

const tabItems: { key: Tab; label: string; count?: number }[] = [
    { key: 'all', label: 'Tất cả', count: props.tasks.length },
    {
        key: 'pending_review',
        label: 'Chờ duyệt',
        count: props.stats.pending_review,
    },
    { key: 'editing', label: 'Đang chỉnh sửa', count: props.stats.editing },
    { key: 'approved', label: 'Đã duyệt', count: props.stats.approved },
    { key: 'rejected', label: 'Đã từ chối', count: props.stats.rejected },
    { key: 'published', label: 'Đã đăng', count: props.stats.published },
];

const filteredTasks = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    const result = props.tasks.filter((task) => {
        const matchesTab =
            activeTab.value === 'all' ||
            (activeTab.value === 'pending_review' &&
                task.workflow_status === 'pending_review') ||
            (activeTab.value === 'editing' &&
                task.workflow_status === 'rejected') ||
            (activeTab.value === 'approved' &&
                task.workflow_status === 'approved') ||
            (activeTab.value === 'rejected' &&
                task.workflow_status === 'rejected') ||
            (activeTab.value === 'published' &&
                ['published', 'partially_published'].includes(
                    task.post_status,
                ));
        const matchesSearch =
            !keyword ||
            task.title.toLowerCase().includes(keyword) ||
            task.excerpt.toLowerCase().includes(keyword) ||
            (task.author ?? '').toLowerCase().includes(keyword) ||
            (task.platform_name ?? '').toLowerCase().includes(keyword);

        return matchesTab && matchesSearch;
    });

    return result.sort((a, b) => {
        const direction = sort.value === 'recent' ? -1 : 1;
        return (
            direction *
            (new Date(a.created_at).getTime() -
                new Date(b.created_at).getTime())
        );
    });
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredTasks.value.length / pageSize)),
);
const pagedTasks = computed(() =>
    filteredTasks.value.slice(
        (page.value - 1) * pageSize,
        page.value * pageSize,
    ),
);

const setTab = (tab: Tab) => {
    activeTab.value = tab;
    page.value = 1;
};

const statusLabel = (task: Task): string => {
    if (task.post_status === 'published') return 'Đã đăng';
    if (task.post_status === 'partially_published') return 'Đăng một phần';
    if (task.workflow_status === 'pending_review') return 'Chờ duyệt';
    if (task.workflow_status === 'approved') return 'Đã duyệt';
    if (task.workflow_status === 'rejected') return 'Đang chỉnh sửa';
    return task.role === 'write' ? 'Đang viết' : 'Bản nháp';
};

const statusClass = (task: Task): string => {
    if (task.post_status === 'published')
        return 'bg-sky-50 text-sky-700 border-sky-200/80';
    if (task.post_status === 'partially_published')
        return 'bg-amber-50 text-amber-700 border-amber-200/80';
    if (task.workflow_status === 'pending_review')
        return 'bg-amber-50 text-amber-800 border-amber-200/80';
    if (task.workflow_status === 'approved')
        return 'bg-emerald-50 text-emerald-700 border-emerald-200/80';
    if (task.workflow_status === 'rejected')
        return 'bg-rose-50 text-rose-700 border-rose-200/80';
    return 'bg-slate-50 text-slate-700 border-slate-200/80';
};

const statusDotClass = (task: Task): string => {
    if (task.post_status === 'published') return 'bg-sky-500';
    if (task.post_status === 'partially_published') return 'bg-amber-500';
    if (task.workflow_status === 'pending_review') return 'bg-amber-500';
    if (task.workflow_status === 'approved') return 'bg-emerald-500';
    if (task.workflow_status === 'rejected') return 'bg-rose-500';
    return 'bg-slate-400';
};

const platformClass = (platform: string | null): string =>
    ({
        facebook: 'bg-blue-600',
        instagram:
            'bg-gradient-to-tr from-amber-500 via-rose-500 to-purple-600',
        tiktok: 'bg-slate-900',
        youtube: 'bg-red-600',
        linkedin: 'bg-sky-700',
    })[platform ?? ''] ?? 'bg-amber-500';

const markRead = (task: Task) => {
    if (!task.read_at)
        router.put(read.url(task.id), {}, { preserveScroll: true });
};

const openTask = (task: Task) => {
    markRead(task);
    router.visit(editPost.url(task.post_id));
};

const approve = (task: Task) => {
    if (processingTaskId.value) return;
    processingTaskId.value = task.id;
    markRead(task);
    router.post(
        approveWorkflow.url(task.post_id),
        {},
        {
            preserveScroll: true,
            onError: (errors: Record<string, string>) => {
                toast.error(
                    Object.values(errors)[0] ?? 'Không thể duyệt bài viết.',
                );
            },
            onFinish: () => {
                processingTaskId.value = null;
            },
        },
    );
};

const reject = (task: Task) => {
    if (processingTaskId.value) return;
    const note = window.prompt(
        'Lý do trả bài:',
        task.workflow_note ?? 'Vui lòng chỉnh sửa lại nội dung.',
    );
    if (note === null) return;
    processingTaskId.value = task.id;
    markRead(task);
    router.post(
        rejectWorkflow.url(task.post_id),
        { note },
        {
            preserveScroll: true,
            onError: (errors: Record<string, string>) => {
                toast.error(Object.values(errors)[0] ?? 'Không thể trả bài.');
            },
            onFinish: () => {
                processingTaskId.value = null;
            },
        },
    );
};

const markImageFailed = (taskId: string) => {
    failedImages.value[taskId] = true;
};
</script>

<template>
    <Head title="Phê duyệt & Thông báo" />
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 px-6 py-8">
            <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <!-- Page Header -->
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1
                            class="text-2xl font-bold tracking-tight text-foreground"
                        >
                            Phê duyệt bài viết
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Quản lý, phê duyệt và theo dõi trạng thái các bài
                            viết trong Workspace.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="unreadCount"
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-border bg-card px-3.5 py-2 text-sm font-medium text-foreground shadow-2xs transition-colors hover:bg-muted"
                            @click="
                                router.post(
                                    readAll.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            "
                        >
                            <IconCheck class="size-4 text-emerald-600" />
                            Đánh dấu đã đọc
                        </button>
                    </div>
                </div>

                <!-- KPI Stats Cards -->
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div
                        class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-2xs transition-colors hover:border-border/80"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-amber-500/10 text-amber-700"
                        >
                            <IconClock class="size-5" />
                        </div>
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Chờ duyệt
                            </p>
                            <p class="text-xl font-bold text-foreground">
                                {{ stats.pending_review }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-2xs transition-colors hover:border-border/80"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-blue-500/10 text-blue-700"
                        >
                            <IconFileText class="size-5" />
                        </div>
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Đang chỉnh sửa
                            </p>
                            <p class="text-xl font-bold text-foreground">
                                {{ stats.editing }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-2xs transition-colors hover:border-border/80"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-700"
                        >
                            <IconCircleCheck class="size-5" />
                        </div>
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Đã duyệt
                            </p>
                            <p class="text-xl font-bold text-foreground">
                                {{ stats.approved }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-2xs transition-colors hover:border-border/80"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-rose-500/10 text-rose-700"
                        >
                            <IconX class="size-5" />
                        </div>
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Đã từ chối
                            </p>
                            <p class="text-xl font-bold text-foreground">
                                {{ stats.rejected }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tabs & Toolbar -->
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <!-- Segmented Tabs -->
                    <div
                        class="inline-flex items-center gap-1 overflow-x-auto rounded-lg border border-border/50 bg-muted/60 p-1"
                    >
                        <button
                            v-for="tab in tabItems"
                            :key="tab.key"
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-all"
                            :class="
                                activeTab === tab.key
                                    ? 'border border-border/60 bg-card font-semibold text-foreground shadow-2xs'
                                    : 'text-muted-foreground hover:bg-card/40 hover:text-foreground'
                            "
                            @click="setTab(tab.key)"
                        >
                            <span>{{ tab.label }}</span>
                            <span
                                v-if="tab.count !== undefined"
                                class="py-0.2 rounded-full px-1.5 text-[10px]"
                                :class="
                                    activeTab === tab.key
                                        ? 'bg-muted text-foreground'
                                        : 'bg-muted/80 text-muted-foreground'
                                "
                            >
                                {{ tab.count }}
                            </span>
                        </button>
                    </div>

                    <!-- Search, Filter, Sort Toolbar -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <div class="relative flex-1 sm:w-64">
                            <IconSearch
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <input
                                v-model="search"
                                class="h-9 w-full rounded-lg border border-input bg-card pr-3 pl-9 text-xs shadow-2xs transition-all outline-none placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20"
                                placeholder="Tìm kiếm bài viết..."
                                @input="page = 1"
                            />
                        </div>

                        <div class="flex items-center gap-1.5">
                            <input
                                v-model="fromDate"
                                type="date"
                                class="h-9 rounded-lg border border-input bg-card px-2.5 text-xs text-foreground shadow-2xs outline-none focus:border-ring focus:ring-2 focus:ring-ring/20"
                            />
                            <span class="text-xs text-muted-foreground">-</span>
                            <input
                                v-model="toDate"
                                type="date"
                                class="h-9 rounded-lg border border-input bg-card px-2.5 text-xs text-foreground shadow-2xs outline-none focus:border-ring focus:ring-2 focus:ring-ring/20"
                            />
                        </div>

                        <select
                            v-model="sort"
                            class="h-9 cursor-pointer rounded-lg border border-input bg-card px-3 text-xs font-medium text-foreground shadow-2xs outline-none focus:border-ring focus:ring-2 focus:ring-ring/20"
                        >
                            <option value="recent">Mới nhất</option>
                            <option value="oldest">Cũ nhất</option>
                        </select>
                    </div>
                </div>

                <!-- Data Table Container -->
                <div
                    class="overflow-hidden rounded-xl border border-border bg-card shadow-2xs"
                >
                    <div
                        v-if="!pagedTasks.length"
                        class="flex flex-col items-center justify-center p-16 text-center"
                    >
                        <div
                            class="mb-3 flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
                        >
                            <IconFileText class="size-6" />
                        </div>
                        <p class="text-sm font-medium text-foreground">
                            Không có bài viết nào phù hợp
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Thử điều chỉnh bộ lọc hoặc từ khóa tìm kiếm.
                        </p>
                    </div>

                    <div v-else class="divide-y divide-border">
                        <div
                            v-for="task in pagedTasks"
                            :key="task.id + '-' + task.role"
                            class="flex flex-col gap-4 p-4 transition-colors hover:bg-muted/30 md:flex-row md:items-center md:justify-between"
                        >
                            <!-- Left: Thumbnail + Info -->
                            <div
                                class="flex min-w-0 flex-1 items-center gap-3.5"
                            >
                                <!-- Platform Icon -->
                                <div
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-white shadow-2xs"
                                    :class="platformClass(task.platform)"
                                >
                                    {{
                                        task.platform === 'facebook'
                                            ? 'f'
                                            : task.platform === 'instagram'
                                              ? 'IG'
                                              : task.platform === 'youtube'
                                                ? 'YT'
                                                : task.platform === 'tiktok'
                                                  ? 'TT'
                                                  : '•'
                                    }}
                                </div>

                                <!-- Thumbnail -->
                                <div
                                    v-if="
                                        task.thumbnail && !failedImages[task.id]
                                    "
                                    class="hidden size-12 shrink-0 overflow-hidden rounded-lg border border-border bg-muted sm:block"
                                >
                                    <img
                                        :src="task.thumbnail"
                                        class="size-full object-cover"
                                        alt=""
                                        @error="markImageFailed(task.id)"
                                    />
                                </div>
                                <div
                                    v-else
                                    class="hidden size-12 shrink-0 items-center justify-center rounded-lg border border-border bg-muted/60 text-muted-foreground/50 sm:flex"
                                >
                                    <IconPhoto class="size-5" />
                                </div>

                                <!-- Content text -->
                                <div class="min-w-0 flex-1">
                                    <button
                                        type="button"
                                        class="block max-w-full cursor-pointer truncate text-left text-sm font-semibold text-foreground transition-colors hover:text-amber-600"
                                        @click="openTask(task)"
                                    >
                                        {{ task.title }}
                                    </button>
                                    <p
                                        class="mt-0.5 truncate text-xs text-muted-foreground"
                                    >
                                        {{ task.excerpt }}
                                    </p>
                                    <div
                                        class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1"
                                        >
                                            <IconCalendar class="size-3" />
                                            {{
                                                new Date(
                                                    task.created_at,
                                                ).toLocaleString('vi-VN')
                                            }}
                                        </span>
                                        <span>•</span>
                                        <span>{{
                                            task.author || 'Tác giả ẩn danh'
                                        }}</span>
                                        <span>•</span>
                                        <span>{{
                                            task.platform_name || 'Tất cả kênh'
                                        }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Status + Actions -->
                            <div
                                class="flex shrink-0 flex-wrap items-center gap-3 md:justify-end"
                            >
                                <!-- Status Badge -->
                                <div class="flex flex-col items-end">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                        :class="statusClass(task)"
                                    >
                                        <span
                                            class="size-1.5 rounded-full"
                                            :class="statusDotClass(task)"
                                        />
                                        {{ statusLabel(task) }}
                                    </span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center gap-1.5">
                                    <template
                                        v-if="
                                            task.role === 'review' &&
                                            task.workflow_status ===
                                                'pending_review'
                                        "
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex h-8 cursor-pointer items-center justify-center rounded-lg bg-emerald-600 px-3 text-xs font-medium text-white shadow-2xs transition-colors hover:bg-emerald-700 disabled:opacity-50"
                                            :disabled="
                                                processingTaskId === task.id
                                            "
                                            @click="approve(task)"
                                        >
                                            <IconCheck class="mr-1 size-3.5" />
                                            {{
                                                processingTaskId === task.id
                                                    ? 'Đang duyệt...'
                                                    : 'Duyệt'
                                            }}
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 cursor-pointer items-center justify-center rounded-lg border border-rose-300 bg-card px-2.5 text-xs font-medium text-rose-700 shadow-2xs transition-colors hover:bg-rose-50 disabled:opacity-50"
                                            :disabled="
                                                processingTaskId === task.id
                                            "
                                            @click="reject(task)"
                                        >
                                            <IconX class="mr-1 size-3.5" />
                                            {{
                                                processingTaskId === task.id
                                                    ? 'Đang gửi...'
                                                    : 'Trả bài'
                                            }}
                                        </button>
                                    </template>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 cursor-pointer items-center justify-center rounded-lg border border-border bg-card px-3 text-xs font-medium text-foreground shadow-2xs transition-colors hover:bg-muted"
                                        @click="openTask(task)"
                                    >
                                        Xem chi tiết
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination Footer -->
                <div
                    class="flex items-center justify-between text-xs text-muted-foreground"
                >
                    <span>
                        Hiển thị
                        {{
                            filteredTasks.length
                                ? (page - 1) * pageSize + 1
                                : 0
                        }}–{{
                            Math.min(page * pageSize, filteredTasks.length)
                        }}
                        trong tổng số {{ filteredTasks.length }} bài viết
                    </span>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            class="inline-flex size-8 cursor-pointer items-center justify-center rounded-lg border border-border bg-card text-foreground shadow-2xs transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                            :disabled="page === 1"
                            @click="page--"
                        >
                            <IconChevronLeft class="size-4" />
                        </button>
                        <span class="px-2 font-medium text-foreground"
                            >{{ page }} / {{ totalPages }}</span
                        >
                        <button
                            type="button"
                            class="inline-flex size-8 cursor-pointer items-center justify-center rounded-lg border border-border bg-card text-foreground shadow-2xs transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                            :disabled="page >= totalPages"
                            @click="page++"
                        >
                            <IconChevronRight class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
