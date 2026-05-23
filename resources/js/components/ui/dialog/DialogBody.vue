<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="dialog-body"
    :class="cn('max-h-[60vh] overflow-y-auto px-6 py-5', props.class)"
  >
    <slot />
  </div>
</template>
