<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <tr
    data-slot="table-row"
    :class="cn('border-b border-border/70 transition-colors hover:bg-muted/40 data-[state=selected]:bg-muted/60 last:border-b-0', props.class)"
  >
    <slot />
  </tr>
</template>
