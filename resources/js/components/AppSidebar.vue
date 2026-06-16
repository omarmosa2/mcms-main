<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarClock,
    CalendarOff,
    Key,
    LayoutGrid,
    LogOut,
    Settings,
    Shield,
    UserCog,
    UserRound,
    Users,
    Wallet,
    BadgeDollarSign,
    FileText,
    UserCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import DepartmentController from '@/actions/App/Http/Controllers/Departments/DepartmentController';
import DoctorProfileController from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import PayrollController from '@/actions/App/Http/Controllers/Payroll/PayrollController';
import RoleController from '@/actions/App/Http/Controllers/Rbac/RoleController';
import UserController from '@/actions/App/Http/Controllers/Security/UserController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
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
import { dashboard, logout } from '@/routes';
import { clinic as adminSettingsUrl } from '@/routes/admin-settings';
import { index as doctorLeavesIndex } from '@/routes/doctor-leaves';
import { index as financialIndex } from '@/routes/financial';
import type { NavItem, NavSection } from '@/types';

type MainNavItem = NavItem & {
    group: string;
    permission?: string;
    anyPermissions?: string[];
    doctorOnly?: boolean;
};

const { can } = usePermissions();

const roleItemOrder: Record<string, string[]> = {
    doctor: [
        'لوحة التحكم',
        'مواعيد اليوم',
        'الوصفات الطبية',
        'ملفي الشخصي',
    ],
    receptionist: [
        'لوحة التحكم',
        'المرضى',
        'العيادات',
        'الأطباء',
        'المواعيد',
        'المالية',
    ],
    accountant: ['لوحة التحكم', 'المالية'],
    admin: [
        'لوحة التحكم',
        'المرضى',
        'العيادات',
        'الأطباء',
        'المواعيد',
        'جدول اليوم',
        'المالية',
        'المستخدمون',
        'الأدوار',
        'الإعدادات',
    ],
    clinic_admin: [
        'لوحة التحكم',
        'المرضى',
        'العيادات',
        'الأطباء',
        'المواعيد',
        'جدول اليوم',
        'المالية',
        'المستخدمون',
        'الأدوار',
        'الإعدادات',
    ],
    super_admin: [
        'لوحة التحكم',
        'المرضى',
        'العيادات',
        'الأطباء',
        'المواعيد',
        'جدول اليوم',
        'المالية',
        'المستخدمون',
        'الأدوار',
        'الإعدادات',
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

const isDoctor = computed(() => primaryRole.value === 'doctor');

const mainNavItems = computed<MainNavItem[]>(() => {
    const visibleItems = (
        [
            {
                title: 'لوحة التحكم',
                href: isDoctor.value ? '/doctor/workspace' : dashboard(),
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
                title: 'إدارة الموظفين',
                href: EmployeeController.index(),
                icon: UserCog,
                group: 'clinical',
                permission: 'employees.view',
            },
            {
                title: 'المواعيد',
                href: AppointmentController.index(),
                icon: CalendarClock,
                group: 'clinical',
                permission: 'appointment.view',
            },
            {
                title: 'مواعيد اليوم',
                href: '/doctor/appointments/today',
                icon: CalendarClock,
                group: 'clinical',
                doctorOnly: true,
            },
            {
                title: 'الوصفات الطبية',
                href: '/doctor/prescriptions',
                icon: FileText,
                group: 'clinical',
                doctorOnly: true,
            },
            {
                title: 'ملفي الشخصي',
                href: '/doctor/profile',
                icon: UserCircle,
                group: 'clinical',
                doctorOnly: true,
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
                title: 'الرواتب',
                href: PayrollController.index(),
                icon: BadgeDollarSign,
                group: 'clinical',
                permission: 'salaries.view',
            },
            {
                title: 'جدول اليوم',
                href: '/daily-schedule',
                icon: CalendarClock,
                group: 'clinical',
                permission: 'doctor_schedule.view',
            },
            {
                title: 'إجازات الأطباء',
                href: doctorLeavesIndex(),
                icon: CalendarOff,
                group: 'clinical',
                permission: 'doctor_schedule.view',
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
            {
                title: 'الإعدادات',
                href: adminSettingsUrl(),
                icon: Settings,
                group: 'settings',
                permission: 'settings.view',
            },
        ] as MainNavItem[]
    ).filter((item) => {
        if (item.doctorOnly) {
            return isDoctor.value;
        }

        if (
            isDoctor.value &&
            !item.doctorOnly &&
            item.title !== 'لوحة التحكم'
        ) {
            return false;
        }

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
    { key: 'main', label: 'الرئيسية', description: '' },
    { key: 'clinical', label: 'الصفحات المتاحة', description: '' },
    { key: 'settings', label: 'الإدارة', description: '' },
];

const sidebarSections = computed<NavSection[]>(() =>
    sectionMetadata.map((section) => ({
        ...section,
        items: mainNavItems.value.filter(
            (item) => (item as MainNavItem).group === section.key,
        ),
    })),
);

const handleLogout = () => {
    router.post(logout());
};
</script>

<template>
    <Sidebar
        side="right"
        collapsible="icon"
        variant="sidebar"
        class="border-inline-start border-sidebar-border/80 bg-sidebar shadow-sidebar"
    >
        <!-- Logo & Clinic Name -->
        <SidebarHeader class="px-4 pt-4 pb-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="h-12 rounded-2xl px-2.5 transition-colors duration-200 hover:bg-sidebar-accent/50"
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

        <!-- Logout Button -->
        <SidebarFooter class="border-t border-sidebar-border/80 px-3 py-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        class="rounded-2xl transition-colors duration-200 hover:bg-sidebar-accent/50"
                        @click="handleLogout"
                    >
                        <LogOut class="h-5 w-5" />
                        <span>تسجيل الخروج</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>

    <slot />
</template>
