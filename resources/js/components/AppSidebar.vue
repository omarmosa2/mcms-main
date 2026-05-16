<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Bell,
    Building2,
    CalendarClock,
    DollarSign,
    HelpCircle,
    Key,
    LayoutGrid,
    ListOrdered,
    ReceiptText,
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
import InvoiceController from '@/actions/App/Http/Controllers/Billing/InvoiceController';
import CashboxController from '@/actions/App/Http/Controllers/Cashbox/CashboxController';
import DepartmentController from '@/actions/App/Http/Controllers/Departments/DepartmentController';
import DoctorProfileController from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
import ExpenseController from '@/actions/App/Http/Controllers/Expenses/ExpenseController';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import QueueEntryController from '@/actions/App/Http/Controllers/Queue/QueueEntryController';
import RoleController from '@/actions/App/Http/Controllers/Rbac/RoleController';
import ReportController from '@/actions/App/Http/Controllers/Reports/ReportController';
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
import { useDirection } from '@/composables/useDirection';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import type { NavItem, NavSection } from '@/types';

const { can } = usePermissions();
const { isRtl } = useDirection();
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

const roleWorkspace = {
    super_admin: {
        label: 'مساحة مدير النظام',
        description: 'وصول كامل لجميع الوحدات.',
    },
    admin: {
        label: 'مساحة المدير',
        description: 'إدارة العمليات والإيرادات والتقارير.',
    },
    clinic_admin: {
        label: 'مساحة مدير العيادة',
        description: 'إدارة العمليات والإيرادات والتقارير.',
    },
    receptionist: {
        label: 'مساحة الاستقبال',
        description: 'التركيز على ملفات المرضى والجدولة.',
    },
    doctor: {
        label: 'مساحة الطبيب',
        description: 'تتبع قائمة الانتظار والاستشارات.',
    },
    accountant: {
        label: 'مساحة المالية',
        description: 'التحكم بالفواتير والمدفوعات.',
    },
    staff: {
        label: 'مساحة العمل',
        description: 'التنقل مفلتر حسب صلاحياتك.',
    },
} as const;

const activeWorkspaceProfile = computed(() => {
    return roleWorkspace[primaryRole.value as keyof typeof roleWorkspace] ?? roleWorkspace.staff;
});

const roleItemOrder: Record<string, string[]> = {
    doctor: ['قائمة الانتظار', 'الزيارات', 'المواعيد', 'المرضى', 'الأقسام', 'الأطباء', 'جداول الدوام', 'لوحة التحكم'],
    receptionist: ['المرضى', 'الأقسام', 'الأطباء', 'جداول الدوام', 'المواعيد', 'قائمة الانتظار', 'الفواتير', 'لوحة التحكم'],
    accountant: ['الفواتير', 'المصروفات', 'الصندوق', 'التقارير', 'لوحة التحكم'],
    admin: ['لوحة التحكم', 'المرضى', 'الأقسام', 'الأطباء', 'جداول الدوام', 'المواعيد', 'قائمة الانتظار', 'الزيارات', 'المستخدمون', 'الأدوار', 'الفواتير', 'المصروفات', 'الصندوق', 'التقارير'],
    clinic_admin: ['لوحة التحكم', 'المرضى', 'الأقسام', 'الأطباء', 'جداول الدوام', 'المواعيد', 'قائمة الانتظار', 'الزيارات', 'المستخدمون', 'الأدوار', 'الفواتير', 'المصروفات', 'الصندوق', 'التقارير'],
    super_admin: ['لوحة التحكم', 'المرضى', 'الأقسام', 'الأطباء', 'جداول الدوام', 'المواعيد', 'قائمة الانتظار', 'الزيارات', 'المستخدمون', 'الأدوار', 'الفواتير', 'المصروفات', 'الصندوق', 'التقارير'],
};

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
                title: 'الأقسام',
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
                title: 'جداول الدوام',
                href: '/doctor-schedules',
                icon: CalendarDays,
                group: 'clinical',
                permission: 'doctor_schedule.view',
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
            {
                title: 'الفواتير',
                href: InvoiceController.index(),
                icon: ReceiptText,
                group: 'finance',
                permission: 'billing.view',
            },
            {
                title: 'المصروفات',
                href: ExpenseController.index(),
                icon: Wallet,
                group: 'finance',
                permission: 'expenses.view',
            },
            {
                title: 'الصندوق',
                href: CashboxController.index(),
                icon: DollarSign,
                group: 'finance',
                permission: 'cashbox.view',
            },
            {
                title: 'التقارير',
                href: ReportController.index(),
                icon: BarChart3,
                group: 'finance',
                anyPermissions: ['reports.view', 'reports.financial'],
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

    return [...visibleItems].sort((firstItem, secondItem) => {
        const firstOrder = orderMap.get(firstItem.title) ?? Number.MAX_SAFE_INTEGER;
        const secondOrder = orderMap.get(secondItem.title) ?? Number.MAX_SAFE_INTEGER;

        return firstOrder - secondOrder;
    });
});

