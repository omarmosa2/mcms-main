<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Eye,
    Pencil,
    Plus,
    Search,
    Trash2,
    UsersRound,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import ConfirmationDialog from '@/components/ui/confirmation-dialog/ConfirmationDialog.vue';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import { useToast } from '@/composables/useToast';
import EmployeeStatsCards from './components/EmployeeStatsCards.vue';

type DepartmentOption = { id: number; name: string };
type UserBrief = { id: number; name: string; email: string };
type Employee = {
    id: number;
    full_name: string;
    gender: 'male' | 'female';
    birth_date: string | null;
    phone: string;
    address: string | null;
    national_id: string | null;
    marital_status: string | null;
    hire_date: string;
    status: 'active' | 'inactive';
    job_title: string;
    department_id: number | null;
    department: DepartmentOption | null;
    employee_type: string;
    specialty: string | null;
    job_description: string | null;
    education_level: string | null;
    certificate_name: string | null;
    education_specialty: string | null;
    graduation_year: number | null;
    issuing_institution: string | null;
    base_salary: number;
    additional_allowance: number | null;
    salary_notes: string | null;
    salary_payments_count: number;
    user: UserBrief | null;
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
    stats: {
        total: number;
        active: number;
        inactive: number;
        monthly_salaries: number;
    };
    options: {
        employee_types: string[];
        education_levels: string[];
        statuses: string[];
        marital_statuses: string[];
        account_roles: string[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'إدارة الموظفين', href: EmployeeController.index() },
        ],
    },
});

const { can } = usePermissions();
const toast = useToast();
const {
    isOpen: isConfirmOpen,
    options: confirmOptions,
    confirm,
    close: closeConfirm,
    handleConfirm: handleConfirmDelete,
    handleCancel: handleConfirmCancel,
} = useConfirm();

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
    user: 'مستخدم',
    cleaner: 'عامل نظافة',
    guard: 'حارس',
    accountant: 'محاسب',
    administrative: 'إداري',
    other: 'أخرى',
    secondary: 'ثانوي',
    institute: 'معهد',
    college: 'كلية',
    postgraduate: 'دراسات عليا',
    none: 'بدون شهادة',
    active: 'نشط',
    inactive: 'غير نشط',
    male: 'ذكر',
    female: 'أنثى',
    single: 'أعزب',
    married: 'متزوج',
    divorced: 'مطلق',
    widowed: 'أرمل',
    receptionist: 'استقبال',
    admin: 'إداري',
};

type EmployeeForm = {
    full_name: string;
    gender: 'male' | 'female';
    birth_date: string;
    phone: string;
    address: string;
    national_id: string;
    marital_status: string;
    hire_date: string;
    status: 'active' | 'inactive';
    job_title: string;
    department_id: number | '';
    employee_type: string;
    specialty: string;
    job_description: string;
    education_level: string;
    certificate_name: string;
    education_specialty: string;
    graduation_year: string;
    issuing_institution: string;
    base_salary: string;
    additional_allowance: string;
    salary_notes: string;
    create_account: boolean;
    email: string;
    password: string;
    role_name: string;
};

const defaults = (employee: Employee | null = null): EmployeeForm => ({
    full_name: employee?.full_name ?? '',
    gender: employee?.gender ?? 'male',
    birth_date: employee?.birth_date ?? '',
    phone: employee?.phone ?? '',
    address: employee?.address ?? '',
    national_id: employee?.national_id ?? '',
    marital_status: employee?.marital_status ?? '',
    hire_date: employee?.hire_date ?? new Date().toISOString().slice(0, 10),
    status: employee?.status ?? 'active',
    job_title: employee?.job_title ?? '',
    department_id: employee?.department_id ?? '',
    employee_type: employee?.employee_type ?? 'reception',
    specialty: employee?.specialty ?? '',
    job_description: employee?.job_description ?? '',
    education_level: employee?.education_level ?? 'none',
    certificate_name: employee?.certificate_name ?? '',
    education_specialty: employee?.education_specialty ?? '',
    graduation_year: employee?.graduation_year
        ? String(employee.graduation_year)
        : '',
    issuing_institution: employee?.issuing_institution ?? '',
    base_salary: employee !== null ? String(employee.base_salary) : '0',
    additional_allowance:
        employee?.additional_allowance !== null &&
        employee?.additional_allowance !== undefined
            ? String(employee.additional_allowance)
            : '',
    salary_notes: employee?.salary_notes ?? '',
    create_account: false,
    email: '',
    password: '',
    role_name: 'receptionist',
});

