<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { IconCopy, IconKey, IconPower } from '@tabler/icons-vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

import {
    destroy,
    rotate,
    update,
} from '@/actions/App/Http/Controllers/App/Omnichat/WebsiteChatController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Channel = {
    id: string;
    name: string;
    public_key: string;
    status: string;
    settings: {
        authorized_origins?: string[];
        welcome_message?: string;
        offline_message?: string;
        primary_color?: string;
        position?: 'left' | 'right';
        privacy_url?: string | null;
    };
};

const props = defineProps<{ channel: Channel }>();
const editing = ref(false);
const form = useForm({
    name: props.channel.name,
    authorized_origins: props.channel.settings.authorized_origins ?? [],
    welcome_message:
        props.channel.settings.welcome_message ??
        'Xin chào! Chúng tôi có thể giúp gì cho bạn?',
    offline_message:
        props.channel.settings.offline_message ??
        'Hiện chúng tôi đang ngoài giờ làm việc. Vui lòng để lại lời nhắn.',
    primary_color: props.channel.settings.primary_color ?? '#2563EB',
    position: props.channel.settings.position ?? 'right',
    privacy_url: props.channel.settings.privacy_url ?? '',
});
const originsText = ref(form.authorized_origins.join('\n'));

const save = () => {
    form.authorized_origins = originsText.value
        .split('\n')
        .map((origin) => origin.trim())
        .filter(Boolean);
    form.submit(update(props.channel.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
            toast.success('Đã cập nhật kênh website.');
        },
    });
};

const copySnippet = async () => {
    const snippet = `<script src="${window.location.origin}/website-chat/widget.js" data-public-key="${props.channel.public_key}" async><\/script>`;
    await navigator.clipboard.writeText(snippet);
    toast.success('Đã sao chép mã nhúng.');
};

const rotateKey = () => {
    if (
        !window.confirm(
            'Khóa cũ sẽ ngừng hoạt động. Bạn chắc chắn muốn xoay khóa?',
        )
    )
        return;
    form.submit(rotate(props.channel.id), { preserveScroll: true });
};

const disableChannel = () => {
    if (!window.confirm('Tắt kênh sẽ ngăn khách tạo phiên chat mới. Tiếp tục?'))
        return;
    form.submit(destroy(props.channel.id), { preserveScroll: true });
};
</script>

<template>
    <Card>
        <CardHeader class="flex-row items-start justify-between gap-4">
            <div class="grid gap-1">
                <CardTitle>{{ channel.name }}</CardTitle>
                <span
                    class="w-fit rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="
                        channel.status === 'connected'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-muted text-muted-foreground'
                    "
                >
                    {{
                        channel.status === 'connected'
                            ? 'Đang hoạt động'
                            : 'Đã tắt'
                    }}
                </span>
            </div>
            <Button variant="outline" size="sm" @click="editing = !editing">
                {{ editing ? 'Đóng' : 'Chỉnh sửa' }}
            </Button>
        </CardHeader>

        <CardContent class="grid gap-5">
            <div class="grid gap-2">
                <Label>Khóa công khai</Label>
                <div class="flex gap-2">
                    <Input
                        :model-value="channel.public_key"
                        readonly
                        class="font-mono text-xs"
                    />
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Sao chép mã nhúng"
                        @click="copySnippet"
                    >
                        <IconCopy class="size-4" />
                    </Button>
                </div>
            </div>

            <form v-if="editing" class="grid gap-4" @submit.prevent="save">
                <div class="grid gap-2">
                    <Label :for="`name-${channel.id}`">Tên kênh</Label>
                    <Input :id="`name-${channel.id}`" v-model="form.name" />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label :for="`origins-${channel.id}`"
                        >Domain được phép, mỗi dòng một origin</Label
                    >
                    <Textarea
                        :id="`origins-${channel.id}`"
                        v-model="originsText"
                        rows="3"
                    />
                    <p
                        v-if="form.errors.authorized_origins"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.authorized_origins }}
                    </p>
                </div>
                <div class="grid gap-2 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="`welcome-${channel.id}`">Lời chào</Label>
                        <Textarea
                            :id="`welcome-${channel.id}`"
                            v-model="form.welcome_message"
                            rows="3"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`offline-${channel.id}`"
                            >Tin ngoài giờ</Label
                        >
                        <Textarea
                            :id="`offline-${channel.id}`"
                            v-model="form.offline_message"
                            rows="3"
                        />
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label :for="`color-${channel.id}`">Màu chính</Label>
                        <Input
                            :id="`color-${channel.id}`"
                            v-model="form.primary_color"
                            type="color"
                            class="h-10"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`position-${channel.id}`">Vị trí</Label>
                        <select
                            :id="`position-${channel.id}`"
                            v-model="form.position"
                            class="h-10 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="right">Bên phải</option>
                            <option value="left">Bên trái</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`privacy-${channel.id}`"
                            >URL quyền riêng tư</Label
                        >
                        <Input
                            :id="`privacy-${channel.id}`"
                            v-model="form.privacy_url"
                            type="url"
                        />
                    </div>
                </div>
                <Button type="submit" :disabled="form.processing"
                    >Lưu cấu hình</Button
                >
            </form>

            <div class="flex flex-wrap gap-2 border-t pt-4">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="form.processing"
                    @click="rotateKey"
                >
                    <IconKey class="size-4" /> Xoay khóa
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    :disabled="
                        form.processing || channel.status !== 'connected'
                    "
                    @click="disableChannel"
                >
                    <IconPower class="size-4" /> Tắt kênh
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
