<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import {
    IconAlertTriangle,
    IconMailFast,
    IconSearch,
    IconSettings,
    IconUsers,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import CampaignFormDialog from '@/components/email-campaigns/CampaignFormDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { campaignLabel, campaignVariant } from '@/lib/emailCampaignStatus';
import {
    index as campaignsIndex,
    show as campaignShow,
} from '@/routes/app/email-campaigns';
import { index as listsIndex } from '@/routes/app/email-campaigns/lists';
import { edit as mailSettingsEdit } from '@/routes/app/email-campaigns/mail-settings';

interface CampaignRow {
    id: string;
    name: string;
    subject: string;
    status: string;
    created_at: string;
    scheduled_at: string | null;
    stats: Record<string, number> | null;
    contact_list: { id: string; name: string } | null;
}

interface ContactList {
    id: string;
    name: string;
}

interface Props {
    campaigns: { data: CampaignRow[]; meta?: { hasNextPage: boolean } };
    filters: { search: string; status: string };
    contactLists: ContactList[];
    mailConfigured: boolean;
    statuses: { value: string; label: string }[];
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search);
const statusFilter = ref(props.filters.status);
const isCreateOpen = ref(false);

const reload = () => {
    router.get(
        campaignsIndex.url(),
        {
            search: searchQuery.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true, preserveScroll: true, reset: ['campaigns'] },
    );
};

const search = debounce(() => reload(), 300);

watch(searchQuery, () => search());
watch(statusFilter, () => reload());

const hasFilters = computed(
    () => Boolean(searchQuery.value?.trim()) || Boolean(statusFilter.value),
);

const sentCount = (campaign: CampaignRow): number => {
    const stats = campaign.stats;

    if (!stats) {
        return 0;
    }

    return (
        (stats.sent ?? 0) +
        (stats.delivered ?? 0) +
        (stats.opened ?? 0) +
        (stats.clicked ?? 0)
    );
};

const formatDate = (value: string): string => date.formatDateTime(value);
</script>

<template>
    <Head title="Chiến dịch email" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 px-6 py-8">
            <PageHeader
                title="Chiến dịch email"
                description="Gửi email hàng loạt tới danh sách đã nhập từ file."
            />

            <div
                v-if="!mailConfigured"
                class="flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
            >
                <div class="flex items-start gap-3">
                    <IconAlertTriangle class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-medium">Chưa cấu hình SMTP</p>
                        <p class="text-sm">
                            Workspace cần SMTP riêng trước khi gửi chiến dịch.
                        </p>
                    </div>
                </div>
                <Button as-child variant="outline">
                    <Link :href="mailSettingsEdit.url()">Cấu hình ngay</Link>
                </Button>
            </div>

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative w-full sm:w-auto">
                        <IconSearch
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Tìm theo tên hoặc tiêu đề"
                            class="w-full pl-9 sm:w-64"
                        />
                    </div>

                    <NativeSelect v-model="statusFilter" class="sm:w-44">
                        <NativeSelectOption value="">
                            Tất cả trạng thái
                        </NativeSelectOption>
                        <NativeSelectOption
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </NativeSelectOption>
                    </NativeSelect>
                </div>

                <div class="flex gap-2">
                    <Button as-child variant="outline">
                        <Link :href="listsIndex.url()">
                            <IconUsers class="size-4" />
                            Danh sách email
                        </Link>
                    </Button>
                    <Button as-child variant="outline" size="icon">
                        <Link
                            :href="mailSettingsEdit.url()"
                            aria-label="Cấu hình email"
                        >
                            <IconSettings class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        :disabled="contactLists.length === 0"
                        @click="isCreateOpen = true"
                    >
                        Tạo chiến dịch
                    </Button>
                </div>
            </div>

            <EmptyState
                v-if="campaigns.data.length === 0"
                :icon="IconMailFast"
                :title="
                    hasFilters
                        ? 'Không có chiến dịch phù hợp'
                        : 'Chưa có chiến dịch nào'
                "
                :description="
                    hasFilters
                        ? 'Thử đổi từ khoá hoặc bộ lọc trạng thái.'
                        : 'Nhập một file danh sách email, rồi tạo chiến dịch đầu tiên.'
                "
            />

            <div v-else>
                <InfiniteScroll
                    data="campaigns"
                    items-element="#campaigns-body"
                    preserve-url
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tên</TableHead>
                                <TableHead>Danh sách</TableHead>
                                <TableHead>Trạng thái</TableHead>
                                <TableHead>Đã gửi</TableHead>
                                <TableHead>Tạo lúc</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody id="campaigns-body">
                            <TableRow
                                v-for="campaign in campaigns.data"
                                :key="campaign.id"
                                class="cursor-pointer"
                                @click="
                                    router.visit(campaignShow.url(campaign.id))
                                "
                            >
                                <TableCell>
                                    <p class="font-medium">
                                        {{ campaign.name }}
                                    </p>
                                    <p
                                        class="truncate text-sm text-muted-foreground"
                                    >
                                        {{ campaign.subject }}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    {{ campaign.contact_list?.name ?? '—' }}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            campaignVariant(campaign.status)
                                        "
                                    >
                                        {{ campaignLabel(campaign.status) }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    {{ sentCount(campaign) }} /
                                    {{ campaign.stats?.total ?? 0 }}
                                </TableCell>
                                <TableCell>
                                    {{ formatDate(campaign.created_at) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <template #next="{ loading }">
                        <TableLoadMore v-if="loading" />
                    </template>
                </InfiniteScroll>
            </div>
        </div>
    </AppLayout>

    <CampaignFormDialog
        v-model:open="isCreateOpen"
        :contact-lists="contactLists"
    />
</template>
