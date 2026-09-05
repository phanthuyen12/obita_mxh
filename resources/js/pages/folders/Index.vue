<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    IconLoader2,
    IconLockAccess,
    IconPlus,
    IconSearch,
    IconTrash,
} from '@tabler/icons-vue';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import FolderManager from '@/components/folders/FolderManager.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { subjects, update as updateFolder } from '@/routes/app/folders';
import {
    index as permissionIndex,
    update as updatePermissions,
} from '@/routes/app/folders/permissions';
import type { FolderItem, FolderPermissionItem } from '@/types/folder';

interface Subject {
    id: string;
    name: string;
    email?: string;
}
const props = defineProps<{
    folders: FolderItem[];
    permissionOptions: { value: string; label: string }[];
    canManageAllFolders: boolean;
}>();
const getInitialFolderId = () => {
    if (typeof window !== 'undefined') {
        const folderId = new URLSearchParams(window.location.search).get(
            'folder',
        );
        if (folderId && props.folders.some((f) => f.id === folderId)) {
            return folderId;
        }
    }
    return (
        props.folders.find((folder) => folder.can?.manage)?.id ??
        props.folders[0]?.id ??
        null
    );
};
const selectedId = ref<string | null>(getInitialFolderId());
const selected = computed(
    () =>
        props.folders.find((folder) => folder.id === selectedId.value) ?? null,
);
const grants = ref<FolderPermissionItem[]>([]);
const permissionLoading = ref(false);
const subjectType = ref<'user' | 'team'>('user');
const subjectId = ref('');
const subjectSearch = ref('');
const subjectResults = ref<Subject[]>([]);
const subjectPage = ref(1);
const subjectLastPage = ref(1);
const subjectLoading = ref(false);
const subjectLoadingMore = ref(false);
const hasMoreSubjects = computed(
    () => subjectPage.value < subjectLastPage.value,
);
const selectedPermissions = ref<string[]>(['view']);
const groupedGrants = computed(() => {
    const groups = new Map<
        string,
        {
            key: string;
            user_id: string | null;
            team_id: string | null;
            name: string;
            email?: string;
            type: 'user' | 'team';
            permissions: string[];
        }
    >();

    for (const grant of grants.value) {
        const key = grant.user_id
            ? `user:${grant.user_id}`
            : `team:${grant.team_id}`;
        const existing = groups.get(key);

        if (existing) {
            if (!existing.permissions.includes(grant.permission))
                existing.permissions.push(grant.permission);
            continue;
        }

        groups.set(key, {
            key,
            user_id: grant.user_id,
            team_id: grant.team_id,
            name: grant.user?.name ?? grant.team?.name ?? 'Không xác định',
            email: grant.user?.email,
            type: grant.user ? 'user' : 'team',
            permissions: [grant.permission],
        });
    }

    return [...groups.values()];
});
const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';
const refresh = () =>
    router.reload({ only: ['folders'], preserveScroll: true });
