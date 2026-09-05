<script setup lang="ts">
import { SidebarTrigger } from '@/components/ui/sidebar';
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-14 shrink-0 items-center justify-between gap-4 border-b border-border bg-card/95 px-6 backdrop-blur-xs"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <slot name="left" />
        </div>
        <div class="flex items-center gap-2">
            <slot name="right" />
        </div>
    </header>
</template>
