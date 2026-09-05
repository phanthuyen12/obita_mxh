<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    IconGitPullRequest,
    IconLoader,
    IconPlus,
    IconTrash,
} from '@tabler/icons-vue';
import { reactive, ref } from 'vue';

import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    destroy as destroyWorkflow,
    store as storeWorkflow,
    update as updateWorkflow,
} from '@/routes/app/content-workflows';

interface Member {
    id: string;
    name: string;
    email: string;
}

interface WorkflowMember {
    id: string;
    name: string;
    email: string;
    pivot: {
        can_write: boolean;
        can_review: boolean;
        can_publish: boolean;
    };
}

interface SocialAccount {
    id: string;
    display_name: string | null;
    username: string | null;
    platform: string;
}

interface Workflow {
    id: string;
    name: string;
    description: string | null;
    social_account_id: string | null;
    social_account_ids: string[] | null;
    is_active: boolean;
    social_account: SocialAccount | null;
    members: WorkflowMember[];
}

interface MemberPermission {
    user_id: string;
    can_write: boolean;
    can_review: boolean;
    can_publish: boolean;
}

interface WorkflowForm {
    name: string;
    description: string;
    social_account_ids: string[];
    is_active: boolean;
    members: MemberPermission[];
}

const props = defineProps<{
    workflows: Workflow[];
    socialAccounts: SocialAccount[];
    members: Member[];
    canManageTeam: boolean;
    currentUserId: string;
}>();

const saving = ref<string | null>(null);
const creating = ref(false);
const showCreate = ref(false);
const drafts = reactive<Record<string, WorkflowForm>>({});
const newWorkflow = reactive<WorkflowForm>({
    name: '',
    description: '',
    social_account_ids: [],
    is_active: true,
    members: [],
});

const permissionsForCurrentUser = (workflow: Workflow): string[] => {
    const member = workflow.members.find(
        (item) => item.id === props.currentUserId,
    );
    if (!member) return [];

    return [
        member.pivot.can_write ? 'Viết content' : null,
        member.pivot.can_review ? 'Duyệt content' : null,
        member.pivot.can_publish ? 'Đăng bài' : null,
    ].filter((permission): permission is string => permission !== null);
};

const permissionsFor = (workflow: Workflow): MemberPermission[] => {
    if (!drafts[workflow.id]) {
        let initialAccountIds: string[] = [];
        if (
            Array.isArray(workflow.social_account_ids) &&
            workflow.social_account_ids.length > 0
        ) {
            initialAccountIds = [...workflow.social_account_ids];
        } else if (workflow.social_account_id) {
            initialAccountIds = [workflow.social_account_id];
        }

        drafts[workflow.id] = {
            name: workflow.name,
            description: workflow.description ?? '',
            social_account_ids: initialAccountIds,
            is_active: workflow.is_active,
            members: workflow.members.map((member) => ({
                user_id: member.id,
                can_write: Boolean(member.pivot.can_write),
                can_review: Boolean(member.pivot.can_review),
                can_publish: Boolean(member.pivot.can_publish),
            })),
        };
    }

    return drafts[workflow.id].members;
};

const formFor = (workflow: Workflow): WorkflowForm => {
    permissionsFor(workflow);
    return drafts[workflow.id];
};

const toggleSocialAccount = (form: WorkflowForm, accountId: string) => {
    const idx = form.social_account_ids.indexOf(accountId);
    if (idx >= 0) {
        form.social_account_ids.splice(idx, 1);
    } else {
        form.social_account_ids.push(accountId);
    }
};

const isAllAccountsSelected = (form: WorkflowForm): boolean => {
    return form.social_account_ids.length === 0;
};

const selectAllAccounts = (form: WorkflowForm) => {
    form.social_account_ids = [];
};

const getAccountsLabel = (workflow: Workflow): string => {
    const ids =
        Array.isArray(workflow.social_account_ids) &&
        workflow.social_account_ids.length > 0
            ? workflow.social_account_ids
            : workflow.social_account_id
              ? [workflow.social_account_id]
              : [];

    if (ids.length === 0) {
        return '🌐 Tất cả các kênh (Áp dụng chung)';
    }

    const matchedNames = props.socialAccounts
        .filter((acc) => ids.includes(acc.id))
        .map(
            (acc) =>
                `${acc.display_name || acc.username || acc.id} (${acc.platform.toUpperCase()})`,
        );

    return matchedNames.length > 0
        ? matchedNames.join(', ')
        : '🌐 Tất cả các kênh';
};

const toggleMember = (
    form: WorkflowForm,
    userId: string,
    key: keyof Omit<MemberPermission, 'user_id'>,
) => {
    let member = form.members.find((item) => item.user_id === userId);
    if (!member) {
        member = {
            user_id: userId,
            can_write: false,
            can_review: false,
            can_publish: false,
        };
        form.members.push(member);
    }
    member[key] = !member[key];
};

