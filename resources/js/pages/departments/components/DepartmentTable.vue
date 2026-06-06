<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Clock,
    Eye,
    Filter,
    Pencil,
    Trash2,
} from 'lucide-vue-next';
import { computed } from 'vue';
import DepartmentController from '@/actions/App/Http/Controllers/Departments/DepartmentController';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch, FilterSelect } from '@/components/ui/filter';
import type {
    ActiveFilter,
    Department,
    DepartmentSortField,
    SortDirection,
} from './types';

const search = defineModel<string>('search', { default: '' });
const activeFilter = defineModel<ActiveFilter>('activeFilter', {
    default: 'all',
});
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
    'toggle-all-selection': [event: Event];
    'clear-selection': [];
    view: [department: Department];
    edit: [department: Department];
    delete: [department: Department];
}>();

const statusOptions = computed(() => [
    { label: 'الكل', value: 'all' },
    { label: 'نشطة', value: 'active' },
    { label: 'غير نشطة', value: 'inactive' },
]);

const activeFilters = computed(() => {
    const filters: { key: string; label: string; value: string | null }[] = [];

    if (search.value.trim()) {
        filters.push({
            key: 'search',
            label: 'بحث',
            value: search.value.trim(),
        });
    }

    if (activeFilter.value !== 'all') {
        filters.push({
            key: 'is_active',
            label: 'الحالة',
            value: activeFilter.value === 'active' ? 'نشطة' : 'غير نشطة',
        });
    }

    return filters;
});

const handleRemoveFilter = (key: string) => {
    if (key === 'search') {
        search.value = '';
    }

    if (key === 'is_active') {
        activeFilter.value = 'all';
    }
};

const activeHoursCount = (department: Department): number => {
    return department.working_hours?.filter((row) => row.is_active).length ?? 0;
};

const statusClass = (isActive: boolean): string => {
    return isActive
        ? 'bg-[#DBEAFE] text-[#1D4ED8]'
        : 'bg-[#F4F7FA] text-[#6B7280]';
};

const sortMark = (field: DepartmentSortField): string => {
    if (props.sortBy !== field) {
        return '↕';
    }

    return props.sortDirection === 'asc' ? '↑' : '↓';
};
</script>

