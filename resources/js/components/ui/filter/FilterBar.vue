<script setup lang="ts">
import { computed } from 'vue';
import { SlidersHorizontal, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import FilterChip from './FilterChip.vue';

type ActiveFilter = {
  key: string;
  label: string;
  value: string | null;
};

const props = withDefaults(defineProps<{
  activeFilters: ActiveFilter[];
  showClearAll?: boolean;
  loading?: boolean;
}>(), {
  activeFilters: () => [],
  showClearAll: true,
  loading: false,
});

const emit = defineEmits<{
  (e: 'remove', key: string): void;
  (e: 'clearAll'): void;
}>();

const hasFilters = computed(() => props.activeFilters.length > 0);

const handleRemove = (key: string) => {
  emit('remove', key);
};
</script>

<template>
  <div class="space-y-3">
    <div
      v-if="hasFilters"
      class="flex flex-wrap items-center gap-2 animate-in slide-in-from-top-2 fade-in duration-200"
    >
      <div class="flex items-center gap-1.5 rounded-full border border-border/70 bg-background/80 px-2.5 py-1 text-[0.68rem] font-semibold tracking-normal text-muted-foreground uppercase">
        <SlidersHorizontal class="size-3.5" />
        Filters
      </div>
      <FilterChip
        v-for="filter in activeFilters"
        :key="filter.key"
        :label="filter.label"
        :value="filter.value"
        removable
        @remove="handleRemove(filter.key)"
      />
      <Button
        v-if="showClearAll && !loading"
        type="button"
        variant="ghost"
        size="sm"
        class="h-7 text-xs text-muted-foreground hover:text-destructive"
        @click="emit('clearAll')"
      >
        <X class="me-1 size-3" />
        Clear All
      </Button>
      <span v-if="loading" class="text-xs text-muted-foreground">Loading...</span>
    </div>
  </div>
</template>
