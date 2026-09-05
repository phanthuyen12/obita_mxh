<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { IconExternalLink, IconSearch } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import { index as assignmentsRoute } from '@/actions/App/Http/Controllers/App/WorkspaceAssignmentController';
import PageHeader from '@/components/PageHeader.vue';
import SettingsTabsNav from '@/components/settings/SettingsTabsNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useWorkspaceSettingsTabs } from '@/composables/useWorkspaceSettingsTabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { accounts as accountsRoute } from '@/routes/app';

type Member = { id: string; name: string; email: string };
type PermissionGroup = { omnichat: string[]; content: string[] };
type AssignmentRow = {
    id: string;
    member: Member;
    account: {
        id: string;
        platform: string;
        platform_label: string;
        display_name: string;
        username: string;
        avatar_url: string | null;
        status: string;
        status_label: string;
        connected_by: string | null;
    };
    permissions: PermissionGroup;
};

const props = defineProps<{
    workspace: { id: string; name: string };
    rows: AssignmentRow[];
    members: Member[];
    platforms: Array<{ value: string; label: string }>;
    filters: {
        memberId: string;
        platform: string;
        module: string;
        search: string;
    };
    summary: { assignments: number; profiles: number; members: number };
}>();

const tabs = useWorkspaceSettingsTabs();
const search = ref(props.filters.search);
const selectedMember = ref(props.filters.memberId);
const selectedPlatform = ref(props.filters.platform);
const selectedModule = ref(props.filters.module);

const statusVariant = (status: string) =>
    status === 'connected' ? 'success' : 'destructive';

const permissionLabels: Record<string, string> = {
    can_view_omnichat: 'Xem chat',
    can_reply_omnichat: 'Trả lời',
    can_assign_conversations: 'Phân công',
    can_access_content: 'Sử dụng Content',
};

const submitFilters = () => {
    router.get(
        assignmentsRoute.url(),
        {
            search: search.value,
            member: selectedMember.value,
            platform: selectedPlatform.value,
            module: selectedModule.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const clearFilters = () => {
    search.value = '';
    router.get(assignmentsRoute.url(), {}, { replace: true });
};

const hasRows = computed(() => props.rows.length > 0);
</script>

<template>
    <Head title="Phân công Page và Channel" />

    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-8 px-6 py-8">
            <PageHeader
                title="Phân công Page và Channel"
                description="Theo dõi thành viên, profile và quyền vận hành trên từng nền tảng."
            />

            <SettingsTabsNav :tabs="tabs" active="assignments" />

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Tổng phân công</p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ summary.assignments }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Profile/Channel</p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ summary.profiles }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">
                        Thành viên đang được giao
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ summary.members }}
                    </p>
                </div>
            </div>

            <section class="space-y-4 rounded-xl border bg-card p-4">
                <div
                    class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_200px_200px_180px_auto]"
                >
                    <div class="relative">
                        <IconSearch
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            class="pl-9"
                            placeholder="Tìm Page, profile, username..."
                            @keyup.enter="submitFilters"
                        />
                    </div>
                    <select
                        v-model="selectedMember"
                        class="h-10 rounded-md border-2 border-foreground bg-card px-3 text-sm"
                    >
                        <option value="">Tất cả thành viên</option>
                        <option
                            v-for="member in members"
                            :key="member.id"
                            :value="member.id"
                        >
                            {{ member.name }}
                        </option>
                    </select>
                    <select
                        v-model="selectedPlatform"
                        class="h-10 rounded-md border-2 border-foreground bg-card px-3 text-sm"
                    >
                        <option value="">Tất cả nền tảng</option>
                        <option
                            v-for="item in platforms"
                            :key="item.value"
                            :value="item.value"
                        >
                            {{ item.label }}
                        </option>
                    </select>
                    <select
                        v-model="selectedModule"
                        class="h-10 rounded-md border-2 border-foreground bg-card px-3 text-sm"
                    >
                        <option value="">Tất cả module</option>
                        <option value="omnichat">Omnichat</option>
                        <option value="content">Content</option>
                    </select>
                    <div class="flex gap-2">
                        <Button type="button" @click="submitFilters"
                            >Lọc</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            @click="clearFilters"
                            >Xoá</Button
                        >
                    </div>
                </div>

                <div v-if="hasRows" class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[1050px] text-sm">
                        <thead class="border-b bg-muted/50 text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    Thành viên
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    Nền tảng / Profile
                                </th>
                                <th class="px-4 py-3 font-medium">Kết nối</th>
                                <th class="px-4 py-3 font-medium">Omnichat</th>
                                <th class="px-4 py-3 font-medium">Content</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Thao tác
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                class="align-top hover:bg-muted/30"
                            >
                                <td class="px-4 py-4">
                                    <p class="font-medium">
                                        {{ row.member.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.member.email }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-medium">
                                        {{ row.account.display_name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.account.platform_label }} · @{{
                                            row.account.username
                                        }}
                                    </p>
                                </td>
                                <td class="space-y-1 px-4 py-4">
                                    <Badge
                                        :variant="
                                            statusVariant(row.account.status)
                                        "
                                        >{{ row.account.status_label }}</Badge
                                    >
                                    <p class="text-xs text-muted-foreground">
                                        Bởi
                                        {{
                                            row.account.connected_by ||
                                            'Không rõ'
                                        }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex max-w-48 flex-wrap gap-1">
                                        <Badge
                                            v-for="permission in row.permissions
                                                .omnichat"
                                            :key="permission"
                                            variant="secondary"
                                            class="text-[11px]"
                                            >{{
                                                permissionLabels[permission]
                                            }}</Badge
                                        >
                                        <span
                                            v-if="
                                                row.permissions.omnichat
                                                    .length === 0
                                            "
                                            class="text-xs text-muted-foreground"
                                            >Chưa cấp</span
                                        >
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex max-w-48 flex-wrap gap-1">
                                        <Badge
                                            v-for="permission in row.permissions
                                                .content"
                                            :key="permission"
                                            variant="outline"
                                            class="text-[11px]"
                                            >{{
                                                permissionLabels[permission]
                                            }}</Badge
                                        >
                                        <span
                                            v-if="
                                                row.permissions.content
                                                    .length === 0
                                            "
                                            class="text-xs text-muted-foreground"
                                            >Chưa cấp</span
                                        >
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link :href="accountsRoute.url()">
                                            <IconExternalLink
                                                class="size-3.5"
                                            />
                                            Chỉnh quyền
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-else
                    class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                >
                    Chưa có phân công phù hợp với bộ lọc hiện tại.
                </div>
            </section>
        </div>
    </AppLayout>
</template>
