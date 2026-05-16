<script setup lang="ts">
import { useId } from 'vue';
import { computed } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

type SelectOption = {
  label: string;
  value: string | number | null;
};

const props = withDefaults(defineProps<{
  id?: string;
  label?: string;
  modelValue: string | number | null;
  options: SelectOption[];
  placeholder?: string;
}>(), {
  placeholder: 'Select...',
});

const emit = defineEmits<{
  (e: 'update:modelValue', payload: string | number | null): void;
}>();

const selectId = props.id ?? `filter-select-${useId()}`;

const handleChange = (event: Event) => {
  const target = event.target as HTMLSelectElement;
  const value = target.value;
  const option = props.options.find(o => String(o.value) === value);
  if (option) {
    emit('update:modelValue', option.value);
  } else if (value === '') {
    emit('update:modelValue', null);
  }
};

const currentValue = computed(() => {
  if (props.modelValue === null || props.modelValue === '') return '';
  const option = props.options.find(o => o.value === props.modelValue);
  if (option) return String(option.value);
  return String(props.modelValue);
});
</script>

<template>
  <div class="relative">
    <label
      v-if="label"
      :for="selectId"
      class="sr-only"
    >
      {{ label }}
    </label>
    <select
      :id="selectId"
      :value="currentValue"
      :aria-label="label"
      class="pattern-field-clay h-9 w-full appearance-none cursor-pointer px-3 py-1.5 pe-8 text-sm"
      @change="handleChange"
    >
      <option value="">{{ placeholder }}</option>
      <option
        v-for="option in options"
        :key="String(option.value)"
        :value="String(option.value)"
      >
        {{ option.label }}
      </option>
    </select>
    <ChevronDown
      class="absolute end-3 top-1/2 -translate-y-1/2 pointer-events-none size-4 text-muted-foreground"
    />
  </div>
</template>