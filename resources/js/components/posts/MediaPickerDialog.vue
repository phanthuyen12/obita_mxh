<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed, onMounted, ref } from 'vue';

import GalleryBrowser from '@/components/assets/GalleryBrowser.vue';
import FolderTree from '@/components/folders/FolderTree.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { type MediaType } from '@/lib/mediaType';
import { index as foldersIndex } from '@/routes/app/folders';
import type { FolderItem } from '@/types/folder';

interface PickedMedia {
    id: string;
    path: string;
    url: string;
    type: MediaType;
    mime_type: string;
    original_filename?: string;
    size?: number;
    meta?: { width?: number; height?: number; duration?: number };
}

const emit = defineEmits<{
    (e: 'select', media: PickedMedia[]): void;
}>();

const isOpen = ref(false);
const selected = ref<PickedMedia[]>([]);
const selectedCount = computed(() => selected.value.length);
const folders = ref<FolderItem[]>([]);
const selectedFolderId = ref<string | null>(null);
const selectedFolder = computed(
    () =>
        folders.value.find((folder) => folder.id === selectedFolderId.value) ??
        null,
);
const canUploadToSelectedFolder = computed(
    () => selectedFolder.value?.can?.upload_media ?? true,
);

const loadFolders = async () => {
    const response = await fetch(foldersIndex.url(), {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (response.ok) {
        folders.value = (await response.json()).data;
    }
};

const reset = () => {
    selected.value = [];
};

const open = () => {
    reset();
    void loadFolders();
    isOpen.value = true;
};

const close = () => {
    isOpen.value = false;
};

const confirmSelection = () => {
    if (selected.value.length === 0) return;
    emit('select', selected.value);
    isOpen.value = false;
};

defineExpose({ open, close });
onMounted(loadFolders);
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent
            class="flex h-[85vh] max-w-[calc(100%-2rem)] flex-col gap-0 p-0 sm:max-w-5xl"
        >
            <DialogHeader class="border-b px-6 py-4">
                <DialogTitle>{{
                    trans('posts.edit.media_picker.title')
                }}</DialogTitle>
            </DialogHeader>

            <div class="grid min-h-0 flex-1 md:grid-cols-[260px_minmax(0,1fr)]">
                <aside
                    class="border-b bg-muted/20 p-3 md:overflow-y-auto md:border-r md:border-b-0"
                >
                    <p
                        class="mb-3 px-1 text-xs font-bold tracking-wide text-muted-foreground uppercase"
                    >
                        Thư mục ảnh
                    </p>
                    <div class="mb-2 grid grid-cols-2 gap-2">
                        <Button
                            size="sm"
                            :variant="
                                selectedFolderId === null
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="selectedFolderId = null"
                            >Tất cả</Button
                        >
                        <Button
                            size="sm"
                            :variant="
                                selectedFolderId === 'unfiled'
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="selectedFolderId = 'unfiled'"
                            >Chưa xếp</Button
                        >
                    </div>
                    <FolderTree
                        :folders="folders"
                        :selected-id="selectedFolderId"
                        @select="selectedFolderId = $event.id"
                    />
                    <p
                        v-if="folders.length === 0"
                        class="rounded-lg border border-dashed p-3 text-center text-xs text-muted-foreground"
                    >
                        Bạn chưa được cấp quyền vào thư mục nào.
                    </p>
                </aside>

                <div class="overflow-y-auto px-4 py-4 md:px-6">
                    <div
                        v-if="selectedFolder && !canUploadToSelectedFolder"
                        class="mb-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900"
                    >
                        Bạn có thể chọn ảnh trong thư mục này nhưng chưa được
                        cấp quyền upload.
                    </div>
                    <GalleryBrowser
                        v-model:selected="selected"
                        mode="picker"
                        :folder-id="selectedFolderId"
                        :allow-upload="canUploadToSelectedFolder"
                    />
                </div>
            </div>

            <DialogFooter class="border-t px-6 py-3">
                <Button
                    type="button"
                    :disabled="selectedCount === 0"
                    @click="confirmSelection"
                >
                    <template v-if="selectedCount > 0">
                        {{
                            trans('posts.edit.media_picker.add_count', {
                                count: String(selectedCount),
                            })
                        }}
                    </template>
                    <template v-else>
                        {{ trans('posts.edit.media_picker.add') }}
                    </template>
                </Button>
                <Button type="button" variant="ghost" @click="close">
                    {{ trans('posts.edit.media_picker.cancel') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
