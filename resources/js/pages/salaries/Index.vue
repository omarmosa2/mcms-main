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
import PayrollStatsCards from './components/PayrollStatsCards.vue';

type DepartmentOption = { id: number; name: string };
type SalaryStatus = 'unpaid' | 'partially_paid' | 'paid';

type EmployeeSalaryRow = {
    id: number;
    employee_monthly_salary_id: number;
    employee_id: number;
    name: string;
    employee_type: string | null;
    job_title: string | null;
    department: string | null;
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
    department: string | null;
    payment_type: string;
    percentage: number | null;
    fixed_weekly_amount: number | null;
    fixed_monthly_amount: number | null;
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
    departments: DepartmentOption[];
    filters: Record<string, string | number | null>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'الرواتب', href: PayrollController.index() }],
    },
});

const { can } = usePermissions();
const toast = useToast();
const activeTab = ref<'employees' | 'doctors'>('employees');
const paymentDialogOpen = ref(false);
const paymentTarget = ref<EmployeeSalaryRow | DoctorDueRow | null>(null);
const paymentKind = ref<'employee' | 'doctor'>('employee');
const month = ref(
    String(props.filters.month ?? new Date().toISOString().slice(0, 7)),
);
const personType = ref(String(props.filters.person_type ?? ''));
const status = ref(String(props.filters.status ?? ''));
const departmentId = ref(String(props.filters.department_id ?? ''));

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
    weekly: 'أجر أسبوعي',
    monthly: 'أجر شهري',
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
    new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 0 }).format(value);

const labelFor = (value: string | null): string =>
    value !== null ? (labels[value] ?? value) : '-';

const statusClass = (value: SalaryStatus): string => {
    if (value === 'paid') {
        return 'bg-primary/10 text-primary ring-primary/20';
    }

    if (value === 'partially_paid') {
        return 'bg-amber-500/10 text-amber-700 ring-amber-500/20';
    }

    return 'bg-muted text-muted-foreground ring-border';
};

const filteredTabs = computed(() => ({
    employees: personType.value !== 'doctor',
    doctors: personType.value !== 'employee',
}));

const employeesTotal = computed(() => props.employee_salaries.length);
const doctorsTotal = computed(() => props.doctor_dues.length);

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
            department_id: departmentId.value || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

watch([month, personType, status, departmentId], () => {
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
        onSuccess: () => {
            paymentDialogOpen.value = false;
            toast.success('تم تسجيل الدفعة بنجاح');
        },
        onError: () => toast.error('تعذر تسجيل الدفعة'),
    };

    if (paymentKind.value === 'employee') {
        paymentForm.post(PayrollController.storeEmployeePayment.url(), options);

        return;
    }

    paymentForm.post(PayrollController.storeDoctorPayment.url(), options);
};

