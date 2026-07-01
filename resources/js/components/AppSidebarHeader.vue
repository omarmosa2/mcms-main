<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Activity, CalendarDays, Moon, Search, Sun } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';

const { appearance, updateAppearance } = useAppearance();

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const todayLabel = computed<string>(() =>
    new Intl.DateTimeFormat('ar-SA', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    }).format(new Date()),
);

const jumpQuery = ref<string>('');

const jumpResults = computed<NavItem[]>(() => {
    const query = jumpQuery.value.trim().toLowerCase();

    if (query === '') {
return [];
}

    return [
        { title: 'لوحة التحكم', href: dashboard() },
        { title: 'المرضى', href: PatientController.index() },
        { title: 'المواعيد', href: AppointmentController.index() },
    ].filter((item) => item.title.toLowerCase().includes(query));
});

const jumpToFirstMatch = (): void => {
    const first = jumpResults.value[0];

    if (first?.href) {
router.visit(first.href);
}
};
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-border/80 bg-background/90 px-4 py-3 backdrop-blur" dir="rtl">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <SidebarTrigger
                    class="size-9 rounded-xl border border-border bg-card/90 text-foreground shadow-sm hover:bg-accent"
                />

                <div class="min-w-0 text-muted-foreground">
                    <Breadcrumbs
                        v-if="props.breadcrumbs.length > 0"
                        :breadcrumbs="props.breadcrumbs"
                    />
                    <span v-else class="text-sm font-bold text-primary">لوحة التحكم</span>
                </div>
            </div>

            <div class="flex w-full flex-col-reverse gap-2 sm:flex-row lg:w-auto lg:items-center">
                

                <div class="flex items-center gap-2">
                    <div class="hidden items-center gap-2 rounded-full border border-border bg-card/80 px-3 py-2 text-xs font-semibold text-muted-foreground sm:flex">
                        <CalendarDays class="size-4 text-primary" />
                        <span>{{ todayLabel }}</span>
                    </div>

                    <div class="hidden items-center gap-2 rounded-full border border-primary/20 bg-accent px-3 py-2 text-xs font-bold text-accent-foreground lg:flex">
                        <Activity class="size-3.5 text-primary" />
                        <span>متصل</span>
                    </div>

                    <button
                        class="size-9 rounded-xl border border-border bg-card/90 text-muted-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                        @click="updateAppearance(appearance === 'dark' ? 'light' : 'dark')"
                    >
                        <Sun v-if="appearance === 'dark'" class="mx-auto size-4" />
                        <Moon v-else class="mx-auto size-4" />
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>
