<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Activity, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import InvoiceController from '@/actions/App/Http/Controllers/Billing/InvoiceController';
import DepartmentController from '@/actions/App/Http/Controllers/Departments/DepartmentController';
import DoctorProfileController from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import QueueEntryController from '@/actions/App/Http/Controllers/Queue/QueueEntryController';
import ReportController from '@/actions/App/Http/Controllers/Reports/ReportController';
import VisitController from '@/actions/App/Http/Controllers/Visits/VisitController';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import { toUrl } from '@/lib/utils';
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

type QuickNavItem = NavItem & {
    permission?: string;
    anyPermissions?: string[];
};

const { can } = usePermissions();
const { isCurrentUrl } = useCurrentUrl();
const page = usePage();

const roleNames = computed<string[]>(() => {
    return (
        ((page.props.auth as { roles?: string[] } | undefined)?.roles ?? [])
            .filter((value): value is string => typeof value === 'string')
    );
});

const primaryRole = computed<string>(() => {
    const rolePriority = [
        'super_admin',
        'admin',
        'clinic_admin',
        'doctor',
        'receptionist',
        'accountant',
    ];

    return rolePriority.find((role) => roleNames.value.includes(role)) ?? 'staff';
});

const quickNavOrderByRole: Record<string, string[]> = {
    doctor: ['قائمة الانتظار', 'الزيارات', 'المواعيد', 'المرضى', 'الأقسام', 'الأطباء', 'لوحة التحكم'],
    receptionist: ['المرضى', 'الأقسام', 'الأطباء', 'المواعيد', 'قائمة الانتظار', 'لوحة التحكم'],
    accountant: ['الفواتير', 'التقارير', 'لوحة التحكم'],
    admin: ['لوحة التحكم', 'المرضى', 'الأقسام', 'الأطباء', 'المواعيد', 'قائمة الانتظار', 'الزيارات', 'الفواتير', 'التقارير'],
    clinic_admin: ['لوحة التحكم', 'المرضى', 'الأقسام', 'الأطباء', 'المواعيد', 'قائمة الانتظار', 'الزيارات', 'الفواتير', 'التقارير'],
    super_admin: ['لوحة التحكم', 'المرضى', 'الأقسام', 'الأطباء', 'المواعيد', 'قائمة الانتظار', 'الزيارات', 'الفواتير', 'التقارير'],
};

const quickNavItems = computed<NavItem[]>(() => {
    const visibleItems = (
        [
            {
                title: 'لوحة التحكم',
                href: dashboard(),
            },
            {
                title: 'المرضى',
                href: PatientController.index(),
                permission: 'patient.view',
            },
            {
                title: 'الأقسام',
                href: DepartmentController.index(),
                permission: 'department.view',
            },
            {
                title: 'الأطباء',
                href: DoctorProfileController.index(),
                permission: 'doctor_profile.view',
            },
            {
                title: 'المواعيد',
                href: AppointmentController.index(),
                permission: 'appointment.view',
            },
            {
                title: 'قائمة الانتظار',
                href: QueueEntryController.index(),
                permission: 'queue.view',
            },
            {
                title: 'الزيارات',
                href: VisitController.index(),
                anyPermissions: ['visit.start', 'visit.update', 'visit.complete'],
            },
            {
                title: 'الفواتير',
                href: InvoiceController.index(),
                permission: 'billing.view',
            },
            {
                title: 'التقارير',
                href: ReportController.index(),
                anyPermissions: ['reports.view', 'reports.financial'],
            },
        ] as QuickNavItem[]
    ).filter((item) => {
        if (item.permission !== undefined && !can(item.permission)) {
            return false;
        }

        if (item.anyPermissions !== undefined) {
            return item.anyPermissions.some((permission) => can(permission));
        }

        return true;
    });

    const roleOrder = quickNavOrderByRole[primaryRole.value] ?? [];

    if (roleOrder.length === 0) {
        return visibleItems;
    }

    const orderMap = new Map<string, number>(
        roleOrder.map((title, index) => [title, index]),
    );

    return [...visibleItems].sort((firstItem, secondItem) => {
        const firstOrder = orderMap.get(firstItem.title) ?? Number.MAX_SAFE_INTEGER;
        const secondOrder = orderMap.get(secondItem.title) ?? Number.MAX_SAFE_INTEGER;

        return firstOrder - secondOrder;
    });
});