const doctorCompensationDisplay = (row: DoctorDueRow): string => {
    if (row.payment_type === 'percentage' && row.percentage !== null) {
        return `نسبة ${row.percentage}%`;
    }

    if (row.payment_type === 'weekly' && row.fixed_weekly_amount !== null) {
        return `أسبوعي: ${formatMoney(row.fixed_weekly_amount)}`;
    }

    if (row.payment_type === 'monthly' && row.fixed_monthly_amount !== null) {
        return `شهري: ${formatMoney(row.fixed_monthly_amount)}`;
    }

    return labelFor(row.payment_type);
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
            <div class="grid gap-3 md:grid-cols-4">
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
                    <Label>القسم / العيادة</Label>
                    <select
                        v-model="departmentId"
                        class="h-11 rounded-lg border border-input bg-muted px-3 text-sm"
                    >
                        <option value="">الكل</option>
                        <option
                            v-for="department in departments"
                            :key="department.id"
                            :value="department.id"
                        >
                            {{ department.name }}
                        </option>
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
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1120px] text-right text-sm">
                        <thead
                            class="bg-muted/70 text-[11px] font-bold text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">الموظف</th>
                                <th class="px-4 py-3">الوظيفة</th>
                                <th class="px-4 py-3">القسم</th>
                                <th class="px-4 py-3">الراتب الأساسي</th>
                                <th class="px-4 py-3">المستحق</th>
                                <th class="px-4 py-3">المدفوع</th>
                                <th class="px-4 py-3">المتبقي</th>
                                <th class="px-4 py-3">الحالة</th>
                                <th class="px-4 py-3">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in employee_salaries"
                                :key="row.id"
                                class="border-t border-border/70 transition-colors hover:bg-primary/5"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-bold text-foreground">
                                        {{ row.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ labelFor(row.employee_type) }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-foreground">
                                    {{ row.job_title ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-foreground">
                                    {{ row.department ?? '-' }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-foreground tabular-nums"
                                >
                                    {{ formatMoney(row.base_salary) }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono font-bold text-foreground tabular-nums"
                                >
                                    {{ formatMoney(row.due_amount) }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-primary tabular-nums"
                                >
                                    {{ formatMoney(row.paid_amount) }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-amber-700 tabular-nums"
                                >
                                    {{ formatMoney(row.remaining_amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ring-1"
                                        :class="statusClass(row.status)"
                                    >
                                        {{ labelFor(row.status) }}
                                    </span>
                                    <p
                                        v-if="row.payments_count > 0"
                                        class="mt-1 text-[11px] text-muted-foreground"
                                    >
                                        تم تسجيل دفعة الشهر
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <Button
                                        v-if="
                                            can('salaries.pay') &&
                                            row.can_pay &&
                                            row.remaining_amount > 0
                                        "
                                        size="sm"
                                        class="h-9 rounded-lg bg-primary text-primary-foreground hover:bg-primary/90"
                                        @click="openEmployeePayment(row)"
                                    >
                                        <HandCoins class="size-4" />
                                        تسديد كامل
                                    </Button>
                                    <span
                                        v-else
                                        class="text-xs font-medium text-muted-foreground"
                                        >لا يوجد إجراء</span
                                    >
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
                        يعرض طريقة الأجر، إيرادات الزيارات، الخصومات، وحالة
                        التسديد.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1320px] text-right text-sm">
                        <thead
                            class="bg-muted/70 text-[11px] font-bold text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">الطبيب</th>
                                <th class="px-4 py-3">نوع الأجر</th>
                                <th class="px-4 py-3">إجمالي المواعيد</th>
                                <th class="px-4 py-3">الخصومات</th>
                                <th class="px-4 py-3">المستحق</th>
                                <th class="px-4 py-3">المدفوع</th>
                                <th class="px-4 py-3">المتبقي</th>
                                <th class="px-4 py-3">الحالة</th>
                                <th class="px-4 py-3">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in doctor_dues"
                                :key="row.id"
                                class="border-t border-border/70 transition-colors hover:bg-primary/5"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-bold text-foreground">
                                        {{ row.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.department ?? '-' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-foreground">
                                    {{ doctorCompensationDisplay(row) }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-foreground tabular-nums"
                                >
                                    {{ formatMoney(row.visits_total_amount) }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-foreground tabular-nums"
                                >
                                    {{ formatMoney(row.deductions_amount) }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono font-bold text-foreground tabular-nums"
                                >
                                    {{ formatMoney(row.due_amount) }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-primary tabular-nums"
                                >
                                    {{ formatMoney(row.paid_amount) }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-amber-700 tabular-nums"
                                >
                                    {{ formatMoney(row.remaining_amount) }}
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
                                    <Button
                                        v-if="
                                            can('salaries.pay') &&
                                            row.remaining_amount > 0
                                        "
                                        size="sm"
                                        class="h-9 rounded-lg bg-primary text-primary-foreground hover:bg-primary/90"
                                        @click="openDoctorPayment(row)"
                                    >
                                        <CircleDollarSign class="size-4" />
                                        تسجيل دفعة
                                    </Button>
                                    <span
                                        v-else
                                        class="text-xs font-medium text-muted-foreground"
                                        >لا يوجد إجراء</span
                                    >
                                </td>
                            </tr>
                            <tr v-if="doctor_dues.length === 0">
                                <td
                                    colspan="9"
                                    class="px-4 py-12 text-center text-muted-foreground"
                                >
                                    لا توجد مستحقات أطباء ضمن الفلاتر الحالية.
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
                                :readonly="paymentKind === 'employee'"
                                :class="{
                                    'bg-muted/70': paymentKind === 'employee',
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
