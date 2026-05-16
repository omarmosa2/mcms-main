<script setup lang="ts">
import { Search, Inbox } from 'lucide-vue-next';

withDefaults(defineProps<{
    title?: string;
    description?: string;
    icon?: 'search' | 'inbox';
}>(), {
    title: 'لا توجد نتائج',
    description: 'لا توجد عناصر للعرض حالياً.',
    icon: 'inbox',
});

const iconMap = {
    search: Search,
    inbox: Inbox,
};
</script>

<template>
    <div class="flex flex-col items-center justify-center py-12 text-center">
        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted/50">
            <component :is="iconMap[icon]" class="size-8 text-muted-foreground" />
        </div>
        <h3 class="text-lg font-semibold">{{ title }}</h3>
        <p class="mt-2 max-w-sm text-sm text-muted-foreground">{{ description }}</p>
        <slot />
    </div>
</template>
