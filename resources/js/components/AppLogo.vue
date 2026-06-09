<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();

const appName = computed<string>(() => {
    const clinicName = page.props.clinic_name;

    if (typeof clinicName === 'string' && clinicName.trim() !== '') {
        return clinicName;
    }

    const name = page.props.name;

    if (typeof name === 'string' && name.trim() !== '') {
        return name;
    }

    return 'MCMS';
});
</script>

<template>
    <div class="flex aspect-square size-10 items-center justify-center rounded-full bg-[#0EA5E9] text-white shadow-[0_12px_24px_-16px_rgb(14_165_233_/_0.85)]">
        <AppLogoIcon class="size-[18px] fill-current text-white" />
    </div>
    <div class="me-3 grid flex-1 text-start leading-tight group-data-[collapsible=icon]:hidden">
        <span class="truncate text-sm font-bold text-[#0F172A]">{{ appName }}</span>
    </div>
</template>
