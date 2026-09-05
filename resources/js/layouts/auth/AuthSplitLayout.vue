<script setup lang="ts">
import {
    IconCalendar,
    IconClock,
    IconHash,
    IconPhoto,
    IconUsers,
    IconVideo,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const slideKeys = [
    'calendar',
    'scheduling',
    'media',
    'video',
    'team',
    'signatures',
] as const;

const slideIcons = {
    calendar: IconCalendar,
    scheduling: IconClock,
    media: IconPhoto,
    video: IconVideo,
    team: IconUsers,
    signatures: IconHash,
};

const slides = computed(() =>
    slideKeys.map((key) => ({
        icon: slideIcons[key],
        title: trans(`auth.slides.${key}.title`),
        description: trans(`auth.slides.${key}.description`),
    })),
);

const activeIndex = ref(0);
const isPaused = ref(false);
let intervalId: ReturnType<typeof setInterval> | null = null;

const activeSlide = computed(() => slides.value[activeIndex.value]);

const goTo = (index: number) => {
    activeIndex.value = index;
    restartInterval();
};

const startInterval = () => {
    intervalId = setInterval(() => {
        if (!isPaused.value) {
            activeIndex.value = (activeIndex.value + 1) % slides.value.length;
        }
    }, 4000);
};

const restartInterval = () => {
    if (intervalId) {
        clearInterval(intervalId);
    }
    startInterval();
};

onMounted(() => {
    startInterval();
});

onBeforeUnmount(() => {
    if (intervalId) {
        clearInterval(intervalId);
    }
});

const platforms = [
    { name: 'LinkedIn', icon: '/images/accounts/linkedin.png' },
    { name: 'X', icon: '/images/accounts/x.png' },
    { name: 'Instagram', icon: '/images/accounts/instagram.png' },
    { name: 'Facebook', icon: '/images/accounts/facebook.png' },
    { name: 'TikTok', icon: '/images/accounts/tiktok.png' },
    { name: 'YouTube', icon: '/images/accounts/youtube.png' },
    { name: 'Threads', icon: '/images/accounts/threads.png' },
    { name: 'Pinterest', icon: '/images/accounts/pinterest.png' },
    { name: 'Bluesky', icon: '/images/accounts/bluesky.png' },
    { name: 'Mastodon', icon: '/images/accounts/mastodon.png' },
];
</script>

<template>
    <div class="grid min-h-svh grid-cols-1 bg-background lg:grid-cols-2">
        <!-- Form Left Pane -->
        <div class="flex min-w-0 flex-col justify-between p-8 md:p-12 lg:p-16">
            <div class="flex items-center gap-3">
                <img
                    src="/images/aetrading.png"
                    alt="AETrading"
                    class="h-10 w-auto"
                />
                <span
                    class="text-sm font-semibold tracking-tight text-foreground/80"
                    >AETrading</span
                >
            </div>

            <div class="my-8 flex flex-1 items-center justify-center">
                <div class="w-full max-w-md">
                    <div class="flex flex-col gap-6">
                        <div class="flex flex-col gap-2 text-left">
                            <h1
                                v-if="title"
                                class="text-2xl font-bold tracking-tight text-foreground"
                            >
                                {{ title }}
                            </h1>
                            <p
                                v-if="description"
                                class="text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ description }}
                            </p>
                        </div>

                        <slot />
                    </div>
                </div>
            </div>

            <div class="text-xs text-muted-foreground">
                &copy; {{ new Date().getFullYear() }} TryPost. All rights
                reserved.
            </div>
        </div>

        <!-- Showcase Right Pane -->
        <div
            class="relative hidden overflow-hidden border-l border-border/40 bg-slate-950 p-12 lg:sticky lg:top-0 lg:flex lg:h-svh lg:flex-col lg:items-center lg:justify-center xl:p-16"
            @mouseenter="isPaused = true"
            @mouseleave="isPaused = false"
        >
            <!-- Ambient Glow Gradients -->
            <div
                class="pointer-events-none absolute -top-24 -right-24 size-[480px] rounded-full bg-zinc-500/10 blur-[120px]"
            />
            <div
                class="pointer-events-none absolute -bottom-32 -left-32 size-[480px] rounded-full bg-slate-500/10 blur-[120px]"
            />

            <!-- Subtle Dot Grid -->
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.15]"
                style="
                    background-image: radial-gradient(
                        circle,
                        #ffffff 1px,
                        transparent 1px
                    );
                    background-size: 24px 24px;
                "
            />

            <div class="relative flex w-full max-w-md flex-col items-center">
                <!-- Mockup Card Carousel -->
                <div class="relative h-[290px] w-full">
                    <template v-for="(slide, index) in slides" :key="index">
                        <Transition
                            enter-active-class="transition-all duration-400 ease-out"
                            leave-active-class="transition-all duration-300 ease-in"
                            enter-from-class="opacity-0 translate-y-3"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-3"
                        >
                            <div
                                v-if="activeIndex === index"
                                class="absolute inset-0 flex items-center justify-center"
                            >
                                <!-- Interactive Card Mockup -->
                                <div
                                    class="w-full max-w-sm rounded-2xl border border-slate-800 bg-slate-900/90 shadow-2xl backdrop-blur-xl transition-all duration-300 hover:border-slate-700"
                                >
                                    <!-- Window Titlebar -->
                                    <div
                                        class="flex items-center border-b border-slate-800/80 px-4 py-3"
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="size-2.5 rounded-full bg-slate-700"
                                            />
                                            <span
                                                class="size-2.5 rounded-full bg-slate-700"
                                            />
                                            <span
                                                class="size-2.5 rounded-full bg-slate-700"
                                            />
                                        </div>
                                        <div
                                            class="ml-2 truncate text-[11px] font-medium tracking-wide text-slate-400"
                                        >
                                            AETrading
                                        </div>
                                        <span
                                            class="ml-auto inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-medium text-emerald-400"
                                        >
                                            <span
                                                class="relative flex size-1.5"
                                            >
                                                <span
                                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400/80"
                                                />
                                                <span
                                                    class="relative inline-flex size-1.5 rounded-full bg-emerald-400"
                                                />
                                            </span>
                                            Live
                                        </span>
                                    </div>

                                    <!-- Feature Body -->
                                    <div
                                        class="flex items-center justify-center py-10"
                                    >
                                        <div
                                            class="flex size-16 items-center justify-center rounded-2xl border border-zinc-700 bg-zinc-800 text-white shadow-inner"
                                        >
                                            <component
                                                :is="slide.icon"
                                                class="size-8"
                                            />
                                        </div>
                                    </div>

                                    <!-- Platforms Bar -->
                                    <div
                                        class="flex flex-wrap justify-center gap-2 border-t border-slate-800/80 bg-slate-950/40 px-4 py-3"
                                    >
                                        <img
                                            v-for="platform in platforms"
                                            :key="platform.name"
                                            :src="platform.icon"
                                            :alt="platform.name"
                                            class="size-6 rounded-full border border-slate-700/60 bg-slate-800 p-0.5"
                                        />
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </template>
                </div>

                <!-- Text Content -->
                <div class="mt-8 w-full text-center">
                    <div class="relative h-[90px]">
                        <TransitionGroup
                            enter-active-class="transition-all duration-400 ease-out"
                            leave-active-class="transition-all duration-300 ease-in"
                            enter-from-class="opacity-0 translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div
                                :key="activeIndex"
                                class="absolute inset-x-0 top-0"
                            >
                                <h3
                                    class="text-lg font-semibold tracking-tight text-white"
                                >
                                    {{ activeSlide.title }}
                                </h3>
                                <p
                                    class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-slate-400"
                                >
                                    {{ activeSlide.description }}
                                </p>
                            </div>
                        </TransitionGroup>
                    </div>

                    <!-- Slide Dots -->
                    <div class="mt-2 flex items-center justify-center gap-1.5">
                        <button
                            v-for="(_, index) in slides"
                            :key="index"
                            class="group relative flex h-4 cursor-pointer items-center justify-center p-0.5"
                            @click="goTo(index)"
                        >
                            <span
                                class="block h-1 rounded-full transition-all duration-300"
                                :class="
                                    activeIndex === index
                                        ? 'w-6 bg-white'
                                        : 'w-1.5 bg-slate-600 group-hover:bg-slate-400'
                                "
                            />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
