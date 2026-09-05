<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

import FolderTree from '@/components/folders/FolderTree.vue';
import { index as foldersIndex } from '@/routes/app/folders';
import { update as movePost } from '@/routes/app/posts/folder';
import type { FolderItem } from '@/types/folder';

const folders = ref<FolderItem[]>([]);
const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';
const load = async () => {
    const response = await fetch(foldersIndex.url(), {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    if (response.ok) folders.value = (await response.json()).data;
};
const drop = async (folder: FolderItem, event: DragEvent) => {
    const postId = event.dataTransfer?.getData('application/x-post-id');
    if (!postId || !folder.can?.view) return;
    const response = await fetch(movePost.url(postId), {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ folder_id: folder.id }),
    });
    response.ok
        ? toast.success(`Đã chuyển bài viết vào ${folder.name}.`)
        : toast.error('Không thể chuyển bài viết.');
};
onMounted(load);
</script>

<template>
    <details v-if="folders.length" class="rounded-xl border bg-card p-3">
        <summary class="cursor-pointer text-sm font-semibold">
            Thả bài viết vào Folder
        </summary>
        <div class="mt-3 max-h-52 overflow-auto">
            <FolderTree :folders="folders" @drop="drop" />
        </div>
    </details>
</template>
