<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { X } from "lucide-vue-next"
import {
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogPortal,
  DialogTitle,
  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"
import DialogOverlay from "./DialogOverlay.vue"

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(defineProps<DialogContentProps & { class?: HTMLAttributes["class"], showCloseButton?: boolean, describedBy?: string, size?: "sm" | "md" | "lg" | "2xl", closeOnOverlay?: boolean }>(), {
  showCloseButton: true,
  size: "md",
  closeOnOverlay: true,
})
const emits = defineEmits<DialogContentEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)

const sizeClasses: Record<string, string> = {
  sm: "sm:max-w-[420px]",
  md: "sm:max-w-[520px]",
  lg: "sm:max-w-[680px]",
  "2xl": "sm:max-w-[800px]",
}

const handleInteractOutside = (event: Event) => {
  if (!props.closeOnOverlay) {
    event.preventDefault()
  }
}
</script>

<template>
  <DialogPortal>
    <DialogOverlay aria-hidden="true" />
    <DialogContent
      :aria-describedby="describedBy"
      data-slot="dialog-content"
      v-bind="{ ...$attrs, ...forwarded }"
      :class="
        cn(
          'bg-card data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-[96%] data-[state=open]:zoom-in-[96%] fixed top-[50%] start-[50%] z-50 w-full max-w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 rounded-xl border border-border/70 shadow-soft duration-150 flex flex-col overflow-hidden',
          sizeClasses[size],
          props.class,
        )"
      @interact-outside="handleInteractOutside"
    >
      <slot />

      <DialogClose
        v-if="showCloseButton"
        type="button"
        data-slot="dialog-close"
        aria-label="إغلاق"
        class="absolute top-4 inset-inline-end-4 flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none [&_svg]:size-4"
      >
        <X />
        <span class="sr-only">إغلاق</span>
      </DialogClose>
    </DialogContent>
  </DialogPortal>
</template>
