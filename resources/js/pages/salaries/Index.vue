<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    CalendarDays,
    CircleDollarSign,
    Filter,
    HandCoins,
    Stethoscope,
    UsersRound,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PayrollController from '@/actions/App/Http/Controllers/Payroll/PayrollController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import { store as storeDoctorPayment } from '@/routes/salaries/doctor-payments';
import { store as storeEmployeePayment } from '@/routes/salaries/employee-payments';
import PayrollStatsCards from './components/PayrollStatsCards.vue';

type ClinicOption = { id: number; name: string };
type SalaryStatus = 'unpaid' | 'partially_paid' | 'paid';

type EmployeeSalaryRow = {
    id: number;
    employee_monthly_salary_id: number;
    employee_id: number;
    name: string;
    employee_type: string | null;
    job_title: string | null;
    clinic_id: number | null;
    clinic: string | null;
    base_salary: number;
    salary_month: string;
    due_amount: number;
    paid_amount: number;
    remaining_amount: number;
    status: SalaryStatus;
    payments_count: number;
    can_pay: boolean;
};

type DoctorDueRow = {
    id: number;
    doctor_monthly_due_id: number;
    doctor_id: number;
    name: string;
    clinic_id: number | null;
    clinic: string | null;
    payment_type: string;
    percentage: number | null;
    fixed_weekly_amount: number | null;
    fixed_monthly_amount: number | null;
    visits_count: number;
    visits_total_amount: number;
    deductions_amount: number;
    due_amount: number;
    paid_amount: number;
    remaining_amount: number;
    salary_month: string;
    status: SalaryStatus;
};

const props = defineProps<{
    employee_salaries: EmployeeSalaryRow[];
    doctor_dues: DoctorDueRow[];
    summaries: {
        employee_due: number;
        employee_paid: number;
        employee_remaining: number;
        employee_count: number;
        employee_paid_count: number;
        employee_unpaid_count: number;
        doctor_due: number;
        doctor_paid: number;
        doctor_remaining: number;
        doctor_count: number;
        doctor_paid_count: number;
        doctor_unpaid_count: number;
        total_due: number;
        total_paid: number;
        total_remaining: number;
        total_count: number;
    };
    clinics: ClinicOption[];
    filters: Record<string, string | number | null>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'الرواتب', href: PayrollController.index() }],
    },
});

const { can } = usePermissions();
const toast = useToast();
const activeTab = ref<'employees' | 'doctors'>(
    props.filters.person_type === 'doctor' ? 'doctors' : 'employees',
);
const paymentDialogOpen = ref(false);
const paymentTarget = ref<EmployeeSalaryRow | DoctorDueRow | null>(null);
const paymentKind = ref<'employee' | 'doctor'>('employee');
const month = ref(
    String(props.filters.month ?? new Date().toISOString().slice(0, 7)),
);
const personType = ref(String(props.filters.person_type ?? ''));
const status = ref(String(props.filters.status ?? ''));
const clinicId = ref(String(props.filters.clinic_id ?? ''));
const employeeType = ref(String(props.filters.employee_type ?? ''));
const doctorPaymentType = ref('');
const expandedDoctorDueId = ref<number | null>(null);
const unassignedClinicLabel = 'غير مرتبط بعيادة';

const labels: Record<string, string> = {
    reception: 'استقبال',
    nurse: 'ممرض',
    lab: 'مخبري',
    cleaner: 'عامل نظافة',
    guard: 'حارس',
    accountant: 'محاسب',
    administrative: 'إداري',
    other: 'أخرى',
    percentage: 'نسبة مئوية',
    fixed_weekly: 'أجر أسبوعي',
    fixed_monthly: 'أجر شهري',
    unpaid: 'غير مدفوع',
    partially_paid: 'مدفوع جزئيا',
    paid: 'مدفوع بالكامل',
    cash: 'نقدا',
    bank_transfer: 'حوالة بنكية',
    card: 'بطاقة',
};

const paymentForm = useForm({
    employee_monthly_salary_id: 0,
    doctor_monthly_due_id: 0,
    amount: '',
    payment_method: 'cash',
    payment_date: new Date().toISOString().slice(0, 10),
    notes: '',
});

const formatMoney = (value: number): string =>
    new Intl.NumberFormat('en-US-u-nu-latn', {
        maximumFractionDigits: 0,
    }).format(value);

