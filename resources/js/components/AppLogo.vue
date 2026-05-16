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
    <div
        class="flex aspect-square size-9 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 via-mint-500 to-teal-500 text-white shadow-sm"
    >
        <AppLogoIcon class="size-5 fill-current text-white" />
    </div>
    <div class="ml-2 grid flex-1 text-left leading-tight">
        <span class="truncate text-base font-bold">{{ appName }}</span>
        <span
            class="truncate text-[0.7rem] font-medium tracking-[0.12em] text-sidebar-foreground/60 uppercase"
        >
            Operations Suite
        </span>
    </div>
</template>
