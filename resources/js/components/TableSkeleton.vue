<script setup lang="ts">
withDefaults(defineProps<{
    rows?: number;
    columns?: number;
}>(), {
    rows: 5,
    columns: 4,
});
</script>

<template>
    <div class="w-full space-y-3">
        <div class="flex gap-3">
            <div v-for="c in columns" :key="`header-${c}`" class="h-8 flex-1 animate-pulse motion-reduce:animate-none motion-reduce:opacity-30 rounded bg-muted/60" />
        </div>
        <div v-for="r in rows" :key="`row-${r}`" class="flex gap-3">
            <div v-for="c in columns" :key="`cell-${r}-${c}`" class="h-10 flex-1 animate-pulse motion-reduce:animate-none motion-reduce:opacity-30 rounded bg-muted/40" />
        </div>
    </div>
</template>
