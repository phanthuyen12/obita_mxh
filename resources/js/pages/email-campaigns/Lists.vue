<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { IconArrowLeft, IconTrash, IconUsers } from '@tabler/icons-vue';
import { ref } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import ImportDialog from '@/components/email-campaigns/ImportDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { importLabel, importVariant } from '@/lib/emailCampaignStatus';
import { index as campaignsIndex } from '@/routes/app/email-campaigns';
import {
    destroy as listDestroy,
    show as listShow,
} from '@/routes/app/email-campaigns/lists';

interface ContactList {
    id: string;
    name: string;
    source_filename: string | null;
    status: string;
    contacts_count: number;
    invalid_count: number;
    duplicate_count: number;
    error_message: string | null;
    created_at: string;
}

defineProps<{
    lists: { data: ContactList[]; meta?: { hasNextPage: boolean } };
}>();

const isImportOpen = ref(false);
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const confirmDelete = (list: ContactList) => {
    deleteModal.value?.open({
        url: listDestroy.url(list.id),
        confirmText: list.name,
    });
};

const formatDate = (value: string): string => date.formatDate(value);
</script>

<template>
    <Head title="Danh sách email" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 px-6 py-8">
            <PageHeader
                title="Danh sách email"
                description="Mỗi lần nhập file tạo ra một danh sách người nhận."
            />

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <Button as-child variant="outline">
                    <Link :href="campaignsIndex.url()">
                        <IconArrowLeft class="size-4" />
                        Về chiến dịch
                    </Link>
                </Button>

                <Button @click="isImportOpen = true">Nhập file</Button>
            </div>

            <EmptyState
                v-if="lists.data.length === 0"
                :icon="IconUsers"
                title="Chưa có danh sách nào"
                description="Tải lên file CSV hoặc Excel chứa cột email để bắt đầu."
            />

            <div v-else>
                <InfiniteScroll
                    data="lists"
                    items-element="#lists-body"
                    preserve-url
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tên</TableHead>
                                <TableHead>Trạng thái</TableHead>
                                <TableHead>Email hợp lệ</TableHead>
                                <TableHead>Lỗi / trùng</TableHead>
                                <TableHead>Nhập lúc</TableHead>
                                <TableHead class="text-right" />
                            </TableRow>
                        </TableHeader>
                        <TableBody id="lists-body">
                            <TableRow
                                v-for="list in lists.data"
                                :key="list.id"
                                class="cursor-pointer"
                                @click="router.visit(listShow.url(list.id))"
                            >
                                <TableCell>
                                    <p class="font-medium">{{ list.name }}</p>
                                    <p
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ list.source_filename ?? '—' }}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="importVariant(list.status)">
                                        {{ importLabel(list.status) }}
                                    </Badge>
                                    <p
                                        v-if="list.error_message"
                                        class="mt-1 max-w-[220px] truncate text-sm text-destructive"
                                    >
                                        {{ list.error_message }}
                                    </p>
                                </TableCell>
                                <TableCell>{{ list.contacts_count }}</TableCell>
                                <TableCell>
                                    {{ list.invalid_count }} /
                                    {{ list.duplicate_count }}
                                </TableCell>
                                <TableCell>
                                    {{ formatDate(list.created_at) }}
                                </TableCell>
                                <TableCell class="text-right" @click.stop>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="size-8 bg-rose-100 hover:bg-rose-200"
                                        aria-label="Xoá danh sách"
                                        @click="confirmDelete(list)"
                                    >
                                        <IconTrash
                                            class="size-4 text-rose-700"
                                        />
                                    </Button>
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

    <ImportDialog v-model:open="isImportOpen" />

    <ConfirmDeleteModal
        ref="deleteModal"
        title="Xoá danh sách email"
        description="Toàn bộ địa chỉ trong danh sách này sẽ bị xoá. Hành động không thể hoàn tác."
        action="Xoá danh sách"
        cancel="Huỷ"
    />
</template>
