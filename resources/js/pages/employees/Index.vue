<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Search, Trash2, UsersRound, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';

type DepartmentOption = { id: number; name: string };
type Employee = {
    id: number;
    full_name: string;
    gender: 'male' | 'female';
    birth_date: string | null;
    phone: string;
    address: string | null;
    national_id: string | null;
    hire_date: string;
    status: 'active' | 'inactive';
    job_title: string;
    department_id: number | null;
    department: DepartmentOption | null;
    employee_type: string;
    education_level: string | null;
    certificate_type: string | null;
    base_salary: number;
    salary_notes: string | null;
    salary_payments_count: number;
};
type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    employees: Paginated<Employee>;
    departments: DepartmentOption[];
    filters: Record<string, string | number | null>;
    stats: { total: number; active: number; inactive: number; monthly_salaries: number };
    options: { employee_types: string[]; education_levels: string[]; statuses: string[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'إدارة الموظفين', href: EmployeeController.index() }],
    },
});

const { can } = usePermissions();
const toast = useToast();
const { isOpen: isConfirmOpen, options: confirmOptions, confirm, handleConfirm: handleConfirmDelete, handleCancel: handleConfirmCancel } = useConfirm();

const showForm = ref(false);
const viewing = ref<Employee | null>(null);
const editing = ref<Employee | null>(null);
const search = ref(String(props.filters.search ?? ''));
const employeeType = ref(String(props.filters.employee_type ?? ''));
const status = ref(String(props.filters.status ?? ''));
const departmentId = ref(String(props.filters.department_id ?? ''));
const educationLevel = ref(String(props.filters.education_level ?? ''));
const hireDateFrom = ref(String(props.filters.hire_date_from ?? ''));
const hireDateTo = ref(String(props.filters.hire_date_to ?? ''));

const labels: Record<string, string> = {
    reception: 'استقبال',
    nurse: 'ممرض',
    lab: 'مخبري',
    cleaner: 'عامل نظافة',
    guard: 'حارس',
    accountant: 'محاسب',
    administrative: 'إداري',
    other: 'أخرى',
    institute: 'معهد',
    college: 'كلية',
    postgraduate: 'دراسات عليا',
    none: 'بدون شهادة',
    active: 'نشط',
    inactive: 'غير نشط',
    male: 'ذكر',
    female: 'أنثى',
};

type EmployeeForm = {
    full_name: string;
    gender: 'male' | 'female';
    birth_date: string;
    phone: string;
    address: string;
    national_id: string;
    hire_date: string;
    status: 'active' | 'inactive';
    job_title: string;
    department_id: number | '';
    employee_type: string;
    education_level: string;
    certificate_type: string;
    base_salary: string;
    salary_notes: string;
};

const defaults = (employee: Employee | null = null): EmployeeForm => ({
    full_name: employee?.full_name ?? '',
    gender: employee?.gender ?? 'male',
    birth_date: employee?.birth_date ?? '',
    phone: employee?.phone ?? '',
    address: employee?.address ?? '',
    national_id: employee?.national_id ?? '',
    hire_date: employee?.hire_date ?? new Date().toISOString().slice(0, 10),
    status: employee?.status ?? 'active',
    job_title: employee?.job_title ?? '',
    department_id: employee?.department_id ?? '',
    employee_type: employee?.employee_type ?? 'reception',
    education_level: employee?.education_level ?? 'none',
    certificate_type: employee?.certificate_type ?? '',
    base_salary: employee !== null ? String(employee.base_salary) : '0',
    salary_notes: employee?.salary_notes ?? '',
});

const form = useForm<EmployeeForm>(defaults());
const isEditing = computed(() => editing.value !== null);
const formatMoney = (value: number): string => new Intl.NumberFormat('ar-SY', { maximumFractionDigits: 0 }).format(value);
const labelFor = (value: string | null): string => (value !== null ? labels[value] ?? value : '-');

