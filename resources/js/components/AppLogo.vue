<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();

const appName = computed<string>(() => {
    const name = page.props.name;

    if (typeof name === 'string' && name.trim() !== '') {
return name;
}

    return 'MCMS';
});
</script>

<template>
    <div class="flex aspect-square size-9 items-center justify-center rounded-lg bg-[#1D9E75] text-white">
        <AppLogoIcon class="size-[18px] fill-current text-white" />
    </div>
    <div class="me-3 grid flex-1 text-start leading-tight group-data-[collapsible=icon]:hidden">
        <span class="truncate text-sm text-[#1A1A1A]">{{ appName }}</span>
    </div>
</template>
