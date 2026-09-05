<script setup lang="ts">
import { IconAlertCircle, IconSend } from '@tabler/icons-vue';
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

const emit = defineEmits<{
    (e: 'confirm', note: string): void;
}>();

const isOpen = ref(false);
const note = ref('');
const initialNote = ref('');

const PRESETS = [
    'Vui lòng chỉnh sửa lại nội dung văn bản.',
    'Cần thay đổi hoặc bổ sung hình ảnh / video.',
    'Hãy kiểm tra và điều chỉnh lại thời gian đăng bài.',
    'Cần bổ sung hoặc chọn lại Kênh / Nhãn phù hợp.',
];

const open = (defaultNote: string = '') => {
    initialNote.value = defaultNote || 'Vui lòng chỉnh sửa lại nội dung.';
    note.value = initialNote.value;
    isOpen.value = true;
};

const close = () => {
    isOpen.value = false;
};

const selectPreset = (presetText: string) => {
    note.value = presetText;
};

const submit = () => {
    const trimmed = note.value.trim();
    if (!trimmed) return;
    emit('confirm', trimmed);
    close();
};

const onOpenChange = (value: boolean) => {
    isOpen.value = value;
};

defineExpose({ open, close });
</script>

<template>
    <Dialog :open="isOpen" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700"
                    >
                        <IconAlertCircle class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold"
                            >Trả lại bài viết (Từ chối duyệt)</DialogTitle
                        >
                        <DialogDescription
                            class="text-xs text-muted-foreground"
                        >
                            Nhập lý do hoặc yêu cầu chỉnh sửa để thông báo cho
                            người viết bài.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-3 py-2">
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-muted-foreground"
                        >Lựa chọn nhanh lý do thường gặp:</label
                    >
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="preset in PRESETS"
                            :key="preset"
                            type="button"
                            class="rounded-md border bg-muted/40 px-2.5 py-1 text-xs text-foreground/80 transition-colors hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700"
                            @click="selectPreset(preset)"
                        >
                            {{ preset }}
                        </button>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-muted-foreground"
                        >Chi tiết lý do trả bài:</label
                    >
                    <textarea
                        v-model="note"
                        rows="3"
                        class="w-full rounded-lg border bg-background p-3 text-sm focus:ring-2 focus:ring-rose-500/20 focus:outline-hidden"
                        placeholder="Nhập chi tiết yêu cầu chỉnh sửa..."
                    />
                </div>
            </div>

            <DialogFooter class="gap-2 sm:justify-end">
                <Button variant="outline" size="sm" @click="close">
                    Hủy bỏ
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    :disabled="!note.trim()"
                    @click="submit"
                >
                    <IconSend class="mr-1.5 size-4" />
                    Xác nhận trả bài
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
