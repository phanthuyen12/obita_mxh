<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

import InputError from '@/components/InputError.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { createAccount as createAccountRoute } from '@/actions/App/Http/Controllers/App/WorkspaceInviteController';
import { store as storeInvite } from '@/routes/app/invites';
import { WorkspaceRole } from '@/types/workspace-role';

const open = defineModel<boolean>('open', { default: false });

type Mode = 'invite' | 'create';
const mode = ref<Mode>('invite');

const inviteRole = ref(WorkspaceRole.Member);
const createRole = ref(WorkspaceRole.Member);
const showPassword = ref(false);
const showConfirm = ref(false);

const onInviteSuccess = () => {
    inviteRole.value = WorkspaceRole.Member;
    open.value = false;
};

const onCreateSuccess = () => {
    createRole.value = WorkspaceRole.Member;
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Thêm thành viên</DialogTitle>
                <DialogDescription>
                    Mời qua email hoặc tạo tài khoản + mật khẩu trực tiếp.
                </DialogDescription>
            </DialogHeader>

            <!-- Mode switcher tabs -->
            <div class="flex rounded-lg border p-1 gap-1">
                <button
                    type="button"
                    class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="mode === 'invite'
                        ? 'bg-foreground text-background shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="mode = 'invite'"
                >
                    Gửi lời mời
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="mode === 'create'
                        ? 'bg-foreground text-background shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="mode = 'create'"
                >
                    Tạo tài khoản
                </button>
            </div>

            <!-- TAB: Send Invite -->
            <Form
                v-if="mode === 'invite'"
                v-bind="storeInvite.form()"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="onInviteSuccess"
            >
                <div class="grid gap-2">
                    <Label for="invite-email">{{
                        $t('settings.members.invite.email')
                    }}</Label>
                    <Input
                        id="invite-email"
                        name="email"
                        type="email"
                        :placeholder="
                            trans('settings.members.invite.email_placeholder')
                        "
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="invite-role">{{
                        $t('settings.members.invite.role')
                    }}</Label>
                    <Select v-model="inviteRole" name="role">
                        <SelectTrigger class="w-full">
                            <SelectValue
                                :placeholder="
                                    trans(
                                        'settings.members.invite.role_placeholder',
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="WorkspaceRole.Member">{{
                                $t('settings.members.roles.member')
                            }}</SelectItem>
                            <SelectItem :value="WorkspaceRole.Admin">{{
                                $t('settings.members.roles.admin')
                            }}</SelectItem>
                            <SelectItem :value="WorkspaceRole.Viewer">{{
                                $t('settings.members.roles.viewer')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <input type="hidden" name="role" :value="inviteRole" />
                    <InputError :message="errors.role" />
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        {{ $t('settings.members.invite.submit') }}
                    </Button>
                    <Button
                        variant="secondary"
                        type="button"
                        @click="open = false"
                    >
                        {{ $t('settings.members.cancel') }}
                    </Button>
                </DialogFooter>
            </Form>

            <!-- TAB: Create Account -->
            <Form
                v-else
                v-bind="createAccountRoute.form()"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="onCreateSuccess"
            >
                <div class="grid gap-2">
                    <Label for="create-name">Họ tên</Label>
                    <Input
                        id="create-name"
                        name="name"
                        type="text"
                        placeholder="Nguyễn Văn A"
                        autocomplete="off"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="create-email">Email</Label>
                    <Input
                        id="create-email"
                        name="email"
                        type="email"
                        placeholder="member@company.com"
                        autocomplete="off"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="create-password">Mật khẩu</Label>
                    <div class="relative">
                        <Input
                            id="create-password"
                            name="password"
                            :type="showPassword ? 'text' : 'password'"
                            placeholder="Tối thiểu 8 ký tự"
                            autocomplete="new-password"
                            class="pr-20"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-2 flex items-center text-xs text-muted-foreground hover:text-foreground"
                            @click="showPassword = !showPassword"
                        >
                            {{ showPassword ? 'Ẩn' : 'Hiện' }}
                        </button>
                    </div>
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="create-password-confirmation">Xác nhận mật khẩu</Label>
                    <div class="relative">
                        <Input
                            id="create-password-confirmation"
                            name="password_confirmation"
                            :type="showConfirm ? 'text' : 'password'"
                            placeholder="Nhập lại mật khẩu"
                            autocomplete="new-password"
                            class="pr-20"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-2 flex items-center text-xs text-muted-foreground hover:text-foreground"
                            @click="showConfirm = !showConfirm"
                        >
                            {{ showConfirm ? 'Ẩn' : 'Hiện' }}
                        </button>
                    </div>
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="grid gap-2">
                    <Label for="create-role">Vai trò</Label>
                    <Select v-model="createRole" name="role">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Chọn vai trò" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="WorkspaceRole.Member">{{
                                $t('settings.members.roles.member')
                            }}</SelectItem>
                            <SelectItem :value="WorkspaceRole.Admin">{{
                                $t('settings.members.roles.admin')
                            }}</SelectItem>
                            <SelectItem :value="WorkspaceRole.Viewer">{{
                                $t('settings.members.roles.viewer')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <input type="hidden" name="role" :value="createRole" />
                    <InputError :message="errors.role" />
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400">
                    Tài khoản sẽ được tạo ngay lập tức. Hãy chia sẻ email + mật khẩu cho thành viên.
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Đang tạo...' : 'Tạo tài khoản' }}
                    </Button>
                    <Button
                        variant="secondary"
                        type="button"
                        @click="open = false"
                    >
                        {{ $t('settings.members.cancel') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