<template>
    <div class="space-y-8">
        <section
            class="rounded-[1.45rem] border border-[#E2ECF6] bg-white/95 p-6 shadow-card-float"
        >
            <div class="grid gap-4 lg:grid-cols-[1fr_18rem_9rem]">
                <FilterSearch
                    v-model="search"
                    placeholder="البحث في بيانات العيادات..."
                />

                <FilterSelect
                    v-model="activeFilter"
                    :options="statusOptions"
                    placeholder="الحالة"
                />

                <button
                    type="button"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl border border-[#E8EEF6] bg-white px-5 text-sm font-semibold text-[#1A2B3F] shadow-[0_10px_24px_-24px_rgb(15_42_71_/_0.35)] transition-all duration-200 hover:bg-[#F7FBFE] hover:text-[#075985]"
                    @click="emit('reset-filters')"
                >
                    <Filter class="size-4" />
                    تصفية
                </button>
            </div>

            <FilterBar
                v-if="activeFilters.length > 0"
                class="mt-4"
                :active-filters="activeFilters"
                @remove="handleRemoveFilter"
                @clear-all="emit('reset-filters')"
            />
        </section>

        <Form
            v-if="canDelete && selectedIds.length > 0"
            v-bind="DepartmentController.bulkDestroy.form()"
            class="flex items-center gap-3 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 shadow-card"
            v-slot="{ processing }"
        >
            <input
                v-for="departmentId in selectedIds"
                :key="`selected-clinic-${departmentId}`"
                type="hidden"
                name="ids[]"
                :value="departmentId"
            />

            <p class="flex-1 text-sm font-semibold text-[#DC2626]">
                {{ selectedIds.length }} عيادة محددة
            </p>
            <Button
                type="submit"
                variant="destructive"
                size="sm"
                class="h-9 rounded-xl px-4 text-xs"
                :disabled="processing"
            >
                حذف
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-9 rounded-xl px-4 text-xs text-[#6B7280] hover:bg-white"
                @click="emit('clear-selection')"
            >
                إلغاء
            </Button>
        </Form>

        <section
            class="overflow-hidden rounded-[1.35rem] border border-[#DDE8F3] bg-white/95 shadow-card-float"
        >
            <div class="w-full overflow-hidden">
                <table
                    class="w-full table-fixed border-separate border-spacing-0 [&_td]:align-middle [&_th]:align-middle"
                >
                    <colgroup>
                        <col class="w-[5%]" />
                        <col class="w-[24%]" />
                        <col class="w-[12%]" />
                        <col class="w-[11%]" />
                        <col class="w-[13%]" />
                        <col class="w-[12%]" />
                        <col class="w-[13%]" />
                        <col class="w-[10%]" />
                    </colgroup>
                    <thead>
                        <tr class="h-16 bg-[#F3F8FC]">
                            <th
                                class="px-2 py-3 text-center text-sm font-bold text-[#111827]"
                            >
                                #
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-[#111827] transition-colors select-none hover:text-[#0284C7]"
                                @click="emit('toggle-sort', 'name')"
                            >
                                اسم العيادة
                                <span class="ms-1 text-[#A8B8C8]">{{
                                    sortMark('name')
                                }}</span>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-[#111827] transition-colors select-none hover:text-[#0284C7]"
                                @click="emit('toggle-sort', 'code')"
                            >
                                الرمز
                                <span class="ms-1 text-[#A8B8C8]">{{
                                    sortMark('code')
                                }}</span>
                            </th>
                            <!-- <th class="min-w-64 px-5 py-3 text-right text-sm font-bold text-[#111827]">الوصف</th> -->
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-[#111827] transition-colors select-none hover:text-[#0284C7]"
                                @click="
                                    emit('toggle-sort', 'doctor_profiles_count')
                                "
                            >
                                الأطباء
                                <span class="ms-1 text-[#A8B8C8]">{{
                                    sortMark('doctor_profiles_count')
                                }}</span>
                            </th>
                            <th
                                class="px-3 py-3 text-center text-sm font-bold text-[#111827]"
                            >
                                أيام الدوام
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-[#111827] transition-colors select-none hover:text-[#0284C7]"
                                @click="emit('toggle-sort', 'is_active')"
                            >
                                الحالة
                                <span class="ms-1 text-[#A8B8C8]">{{
                                    sortMark('is_active')
                                }}</span>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-[#111827] transition-colors select-none hover:text-[#0284C7]"
                                @click="emit('toggle-sort', 'created_at')"
                            >
                                تاريخ الإضافة
                                <span class="ms-1 text-[#A8B8C8]">{{
                                    sortMark('created_at')
                                }}</span>
                            </th>
                            <th
                                class="px-3 py-3 text-center text-sm font-bold text-[#111827]"
                            >
                                الإجراءات
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(department, index) in departments"
                            :key="department.id"
                            class="group h-20 border-b border-[#E8EEF6] transition-all duration-150 last:border-b-0 hover:bg-[#F8FCFF]"
                        >
                            <td
                                class="px-2 py-4 text-center text-sm font-bold text-[#111827]"
                            >
                                {{ visibleFrom + index }}
                            </td>
                            <td class="px-3 py-4 text-center">
                                <div
                                    class="flex items-center justify-center gap-3"
                                >
                                    <span
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#0EA5E9] text-sm font-bold text-white shadow-[0_12px_24px_-18px_rgb(14_165_233_/_0.95)]"
                                    >
                                        {{ department.name.charAt(0) }}
                                    </span>
                                    <span
                                        class="min-w-0 truncate text-sm font-semibold text-[#111827]"
                                        >{{ department.name }}</span
                                    >
                                </div>
                            </td>
                            <td
                                class="px-3 py-4 text-center text-sm font-semibold text-[#111827]"
                            >
                                {{ department.code ?? '-' }}
                            </td>
                            <!-- <td class="max-w-xs px-5 py-4 text-sm text-[#6C7F95]">
                                <span class="line-clamp-2">{{ department.description ?? '-' }}</span>
                            </td> -->
                            <td class="px-3 py-4 text-center">
                                <span
                                    class="inline-flex min-w-10 items-center justify-center rounded-full bg-[#F4F7FA] px-3 py-1.5 text-xs font-bold text-[#111827]"
                                >
                                    {{ department.doctor_profiles_count }}
                                </span>
                            </td>
                            <td class="px-3 py-4 text-center">
                                <span
                                    class="inline-flex items-center justify-center gap-2 rounded-full bg-[#EAF7FE] px-3 py-1.5 text-xs font-bold text-[#0284C7]"
                                >
                                    <Clock class="size-3.5" />
                                    {{ activeHoursCount(department) }} أيام
                                </span>
                            </td>
                            <td class="px-3 py-4 text-center">
                                <span
                                    class="inline-flex min-w-20 items-center justify-center rounded-full px-3 py-1.5 text-xs font-bold"
                                    :class="statusClass(department.is_active)"
                                >
                                    {{
                                        department.is_active
                                            ? 'نشطة'
                                            : 'غير نشطة'
                                    }}
                                </span>
                            </td>
                            <td
                                class="px-3 py-4 text-center text-sm text-[#111827]"
                            >
                                {{
                                    department.created_at !== null
                                        ? new Date(
                                              department.created_at,
                                          ).toLocaleDateString('ar-EG')
                                        : '-'
                                }}
                            </td>
                            <td class="px-3 py-4 text-center">
                                <div
                                    class="flex items-center justify-center gap-2"
                                >
                                    <button
                                        v-if="canDelete"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-[#FF3B30] transition-all duration-150 hover:bg-[#FEF2F2] active:scale-[0.95]"
                                        title="حذف"
                                        @click="emit('delete', department)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                    <button
                                        v-if="canUpdate"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-[#2563EB] transition-all duration-150 hover:bg-[#EFF6FF] active:scale-[0.95]"
                                        title="تعديل"
                                        @click="emit('edit', department)"
                                    >
                                        <Pencil class="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-[#0EA5E9] transition-all duration-150 hover:bg-[#EAF7FE] active:scale-[0.95]"
                                        title="عرض"
                                        @click="emit('view', department)"
                                    >
                                        <Eye class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="departments.length === 0">
                            <td colspan="8" class="px-5">
                                <div class="py-20 text-center">
                                    <h3
                                        class="mb-2 text-base font-bold text-[#111827]"
                                    >
                                        لا توجد عيادات
                                    </h3>
                                    <p class="text-sm text-[#6C7F95]">
                                        غيّر التصفية أو أضف عيادة جديدة للبدء.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div
            class="flex flex-col gap-4 px-2 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-[#E8EEF6] bg-white text-[#93A4B7] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition hover:text-[#075985] disabled:opacity-40"
                        :disabled="page === 1"
                        @click="emit('previous-page')"
                    >
                        <ChevronsRight class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-[#E8EEF6] bg-white text-[#93A4B7] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition hover:text-[#075985] disabled:opacity-40"
                        :disabled="page === 1"
                        @click="emit('previous-page')"
                    >
                        <ChevronRight class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-[#E8EEF6] bg-white text-[#93A4B7] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition hover:text-[#075985] disabled:opacity-40"
                        :disabled="page >= totalPages"
                        @click="emit('next-page')"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-[#E8EEF6] bg-white text-[#93A4B7] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition hover:text-[#075985] disabled:opacity-40"
                        :disabled="page >= totalPages"
                        @click="emit('next-page')"
                    >
                        <ChevronsLeft class="size-4" />
                    </button>
                </div>

                <span class="text-sm font-semibold text-[#111827]"
                    >صفحة {{ page }} من {{ totalPages }}</span
                >

                <div class="flex items-center gap-3">
                    <select
                        v-model.number="rowsPerPage"
                        class="h-11 rounded-2xl border border-[#DDE9F3] bg-white px-4 text-sm font-semibold text-[#1A2B3F] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10 focus:outline-none"
                    >
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <span class="text-sm font-semibold text-[#111827]"
                        >عدد الصفوف لكل صفحة</span
                    >
                </div>
            </div>

            <p class="text-sm font-medium text-[#6C7F95]">
                عرض {{ visibleFrom }} إلى {{ visibleTo }} من
                {{ totalDepartments }} عيادة
            </p>
        </div>
    </div>
</template>
