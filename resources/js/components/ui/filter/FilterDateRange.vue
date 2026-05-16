<script setup lang="ts">
import { useId } from 'vue';
import { computed } from 'vue';
import { Calendar, X } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

const props = withDefaults(defineProps<{
  from: string;
  to: string;
  label?: string;
}>(), {
  from: '',
  to: '',
});

const emit = defineEmits<{
  (e: 'update:from', payload: string): void;
  (e: 'update:to', payload: string): void;
}>();

const dateFromId = `date-from-${useId()}`;
const dateToId = `date-to-${useId()}`;

const isActive = computed(() => props.from || props.to);

const clearRange = () => {
  emit('update:from', '');
  emit('update:to', '');
};
</script>

<template>
  <div class="flex items-center gap-2" role="group" :aria-label="label">
    <div class="relative">
      <label :for="dateFromId" class="sr-only">Start date</label>
      <Calendar class="absolute start-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
      <Input
        :id="dateFromId"
        type="date"
        :value="from"
        aria-label="Start date"
        class="pattern-field-clay ps-9 pe-2"
        @input="emit('update:from', ($event.target as HTMLInputElement).value)"
      />
    </div>
    <span class="text-muted-foreground text-xs" aria-hidden="true">-</span>
    <div class="relative">
      <label :for="dateToId" class="sr-only">End date</label>
      <Input
        :id="dateToId"
        type="date"
        :value="to"
        aria-label="End date"
        class="pattern-field-clay ps-9 pe-2"
        @input="emit('update:to', ($event.target as HTMLInputElement).value)"
      />
    </div>
    <Button
      v-if="isActive"
      type="button"
      variant="ghost"
      size="icon-sm"
      class="size-8 shrink-0 text-muted-foreground hover:text-destructive"
      aria-label="Clear date range"
      @click="clearRange"
    >
      <X class="size-3" />
    </Button>
  </div>
</template>