const labelFor = (value: string | null): string =>
    value !== null ? (labels[value] ?? value) : '-';

const statusClass = (value: SalaryStatus): string => {
    if (value === 'paid') {
        return 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/25';
    }

    if (value === 'partially_paid') {
        return 'bg-amber-500/10 text-amber-700 ring-amber-500/20';
    }

    return 'bg-rose-500/10 text-rose-700 ring-rose-500/20';
};

const filteredTabs = computed(() => ({
    employees: personType.value !== 'doctor',
    doctors: personType.value !== 'employee',
}));

const filteredDoctorDues = computed(() =>
    props.doctor_dues.filter((row) => {
        const matchesPaymentType =
            doctorPaymentType.value === '' ||
            row.payment_type === doctorPaymentType.value;

        return matchesPaymentType;
    }),
);

const doctorTableSummary = computed(() =>
    filteredDoctorDues.value.reduce(
        (summary, row) => ({
            due: summary.due + row.due_amount,
            paid: summary.paid + row.paid_amount,
            remaining: summary.remaining + row.remaining_amount,
        }),
        { due: 0, paid: 0, remaining: 0 },
    ),
);

const employeesTotal = computed(() => props.employee_salaries.length);
const doctorsTotal = computed(() => filteredDoctorDues.value.length);

const selectedMonthLabel = computed(() => {
    const [year, monthNumber] = month.value.split('-');

    if (!year || !monthNumber) {
        return month.value;
    }

    return `${monthNumber}/${year}`;
});

