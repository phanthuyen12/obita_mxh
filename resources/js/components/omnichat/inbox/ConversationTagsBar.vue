<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { IconPlus, IconTag } from '@tabler/icons-vue';
import { ref, watch } from 'vue';

import ConversationTagController from '@/actions/App/Http/Controllers/App/Omnichat/ConversationTagController';
import LabelBadge from '@/components/labels/LabelBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

type Tag = { id: string; name: string; color: string };

const props = defineProps<{
    conversation: { id: string; labels: Tag[] };
    availableTags: Tag[];
}>();

const selectedTagIds = ref(props.conversation.labels.map((tag) => tag.id));
const tagRequest = useHttp<{ tag_ids: string[] }, { tags: Tag[] }>({
    tag_ids: [],
});

watch(
    () => props.conversation.labels,
    (labels) => {
        selectedTagIds.value = labels.map((tag) => tag.id);
    },
);

const toggleTag = async (tagId: string): Promise<void> => {
    if (tagRequest.processing) return;

    selectedTagIds.value = selectedTagIds.value.includes(tagId)
        ? selectedTagIds.value.filter((id) => id !== tagId)
        : [...selectedTagIds.value, tagId];
    tagRequest.tag_ids = selectedTagIds.value;

    await tagRequest.put(ConversationTagController.url(props.conversation.id));
    router.reload({
        only: ['conversations', 'selectedConversation', 'filterOptions'],
    });
};
</script>

<template>
    <div
        class="flex min-h-11 shrink-0 items-center gap-2 border-b border-border bg-card px-4 py-2"
    >
        <IconTag class="size-4 shrink-0 text-muted-foreground" />
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
            <LabelBadge
                v-for="tag in conversation.labels"
                :key="tag.id"
                :label="tag"
                class="text-[10px]"
            />
            <span
                v-if="conversation.labels.length === 0"
                class="text-xs text-muted-foreground"
            >
                Chưa có thẻ
            </span>
        </div>

        <Popover>
            <PopoverTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-7 shrink-0 gap-1 px-2 text-xs"
                >
                    <IconPlus class="size-3.5" />
                    <span class="hidden sm:inline">Gắn thẻ</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" class="w-64 p-2">
                <p class="px-2 py-1 text-xs font-semibold">Thẻ hội thoại</p>
                <div class="mt-1 max-h-56 space-y-1 overflow-y-auto">
                    <button
                        v-for="tag in availableTags"
                        :key="tag.id"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-xs hover:bg-muted"
                        @click="toggleTag(tag.id)"
                    >
                        <span
                            class="size-3 shrink-0 rounded-full"
                            :style="{ backgroundColor: tag.color }"
                        />
                        <span class="min-w-0 flex-1 truncate">{{
                            tag.name
                        }}</span>
                        <span
                            v-if="selectedTagIds.includes(tag.id)"
                            class="text-primary"
                        >
                            ✓
                        </span>
                    </button>
                    <p
                        v-if="availableTags.length === 0"
                        class="px-2 py-4 text-center text-xs text-muted-foreground"
                    >
                        Chưa có thẻ. Hãy tạo thẻ trong panel khách hàng.
                    </p>
                </div>
            </PopoverContent>
        </Popover>
    </div>
</template>
