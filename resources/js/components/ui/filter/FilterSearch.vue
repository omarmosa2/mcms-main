<script setup lang="ts">
import { useId } from 'vue';
import { ref, watch } from 'vue';
import { Search, Loader2 } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { useDebounceFn } from '@vueuse/core';

const props = withDefaults(defineProps<{
  id?: string;
  label?: string;
  modelValue?: string;
  placeholder?: string;
  debounce?: number;
  loading?: boolean;
}>(), {
  modelValue: '',
  placeholder: 'Search...',
  debounce: 300,
  loading: false,
});

const emit = defineEmits<{
  (e: 'update:modelValue', payload: string): void;
}>();

const searchId = props.id ?? `search-${useId()}`;

const localValue = ref(props.modelValue);

watch(() => props.modelValue, (val) => {
  localValue.value = val;
});

const debouncedUpdate = useDebounceFn((value: string) => {
  emit('update:modelValue', value);
}, props.debounce);

watch(localValue, (val) => {
  debouncedUpdate(val);
});

const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    emit('update:modelValue', localValue.value);
  }
};
</script>

<template>
  <div class="relative">
    <label :for="searchId" class="sr-only">{{ label || placeholder }}</label>
    <Search
      class="absolute start-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none"
      aria-hidden="true"
    />
    <Input
      :id="searchId"
      v-model="localValue"
      :placeholder="placeholder"
      :aria-label="label || placeholder"
      :aria-busy="loading"
      class="pattern-field-clay ps-9 pe-8"
      @keydown="handleKeydown"
    />
    <Loader2
      v-if="loading"
      class="absolute end-3 top-1/2 -translate-y-1/2 size-4 animate-spin motion-reduce:animate-none motion-reduce:opacity-50 text-muted-foreground"
      aria-hidden="true"
    />
  </div>
</template>