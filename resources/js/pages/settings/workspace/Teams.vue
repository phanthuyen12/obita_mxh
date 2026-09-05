<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    IconEdit,
    IconLoader2,
    IconPlus,
    IconSearch,
    IconTrash,
    IconUsersGroup,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import PageHeader from '@/components/PageHeader.vue';
import SettingsTabsNav from '@/components/settings/SettingsTabsNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useWorkspaceSettingsTabs } from '@/composables/useWorkspaceSettingsTabs';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    destroy,
    memberIds,
    members as membersRoute,
    store,
    update,
} from '@/routes/app/teams';

interface Member {
    id: string;
    name: string;
    email: string;
}
interface Team {
    id: string;
    name: string;
    description: string | null;
    is_active: boolean;
    users_count: number;
    permissions_count: number;
}

defineProps<{
    workspace: { id: string; name: string };
    teams: Team[];
}>();

const tabs = useWorkspaceSettingsTabs();
const dialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const editingTeam = ref<Team | null>(null);
const deletingTeam = ref<Team | null>(null);
const form = useForm({
    name: '',
    description: '',
    is_active: true,
    user_ids: [] as string[],
});
const dialogTitle = computed(() =>
    editingTeam.value ? 'Chỉnh sửa Team' : 'Tạo Team mới',
);
const memberResults = ref<Member[]>([]);
const memberSearch = ref('');
const memberPage = ref(1);
const memberLastPage = ref(1);
const memberLoading = ref(false);
const memberLoadingMore = ref(false);
const hasMoreMembers = computed(() => memberPage.value < memberLastPage.value);

const fetchMembers = async (page = 1, append = false) => {
    append ? (memberLoadingMore.value = true) : (memberLoading.value = true);
    try {
        const response = await fetch(
            membersRoute.url({
                query: { search: memberSearch.value || undefined, page },
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
        memberResults.value = append
            ? [...memberResults.value, ...payload.data]
            : payload.data;
        memberPage.value = payload.current_page;
        memberLastPage.value = payload.last_page;
    } finally {
        memberLoading.value = false;
        memberLoadingMore.value = false;
    }
};
const debouncedMemberSearch = debounce(() => void fetchMembers(), 300);
watch(memberSearch, debouncedMemberSearch);

const openCreate = () => {
    editingTeam.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
    memberSearch.value = '';
    void fetchMembers();
};
const openEdit = async (team: Team) => {
    editingTeam.value = team;
    form.name = team.name;
    form.description = team.description ?? '';
    form.is_active = team.is_active;
    form.user_ids = [];
    form.clearErrors();
    dialogOpen.value = true;
    memberSearch.value = '';
    const [response] = await Promise.all([
        fetch(memberIds.url(team.id), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }),
        fetchMembers(),
    ]);
    if (response.ok) form.user_ids = (await response.json()).data;
};
const toggleMember = (memberId: string, checked: boolean) => {
    form.user_ids = checked
        ? [...new Set([...form.user_ids, memberId])]
        : form.user_ids.filter((id) => id !== memberId);
};
const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    };
    editingTeam.value
        ? form.put(update.url(editingTeam.value.id), options)
        : form.post(store.url(), options);
};
const confirmDelete = (team: Team) => {
    deletingTeam.value = team;
    deleteDialogOpen.value = true;
};
const remove = () => {
    if (!deletingTeam.value) return;
    router.delete(destroy.url(deletingTeam.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
            deletingTeam.value = null;
        },
    });
};
</script>

