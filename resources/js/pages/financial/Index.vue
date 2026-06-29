<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Banknote,
    FileSpreadsheet,
    FileText,
    Filter,
    Plus,
    Printer,
    Receipt,
    Stethoscope,
    Trash2,
    ArrowUpRight,
    UsersRound,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Chart from '@/components/Chart.vue';
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
import { useMoneyFormatter } from '@/lib/money';
import { index as financialIndex } from '@/routes/financial/index';
import FinancialStatsCards from './components/FinancialStatsCards.vue';

type FinancialRow = {
    appointment_id: number;
    clinic_name: string;
    patient_name: string;
    file_number: number | null;
    doctor_name: string;
    appointment_type: string;
    cost: number;
    paid_amount: number;
    remaining_amount: number;
    payment_status: 'unpaid' | 'partially_paid' | 'paid';
    appointment_date: string | null;
    payment_method: string | null;
    created_by_name: string;
};

type ExpenseRow = {
    id: number;
    expense_date: string | null;
    category_name: string;
    description: string;
    amount: number;
    payment_method: string | null;
    user_name: string;
    clinic_name: string;
    notes: string | null;
};

type DoctorEntitlementRow = {
    id: number;
    doctor_name: string;
    clinic_name: string;
    payment_type: string;
    due_amount: number;
    paid_amount: number;
    remaining_amount: number;
    status: string;
};

type EmployeeSalaryRow = {
    id: number;
    employee_name: string;
    base_salary: number;
    due_amount: number;
    paid_amount: number;
    remaining_amount: number;
    status: string;
};

type DoctorOption = { id: number; name: string };
type PatientOption = { id: number; full_name: string };
type ClinicOption = { id: number; name: string };
type CategoryOption = { id: number; name: string };

const props = defineProps<{
    financial_rows: FinancialRow[];
    expense_rows: ExpenseRow[];
    doctor_entitlements: DoctorEntitlementRow[];
    employee_salaries: EmployeeSalaryRow[];
    summaries: {
        total_income: number;
        total_collected: number;
        total_remaining: number;
        doctor_due: number;
        doctor_paid: number;
        employee_salaries: number;
        total_expenses: number;
        net_profit: number;
        net_liquidity: number;
        total_outflow: number;
        paid_count: number;
        unpaid_count: number;
        partially_paid_count: number;
    };
    chart_data: {
        daily_income: { date: string; amount: number }[];
        income_by_clinic: { clinic_name: string; amount: number }[];
        income_by_doctor: { doctor_name: string; amount: number }[];
        expenses_by_category: { category_name: string; amount: number }[];
        monthly_profit: { month: string; income: number; outflow: number; profit: number }[];
    };
    filters: Record<string, string | number | null>;
    clinics: ClinicOption[];
    doctors: DoctorOption[];
    patients: PatientOption[];
    expense_categories: CategoryOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'المالية', href: financialIndex() }],
    },
});

const { can } = usePermissions();
const toast = useToast();
const { formatMoney } = useMoneyFormatter();

const activeTab = ref<'income' | 'expenses' | 'doctors' | 'employees'>('income');
const expenseDialogOpen = ref(false);
const editingExpense = ref<ExpenseRow | null>(null);
const deleteConfirmOpen = ref(false);
const deletingExpenseId = ref<number | null>(null);

const month = ref(String(props.filters.month ?? new Date().toISOString().slice(0, 7)));
const dateFrom = ref(String(props.filters.date_from ?? ''));
const dateTo = ref(String(props.filters.date_to ?? ''));
const status = ref(String(props.filters.status ?? ''));
const clinicId = ref(String(props.filters.clinic_id ?? ''));
const appointmentType = ref(String(props.filters.appointment_type ?? ''));
const doctorId = ref(String(props.filters.doctor_id ?? ''));
const patientId = ref(String(props.filters.patient_id ?? ''));
const paymentMethod = ref(String(props.filters.payment_method ?? ''));
const transactionType = ref(String(props.filters.transaction_type ?? ''));
const expenseCategoryId = ref(String(props.filters.expense_category_id ?? ''));

