<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    Building2,
    CalendarClock,
    CalendarDays,
    CalendarOff,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    FileText,
    Key,
    LayoutGrid,
    Lock,
    LogOut,
    Settings,
    Shield,
    UserCircle,
    UserCog,
    UserRound,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import ClinicController from '@/actions/App/Http/Controllers/Clinics/ClinicController';
import DoctorController from '@/actions/App/Http/Controllers/DoctorController';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import MedicalRecordController from '@/actions/App/Http/Controllers/MedicalRecords/MedicalRecordController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import PayrollController from '@/actions/App/Http/Controllers/Payroll/PayrollController';
import RoleController from '@/actions/App/Http/Controllers/Rbac/RoleController';
import UserController from '@/actions/App/Http/Controllers/Security/UserController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    useSidebar,
} from '@/components/ui/sidebar';
import { useDirection } from '@/composables/useDirection';
import { useInitials } from '@/composables/useInitials';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard, logout } from '@/routes';
import {
    clinic as adminSettingsUrl,
    security as adminSecurityUrl,
} from '@/routes/admin-settings';
import { index as doctorLeavesIndex } from '@/routes/doctor-leaves';
import { index as financialIndex } from '@/routes/financial';
import type { NavItem, NavSection, User } from '@/types';

type MainNavItem = NavItem & {
    group: string;
    permission?: string;
    anyPermissions?: string[];
    doctorOnly?: boolean;
};

const { can } = usePermissions();
const { isRtl } = useDirection();
const { state, toggleSidebar } = useSidebar();
const { getInitials } = useInitials();

const page = usePage();

const user = computed<User | undefined>(() => {
    const auth = page.props.auth as { user?: User } | undefined;

    return auth?.user;
});

const showAvatar = computed(
    () => user.value?.avatar !== undefined && user.value?.avatar !== '',
);

const roleNames = computed<string[]>(() => {
    return (
        (page.props.auth as { roles?: string[] } | undefined)?.roles ?? []
    ).filter((value): value is string => typeof value === 'string');
});

