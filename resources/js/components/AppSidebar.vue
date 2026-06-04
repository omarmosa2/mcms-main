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
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import type { NavItem, NavSection } from '@/types';

type MainNavItem = NavItem & {
    group: string;
    permission?: string;
    anyPermissions?: string[];
};

const { can } = usePermissions();

const roleItemOrder: Record<string, string[]> = {
    doctor: ['قائمة الانتظار', 'الزيارات', 'المواعيد', 'المرضى', 'الأقسام', 'الأطباء', 'جداول الدوام', 'لوحة التحكم'],
    receptionist: ['المرضى', 'الأقسام', 'الأطباء', 'جداول الدوام', 'المواعيد', 'قائمة الانتظار', 'الفواتير', 'لوحة التحكم'],
    accountant: ['الفواتير', 'المصروفات', 'الصندوق', 'التقارير', 'لوحة التحكم'],
    admin: ['لوحة التحكم', 'المرضى', 'الأقسام', 'الأطباء', 'جداول الدوام', 'المواعيد', 'قائمة الانتظار', 'الزيارات', 'المستخدمون', 'الأدوار', 'الفواتير', 'المصروفات', 'الصندوق', 'التقارير'],
    clinic_admin: ['لوحة التحكم', 'المرضى', 'الأقسام', 'الأطباء', 'جداول الدوام', 'المواعيد', 'قائمة الانتظار', 'الزيارات', 'المستخدمون', 'الأدوار', 'الفواتير', 'المصروفات', 'الصندوق', 'التقارير'],
    super_admin: ['لوحة التحكم', 'المرضى', 'الأقسام', 'الأطباء', 'جداول الدوام', 'المواعيد', 'قائمة الانتظار', 'الزيارات', 'المستخدمون', 'الأدوار', 'الفواتير', 'المصروفات', 'الصندوق', 'التقارير'],
};

const roleNames = computed<string[]>(() => {
    return (
        ((usePage().props.auth as { roles?: string[] } | undefined)?.roles ?? [])
            .filter((value): value is string => typeof value === 'string')
    );
});

const primaryRole = computed<string>(() => {
    const rolePriority = ['super_admin', 'admin', 'clinic_admin', 'doctor', 'receptionist', 'accountant'];

    return rolePriority.find((role) => roleNames.value.includes(role)) ?? 'staff';
});

const mainNavItems = computed<MainNavItem[]>(() => {
    const visibleItems = (
        [
            { title: 'لوحة التحكم', href: dashboard(), icon: LayoutGrid, group: 'main' },
            { title: 'المرضى', href: PatientController.index(), icon: Users, group: 'clinical', permission: 'patient.view' },
            { title: 'العيادات', href: DepartmentController.index(), icon: Building2, group: 'clinical', permission: 'department.view' },
            { title: 'الأطباء', href: DoctorProfileController.index(), icon: UserRound, group: 'clinical', permission: 'doctor_profile.view' },
            { title: 'جداول الدوام', href: '/doctor-schedules', icon: CalendarDays, group: 'clinical', permission: 'doctor_schedule.view' },
            { title: 'المواعيد', href: AppointmentController.index(), icon: CalendarClock, group: 'clinical', permission: 'appointment.view' },
            { title: 'قائمة الانتظار', href: QueueEntryController.index(), icon: ListOrdered, group: 'clinical', permission: 'queue.view' },
            { title: 'الزيارات', href: VisitController.index(), icon: Stethoscope, group: 'clinical', anyPermissions: ['visit.start', 'visit.update', 'visit.complete'] },
            { title: 'المستخدمون', href: UserController.index(), icon: Shield, group: 'settings', permission: 'users.view' },
            { title: 'الأدوار', href: RoleController.index(), icon: Key, group: 'settings', permission: 'roles.view' },
            { title: 'الفواتير', href: InvoiceController.index(), icon: ReceiptText, group: 'finance', permission: 'billing.view' },
            { title: 'المصروفات', href: ExpenseController.index(), icon: Wallet, group: 'finance', permission: 'expenses.view' },
            { title: 'الصندوق', href: CashboxController.index(), icon: DollarSign, group: 'finance', permission: 'cashbox.view' },
            { title: 'التقارير', href: ReportController.index(), icon: BarChart3, group: 'finance', anyPermissions: ['reports.view', 'reports.financial'] },
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

    const orderMap = new Map<string, number>(orderedTitles.map((title, index) => [title, index]));

    return [...visibleItems].sort((a, b) => {
        const aOrder = orderMap.get(a.title) ?? Number.MAX_SAFE_INTEGER;
        const bOrder = orderMap.get(b.title) ?? Number.MAX_SAFE_INTEGER;

        return aOrder - bOrder;
    });
});

const sectionMetadata: Array<Omit<NavSection, 'items'>> = [
    { key: 'main', label: 'الرئيسية', description: '' },
    { key: 'clinical', label: 'العيادة', description: '' },
    { key: 'settings', label: 'الإدارة', description: '' },
    { key: 'finance', label: 'المالية', description: '' },
];

const sidebarSections = computed<NavSection[]>(() =>
    sectionMetadata.map((section) => ({
        ...section,
        items: mainNavItems.value.filter((item) => (item as MainNavItem).group === section.key),
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

        <!-- Footer: secondary links + user -->
        <SidebarFooter class="border-t border-[#CFE8F7]/70 px-3.5 pt-3 pb-3">

            <!-- Secondary nav links — hidden when sidebar is collapsed to icon-only -->
            <div class="mb-1.5 space-y-0.5 group-data-[collapsible=icon]:hidden">
                <Link
                    :href="'/settings/notifications'"
                    class="
                        flex items-center gap-2
                        rounded-xl px-2.5 py-2
                        text-[13px] text-[#47677F]
                        transition-all duration-200
                        hover:bg-white/65 hover:text-[#075985]
                    "
                >
                    <Bell class="size-7 shrink-0 rounded-full bg-[#D7F1FE] p-1.5 text-[#0EA5E9]" />
                    <span>الإشعارات</span>
                </Link>

                <Link
                    :href="'/trash'"
                    class="
                        flex items-center gap-2
                        rounded-xl px-2.5 py-2
                        text-[13px] text-[#47677F]
                        transition-all duration-200
                        hover:bg-white/65 hover:text-[#075985]
                    "
                >
                    <Trash2 class="size-7 shrink-0 rounded-full bg-[#D7F1FE] p-1.5 text-[#0EA5E9]" />
                    <span>سلة المحذوفات</span>
                </Link>

                <Link
                    :href="'/help'"
                    class="
                        flex items-center gap-2
                        rounded-xl px-2.5 py-2
                        text-[13px] text-[#47677F]
                        transition-all duration-200
                        hover:bg-white/65 hover:text-[#075985]
                    "
                >
                    <HelpCircle class="size-7 shrink-0 rounded-full bg-[#D7F1FE] p-1.5 text-[#0EA5E9]" />
                    <span>مركز المساعدة</span>
                </Link>
            </div>

            <!-- User avatar & info -->
            <NavUser />
        </SidebarFooter>
    </Sidebar>

    <slot />
</template>