const expenseForm = ref({
    category_id: null as number | null,
    description: '',
    amount: '',
    expense_date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash',
    notes: '',
});

const labels: Record<string, string> = {
    first_visit: 'كشفية أولى',
    review: 'مراجعة',
    unpaid: 'غير مدفوع',
    partially_paid: 'مدفوع جزئياً',
    paid: 'مدفوع',
    cash: 'نقداً',
    card: 'بطاقة',
    bank_transfer: 'حوالة بنكية',
    insurance: 'تأمين',
    online: 'إلكتروني',
    percentage: 'نسبة',
    fixed_weekly: 'أسبوعي',
    fixed_monthly: 'شهري',
};

const labelFor = (value: string | null): string =>
    value !== null ? (labels[value] ?? value) : '-';

const statusClass = (value: string): string => {
    if (value === 'paid') return 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/25';
    if (value === 'partially_paid') return 'bg-amber-500/10 text-amber-700 ring-amber-500/20';
    return 'bg-rose-500/10 text-rose-700 ring-rose-500/20';
};

const reload = (): void => {
    router.get(
        financialIndex(),
        {
            month: month.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            status: status.value || undefined,
            clinic_id: clinicId.value || undefined,
            appointment_type: appointmentType.value || undefined,
            doctor_id: doctorId.value || undefined,
            patient_id: patientId.value || undefined,
            payment_method: paymentMethod.value || undefined,
            transaction_type: transactionType.value || undefined,
            expense_category_id: expenseCategoryId.value || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

watch(
    [month, dateFrom, dateTo, status, clinicId, appointmentType, doctorId, patientId, paymentMethod, transactionType, expenseCategoryId],
    () => { reload(); },
);

const openAddExpense = (): void => {
    editingExpense.value = null;
    expenseForm.value = {
        category_id: null,
        description: '',
        amount: '',
        expense_date: new Date().toISOString().slice(0, 10),
        payment_method: 'cash',
        notes: '',
    };
    expenseDialogOpen.value = true;
};

const openEditExpense = (row: ExpenseRow): void => {
    editingExpense.value = row;
    expenseForm.value = {
        category_id: null,
        description: row.description,
        amount: String(row.amount),
        expense_date: row.expense_date ?? new Date().toISOString().slice(0, 10),
        payment_method: row.payment_method ?? 'cash',
        notes: row.notes ?? '',
    };
    expenseDialogOpen.value = true;
};

const submitExpense = (): void => {
    const data = {
        description: expenseForm.value.description,
        amount: expenseForm.value.amount,
        expense_date: expenseForm.value.expense_date,
        payment_method: expenseForm.value.payment_method,
        notes: expenseForm.value.notes || null,
        category_id: expenseForm.value.category_id,
    };

    if (editingExpense.value) {
        router.put(`/financial/expenses/${editingExpense.value.id}`, data, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                expenseDialogOpen.value = false;
                toast.success('تم تعديل المصروف بنجاح');
            },
            onError: () => toast.error('تعذر تعديل المصروف'),
        });
    } else {
        router.post('/financial/expenses', data, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                expenseDialogOpen.value = false;
                toast.success('تم تسجيل المصروف بنجاح');
            },
            onError: () => toast.error('تعذر تسجيل المصروف'),
        });
    }
};

const confirmDeleteExpense = (id: number): void => {
    deletingExpenseId.value = id;
    deleteConfirmOpen.value = true;
};

const doDeleteExpense = (): void => {
    if (!deletingExpenseId.value) return;
    router.delete(`/financial/expenses/${deletingExpenseId.value}`, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            deleteConfirmOpen.value = false;
            deletingExpenseId.value = null;
            toast.success('تم حذف المصروف بنجاح');
        },
        onError: () => toast.error('تعذر حذف المصروف'),
    });
};

const dailyIncomeChartData = computed(() => {
    const data = props.chart_data.daily_income;
    return {
        labels: data.map((d) => d.date),
        datasets: [
            {
                label: 'الدخل اليومي',
                data: data.map((d) => d.amount),
                backgroundColor: 'rgba(16, 185, 129, 0.5)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 2,
            },
        ],
    };
});