const reload = (): void => {
    router.get(
        PayrollController.index.url(),
        {
            month: month.value || undefined,
            person_type: personType.value || undefined,
            status: status.value || undefined,
            clinic_id: clinicId.value || undefined,
            employee_type: employeeType.value || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

watch([month, personType, status, clinicId, employeeType], () => {
    if (personType.value === 'employee') {
        activeTab.value = 'employees';
    }

    if (personType.value === 'doctor') {
        activeTab.value = 'doctors';
    }

    reload();
});

const openEmployeePayment = (row: EmployeeSalaryRow): void => {
    paymentKind.value = 'employee';
    paymentTarget.value = row;
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentForm.employee_monthly_salary_id = row.employee_monthly_salary_id;
    paymentForm.doctor_monthly_due_id = 0;
    paymentForm.amount = String(row.remaining_amount);
    paymentDialogOpen.value = true;
};

const openDoctorPayment = (row: DoctorDueRow): void => {
    paymentKind.value = 'doctor';
    paymentTarget.value = row;
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentForm.employee_monthly_salary_id = 0;
    paymentForm.doctor_monthly_due_id = row.doctor_monthly_due_id;
    paymentForm.amount = String(row.remaining_amount);
    paymentDialogOpen.value = true;
};

const submitPayment = (): void => {
    const options = {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            paymentDialogOpen.value = false;
            paymentTarget.value = null;
            toast.success('تم تسجيل الدفعة بنجاح');
        },
        onError: () => toast.error('تعذر تسجيل الدفعة'),
    };

    if (paymentKind.value === 'employee') {
        paymentForm.post(storeEmployeePayment.url(), options);

        return;
    }

    paymentForm.post(storeDoctorPayment.url(), options);
};

const doctorCompensationDisplay = (row: DoctorDueRow): string => {
    if (row.payment_type === 'percentage' && row.percentage !== null) {
        return `نسبة ${row.percentage}%`;
    }

    if (
        row.payment_type === 'fixed_weekly' &&
        row.fixed_weekly_amount !== null
    ) {
        return `أسبوعي: ${formatMoney(row.fixed_weekly_amount)}`;
    }

    if (
        row.payment_type === 'fixed_monthly' &&
        row.fixed_monthly_amount !== null
    ) {
        return `شهري: ${formatMoney(row.fixed_monthly_amount)}`;
    }

    return labelFor(row.payment_type);
};

const doctorPaymentActionLabel = (row: DoctorDueRow): string => {
    if (row.payment_type === 'percentage') {
        return 'تسديد مستحقات الفترة';
    }

    if (row.payment_type === 'fixed_weekly') {
        return 'تسديد أجر أسبوع';
    }

    if (row.payment_type === 'fixed_monthly') {
        return 'تسديد أجر شهر';
    }

    return 'تسديد مستحقات الطبيب';
};

const toggleDoctorDetails = (row: DoctorDueRow): void => {
    expandedDoctorDueId.value =
        expandedDoctorDueId.value === row.doctor_monthly_due_id
            ? null
            : row.doctor_monthly_due_id;
};

const paymentTitle = computed(() =>
    paymentKind.value === 'employee' ? 'تسديد راتب موظف' : 'تسديد مستحقات طبيب',
);

const paymentHelpText = computed(() =>
    paymentKind.value === 'employee'
        ? 'راتب الموظف يسدد دفعة واحدة كاملة لهذا الشهر، ولا يمكن تسجيل دفعة ثانية لنفس الشهر.'
        : 'يمكن تسديد مستحقات الطبيب كدفعة كاملة أو جزئية حسب المتبقي.',
);
</script>

<template>
    <Head title="الرواتب" />

    <div class="mx-auto w-full max-w-[1680px] space-y-6 p-4 md:p-6" dir="rtl">
        <section
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div class="space-y-2 text-right">
                <div
                    class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary"
                >
                    <Banknote class="size-4" />
                    الرواتب
                </div>
                <h1 class="text-3xl font-extrabold text-foreground">
                    إدارة الرواتب والمستحقات الشهرية
                </h1>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    متابعة رواتب الموظفين ومستحقات الأطباء لشهر
                    {{ selectedMonthLabel }} مع منع تكرار صرف راتب الموظف خلال
                    الشهر.
                </p>
            </div>
        </section>

        <PayrollStatsCards :summaries="summaries" />

        <section
            class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card"
        >
            <div
                class="mb-4 flex items-center gap-2 text-sm font-bold text-foreground"
            >
                <Filter class="size-4 text-primary" />
                <span>الفلاتر</span>
            </div>
            <div class="grid gap-3 md:grid-cols-5">
                <div class="grid gap-1.5">
                    <Label>الشهر المالي</Label>
                    <Input
                        v-model="month"
                        type="month"
                        class="h-11 rounded-lg"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label>نوع الشخص</Label>
                    <select
                        v-model="personType"
                        class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                    >
                        <option value="">الكل</option>
                        <option value="employee">موظف</option>
                        <option value="doctor">طبيب</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>الحالة</Label>
                    <select
                        v-model="status"
                        class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                    >
                        <option value="">كل الحالات</option>
                        <option value="unpaid">غير مدفوع</option>
                        <option value="partially_paid">مدفوع جزئيا</option>
                        <option value="paid">مدفوع بالكامل</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>العيادة</Label>
                    <select
                        v-model="clinicId"
                        class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                    >
                        <option value="">الكل</option>
                        <option value="unassigned">
                            {{ unassignedClinicLabel }}
                        </option>
                        <option
                            v-for="clinic in clinics"
                            :key="clinic.id"
                            :value="clinic.id"
                        >
                            {{ clinic.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>نوع الموظف</Label>
                    <select
                        v-model="employeeType"
                        class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                    >
                        <option value="">كل الأنواع</option>
                        <option value="reception">استقبال</option>
                        <option value="nurse">ممرض</option>
                        <option value="lab">مخبري</option>
                        <option value="user">مستخدم</option>
                        <option value="cleaner">عامل نظافة</option>
                        <option value="guard">حارس</option>
                        <option value="accountant">محاسب</option>
                        <option value="administrative">إداري</option>
                        <option value="other">أخرى</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div
                class="flex flex-col gap-3 rounded-[1.2rem] border border-border bg-card/95 p-3 shadow-card lg:flex-row lg:items-center lg:justify-between"
            >
                <div
                    class="inline-flex w-fit rounded-xl border border-border bg-muted/70 p-1"
                >
                    <button
                        v-if="filteredTabs.employees"
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg px-4 text-sm font-bold transition-colors"
                        :class="
                            activeTab === 'employees'
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="activeTab = 'employees'"
                    >
                        <UsersRound class="size-4" />
                        رواتب الموظفين
                        <span
                            class="rounded-full bg-background/80 px-2 py-0.5 text-xs text-foreground"
                            >{{ employeesTotal }}</span
                        >
                    </button>
                    <button
                        v-if="filteredTabs.doctors"
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg px-4 text-sm font-bold transition-colors"
                        :class="
                            activeTab === 'doctors'
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="activeTab = 'doctors'"
                    >
                        <Stethoscope class="size-4" />
                        مستحقات الأطباء
                        <span
                            class="rounded-full bg-background/80 px-2 py-0.5 text-xs text-foreground"
                            >{{ doctorsTotal }}</span
                        >
                    </button>
                </div>

                <p class="text-sm text-muted-foreground">
                    الموظفون يسددون مرة واحدة شهريا، والأطباء يدعمون الدفعات
                    الجزئية.
                </p>
            </div>

            <div
                v-if="activeTab === 'employees'"
                class="overflow-hidden rounded-[1.2rem] border border-border bg-card shadow-card"
            >
                <div class="border-b border-border bg-muted/50 px-5 py-4">
                    <h2 class="text-base font-extrabold text-foreground">
                        جدول رواتب الموظفين
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        كل صف يمثل راتب موظف واحد لهذا الشهر.
                    </p>
                </div>
                <div class="overflow-hidden">
                    <table
                        class="w-full table-fixed text-right text-xs xl:text-sm"
                    >
                        <colgroup>
                            <col class="w-[15%]" />
                            <col class="w-[13%]" />
                            <col class="w-[10%]" />
                            <col class="w-[11%]" />
                            <col class="w-[10%]" />
                            <col class="w-[10%]" />
                            <col class="w-[10%]" />
                            <col class="w-[12%]" />
                            <col class="w-[9%]" />
                        </colgroup>
                        <thead
                            class="bg-muted/70 text-[11px] font-bold text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-3 py-3">الموظف</th>
                                <th class="px-3 py-3">الوظيفة</th>
                                <th class="px-3 py-3">القسم</th>
                                <th class="px-3 py-3">الراتب الأساسي</th>
                                <th class="px-3 py-3">المستحق</th>
                                <th class="px-3 py-3">المدفوع</th>
                                <th class="px-3 py-3">المتبقي</th>
                                <th class="px-3 py-3">الحالة</th>
                                <th class="px-3 py-3">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in employee_salaries"
                                :key="row.id"
                                class="border-t border-border/70 transition-colors hover:bg-primary/5"
                            >
                                <td class="px-3 py-3 align-top">
                                    <p
                                        class="truncate font-bold text-foreground"
                                    >
                                        {{ row.name }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ labelFor(row.employee_type) }}
                                    </p>
                                </td>
                                <td class="px-3 py-3 align-top text-foreground">
                                    <span class="block truncate">
                                        {{ row.job_title ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 align-top text-foreground">
                                    <span class="block truncate">
                                        {{ row.clinic ?? unassignedClinicLabel }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-3 align-top font-mono text-foreground tabular-nums"
                                >
                                    {{ formatMoney(row.base_salary) }}
                                </td>
                                <td
                                    class="px-3 py-3 align-top font-mono font-bold text-foreground tabular-nums"
                                >
                                    {{ formatMoney(row.due_amount) }}
                                </td>
                                <td
                                    class="px-3 py-3 align-top font-mono text-primary tabular-nums"
                                >
                                    {{ formatMoney(row.paid_amount) }}
                                </td>
                                <td
                                    class="px-3 py-3 align-top font-mono text-amber-700 tabular-nums"
                                >
                                    {{ formatMoney(row.remaining_amount) }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-bold ring-1"
                                        :class="statusClass(row.status)"
                                    >
                                        {{ labelFor(row.status) }}
                                    </span>
                                    <p
                                        v-if="row.payments_count > 0"
                                        class="mt-1 text-[10px] leading-tight text-muted-foreground"
                                    >
                                        تم تسجيل دفعة الشهر
                                    </p>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <Button
                                        v-if="
                                            can('salaries.pay') &&
                                            row.can_pay &&
                                            row.remaining_amount > 0
                                        "
                                        size="sm"
                                        class="h-8 w-full justify-center rounded-lg bg-primary px-2 text-[11px] text-primary-foreground hover:bg-primary/90"
                                        @click="openEmployeePayment(row)"
                                    >
                                        <HandCoins class="size-4" />
                                        تسديد كامل
                                    </Button>
                                    <span
                                        v-else
                                        class="block text-center text-[10px] leading-tight font-medium text-muted-foreground"
                                    >
                                        لا يوجد إجراء
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="employee_salaries.length === 0">
                                <td
                                    colspan="9"
                                    class="px-4 py-12 text-center text-muted-foreground"
                                >
                                    لا توجد رواتب موظفين ضمن الفلاتر الحالية.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="activeTab === 'doctors'"
                class="overflow-hidden rounded-[1.2rem] border border-border bg-card shadow-card"
            >
                <div class="border-b border-border bg-muted/50 px-5 py-4">
                    <h2 class="text-base font-extrabold text-foreground">
                        جدول مستحقات الأطباء
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        عرض إداري واضح للمستحقات، الدفعات، المتبقي، وحالة السداد
                        حسب الفترة المختارة.
                    </p>
                </div>

                <div class="border-b border-border bg-background px-5 py-4">
                    <div
                        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[repeat(4,minmax(0,1fr))]"
                    >
                        <div class="rounded-lg border border-border px-3 py-2">
                            <p
                                class="text-[11px] font-bold text-muted-foreground"
                            >
                                إجمالي مستحقات الأطباء
                            </p>
                            <p
                                class="mt-1 font-mono text-lg font-black text-foreground tabular-nums"
                            >
                                {{ formatMoney(doctorTableSummary.due) }}
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-emerald-500/20 px-3 py-2"
                        >
                            <p class="text-[11px] font-bold text-emerald-700">
                                إجمالي المدفوع
                            </p>
                            <p
                                class="mt-1 font-mono text-lg font-black text-emerald-700 tabular-nums"
                            >
                                {{ formatMoney(doctorTableSummary.paid) }}
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-amber-500/25 px-3 py-2"
                        >
                            <p class="text-[11px] font-bold text-amber-700">
                                إجمالي المتبقي
                            </p>
                            <p
                                class="mt-1 font-mono text-lg font-black text-amber-700 tabular-nums"
                            >
                                {{ formatMoney(doctorTableSummary.remaining) }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-border px-3 py-2">
                            <p
                                class="text-[11px] font-bold text-muted-foreground"
                            >
                                عدد الأطباء
                            </p>
                            <p
                                class="mt-1 font-mono text-lg font-black text-foreground tabular-nums"
                            >
                                {{ doctorsTotal }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div class="grid gap-1.5">
                            <Label>الفترة الزمنية</Label>
                            <Input
                                v-model="month"
                                type="month"
                                class="h-10 rounded-lg"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>العيادة</Label>
                            <select
                                v-model="clinicId"
                                class="h-10 rounded-lg border border-input bg-muted px-3 text-sm"
                            >
                                <option value="">كل العيادات</option>
                                <option
                                    v-for="clinic in clinics"
                                    :key="clinic.id"
                                    :value="clinic.id"
                                >
                                    {{ clinic.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>نوع الأجر</Label>
                            <select
                                v-model="doctorPaymentType"
                                class="h-10 rounded-lg border border-input bg-muted px-3 text-sm"
                            >
                                <option value="">كل الأنواع</option>
                                <option value="percentage">نسبة</option>
                                <option value="fixed_weekly">أسبوعي</option>
                                <option value="fixed_monthly">شهري</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>حالة السداد</Label>
                            <select
                                v-model="status"
                                class="h-10 rounded-lg border border-input bg-muted px-3 text-sm"
                            >
                                <option value="">كل الحالات</option>
                                <option value="unpaid">غير مدفوع</option>
                                <option value="partially_paid">
                                    مدفوع جزئياً
                                </option>
                                <option value="paid">مدفوع بالكامل</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="hidden xl:block">
                    <table class="w-full table-fixed text-right text-sm">
                        <colgroup>
                            <col class="w-[15%]" />
                            <col class="w-[11%]" />
                            <col class="w-[10%]" />
                            <col class="w-[8%]" />
                            <col class="w-[8%]" />
                            <col class="w-[10%]" />
                            <col class="w-[10%]" />
                            <col class="w-[10%]" />
                            <col class="w-[9%]" />
                            <col class="w-[9%]" />
                        </colgroup>
                        <thead
                            class="bg-muted/60 text-[11px] font-bold text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3">الطبيب</th>
                                <th class="px-4 py-3">العيادة</th>
                                <th class="px-4 py-3">نوع الأجر</th>
                                <th class="px-4 py-3">عدد الزيارات</th>
                                <th class="px-4 py-3">الخصومات</th>
                                <th class="px-4 py-3">المستحق</th>
                                <th class="px-4 py-3">المدفوع</th>
                                <th class="px-4 py-3">المتبقي</th>
                                <th class="px-4 py-3">حالة السداد</th>
                                <th class="px-4 py-3 text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in filteredDoctorDues"
                                :key="row.id"
                                class="border-t border-border/70 transition-colors hover:bg-primary/5"
                            >
                                <td class="px-4 py-3">
                                    <p
                                        class="truncate font-bold text-foreground"
                                    >
                                        {{ row.name }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ row.clinic ?? unassignedClinicLabel }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ row.clinic ?? unassignedClinicLabel }}
                                </td>
                                <td
                                    class="px-4 py-3 font-semibold text-foreground"
                                >
                                    {{ doctorCompensationDisplay(row) }}
                                </td>
                                <td class="px-4 py-3 font-mono tabular-nums">
                                    {{ formatMoney(row.visits_count) }}
                                </td>
                                <td class="px-4 py-3 font-mono tabular-nums">
                                    {{ formatMoney(row.deductions_amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex min-w-20 justify-center rounded-lg bg-muted px-2.5 py-1.5 font-mono font-black text-foreground tabular-nums"
                                    >
                                        {{ formatMoney(row.due_amount) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex min-w-20 justify-center rounded-lg bg-emerald-500/10 px-2.5 py-1.5 font-mono font-black text-emerald-700 tabular-nums"
                                    >
                                        {{ formatMoney(row.paid_amount) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex min-w-20 justify-center rounded-lg bg-amber-500/10 px-2.5 py-1.5 font-mono font-black text-amber-700 tabular-nums"
                                    >
                                        {{ formatMoney(row.remaining_amount) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ring-1"
                                        :class="statusClass(row.status)"
                                    >
                                        {{ labelFor(row.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="h-8 rounded-lg text-xs"
                                            @click="toggleDoctorDetails(row)"
                                        >
                                            عرض التفاصيل
                                        </Button>
                                        <Button
                                            v-if="
                                                can('salaries.pay') &&
                                                row.remaining_amount > 0
                                            "
                                            size="sm"
                                            class="h-8 rounded-lg bg-primary text-xs text-primary-foreground hover:bg-primary/90"
                                            @click="openDoctorPayment(row)"
                                        >
                                            {{ doctorPaymentActionLabel(row) }}
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-4 p-4 md:grid-cols-2 xl:hidden">
                    <article
                        v-for="row in filteredDoctorDues"
                        :key="row.id"
                        class="flex h-full flex-col gap-4 rounded-lg border border-border bg-background p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3
                                    class="truncate text-base font-extrabold text-foreground"
                                >
                                    {{ row.name }}
                                </h3>
                                <p
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    {{ row.clinic ?? unassignedClinicLabel }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-3 py-1.5 text-xs font-bold ring-1"
                                :class="statusClass(row.status)"
                            >
                                {{ labelFor(row.status) }}
                            </span>
                        </div>

                        <div class="grid gap-2 text-sm">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="font-bold text-muted-foreground">
                                    نوع الأجر
                                </span>
                                <span class="font-bold text-foreground">
                                    {{ doctorCompensationDisplay(row) }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="font-bold text-muted-foreground">
                                    عدد الزيارات
                                </span>
                                <span
                                    class="font-mono font-bold text-foreground tabular-nums"
                                >
                                    {{ formatMoney(row.visits_count) }} زيارة
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="font-bold text-muted-foreground">
                                    إجمالي الإيرادات
                                </span>
                                <span
                                    class="font-mono font-bold text-foreground tabular-nums"
                                >
                                    {{ formatMoney(row.visits_total_amount) }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="font-bold text-muted-foreground">
                                    الخصومات
                                </span>
                                <span
                                    class="font-mono font-bold text-foreground tabular-nums"
                                >
                                    {{ formatMoney(row.deductions_amount) }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <div class="rounded-lg bg-muted px-3 py-2">
                                <p
                                    class="text-xs font-bold text-muted-foreground"
                                >
                                    المستحق
                                </p>
                                <p
                                    class="mt-1 font-mono text-lg font-black text-foreground tabular-nums"
                                >
                                    {{ formatMoney(row.due_amount) }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-emerald-500/10 px-3 py-2">
                                <p class="text-xs font-bold text-emerald-700">
                                    المدفوع
                                </p>
                                <p
                                    class="mt-1 font-mono text-lg font-black text-emerald-700 tabular-nums"
                                >
                                    {{ formatMoney(row.paid_amount) }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-amber-500/10 px-3 py-2">
                                <p class="text-xs font-bold text-amber-700">
                                    المتبقي
                                </p>
                                <p
                                    class="mt-1 font-mono text-lg font-black text-amber-700 tabular-nums"
                                >
                                    {{ formatMoney(row.remaining_amount) }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="
                                expandedDoctorDueId ===
                                row.doctor_monthly_due_id
                            "
                            class="rounded-lg border border-border bg-muted/35 p-3 text-sm"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="font-bold text-muted-foreground">
                                    طريقة الحساب
                                </span>
                                <span class="font-bold text-foreground">
                                    {{ doctorCompensationDisplay(row) }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-auto grid gap-2 sm:grid-cols-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-10 rounded-lg"
                                @click="toggleDoctorDetails(row)"
                            >
                                عرض التفاصيل
                            </Button>
                            <Button
                                v-if="
                                    can('salaries.pay') &&
                                    row.remaining_amount > 0
                                "
                                size="sm"
                                class="h-10 rounded-lg bg-primary text-primary-foreground hover:bg-primary/90"
                                @click="openDoctorPayment(row)"
                            >
                                <CircleDollarSign class="size-4" />
                                {{ doctorPaymentActionLabel(row) }}
                            </Button>
                        </div>
                    </article>
                </div>

                <div
                    v-if="filteredDoctorDues.length === 0"
                    class="px-4 py-12 text-center text-muted-foreground"
                >
                    لا توجد مستحقات أطباء ضمن الفلاتر الحالية.
                </div>
            </div>
        </section>

        <Dialog
            :open="paymentDialogOpen"
            @update:open="paymentDialogOpen = $event"
        >
            <DialogContent class="max-w-2xl rounded-xl bg-card" dir="rtl">
                <DialogHeader class="text-right">
                    <DialogTitle class="text-foreground">{{
                        paymentTitle
                    }}</DialogTitle>
                </DialogHeader>
                <div v-if="paymentTarget" class="space-y-4">
                    <div
                        class="rounded-xl border border-border bg-muted/60 p-4 text-sm"
                    >
                        <p class="font-bold text-foreground">
                            {{ paymentTarget.name }}
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            الشهر: {{ paymentTarget.salary_month }}، المستحق:
                            {{
                                formatMoney(
                                    paymentKind === 'employee'
                                        ? (paymentTarget as EmployeeSalaryRow)
                                              .due_amount
                                        : (paymentTarget as DoctorDueRow)
                                              .due_amount,
                                )
                            }}، المتبقي:
                            {{ formatMoney(paymentTarget.remaining_amount) }}
                        </p>
                        <p
                            class="mt-2 text-xs font-medium text-muted-foreground"
                        >
                            {{ paymentHelpText }}
                        </p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>المبلغ المراد دفعه</Label>
                            <Input
                                v-model="paymentForm.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                :readonly="
                                    paymentKind === 'employee' ||
                                    paymentKind === 'doctor'
                                "
                                :class="{
                                    'bg-muted/70':
                                        paymentKind === 'employee' ||
                                        paymentKind === 'doctor',
                                }"
                            />
                            <InputError :message="paymentForm.errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label>طريقة الدفع</Label>
                            <select
                                v-model="paymentForm.payment_method"
                                class="h-10 rounded-md border border-input bg-muted px-3"
                            >
                                <option value="cash">نقدا</option>
                                <option value="bank_transfer">
                                    حوالة بنكية
                                </option>
                                <option value="card">بطاقة</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label>تاريخ الدفع</Label>
                            <Input
                                v-model="paymentForm.payment_date"
                                type="date"
                            />
                            <InputError
                                :message="paymentForm.errors.payment_date"
                            />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label>ملاحظات</Label>
                            <Input v-model="paymentForm.notes" />
                            <InputError
                                :message="
                                    paymentForm.errors
                                        .employee_monthly_salary_id
                                "
                            />
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="paymentDialogOpen = false"
                        >إلغاء</Button
                    >
                    <Button
                        type="button"
                        class="bg-primary text-primary-foreground hover:bg-primary/90"
                        :disabled="paymentForm.processing"
                        @click="submitPayment"
                    >
                        <CalendarDays class="size-4" />
                        حفظ الدفعة
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
