<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Building2,
    CalendarClock,
    HelpCircle,
    Key,
    LayoutGrid,
    ListOrdered,
    Shield,
    Stethoscope,
    Trash2,
    UserRound,
    Users,
    Wallet,
    CalendarDays,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import DepartmentController from '@/actions/App/Http/Controllers/Departments/DepartmentController';
import DoctorProfileController from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import QueueEntryController from '@/actions/App/Http/Controllers/Queue/QueueEntryController';
import RoleController from '@/actions/App/Http/Controllers/Rbac/RoleController';
import UserController from '@/actions/App/Http/Controllers/Security/UserController';
import VisitController from '@/actions/App/Http/Controllers/Visits/VisitController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { index as financialIndex } from '@/routes/financial';
import type { NavItem, NavSection } from '@/types';

type MainNavItem = NavItem & {
    group: string;
    permission?: string;
    anyPermissions?: string[];
};

const { can } = usePermissions();

const roleItemOrder: Record<string, string[]> = {
    doctor: [
        'المرضى',
        'العيادات',
        'الأطباء',
        'المواعيد',
        'قائمة الانتظار',
        'المالية',
        'الزيارات',
        'جداول الدوام',
        'لوحة التحكم',
    ],
    receptionist: [
        'المرضى',
        'العيادات',
        'الأطباء',
        'المواعيد',
        'قائمة الانتظار',
        'المالية',
        'جداول الدوام',
        'لوحة التحكم',
    ],
    accountant: ['المالية', 'لوحة التحكم'],
    admin: [
        'لوحة التحكم',
        'المرضى',
        'العيادات',
        'الأطباء',
        'المواعيد',
        'قائمة الانتظار',
        'المالية',
        'جداول الدوام',
        'الزيارات',
        'المستخدمون',
        'الأدوار',
    ],
    clinic_admin: [
        'المرضى',
        'العيادات',
        'الأطباء',
        'المواعيد',
        'قائمة الانتظار',
        'المالية',
        'جداول الدوام',
        'الزيارات',
        'لوحة التحكم',
        'المستخدمون',
        'الأدوار',
    ],
    super_admin: [
        'المرضى',
        'العيادات',
        'الأطباء',
        'المواعيد',
        'قائمة الانتظار',
        'المالية',
        'جداول الدوام',
        'الزيارات',
        'لوحة التحكم',
        'المستخدمون',
        'الأدوار',
    ],
};

const roleNames = computed<string[]>(() => {
    return (
        (usePage().props.auth as { roles?: string[] } | undefined)?.roles ?? []
    ).filter((value): value is string => typeof value === 'string');
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

    return (
        rolePriority.find((role) => roleNames.value.includes(role)) ?? 'staff'
    );
});

const mainNavItems = computed<MainNavItem[]>(() => {
    const visibleItems = (
        [
            {
                title: 'لوحة التحكم',
                href: dashboard(),
                icon: LayoutGrid,
                group: 'main',
            },
            {
                title: 'المرضى',
                href: PatientController.index(),
                icon: Users,
                group: 'clinical',
                permission: 'patient.view',
            },
            {
                title: 'العيادات',
                href: DepartmentController.index(),
                icon: Building2,
                group: 'clinical',
                permission: 'department.view',
            },
            {
                title: 'الأطباء',
                href: DoctorProfileController.index(),
                icon: UserRound,
                group: 'clinical',
                permission: 'doctor_profile.view',
            },
            {
                title: 'المواعيد',
                href: AppointmentController.index(),
                icon: CalendarClock,
                group: 'clinical',
                permission: 'appointment.view',
            },
            {
                title: 'قائمة الانتظار',
                href: QueueEntryController.index(),
                icon: ListOrdered,
                group: 'clinical',
                permission: 'queue.view',
            },
            {
                title: 'المالية',
                href: financialIndex(),
                icon: Wallet,
                group: 'clinical',
                anyPermissions: [
                    'billing.view',
                    'billing.generate',
                    'payment.record',
                    'payment.refund',
                    'accounts.view',
                    'expenses.view',
                    'cashbox.view',
                    'reports.financial',
                    'salaries.view',
                ],
            },
            {
                title: 'جداول الدوام',
                href: '/doctor-schedules',
                icon: CalendarDays,
                group: 'clinical',
                permission: 'doctor_schedule.view',
            },
            {
                title: 'الزيارات',
                href: VisitController.index(),
                icon: Stethoscope,
                group: 'clinical',
                anyPermissions: [
                    'visit.start',
                    'visit.update',
                    'visit.complete',
                ],
            },
            {
                title: 'المستخدمون',
                href: UserController.index(),
                icon: Shield,
                group: 'settings',
                permission: 'users.view',
            },
            {
                title: 'الأدوار',
                href: RoleController.index(),
                icon: Key,
                group: 'settings',
                permission: 'roles.view',
            },
        ] as MainNavItem[]
    ).filter((item) => {
        if (item.permission !== undefined && !can(item.permission)) {
            return false;
        }

        if (item.anyPermissions !== undefined) {
            return item.anyPermissions.some((permission) => can(permission));
        }

        return true;
    });

    const orderedTitles = roleItemOrder[primaryRole.value] ?? [];

    if (orderedTitles.length === 0) {
        return visibleItems;
    }

    const orderMap = new Map<string, number>(
        orderedTitles.map((title, index) => [title, index]),
    );

    return [...visibleItems].sort((a, b) => {
        const aOrder = orderMap.get(a.title) ?? Number.MAX_SAFE_INTEGER;
        const bOrder = orderMap.get(b.title) ?? Number.MAX_SAFE_INTEGER;

        return aOrder - bOrder;
    });
});

const sectionMetadata: Array<Omit<NavSection, 'items'>> = [
    { key: 'clinical', label: 'الصفحات المتاحة', description: '' },
    
   
];

const sidebarSections = computed<NavSection[]>(() =>
    sectionMetadata.map((section) => ({
        ...section,
        items: mainNavItems.value.filter(
            (item) => (item as MainNavItem).group === section.key,
        ),
    })),
);
</script>

<template>
    <Sidebar
        side="right"
        collapsible="icon"
        variant="sidebar"
        class="border-inline-start border-[#CFE8F7]/80 bg-[#EAF7FE] shadow-sidebar"
    >
        <!-- Logo & Clinic Name -->
        <SidebarHeader class="px-4 pt-4 pb-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="h-12 rounded-2xl px-2.5 transition-colors duration-200 hover:bg-white/70"
                    >
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <!-- Main Navigation -->
        <SidebarContent class="flex-1 overflow-y-auto px-3.5 py-2">
            <NavMain :sections="sidebarSections" />
        </SidebarContent>

       
    </Sidebar>

    <slot />
</template>