const clinicIncomeChartData = computed(() => {
    const data = props.chart_data.income_by_clinic;
    return {
        labels: data.map((d) => d.clinic_name),
        datasets: [
            {
                label: 'الدخل حسب العيادة',
                data: data.map((d) => d.amount),
                backgroundColor: ['rgba(59, 130, 246, 0.5)', 'rgba(168, 85, 247, 0.5)', 'rgba(245, 158, 11, 0.5)', 'rgba(239, 68, 68, 0.5)', 'rgba(20, 184, 166, 0.5)'],
                borderColor: ['rgba(59, 130, 246, 1)', 'rgba(168, 85, 247, 1)', 'rgba(245, 158, 11, 1)', 'rgba(239, 68, 68, 1)', 'rgba(20, 184, 166, 1)'],
                borderWidth: 1,
            },
        ],
    };
});

const doctorIncomeChartData = computed(() => {
    const data = props.chart_data.income_by_doctor;
    return {
        labels: data.map((d) => d.doctor_name),
        datasets: [
            {
                label: 'الدخل حسب الطبيب',
                data: data.map((d) => d.amount),
                backgroundColor: 'rgba(139, 92, 246, 0.5)',
                borderColor: 'rgba(139, 92, 246, 1)',
                borderWidth: 2,
            },
        ],
    };
});

const expenseCategoryChartData = computed(() => {
    const data = props.chart_data.expenses_by_category;
    return {
        labels: data.map((d) => d.category_name),
        datasets: [
            {
                label: 'المصروفات حسب النوع',
                data: data.map((d) => d.amount),
                backgroundColor: ['rgba(239, 68, 68, 0.5)', 'rgba(245, 158, 11, 0.5)', 'rgba(59, 130, 246, 0.5)', 'rgba(168, 85, 247, 0.5)', 'rgba(20, 184, 166, 0.5)'],
                borderColor: ['rgba(239, 68, 68, 1)', 'rgba(245, 158, 11, 1)', 'rgba(59, 130, 246, 1)', 'rgba(168, 85, 247, 1)', 'rgba(20, 184, 166, 1)'],
                borderWidth: 1,
            },
        ],
    };
});

const monthlyProfitChartData = computed(() => {
    const data = props.chart_data.monthly_profit;
    return {
        labels: data.map((d) => d.month),
        datasets: [
            {
                label: 'الدخل',
                data: data.map((d) => d.income),
                backgroundColor: 'rgba(16, 185, 129, 0.5)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 2,
            },
            {
                label: 'الخرج',
                data: data.map((d) => d.outflow),
                backgroundColor: 'rgba(239, 68, 68, 0.5)',
                borderColor: 'rgba(239, 68, 68, 1)',
                borderWidth: 2,
            },
            {
                label: 'صافي الربح',
                data: data.map((d) => d.profit),
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 2,
            },
        ],
    };
});

const exportExcel = (): void => {
    window.open(financialIndex({ query: { export: 'excel' } }).url, '_blank');
};

const exportPdf = (): void => {
    window.print();
};
</script>

