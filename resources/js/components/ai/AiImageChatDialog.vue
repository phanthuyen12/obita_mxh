<script setup lang="ts">
import { IconPhoto, IconSparkles } from '@tabler/icons-vue';
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps<{ open: boolean }>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'select', url: string): void;
}>();

const prompt = ref('');
const size = ref<'1024x1024' | '1024x1792' | '1792x1024'>('1024x1024');
const quality = ref<'low' | 'medium' | 'high'>('medium');
const generating = ref(false);
const generatedUrl = ref<string | null>(null);
const errorMsg = ref<string | null>(null);
const history = ref<string[]>([]);

const csrfToken = (): string =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
        ?.content ?? '';

const pollTaskResult = async (taskId: string): Promise<string | null> => {
    for (let i = 0; i < 60; i++) {
        await new Promise((r) => setTimeout(r, 2000));
        const res = await fetch(`/content-clones/preview-status/${taskId}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        if (data.status === 'completed')
            return data.url ?? data.result?.url ?? null;
        if (data.status === 'failed')
            throw new Error(data.error || 'Tác vụ thất bại');
    }
    return null;
};

const generate = async () => {
    if (!prompt.value.trim() || generating.value) return;
    generating.value = true;
    generatedUrl.value = null;
    errorMsg.value = null;

    try {
        const res = await fetch('/ai-image-chat/generate', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                prompt: prompt.value,
                size: size.value,
                quality: quality.value,
            }),
        });

        const data = await res.json();

        if (data.task_id) {
            const url = await pollTaskResult(data.task_id);
            if (url) {
                generatedUrl.value = url;
                history.value.unshift(url);
                if (history.value.length > 8) history.value.pop();
            }
        }
    } catch (e: any) {
        errorMsg.value = e?.message ?? 'Có lỗi xảy ra. Vui lòng thử lại!';
    } finally {
        generating.value = false;
    }
};

const useImage = () => {
    if (generatedUrl.value) {
        emit('select', generatedUrl.value);
        emit('update:open', false);
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <IconSparkles class="size-5 text-violet-500" />
                    Chat tạo ảnh AI
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Prompt -->
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">Mô tả ảnh muốn tạo</label>
                    <textarea
                        v-model="prompt"
                        rows="3"
                        placeholder="VD: A coffee cup on a wooden table, warm morning sunlight, commercial photography style..."
                        class="w-full resize-none rounded-xl border-2 border-foreground/10 bg-card px-4 py-3 text-sm outline-none transition focus:border-violet-500"
                        @keydown.ctrl.enter="generate"
                    />
                    <p class="mt-1 text-xs text-foreground/50">Ctrl+Enter để tạo ngay</p>
                </div>

                <!-- Options -->
                <div class="flex flex-wrap gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-foreground/60">Kích thước</label>
                        <select v-model="size" class="rounded-lg border-2 border-foreground/10 bg-card px-3 py-1.5 text-sm">
                            <option value="1024x1024">1:1 – Vuông</option>
                            <option value="1024x1792">9:16 – Dọc (Story/TikTok)</option>
                            <option value="1792x1024">16:9 – Ngang (YouTube)</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-foreground/60">Chất lượng</label>
                        <select v-model="quality" class="rounded-lg border-2 border-foreground/10 bg-card px-3 py-1.5 text-sm">
                            <option value="low">Nhanh (Low)</option>
                            <option value="medium">Cân bằng (Medium)</option>
                            <option value="high">Cao nhất (High)</option>
                        </select>
                    </div>
                </div>

                <!-- Generate Button -->
                <Button class="w-full" :disabled="generating || !prompt.trim()" @click="generate">
                    <span
                        v-if="generating"
                        class="mr-2 inline-block size-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                    />
                    <IconSparkles v-else class="size-4" />
                    {{ generating ? 'Đang tạo ảnh AI...' : 'Tạo ảnh AI' }}
                </Button>

                <!-- Error -->
                <p v-if="errorMsg" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-600">
                    {{ errorMsg }}
                </p>

                <!-- Result -->
                <div v-if="generatedUrl" class="space-y-3">
                    <div class="overflow-hidden rounded-xl border-2 border-violet-500/30 shadow-lg">
                        <img :src="generatedUrl" alt="AI Generated" class="w-full object-cover" />
                    </div>
                    <div class="flex gap-2">
                        <Button class="flex-1 bg-violet-600 hover:bg-violet-700" @click="useImage">
                            <IconPhoto class="size-4" />
                            Dùng ảnh này
                        </Button>
                        <Button variant="outline" :disabled="generating" @click="generate">
                            Tạo lại
                        </Button>
                    </div>
                </div>

                <!-- History -->
                <div v-if="history.length > 1">
                    <p class="mb-2 text-xs font-semibold text-foreground/50">Ảnh đã tạo trước</p>
                    <div class="grid grid-cols-4 gap-2">
                        <button
                            v-for="(url, i) in history.slice(1)"
                            :key="i"
                            type="button"
                            class="overflow-hidden rounded-lg border-2 border-foreground/10 transition hover:border-violet-500"
                            @click="generatedUrl = url"
                        >
                            <img :src="url" alt="" class="aspect-square w-full object-cover" />
                        </button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