const loadPermissions = async () => {
    if (!selectedId.value || !selected.value?.can?.manage) {
        grants.value = [];
        return;
    }
    permissionLoading.value = true;
    try {
        const response = await fetch(permissionIndex.url(selectedId.value), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        grants.value = response.ok ? (await response.json()).data : [];
    } finally {
        permissionLoading.value = false;
    }
};
const fetchSubjects = async (page = 1, append = false) => {
    append ? (subjectLoadingMore.value = true) : (subjectLoading.value = true);
    try {
        const response = await fetch(
            subjects.url({
                query: {
                    type: subjectType.value,
                    search: subjectSearch.value || undefined,
                    page,
                },
            }),
            {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        if (!response.ok) return;
        const payload = await response.json();
        subjectResults.value = append
            ? [...subjectResults.value, ...payload.data]
            : payload.data;
        subjectPage.value = payload.current_page;
        subjectLastPage.value = payload.last_page;
    } finally {
        subjectLoading.value = false;
        subjectLoadingMore.value = false;
    }
};
const debouncedSubjectSearch = debounce(() => void fetchSubjects(), 300);
watch(subjectSearch, debouncedSubjectSearch);
watch(subjectType, () => {
    subjectId.value = '';
    subjectSearch.value = '';
    void fetchSubjects();
});
const selectFolder = (folder: FolderItem) => {
    selectedId.value = folder.id;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('folder', folder.id);
        window.history.replaceState(window.history.state, '', url);
    }
    grants.value = [];
    void loadPermissions();
};
const toggleSelectedPermission = (
    permissionValue: string,
    checked: boolean,
) => {
    selectedPermissions.value = checked
        ? [...new Set([...selectedPermissions.value, permissionValue])]
        : selectedPermissions.value.filter(
              (value) => value !== permissionValue,
          );
};
const addGrant = () => {
    if (!subjectId.value || selectedPermissions.value.length === 0) return;
    const subject = subjectResults.value.find(
        (item) => item.id === subjectId.value,
    );

    grants.value = grants.value.filter((grant) =>
        subjectType.value === 'user'
            ? grant.user_id !== subjectId.value
            : grant.team_id !== subjectId.value,
    );

    grants.value.push(
        ...selectedPermissions.value.map((permissionValue) => ({
            id: crypto.randomUUID(),
            permission: permissionValue,
            user_id: subjectType.value === 'user' ? subjectId.value : null,
            team_id: subjectType.value === 'team' ? subjectId.value : null,
            user: subjectType.value === 'user' ? (subject ?? null) : null,
            team: subjectType.value === 'team' ? (subject ?? null) : null,
        })),
    );
    subjectId.value = '';
    selectedPermissions.value = ['view'];
};
const toggleGroupPermission = (
    groupKey: string,
    permissionValue: string,
    checked: boolean,
) => {
    const group = groupedGrants.value.find((item) => item.key === groupKey);
    if (!group) return;

    if (checked) {
        if (group.permissions.includes(permissionValue)) return;
        grants.value.push({
            id: crypto.randomUUID(),
            permission: permissionValue,
            user_id: group.user_id,
            team_id: group.team_id,
            user:
                group.type === 'user'
                    ? {
                          id: group.user_id!,
                          name: group.name,
                          email: group.email ?? '',
                      }
                    : null,
            team:
                group.type === 'team'
                    ? { id: group.team_id!, name: group.name }
                    : null,
        });
        return;
    }

    grants.value = grants.value.filter(
        (grant) =>
            !(
                grant.permission === permissionValue &&
                grant.user_id === group.user_id &&
                grant.team_id === group.team_id
            ),
    );
};
const removeGroup = (groupKey: string) => {
    const group = groupedGrants.value.find((item) => item.key === groupKey);
    if (!group) return;
    grants.value = grants.value.filter(
        (grant) =>
            grant.user_id !== group.user_id || grant.team_id !== group.team_id,
    );
};
const savePermissions = async () => {
    if (!selected.value?.can?.manage) return;
    const response = await fetch(updatePermissions.url(selected.value.id), {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            permissions: grants.value.map((grant) => ({
                user_id: grant.user_id,
                team_id: grant.team_id,
                permission: grant.permission,
            })),
        }),
    });
    response.ok
        ? toast.success('Đã lưu quyền truy cập.')
        : toast.error('Không thể lưu quyền truy cập.');
    if (response.ok) refresh();
};
const updateWorkspaceSharing = async (checked: boolean) => {
    if (!selected.value?.can?.manage) return;
    const response = await fetch(updateFolder.url(selected.value.id), {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ is_shared_with_workspace: checked }),
    });
    if (response.ok) {
        selected.value.is_shared_with_workspace = checked;
        toast.success(
            checked
                ? 'Mọi nhân viên đã có thể xem thư mục.'
                : 'Đã chuyển thư mục về riêng tư.',
        );
        refresh();
    } else {
        toast.error('Không thể cập nhật chế độ chia sẻ.');
    }
};
onMounted(() => {
    void fetchSubjects();
    void loadPermissions();
});
</script>

