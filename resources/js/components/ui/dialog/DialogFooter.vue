<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <div
    data-slot="dialog-footer"
    :class="cn('shrink-0 flex flex-row-reverse items-center gap-2 border-t border-border/60 px-6 py-4', props.class)"
  >
    <slot />
  </div>
</template>
