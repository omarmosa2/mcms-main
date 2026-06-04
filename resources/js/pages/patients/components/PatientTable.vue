<script setup lang="ts">
import { Eye, Pencil, Trash2, MoreVertical, Users } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch } from '@/components/ui/filter';
import { usePermissions } from '@/composables/usePermissions';
import type { ActiveFilter, Patient, PatientSortField, SortDirection } from './types';

const props = defineProps<{
    patients: Patient[];
    search: string;
    rowsPerPage: number;
    currentPage: number;
    totalPages: number;
    visibleFrom: number;
    visibleTo: number;
    totalRecords: number;
    sortBy: PatientSortField;
    sortDirection: SortDirection;
    selectedIds: number[];
    areAllSelected: boolean;
    activeFilters: ActiveFilter[];
}>();

const emit = defineEmits<{
    'update:search': [value: string];
    'update:rowsPerPage': [value: number];
    'previous-page': [];
    'next-page': [];
    'sort': [field: PatientSortField];
    'remove-filter': [key: string];
    'clear-filters': [];
    'toggle-select-all': [checked: boolean];
    'update:selectedIds': [ids: number[]];
    'delete': [patient: Patient];
    'edit': [patient: Patient];
    'bulk-delete': [];
    'clear-selection': [];
    'toggle-quick-add': [];
}>();

const { can } = usePermissions();

const sortIconFor = (field: PatientSortField) => {
    if (props.sortBy !== field) {
        return null;
    }

    return props.sortDirection === 'asc' ? 'asc' : 'desc';
};

const patientGenderClass = (gender: string | null): string => {
    if (gender === 'male') {
        return 'bg-[#DBEAFE] text-[#1D4ED8]';
    }

    if (gender === 'female') {
        return 'bg-[#FDF2F8] text-[#9D174D]';
    }

    return 'bg-[#F3F4F6] text-[#6B7280]';
};

const patientGenderLabel = (gender: string | null): string => {
    const labels: Record<string, string> = {
        male: 'ذكر',
        female: 'أنثى',
        other: 'آخر',
    };

    return labels[gender ?? ''] ?? 'غير محدد';
};

const selectablePatientIds = computed<number[]>(() =>
    props.patients.map((patient) => patient.id),
);

const handleToggleAll = (event: Event) => {
    const target = event.target as HTMLInputElement;
    emit('toggle-select-all', target.checked);
};

const handleRowCheckbox = (patientId: number) => {
    const newSelectedIds = props.selectedIds.includes(patientId)
        ? props.selectedIds.filter((id) => id !== patientId)
        : [...props.selectedIds, patientId];
    emit('update:selectedIds', newSelectedIds);
};

const handleRemoveFilter = (key: string) => {
    emit('remove-filter', key);
};

const handleClearFilters = () => {
    emit('clear-filters');
};

const handleSort = (field: PatientSortField) => {
    emit('sort', field);
};

const handlePreviousPage = () => {
    if (props.currentPage > 1) {
        emit('previous-page');
    }
};

const handleNextPage = () => {
    if (props.currentPage < props.totalPages) {
        emit('next-page');
    }
};

const handleSearch = (value: string) => {
    emit('update:search', value);
};

const handleRowsPerPage = (value: number) => {
    emit('update:rowsPerPage', value);
};
</script>

