<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Activity, CalendarDays, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import QueueEntryController from '@/actions/App/Http/Controllers/Queue/QueueEntryController';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';

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
        { title: 'قائمة الانتظار', href: QueueEntryController.index() },
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
    <header class="sticky top-0 z-30 border-b border-[#E5EEF7]/80 bg-[#F7FAFD]/90 px-4 py-3 backdrop-blur" dir="rtl">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <SidebarTrigger
                    class="size-9 rounded-xl border border-[#D9EAF6] bg-white/90 text-[#0F5F86] shadow-[0_1px_2px_rgb(15_42_71_/_0.06)] hover:bg-[#EAF7FE]"
                />

                <div class="min-w-0 text-[#6C7F95]">
                    <Breadcrumbs
                        v-if="props.breadcrumbs.length > 0"
                        :breadcrumbs="props.breadcrumbs"
                    />
                    <span v-else class="text-sm font-bold text-[#075985]">لوحة التحكم</span>
                </div>
            </div>

            <div class="flex w-full flex-col-reverse gap-2 sm:flex-row lg:w-auto lg:items-center">
                <div class="relative w-full sm:w-72 xl:w-80">
                    <Search class="pointer-events-none absolute top-1/2 inset-inline-start-3.5 z-10 size-4 -translate-y-1/2 text-[#6C7F95]" />
                    <Input
                        v-model="jumpQuery"
                        class="h-10 rounded-2xl border-[#DDE9F3] bg-white/90 ps-10 text-sm shadow-[0_1px_2px_rgb(15_42_71_/_0.05)] focus-visible:border-[#0EA5E9] focus-visible:ring-[#0EA5E9]/15"
                        placeholder="بحث سريع..."
                        @keydown.enter.prevent="jumpToFirstMatch"
                    />

                    <div
                        v-if="jumpResults.length > 0"
                        class="absolute inset-x-0 top-full z-50 mt-2 overflow-hidden rounded-2xl border border-[#DDE9F3] bg-white shadow-dropdown"
                    >
                        <Link
                            v-for="item in jumpResults"
                            :key="item.title"
                            :href="item.href"
                            class="block px-4 py-2.5 text-sm font-medium text-[#1A2B3F] transition hover:bg-[#EAF7FE] hover:text-[#075985]"
                            @click="jumpQuery = ''"
                        >
                            {{ item.title }}
                        </Link>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <div class="hidden items-center gap-2 rounded-full border border-[#D9EAF6] bg-white/80 px-3 py-2 text-xs font-semibold text-[#5F7890] sm:flex">
                        <CalendarDays class="size-4 text-[#0EA5E9]" />
                        <span>{{ todayLabel }}</span>
                    </div>

                    <div class="hidden items-center gap-2 rounded-full border border-[#BFE3F5] bg-[#EAF7FE] px-3 py-2 text-xs font-bold text-[#075985] lg:flex">
                        <Activity class="size-3.5 text-[#0EA5E9]" />
                        <span>مباشر</span>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
