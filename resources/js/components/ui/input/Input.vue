<script setup lang="ts">
import { useId } from "vue"
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"

const props = defineProps<{
  id?: string
  type?: string
  name?: string
  placeholder?: string
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
  disabled?: boolean
  readonly?: boolean
  required?: boolean
  ariaLabel?: string
  ariaDescribedBy?: string
  invalid?: boolean
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const inputId = props.id ?? `input-${useId()}`

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <input
    :id="inputId"
    v-model="modelValue"
    :type="type"
    :name="name"
    :placeholder="placeholder"
    :disabled="disabled"
    :readonly="readonly"
    :required="required"
    :aria-label="ariaLabel"
    :aria-describedby="ariaDescribedBy"
    :aria-invalid="invalid"
    data-slot="input"
    :class="cn(
      'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground h-9 w-full min-w-0 rounded-md border border-border/80 bg-background/70 px-3 py-1 text-base shadow-[0_1px_0_hsl(0_0%_100%_/_0.5)] transition-[color,box-shadow,border-color,background-color] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/40',
      'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
      'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
      props.class,
    )"
  >
</template>
