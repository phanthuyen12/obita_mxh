<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { IconArrowLeft, IconMail, IconSearch } from '@tabler/icons-vue';
import { ref, watch } from 'vue';

import CampaignFormDialog from '@/components/email-campaigns/CampaignFormDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableLoadMore,
    TableRow,
} from '@/components/ui/table';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as campaignsIndex } from '@/routes/app/email-campaigns';
import { index as listsIndex, show as listShow } from '@/routes/app/email-campaigns/lists';

interface ContactList {
    id: string;
    name: string;
    source_filename: string | null;
    imported_count: number;
    invalid_count: number;
    duplicate_count: number;
}

interface Contact {
    id: string;
    email: string;
    name: string | null;
    status: string;
}

interface Props {
    list: ContactList;
    contacts: { data: Contact[]; meta?: { hasNextPage: boolean } };
    filters: { search: string };
}

const props = defineProps<Props>();

const isCreateOpen = ref(false);
const searchQuery = ref(props.filters.search);

const search = debounce(() => {
    router.get(
        listShow.url(props.list.id),
        { search: searchQuery.value || undefined },
        { preserveState: true, preserveScroll: true, reset: ['contacts'] },
    );
}, 300);

watch(searchQuery, () => search());

const statusVariant = (status: string): 'success' | 'secondary' | 'destructive' => {
    if (status === 'active') {
        return 'success';
    }

    return status === 'unsubscribed' ? 'secondary' : 'destructive';
};

const statusLabel = (status: string): string =>
    ({
        active: 'Đang nhận',
        unsubscribed: 'Đã huỷ nhận',
        bounced: 'Bị trả lại',
        complained: 'Báo spam',
    })[status] ?? status;
</script>

<template>
    <Head :title="list.name" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 px-6 py-8">
            <PageHeader
                :title="list.name"
                :description="`${list.imported_count} email hợp lệ · ${list.invalid_count} lỗi · ${list.duplicate_count} trùng`"
            />

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline">
                        <Link :href="listsIndex.url()">
                            <IconArrowLeft class="size-4" />
                            Về danh sách
                        </Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="campaignsIndex.url()">Về chiến dịch</Link>
                    </Button>
                    <Button @click="isCreateOpen = true">
                        <IconMail class="size-4" />
                        Tạo chiến dịch với danh sách này
                    </Button>
                </div>

                <div class="relative w-full sm:w-auto">
                    <IconSearch
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Tìm theo email"
                        class="w-full pl-9 sm:w-64"
                    />
                </div>
            </div>

            <InfiniteScroll
                data="contacts"
                items-element="#contacts-body"
                preserve-url
            >
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Email</TableHead>
                            <TableHead>Tên</TableHead>
                            <TableHead>Trạng thái</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody id="contacts-body">
                        <TableRow
                            v-for="contact in contacts.data"
                            :key="contact.id"
                        >
                            <TableCell>{{ contact.email }}</TableCell>
                            <TableCell>{{ contact.name ?? '—' }}</TableCell>
                            <TableCell>
                                <Badge :variant="statusVariant(contact.status)">
                                    {{ statusLabel(contact.status) }}
                                </Badge>
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
        v-model:open="isCreateOpen"
        :contact-lists="[{ id: list.id, name: list.name }]"
        :default-contact-list-id="list.id"
    />
</template>
