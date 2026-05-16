<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { PanelLeftClose, PanelLeftOpen, PanelRightClose, PanelRightOpen } from "lucide-vue-next"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"
import { useDirection } from "@/composables/useDirection"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { isMobile, state, toggleSidebar, isMounted } = useSidebar()
const { isRtl } = useDirection()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :class="cn('h-7 w-7', props.class)"
    @click="toggleSidebar"
  >
    <template v-if="isMounted">
      <PanelRightOpen v-if="isRtl && (isMobile || state === 'collapsed')" />
      <PanelRightClose v-else-if="isRtl" />
      <PanelLeftOpen v-else-if="isMobile || state === 'collapsed'" />
      <PanelLeftClose v-else />
    </template>
    <PanelLeftClose v-else />
    <span class="sr-only">Toggle sidebar</span>
  </Button>
</template>
