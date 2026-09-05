<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

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
import { store as tagsStore } from '@/routes/app/post-tags';

const open = defineModel<boolean>('open', { default: false });

const DEFAULT_COLOR = '#6366f1';

const form = useForm({
    name: '',
    color: DEFAULT_COLOR,
});

const submit = () => {
    form.post(tagsStore.url(), {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};

const handleOpenChange = (value: boolean) => {
    if (value) {
        form.reset();
        form.color = DEFAULT_COLOR;
        form.clearErrors();
    }
    open.value = value;
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Tạo thẻ bài viết</DialogTitle>
                <DialogDescription>
                    Thẻ này có thể dùng chung cho bài viết và hình ảnh trong
                    workspace.
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-6">
                <div class="space-y-2">
                    <Label for="create-name">Tên thẻ</Label>
                    <Input
                        id="create-name"
                        v-model="form.name"
                        placeholder="Ví dụ: Marketing, Khuyến mãi, Sản phẩm..."
                        :class="{ 'border-destructive': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="create-color">Màu hiển thị</Label>
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
                        {{ form.processing ? 'Đang tạo...' : 'Tạo mới' }}
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
