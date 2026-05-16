<script setup lang="ts">
import { ref, computed } from 'vue';
import { ChevronDown, Check, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

type SelectOption = {
  label: string;
  value: string | number;
};

const props = withDefaults(defineProps<{
  modelValue: (string | number)[];
  options: SelectOption[];
  placeholder?: string;
}>(), {
  placeholder: 'Select...',
});

const emit = defineEmits<{
  (e: 'update:modelValue', payload: (string | number)[]): void;
}>();

const open = ref(false);

const selectedLabels = computed(() => {
  const selected = props.options.filter(o => props.modelValue.includes(o.value));
  return selected.map(o => o.label);
});

const isSelected = (value: string | number) => props.modelValue.includes(value);

const toggle = (value: string | number) => {
  const newValue = isSelected(value)
    ? props.modelValue.filter(v => v !== value)
    : [...props.modelValue, value];
  emit('update:modelValue', newValue);
};

const clear = () => {
  emit('update:modelValue', []);
};

const close = () => {
  open.value = false;
};
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="pattern-field-clay flex h-9 min-w-[140px] items-center gap-2 rounded-md px-3 py-1.5 text-sm transition-colors hover:bg-accent/50"
      @click="open = !open"
    >
      <span v-if="selectedLabels.length === 0" class="text-muted-foreground">{{ placeholder }}</span>
      <span v-else class="truncate max-w-[120px]">{{ selectedLabels.join(', ') }}</span>
      <ChevronDown class="ms-auto size-4 text-muted-foreground" :class="{ 'rotate-180': open }" />
    </button>
    <div
      v-if="open"
      class="absolute z-50 mt-1 w-56 rounded-md border bg-background p-1 shadow-lg animate-in fade-in zoom-in-95"
      @click.outside="close"
    >
      <div class="max-h-64 overflow-y-auto space-y-0.5">
        <button
          v-for="option in options"
          :key="option.value"
          type="button"
          class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-accent"
          :class="[
            isSelected(option.value)
              ? 'bg-primary/10 text-primary'
              : 'text-foreground',
          ]"
          @click="toggle(option.value)"
        >
          <span
            class="flex size-4 items-center justify-center rounded border"
            :class="[
              isSelected(option.value)
                ? 'border-primary bg-primary text-primary-foreground'
                : 'border-input',
            ]"
          >
            <Check v-if="isSelected(option.value)" class="size-3" />
          </span>
          {{ option.label }}
        </button>
      </div>
      <div v-if="modelValue.length > 0" class="mt-2 border-t pt-2">
        <button
          type="button"
          class="w-full px-2 py-1 text-xs text-destructive hover:underline"
          @click="clear"
        >
          Clear selection
        </button>
      </div>
    </div>
  </div>
</template>