<template>
    <div class="bg-white rounded-xl border border-[#E5E7EB] overflow-hidden shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 p-5 border-b border-[#E5E7EB] bg-[#FAFBFC]">
            <div class="flex items-center gap-3">
                <select
                    :value="rowsPerPage"
                    class="h-9 rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm text-[#6B7280] focus:outline-none focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10 transition-colors appearance-none cursor-pointer hover:border-[#0EA5E9]/50"
                    @change="(e) => handleRowsPerPage(Number((e.target as HTMLSelectElement).value))"
                >
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                <span class="text-xs font-medium text-[#6B7280]">
                    <span class="text-[#0EA5E9]">{{ totalRecords }}</span> 
                    سجل
                </span>
            </div>
            <div class="relative flex-1 md:flex-none md:w-70">
                <FilterSearch
                    :model-value="search"
                    placeholder="ابحث برقم الملف، الاسم، الهاتف..."
                    @update:model-value="handleSearch"
                />
            </div>
        </div>

        <FilterBar
            v-if="activeFilters.length > 0"
            :active-filters="activeFilters"
            @remove="handleRemoveFilter"
            @clear-all="handleClearFilters"
        />

        <div
            v-if="can('patient.delete') && selectedIds.length > 0"
            class="mx-5 my-4 flex items-center gap-2 rounded-lg border border-[#DC2626]/20 bg-[#FEF2F2] p-4"
        >
            <div class="flex-1">
                <p class="text-sm font-medium text-[#DC2626]">{{ selectedIds.length }} مريض محدد</p>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    class="rounded-lg h-8 px-3 text-xs"
                    @click="emit('bulk-delete')"
                >
                    حذف
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="rounded-lg h-8 px-3 text-xs text-[#6B7280] hover:bg-white"
                    @click="emit('clear-selection')"
                >
                    إلغاء
                </Button>
            </div>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="min-w-full border-separate border-spacing-0">
                <thead>
                    <tr class="bg-[#F9FAFB] h-12">
                        <th v-if="can('patient.delete')" class="px-5 text-right text-xs font-semibold text-[#374151] select-none">
                            <input
                                type="checkbox"
                                class="size-4 rounded border-[#E5E7EB] cursor-pointer"
                                :checked="areAllSelected"
                                @change="handleToggleAll"
                            />
                        </th>
                        <th
                            class="px-5 py-3 text-right text-xs font-semibold text-[#374151] select-none whitespace-nowrap cursor-pointer hover:text-[#0EA5E9] transition-colors group"
                            @click="handleSort('file_number')"
                        >
                            <div class="flex items-center gap-1.5 justify-end">
                                <span class="group-hover:underline">رقم الملف</span>
                            </div>
                        </th>
                        <th
                            class="px-5 py-3 text-right text-xs font-semibold text-[#374151] select-none whitespace-nowrap cursor-pointer hover:text-[#0EA5E9] transition-colors group"
                            @click="handleSort('full_name')"
                        >
                            <div class="flex items-center gap-1.5 justify-end">
                                <span class="group-hover:underline">الاسم</span>
                            </div>
                        </th>
                        <th
                            class="px-5 py-3 text-right text-xs font-semibold text-[#374151] select-none whitespace-nowrap cursor-pointer hover:text-[#0EA5E9] transition-colors group"
                            @click="handleSort('date_of_birth')"
                        >
                            <div class="flex items-center gap-1.5 justify-end">
                                <span class="group-hover:underline">تاريخ الميلاد</span>
                            </div>
                        </th>
                        <th
                            class="px-5 py-3 text-right text-xs font-semibold text-[#374151] select-none whitespace-nowrap cursor-pointer hover:text-[#0EA5E9] transition-colors group"
                            @click="handleSort('gender')"
                        >
                            <div class="flex items-center gap-1.5 justify-end">
                                <span class="group-hover:underline">الجنس</span>
                            </div>
                        </th>
                        <th
                            class="px-5 py-3 text-right text-xs font-semibold text-[#374151] select-none whitespace-nowrap cursor-pointer hover:text-[#0EA5E9] transition-colors group"
                            @click="handleSort('phone')"
                        >
                            <div class="flex items-center gap-1.5 justify-end">
                                <span class="group-hover:underline">الهاتف</span>
                            </div>
                        </th>
                        <th
                            class="px-5 py-3 text-right text-xs font-semibold text-[#374151] select-none whitespace-nowrap cursor-pointer hover:text-[#0EA5E9] transition-colors group"
                            @click="handleSort('email')"
                        >
                            <div class="flex items-center gap-1.5 justify-end">
                                <span class="group-hover:underline">البريد</span>
                            </div>
                        </th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-[#374151] select-none whitespace-nowrap">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="patient in patients"
                        :key="patient.id"
                        class="h-14 border-b border-[#E5E7EB] last:border-b-0 hover:bg-[#F9FAFB] transition-all duration-150 group"
                    >
                        <td v-if="can('patient.delete')" class="px-5 py-3" data-label="تحديد">
                            <input
                                type="checkbox"
                                class="size-4 rounded border-[#E5E7EB] cursor-pointer"
                                :checked="selectedIds.includes(patient.id)"
                                @change="handleRowCheckbox(patient.id)"
                            />
                        </td>
                        <td class="px-5 py-3 text-sm font-mono text-[#9CA3AF] font-medium" data-label="رقم الملف">
                            {{ patient.file_number }}
                        </td>
                        <td class="px-5 py-3 text-sm font-semibold text-[#1A1A1A]" data-label="الاسم">
                            {{ patient.full_name }}
                        </td>
                        <td class="px-5 py-3 text-sm text-[#6B7280]" data-label="تاريخ الميلاد">
                            <span :class="patient.date_of_birth ? 'text-[#6B7280]' : 'text-[#9CA3AF]'">
                                {{ patient.date_of_birth ?? 'غير محدد' }}
                            </span>
                        </td>
                        <td class="px-5 py-3" data-label="الجنس">
                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                :class="patientGenderClass(patient.gender)"
                            >
                                {{ patientGenderLabel(patient.gender) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-sm text-[#6B7280]" data-label="الهاتف">
                            <span :class="patient.phone ? 'text-[#6B7280]' : 'text-[#9CA3AF]'">
                                {{ patient.phone ?? 'غير محدد' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-sm text-[#6B7280]" data-label="البريد">
                            <span
                                class="block truncate max-w-50"
                                :title="patient.email ?? 'غير محدد'"
                                :class="patient.email ? 'text-[#6B7280]' : 'text-[#9CA3AF]'"
                            >
                                {{ patient.email ?? 'غير محدد' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 md:text-right" data-label="الإجراءات">
                            <div class="hidden md:flex items-center justify-end gap-2">
                                <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-all duration-150 -mr-2">
                                    <Link
                                        v-if="can('patient.view')"
                                        :href="PatientController.show.url(patient.id)"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-[#E5E7EB] bg-white text-[#9CA3AF] transition-all duration-150 hover:text-[#0EA5E9] hover:border-[#0EA5E9] hover:bg-[#EAF7FE] active:scale-[0.95]"
                                        :aria-label="`عرض ${patient.full_name}`"
                                        :title="'عرض'"
                                    >
                                        <Eye class="size-4" />
                                    </Link>
                                    <button
                                        v-if="can('patient.update')"
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-[#E5E7EB] bg-white text-[#9CA3AF] transition-all duration-150 hover:text-[#0EA5E9] hover:border-[#0EA5E9] hover:bg-[#EAF7FE] active:scale-[0.95]"
                                        :aria-label="`تعديل ${patient.full_name}`"
                                        :title="'تعديل'"
                                        @click="emit('edit', patient)"
                                    >
                                        <Pencil class="size-4" />
                                    </button>
                                    <button
                                        v-if="can('patient.delete')"
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-lg border border-[#E5E7EB] bg-white text-[#9CA3AF] transition-all duration-150 hover:text-[#DC2626] hover:border-[#DC2626] hover:bg-[#FEF2F2] active:scale-[0.95]"
                                        :aria-label="`حذف ${patient.full_name}`"
                                        :title="'حذف'"
                                        @click="emit('delete', patient)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </div>
                            <div class="flex md:hidden items-center justify-end">
                                <button
                                    type="button"
                                    class="inline-flex size-9 items-center justify-center rounded-lg border border-[#E5E7EB] bg-white text-[#9CA3AF] transition-all duration-150 hover:bg-[#F9FAFB] active:scale-[0.95]"
                                    :aria-label="`إجراءات ${patient.full_name}`"
                                >
                                    <MoreVertical class="size-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty State -->
                    <tr v-if="patients.length === 0">
                        <td :colspan="can('patient.delete') ? 8 : 7" class="px-5">
                            <div class="py-20 text-center">
                                <div class="mb-4 flex justify-center">
                                    <Users class="size-16 text-[#D1D5DB]" />
                                </div>
                                <h3 class="text-base font-semibold text-[#374151] mb-2">لا يوجد مرضى</h3>
                                <p class="text-sm text-[#6B7280] mb-6">جرب تغيير كلمة البحث أو أضف مريضاً جديداً للبدء</p>
                                <Button
                                    v-if="can('patient.create')"
                                    variant="default"
                                    size="sm"
                                    class="h-10 px-4 rounded-lg bg-[#0EA5E9] text-white text-sm font-medium hover:bg-[#0284C7] active:scale-[0.98] transition-all duration-150"
                                    @click="emit('toggle-quick-add')"
                                >
                                    إضافة أول مريض
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 px-5 py-4 border-t border-[#E5E7EB] bg-[#FAFBFC]">
            <p class="text-sm text-[#6B7280]">
                <span class="font-medium text-[#374151]">{{ visibleFrom }}</span>-<span class="font-medium text-[#374151]">{{ visibleTo }}</span>
                من 
                <span class="font-medium text-[#0EA5E9]">{{ totalRecords }}</span> 
                سجل
            </p>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center justify-center size-9 rounded-lg text-sm text-[#6B7280] border border-[#E5E7EB] bg-white hover:bg-[#F9FAFB] hover:border-[#0EA5E9]/30 transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="currentPage === 1"
                    @click="handlePreviousPage"
                >
                    السابق
                </button>
                <span class="text-xs font-medium text-[#6B7280] px-3 py-2 rounded-lg bg-[#F3F4F6]">
                    <span class="text-[#0EA5E9]">{{ currentPage }}</span> / {{ totalPages }}
                </span>
                <button
                    type="button"
                    class="inline-flex items-center justify-center size-9 rounded-lg text-sm text-[#6B7280] border border-[#E5E7EB] bg-white hover:bg-[#F9FAFB] hover:border-[#0EA5E9]/30 transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="currentPage >= totalPages"
                    @click="handleNextPage"
                >
                    التالي
                </button>
            </div>
        </div>
    </div>
</template>