<template>
    <Head title="Quản lý thư mục" />
    <AppLayout>
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Quản lý và chia sẻ thư mục"
                description="Thư mục nhân viên tạo mặc định là riêng tư. Chỉ người được chia sẻ hoặc toàn workspace mới nhìn thấy."
            />
            <div class="grid gap-5 lg:grid-cols-[340px_minmax(0,1fr)]">
                <FolderManager
                    :folders="folders"
                    :selected-id="selectedId"
                    :can-create-master="canManageAllFolders"
                    @select="selectFolder"
                    @changed="refresh"
                />
                <section class="rounded-xl border bg-card p-5">
                    <template v-if="selected">
                        <div
                            class="mb-5 flex flex-wrap items-start justify-between gap-3 border-b pb-4"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-lg font-bold">
                                        {{
                                            selected.display_name ??
                                            selected.name
                                        }}
                                    </h2>
                                    <Badge>{{
                                        selected.type === 'master'
                                            ? 'Master'
                                            : 'Cá nhân'
                                    }}</Badge>
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ selected.medias_count ?? 0 }} media ·
                                    {{ selected.posts_count ?? 0 }} bài viết ·
                                    {{ selected.children_count ?? 0 }} thư mục
                                    con
                                </p>
                            </div>
                            <Button
                                v-if="selected.can?.manage"
                                @click="savePermissions"
                                ><IconLockAccess class="size-4" /> Lưu phân
                                quyền</Button
                            >
                        </div>
                        <div
                            v-if="selected.can?.manage"
                            class="mb-4 flex items-start gap-3 rounded-lg border bg-muted/30 p-4"
                        >
                            <Checkbox
                                :model-value="selected.is_shared_with_workspace"
                                @update:model-value="
                                    updateWorkspaceSharing(Boolean($event))
                                "
                            />
                            <div>
                                <p class="text-sm font-semibold">
                                    Chia sẻ cho toàn bộ nhân viên
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Mọi thành viên trong workspace được xem thư
                                    mục. Các quyền upload, sửa và xóa vẫn phải
                                    cấp riêng.
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="selected.can?.manage"
                            class="space-y-4 rounded-lg border bg-muted/30 p-4"
                        >
                            <div
                                class="grid gap-3 md:grid-cols-[140px_minmax(180px,0.8fr)_minmax(220px,1fr)_auto]"
                            >
                                <select
                                    v-model="subjectType"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="user">Nhân viên</option>
                                    <option value="team">Team</option>
                                </select>
                                <div class="relative">
                                    <IconSearch
                                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    /><Input
                                        v-model="subjectSearch"
                                        class="pl-9"
                                        :placeholder="
                                            subjectType === 'user'
                                                ? 'Tìm tên hoặc email…'
                                                : 'Tìm Team…'
                                        "
                                    />
                                </div>
                                <select
                                    v-model="subjectId"
                                    :disabled="subjectLoading"
                                    class="h-10 min-w-0 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">
                                        {{
                                            subjectLoading
                                                ? 'Đang tìm…'
                                                : 'Chọn đối tượng'
                                        }}
                                    </option>
                                    <option
                                        v-for="item in subjectResults"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{ item.name
                                        }}{{
                                            item.email ? ` (${item.email})` : ''
                                        }}
                                    </option>
                                </select>
                                <Button
                                    variant="outline"
                                    :disabled="
                                        !subjectId ||
                                        selectedPermissions.length === 0
                                    "
                                    @click="addGrant"
                                    ><IconPlus class="size-4" /> Áp dụng
                                    quyền</Button
                                >
                            </div>
                            <div
                                v-if="hasMoreSubjects"
                                class="flex justify-end"
                            >
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    :disabled="subjectLoadingMore"
                                    @click="
                                        fetchSubjects(subjectPage + 1, true)
                                    "
                                    ><IconLoader2
                                        v-if="subjectLoadingMore"
                                        class="size-4 animate-spin"
                                    />
                                    Tải thêm 30</Button
                                >
                            </div>
                            <div>
                                <p
                                    class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Chọn một hoặc nhiều quyền
                                </p>
                                <div
                                    class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4"
                                >
                                    <label
                                        v-for="option in permissionOptions"
                                        :key="option.value"
                                        class="flex cursor-pointer items-center gap-2 rounded-md border bg-background p-2.5 text-sm hover:bg-muted"
                                    >
                                        <Checkbox
                                            :model-value="
                                                selectedPermissions.includes(
                                                    option.value,
                                                )
                                            "
                                            @update:model-value="
                                                toggleSelectedPermission(
                                                    option.value,
                                                    Boolean($event),
                                                )
                                            "
                                        />
                                        {{ option.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div v-if="selected.can?.manage" class="mt-4 space-y-3">
                            <div
                                v-if="permissionLoading"
                                class="grid place-items-center rounded-lg border p-12"
                            >
                                <IconLoader2
                                    class="size-6 animate-spin text-muted-foreground"
                                />
                            </div>
                            <div
                                v-for="group in groupedGrants"
                                :key="group.key"
                                class="rounded-lg border p-4"
                            >
                                <div
                                    class="mb-3 flex items-center gap-3 border-b pb-3"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm font-semibold"
                                        >
                                            {{ group.name }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            {{
                                                group.type === 'user'
                                                    ? `Nhân viên${group.email ? ` · ${group.email}` : ''}`
                                                    : 'Team'
                                            }}
                                            ·
                                            {{ group.permissions.length }} quyền
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive"
                                        title="Gỡ toàn bộ quyền"
                                        @click="removeGroup(group.key)"
                                        ><IconTrash class="size-4"
                                    /></Button>
                                </div>
                                <div
                                    class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4"
                                >
                                    <label
                                        v-for="option in permissionOptions"
                                        :key="option.value"
                                        class="flex cursor-pointer items-center gap-2 rounded-md border p-2.5 text-sm hover:bg-muted"
                                    >
                                        <Checkbox
                                            :model-value="
                                                group.permissions.includes(
                                                    option.value,
                                                )
                                            "
                                            @update:model-value="
                                                toggleGroupPermission(
                                                    group.key,
                                                    option.value,
                                                    Boolean($event),
                                                )
                                            "
                                        />
                                        {{ option.label }}
                                    </label>
                                </div>
                            </div>
                            <p
                                v-if="
                                    !permissionLoading &&
                                    groupedGrants.length === 0
                                "
                                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                            >
                                Chưa cấp quyền cho ai.
                            </p>
                        </div>
                        <p
                            v-else
                            class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                        >
                            Bạn được chia sẻ quyền xem thư mục này nhưng không
                            có quyền thay đổi chia sẻ.
                        </p>
                    </template>
                    <div
                        v-else
                        class="grid min-h-64 place-items-center text-center text-muted-foreground"
                    >
                        <div>
                            <IconLockAccess class="mx-auto mb-2 size-8" />
                            <p>Chọn một thư mục để quản lý quyền.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
