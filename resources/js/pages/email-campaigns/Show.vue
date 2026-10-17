<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    IconAlertTriangle,
    IconPlayerPause,
    IconRefresh,
    IconSend,
    IconX,
} from '@tabler/icons-vue';
import CampaignFormDialog from '@/components/email-campaigns/CampaignFormDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableLoadMore,
    TableRow,
} from '@/components/ui/table';
import date from '@/date';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    campaignLabel,
    campaignVariant,
    recipientLabel,
    recipientVariant,
} from '@/lib/emailCampaignStatus';
import {
    cancel as campaignCancel,
    pause as campaignPause,
    retryFailed as campaignRetryFailed,
    send as campaignSend,
    show as campaignShow,
} from '@/routes/app/email-campaigns';

interface Campaign {
    id: string;
    name: string;
    subject: string;
    preview_text: string | null;
    body_html: string;
    from_name: string | null;
    reply_to: string | null;
    email_contact_list_id: string | null;
    delay_seconds: number;
    status: string;
    scheduled_at: string | null;
    contact_list: { id: string; name: string } | null;
}

interface Recipient {
    id: string;
    email: string;
    name: string | null;
    status: string;
    error_message: string | null;
    sent_at: string | null;
}

interface Props {
    campaign: Campaign;
    recipients: { data: Recipient[]; meta?: { hasNextPage: boolean } };
    stats: Record<string, number>;
    filters: { status: string; search: string };
    recipientStatuses: { value: string; label: string }[];
    mailConfigured: boolean;
}

const props = defineProps<Props>();

const statusFilter = ref(props.filters.status);
const searchQuery = ref(props.filters.search);
const isEditOpen = ref(false);
const isBusy = ref(false);
const isSendConfirmationOpen = ref(false);
const isCancelConfirmationOpen = ref(false);

const canSend = computed(() =>
    ['draft', 'scheduled', 'paused'].includes(props.campaign.status),
);
const canCancel = computed(
    () => !['completed', 'cancelled'].includes(props.campaign.status),
);

const reload = () => {
    router.get(
        campaignShow.url(props.campaign.id),
        {
            status: statusFilter.value || undefined,
            search: searchQuery.value || undefined,
        },
        { preserveState: true, preserveScroll: true, reset: ['recipients'] },
    );
};

const search = debounce(() => reload(), 300);

watch(statusFilter, () => reload());
watch(searchQuery, () => search());

const post = (url: string) => {
    isBusy.value = true;

    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isBusy.value = false;
            },
        },
    );
};

const sendNow = () => post(campaignSend.url(props.campaign.id));
const pause = () => post(campaignPause.url(props.campaign.id));
const cancel = () => post(campaignCancel.url(props.campaign.id));
const retryFailed = () => post(campaignRetryFailed.url(props.campaign.id));

const formatDateTime = (value: string | null): string =>
    value ? date.formatDateTime(value) : '—';

const statCards: { key: string; label: string }[] = [
    { key: 'total', label: 'Tổng' },
    { key: 'queued', label: 'Chờ gửi' },
    { key: 'sent', label: 'Đã gửi' },
    { key: 'delivered', label: 'Đã nhận' },
    { key: 'opened', label: 'Đã mở' },
    { key: 'failed', label: 'Lỗi' },
    { key: 'skipped', label: 'Bỏ qua' },
];
</script>