const sectionMetadata: Array<Omit<NavSection, 'items'>> = [
    {
        key: 'main',
        label: 'الرئيسية',
        description: 'التنقل الرئيسي.',
    },
    {
        key: 'clinical',
        label: 'سريري',
        description: 'رعاية المرضى والعمليات.',
    },
    {
        key: 'settings',
        label: 'الإعدادات',
        description: 'المستخدمون والأدوار.',
    },
    {
        key: 'finance',
        label: 'المالية',
        description: 'الفواتير والتقارير.',
    },
];

const sidebarSections = computed<NavSection[]>(() =>
    sectionMetadata.map((section) => ({
        ...section,
        items: mainNavItems.value.filter(
            (item) => (item as MainNavItem).group === section.key,
        ),
    })),
);

const totalVisibleModules = computed<number>(() => mainNavItems.value.length);

const clinicalModules = computed<number>(
    () =>
        mainNavItems.value.filter((item) => (item as MainNavItem).group === 'clinical')
            .length,
);
</script>

<template>
    <Sidebar
        :side="isRtl ? 'right' : 'left'"
        collapsible="icon"
        variant="sidebar"
        class="border-s-0 [&_[data-sidebar=sidebar]]:bg-sidebar"
    >
        <SidebarHeader class="px-4 pt-5 pb-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="h-14 rounded-2xl border border-sidebar-border/60 bg-sidebar-accent/50 px-4 text-sidebar-foreground transition-all duration-200 hover:bg-sidebar-accent hover:shadow-sm"
                    >
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <div
                class="mt-4 rounded-2xl border border-sidebar-border/50 bg-sidebar-accent/50 p-4 text-sidebar-foreground group-data-[collapsible=icon]:hidden"
            >
                <p
                    class="text-[0.65rem] font-semibold tracking-[0.12em] text-sidebar-foreground/50 uppercase"
                >
                    مساحة العمل
                </p>
                <p class="mt-1.5 text-sm font-semibold">
                    {{ activeWorkspaceProfile.label }}
                </p>
                <div class="mt-3 flex items-center gap-2">
                    <span
                        class="rounded-full border border-sidebar-primary/20 bg-sidebar-primary/10 px-2.5 py-0.5 text-[0.65rem] font-semibold text-sidebar-primary"
                    >
                        {{ totalVisibleModules }} وحدات
                    </span>
                    <span
                        v-if="clinicalModules > 0"
                        class="rounded-full border border-sidebar-border/50 bg-sidebar-accent/60 px-2.5 py-0.5 text-[0.65rem] font-semibold text-sidebar-foreground/60"
                    >
                        {{ clinicalModules }} سريري
                    </span>
                </div>
            </div>
        </SidebarHeader>

        <SidebarContent class="px-2 pb-4">
            <NavMain :sections="sidebarSections" />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border/60 px-3 py-4">
            <div class="mb-3 space-y-1.5 group-data-[collapsible=icon]:hidden">
                <Link
                    :href="'/settings/notifications'"
                    class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-medium text-sidebar-foreground/60 transition-all duration-150 hover:bg-sidebar-accent hover:text-sidebar-foreground"
                >
                    <Bell class="size-4" />
                    الإشعارات
                </Link>
                <Link
                    :href="'/trash'"
                    class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-medium text-sidebar-foreground/60 transition-all duration-150 hover:bg-sidebar-accent hover:text-sidebar-foreground"
                >
                    <Trash2 class="size-4" />
                    سلة المحذوفات
                </Link>
                <Link
                    :href="'/help'"
                    class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-medium text-sidebar-foreground/60 transition-all duration-150 hover:bg-sidebar-accent hover:text-sidebar-foreground"
                >
                    <HelpCircle class="size-4" />
                    مركز المساعدة
                </Link>
            </div>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
