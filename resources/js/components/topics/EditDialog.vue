<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import HexColorInput from '@/components/HexColorInput.vue';
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
import { Label } from '@/components/ui/label';
import { update as updateTag } from '@/routes/app/post-tags';

interface Topic {
    id: string;
    name: string;
    color: string;
}

const props = defineProps<{
    topic: Topic | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
    color: '#6366f1',
});

watch(
    () => props.topic,
    (topic) => {
        if (topic) {
            form.name = topic.name;
            form.color = topic.color || '#6366f1';
            form.clearErrors();
        }
    },
    { immediate: true },
);

const submit = () => {
    if (!props.topic) return;

    form.put(updateTag.url(props.topic.id), {
        onSuccess: () => {
            open.value = false;
        },
    });
};

const handleOpenChange = (value: boolean) => {
    if (!value) {
        form.clearErrors();
    }
    open.value = value;
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Chỉnh sửa thẻ bài viết</DialogTitle>
                <DialogDescription>
                    Đổi tên hoặc màu thẻ sẽ áp dụng cho mọi bài viết và hình ảnh
                    đang sử dụng.
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-6">
                <div class="space-y-2">
                    <Label for="edit-name">Tên thẻ</Label>
                    <Input
                        id="edit-name"
                        v-model="form.name"
                        placeholder="Tên thẻ bài viết"
                        :class="{ 'border-destructive': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="edit-color">Màu hiển thị</Label>
                    <HexColorInput v-model="form.color" name="color" />
                    <p
                        v-if="form.errors.color"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.color }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                    >
                        Hủy
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