<template>
    <div class="mx-auto w-full max-w-[1680px] space-y-6 p-4 md:p-6" dir="rtl">
        <section class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="space-y-2 text-right">
                <div class="inline-flex items-center gap-2 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-700">
                    <Banknote class="size-4" />
                    المالية
                </div>
                <h1 class="text-3xl font-extrabold text-foreground">إدارة الدخل والخرج المالي</h1>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    المركز الرئيسي لمتابعة الوضع المالي الكامل للمجمع: الدخل، المقبوض، المتبقي، المستحقات، المصروفات، وصافي الربح.
                </p>
            </div>
            <div class="flex gap-2">
                <Button v-if="can('expenses.create')" variant="outline" size="sm" @click="openAddExpense">
                    <Plus class="me-1 size-4" />
                    إضافة مصروف
                </Button>
                <Button variant="outline" size="sm" @click="exportPdf">
                    <Printer class="me-1 size-4" />
                    طباعة
                </Button>
                <Button variant="outline" size="sm" @click="exportExcel">
                    <FileSpreadsheet class="me-1 size-4" />
                    Excel
                </Button>
            </div>
        </section>

        <FinancialStatsCards :summaries="summaries" />

        <section class="rounded-[1.2rem] border border-border bg-card/95 p-5 shadow-card">
            <div class="mb-4 flex items-center gap-2 text-sm font-bold text-foreground">
                <Filter class="size-4 text-primary" />
                <span>الفلاتر</span>
            </div>
            <div class="grid gap-3 md:grid-cols-4 lg:grid-cols-6">
                <div class="grid gap-1.5">
                    <Label>الشهر المالي</Label>
                    <Input v-model="month" type="month" class="h-10" />
                </div>
                <div class="grid gap-1.5">
                    <Label>من تاريخ</Label>
                    <Input v-model="dateFrom" type="date" class="h-10" />
                </div>
                <div class="grid gap-1.5">
                    <Label>إلى تاريخ</Label>
                    <Input v-model="dateTo" type="date" class="h-10" />
                </div>
                <div class="grid gap-1.5">
                    <Label>العيادة</Label>
                    <select v-model="clinicId" class="h-10 rounded-md border border-input bg-muted px-3 text-sm">
                        <option value="">كل العيادات</option>
                        <option v-for="c in clinics" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>نوع العملية</Label>
                    <select v-model="transactionType" class="h-10 rounded-md border border-input bg-muted px-3 text-sm">
                        <option value="">الكل</option>
                        <option value="income">دخل</option>
                        <option value="expense">خرج</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>الطبيب</Label>
                    <select v-model="doctorId" class="h-10 rounded-md border border-input bg-muted px-3 text-sm">
                        <option value="">الكل</option>
                        <option v-for="d in doctors" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>المريض</Label>
                    <select v-model="patientId" class="h-10 rounded-md border border-input bg-muted px-3 text-sm">
                        <option value="">الكل</option>
                        <option v-for="p in patients" :key="p.id" :value="p.id">{{ p.full_name }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>حالة الدفع</Label>
                    <select v-model="status" class="h-10 rounded-md border border-input bg-muted px-3 text-sm">
                        <option value="">كل الحالات</option>
                        <option value="unpaid">غير مدفوع</option>
                        <option value="partially_paid">مدفوع جزئياً</option>
                        <option value="paid">مدفوع</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>نوع الموعد</Label>
                    <select v-model="appointmentType" class="h-10 rounded-md border border-input bg-muted px-3 text-sm">
                        <option value="">الكل</option>
                        <option value="first_visit">كشفية أولى</option>
                        <option value="review">مراجعة</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>طريقة الدفع</Label>
                    <select v-model="paymentMethod" class="h-10 rounded-md border border-input bg-muted px-3 text-sm">
                        <option value="">الكل</option>
                        <option value="cash">نقداً</option>
                        <option value="card">بطاقة</option>
                        <option value="bank_transfer">حوالة بنكية</option>
                        <option value="insurance">تأمين</option>
                        <option value="online">إلكتروني</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>تصنيف المصروف</Label>
                    <select v-model="expenseCategoryId" class="h-10 rounded-md border border-input bg-muted px-3 text-sm">
                        <option value="">الكل</option>
                        <option v-for="cat in expense_categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-[1.2rem] border border-border bg-card/95 p-4 shadow-card">
                <h3 class="mb-3 text-sm font-bold text-foreground">الدخل اليومي</h3>
                <Chart type="bar" :labels="dailyIncomeChartData.labels" :datasets="dailyIncomeChartData.datasets" />
            </div>
            <div class="rounded-[1.2rem] border border-border bg-card/95 p-4 shadow-card">
                <h3 class="mb-3 text-sm font-bold text-foreground">الدخل حسب العيادة</h3>
                <Chart type="bar" :labels="clinicIncomeChartData.labels" :datasets="clinicIncomeChartData.datasets" />
            </div>
            <div class="rounded-[1.2rem] border border-border bg-card/95 p-4 shadow-card">
                <h3 class="mb-3 text-sm font-bold text-foreground">الدخل حسب الطبيب</h3>
                <Chart type="bar" :labels="doctorIncomeChartData.labels" :datasets="doctorIncomeChartData.datasets" />
            </div>
            <div class="rounded-[1.2rem] border border-border bg-card/95 p-4 shadow-card">
                <h3 class="mb-3 text-sm font-bold text-foreground">المصروفات حسب النوع</h3>
                <Chart type="bar" :labels="expenseCategoryChartData.labels" :datasets="expenseCategoryChartData.datasets" />
            </div>
            <div class="rounded-[1.2rem] border border-border bg-card/95 p-4 shadow-card lg:col-span-2">
                <h3 class="mb-3 text-sm font-bold text-foreground">صافي الربح شهرياً (آخر 6 أشهر)</h3>
                <Chart type="line" :labels="monthlyProfitChartData.labels" :datasets="monthlyProfitChartData.datasets" />
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex flex-col gap-3 rounded-[1.2rem] border border-border bg-card/95 p-3 shadow-card lg:flex-row lg:items-center lg:justify-between">
                <div class="inline-flex w-fit rounded-xl border border-border bg-muted/70 p-1">
                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg px-4 text-sm font-bold transition-colors"
                        :class="activeTab === 'income' ? 'bg-emerald-600 text-white shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'income'"
                    >
                        <ArrowUpRight class="size-4" />
                        الدخل
                        <span class="rounded-full bg-background/80 px-2 py-0.5 text-xs">{{ financial_rows.length }}</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg px-4 text-sm font-bold transition-colors"
                        :class="activeTab === 'expenses' ? 'bg-rose-600 text-white shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'expenses'"
                    >
                        <Receipt class="size-4" />
                        المصروفات
                        <span class="rounded-full bg-background/80 px-2 py-0.5 text-xs">{{ expense_rows.length }}</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg px-4 text-sm font-bold transition-colors"
                        :class="activeTab === 'doctors' ? 'bg-violet-600 text-white shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'doctors'"
                    >
                        <Stethoscope class="size-4" />
                        مستحقات الأطباء
                        <span class="rounded-full bg-background/80 px-2 py-0.5 text-xs">{{ doctor_entitlements.length }}</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg px-4 text-sm font-bold transition-colors"
                        :class="activeTab === 'employees' ? 'bg-sky-600 text-white shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'employees'"
                    >
                        <UsersRound class="size-4" />
                        رواتب الموظفين
                        <span class="rounded-full bg-background/80 px-2 py-0.5 text-xs">{{ employee_salaries.length }}</span>
                    </button>
                </div>
            </div>

            <div v-if="activeTab === 'income'" class="overflow-hidden rounded-[1.2rem] border border-border bg-card shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1400px] text-right text-sm">
                        <thead class="bg-muted text-xs text-muted-foreground">
                            <tr>
                                <th class="px-3 py-3">#</th>
                                <th class="px-3 py-3">تاريخ الموعد</th>
                                <th class="px-3 py-3">اسم المريض</th>
                                <th class="px-3 py-3">رقم الملف</th>
                                <th class="px-3 py-3">العيادة</th>
                                <th class="px-3 py-3">الطبيب</th>
                                <th class="px-3 py-3">نوع الموعد</th>
                                <th class="px-3 py-3">التكلفة</th>
                                <th class="px-3 py-3">المدفوع</th>
                                <th class="px-3 py-3">المتبقي</th>
                                <th class="px-3 py-3">حالة الدفع</th>
                                <th class="px-3 py-3">طريقة الدفع</th>
                                <th class="px-3 py-3">المسجل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, idx) in financial_rows" :key="row.appointment_id" class="border-t">
                                <td class="px-3 py-3 text-muted-foreground">{{ idx + 1 }}</td>
                                <td class="px-3 py-3 text-foreground">{{ row.appointment_date ?? '-' }}</td>
                                <td class="px-3 py-3 font-semibold text-foreground">{{ row.patient_name }}</td>
                                <td class="px-3 py-3 font-mono text-foreground">{{ row.file_number ?? '-' }}</td>
                                <td class="px-3 py-3 text-foreground">{{ row.clinic_name }}</td>
                                <td class="px-3 py-3 text-foreground">{{ row.doctor_name }}</td>
                                <td class="px-3 py-3 text-foreground">{{ labelFor(row.appointment_type) }}</td>
                                <td class="px-3 py-3 font-mono text-foreground">{{ formatMoney(row.cost) }}</td>
                                <td class="px-3 py-3 font-mono text-emerald-700">{{ formatMoney(row.paid_amount) }}</td>
                                <td class="px-3 py-3 font-mono text-amber-700">{{ formatMoney(row.remaining_amount) }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1" :class="statusClass(row.payment_status)">
                                        {{ labelFor(row.payment_status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-foreground">{{ labelFor(row.payment_method) }}</td>
                                <td class="px-3 py-3 text-foreground">{{ row.created_by_name }}</td>
                            </tr>
                            <tr v-if="financial_rows.length === 0">
                                <td colspan="13" class="px-4 py-10 text-center text-muted-foreground">لا توجد بيانات دخل ضمن الفلاتر الحالية.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="activeTab === 'expenses'" class="overflow-hidden rounded-[1.2rem] border border-border bg-card shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-right text-sm">
                        <thead class="bg-muted text-xs text-muted-foreground">
                            <tr>
                                <th class="px-3 py-3">التاريخ</th>
                                <th class="px-3 py-3">التصنيف</th>
                                <th class="px-3 py-3">الوصف</th>
                                <th class="px-3 py-3">المبلغ</th>
                                <th class="px-3 py-3">طريقة الدفع</th>
                                <th class="px-3 py-3">المستخدم</th>
                                <th class="px-3 py-3">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in expense_rows" :key="row.id" class="border-t">
                                <td class="px-3 py-3 text-foreground">{{ row.expense_date ?? '-' }}</td>
                                <td class="px-3 py-3 text-foreground">{{ row.category_name }}</td>
                                <td class="px-3 py-3 text-foreground">{{ row.description }}</td>
                                <td class="px-3 py-3 font-mono text-rose-700">{{ formatMoney(row.amount) }}</td>
                                <td class="px-3 py-3 text-foreground">{{ labelFor(row.payment_method) }}</td>
                                <td class="px-3 py-3 text-foreground">{{ row.user_name }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex gap-1">
                                        <Button v-if="can('expenses.update')" variant="outline" size="sm" class="h-8 w-8 p-0" @click="openEditExpense(row)">
                                            <FileText class="size-3.5" />
                                        </Button>
                                        <Button v-if="can('expenses.delete')" variant="outline" size="sm" class="h-8 w-8 p-0 text-rose-600" @click="confirmDeleteExpense(row.id)">
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="expense_rows.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">لا توجد مصروفات ضمن الفلاتر الحالية.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="activeTab === 'doctors'" class="overflow-hidden rounded-[1.2rem] border border-border bg-card shadow-card">
                <div class="border-b border-border bg-muted/50 px-5 py-3">
                    <p class="text-xs text-muted-foreground">هذا القسم للعرض فقط. التسديد يتم من صفحة الرواتب.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-right text-sm">
                        <thead class="bg-muted text-xs text-muted-foreground">
                            <tr>
                                <th class="px-3 py-3">اسم الطبيب</th>
                                <th class="px-3 py-3">العيادة</th>
                                <th class="px-3 py-3">نوع الأجر</th>
                                <th class="px-3 py-3">المستحق</th>
                                <th class="px-3 py-3">المدفوع</th>
                                <th class="px-3 py-3">المتبقي</th>
                                <th class="px-3 py-3">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in doctor_entitlements" :key="row.id" class="border-t">
                                <td class="px-3 py-3 font-semibold text-foreground">{{ row.doctor_name }}</td>
                                <td class="px-3 py-3 text-foreground">{{ row.clinic_name }}</td>
                                <td class="px-3 py-3 text-foreground">{{ labelFor(row.payment_type) }}</td>
                                <td class="px-3 py-3 font-mono text-foreground">{{ formatMoney(row.due_amount) }}</td>
                                <td class="px-3 py-3 font-mono text-emerald-700">{{ formatMoney(row.paid_amount) }}</td>
                                <td class="px-3 py-3 font-mono text-amber-700">{{ formatMoney(row.remaining_amount) }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1" :class="statusClass(row.status)">
                                        {{ labelFor(row.status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="doctor_entitlements.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">لا توجد مستحقات أطباء ضمن الفلاتر الحالية.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="activeTab === 'employees'" class="overflow-hidden rounded-[1.2rem] border border-border bg-card shadow-card">
                <div class="border-b border-border bg-muted/50 px-5 py-3">
                    <p class="text-xs text-muted-foreground">هذا القسم للعرض فقط. التسديد يتم من صفحة الرواتب.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[800px] text-right text-sm">
                        <thead class="bg-muted text-xs text-muted-foreground">
                            <tr>
                                <th class="px-3 py-3">اسم الموظف</th>
                                <th class="px-3 py-3">الراتب الأساسي</th>
                                <th class="px-3 py-3">المستحق</th>
                                <th class="px-3 py-3">المدفوع</th>
                                <th class="px-3 py-3">المتبقي</th>
                                <th class="px-3 py-3">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in employee_salaries" :key="row.id" class="border-t">
                                <td class="px-3 py-3 font-semibold text-foreground">{{ row.employee_name }}</td>
                                <td class="px-3 py-3 font-mono text-foreground">{{ formatMoney(row.base_salary) }}</td>
                                <td class="px-3 py-3 font-mono text-foreground">{{ formatMoney(row.due_amount) }}</td>
                                <td class="px-3 py-3 font-mono text-emerald-700">{{ formatMoney(row.paid_amount) }}</td>
                                <td class="px-3 py-3 font-mono text-amber-700">{{ formatMoney(row.remaining_amount) }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1" :class="statusClass(row.status)">
                                        {{ labelFor(row.status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="employee_salaries.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">لا توجد رواتب موظفين ضمن الفلاتر الحالية.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <Dialog :open="expenseDialogOpen" @update:open="expenseDialogOpen = $event">
            <DialogContent class="max-w-lg rounded-xl bg-card" dir="rtl">
                <DialogHeader class="text-right">
                    <DialogTitle class="text-foreground">
                        {{ editingExpense ? 'تعديل المصروف' : 'إضافة مصروف جديد' }}
                    </DialogTitle>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label>الوصف</Label>
                        <Input v-model="expenseForm.description" placeholder="وصف المصروف" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>المبلغ</Label>
                            <Input v-model="expenseForm.amount" type="number" min="0.01" step="0.01" />
                        </div>
                        <div class="grid gap-2">
                            <Label>التاريخ</Label>
                            <Input v-model="expenseForm.expense_date" type="date" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>التصنيف</Label>
                            <select v-model="expenseForm.category_id" class="h-10 rounded-md border border-input bg-muted px-3 text-sm">
                                <option :value="null">بدون تصنيف</option>
                                <option v-for="cat in expense_categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label>طريقة الدفع</Label>
                            <select v-model="expenseForm.payment_method" class="h-10 rounded-md border border-input bg-muted px-3 text-sm">
                                <option value="cash">نقداً</option>
                                <option value="card">بطاقة</option>
                                <option value="bank_transfer">حوالة بنكية</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>ملاحظات</Label>
                        <Input v-model="expenseForm.notes" placeholder="ملاحظات اختيارية" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="expenseDialogOpen = false">إلغاء</Button>
                    <Button @click="submitExpense">
                        {{ editingExpense ? 'حفظ التعديل' : 'إضافة المصروف' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="deleteConfirmOpen" @update:open="deleteConfirmOpen = $event">
            <DialogContent class="max-w-sm rounded-xl bg-card" dir="rtl">
                <DialogHeader class="text-right">
                    <DialogTitle class="text-foreground">تأكيد الحذف</DialogTitle>
                </DialogHeader>
                <p class="text-sm text-muted-foreground">هل أنت متأكد من حذف هذا المصروف؟ لا يمكن التراجع عن هذا الإجراء.</p>
                <DialogFooter>
                    <Button variant="outline" @click="deleteConfirmOpen = false">إلغاء</Button>
                    <Button variant="destructive" @click="doDeleteExpense">حذف</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
