<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconCircleCheck, IconSend } from '@tabler/icons-vue';
import { ref } from 'vue';

import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import date from '@/date';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as campaignsIndex } from '@/routes/app/email-campaigns';
import {
    test as mailSettingsTest,
    update as mailSettingsUpdate,
} from '@/routes/app/email-campaigns/mail-settings';

interface Settings {
    host: string;
    port: number;
    encryption: string | null;
    username: string | null;
    from_address: string;
    from_name: string;
    reply_to: string | null;
    daily_limit: number | null;
    verified_at: string | null;
    last_error: string | null;
    has_password: boolean;
}

const props = defineProps<{ settings: Settings | null }>();

const isTesting = ref(false);

const form = useForm({
    host: props.settings?.host ?? '',
    port: props.settings?.port ?? 587,
    encryption: props.settings?.encryption ?? 'tls',
    username: props.settings?.username ?? '',
    password: '',
    from_address: props.settings?.from_address ?? '',
    from_name: props.settings?.from_name ?? '',
    reply_to: props.settings?.reply_to ?? '',
    daily_limit: props.settings?.daily_limit ?? null,
});

const submit = () => {
    form.put(mailSettingsUpdate.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.password = '';
        },
    });
};

const sendTest = () => {
    isTesting.value = true;

    router.post(
        mailSettingsTest.url(),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isTesting.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Cấu hình email" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 px-6 py-8">
            <PageHeader
                title="Cấu hình email gửi đi"
                description="Chiến dịch luôn gửi qua SMTP riêng của workspace, không dùng mail hệ thống."
            />

            <div>
                <Button as-child variant="outline">
                    <Link :href="campaignsIndex.url()">
                        <IconArrowLeft class="size-4" />
                        Về chiến dịch
                    </Link>
                </Button>
            </div>

            <Card
                v-if="settings?.verified_at"
                class="border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950"
            >
                <CardContent class="flex items-center gap-3 p-4">
                    <IconCircleCheck class="size-5 text-emerald-600" />
                    <p class="text-sm text-emerald-900 dark:text-emerald-100">
                        Đã gửi thử thành công lúc
                        {{ date.formatDateTime(settings.verified_at) }}.
                    </p>
                </CardContent>
            </Card>

            <Card
                v-else-if="settings?.last_error"
                class="border-destructive/40 bg-destructive/5"
            >
                <CardContent class="p-4">
                    <p class="text-sm font-medium text-destructive">
                        Lần gửi thử gần nhất thất bại
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ settings.last_error }}
                    </p>
                </CardContent>
            </Card>

            <form class="max-w-2xl space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="mail-host">Máy chủ SMTP</Label>
                        <Input
                            id="mail-host"
                            v-model="form.host"
                            placeholder="smtp.example.com"
                            :class="{ 'border-destructive': form.errors.host }"
                        />
                        <p
                            v-if="form.errors.host"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.host }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="mail-port">Cổng</Label>
                        <Input
                            id="mail-port"
                            v-model.number="form.port"
                            type="number"
                            :class="{ 'border-destructive': form.errors.port }"
                        />
                        <p
                            v-if="form.errors.port"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.port }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="mail-encryption">Mã hoá</Label>
                    <NativeSelect
                        id="mail-encryption"
                        v-model="form.encryption"
                        class="w-full sm:w-44"
                    >
                        <NativeSelectOption value="tls">TLS</NativeSelectOption>
                        <NativeSelectOption value="ssl">SSL</NativeSelectOption>
                        <NativeSelectOption value="">Không</NativeSelectOption>
                    </NativeSelect>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="mail-username">Tài khoản</Label>
                        <Input
                            id="mail-username"
                            v-model="form.username"
                            autocomplete="off"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="mail-password">Mật khẩu</Label>
                        <Input
                            id="mail-password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                settings?.has_password
                                    ? 'Để trống nếu giữ mật khẩu cũ'
                                    : ''
                            "
                        />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="mail-from-address">Email người gửi</Label>
                        <Input
                            id="mail-from-address"
                            v-model="form.from_address"
                            :class="{
                                'border-destructive': form.errors.from_address,
                            }"
                        />
                        <p
                            v-if="form.errors.from_address"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.from_address }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="mail-from-name">Tên người gửi</Label>
                        <Input
                            id="mail-from-name"
                            v-model="form.from_name"
                            :class="{
                                'border-destructive': form.errors.from_name,
                            }"
                        />
                        <p
                            v-if="form.errors.from_name"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.from_name }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="mail-reply-to">Email nhận phản hồi</Label>
                        <Input id="mail-reply-to" v-model="form.reply_to" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="mail-daily-limit">
                            Giới hạn gửi mỗi ngày
                        </Label>
                        <Input
                            id="mail-daily-limit"
                            v-model.number="form.daily_limit"
                            type="number"
                            min="1"
                            placeholder="Để trống nếu không giới hạn"
                        />
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Đang lưu…' : 'Lưu cấu hình' }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isTesting || !settings"
                        @click="sendTest"
                    >
                        <IconSend class="size-4" />
                        {{ isTesting ? 'Đang gửi…' : 'Gửi email thử' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
