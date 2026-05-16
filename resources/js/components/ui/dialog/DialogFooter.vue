<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <div
    data-slot="dialog-footer"
    :class="cn('shrink-0 flex flex-col-reverse gap-2 border-t border-border/60 p-6 sm:flex-row sm:justify-between', props.class)"
  >
    <slot />
  </div>
</template>