<template>
    <Head title="Quản lý Team" />
    <AppLayout>
        <div class="mx-auto max-w-5xl space-y-8 px-6 py-8">
            <PageHeader
                title="Cài đặt Workspace"
                description="Tạo nhóm nhân viên để phân quyền Folder nhanh và nhất quán."
            />
            <SettingsTabsNav :tabs="tabs" active="teams" />

            <section class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold">Team</h2>
                        <p class="text-sm text-muted-foreground">
                            Ví dụ: CEO, Marketing, Truyền thông.
                        </p>
                    </div>
                    <Button @click="openCreate"
                        ><IconPlus class="size-4" /> Tạo Team</Button
                    >
                </div>

                <div v-if="teams.length" class="grid gap-4 md:grid-cols-2">
                    <article
                        v-for="team in teams"
                        :key="team.id"
                        class="rounded-xl border bg-card p-5 shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="grid size-9 place-items-center rounded-lg bg-primary/10 text-primary"
                                        ><IconUsersGroup class="size-5"
                                    /></span>
                                    <div>
                                        <h3 class="truncate font-bold">
                                            {{ team.name }}
                                        </h3>
                                        <Badge
                                            :variant="
                                                team.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                            >{{
                                                team.is_active
                                                    ? 'Đang hoạt động'
                                                    : 'Tạm dừng'
                                            }}</Badge
                                        >
                                    </div>
                                </div>
                                <p
                                    v-if="team.description"
                                    class="mt-3 line-clamp-2 text-sm text-muted-foreground"
                                >
                                    {{ team.description }}
                                </p>
                            </div>
                            <div class="flex gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    title="Chỉnh sửa"
                                    @click="openEdit(team)"
                                    ><IconEdit class="size-4"
                                /></Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-destructive"
                                    title="Xóa"
                                    @click="confirmDelete(team)"
                                    ><IconTrash class="size-4"
                                /></Button>
                            </div>
                        </div>
                        <div class="mt-4 border-t pt-3">
                            <p
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                {{ team.users_count.toLocaleString('vi-VN') }}
                                thành viên · {{ team.permissions_count }} quyền
                                Folder
                            </p>
                        </div>
                    </article>
                </div>
                <div
                    v-else
                    class="rounded-xl border border-dashed bg-card p-10 text-center"
                >
                    <IconUsersGroup
                        class="mx-auto size-10 text-muted-foreground"
                    />
                    <h3 class="mt-3 font-semibold">Chưa có Team</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Tạo Team đầu tiên và thêm nhân viên vào nhóm.
                    </p>
                </div>
            </section>
        </div>
    </AppLayout>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader
                ><DialogTitle>{{ dialogTitle }}</DialogTitle
                ><DialogDescription
                    >Team có thể được gán nhiều quyền trên Master
                    Folder.</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="submit">
                <label class="block space-y-1.5 text-sm font-medium"
                    >Tên Team<Input
                        v-model="form.name"
                        maxlength="120"
                        placeholder="Marketing"
                    /><span
                        v-if="form.errors.name"
                        class="text-xs text-destructive"
                        >{{ form.errors.name }}</span
                    ></label
                >
                <label class="block space-y-1.5 text-sm font-medium"
                    >Mô tả<Input
                        v-model="form.description"
                        maxlength="1000"
                        placeholder="Nhóm phụ trách nội dung marketing"
                /></label>
                <label class="flex items-center gap-2 text-sm font-medium"
                    ><Checkbox v-model="form.is_active" /> Đang hoạt động</label
                >
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <p class="text-sm font-medium">Thành viên</p>
                        <Badge variant="secondary"
                            >Đã chọn
                            {{
                                form.user_ids.length.toLocaleString('vi-VN')
                            }}</Badge
                        >
                    </div>
                    <div class="relative mb-2">
                        <IconSearch
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="memberSearch"
                            class="pl-9"
                            placeholder="Tìm theo tên hoặc email…"
                        />
                    </div>
                    <div
                        class="max-h-72 space-y-1 overflow-y-auto rounded-lg border p-2"
                    >
                        <div
                            v-if="memberLoading"
                            class="grid place-items-center p-8"
                        >
                            <IconLoader2
                                class="size-5 animate-spin text-muted-foreground"
                            />
                        </div>
                        <label
                            v-for="member in memberResults"
                            v-else
                            :key="member.id"
                            class="flex cursor-pointer items-center gap-3 rounded-md p-2 hover:bg-muted"
                        >
                            <Checkbox
                                :model-value="form.user_ids.includes(member.id)"
                                @update:model-value="
                                    toggleMember(member.id, Boolean($event))
                                "
                            />
                            <span class="min-w-0"
                                ><span
                                    class="block truncate text-sm font-medium"
                                    >{{ member.name }}</span
                                ><span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ member.email }}</span
                                ></span
                            >
                        </label>
                        <p
                            v-if="!memberLoading && memberResults.length === 0"
                            class="p-5 text-center text-sm text-muted-foreground"
                        >
                            Không tìm thấy nhân viên.
                        </p>
                        <Button
                            v-if="hasMoreMembers"
                            type="button"
                            variant="ghost"
                            class="w-full"
                            :disabled="memberLoadingMore"
                            @click="fetchMembers(memberPage + 1, true)"
                        >
                            <IconLoader2
                                v-if="memberLoadingMore"
                                class="size-4 animate-spin"
                            />
                            Xem thêm 30 người
                        </Button>
                    </div>
                    <span
                        v-if="form.errors.user_ids"
                        class="text-xs text-destructive"
                        >{{ form.errors.user_ids }}</span
                    >
                </div>
                <DialogFooter
                    ><Button
                        type="button"
                        variant="outline"
                        @click="dialogOpen = false"
                        >Hủy</Button
                    ><Button
                        type="submit"
                        :disabled="form.processing || !form.name.trim()"
                        >{{ editingTeam ? 'Lưu thay đổi' : 'Tạo Team' }}</Button
                    ></DialogFooter
                >
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent
            ><DialogHeader
                ><DialogTitle>Xóa Team {{ deletingTeam?.name }}?</DialogTitle
                ><DialogDescription
                    >Các thành viên không bị xóa khỏi Workspace, nhưng toàn bộ
                    quyền Folder đã gán cho Team này sẽ bị
                    xóa.</DialogDescription
                ></DialogHeader
            ><DialogFooter
                ><Button variant="outline" @click="deleteDialogOpen = false"
                    >Hủy</Button
                ><Button variant="destructive" @click="remove"
                    >Xóa Team</Button
                ></DialogFooter
            ></DialogContent
        >
    </Dialog>
</template>
