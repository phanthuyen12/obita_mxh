<script setup lang="ts">
import { IconCalendar, IconTrash } from '@tabler/icons-vue';
import { computed } from 'vue';

import InputError from '@/components/InputError.vue';
import PickTimePopover from '@/components/posts/PickTimePopover.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePageErrors } from '@/composables/usePageErrors';
import { PostStatus } from '@/types/post';

withDefaults(
    defineProps<{
        isReadOnly: boolean;
        isScheduled: boolean;
        canEdit?: boolean;
        isSaving: boolean;
        isSubmitting: boolean;
        isPostActionDisabled: boolean;
        postActionTooltip: string;
        pickTimeLabel: string;
        hideSchedule?: boolean;
        hidePublishingActions?: boolean;
    }>(),
    {
        canEdit: true,
        hideSchedule: false,
        hidePublishingActions: false,
    },
);

const hasPickedTime = defineModel<boolean>('hasPickedTime', { required: true });
const scheduledDateTime = defineModel<string>('scheduledDateTime', {
    required: true,
});

const emit = defineEmits<{
    (e: 'delete'): void;
    (e: 'unschedule'): void;
    (e: 'submit', status: string): void;
}>();

const errors = usePageErrors();
const scheduledAtError = computed(() => errors.value.scheduled_at);
</script>

<template>
    <Button
        v-if="isScheduled && canEdit"
        type="button"
        variant="outline"
        class="bg-background hover:bg-violet-50"
        :disabled="isSubmitting"
        @click="emit('unschedule')"
    >
        {{ $t('posts.edit.unschedule_cta') }}
    </Button>

    <div
        v-else-if="!isReadOnly && canEdit"
        class="flex w-full flex-col gap-1 lg:w-auto lg:items-end"
    >
        <div class="flex w-full items-center gap-2 lg:w-auto">
            <TooltipProvider>
                <Tooltip v-if="!hidePublishingActions">
                    <TooltipTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="bg-rose-100 hover:bg-rose-200"
                            :disabled="isSaving || isSubmitting"
                            @click="emit('delete')"
                        >
                            <IconTrash class="size-4 text-rose-700" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{
                        $t('posts.edit.delete')
                    }}</TooltipContent>
                </Tooltip>

                <Tooltip v-if="!hidePublishingActions">
                    <TooltipTrigger as-child>
                        <span tabindex="0">
                            <PickTimePopover
                                v-if="!hideSchedule"
                                v-model="scheduledDateTime"
                                :disabled="isPostActionDisabled"
                                :show-remove="hasPickedTime"
                                @confirm="hasPickedTime = true"
                                @remove="emit('unschedule')"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-12 min-w-[188px] justify-start gap-2 bg-background px-3 hover:bg-violet-50"
                                    :disabled="isPostActionDisabled"
                                >
                                    <span
                                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700"
                                    >
                                        <IconCalendar class="size-4" />
                                    </span>
                                    <span
                                        class="flex min-w-0 flex-col items-start leading-tight"
                                    >
                                        <span
                                            class="text-[10px] font-black tracking-wider text-foreground/55 uppercase"
                                        >
                                            {{ $t('posts.edit.time') }}
                                        </span>
                                        <span
                                            class="max-w-[132px] truncate text-xs font-bold text-foreground"
                                        >
                                            {{
                                                hasPickedTime
                                                    ? pickTimeLabel
                                                    : $t('posts.edit.post_now')
                                            }}
                                        </span>
                                    </span>
                                </Button>
                            </PickTimePopover>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent
                        v-if="postActionTooltip"
                        class="max-w-xs whitespace-pre-line"
                    >
                        {{ postActionTooltip }}
                    </TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <span tabindex="0" class="flex-1 lg:flex-none">
                            <Button
                                type="button"
                                class="w-full lg:w-auto"
                                :disabled="isPostActionDisabled"
                                @click="
                                    emit(
                                        'submit',
                                        hideSchedule || !hasPickedTime
                                            ? PostStatus.Publishing
                                            : PostStatus.Scheduled,
                                    )
                                "
                            >
                                {{
                                    !hideSchedule && hasPickedTime
                                        ? $t('posts.edit.schedule')
                                        : $t('posts.edit.post_now')
                                }}
                            </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent
                        v-if="postActionTooltip"
                        class="max-w-xs whitespace-pre-line"
                    >
                        {{ postActionTooltip }}
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>
        <InputError :message="scheduledAtError" />
    </div>
</template>
