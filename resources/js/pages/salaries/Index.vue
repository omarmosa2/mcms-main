<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Banknote, CalendarDays, CircleDollarSign, HandCoins } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PayrollController from '@/actions/App/Http/Controllers/Payroll/PayrollController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';

type DepartmentOption = { id: number; name: string };
type EmployeeSalaryRow = {
    employee_id: number;
    name: string;
    employee_type: string;
    job_title: string;
    department: string | null;
    base_salary: number;
    period_month: string;
    status: 'unpaid' | 'partially_paid' | 'paid';
    amount_paid: number;
    amount_remaining: number;
    paid_at: string | null;
};
type DoctorDueRow = {
    doctor_profile_id: number;
    name: string;
    department: string | null;
    compensation_type: string | null;
    compensation_value: number;
    visits_count: number;
    consultation_revenue: number;
    procedure_revenue: number;
    deductions: number;
    amount_due: number;
    amount_paid: number;
    amount_remaining: number;
    status: 'unpaid' | 'partially_paid' | 'paid';
    period_start: string;
    period_end: string;
};

const props = defineProps<{
    employee_salaries: EmployeeSalaryRow[];
    doctor_dues: DoctorDueRow[];
    summaries: {
        employee_due: number;
        employee_paid: number;
        doctor_due: number;
        doctor_paid: number;
        remaining: number;
        total_monthly_payroll: number;
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
const month = ref(String(props.filters.month ?? new Date().toISOString().slice(0, 7)));
const dateFrom = ref(String(props.filters.date_from ?? ''));
const dateTo = ref(String(props.filters.date_to ?? ''));
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
    weekly: 'أجر أسبوعي ثابت',
    monthly: 'أجر شهري ثابت',
    unpaid: 'غير مدفوع',
    partially_paid: 'مدفوع جزئياً',
    paid: 'مدفوع',
};

const paymentForm = useForm({
    employee_id: 0,
    doctor_profile_id: 0,
    period_month: month.value,
    period_start: String(props.filters.period_start ?? ''),
    period_end: String(props.filters.period_end ?? ''),
    amount_paid: '',
    payment_method: 'cash',
    paid_at: new Date().toISOString().slice(0, 10),
    notes: '',
});

const formatMoney = (value: number): string => new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 0 }).format(value);
const labelFor = (value: string | null): string => (value !== null ? labels[value] ?? value : '-');
const statusClass = (value: string): string => {
    if (value === 'paid') {
        return 'bg-emerald-50 text-emerald-700';
    }
    if (value === 'partially_paid') {
        return 'bg-amber-50 text-amber-700';
    }
    return 'bg-slate-100 text-slate-600';
};

const filteredTabs = computed(() => ({
    employees: personType.value !== 'doctor',
    doctors: personType.value !== 'employee',
}));

