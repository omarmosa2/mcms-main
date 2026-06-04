<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <div class="glass-panel-soft space-y-6 p-5">
        <Heading
            tone="typographic"
            variant="small"
            title="Appearance and Theme"
            description="Choose the visual mode that fits your workflow environment."
        />
        <div class="metric-tile">
            <p
                class="text-xs font-semibold tracking-normal text-muted-foreground uppercase"
            >
                Workspace theme
            </p>
            <p class="mt-2 text-sm text-muted-foreground">
                Theme changes apply instantly across dashboard, forms, and data
                views for a consistent visual experience.
            </p>
        </div>
        <AppearanceTabs />
    </div>
</template>
