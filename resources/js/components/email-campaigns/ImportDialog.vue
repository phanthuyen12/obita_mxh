<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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
import { store as listsStore } from '@/routes/app/email-campaigns/lists';

const open = defineModel<boolean>('open', { default: false });

const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{ name: string; file: File | null }>({
    name: '',
    file: null,
});

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    form.file = file;

    if (file && !form.name) {
        form.name = file.name.replace(/\.[^.]+$/, '');
    }
};

const submit = () => {
    form.post(listsStore.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};

const handleOpenChange = (value: boolean) => {
    if (value) {
        form.reset();
        form.clearErrors();
    }

    open.value = value;
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Nhập danh sách email</DialogTitle>
                <DialogDescription>
                    Tải lên file CSV hoặc Excel. File cần có cột
                    <code>email</code>; cột <code>name</code> là tuỳ chọn, các
                    cột còn lại dùng được làm biến trong nội dung mail.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="list-name">Tên danh sách</Label>
                    <Input
                        id="list-name"
                        v-model="form.name"
                        placeholder="Ví dụ: Khách tháng 10"
                        :class="{ 'border-destructive': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="list-file">File danh sách</Label>
                    <Input
                        id="list-file"
                        ref="fileInput"
                        type="file"
                        accept=".csv,.txt,.xlsx,.xls"
                        :class="{ 'border-destructive': form.errors.file }"
                        @change="onFileChange"
                    />
                    <a
                        href="/email-contacts-template.csv"
                        download="email-contacts-template.csv"
                        class="text-sm text-primary underline underline-offset-4"
                    >
                        Tải file mẫu CSV
                    </a>
                    <p v-if="form.errors.file" class="text-sm text-destructive">
                        {{ form.errors.file }}
                    </p>
                    <p
                        v-if="form.progress"
                        class="text-sm text-muted-foreground"
                    >
                        Đang tải lên… {{ form.progress.percentage }}%
                    </p>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing ? 'Đang tải lên…' : 'Nhập danh sách'
                        }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Huỷ
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