const reload = (): void => {
    router.get(
        PayrollController.index.url(),
        {
            month: month.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            person_type: personType.value || undefined,
            status: status.value || undefined,
            department_id: departmentId.value || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

watch([month, dateFrom, dateTo, personType, status, departmentId], () => {
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
    paymentForm.employee_id = row.employee_id;
    paymentForm.period_month = row.period_month;
    paymentForm.amount_paid = String(row.amount_remaining);
    paymentDialogOpen.value = true;
};

const openDoctorPayment = (row: DoctorDueRow): void => {
    paymentKind.value = 'doctor';
    paymentTarget.value = row;
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentForm.doctor_profile_id = row.doctor_profile_id;
    paymentForm.period_start = row.period_start;
    paymentForm.period_end = row.period_end;
    paymentForm.amount_paid = String(row.amount_remaining);
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
</script>

<template>
    <Head title="الرواتب" />

    <div class="mx-auto w-full max-w-[1680px] space-y-6 p-4 md:p-6" dir="rtl">
        <section class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="space-y-2 text-right">
                <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                    <Banknote class="size-4" />
                    الرواتب
                </div>
                <h1 class="text-3xl font-extrabold text-slate-950">إدارة الرواتب والمستحقات</h1>
                <p class="max-w-3xl text-sm text-slate-500">تسديد رواتب الموظفين ومتابعة مستحقات الأطباء المحسوبة حسب نوع الأجر والفترة المالية.</p>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-lg border bg-white p-4"><p class="text-xs text-slate-500">إجمالي رواتب الموظفين المستحقة</p><p class="text-xl font-bold">{{ formatMoney(summaries.employee_due) }}</p></div>
            <div class="rounded-lg border bg-white p-4"><p class="text-xs text-slate-500">إجمالي رواتب الموظفين المدفوعة</p><p class="text-xl font-bold text-emerald-700">{{ formatMoney(summaries.employee_paid) }}</p></div>
            <div class="rounded-lg border bg-white p-4"><p class="text-xs text-slate-500">إجمالي مستحقات الأطباء</p><p class="text-xl font-bold">{{ formatMoney(summaries.doctor_due) }}</p></div>
            <div class="rounded-lg border bg-white p-4"><p class="text-xs text-slate-500">إجمالي المدفوع للأطباء</p><p class="text-xl font-bold text-emerald-700">{{ formatMoney(summaries.doctor_paid) }}</p></div>
            <div class="rounded-lg border bg-white p-4"><p class="text-xs text-slate-500">إجمالي المتبقي</p><p class="text-xl font-bold text-amber-700">{{ formatMoney(summaries.remaining) }}</p></div>
            <div class="rounded-lg border bg-white p-4"><p class="text-xs text-slate-500">مدفوعات هذا الشهر</p><p class="text-xl font-bold">{{ formatMoney(summaries.total_monthly_payroll) }}</p></div>
        </section>

        <section class="rounded-lg border bg-white p-4">
            <div class="grid gap-3 md:grid-cols-6">
                <div class="grid gap-1"><Label>الشهر</Label><Input v-model="month" type="month" class="h-10" /></div>
                <div class="grid gap-1"><Label>من تاريخ</Label><Input v-model="dateFrom" type="date" class="h-10" /></div>
                <div class="grid gap-1"><Label>إلى تاريخ</Label><Input v-model="dateTo" type="date" class="h-10" /></div>
                <div class="grid gap-1"><Label>نوع الشخص</Label><select v-model="personType" class="h-10 rounded-md border px-3 text-sm"><option value="">الكل</option><option value="employee">موظف</option><option value="doctor">طبيب</option></select></div>
                <div class="grid gap-1"><Label>الحالة</Label><select v-model="status" class="h-10 rounded-md border px-3 text-sm"><option value="">كل الحالات</option><option value="unpaid">غير مدفوع</option><option value="partially_paid">مدفوع جزئياً</option><option value="paid">مدفوع</option></select></div>
                <div class="grid gap-1"><Label>القسم / العيادة</Label><select v-model="departmentId" class="h-10 rounded-md border px-3 text-sm"><option value="">الكل</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="inline-flex rounded-lg border bg-white p-1">
                <button v-if="filteredTabs.employees" type="button" class="rounded-md px-4 py-2 text-sm font-bold" :class="activeTab === 'employees' ? 'bg-sky-600 text-white' : 'text-slate-600'" @click="activeTab = 'employees'">رواتب الموظفين</button>
                <button v-if="filteredTabs.doctors" type="button" class="rounded-md px-4 py-2 text-sm font-bold" :class="activeTab === 'doctors' ? 'bg-sky-600 text-white' : 'text-slate-600'" @click="activeTab = 'doctors'">مستحقات الأطباء</button>
            </div>

            <div v-if="activeTab === 'employees'" class="overflow-hidden rounded-lg border bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1020px] text-right text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3">اسم الموظف</th><th class="px-4 py-3">نوع الموظف</th><th class="px-4 py-3">المسمى الوظيفي</th><th class="px-4 py-3">الراتب الأساسي</th><th class="px-4 py-3">الشهر المستحق</th><th class="px-4 py-3">الحالة</th><th class="px-4 py-3">المدفوع</th><th class="px-4 py-3">المتبقي</th><th class="px-4 py-3">تاريخ الدفع</th><th class="px-4 py-3">الإجراءات</th></tr></thead>
                        <tbody>
                            <tr v-for="row in employee_salaries" :key="row.employee_id" class="border-t">
                                <td class="px-4 py-3 font-semibold">{{ row.name }}</td><td class="px-4 py-3">{{ labelFor(row.employee_type) }}</td><td class="px-4 py-3">{{ row.job_title }}</td><td class="px-4 py-3 font-mono">{{ formatMoney(row.base_salary) }}</td><td class="px-4 py-3">{{ row.period_month }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(row.status)">{{ labelFor(row.status) }}</span></td>
                                <td class="px-4 py-3 font-mono">{{ formatMoney(row.amount_paid) }}</td><td class="px-4 py-3 font-mono">{{ formatMoney(row.amount_remaining) }}</td><td class="px-4 py-3">{{ row.paid_at ?? '-' }}</td>
                                <td class="px-4 py-3"><Button v-if="can('salaries.pay') && row.amount_remaining > 0" size="sm" class="bg-emerald-600 text-white hover:bg-emerald-700" @click="openEmployeePayment(row)"><HandCoins class="size-4" />تسديد راتب</Button></td>
                            </tr>
                            <tr v-if="employee_salaries.length === 0"><td colspan="10" class="px-4 py-10 text-center text-slate-500">لا توجد رواتب موظفين ضمن الفلاتر الحالية.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="activeTab === 'doctors'" class="overflow-hidden rounded-lg border bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1240px] text-right text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3">اسم الطبيب</th><th class="px-4 py-3">العيادة</th><th class="px-4 py-3">نوع الأجر</th><th class="px-4 py-3">قيمة الأجر</th><th class="px-4 py-3">الزيارات</th><th class="px-4 py-3">إيرادات المعاينات</th><th class="px-4 py-3">إيرادات الإجراءات</th><th class="px-4 py-3">الخصومات / السلف</th><th class="px-4 py-3">المستحق</th><th class="px-4 py-3">المدفوع</th><th class="px-4 py-3">المتبقي</th><th class="px-4 py-3">الحالة</th><th class="px-4 py-3">الإجراءات</th></tr></thead>
                        <tbody>
                            <tr v-for="row in doctor_dues" :key="row.doctor_profile_id" class="border-t">
                                <td class="px-4 py-3 font-semibold">{{ row.name }}</td><td class="px-4 py-3">{{ row.department ?? '-' }}</td><td class="px-4 py-3">{{ labelFor(row.compensation_type) }}</td><td class="px-4 py-3 font-mono">{{ formatMoney(row.compensation_value) }}</td><td class="px-4 py-3">{{ row.visits_count }}</td><td class="px-4 py-3 font-mono">{{ formatMoney(row.consultation_revenue) }}</td><td class="px-4 py-3 font-mono">{{ formatMoney(row.procedure_revenue) }}</td><td class="px-4 py-3 font-mono">{{ formatMoney(row.deductions) }}</td><td class="px-4 py-3 font-mono font-bold">{{ formatMoney(row.amount_due) }}</td><td class="px-4 py-3 font-mono">{{ formatMoney(row.amount_paid) }}</td><td class="px-4 py-3 font-mono">{{ formatMoney(row.amount_remaining) }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(row.status)">{{ labelFor(row.status) }}</span></td>
                                <td class="px-4 py-3"><Button v-if="can('salaries.pay') && row.amount_remaining > 0" size="sm" class="bg-emerald-600 text-white hover:bg-emerald-700" @click="openDoctorPayment(row)"><CircleDollarSign class="size-4" />تسديد مستحقات</Button></td>
                            </tr>
                            <tr v-if="doctor_dues.length === 0"><td colspan="13" class="px-4 py-10 text-center text-slate-500">لا توجد مستحقات أطباء ضمن الفلاتر الحالية.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <Dialog :open="paymentDialogOpen" @update:open="paymentDialogOpen = $event">
            <DialogContent class="max-w-2xl rounded-lg bg-white" dir="rtl">
                <DialogHeader class="text-right"><DialogTitle>{{ paymentKind === 'employee' ? 'تسديد راتب' : 'تسديد مستحقات' }}</DialogTitle></DialogHeader>
                <div v-if="paymentTarget" class="space-y-4">
                    <div class="rounded-lg border bg-slate-50 p-4 text-sm">
                        <p class="font-bold">{{ paymentTarget.name }}</p>
                        <p class="text-slate-500">المبلغ المستحق: {{ formatMoney(paymentKind === 'employee' ? (paymentTarget as EmployeeSalaryRow).base_salary : (paymentTarget as DoctorDueRow).amount_due) }} | المتبقي: {{ formatMoney(paymentTarget.amount_remaining) }}</p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2"><Label>{{ paymentKind === 'employee' ? 'الشهر' : 'الفترة المالية' }}</Label><Input :model-value="paymentKind === 'employee' ? paymentForm.period_month : `${paymentForm.period_start} - ${paymentForm.period_end}`" readonly /></div>
                        <div class="grid gap-2"><Label>المبلغ المراد دفعه</Label><Input v-model="paymentForm.amount_paid" type="number" min="0.01" step="0.01" /><InputError :message="paymentForm.errors.amount_paid" /></div>
                        <div class="grid gap-2"><Label>طريقة الدفع</Label><select v-model="paymentForm.payment_method" class="h-10 rounded-md border px-3"><option value="cash">نقداً</option><option value="bank_transfer">حوالة بنكية</option><option value="card">بطاقة</option></select></div>
                        <div class="grid gap-2"><Label>تاريخ الدفع</Label><Input v-model="paymentForm.paid_at" type="date" /><InputError :message="paymentForm.errors.paid_at" /></div>
                        <div class="grid gap-2 md:col-span-2"><Label>ملاحظات</Label><Input v-model="paymentForm.notes" /></div>
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="paymentDialogOpen = false">إلغاء</Button>
                    <Button type="button" class="bg-emerald-600 text-white hover:bg-emerald-700" :disabled="paymentForm.processing" @click="submitPayment"><CalendarDays class="size-4" />حفظ الدفعة</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
