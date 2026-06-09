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
    status: 'unpaid' | 'partially_paid' | 'paid';
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
    status: 'unpaid' | 'partially_paid' | 'paid';
};

const props = defineProps<{
    employee_salaries: EmployeeSalaryRow[];
    doctor_dues: DoctorDueRow[];
    summaries: {
        employee_due: number;
        employee_paid: number;
        employee_remaining: number;
        doctor_due: number;
        doctor_paid: number;
        doctor_remaining: number;
        total_due: number;
        total_paid: number;
        total_remaining: number;
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
    partially_paid: 'مدفوع جزئياً',
    paid: 'مدفوع بالكامل',
};

const paymentForm = useForm({
    employee_monthly_salary_id: 0,
    doctor_monthly_due_id: 0,
    amount: '',
    payment_method: 'cash',
    payment_date: new Date().toISOString().slice(0, 10),
    notes: '',
});

const formatMoney = (value: number): string => new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 0 }).format(value);
const labelFor = (value: string | null): string => (value !== null ? labels[value] ?? value : '-');
const statusClass = (value: string): string => {
    if (value === 'paid') {
        return 'bg-success/10 text-success';
    }

    if (value === 'partially_paid') {
        return 'bg-warning/10 text-warning';
    }

    return 'bg-muted text-muted-foreground';
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
    paymentForm.amount = String(row.remaining_amount);
    paymentDialogOpen.value = true;
};

