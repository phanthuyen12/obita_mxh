<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconMessageCirclePlus } from '@tabler/icons-vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

import { store } from '@/actions/App/Http/Controllers/App/Omnichat/WebsiteChatController';
import WebsiteChatChannelCard from '@/components/omnichat/website-chat/WebsiteChatChannelCard.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';

type Channel = InstanceType<typeof WebsiteChatChannelCard>['$props']['channel'];
defineProps<{ channels: Channel[] }>();

const showCreate = ref(false);
const originsText = ref('https://example.com');
const form = useForm({
    name: 'Website chính',
    authorized_origins: ['https://example.com'],
    welcome_message: 'Xin chào! Chúng tôi có thể giúp gì cho bạn?',
    offline_message:
        'Hiện chúng tôi đang ngoài giờ làm việc. Vui lòng để lại lời nhắn.',
    primary_color: '#2563EB',
    position: 'right' as 'left' | 'right',
    privacy_url: '',
});

const createChannel = () => {
    form.authorized_origins = originsText.value
        .split('\n')
        .map((value) => value.trim())
        .filter(Boolean);
    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            showCreate.value = false;
            toast.success('Đã tạo kênh Website Live Chat.');
        },
    });
};
</script>

<template>
    <Head title="Website Live Chat" />
    <AppLayout>
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div class="grid gap-1">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Website Live Chat
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Tạo widget chat và đưa tin nhắn khách truy cập vào
                        Omnichat Inbox.
                    </p>
                </div>
                <Button @click="showCreate = !showCreate">
                    <IconMessageCirclePlus class="size-4" />
                    {{ showCreate ? 'Đóng' : 'Tạo kênh website' }}
                </Button>
            </div>

            <Card v-if="showCreate">
                <CardHeader>
                    <CardTitle>Tạo kênh mới</CardTitle>
                    <CardDescription
                        >Chỉ các origin trong danh sách mới có thể dùng khóa
                        widget.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form class="grid gap-4" @submit.prevent="createChannel">
                        <div class="grid gap-2 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="channel-name">Tên kênh</Label>
                                <Input id="channel-name" v-model="form.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="origins"
                                    >Domain được phép, mỗi dòng một
                                    origin</Label
                                >
                                <Textarea
                                    id="origins"
                                    v-model="originsText"
                                    rows="3"
                                />
                            </div>
                        </div>
                        <div class="grid gap-2 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="welcome">Lời chào</Label
                                ><Textarea
                                    id="welcome"
                                    v-model="form.welcome_message"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="offline">Tin ngoài giờ</Label
                                ><Textarea
                                    id="offline"
                                    v-model="form.offline_message"
                                />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="primary-color">Màu chính</Label
                                ><Input
                                    id="primary-color"
                                    v-model="form.primary_color"
                                    type="color"
                                    class="h-10"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="position">Vị trí</Label
                                ><select
                                    id="position"
                                    v-model="form.position"
                                    class="h-10 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="right">Bên phải</option>
                                    <option value="left">Bên trái</option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="privacy-url"
                                    >URL quyền riêng tư</Label
                                ><Input
                                    id="privacy-url"
                                    v-model="form.privacy_url"
                                    type="url"
                                />
                            </div>
                        </div>
                        <p
                            v-if="Object.keys(form.errors).length"
                            class="text-sm text-destructive"
                        >
                            Vui lòng kiểm tra lại thông tin cấu hình.
                        </p>
                        <Button type="submit" :disabled="form.processing"
                            >Tạo và lấy mã nhúng</Button
                        >
                    </form>
                </CardContent>
            </Card>

            <div v-if="channels.length" class="grid gap-4 lg:grid-cols-2">
                <WebsiteChatChannelCard
                    v-for="channel in channels"
                    :key="channel.id"
                    :channel="channel"
                />
            </div>
            <div
                v-else-if="!showCreate"
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                Chưa có kênh Website Live Chat. Hãy tạo kênh đầu tiên để lấy mã
                nhúng.
            </div>
        </div>
    </AppLayout>
</template>