const pageTitle = computed<string>(() => {
    if (props.breadcrumbs.length > 0) {
        return props.breadcrumbs[props.breadcrumbs.length - 1].title;
    }

    return 'Dashboard';
});

const contextLabel = computed<string>(() => {
    if (props.breadcrumbs.length > 1) {
        return props.breadcrumbs[0].title;
    }

    return 'مساحة العمليات';
});

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
        return quickNavItems.value;
    }

    return quickNavItems.value.filter((item) =>
        item.title.toLowerCase().includes(query),
    );
});

const canOpenPatients = computed<boolean>(
    () => can('patient.view') || can('patient.create'),
);

const canOpenAppointments = computed<boolean>(
    () => can('appointment.view') || can('appointment.create'),
);

const jumpToFirstMatch = (): void => {
    const first = jumpResults.value[0];

    if (first?.href) {
        router.visit(first.href);
    }
};
</script>

<template>
    <header class="sticky top-0 z-30 px-4 pt-4 pb-3 backdrop-blur-md md:px-6" dir="rtl">
        <div class="rounded-2xl border border-border/60 bg-card/95 px-4 py-4 shadow-sm md:px-5">
            <div class="flex flex-wrap items-center gap-4">
                <SidebarTrigger
                    class="rounded-xl border border-border/60 bg-background/80 p-2 transition-all duration-200 hover:bg-accent hover:shadow-sm"
                />

                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold tracking-[0.12em] text-muted-foreground uppercase">
                        {{ contextLabel }}
                    </p>
                    <h1
                        class="truncate text-lg font-bold text-foreground md:text-xl"
                    >
                        {{ pageTitle }}
                    </h1>
                </div>

                <div
                    class="hidden items-center gap-2 rounded-full border border-success-300/40 bg-success-500/10 px-3.5 py-1.5 text-xs font-semibold text-success-700 lg:flex dark:border-success-400/20 dark:bg-success-500/15 dark:text-success-300"
                >
                    <Activity class="size-4" />
                    <span>مباشر</span>
                    <span class="opacity-70">{{ todayLabel }}</span>
                </div>

                <div class="relative order-3 w-full lg:order-none lg:w-[24rem]">
                    <Search
                        class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-muted-foreground/70"
                    />
                    <Input
                        v-model="jumpQuery"
                        class="pattern-field-clay h-10 rounded-xl border-border/60 bg-background/80 pr-10 text-sm"
                        placeholder="انتقال لوحدة..."
                        @keydown.enter.prevent="jumpToFirstMatch"
                    />
                </div>

                <div class="hidden items-center gap-2.5 md:flex">
                    <Button
                        v-if="canOpenPatients"
                        as-child
                        size="sm"
                        class="h-9 rounded-xl px-4 text-xs font-semibold shadow-sm active:scale-95 transition-all"
                    >
                        <Link :href="PatientController.index()">
                            المرضى
                        </Link>
                    </Button>
                    <Button
                        v-if="canOpenAppointments"
                        as-child
                        variant="outline"
                        size="sm"
                        class="h-9 rounded-xl px-4 text-xs font-semibold border-border/60 hover:bg-accent active:scale-95 transition-all"
                    >
                        <Link :href="AppointmentController.index()">
                            المواعيد
                        </Link>
                    </Button>
                </div>
            </div>

            <nav class="mt-4 flex items-center gap-2.5 overflow-x-auto pb-1">
                <Link
                    v-for="item in quickNavItems"
                    :key="`quick-nav-${item.title}`"
                    :href="item.href"
                    class="shrink-0 rounded-full border px-3.5 py-2 text-xs font-semibold transition-all duration-200"
                    :class="
                        isCurrentUrl(item.href, undefined, true)
                            ? 'border-primary bg-primary text-primary-foreground shadow-md'
                            : 'border-border/60 bg-background/80 text-muted-foreground hover:bg-muted hover:text-foreground hover:shadow-sm'
                    "
                >
                    {{ item.title }}
                </Link>
            </nav>

            <div
                v-if="props.breadcrumbs.length > 1"
                class="mt-3 border-t border-border/60 pt-3 text-muted-foreground"
            >
                <Breadcrumbs :breadcrumbs="props.breadcrumbs" />
            </div>
        </div>
    </header>
</template>
