<script setup lang="ts">
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/App/WordPressSiteController';
import { useForm } from '@inertiajs/vue3';
import { IconInfoCircle, IconLoader2, IconWorld } from '@tabler/icons-vue';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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

const open = defineModel<boolean>('open', { default: false });

type WordPressSite = {
    id: string;
    name: string;
    url: string;
    username: string;
};

const props = defineProps<{ site?: WordPressSite | null }>();
const isEditing = computed(
    () => props.site !== null && props.site !== undefined,
);

const form = useForm({
    name: '',
    url: '',
    username: '',
    application_password: '',
});

const fillForm = () => {
    form.name = props.site?.name ?? '';
    form.url = props.site?.url ?? '';
    form.username = props.site?.username ?? '';
    form.application_password = '';
    form.clearErrors();
};

watch(open, (isOpen) => {
    if (isOpen) {
        fillForm();
    }
});

watch(
    () => props.site,
    () => {
        if (open.value) {
            fillForm();
        }
    },
);

const submit = () => {
    const action =
        isEditing.value && props.site ? update.url(props.site.id) : store.url();
    const method = isEditing.value ? 'put' : 'post';

    form.submit(method, action, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(
                isEditing.value
                    ? 'Đã cập nhật cấu hình WordPress!'
                    : 'Kết nối website WordPress thành công!',
            );
            form.reset();
            open.value = false;
        },
        onError: (errors) => {
            if (errors.connection) {
                toast.error(errors.connection);
            } else {
                toast.error('Vui lòng kiểm tra lại các trường thông tin');
            }
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="(val: boolean) => (open = val)">
        <DialogContent class="sm:max-w-[540px]">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-cyan-100 p-2 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400"
                    >
                        <IconWorld class="size-6" />
                    </div>
                    <div>
                        <DialogTitle class="text-lg font-semibold">
                            {{
                                isEditing
                                    ? 'Chỉnh sửa Website WordPress'
                                    : 'Kết nối Website WordPress'
                            }}
                        </DialogTitle>
                        <DialogDescription
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                isEditing
                                    ? 'Cập nhật domain, username hoặc Application Password.'
                                    : 'Đăng bài tự động lên website WordPress qua REST API chuẩn.'
                            }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 py-2">
                <Alert
                    class="border-cyan-200 bg-cyan-50/50 dark:border-cyan-900 dark:bg-cyan-950/30"
                >
                    <IconInfoCircle
                        class="size-4 text-cyan-600 dark:text-cyan-400"
                    />
                    <AlertTitle
                        class="text-xs font-semibold text-cyan-950 dark:text-cyan-200"
                    >
                        Cách lấy Application Password (Mật khẩu ứng dụng)
                    </AlertTitle>
                    <AlertDescription
                        class="mt-1 space-y-1 text-xs text-muted-foreground"
                    >
                        <p>
                            1. Vào <strong>WP Admin</strong> &rarr;
                            <strong>Thành viên (Users)</strong> &rarr;
                            <strong>Hồ sơ cá nhân (Profile)</strong>.
                        </p>
                        <p>
                            2. Cuộn xuống phần
                            <strong
                                >Mật khẩu ứng dụng (Application
                                Passwords)</strong
                            >, nhập tên
                            <code class="rounded bg-muted px-1">King Hub</code>
                            rồi bấm <em>Thêm mật khẩu mới</em>.
                        </p>
                        <p>3. Copy mật khẩu được cấp và dán vào ô bên dưới.</p>
                    </AlertDescription>
                </Alert>

                <div class="grid gap-2">
                    <Label for="site-name" class="text-sm font-medium"
                        >Tên Website / Gợi nhớ</Label
                    >
                    <Input
                        id="site-name"
                        v-model="form.name"
                        placeholder="VD: King Coffee Blog, TNI Corporation..."
                        required
                    />
                    <span
                        v-if="form.errors.name"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.name }}
                    </span>
                </div>

                <div class="grid gap-2">
                    <Label for="site-url" class="text-sm font-medium"
                        >Địa chỉ Website (Domain / URL)</Label
                    >
                    <Input
                        id="site-url"
                        v-model="form.url"
                        placeholder="https://kingcoffee.com"
                        type="url"
                        required
                    />
                    <span
                        v-if="form.errors.url"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.url }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="site-username" class="text-sm font-medium"
                            >Tên đăng nhập (Username)</Label
                        >
                        <Input
                            id="site-username"
                            v-model="form.username"
                            placeholder="admin hoặc author"
                            required
                        />
                        <span
                            v-if="form.errors.username"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.username }}
                        </span>
                    </div>

                    <div class="grid gap-2">
                        <Label
                            for="site-app-password"
                            class="text-sm font-medium"
                            >Application Password</Label
                        >
                        <Input
                            id="site-app-password"
                            v-model="form.application_password"
                            type="password"
                            placeholder="xxxx xxxx xxxx xxxx"
                            :required="!isEditing"
                        />
                        <p
                            v-if="isEditing"
                            class="text-[11px] text-muted-foreground"
                        >
                            Để trống nếu muốn giữ Application Password hiện tại.
                        </p>
                        <span
                            v-if="form.errors.application_password"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.application_password }}
                        </span>
                    </div>
                </div>

                <span
                    v-if="form.errors.connection"
                    class="block text-xs font-medium text-destructive"
                >
                    {{ form.errors.connection }}
                </span>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="form.processing"
                    >
                        Hủy
                    </Button>
                    <Button
                        type="submit"
                        class="bg-[#21759B] text-white hover:bg-[#1b6282]"
                        :disabled="form.processing"
                    >
                        <IconLoader2
                            v-if="form.processing"
                            class="mr-2 size-4 animate-spin"
                        />
                        {{
                            form.processing
                                ? 'Đang kiểm tra kết nối...'
                                : isEditing
                                  ? 'Lưu cấu hình'
                                  : 'Kiểm tra & Kết nối'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
