<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Activity, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import QueueEntryController from '@/actions/App/Http/Controllers/Queue/QueueEntryController';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
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

const { can } = usePermissions();
const { isCurrentUrl } = useCurrentUrl();
const page = usePage();

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
    <!-- <header class="sticky top-0 z-30 px-4 pt-4 pb-3" dir="rtl">
        <div class="flex items-center gap-4 rounded-xl border border-[#E5E7EB] bg-white px-4 py-2.5 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
            <SidebarTrigger
                class="rounded-lg border border-[#E5E7EB] bg-white p-2 transition-colors hover:bg-[#F9FAFB]"
            />

            <div class="relative flex-1 max-w-md">
                <Search class="pointer-events-none absolute top-1/2 inset-inline-start-3 size-4 -translate-y-1/2 text-[#9CA3AF]" />
                <Input
                    v-model="jumpQuery"
                    class="h-9 w-full rounded-lg border border-[#E5E7EB] bg-[#F7F8FA] ps-10 text-sm text-[#1A1A1A] placeholder:text-[#9CA3AF] focus-visible:border-[#1D9E75] focus-visible:ring-0"
                    placeholder="بحث..."
                    @keydown.enter.prevent="jumpToFirstMatch"
                />
            </div>

            <div
                class="hidden items-center gap-2 rounded-full border border-[#1D9E75]/20 bg-[#E1F5EE] px-3 py-1.5 text-xs text-[#0F6E56] lg:flex"
            >
                <Activity class="size-3.5" />
                <span>مباشر</span>
                <span class="text-[#1D9E75]/60">{{ todayLabel }}</span>
            </div>
        </div>

        <div
            v-if="props.breadcrumbs.length > 1"
            class="mt-2 px-4 text-[#6B7280]"
        >
            <Breadcrumbs :breadcrumbs="props.breadcrumbs" />
        </div>
    </header> -->
</template>