<template>
    <Head :title="campaign.name" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 px-6 py-8">
            <PageHeader :title="campaign.name" :description="campaign.subject" />

            <div class="flex flex-wrap items-center gap-3">
                <Badge :variant="campaignVariant(campaign.status)">
                    {{ campaignLabel(campaign.status) }}
                </Badge>
                <span class="text-sm text-muted-foreground">
                    Danh sách: {{ campaign.contact_list?.name ?? '—' }}
                </span>
                <span
                    v-if="campaign.scheduled_at"
                    class="text-sm text-muted-foreground"
                >
                    Hẹn gửi: {{ formatDateTime(campaign.scheduled_at) }}
                </span>
            </div>

            <div
                v-if="!mailConfigured"
                class="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
            >
                <IconAlertTriangle class="mt-0.5 size-5 shrink-0" />
                <p class="text-sm">
                    Workspace chưa cấu hình SMTP riêng nên chưa gửi được chiến
                    dịch này.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="canSend"
                    :disabled="isBusy || !mailConfigured"
                    @click="isSendConfirmationOpen = true"
                >
                    <IconSend class="size-4" />
                    {{ campaign.status === 'paused' ? 'Tiếp tục gửi' : 'Gửi ngay' }}
                </Button>
                <Button
                    v-if="campaign.status === 'sending'"
                    variant="outline"
                    :disabled="isBusy"
                    @click="pause"
                >
                    <IconPlayerPause class="size-4" />
                    Tạm dừng
                </Button>
                <Button
                    v-if="(stats.failed ?? 0) > 0"
                    variant="outline"
                    :disabled="isBusy"
                    @click="retryFailed"
                >
                    <IconRefresh class="size-4" />
                    Gửi lại email lỗi
                </Button>
                <Button
                    v-if="canCancel"
                    variant="ghost"
                    class="text-destructive hover:text-destructive"
                    :disabled="isBusy"
                    @click="isCancelConfirmationOpen = true"
                >
                    <IconX class="size-4" />
                    Huỷ chiến dịch
                </Button>
            </div>

            <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-7">
                <Card v-for="card in statCards" :key="card.key">
                    <CardContent class="p-4">
                        <p class="text-sm text-muted-foreground">
                            {{ card.label }}
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ stats[card.key] ?? 0 }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardContent class="space-y-4 p-5">
                    <div
                        class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">Nội dung email</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ campaign.subject }}
                                <span v-if="campaign.preview_text">
                                    · {{ campaign.preview_text }}
                                </span>
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            :disabled="isBusy || campaign.status === 'sending'"
                            @click="isEditOpen = true"
                        >
                            Sửa HTML / CSS
                        </Button>
                    </div>
                    <iframe
                        :srcdoc="campaign.body_html"
                        sandbox
                        title="Bản xem trước email chiến dịch"
                        class="h-[520px] w-full rounded-lg border bg-white"
                    />
                </CardContent>
            </Card>

            <div class="space-y-1">
                <h2 class="text-lg font-semibold">Nhật ký gửi email</h2>
                <p class="text-sm text-muted-foreground">
                    Trạng thái từng người nhận, thời điểm gửi và thông tin lỗi.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <Input
                    v-model="searchQuery"
                    placeholder="Tìm theo email"
                    class="w-full sm:w-64"
                />
                <NativeSelect v-model="statusFilter" class="sm:w-44">
                    <NativeSelectOption value="">
                        Tất cả trạng thái
                    </NativeSelectOption>
                    <NativeSelectOption
                        v-for="status in recipientStatuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </NativeSelectOption>
                </NativeSelect>
            </div>

            <InfiniteScroll
                data="recipients"
                items-element="#recipients-body"
                preserve-url
            >
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Email</TableHead>
                            <TableHead>Tên</TableHead>
                            <TableHead>Trạng thái</TableHead>
                            <TableHead>Gửi lúc</TableHead>
                            <TableHead>Ghi chú</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody id="recipients-body">
                        <TableRow
                            v-for="recipient in recipients.data"
                            :key="recipient.id"
                        >
                            <TableCell>{{ recipient.email }}</TableCell>
                            <TableCell>{{ recipient.name ?? '—' }}</TableCell>
                            <TableCell>
                                <Badge
                                    :variant="recipientVariant(recipient.status)"
                                >
                                    {{ recipientLabel(recipient.status) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                {{ formatDateTime(recipient.sent_at) }}
                            </TableCell>
                            <TableCell class="max-w-xs">
                                <p class="truncate text-sm text-muted-foreground">
                                    {{ recipient.error_message ?? '—' }}
                                </p>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <template #next="{ loading }">
                    <TableLoadMore v-if="loading" />
                </template>
            </InfiniteScroll>
        </div>
    </AppLayout>

    <CampaignFormDialog
        v-model:open="isEditOpen"
        :campaign="campaign"
        :contact-lists="
            campaign.contact_list ? [campaign.contact_list] : []
        "
    />

    <AlertDialog v-model:open="isSendConfirmationOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {{ campaign.status === 'paused' ? 'Tiếp tục gửi chiến dịch?' : 'Bắt đầu gửi chiến dịch?' }}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    Email sẽ được đưa vào hàng đợi gửi tới danh sách
                    “{{ campaign.contact_list?.name ?? 'người nhận' }}”. Bạn có thể tạm dừng khi chiến dịch đang chạy.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Để sau</AlertDialogCancel>
                <AlertDialogAction :disabled="isBusy" @click="sendNow">
                    {{ campaign.status === 'paused' ? 'Tiếp tục gửi' : 'Xác nhận gửi' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <AlertDialog v-model:open="isCancelConfirmationOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Huỷ chiến dịch này?</AlertDialogTitle>
                <AlertDialogDescription>
                    Chiến dịch sẽ dừng và không thể tiếp tục gửi. Những email đã gửi sẽ không bị thu hồi.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Quay lại</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-white hover:bg-destructive/90"
                    :disabled="isBusy"
                    @click="cancel"
                >
                    Huỷ chiến dịch
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
