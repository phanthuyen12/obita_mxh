<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconInbox, IconPhoto } from '@tabler/icons-vue';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

import GalleryBrowser from '@/components/assets/GalleryBrowser.vue';
import FolderManager from '@/components/folders/FolderManager.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { update as moveAsset } from '@/routes/app/assets/folder';
import {
    index as foldersIndex,
    update as updateFolder,
} from '@/routes/app/folders';
import type { FolderItem } from '@/types/folder';

const folders = ref<FolderItem[]>([]);
const selectedFolderId = ref<string | null>(null);
const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';
const request = async (url: string, method: string, body: object) =>
    fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
const loadFolders = async () => {
    const response = await fetch(foldersIndex.url(), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });
    if (response.ok) folders.value = (await response.json()).data;
};
const handleDrop = async (target: FolderItem, event: DragEvent) => {
    const mediaId = event.dataTransfer?.getData('application/x-media-id');
    const folderId = event.dataTransfer?.getData('application/x-folder-id');
    let response: Response | null = null;
    if (mediaId)
        response = await request(moveAsset.url(mediaId), 'PUT', {
            folder_id: target.id,
        });
    if (folderId && folderId !== target.id)
        response = await request(updateFolder.url(folderId), 'PUT', {
            parent_id: target.id,
        });
    if (response?.ok) {
        toast.success('Đã di chuyển nội dung.');
        await loadFolders();
    } else if (response) toast.error('Không thể di chuyển vào thư mục này.');
};
onMounted(loadFolders);
</script>

<template>
    <Head :title="$t('assets.title')" />

    <AppLayout>
        <div
            class="flex h-full flex-1 flex-col gap-6 px-4 py-6 md:px-6 md:py-8"
        >
            <PageHeader :title="$t('assets.title')" />
            <div
                class="grid min-h-0 flex-1 gap-5 lg:grid-cols-[300px_minmax(0,1fr)]"
            >
                <aside class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <Button
                            :variant="
                                selectedFolderId === null
                                    ? 'default'
                                    : 'outline'
                            "
                            size="sm"
                            @click="selectedFolderId = null"
                            ><IconPhoto class="size-4" /> Tất cả</Button
                        >
                        <Button
                            :variant="
                                selectedFolderId === 'unfiled'
                                    ? 'default'
                                    : 'outline'
                            "
                            size="sm"
                            @click="selectedFolderId = 'unfiled'"
                            ><IconInbox class="size-4" /> Chưa xếp</Button
                        >
                    </div>
                    <FolderManager
                        :folders="folders"
                        :selected-id="selectedFolderId"
                        @select="selectedFolderId = $event.id"
                        @changed="loadFolders"
                        @drop="handleDrop"
                    />
                </aside>
                <main class="min-w-0">
                    <GalleryBrowser
                        mode="standalone"
                        :folder-id="selectedFolderId"
                    />
                </main>
            </div>
        </div>
    </AppLayout>
</template>