let timer: ReturnType<typeof setTimeout> | null = null;
const reload = (): void => {
    router.get(
        EmployeeController.index.url(),
        {
            search: search.value || undefined,
            employee_type: employeeType.value || undefined,
            status: status.value || undefined,
            department_id: departmentId.value || undefined,
            education_level: educationLevel.value || undefined,
            hire_date_from: hireDateFrom.value || undefined,
            hire_date_to: hireDateTo.value || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

watch([employeeType, status, departmentId, educationLevel, hireDateFrom, hireDateTo], reload);
watch(search, () => {
    if (timer !== null) {
        clearTimeout(timer);
    }
    timer = setTimeout(reload, 350);
});

const openCreate = (): void => {
    editing.value = null;
    form.defaults(defaults());
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (employee: Employee): void => {
    editing.value = employee;
    form.defaults(defaults(employee));
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const submit = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            editing.value = null;
            toast.success('تم حفظ بيانات الموظف بنجاح');
        },
    };

    if (editing.value !== null) {
        form.put(EmployeeController.update.url(editing.value.id), options);
        return;
    }

    form.post(EmployeeController.store.url(), options);
};

const deleteEmployee = async (employee: Employee): Promise<void> => {
    const accepted = await confirm({
        title: 'حذف الموظف',
        description: employee.salary_payments_count > 0 ? 'للموظف سجلات رواتب، سيتم تغيير حالته إلى غير نشط حفاظاً على السجلات.' : 'سيتم حذف الموظف بعد التأكيد.',
        confirmText: 'تأكيد',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (!accepted) {
        return;
    }

    router.delete(EmployeeController.destroy.url(employee.id), {
        preserveScroll: true,
        onSuccess: () => toast.success('تم تنفيذ العملية بنجاح'),
        onError: () => toast.error('تعذر تنفيذ العملية'),
    });
};
</script>

<template>
    <Head title="إدارة الموظفين" />

    <div class="mx-auto w-full max-w-[1680px] space-y-6 p-4 md:p-6" dir="rtl">
        <section class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="space-y-2 text-right">
                <div class="inline-flex items-center gap-2 rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">
                    <UsersRound class="size-4" />
                    إدارة الموظفين
                </div>
                <h1 class="text-3xl font-extrabold text-slate-950">إدارة الموظفين</h1>
                <p class="max-w-3xl text-sm text-slate-500">إدارة موظفي المجمع غير الأطباء مع بياناتهم الوظيفية والشهادات والرواتب الأساسية.</p>
            </div>

            <Button v-if="can('employees.create')" type="button" class="h-11 rounded-lg bg-sky-600 px-5 text-white hover:bg-sky-700" @click="openCreate">
                <Plus class="size-4" />
                إضافة موظف جديد
            </Button>
        </section>

        <section class="grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border bg-white p-4"><p class="text-xs text-slate-500">إجمالي الموظفين</p><p class="text-2xl font-bold">{{ stats.total }}</p></div>
            <div class="rounded-lg border bg-white p-4"><p class="text-xs text-slate-500">النشطون</p><p class="text-2xl font-bold text-emerald-700">{{ stats.active }}</p></div>
            <div class="rounded-lg border bg-white p-4"><p class="text-xs text-slate-500">غير النشطين</p><p class="text-2xl font-bold text-slate-600">{{ stats.inactive }}</p></div>
            <div class="rounded-lg border bg-white p-4"><p class="text-xs text-slate-500">رواتب شهرية أساسية</p><p class="text-2xl font-bold">{{ formatMoney(stats.monthly_salaries) }}</p></div>
        </section>

        <section class="rounded-lg border bg-white p-4">
            <div class="grid gap-3 md:grid-cols-7">
                <div class="relative md:col-span-2">
                    <Search class="absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <Input v-model="search" class="h-10 pr-10" placeholder="الاسم، الهاتف، الهوية، المسمى..." />
                </div>
                <select v-model="employeeType" class="h-10 rounded-md border px-3 text-sm"><option value="">كل الأنواع</option><option v-for="type in options.employee_types" :key="type" :value="type">{{ labelFor(type) }}</option></select>
                <select v-model="status" class="h-10 rounded-md border px-3 text-sm"><option value="">كل الحالات</option><option value="active">نشط</option><option value="inactive">غير نشط</option></select>
                <select v-model="departmentId" class="h-10 rounded-md border px-3 text-sm"><option value="">كل الأقسام</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select>
                <select v-model="educationLevel" class="h-10 rounded-md border px-3 text-sm"><option value="">كل الشهادات</option><option v-for="level in options.education_levels" :key="level" :value="level">{{ labelFor(level) }}</option></select>
                <div class="grid grid-cols-2 gap-2"><Input v-model="hireDateFrom" type="date" class="h-10" /><Input v-model="hireDateTo" type="date" class="h-10" /></div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1180px] text-right text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-4 py-3">الاسم الكامل</th>
                            <th class="px-4 py-3">الجنس</th>
                            <th class="px-4 py-3">رقم الهاتف</th>
                            <th class="px-4 py-3">المسمى الوظيفي</th>
                            <th class="px-4 py-3">نوع الموظف</th>
                            <th class="px-4 py-3">القسم / العيادة</th>
                            <th class="px-4 py-3">المستوى العلمي</th>
                            <th class="px-4 py-3">نوع الشهادة</th>
                            <th class="px-4 py-3">الراتب الشهري</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">تاريخ التعيين</th>
                            <th class="px-4 py-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="employee in employees.data" :key="employee.id" class="border-t">
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ employee.full_name }}</td>
                            <td class="px-4 py-3">{{ labelFor(employee.gender) }}</td>
                            <td class="px-4 py-3">{{ employee.phone }}</td>
                            <td class="px-4 py-3">{{ employee.job_title }}</td>
                            <td class="px-4 py-3">{{ labelFor(employee.employee_type) }}</td>
                            <td class="px-4 py-3">{{ employee.department?.name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ labelFor(employee.education_level) }}</td>
                            <td class="px-4 py-3">{{ employee.certificate_type ?? '-' }}</td>
                            <td class="px-4 py-3 font-mono">{{ formatMoney(employee.base_salary) }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="employee.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ labelFor(employee.status) }}</span></td>
                            <td class="px-4 py-3">{{ employee.hire_date }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button type="button" size="icon" variant="ghost" class="size-8" @click="viewing = employee"><Eye class="size-4" /></Button>
                                    <Button v-if="can('employees.update')" type="button" size="icon" variant="ghost" class="size-8" @click="openEdit(employee)"><Pencil class="size-4" /></Button>
                                    <Button v-if="can('employees.delete')" type="button" size="icon" variant="ghost" class="size-8 text-red-600" @click="deleteEmployee(employee)"><Trash2 class="size-4" /></Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="employees.data.length === 0"><td colspan="12" class="px-4 py-10 text-center text-slate-500">لا توجد بيانات موظفين.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
            <p>عرض {{ employees.from ?? 0 }} إلى {{ employees.to ?? 0 }} من {{ employees.total }}</p>
            <div class="flex items-center gap-2">
                <Button variant="outline" :disabled="employees.prev_page_url === null" @click="router.visit(employees.prev_page_url ?? '')">السابق</Button>
                <span class="font-semibold">صفحة {{ employees.current_page }} / {{ employees.last_page }}</span>
                <Button variant="outline" :disabled="employees.next_page_url === null" @click="router.visit(employees.next_page_url ?? '')">التالي</Button>
            </div>
        </div>

        <Dialog :open="showForm" @update:open="showForm = $event">
            <DialogContent class="max-h-[92vh] max-w-5xl overflow-hidden rounded-lg bg-white p-0" dir="rtl">
                <DialogHeader class="border-b px-6 py-4 text-right"><DialogTitle>{{ isEditing ? 'تعديل موظف' : 'إضافة موظف جديد' }}</DialogTitle></DialogHeader>
                <form class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-5" @submit.prevent="submit">
                    <section class="grid gap-4 md:grid-cols-3">
                        <div class="grid gap-2"><Label>الاسم الكامل</Label><Input v-model="form.full_name" /><InputError :message="form.errors.full_name" /></div>
                        <div class="grid gap-2"><Label>الجنس</Label><select v-model="form.gender" class="h-10 rounded-md border px-3"><option value="male">ذكر</option><option value="female">أنثى</option></select><InputError :message="form.errors.gender" /></div>
                        <div class="grid gap-2"><Label>تاريخ الميلاد</Label><Input v-model="form.birth_date" type="date" /><InputError :message="form.errors.birth_date" /></div>
                        <div class="grid gap-2"><Label>رقم الهاتف</Label><Input v-model="form.phone" /><InputError :message="form.errors.phone" /></div>
                        <div class="grid gap-2"><Label>الرقم الوطني أو الهوية</Label><Input v-model="form.national_id" /><InputError :message="form.errors.national_id" /></div>
                        <div class="grid gap-2"><Label>تاريخ التعيين</Label><Input v-model="form.hire_date" type="date" /><InputError :message="form.errors.hire_date" /></div>
                        <div class="grid gap-2 md:col-span-2"><Label>العنوان</Label><Input v-model="form.address" /><InputError :message="form.errors.address" /></div>
                        <div class="grid gap-2"><Label>الحالة</Label><select v-model="form.status" class="h-10 rounded-md border px-3"><option value="active">نشط</option><option value="inactive">غير نشط</option></select></div>
                    </section>
                    <section class="grid gap-4 md:grid-cols-3">
                        <div class="grid gap-2"><Label>المسمى الوظيفي</Label><Input v-model="form.job_title" /><InputError :message="form.errors.job_title" /></div>
                        <div class="grid gap-2"><Label>القسم / العيادة</Label><select v-model="form.department_id" class="h-10 rounded-md border px-3"><option value="">بدون</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select><InputError :message="form.errors.department_id" /></div>
                        <div class="grid gap-2"><Label>نوع الموظف</Label><select v-model="form.employee_type" class="h-10 rounded-md border px-3"><option v-for="type in options.employee_types" :key="type" :value="type">{{ labelFor(type) }}</option></select><InputError :message="form.errors.employee_type" /></div>
                    </section>
                    <section class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2"><Label>المستوى العلمي</Label><select v-model="form.education_level" class="h-10 rounded-md border px-3"><option v-for="level in options.education_levels" :key="level" :value="level">{{ labelFor(level) }}</option></select><InputError :message="form.errors.education_level" /></div>
                        <div class="grid gap-2"><Label>نوع الشهادة</Label><Input v-model="form.certificate_type" placeholder="تمريض، مخبر، محاسبة..." /><InputError :message="form.errors.certificate_type" /></div>
                    </section>
                    <section class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2"><Label>الراتب الشهري الأساسي</Label><Input v-model="form.base_salary" type="number" min="0" step="0.01" /><InputError :message="form.errors.base_salary" /></div>
                        <div class="grid gap-2"><Label>ملاحظات الراتب</Label><Input v-model="form.salary_notes" /><InputError :message="form.errors.salary_notes" /></div>
                    </section>
                </form>
                <DialogFooter class="border-t px-6 py-4">
                    <Button type="button" variant="outline" @click="showForm = false"><X class="size-4" />إلغاء</Button>
                    <Button type="button" class="bg-sky-600 text-white hover:bg-sky-700" :disabled="form.processing" @click="submit">{{ isEditing ? 'حفظ التعديلات' : 'حفظ الموظف' }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="viewing !== null" @update:open="viewing = null">
            <DialogContent class="max-w-3xl rounded-lg bg-white" dir="rtl">
                <DialogHeader class="text-right"><DialogTitle>{{ viewing?.full_name }}</DialogTitle></DialogHeader>
                <div v-if="viewing" class="grid gap-3 text-sm md:grid-cols-2">
                    <p><b>الهاتف:</b> {{ viewing.phone }}</p><p><b>الهوية:</b> {{ viewing.national_id ?? '-' }}</p>
                    <p><b>الوظيفة:</b> {{ viewing.job_title }}</p><p><b>نوع الموظف:</b> {{ labelFor(viewing.employee_type) }}</p>
                    <p><b>القسم:</b> {{ viewing.department?.name ?? '-' }}</p><p><b>الشهادة:</b> {{ labelFor(viewing.education_level) }} / {{ viewing.certificate_type ?? '-' }}</p>
                    <p><b>الراتب:</b> {{ formatMoney(viewing.base_salary) }}</p><p><b>الحالة:</b> {{ labelFor(viewing.status) }}</p>
                    <p class="md:col-span-2"><b>العنوان:</b> {{ viewing.address ?? '-' }}</p>
                    <p class="md:col-span-2"><b>ملاحظات الراتب:</b> {{ viewing.salary_notes ?? '-' }}</p>
                </div>
            </DialogContent>
        </Dialog>

        <ConfirmationDialog :open="isConfirmOpen" :options="confirmOptions" @confirm="handleConfirmDelete" @cancel="handleConfirmCancel" @update:open="handleConfirmCancel" />
    </div>
</template>