const openDoctorPayment = (row: DoctorDueRow): void => {
    paymentKind.value = 'doctor';
    paymentTarget.value = row;
    paymentForm.reset();
    paymentForm.clearErrors();
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
</script>

<template>
    <Head title="الرواتب" />

    <div class="mx-auto w-full max-w-[1680px] space-y-6 p-4 md:p-6" dir="rtl">
        <section class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="space-y-2 text-right">
                <div class="inline-flex items-center gap-2 rounded-full bg-success/10 px-3 py-1 text-xs font-semibold text-success">
                    <Banknote class="size-4" />
                    الرواتب
                </div>
                <h1 class="text-3xl font-extrabold text-foreground">إدارة الرواتب والمستحقات الشهرية</h1>
                <p class="max-w-3xl text-sm text-muted-foreground">تسديد رواتب الموظفين ومستحقات الأطباء بشكل شهري مستقل مع تتبع الدفعات.</p>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-3 xl:grid-cols-3">
            <div class="rounded-lg border bg-card p-4 space-y-2">
                <p class="text-sm font-bold text-foreground">رواتب الموظفين</p>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div><p class="text-[10px] text-muted-foreground">المستحقة</p><p class="text-sm font-bold text-foreground">{{ formatMoney(summaries.employee_due) }}</p></div>
                    <div><p class="text-[10px] text-muted-foreground">المدفوعة</p><p class="text-sm font-bold text-success">{{ formatMoney(summaries.employee_paid) }}</p></div>
                    <div><p class="text-[10px] text-muted-foreground">المتبقية</p><p class="text-sm font-bold text-warning">{{ formatMoney(summaries.employee_remaining) }}</p></div>
                </div>
            </div>
            <div class="rounded-lg border bg-card p-4 space-y-2">
                <p class="text-sm font-bold text-foreground">مستحقات الأطباء</p>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div><p class="text-[10px] text-muted-foreground">المستحقة</p><p class="text-sm font-bold text-foreground">{{ formatMoney(summaries.doctor_due) }}</p></div>
                    <div><p class="text-[10px] text-muted-foreground">المدفوعة</p><p class="text-sm font-bold text-success">{{ formatMoney(summaries.doctor_paid) }}</p></div>
                    <div><p class="text-[10px] text-muted-foreground">المتبقية</p><p class="text-sm font-bold text-warning">{{ formatMoney(summaries.doctor_remaining) }}</p></div>
                </div>
            </div>
            <div class="rounded-lg border bg-card p-4 space-y-2">
                <p class="text-sm font-bold text-foreground">الإجمالي</p>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div><p class="text-[10px] text-muted-foreground">المستحقة</p><p class="text-sm font-bold text-foreground">{{ formatMoney(summaries.total_due) }}</p></div>
                    <div><p class="text-[10px] text-muted-foreground">المدفوعة</p><p class="text-sm font-bold text-success">{{ formatMoney(summaries.total_paid) }}</p></div>
                    <div><p class="text-[10px] text-muted-foreground">المتبقية</p><p class="text-sm font-bold text-warning">{{ formatMoney(summaries.total_remaining) }}</p></div>
                </div>
            </div>
        </section>

        <section class="rounded-lg border bg-card p-4">
            <div class="grid gap-3 md:grid-cols-4">
                <div class="grid gap-1"><Label>الشهر المالي</Label><Input v-model="month" type="month" class="h-10" /></div>
                <div class="grid gap-1"><Label>نوع الشخص</Label><select v-model="personType" class="h-10 rounded-md border border-input bg-muted px-3 text-sm"><option value="">الكل</option><option value="employee">موظف</option><option value="doctor">طبيب</option></select></div>
                <div class="grid gap-1"><Label>الحالة</Label><select v-model="status" class="h-10 rounded-md border border-input bg-muted px-3 text-sm"><option value="">كل الحالات</option><option value="unpaid">غير مدفوع</option><option value="partially_paid">مدفوع جزئياً</option><option value="paid">مدفوع بالكامل</option></select></div>
                <div class="grid gap-1"><Label>القسم / العيادة</Label><select v-model="departmentId" class="h-10 rounded-md border border-input bg-muted px-3 text-sm"><option value="">الكل</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="inline-flex rounded-lg border bg-card p-1">
                <button v-if="filteredTabs.employees" type="button" class="rounded-md px-4 py-2 text-sm font-bold" :class="activeTab === 'employees' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="activeTab = 'employees'">رواتب الموظفين</button>
                <button v-if="filteredTabs.doctors" type="button" class="rounded-md px-4 py-2 text-sm font-bold" :class="activeTab === 'doctors' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="activeTab = 'doctors'">مستحقات الأطباء</button>
            </div>

            <div v-if="activeTab === 'employees'" class="overflow-hidden rounded-lg border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1020px] text-right text-sm">
                        <thead class="bg-muted text-xs text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">اسم الموظف</th>
                                <th class="px-4 py-3">نوع الموظف</th>
                                <th class="px-4 py-3">المسمى الوظيفي</th>
                                <th class="px-4 py-3">الراتب الأساسي</th>
                                <th class="px-4 py-3">المستحق</th>
                                <th class="px-4 py-3">المدفوع</th>
                                <th class="px-4 py-3">المتبقي</th>
                                <th class="px-4 py-3">الحالة</th>
                                <th class="px-4 py-3">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in employee_salaries" :key="row.id" class="border-t">
                                <td class="px-4 py-3 font-semibold text-foreground">{{ row.name }}</td>
                                <td class="px-4 py-3 text-foreground">{{ labelFor(row.employee_type) }}</td>
                                <td class="px-4 py-3 text-foreground">{{ row.job_title }}</td>
                                <td class="px-4 py-3 font-mono text-foreground">{{ formatMoney(row.base_salary) }}</td>
                                <td class="px-4 py-3 font-mono text-foreground">{{ formatMoney(row.due_amount) }}</td>
                                <td class="px-4 py-3 font-mono text-success">{{ formatMoney(row.paid_amount) }}</td>
                                <td class="px-4 py-3 font-mono text-warning">{{ formatMoney(row.remaining_amount) }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(row.status)">{{ labelFor(row.status) }}</span></td>
                                <td class="px-4 py-3">
                                    <Button v-if="can('salaries.pay') && row.remaining_amount > 0" size="sm" class="bg-success text-success-foreground hover:bg-success/90" @click="openEmployeePayment(row)">
                                        <HandCoins class="size-4" />تسديد راتب
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="employee_salaries.length === 0"><td colspan="9" class="px-4 py-10 text-center text-muted-foreground">لا توجد رواتب موظفين لهذا الشهر.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="activeTab === 'doctors'" class="overflow-hidden rounded-lg border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1240px] text-right text-sm">
                        <thead class="bg-muted text-xs text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3">اسم الطبيب</th>
                                <th class="px-4 py-3">العيادة</th>
                                <th class="px-4 py-3">نوع الأجر</th>
                                <th class="px-4 py-3">إجمالي المواعيد</th>
                                <th class="px-4 py-3">الخصومات</th>
                                <th class="px-4 py-3">المستحق</th>
                                <th class="px-4 py-3">المدفوع</th>
                                <th class="px-4 py-3">المتبقي</th>
                                <th class="px-4 py-3">الحالة</th>
                                <th class="px-4 py-3">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in doctor_dues" :key="row.id" class="border-t">
                                <td class="px-4 py-3 font-semibold text-foreground">{{ row.name }}</td>
                                <td class="px-4 py-3 text-foreground">{{ row.department ?? '-' }}</td>
                                <td class="px-4 py-3 text-foreground">{{ doctorCompensationDisplay(row) }}</td>
                                <td class="px-4 py-3 font-mono text-foreground">{{ formatMoney(row.visits_total_amount) }}</td>
                                <td class="px-4 py-3 font-mono text-foreground">{{ formatMoney(row.deductions_amount) }}</td>
                                <td class="px-4 py-3 font-mono font-bold text-foreground">{{ formatMoney(row.due_amount) }}</td>
                                <td class="px-4 py-3 font-mono text-success">{{ formatMoney(row.paid_amount) }}</td>
                                <td class="px-4 py-3 font-mono text-warning">{{ formatMoney(row.remaining_amount) }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(row.status)">{{ labelFor(row.status) }}</span></td>
                                <td class="px-4 py-3">
                                    <Button v-if="can('salaries.pay') && row.remaining_amount > 0" size="sm" class="bg-success text-success-foreground hover:bg-success/90" @click="openDoctorPayment(row)">
                                        <CircleDollarSign class="size-4" />تسديد مستحقات
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="doctor_dues.length === 0"><td colspan="10" class="px-4 py-10 text-center text-muted-foreground">لا توجد مستحقات أطباء لهذا الشهر.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <Dialog :open="paymentDialogOpen" @update:open="paymentDialogOpen = $event">
            <DialogContent class="max-w-2xl rounded-lg bg-card" dir="rtl">
                <DialogHeader class="text-right"><DialogTitle class="text-foreground">{{ paymentKind === 'employee' ? 'تسديد راتب موظف' : 'تسديد مستحقات طبيب' }}</DialogTitle></DialogHeader>
                <div v-if="paymentTarget" class="space-y-4">
                    <div class="rounded-lg border bg-muted p-4 text-sm">
                        <p class="font-bold text-foreground">{{ paymentTarget.name }}</p>
                        <p class="text-muted-foreground">
                            الشهر: {{ (paymentTarget as EmployeeSalaryRow).salary_month }} |
                            المستحق: {{ formatMoney(paymentKind === 'employee' ? (paymentTarget as EmployeeSalaryRow).due_amount : (paymentTarget as DoctorDueRow).due_amount) }} |
                            المتبقي: {{ formatMoney(paymentTarget.remaining_amount) }}
                        </p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>المبلغ المراد دفعه</Label>
                            <Input v-model="paymentForm.amount" type="number" min="0.01" step="0.01" />
                            <InputError :message="paymentForm.errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label>طريقة الدفع</Label>
                            <select v-model="paymentForm.payment_method" class="h-10 rounded-md border border-input bg-muted px-3">
                                <option value="cash">نقداً</option>
                                <option value="bank_transfer">حوالة بنكية</option>
                                <option value="card">بطاقة</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label>تاريخ الدفع</Label>
                            <Input v-model="paymentForm.payment_date" type="date" />
                            <InputError :message="paymentForm.errors.payment_date" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label>ملاحظات</Label>
                            <Input v-model="paymentForm.notes" />
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="paymentDialogOpen = false">إلغاء</Button>
                    <Button type="button" class="bg-success text-success-foreground hover:bg-success/90" :disabled="paymentForm.processing" @click="submitPayment">
                        <CalendarDays class="size-4" />حفظ الدفعة
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
