<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
} from 'lucide-vue-next';
import { computed } from 'vue';
import DepartmentController from '@/actions/App/Http/Controllers/Departments/DepartmentController';
import { Button } from '@/components/ui/button';
import {
    FilterBar,
    FilterSearch,
    FilterSelect,
} from '@/components/ui/filter';
import { Label } from '@/components/ui/label';
import type { ActiveFilter, Department, DepartmentSortField, SortDirection } from './types';

const search = defineModel<string>('search', { default: '' });
const activeFilter = defineModel<ActiveFilter>('activeFilter', { default: 'all' });
const rowsPerPage = defineModel<number>('rowsPerPage', { default: 15 });
const selectedIds = defineModel<number[]>('selectedIds', { default: () => [] });

const props = defineProps<{
    departments: Department[];
    page: number;
    totalPages: number;
    visibleFrom: number;
    visibleTo: number;
    totalDepartments: number;
    sortBy: DepartmentSortField;
    sortDirection: SortDirection;
    areAllSelected: boolean;
    canDelete: boolean;
    canUpdate: boolean;
}>();

const emit = defineEmits<{
    'toggle-sort': [field: DepartmentSortField];
    'previous-page': [];
    'next-page': [];
    'reset-filters': [];
    'remove-filter': [key: string];
    'toggle-all-selection': [event: Event];
    'clear-selection': [];
    view: [department: Department];
    edit: [department: Department];
    delete: [department: Department];
}>();

const statusOptions = computed(() => [
    { label: 'الكل', value: 'all' },
    { label: 'نشط', value: 'active' },
    { label: 'غير نشط', value: 'inactive' },
]);

const activeFilters = computed(() => {
    const filters: { key: string; label: string; value: string | null }[] = [];

    if (search.value.trim()) {
        filters.push({ key: 'search', label: 'بحث', value: search.value.trim() });
    }

    if (activeFilter.value !== 'all') {
        filters.push({ key: 'is_active', label: 'الحالة', value: activeFilter.value === 'active' ? 'نشط' : 'غير نشط' });
    }

    return filters;
});

const sortIconFor = (field: DepartmentSortField) => {
    if (props.sortBy !== field) {
        return ArrowUpDown;
    }

    return props.sortDirection === 'asc' ? ArrowUp : ArrowDown;
};

const departmentStatusClass = (isActive: boolean): string => {
    if (isActive) {
        return 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/35 dark:bg-success-500/15 dark:text-success-100';
    }

    return 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/35 dark:bg-destructive/15 dark:text-destructive-foreground';
};

const departmentStatusDotClass = (isActive: boolean): string => {
    return isActive ? 'bg-success-500' : 'bg-destructive';
};

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        search.value = '';
    } else if (key === 'is_active') {
        activeFilter.value = 'all';
    }
};
</script>

