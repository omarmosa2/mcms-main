<script setup lang="ts">
import { computed } from 'vue';
import { X } from 'lucide-vue-next';

const props = withDefaults(defineProps<{
  label: string;
  value?: string | null;
  removable?: boolean;
}>(), {
  value: null,
});

const emit = defineEmits<{
  (e: 'remove'): void;
}>();

const displayValue = computed(() => props.value ?? props.label);
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/8 px-2.5 py-1 text-xs font-medium text-primary shadow-sm transition-all hover:border-primary/30 hover:bg-primary/12 dark:bg-primary/20 dark:hover:bg-primary/30"
  >
    <span v-if="props.value && props.value !== props.label" class="text-muted-foreground">{{ props.label }}:</span>
    <span class="font-semibold">{{ displayValue }}</span>
    <button
      v-if="removable"
      type="button"
      class="me-0.5 flex size-4 shrink-0 items-center justify-center rounded-full text-primary/60 transition-colors hover:bg-destructive/10 hover:text-destructive"
      :aria-label="`Remove ${label} filter`"
      @click="emit('remove')"
    >
      <X class="size-3" />
    </button>
  </span>
</template>