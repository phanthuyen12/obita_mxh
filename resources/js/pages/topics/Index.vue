<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import {
    IconPencil,
    IconPlus,
    IconSearch,
    IconTag,
    IconTrash,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import CreateDialog from '@/components/topics/CreateDialog.vue';
import EditDialog from '@/components/topics/EditDialog.vue';
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
import date from '@/date';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    destroy as tagsDestroy,
    index as tagsIndex,
} from '@/routes/app/post-tags';

interface PostTag {
    id: string;
    name: string;
    color: string;
    created_at: string;
}

interface ScrollTags {
    data: PostTag[];
    meta: { hasNextPage: boolean };
}

interface Props {
    tags: ScrollTags;
    filters: { search: string };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search);

const buildFilterUrl = () => {
    router.get(
        tagsIndex.url(),
        {
            search: searchQuery.value || undefined,
        },
        { preserveState: true, preserveScroll: true, reset: ['tags'] },
    );
};

const search = debounce(buildFilterUrl, 300);

watch(searchQuery, () => search());

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingTopic = ref<PostTag | null>(null);

const openEditDialog = (topic: PostTag) => {
    editingTopic.value = topic;
    isEditDialogOpen.value = true;
};

const handleDelete = (topic: PostTag) => {
    deleteModal.value?.open({
        url: tagsDestroy.url(topic.id),
        confirmText: topic.name,
    });
};

const formatDate = (value: string): string => date.formatDate(value);

const hasActiveFilters = computed(() => Boolean(searchQuery.value?.trim()));
</script>

<template>
    <Head title="Thẻ bài viết" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6 px-6 py-8">
            <PageHeader
                title="Thẻ bài viết"
                description="Danh mục thẻ dùng chung để phân loại, tìm kiếm bài viết và hình ảnh trong workspace."
            />

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-1 items-center gap-3">
                    <div class="relative w-full sm:max-w-md">
                        <IconSearch
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Tìm kiếm thẻ bài viết..."
                            class="w-full pl-9"
                        />
                    </div>
                </div>

                <Button @click="isCreateDialogOpen = true">
                    <IconPlus class="mr-2 size-4" />
                    Tạo thẻ
                </Button>
            </div>

            <EmptyState
                v-if="tags.data.length === 0"
                :icon="IconTag"
                :title="
                    hasActiveFilters
                        ? 'Không tìm thấy thẻ phù hợp'
                        : 'Chưa có thẻ bài viết nào'
                "
                :description="
                    hasActiveFilters
                        ? 'Hãy thử một từ khóa khác.'
                        : 'Tạo thẻ đầu tiên để phân loại bài viết và hình ảnh.'
                "
            />

            <div v-else>
                <InfiniteScroll
                    data="tags"
                    items-element="#tags-body"
                    preserve-url
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-12" />
                                <TableHead>Tên thẻ</TableHead>
                                <TableHead>Phạm vi sử dụng</TableHead>
                                <TableHead>Ngày tạo</TableHead>
                                <TableHead class="text-right" />
                            </TableRow>
                        </TableHeader>
                        <TableBody id="tags-body">
                            <TableRow
                                v-for="topic in tags.data"
                                :key="topic.id"
                                class="cursor-pointer"
                                @click="openEditDialog(topic)"
                            >
                                <TableCell>
                                    <div
                                        class="size-6 rounded-md border-2 border-foreground shadow-2xs"
                                        :style="{
                                            backgroundColor:
                                                topic.color || '#6366f1',
                                        }"
                                    />
                                </TableCell>
                                <TableCell class="font-medium">
                                    {{ topic.name }}
                                </TableCell>
                                <TableCell
                                    ><Badge variant="secondary"
                                        >Thẻ bài viết</Badge
                                    ></TableCell
                                >
                                <TableCell>{{
                                    formatDate(topic.created_at)
                                }}</TableCell>
                                <TableCell class="text-right" @click.stop>
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            class="size-8"
                                            aria-label="Chỉnh sửa"
                                            @click="openEditDialog(topic)"
                                        >
                                            <IconPencil class="size-4" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            class="size-8 bg-rose-100 hover:bg-rose-200"
                                            aria-label="Xóa"
                                            @click="handleDelete(topic)"
                                        >
                                            <IconTrash
                                                class="size-4 text-rose-700"
                                            />
                                        </Button>
                                    </div>
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

    <CreateDialog v-model:open="isCreateDialogOpen" />
    <EditDialog v-model:open="isEditDialogOpen" :topic="editingTopic" />

    <ConfirmDeleteModal
        ref="deleteModal"
        title="Xóa thẻ bài viết"
        description="Thẻ sẽ được gỡ khỏi tất cả bài viết và hình ảnh đang sử dụng. Hành động này không thể hoàn tác."
        action="Xóa vĩnh viễn"
        cancel="Hủy"
    />
</template>