const save = (workflow: Workflow) => {
    saving.value = workflow.id;
    router.patch(updateWorkflow.url(workflow.id), drafts[workflow.id], {
        preserveScroll: true,
        onFinish: () => {
            saving.value = null;
        },
    });
};

const create = () => {
    creating.value = true;
    router.post(storeWorkflow.url(), newWorkflow, {
        preserveScroll: true,
        onSuccess: () => {
            showCreate.value = false;
        },
        onFinish: () => {
            creating.value = false;
        },
    });
};

const remove = (workflow: Workflow) => {
    if (!window.confirm('Xóa luồng này?')) return;
    router.delete(destroyWorkflow.url(workflow.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Luồng nội dung" />
    <AppLayout>
        <div
            class="mx-auto flex min-h-screen w-full max-w-7xl flex-col gap-6 overflow-y-auto px-6 py-8"
        >
            <PageHeader
                title="Luồng nội dung"
                description="Quy định thành viên được cấp quyền viết content, duyệt bài, và đăng bài cho một hoặc nhiều Kênh chỉ định."
            />

            <div class="flex items-center justify-between">
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <IconGitPullRequest class="size-5 text-primary" />
                    {{ workflows.length }} luồng trong Không gian làm việc
                </div>
                <button
                    v-if="canManageTeam"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground"
                    @click="showCreate = !showCreate"
                >
                    <IconPlus class="size-4" /> Tạo luồng
                </button>
            </div>

            <div
                v-if="!canManageTeam"
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"
            >
                Bạn có thể xem các luồng và thực hiện nhiệm vụ được cấp. Chỉ
                Admin/Owner mới được tạo hoặc chỉnh sửa cấu hình luồng.
            </div>

            <form
                v-if="showCreate"
                class="space-y-4 rounded-xl border bg-card p-5"
                @submit.prevent="create"
            >
                <h2 class="font-semibold">Tạo luồng mới</h2>
                <div class="grid gap-4">
                    <input
                        v-model="newWorkflow.name"
                        required
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                        placeholder="Tên luồng, ví dụ: Quy trình đăng bài Facebook + YouTube"
                    />

                    <div class="space-y-2">
                        <label
                            class="text-xs font-semibold text-muted-foreground"
                            >Chọn Kênh / Nền tảng áp dụng (Có thể chọn nhiều
                            kênh):</label
                        >
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors',
                                    isAllAccountsSelected(newWorkflow)
                                        ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                                        : 'bg-background text-foreground hover:bg-muted',
                                ]"
                                @click="selectAllAccounts(newWorkflow)"
                            >
                                <span>🌐 Tất cả kênh (Áp dụng chung)</span>
                            </button>
                            <button
                                v-for="account in socialAccounts"
                                :key="account.id"
                                type="button"
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors',
                                    newWorkflow.social_account_ids.includes(
                                        account.id,
                                    )
                                        ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                                        : 'bg-background text-foreground hover:bg-muted',
                                ]"
                                @click="
                                    toggleSocialAccount(newWorkflow, account.id)
                                "
                            >
                                <span>{{
                                    account.display_name ||
                                    account.username ||
                                    account.id
                                }}</span>
                                <span class="text-[10px] opacity-75"
                                    >({{
                                        account.platform?.toUpperCase()
                                    }})</span
                                >
                            </button>
                        </div>
                    </div>
                </div>
                <textarea
                    v-model="newWorkflow.description"
                    class="min-h-20 w-full rounded-md border bg-background p-3 text-sm"
                    placeholder="Mô tả mục đích của luồng (không bắt buộc)"
                />
                <button
                    type="submit"
                    class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-60"
                    :disabled="creating || !newWorkflow.name.trim()"
                >
                    <IconLoader
                        v-if="creating"
                        class="mr-1 inline size-4 animate-spin"
                    />
                    Lưu luồng
                </button>
            </form>

            <div
                v-if="workflows.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
            >
                Chưa có luồng nào. Hãy tạo luồng đầu tiên cho một hoặc nhiều
                Kênh.
            </div>

            <div
                v-for="workflow in workflows"
                :key="workflow.id"
                class="space-y-5 rounded-xl border bg-card p-5"
            >
                <div class="grid gap-4 md:grid-cols-[1fr_auto]">
                    <div class="space-y-3">
                        <input
                            v-if="canManageTeam"
                            v-model="formFor(workflow).name"
                            class="h-10 w-full rounded-md border bg-background px-3 text-sm font-semibold"
                        />
                        <div
                            v-else
                            class="flex h-10 items-center rounded-md border bg-muted/40 px-3 text-sm font-semibold"
                        >
                            {{ workflow.name }}
                        </div>

                        <div v-if="canManageTeam" class="space-y-1.5">
                            <label
                                class="text-xs font-semibold text-muted-foreground"
                                >Kênh / Nền tảng áp dụng:</label
                            >
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors',
                                        isAllAccountsSelected(formFor(workflow))
                                            ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                                            : 'bg-background text-foreground hover:bg-muted',
                                    ]"
                                    @click="
                                        selectAllAccounts(formFor(workflow))
                                    "
                                >
                                    <span>🌐 Tất cả kênh (Áp dụng chung)</span>
                                </button>
                                <button
                                    v-for="account in socialAccounts"
                                    :key="account.id"
                                    type="button"
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors',
                                        formFor(
                                            workflow,
                                        ).social_account_ids.includes(
                                            account.id,
                                        )
                                            ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                                            : 'bg-background text-foreground hover:bg-muted',
                                    ]"
                                    @click="
                                        toggleSocialAccount(
                                            formFor(workflow),
                                            account.id,
                                        )
                                    "
                                >
                                    <span>{{
                                        account.display_name ||
                                        account.username ||
                                        account.id
                                    }}</span>
                                    <span class="text-[10px] opacity-75"
                                        >({{
                                            account.platform?.toUpperCase()
                                        }})</span
                                    >
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-xs text-muted-foreground">
                            Kênh áp dụng:
                            <span class="font-medium text-foreground">{{
                                getAccountsLabel(workflow)
                            }}</span>
                        </div>
                    </div>

                    <div class="flex items-start pt-1">
                        <label
                            v-if="canManageTeam"
                            class="flex items-center gap-2 text-sm"
                            ><input
                                v-model="formFor(workflow).is_active"
                                type="checkbox"
                            />
                            Đang bật</label
                        >
                        <span
                            v-else
                            class="flex items-center text-sm text-muted-foreground"
                            >{{
                                workflow.is_active ? 'Đang bật' : 'Đã tắt'
                            }}</span
                        >
                    </div>
                </div>
                <textarea
                    v-if="canManageTeam"
                    v-model="formFor(workflow).description"
                    class="min-h-16 w-full rounded-md border bg-background p-3 text-sm"
                    placeholder="Mô tả luồng"
                />
                <p
                    v-else-if="workflow.description"
                    class="text-sm text-muted-foreground"
                >
                    {{ workflow.description }}
                </p>
                <p v-if="!canManageTeam" class="text-sm">
                    Quyền của bạn:
                    <span class="font-semibold">{{
                        permissionsForCurrentUser(workflow).join(', ') ||
                        'Chưa được cấp quyền phụ'
                    }}</span>
                </p>
                <div>
                    <h3 class="mb-3 text-sm font-semibold">
                        Quyền phụ trong luồng
                    </h3>
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="bg-muted/50 text-xs text-muted-foreground"
                            >
                                <tr>
                                    <th class="p-3">Thành viên</th>
                                    <th class="p-3">Viết content</th>
                                    <th class="p-3">Duyệt content</th>
                                    <th class="p-3">Đăng bài</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="member in members"
                                    :key="member.id"
                                    class="border-t"
                                >
                                    <td class="p-3">
                                        <div class="font-medium">
                                            {{ member.name }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ member.email }}
                                        </div>
                                    </td>
                                    <td
                                        v-for="permission in [
                                            'can_write',
                                            'can_review',
                                            'can_publish',
                                        ] as const"
                                        :key="permission"
                                        class="p-3"
                                    >
                                        <input
                                            :checked="
                                                permissionsFor(workflow).find(
                                                    (item) =>
                                                        item.user_id ===
                                                        member.id,
                                                )?.[permission] ?? false
                                            "
                                            type="checkbox"
                                            :disabled="!canManageTeam"
                                            @change="
                                                toggleMember(
                                                    formFor(workflow),
                                                    member.id,
                                                    permission,
                                                )
                                            "
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <button
                        v-if="canManageTeam"
                        type="button"
                        class="inline-flex items-center gap-2 text-sm text-destructive"
                        @click="remove(workflow)"
                    >
                        <IconTrash class="size-4" /> Xóa luồng
                    </button>
                    <span v-else />
                    <button
                        v-if="canManageTeam"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-60"
                        :disabled="saving === workflow.id"
                        @click="save(workflow)"
                    >
                        <IconLoader
                            v-if="saving === workflow.id"
                            class="size-4 animate-spin"
                        />
                        {{
                            saving === workflow.id
                                ? 'Đang lưu...'
                                : 'Lưu thay đổi'
                        }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
