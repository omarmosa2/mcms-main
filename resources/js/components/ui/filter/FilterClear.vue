<script setup lang="ts">
import { RotateCcw } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

defineProps<{
  disabled?: boolean;
}>();

const emit = defineEmits<{
  (e: 'click'): void;
}>();
</script>

<template>
  <Button
    type="button"
    variant="neumorphic"
    size="sm"
    class="h-9 px-4 text-sm"
    :disabled="disabled"
    @click="emit('click')"
  >
    <RotateCcw class="me-2 size-4" />
    Reset
  </Button>
</template>