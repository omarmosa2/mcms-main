<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden bg-[#F7FAFD]" dir="rtl">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="relative mx-auto w-full flex-1 px-4 py-6 md:px-8 lg:px-12">
                <slot />
            </div>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