const primaryRole = computed<string>(() => {
    if (roleNames.value.length === 0) {
        return 'super_admin';
    }

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

const roleLabels: Record<string, string> = {
    super_admin: 'مدير عام',
    admin: 'مدير',
    clinic_admin: 'مدير العيادة',
    doctor: 'طبيب',
    receptionist: 'موظف استقبال',
    accountant: 'محاسب',
    staff: 'موظف',
};

const roleLabel = computed<string>(
    () => roleLabels[primaryRole.value] ?? 'موظف',
);

const roleItemOrder: Record<string, string[]> = {
    doctor: ['لوحة التحكم', 'مواعيد اليوم', 'الوصفات الطبية', 'ملفي الشخصي'],
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
        'المواعيد',
        'جدول اليوم',
        'السجلات الطبية',
        'إجازات الأطباء',
        'العيادات',
        'الأطباء',
        'إدارة الموظفين',
        'الرواتب',
        'المالية',
        'الإعدادات',
        'الأمان',
        'المستخدمون',
        'الأدوار',
    ],
    clinic_admin: [
        'لوحة التحكم',
        'المرضى',
        'المواعيد',
        'جدول اليوم',
        'السجلات الطبية',
        'إجازات الأطباء',
        'العيادات',
        'الأطباء',
        'إدارة الموظفين',
        'الرواتب',
        'المالية',
        'الإعدادات',
        'الأمان',
        'المستخدمون',
        'الأدوار',
    ],
    super_admin: [
        'لوحة التحكم',
        'المرضى',
        'المواعيد',
        'جدول اليوم',
        'السجلات الطبية',
        'إجازات الأطباء',
        'العيادات',
        'الأطباء',
        'إدارة الموظفين',
        'الرواتب',
        'المالية',
        'الإعدادات',
        'الأمان',
        'المستخدمون',
        'الأدوار',
    ],
};

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
                title: 'المواعيد',
                href: AppointmentController.index(),
                icon: CalendarClock,
                group: 'clinical',
                permission: 'appointment.view',
            },
            {
                title: 'جدول اليوم',
                href: '/daily-schedule',
                icon: CalendarDays,
                group: 'clinical',
                permission: 'doctor_schedule.view',
            },
            {
                title: 'السجلات الطبية',
                href: MedicalRecordController.index(),
                icon: ClipboardList,
                group: 'clinical',
                permission: 'medical_record.view',
            },
            {
                title: 'الوصفات الطبية',
                href: '/doctor/prescriptions',
                icon: FileText,
                group: 'clinical',
                doctorOnly: true,
            },
            {
                title: 'مواعيد اليوم',
                href: '/doctor/appointments/today',
                icon: CalendarClock,
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
                title: 'إجازات الأطباء',
                href: doctorLeavesIndex(),
                icon: CalendarOff,
                group: 'clinical',
                permission: 'doctor_schedule.view',
            },
            {
                title: 'العيادات',
                href: ClinicController.index(),
                icon: Building2,
                group: 'management',
                permission: 'department.view',
            },
            {
                title: 'الأطباء',
                href: DoctorController.index(),
                icon: UserRound,
                group: 'management',
            },
            {
                title: 'إدارة الموظفين',
                href: EmployeeController.index(),
                icon: UserCog,
                group: 'management',
                permission: 'employees.view',
            },
            {
                title: 'الرواتب',
                href: PayrollController.index(),
                icon: BadgeDollarSign,
                group: 'management',
                permission: 'salaries.view',
            },
            {
                title: 'المالية',
                href: financialIndex(),
                icon: Wallet,
                group: 'management',
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
                title: 'الإعدادات',
                href: adminSettingsUrl(),
                icon: Settings,
                group: 'system',
                permission: 'settings.view',
            },
            {
                title: 'الأمان',
                href: adminSecurityUrl(),
                icon: Lock,
                group: 'system',
                permission: 'settings.view',
            },
            {
                title: 'المستخدمون',
                href: UserController.index(),
                icon: Shield,
                group: 'system',
                permission: 'users.view',
            },
            {
                title: 'الأدوار',
                href: RoleController.index(),
                icon: Key,
                group: 'system',
                permission: 'roles.view',
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

        if (roleNames.value.length === 0) {
            return true;
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
    { key: 'clinical', label: 'الإدارة الطبية', description: '' },
    { key: 'management', label: 'الإدارة', description: '' },
    { key: 'system', label: 'النظام', description: '' },
];

const sidebarSections = computed<NavSection[]>(() =>
    sectionMetadata.map((section) => ({
        ...section,
        items: mainNavItems.value.filter(
            (item) => (item as MainNavItem).group === section.key,
        ),
    })),
);

const handleLogout = (): void => {
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
        <!-- Header: logo, clinic name, collapse toggle -->
        <SidebarHeader
            class="border-b border-sidebar-border/60 p-3 transition-all duration-200 ease-linear group-data-[collapsible=icon]:p-2"
        >
            <div
                class="flex items-center gap-2 group-data-[collapsible=icon]:flex-col group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:gap-2.5"
            >
                <Link
                    :href="dashboard()"
                    class="flex min-w-0 flex-1 items-center gap-2.5 rounded-xl p-1 transition-colors duration-200 group-data-[collapsible=icon]:flex-none group-data-[collapsible=icon]:justify-center hover:bg-sidebar-accent/50"
                >
                    <AppLogo />
                </Link>

                <button
                    type="button"
                    :aria-label="
                        state === 'collapsed' ? 'توسيع القائمة' : 'طي القائمة'
                    "
                    :title="
                        state === 'collapsed' ? 'توسيع القائمة' : 'طي القائمة'
                    "
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg text-sidebar-foreground/70 transition-colors duration-200 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none"
                    @click="toggleSidebar"
                >
                    <ChevronRight
                        v-if="isRtl && state === 'expanded'"
                        class="size-4"
                    />
                    <ChevronLeft v-else-if="isRtl" class="size-4" />
                    <ChevronLeft
                        v-else-if="state === 'expanded'"
                        class="size-4"
                    />
                    <ChevronRight v-else class="size-4" />
                </button>
            </div>
        </SidebarHeader>

        <!-- Main navigation -->
        <SidebarContent
            class="flex-1 overflow-x-hidden overflow-y-auto px-3.5 py-3 [scrollbar-width:thin] group-data-[collapsible=icon]:overflow-x-hidden! group-data-[collapsible=icon]:overflow-y-auto! group-data-[collapsible=icon]:px-2 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-sidebar-border/70 [&::-webkit-scrollbar-track]:bg-transparent"
        >
            <NavMain :sections="sidebarSections" />
        </SidebarContent>

        <!-- Footer: account (user info + logout) -->
        <SidebarFooter
            class="border-t border-sidebar-border/60 p-3 transition-all duration-200 ease-linear group-data-[collapsible=icon]:p-2"
        >
            <div class="flex flex-col gap-2">
                <div
                    class="flex items-center gap-2.5 rounded-xl p-1.5 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-0"
                >
                    <Avatar
                        class="size-9 shrink-0 overflow-hidden rounded-full border border-sidebar-border/80 shadow-[0_8px_18px_-14px_rgb(14_165_233_/_0.4)]"
                    >
                        <AvatarImage
                            v-if="showAvatar && user && user.avatar"
                            :src="user.avatar"
                            :alt="user.name"
                        />
                        <AvatarFallback
                            class="rounded-full bg-primary/15 text-xs font-bold text-primary"
                        >
                            {{ user ? getInitials(user.name) : '؟' }}
                        </AvatarFallback>
                    </Avatar>

                    <div
                        class="min-w-0 flex-1 leading-tight transition-opacity duration-200 ease-linear group-data-[collapsible=icon]:pointer-events-none group-data-[collapsible=icon]:absolute group-data-[collapsible=icon]:opacity-0"
                    >
                        <span
                            class="block truncate text-[13px] font-bold text-foreground"
                            >{{ user?.name ?? 'ضيف' }}</span
                        >
                        <span
                            class="block truncate text-[11px] text-muted-foreground"
                            >{{ roleLabel }}</span
                        >
                    </div>
                </div>

                <div class="flex flex-col gap-1">
                    <span
                        class="px-3 pb-0.5 text-[11px] font-bold tracking-normal text-sidebar-foreground/55 transition-[opacity,height] duration-200 ease-linear group-data-[collapsible=icon]:h-0 group-data-[collapsible=icon]:overflow-hidden group-data-[collapsible=icon]:opacity-0"
                    >
                        الحساب
                    </span>

                    <button
                        type="button"
                        class="group/link nav-link relative flex h-11 w-full items-center gap-3 rounded-xl px-3 text-[13px] font-medium text-sidebar-foreground/80 transition-all duration-200 ease-linear outline-none group-data-[collapsible=icon]:mx-auto group-data-[collapsible=icon]:h-11 group-data-[collapsible=icon]:w-11 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:gap-0 group-data-[collapsible=icon]:rounded-2xl group-data-[collapsible=icon]:p-0 hover:bg-destructive/10 hover:text-destructive group-data-[collapsible=icon]:hover:bg-destructive/10 group-data-[collapsible=icon]:hover:text-destructive focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                        @click="handleLogout"
                    >
                        <LogOut
                            class="size-5 shrink-0 text-sidebar-foreground/70 transition-colors duration-200 ease-linear group-hover/link:text-destructive"
                        />
                        <span
                            class="truncate transition-opacity duration-200 ease-linear group-data-[collapsible=icon]:pointer-events-none group-data-[collapsible=icon]:absolute group-data-[collapsible=icon]:opacity-0"
                        >
                            تسجيل الخروج
                        </span>
                    </button>
                </div>
            </div>
        </SidebarFooter>
    </Sidebar>

    <slot />
</template>
