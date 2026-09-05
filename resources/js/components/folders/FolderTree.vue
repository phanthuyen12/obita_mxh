<script setup lang="ts">
import {
    IconChevronDown,
    IconChevronRight,
    IconFolder,
    IconFolderOpen,
    IconGripVertical,
    IconPencil,
    IconPlus,
    IconTrash,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import type { FolderItem } from '@/types/folder';

defineOptions({ name: 'FolderTree' });

const props = withDefaults(
    defineProps<{
        folders: FolderItem[];
        parentId?: string | null;
        selectedId?: string | null;
        editable?: boolean;
    }>(),
    { parentId: null, selectedId: null, editable: false },
);

const emit = defineEmits<{
    select: [folder: FolderItem];
    create: [folder: FolderItem];
    rename: [folder: FolderItem];
    remove: [folder: FolderItem];
    drop: [folder: FolderItem, event: DragEvent];
}>();

const openIds = ref(new Set<string>());
const children = computed(() =>
    props.folders.filter((folder) => {
        if (folder.parent_id === props.parentId) return true;

        return (
            props.parentId === null &&
            folder.parent_id !== null &&
            !props.folders.some(
                (candidate) => candidate.id === folder.parent_id,
            )
        );
    }),
);
const hasChildren = (folder: FolderItem) =>
    props.folders.some((candidate) => candidate.parent_id === folder.id);
const toggle = (id: string) => {
    const next = new Set(openIds.value);
    next.has(id) ? next.delete(id) : next.add(id);
    openIds.value = next;
};
const dragFolder = (folder: FolderItem, event: DragEvent) => {
    event.dataTransfer?.setData('application/x-folder-id', folder.id);
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
};
</script>

<template>
    <div class="space-y-1">
        <div v-for="folder in children" :key="folder.id">
            <div
                class="group flex min-h-9 items-center gap-1 rounded-lg px-1.5 text-sm transition-colors hover:bg-muted"
                :class="
                    selectedId === folder.id
                        ? 'bg-primary/10 font-semibold text-primary'
                        : ''
                "
                draggable="true"
                @dragstart="dragFolder(folder, $event)"
                @dragover.prevent
                @drop.prevent="emit('drop', folder, $event)"
            >
                <button
                    type="button"
                    class="grid size-6 shrink-0 place-items-center rounded hover:bg-background"
                    @click="toggle(folder.id)"
                >
                    <component
                        :is="
                            openIds.has(folder.id)
                                ? IconChevronDown
                                : IconChevronRight
                        "
                        v-if="hasChildren(folder)"
                        class="size-3.5"
                    />
                    <IconGripVertical
                        v-else
                        class="size-3.5 opacity-0 group-hover:opacity-40"
                    />
                </button>
                <button
                    type="button"
                    class="flex min-w-0 flex-1 items-center gap-2 py-1 text-left"
                    @click="emit('select', folder)"
                >
                    <component
                        :is="
                            openIds.has(folder.id) ? IconFolderOpen : IconFolder
                        "
                        class="size-4 shrink-0"
                        :class="
                            folder.type === 'master'
                                ? 'text-amber-600'
                                : 'text-sky-600'
                        "
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate">{{ folder.name }}</span>
                        <span
                            v-if="folder.owner_email"
                            class="block truncate text-[10px] font-normal text-muted-foreground"
                            :title="`${folder.owner_name ?? ''} · ${folder.owner_email}`"
                        >
                            {{ folder.owner_email }}
                        </span>
                    </span>
                    <span
                        v-if="folder.medias_count || folder.posts_count"
                        class="ml-auto text-[10px] text-muted-foreground"
                        >{{
                            (folder.medias_count ?? 0) +
                            (folder.posts_count ?? 0)
                        }}</span
                    >
                </button>
                <div
                    v-if="editable"
                    class="hidden items-center group-hover:flex"
                >
                    <Button
                        v-if="folder.can?.create !== false"
                        variant="ghost"
                        size="icon"
                        class="size-7"
                        title="Tạo thư mục con"
                        @click="emit('create', folder)"
                        ><IconPlus class="size-3.5"
                    /></Button>
                    <Button
                        v-if="folder.can?.update !== false"
                        variant="ghost"
                        size="icon"
                        class="size-7"
                        title="Đổi tên"
                        @click="emit('rename', folder)"
                        ><IconPencil class="size-3.5"
                    /></Button>
                    <Button
                        v-if="folder.can?.delete !== false"
                        variant="ghost"
                        size="icon"
                        class="size-7 text-destructive"
                        title="Xóa"
                        @click="emit('remove', folder)"
                        ><IconTrash class="size-3.5"
                    /></Button>
                </div>
            </div>
            <FolderTree
                v-if="openIds.has(folder.id)"
                class="ml-4 border-l pl-1"
                :folders="folders"
                :parent-id="folder.id"
                :selected-id="selectedId"
                :editable="editable"
                @select="emit('select', $event)"
                @create="emit('create', $event)"
                @rename="emit('rename', $event)"
                @remove="emit('remove', $event)"
                @drop="(target, event) => emit('drop', target, event)"
            />
        </div>
    </div>
</template>
