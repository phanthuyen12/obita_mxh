<script setup lang="ts">
import {
    IconBolt,
    IconCircleX,
    IconClock,
    IconGitBranch,
    IconGripVertical,
    IconRss,
    IconSend,
    IconSparkles,
    IconWebhook,
    IconWorld,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import { NodeType } from '@/types/automation/node-type';

const props = defineProps<{
    hasTrigger?: boolean;
}>();

const emit = defineEmits<{
    add: [string];
}>();

const categories = computed(() => [
    {
        title: trans('automations.categories.triggers'),
        nodes: [
            {
                type: NodeType.Trigger,
                label: trans('automations.nodes.trigger'),
                icon: IconBolt,
                accent: 'violet',
                disabled: props.hasTrigger,
            },
        ],
    },
    {
        title: trans('automations.categories.sources'),
        nodes: [
            {
                type: NodeType.FetchRss,
                label: trans('automations.nodes.fetch_rss'),
                icon: IconRss,
                accent: 'amber',
            },
            {
                type: NodeType.HttpRequest,
                label: trans('automations.nodes.http_request'),
                icon: IconWorld,
                accent: 'slate',
            },
        ],
    },
    {
        title: trans('automations.categories.content'),
        nodes: [
            {
                type: NodeType.Generate,
                label: trans('automations.nodes.generate'),
                icon: IconSparkles,
                accent: 'blue',
            },
        ],
    },
    {
        title: trans('automations.categories.flow'),
        nodes: [
            {
                type: NodeType.Condition,
                label: trans('automations.nodes.condition'),
                icon: IconGitBranch,
                accent: 'rose',
            },
            {
                type: NodeType.Delay,
                label: trans('automations.nodes.delay'),
                icon: IconClock,
                accent: 'cyan',
            },
            {
                type: NodeType.End,
                label: trans('automations.nodes.end'),
                icon: IconCircleX,
                accent: 'zinc',
            },
        ],
    },
    {
        title: trans('automations.categories.output'),
        nodes: [
            {
                type: NodeType.Publish,
                label: trans('automations.nodes.publish'),
                icon: IconSend,
                accent: 'emerald',
            },
            {
                type: NodeType.Webhook,
                label: trans('automations.nodes.webhook'),
                icon: IconWebhook,
                accent: 'slate',
            },
        ],
    },
]);

const accentClasses: Record<string, { tint: string; text: string }> = {
    violet: { tint: 'bg-violet-200', text: 'text-violet-900' },
    blue: { tint: 'bg-blue-200', text: 'text-blue-900' },
    amber: { tint: 'bg-amber-200', text: 'text-amber-900' },
    rose: { tint: 'bg-rose-200', text: 'text-rose-900' },
    emerald: { tint: 'bg-emerald-200', text: 'text-emerald-900' },
    slate: { tint: 'bg-slate-200', text: 'text-slate-900' },
    zinc: { tint: 'bg-zinc-200', text: 'text-zinc-900' },
    cyan: { tint: 'bg-cyan-200', text: 'text-cyan-900' },
};

const onDragStart = (event: DragEvent, nodeType: string) => {
    if (!event.dataTransfer) return;
    event.dataTransfer.setData('application/automation-node-type', nodeType);
    event.dataTransfer.effectAllowed = 'move';
};

const addNode = (nodeType: string, disabled?: boolean) => {
    if (disabled) return;
    emit('add', nodeType);
};
</script>

<template>
    <div class="flex flex-col gap-5">
        <div
            v-for="category in categories"
            :key="category.title"
            class="flex flex-col gap-2"
        >
            <p
                class="px-0.5 text-[11px] font-black tracking-widest text-foreground/45 uppercase"
            >
                {{ category.title }}
            </p>
            <button
                v-for="option in category.nodes"
                :key="option.type"
                :draggable="!option.disabled"
                :disabled="option.disabled"
                class="group flex items-center gap-2.5 rounded-xl border-2 border-foreground bg-card p-2.5 text-left text-sm font-bold text-foreground shadow-[2px_2px_0_var(--foreground)] transition-all enabled:cursor-grab enabled:hover:-translate-x-px enabled:hover:-translate-y-px enabled:hover:shadow-[3px_3px_0_var(--foreground)] enabled:active:translate-x-0 enabled:active:translate-y-0 enabled:active:rotate-[-1deg] enabled:active:cursor-grabbing enabled:active:shadow-[1px_1px_0_var(--foreground)] disabled:cursor-not-allowed disabled:opacity-45"
                @click="addNode(option.type, option.disabled)"
                @dragstart="onDragStart($event, option.type)"
            >
                <div
                    :class="[
                        'flex size-7 -rotate-3 items-center justify-center rounded-md border-2 border-foreground',
                        accentClasses[option.accent].tint,
                    ]"
                >
                    <component
                        :is="option.icon"
                        :size="14"
                        :class="accentClasses[option.accent].text"
                    />
                </div>
                <span class="flex-1">{{ option.label }}</span>
                <IconGripVertical
                    :size="16"
                    class="shrink-0 text-foreground/30 transition-colors group-hover:text-foreground/55"
                />
            </button>
        </div>
    </div>
</template>