<template>
    <div class="glass-panel-soft p-5">
        <div
            class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3"
        >
            <h3 class="pattern-typographic-title text-[0.76rem]">
                قائمة الأقسام
            </h3>
            <span class="text-xs text-muted-foreground">
                الإجمالي: {{ totalDepartments }}
            </span>
        </div>

        <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
            <div class="grid gap-3 md:grid-cols-[repeat(3,minmax(0,1fr))] md:items-end">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="departments_search">بحث</Label>
                    <FilterSearch
                        id="departments_search"
                        v-model="search"
                        placeholder="الاسم، الرمز، أو الوصف"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="departments_status">الحالة</Label>
                    <FilterSelect
                        id="departments_status"
                        v-model="activeFilter"
                        :options="statusOptions"
                        placeholder="الكل"
                    />
                </div>
            </div>
            <div class="grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))] md:items-end">
                <div class="grid gap-2 md:max-w-44">
                    <Label for="departments_per_page">صفوف لكل صفحة</Label>
                    <select
                        id="departments_per_page"
                        v-model.number="rowsPerPage"
                        class="pattern-field-clay h-9 px-3 py-1.5"
                    >
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
            <FilterBar
                v-if="activeFilters.length > 0"
                :active-filters="activeFilters"
                @remove="handleRemoveFilter"
                @clear-all="emit('reset-filters')"
            />
        </div>

        <Form
            v-if="canDelete && selectedIds.length > 0"
            v-bind="DepartmentController.bulkDestroy.form()"
            class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
            v-slot="{ processing }"
        >
            <input
                v-for="departmentId in selectedIds"
                :key="`selected-department-${departmentId}`"
                type="hidden"
                name="ids[]"
                :value="departmentId"
            />

            <Button
                type="submit"
                variant="destructive"
                size="sm"
                :disabled="processing"
            >
                حذف المحدد ({{ selectedIds.length }})
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="emit('clear-selection')"
            >
                إلغاء التحديد
            </Button>
        </Form>

        <div class="ui-table-shell">
            <table class="ui-table md:min-w-[940px]">
                <thead>
                    <tr>
                        <th
                            v-if="canDelete"
                            class="px-3 py-2"
                        >
                            <input
                                type="checkbox"
                                class="size-4 rounded border-border"
                                :checked="areAllSelected"
                                @change="emit('toggle-all-selection', $event)"
                            />
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'name')"
                            >
                                الاسم
                                <component
                                    :is="sortIconFor('name')"
                                    class="size-3.5"
                                />
                            </button>
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'code')"
                            >
                                الرمز
                                <component
                                    :is="sortIconFor('code')"
                                    class="size-3.5"
                                />
                            </button>
                        </th>
                        <th class="px-3 py-2">الوصف</th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'doctor_profiles_count')"
                            >
                                الأطباء
                                <component
                                    :is="sortIconFor('doctor_profiles_count')"
                                    class="size-3.5"
                                />
                            </button>
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'is_active')"
                            >
                                الحالة
                                <component
                                    :is="sortIconFor('is_active')"
                                    class="size-3.5"
                                />
                            </button>
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'created_at')"
                            >
                                تاريخ الإنشاء
                                <component
                                    :is="sortIconFor('created_at')"
                                    class="size-3.5"
                                />
                            </button>
                        </th>
                        <th class="px-3 py-2 text-start">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="department in departments"
                        :key="department.id"
                        class="ui-table-row align-top"
                    >
                        <td
                            v-if="canDelete"
                            class="px-3 py-2"
                            data-label="تحديد"
                        >
                            <input
                                v-model="selectedIds"
                                type="checkbox"
                                class="size-4 rounded border-border"
                                :value="department.id"
                            />
                        </td>
                        <td
                            class="px-3 py-2 font-medium"
                            data-label="الاسم"
                        >
                            {{ department.name }}
                        </td>
                        <td class="px-3 py-2" data-label="الرمز">
                            {{ department.code ?? '-' }}
                        </td>
                        <td
                            class="max-w-xs px-3 py-2"
                            data-label="الوصف"
                        >
                            <span
                                class="line-clamp-2 text-sm text-muted-foreground"
                            >
                                {{ department.description ?? '-' }}
                            </span>
                        </td>
                        <td class="px-3 py-2" data-label="الأطباء">
                            <span
                                class="inline-flex rounded-full border border-border/70 bg-background/80 px-2.5 py-1 text-xs font-medium"
                            >
                                {{ department.doctor_profiles_count }}
                            </span>
                        </td>
                        <td class="px-3 py-2" data-label="الحالة">
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="
                                    departmentStatusClass(
                                        department.is_active,
                                    )
                                "
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="
                                        departmentStatusDotClass(
                                            department.is_active,
                                        )
                                    "
                                ></span>
                                {{
                                    department.is_active
                                        ? 'نشط'
                                        : 'غير نشط'
                                }}
                            </span>
                        </td>
                        <td
                            class="px-3 py-2"
                            data-label="تاريخ الإنشاء"
                        >
                            {{
                                department.created_at !== null
                                    ? new Date(
                                          department.created_at,
                                      ).toLocaleDateString('ar-EG')
                                    : '-'
                            }}
                        </td>
                        <td
                            class="table-cell-actions px-3 py-2 md:text-start"
                            data-label="الإجراءات"
                        >
                            <div
                                class="flex flex-wrap justify-end gap-2"
                            >
                                <Button
                                    type="button"
                                    variant="neumorphic"
                                    size="sm"
                                    class="h-8 px-3 text-xs"
                                    @click="emit('view', department)"
                                >
                                    عرض
                                </Button>
                                <Button
                                    v-if="canUpdate"
                                    type="button"
                                    variant="clay"
                                    size="sm"
                                    class="h-8 px-3 text-xs"
                                    @click="emit('edit', department)"
                                >
                                    تعديل
                                </Button>
                                <Button
                                    v-if="canDelete"
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    class="h-8 px-3 text-xs"
                                    @click="emit('delete', department)"
                                >
                                    حذف
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr
                        v-if="departments.length === 0"
                        class="table-empty-state"
                    >
                        <td
                            :colspan="canDelete ? 8 : 7"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            لا توجد أقسام مطابقة.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2"
        >
            <p class="text-xs text-muted-foreground">
                عرض {{ visibleFrom }}-{{ visibleTo }} من
                {{ totalDepartments }} سجل
            </p>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="neumorphic"
                    size="sm"
                    class="h-8 px-3 text-xs"
                    :disabled="page === 1"
                    @click="emit('previous-page')"
                >
                    السابق
                </Button>
                <span class="text-xs font-semibold text-foreground/85">
                    صفحة {{ page }} / {{ totalPages }}
                </span>
                <Button
                    type="button"
                    variant="neumorphic"
                    size="sm"
                    class="h-8 px-3 text-xs"
                    :disabled="page >= totalPages"
                    @click="emit('next-page')"
                >
                    التالي
                </Button>
            </div>
        </div>
    </div>
</template>