const form = useForm<EmployeeForm>(defaults());
const isEditing = computed(() => editing.value !== null);
const formatMoney = (value: number): string =>
    new Intl.NumberFormat('en-US-u-nu-latn', {
        maximumFractionDigits: 0,
    }).format(value);
const labelFor = (value: string | null): string =>
    value !== null ? (labels[value] ?? value) : '-';

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

watch(
    [
        employeeType,
        status,
        departmentId,
        educationLevel,
        hireDateFrom,
        hireDateTo,
    ],
    reload,
);
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
    const data: Record<string, unknown> = { ...form };

    if (!data.create_account) {
        delete data.email;
        delete data.password;
        delete data.role_name;
    }

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
        description:
            employee.salary_payments_count > 0
                ? 'للموظف سجلات رواتب، سيتم تغيير حالته إلى غير نشط حفاظاً على السجلات.'
                : 'سيتم حذف الموظف بعد التأكيد.',
        confirmText: 'تأكيد',
        cancelText: 'إلغاء',
        variant: 'destructive',
    });

    if (!accepted) {
        return;
    }

    router.delete(EmployeeController.destroy.url(employee.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeConfirm();
            toast.success('تم تنفيذ العملية بنجاح');
        },
        onError: () => toast.error('تعذر تنفيذ العملية'),
    });
};
</script>

