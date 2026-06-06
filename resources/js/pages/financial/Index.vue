<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { index as financialIndex } from '@/routes/financial';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'المالية', href: financialIndex() }],
    },
});
</script>

<template>
    <div class="mx-auto w-full max-w-[1680px] p-4 md:p-6" dir="rtl">
        <Head title="المالية" />
    </div>
</template>
