<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Banknote, CalendarCheck, CircleDollarSign, FileText, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as financialIndex } from '@/routes/financial';

type DepartmentOption = { id: number; name: string };
type FinancialRow = {
    appointment_id: number;
    patient_name: string;
    file_number: string | null;
    doctor_name: string;
    department: string;
    appointment_type: string;
    cost: number;
    paid_amount: number;
    remaining_amount: number;
    payment_status: 'unpaid' | 'partially_paid' | 'paid';
    appointment_date: string | null;
    payment_method: string | null;
};

const props = defineProps<{
    financial_rows: FinancialRow[];
    summaries: {
        total_cost: number;
        total_paid: number;
        total_remaining: number;
        paid_count: number;
        unpaid_count: number;
        partially_paid_count: number;
    };
    departments: DepartmentOption[];
    filters: Record<string, string | number | null>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'المالية', href: financialIndex() }],
    },
});

const month = ref(String(props.filters.month ?? new Date().toISOString().slice(0, 7)));
const dateFrom = ref(String(props.filters.date_from ?? ''));
const dateTo = ref(String(props.filters.date_to ?? ''));
const status = ref(String(props.filters.status ?? ''));
const departmentId = ref(String(props.filters.department_id ?? ''));
const appointmentType = ref(String(props.filters.appointment_type ?? ''));

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
};

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

const reload = (): void => {
    router.get(
        financialIndex(),
        {
            month: month.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            status: status.value || undefined,
            department_id: departmentId.value || undefined,
            appointment_type: appointmentType.value || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

watch([month, dateFrom, dateTo, status, departmentId, appointmentType], () => {
    reload();
});
</script>

<template>
    <div class="mx-auto w-full max-w-[1680px] space-y-6 p-4 md:p-6" dir="rtl">
        <section class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="space-y-2 text-right">
                <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                    <Banknote class="size-4" />
                    المالية
                </div>
                <h1 class="text-3xl font-extrabold text-slate-950">إدارة الدخل المالي للمجمع</h1>
                <p class="max-w-3xl text-sm text-slate-500">متابعة إيرادات المواعيد والدفعات وحالة السداد لكل موعد.</p>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-lg border bg-white p-4">
                <div class="flex items-center gap-2 text-slate-500"><TrendingUp class="size-4" /><p class="text-xs">إجمالي تكاليف المواعيد</p></div>
                <p class="mt-1 text-xl font-bold">{{ formatMoney(summaries.total_cost) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <div class="flex items-center gap-2 text-emerald-600"><CircleDollarSign class="size-4" /><p class="text-xs">إجمالي المبالغ المدفوعة</p></div>
                <p class="mt-1 text-xl font-bold text-emerald-700">{{ formatMoney(summaries.total_paid) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <div class="flex items-center gap-2 text-amber-600"><TrendingDown class="size-4" /><p class="text-xs">إجمالي المبالغ المتبقية</p></div>
                <p class="mt-1 text-xl font-bold text-amber-700">{{ formatMoney(summaries.total_remaining) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <div class="flex items-center gap-2 text-emerald-600"><CalendarCheck class="size-4" /><p class="text-xs">مواعيد مدفوعة</p></div>
                <p class="mt-1 text-xl font-bold">{{ summaries.paid_count }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <div class="flex items-center gap-2 text-slate-500"><FileText class="size-4" /><p class="text-xs">مواعيد غير مدفوعة</p></div>
                <p class="mt-1 text-xl font-bold">{{ summaries.unpaid_count }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <div class="flex items-center gap-2 text-amber-600"><FileText class="size-4" /><p class="text-xs">مدفوعة جزئياً</p></div>
                <p class="mt-1 text-xl font-bold">{{ summaries.partially_paid_count }}</p>
            </div>
        </section>

        <section class="rounded-lg border bg-white p-4">
            <div class="grid gap-3 md:grid-cols-6">
                <div class="grid gap-1"><Label>الشهر</Label><Input v-model="month" type="month" class="h-10" /></div>
                <div class="grid gap-1"><Label>من تاريخ</Label><Input v-model="dateFrom" type="date" class="h-10" /></div>
                <div class="grid gap-1"><Label>إلى تاريخ</Label><Input v-model="dateTo" type="date" class="h-10" /></div>
                <div class="grid gap-1"><Label>الحالة</Label><select v-model="status" class="h-10 rounded-md border px-3 text-sm"><option value="">كل الحالات</option><option value="unpaid">غير مدفوع</option><option value="partially_paid">مدفوع جزئياً</option><option value="paid">مدفوع</option></select></div>
                <div class="grid gap-1"><Label>نوع الموعد</Label><select v-model="appointmentType" class="h-10 rounded-md border px-3 text-sm"><option value="">الكل</option><option value="first_visit">كشفية أولى</option><option value="review">مراجعة</option></select></div>
                <div class="grid gap-1"><Label>العيادة</Label><select v-model="departmentId" class="h-10 rounded-md border px-3 text-sm"><option value="">الكل</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1240px] text-right text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-4 py-3">اسم المريض</th>
                            <th class="px-4 py-3">رقم الملف</th>
                            <th class="px-4 py-3">اسم الطبيب</th>
                            <th class="px-4 py-3">العيادة</th>
                            <th class="px-4 py-3">نوع الموعد</th>
                            <th class="px-4 py-3">تكلفة الموعد</th>
                            <th class="px-4 py-3">المبلغ المدفوع</th>
                            <th class="px-4 py-3">المبلغ المتبقي</th>
                            <th class="px-4 py-3">حالة الدفع</th>
                            <th class="px-4 py-3">تاريخ الموعد</th>
                            <th class="px-4 py-3">طريقة الدفع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in financial_rows" :key="row.appointment_id" class="border-t">
                            <td class="px-4 py-3 font-semibold">{{ row.patient_name }}</td>
                            <td class="px-4 py-3 font-mono">{{ row.file_number ?? '-' }}</td>
                            <td class="px-4 py-3">{{ row.doctor_name }}</td>
                            <td class="px-4 py-3">{{ row.department }}</td>
                            <td class="px-4 py-3">{{ labelFor(row.appointment_type) }}</td>
                            <td class="px-4 py-3 font-mono">{{ formatMoney(row.cost) }}</td>
                            <td class="px-4 py-3 font-mono text-emerald-700">{{ formatMoney(row.paid_amount) }}</td>
                            <td class="px-4 py-3 font-mono text-amber-700">{{ formatMoney(row.remaining_amount) }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusClass(row.payment_status)">{{ labelFor(row.payment_status) }}</span></td>
                            <td class="px-4 py-3">{{ row.appointment_date ?? '-' }}</td>
                            <td class="px-4 py-3">{{ labelFor(row.payment_method) }}</td>
                        </tr>
                        <tr v-if="financial_rows.length === 0">
                            <td colspan="11" class="px-4 py-10 text-center text-slate-500">لا توجد بيانات مالية ضمن الفلاتر الحالية.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
