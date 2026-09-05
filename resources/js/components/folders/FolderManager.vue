<script setup lang="ts">
import { IconFolderPlus, IconLoader2 } from '@tabler/icons-vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import FolderTree from '@/components/folders/FolderTree.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { destroy, store, update } from '@/routes/app/folders';
import type { FolderItem } from '@/types/folder';

const props = withDefaults(
    defineProps<{
        folders: FolderItem[];
        selectedId?: string | null;
        canCreateMaster?: boolean;
    }>(),
    { selectedId: null, canCreateMaster: false },
);
const emit = defineEmits<{
    select: [folder: FolderItem];
    changed: [];
    drop: [folder: FolderItem, event: DragEvent];
}>();

const dialogOpen = ref(false);
const saving = ref(false);
const mode = ref<'create' | 'rename' | 'delete'>('create');
const target = ref<FolderItem | null>(null);
const name = ref('');
const title = computed(() =>
    mode.value === 'delete'
        ? 'Xóa thư mục'
        : mode.value === 'rename'
          ? 'Đổi tên thư mục'
          : 'Tạo thư mục',
);
const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const openCreate = (parent: FolderItem | null = null) => {
    mode.value = 'create';
    target.value = parent;
    name.value = '';
    dialogOpen.value = true;
};
const openRename = (folder: FolderItem) => {
    mode.value = 'rename';
    target.value = folder;
    name.value = folder.name;
    dialogOpen.value = true;
};
const openDelete = (folder: FolderItem) => {
    mode.value = 'delete';
    target.value = folder;
    dialogOpen.value = true;
};
const request = async (url: string, method: string, body?: object) => {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new Error(
            data.message ??
                Object.values(data.errors ?? {})
                    .flat()
                    .join(' ') ??
                'Không thể lưu thư mục.',
        );
    }
};
const submit = async () => {
    saving.value = true;
    try {
        if (mode.value === 'delete' && target.value) {
            await request(destroy.url(target.value.id), 'DELETE');
        } else if (mode.value === 'rename' && target.value) {
            await request(update.url(target.value.id), 'PUT', {
                name: name.value,
            });
        } else {
            await request(store.url(), 'POST', {
                name: name.value,
                type: target.value ? 'personal' : 'master',
                parent_id: target.value?.id ?? null,
            });
        }
        dialogOpen.value = false;
        emit('changed');
        toast.success('Đã cập nhật thư mục.');
    } catch (error) {
        toast.error(
            error instanceof Error
                ? error.message
                : 'Không thể cập nhật thư mục.',
        );
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <div class="rounded-xl border bg-card p-3">
        <div class="mb-3 flex items-center justify-between gap-2">
            <div>
                <p class="text-sm font-semibold">Thư mục</p>
                <p class="text-xs text-muted-foreground">
                    Kéo nội dung vào thư mục để di chuyển
                </p>
            </div>
            <Button
                v-if="canCreateMaster"
                size="sm"
                variant="outline"
                @click="openCreate(null)"
                ><IconFolderPlus class="size-4" /> Master</Button
            >
        </div>
        <FolderTree
            :folders="folders"
            :selected-id="selectedId"
            editable
            @select="emit('select', $event)"
            @create="openCreate"
            @rename="openRename"
            @remove="openDelete"
            @drop="(folder, event) => emit('drop', folder, event)"
        />
        <p
            v-if="folders.length === 0"
            class="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground"
        >
            Chưa có thư mục được cấp quyền.
        </p>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="mode === 'delete'"
                    >Chỉ xóa được thư mục trống. Dữ liệu bên trong không bị xóa
                    tự động.</DialogDescription
                >
                <DialogDescription v-else
                    >Nhập tên rõ ràng để mọi người dễ tìm nội
                    dung.</DialogDescription
                >
            </DialogHeader>
            <Input
                v-if="mode !== 'delete'"
                v-model="name"
                autofocus
                maxlength="120"
                placeholder="Ví dụ: Chiến dịch tháng 8"
                @keyup.enter="submit"
            />
            <DialogFooter>
                <Button variant="outline" @click="dialogOpen = false"
                    >Hủy</Button
                >
                <Button
                    :variant="mode === 'delete' ? 'destructive' : 'default'"
                    :disabled="saving || (mode !== 'delete' && !name.trim())"
                    @click="submit"
                >
                    <IconLoader2 v-if="saving" class="size-4 animate-spin" />
                    {{ mode === 'delete' ? 'Xóa' : 'Lưu' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