<template>
    <Head title="إدارة الموظفين" />

    <div class="mx-auto w-full max-w-[1680px] space-y-6 p-4 md:p-6" dir="rtl">
        <section
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div class="space-y-2 text-right">
                <div
                    class="inline-flex items-center gap-2 rounded-full bg-accent px-3 py-1 text-xs font-semibold text-accent-foreground"
                >
                    <UsersRound class="size-4" />
                    إدارة الموظفين
                </div>
                <h1 class="text-3xl font-extrabold text-foreground">
                    إدارة الموظفين
                </h1>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    إدارة موظفي المجمع غير الأطباء مع بياناتهم الوظيفية
                    والشهادات والرواتب الأساسية.
                </p>
            </div>

            <Button
                v-if="can('employees.create')"
                type="button"
                class="h-11 rounded-lg bg-primary px-5 text-primary-foreground hover:bg-primary/90"
                @click="openCreate"
            >
                <Plus class="size-4" />
                إضافة موظف جديد
            </Button>
        </section>

        <EmployeeStatsCards :stats="stats" />

        <section class="rounded-lg border bg-card p-4">
            <div class="grid gap-3 md:grid-cols-7">
                <div class="relative md:col-span-2">
                    <Search
                        class="absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        class="h-10 pr-10"
                        placeholder="الاسم، الهاتف، الهوية، المسمى، الاختصاص..."
                    />
                </div>
                <select
                    v-model="employeeType"
                    class="h-10 rounded-md border border-input bg-muted px-3 text-sm"
                >
                    <option value="">كل الأنواع</option>
                    <option
                        v-for="type in options.employee_types"
                        :key="type"
                        :value="type"
                    >
                        {{ labelFor(type) }}
                    </option>
                </select>
                <select
                    v-model="status"
                    class="h-10 rounded-md border border-input bg-muted px-3 text-sm"
                >
                    <option value="">كل الحالات</option>
                    <option value="active">نشط</option>
                    <option value="inactive">غير نشط</option>
                </select>
                <select
                    v-model="departmentId"
                    class="h-10 rounded-md border border-input bg-muted px-3 text-sm"
                >
                    <option value="">كل الأقسام</option>
                    <option
                        v-for="department in departments"
                        :key="department.id"
                        :value="department.id"
                    >
                        {{ department.name }}
                    </option>
                </select>
                <select
                    v-model="educationLevel"
                    class="h-10 rounded-md border border-input bg-muted px-3 text-sm"
                >
                    <option value="">كل الشهادات</option>
                    <option
                        v-for="level in options.education_levels"
                        :key="level"
                        :value="level"
                    >
                        {{ labelFor(level) }}
                    </option>
                </select>
                <div class="grid grid-cols-2 gap-2">
                    <Input
                        v-model="hireDateFrom"
                        type="date"
                        class="h-10"
                    /><Input v-model="hireDateTo" type="date" class="h-10" />
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1400px] text-right text-sm">
                    <thead class="bg-muted text-xs text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">الاسم الكامل</th>
                            <th class="px-4 py-3">الجنس</th>
                            <th class="px-4 py-3">رقم الهاتف</th>
                            <th class="px-4 py-3">نوع الموظف</th>
                            <th class="px-4 py-3">المسمى الوظيفي</th>
                            <th class="px-4 py-3">القسم / العيادة</th>
                            <th class="px-4 py-3">الاختصاص</th>
                            <th class="px-4 py-3">المستوى العلمي</th>
                            <th class="px-4 py-3">اسم الشهادة</th>
                            <th class="px-4 py-3">الراتب الشهري</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">تاريخ التعيين</th>
                            <th class="px-4 py-3">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="employee in employees.data"
                            :key="employee.id"
                            class="border-t"
                        >
                            <td class="px-4 py-3 font-semibold text-foreground">
                                {{ employee.full_name }}
                            </td>
                            <td class="px-4 py-3 text-foreground">
                                {{ labelFor(employee.gender) }}
                            </td>
                            <td class="px-4 py-3 text-foreground">
                                {{ employee.phone }}
                            </td>
                            <td class="px-4 py-3 text-foreground">
                                {{ labelFor(employee.employee_type) }}
                            </td>
                            <td class="px-4 py-3 text-foreground">
                                {{ employee.job_title }}
                            </td>
                            <td class="px-4 py-3 text-foreground">
                                {{ employee.department?.name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-foreground">
                                {{ employee.specialty ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-foreground">
                                {{ labelFor(employee.education_level) }}
                            </td>
                            <td class="px-4 py-3 text-foreground">
                                {{ employee.certificate_name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 font-mono text-foreground">
                                {{ formatMoney(employee.base_salary) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                    :class="
                                        employee.status === 'active'
                                            ? 'bg-success/10 text-success'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                    >{{ labelFor(employee.status) }}</span
                                >
                            </td>
                            <td class="px-4 py-3 text-foreground">
                                {{ employee.hire_date }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        @click="viewing = employee"
                                        ><Eye class="size-4"
                                    /></Button>
                                    <Button
                                        v-if="can('employees.update')"
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        @click="openEdit(employee)"
                                        ><Pencil class="size-4"
                                    /></Button>
                                    <Button
                                        v-if="can('employees.delete')"
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="size-8 text-destructive"
                                        @click="deleteEmployee(employee)"
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="employees.data.length === 0">
                            <td
                                colspan="13"
                                class="px-4 py-10 text-center text-muted-foreground"
                            >
                                لا توجد بيانات موظفين.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div
            class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
        >
            <p>
                عرض {{ employees.from ?? 0 }} إلى {{ employees.to ?? 0 }} من
                {{ employees.total }}
            </p>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    :disabled="employees.prev_page_url === null"
                    @click="router.visit(employees.prev_page_url ?? '')"
                    >السابق</Button
                >
                <span class="font-semibold text-foreground"
                    >صفحة {{ employees.current_page }} /
                    {{ employees.last_page }}</span
                >
                <Button
                    variant="outline"
                    :disabled="employees.next_page_url === null"
                    @click="router.visit(employees.next_page_url ?? '')"
                    >التالي</Button
                >
            </div>
        </div>

        <Dialog :open="showForm" @update:open="showForm = $event">
            <DialogContent
                class="max-h-[92vh] max-w-5xl overflow-hidden rounded-lg bg-card p-0"
                dir="rtl"
            >
                <DialogHeader
                    class="border-b border-border px-6 py-4 text-right"
                    ><DialogTitle class="text-foreground">{{
                        isEditing ? 'تعديل موظف' : 'إضافة موظف جديد'
                    }}</DialogTitle></DialogHeader
                >
                <form
                    class="max-h-[70vh] space-y-6 overflow-y-auto px-6 py-5"
                    @submit.prevent="submit"
                >
                    <fieldset class="space-y-3">
                        <legend
                            class="mb-2 border-b border-border pb-2 text-sm font-bold text-primary"
                        >
                            البيانات الشخصية
                        </legend>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="grid gap-2">
                                <Label
                                    >الاسم الكامل
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                ><Input v-model="form.full_name" /><InputError
                                    :message="form.errors.full_name"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    >الجنس
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                ><select
                                    v-model="form.gender"
                                    class="h-10 rounded-md border border-input bg-muted px-3"
                                >
                                    <option value="male">ذكر</option>
                                    <option value="female">أنثى</option></select
                                ><InputError :message="form.errors.gender" />
                            </div>
                            <div class="grid gap-2">
                                <Label>تاريخ الميلاد</Label
                                ><Input
                                    v-model="form.birth_date"
                                    type="date"
                                /><InputError
                                    :message="form.errors.birth_date"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    >رقم الهاتف
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                ><Input v-model="form.phone" /><InputError
                                    :message="form.errors.phone"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>الرقم الوطني أو الهوية</Label
                                ><Input v-model="form.national_id" /><InputError
                                    :message="form.errors.national_id"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>الحالة الاجتماعية</Label
                                ><select
                                    v-model="form.marital_status"
                                    class="h-10 rounded-md border border-input bg-muted px-3"
                                >
                                    <option value="">غير محدد</option>
                                    <option
                                        v-for="ms in options.marital_statuses"
                                        :key="ms"
                                        :value="ms"
                                    >
                                        {{ labelFor(ms) }}
                                    </option></select
                                ><InputError
                                    :message="form.errors.marital_status"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    >تاريخ التعيين
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                ><Input
                                    v-model="form.hire_date"
                                    type="date"
                                /><InputError
                                    :message="form.errors.hire_date"
                                />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label>العنوان</Label
                                ><Input v-model="form.address" /><InputError
                                    :message="form.errors.address"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    >حالة الموظف
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                ><select
                                    v-model="form.status"
                                    class="h-10 rounded-md border border-input bg-muted px-3"
                                >
                                    <option value="active">نشط</option>
                                    <option value="inactive">
                                        غير نشط
                                    </option></select
                                ><InputError :message="form.errors.status" />
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend
                            class="mb-2 border-b border-border pb-2 text-sm font-bold text-primary"
                        >
                            بيانات الوظيفة
                        </legend>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="grid gap-2">
                                <Label
                                    >نوع الموظف
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                ><select
                                    v-model="form.employee_type"
                                    class="h-10 rounded-md border border-input bg-muted px-3"
                                >
                                    <option
                                        v-for="type in options.employee_types"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{ labelFor(type) }}
                                    </option></select
                                ><InputError
                                    :message="form.errors.employee_type"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    >المسمى الوظيفي
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                ><Input v-model="form.job_title" /><InputError
                                    :message="form.errors.job_title"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>القسم أو العيادة</Label
                                ><select
                                    v-model="form.department_id"
                                    class="h-10 rounded-md border border-input bg-muted px-3"
                                >
                                    <option value="">بدون</option>
                                    <option
                                        v-for="department in departments"
                                        :key="department.id"
                                        :value="department.id"
                                    >
                                        {{ department.name }}
                                    </option></select
                                ><InputError
                                    :message="form.errors.department_id"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>الاختصاص أو مجال العمل</Label
                                ><Input
                                    v-model="form.specialty"
                                    placeholder="مثال: تمريض عام، محاسبة..."
                                /><InputError
                                    :message="form.errors.specialty"
                                />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label>وصف مهام الموظف</Label
                                ><Input
                                    v-model="form.job_description"
                                    placeholder="وصف مختصر لمهام الموظف..."
                                /><InputError
                                    :message="form.errors.job_description"
                                />
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend
                            class="mb-2 border-b border-border pb-2 text-sm font-bold text-primary"
                        >
                            البيانات العلمية
                        </legend>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="grid gap-2">
                                <Label>المستوى العلمي</Label
                                ><select
                                    v-model="form.education_level"
                                    class="h-10 rounded-md border border-input bg-muted px-3"
                                >
                                    <option
                                        v-for="level in options.education_levels"
                                        :key="level"
                                        :value="level"
                                    >
                                        {{ labelFor(level) }}
                                    </option></select
                                ><InputError
                                    :message="form.errors.education_level"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>اسم الشهادة</Label
                                ><Input
                                    v-model="form.certificate_name"
                                    placeholder="مثال: دبلوم تمريض، بكالوريوس محاسبة..."
                                /><InputError
                                    :message="form.errors.certificate_name"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>نوع الشهادة</Label
                                ><Input
                                    v-model="form.education_specialty"
                                    placeholder="مثال: تمريض، محاسبة، إدارة..."
                                /><InputError
                                    :message="form.errors.education_specialty"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>سنة التخرج</Label
                                ><Input
                                    v-model="form.graduation_year"
                                    type="number"
                                    min="1950"
                                    max="2100"
                                    placeholder="2020"
                                /><InputError
                                    :message="form.errors.graduation_year"
                                />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <Label>الجهة أو الجامعة المانحة</Label
                                ><Input
                                    v-model="form.issuing_institution"
                                    placeholder="مثال: جامعة دمشق..."
                                /><InputError
                                    :message="form.errors.issuing_institution"
                                />
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend
                            class="mb-2 border-b border-border pb-2 text-sm font-bold text-primary"
                        >
                            بيانات الراتب
                        </legend>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="grid gap-2">
                                <Label
                                    >الراتب الشهري الأساسي
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                ><Input
                                    v-model="form.base_salary"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                /><InputError
                                    :message="form.errors.base_salary"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>بدل إضافي</Label
                                ><Input
                                    v-model="form.additional_allowance"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="0"
                                /><InputError
                                    :message="form.errors.additional_allowance"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>ملاحظات الراتب</Label
                                ><Input
                                    v-model="form.salary_notes"
                                /><InputError
                                    :message="form.errors.salary_notes"
                                />
                            </div>
                        </div>
                    </fieldset>

                    <fieldset v-if="!isEditing" class="space-y-3">
                        <legend
                            class="mb-2 border-b border-border pb-2 text-sm font-bold text-primary"
                        >
                            إنشاء حساب مستخدم (اختياري)
                        </legend>
                        <div class="flex items-center gap-3">
                            <Switch
                                id="create-account"
                                v-model:checked="form.create_account"
                            />
                            <Label
                                for="create-account"
                                class="cursor-pointer text-sm font-medium"
                                >إنشاء حساب دخول للنظام</Label
                            >
                        </div>
                        <div
                            v-if="form.create_account"
                            class="grid gap-4 md:grid-cols-3"
                        >
                            <div class="grid gap-2">
                                <Label
                                    >البريد الإلكتروني
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                ><Input
                                    v-model="form.email"
                                    type="email"
                                    placeholder="employee@clinic.com"
                                /><InputError :message="form.errors.email" />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    >كلمة المرور
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                ><Input
                                    v-model="form.password"
                                    type="password"
                                    placeholder="8 أحرف على الأقل"
                                /><InputError :message="form.errors.password" />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    >الصلاحية
                                    <span class="text-destructive"
                                        >*</span
                                    ></Label
                                ><select
                                    v-model="form.role_name"
                                    class="h-10 rounded-md border border-input bg-muted px-3"
                                >
                                    <option
                                        v-for="role in options.account_roles"
                                        :key="role"
                                        :value="role"
                                    >
                                        {{ labelFor(role) }}
                                    </option></select
                                ><InputError :message="form.errors.role_name" />
                            </div>
                        </div>
                    </fieldset>
                </form>
                <DialogFooter class="border-t border-border px-6 py-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showForm = false"
                        ><X class="size-4" />إلغاء</Button
                    >
                    <Button
                        type="button"
                        class="bg-primary text-primary-foreground hover:bg-primary/90"
                        :disabled="form.processing"
                        @click="submit"
                        >{{
                            isEditing ? 'حفظ التعديلات' : 'حفظ الموظف'
                        }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="viewing !== null" @update:open="viewing = null">
            <DialogContent
                class="max-h-[90vh] max-w-4xl overflow-hidden rounded-lg bg-card"
                dir="rtl"
            >
                <DialogHeader
                    class="border-b border-border px-6 py-4 text-right"
                    ><DialogTitle class="text-foreground">{{
                        viewing?.full_name
                    }}</DialogTitle></DialogHeader
                >
                <div
                    v-if="viewing"
                    class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-5"
                >
                    <fieldset class="space-y-3">
                        <legend
                            class="mb-2 border-b border-border pb-2 text-sm font-bold text-primary"
                        >
                            البيانات الشخصية
                        </legend>
                        <div
                            class="grid gap-3 text-sm text-foreground md:grid-cols-2"
                        >
                            <p><b>الاسم الكامل:</b> {{ viewing.full_name }}</p>
                            <p><b>الجنس:</b> {{ labelFor(viewing.gender) }}</p>
                            <p>
                                <b>تاريخ الميلاد:</b>
                                {{ viewing.birth_date ?? '-' }}
                            </p>
                            <p><b>رقم الهاتف:</b> {{ viewing.phone }}</p>
                            <p>
                                <b>الرقم الوطني:</b>
                                {{ viewing.national_id ?? '-' }}
                            </p>
                            <p>
                                <b>الحالة الاجتماعية:</b>
                                {{ labelFor(viewing.marital_status) }}
                            </p>
                            <p><b>تاريخ التعيين:</b> {{ viewing.hire_date }}</p>
                            <p>
                                <b>الحالة:</b>
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                    :class="
                                        viewing.status === 'active'
                                            ? 'bg-success/10 text-success'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                    >{{ labelFor(viewing.status) }}</span
                                >
                            </p>
                            <p class="md:col-span-2">
                                <b>العنوان:</b> {{ viewing.address ?? '-' }}
                            </p>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend
                            class="mb-2 border-b border-border pb-2 text-sm font-bold text-primary"
                        >
                            بيانات الوظيفة
                        </legend>
                        <div
                            class="grid gap-3 text-sm text-foreground md:grid-cols-2"
                        >
                            <p>
                                <b>نوع الموظف:</b>
                                {{ labelFor(viewing.employee_type) }}
                            </p>
                            <p>
                                <b>المسمى الوظيفي:</b> {{ viewing.job_title }}
                            </p>
                            <p>
                                <b>القسم / العيادة:</b>
                                {{ viewing.department?.name ?? '-' }}
                            </p>
                            <p>
                                <b>الاختصاص:</b> {{ viewing.specialty ?? '-' }}
                            </p>
                            <p class="md:col-span-2">
                                <b>وصف المهام:</b>
                                {{ viewing.job_description ?? '-' }}
                            </p>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend
                            class="mb-2 border-b border-border pb-2 text-sm font-bold text-primary"
                        >
                            البيانات العلمية
                        </legend>
                        <div
                            class="grid gap-3 text-sm text-foreground md:grid-cols-2"
                        >
                            <p>
                                <b>المستوى العلمي:</b>
                                {{ labelFor(viewing.education_level) }}
                            </p>
                            <p>
                                <b>اسم الشهادة:</b>
                                {{ viewing.certificate_name ?? '-' }}
                            </p>
                            <p>
                                <b>اختصاص الشهادة:</b>
                                {{ viewing.education_specialty ?? '-' }}
                            </p>
                            <p>
                                <b>سنة التخرج:</b>
                                {{ viewing.graduation_year ?? '-' }}
                            </p>
                            <p class="md:col-span-2">
                                <b>الجهة المانحة:</b>
                                {{ viewing.issuing_institution ?? '-' }}
                            </p>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend
                            class="mb-2 border-b border-border pb-2 text-sm font-bold text-primary"
                        >
                            بيانات الراتب
                        </legend>
                        <div
                            class="grid gap-3 text-sm text-foreground md:grid-cols-2"
                        >
                            <p>
                                <b>الراتب الأساسي:</b>
                                <span class="font-mono">{{
                                    formatMoney(viewing.base_salary)
                                }}</span>
                            </p>
                            <p>
                                <b>البدل الإضافي:</b>
                                <span class="font-mono">{{
                                    viewing.additional_allowance !== null
                                        ? formatMoney(
                                              viewing.additional_allowance,
                                          )
                                        : '-'
                                }}</span>
                            </p>
                            <p class="md:col-span-2">
                                <b>ملاحظات الراتب:</b>
                                {{ viewing.salary_notes ?? '-' }}
                            </p>
                        </div>
                    </fieldset>

                    <fieldset v-if="viewing.user" class="space-y-3">
                        <legend
                            class="mb-2 border-b border-border pb-2 text-sm font-bold text-primary"
                        >
                            حساب النظام
                        </legend>
                        <div
                            class="grid gap-3 text-sm text-foreground md:grid-cols-2"
                        >
                            <p><b>اسم المستخدم:</b> {{ viewing.user.name }}</p>
                            <p>
                                <b>البريد الإلكتروني:</b>
                                {{ viewing.user.email }}
                            </p>
                        </div>
                    </fieldset>
                </div>
            </DialogContent>
        </Dialog>

        <ConfirmationDialog
            :open="isConfirmOpen"
            :options="confirmOptions"
            @confirm="handleConfirmDelete"
            @cancel="handleConfirmCancel"
            @update:open="handleConfirmCancel"
        />
    </div>
</template>
