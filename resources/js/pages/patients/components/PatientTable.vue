<script setup lang="ts">
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Eye, Filter, MoreVertical, Pencil, Trash2, Users } from 'lucide-vue-next';
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
        return 'bg-[#F4F7FA] text-[#111827]';
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
    <div class="space-y-8">
        <section class="rounded-[1.45rem] border border-[#E2ECF6] bg-white/95 p-6 shadow-card-float">
            <div class="grid gap-4 lg:grid-cols-[1fr_18rem_9rem]">
                <FilterSearch
                    :model-value="search"
                    placeholder="البحث في جميع بيانات المريض..."
                    @update:model-value="handleSearch"
                />

                <FilterSearch
                    :model-value="search"
                    placeholder="البحث برقم المريض..."
                    @update:model-value="handleSearch"
                />

                <button
                    type="button"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl border border-[#E8EEF6] bg-white px-5 text-sm font-semibold text-[#1A2B3F] shadow-[0_10px_24px_-24px_rgb(15_42_71_/_0.35)] transition-all duration-200 hover:bg-[#F7FBFE] hover:text-[#075985]"
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
                @clear-all="handleClearFilters"
            />
        </section>

        <div
            v-if="can('patient.delete') && selectedIds.length > 0"
            class="flex items-center gap-3 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 shadow-card"
        >
            <div class="flex-1">
                <p class="text-sm font-semibold text-[#DC2626]">{{ selectedIds.length }} مريض محدد</p>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    class="h-9 rounded-xl px-4 text-xs"
                    @click="emit('bulk-delete')"
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
            </div>
        </div>

        <section class="overflow-hidden rounded-[1.35rem] border border-[#DDE8F3] bg-white/95 shadow-card-float">
            <div class="w-full overflow-x-auto">
                <table class="min-w-full border-separate border-spacing-0">
                    <thead>
                        <tr class="h-16 bg-[#F3F8FC]">
                            <th v-if="can('patient.delete')" class="w-12 px-5 text-right">
                                <input
                                    type="checkbox"
                                    class="size-4 cursor-pointer rounded border-[#CAD8E7] text-[#0EA5E9]"
                                    :checked="areAllSelected"
                                    @change="handleToggleAll"
                                />
                            </th>
                            <th class="w-12 px-4 py-3 text-right text-sm font-bold text-[#111827]">#</th>
                            <th
                                class="min-w-64 cursor-pointer select-none px-5 py-3 text-right text-sm font-bold text-[#111827] transition-colors hover:text-[#0284C7]"
                                @click="handleSort('full_name')"
                            >
                                الاسم الكامل للمريض
                                <span class="ms-1 text-[#A8B8C8]">↕</span>
                            </th>
                            <th
                                class="min-w-32 cursor-pointer select-none px-5 py-3 text-right text-sm font-bold text-[#111827] transition-colors hover:text-[#0284C7]"
                                @click="handleSort('file_number')"
                            >
                                رقم المريض
                                <span class="ms-1 text-[#A8B8C8]">↕</span>
                            </th>
                            <th
                                class="min-w-28 cursor-pointer select-none px-5 py-3 text-right text-sm font-bold text-[#111827] transition-colors hover:text-[#0284C7]"
                                @click="handleSort('gender')"
                            >
                                الجنس
                                <span class="ms-1 text-[#A8B8C8]">↕</span>
                            </th>
                            <th class="min-w-24 px-5 py-3 text-right text-sm font-bold text-[#111827]">
                                العمر
                                <span class="ms-1 text-[#A8B8C8]">↕</span>
                            </th>
                            <th
                                class="min-w-40 cursor-pointer select-none px-5 py-3 text-right text-sm font-bold text-[#111827] transition-colors hover:text-[#0284C7]"
                                @click="handleSort('phone')"
                            >
                                رقم الهاتف
                                <span class="ms-1 text-[#A8B8C8]">↕</span>
                            </th>
                            <th
                                class="min-w-36 cursor-pointer select-none px-5 py-3 text-right text-sm font-bold text-[#111827] transition-colors hover:text-[#0284C7]"
                                @click="handleSort('created_at')"
                            >
                                تاريخ الإضافة
                                <span class="ms-1 text-[#A8B8C8]">↕</span>
                            </th>
                            <th class="min-w-32 px-5 py-3 text-right text-sm font-bold text-[#111827]">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(patient, index) in patients"
                            :key="patient.id"
                            class="group h-20 border-b border-[#E8EEF6] transition-all duration-150 last:border-b-0 hover:bg-[#F8FCFF]"
                        >
                            <td v-if="can('patient.delete')" class="px-5 py-4" data-label="تحديد">
                                <input
                                    type="checkbox"
                                    class="size-4 cursor-pointer rounded border-[#CAD8E7] text-[#0EA5E9]"
                                    :checked="selectedIds.includes(patient.id)"
                                    @change="handleRowCheckbox(patient.id)"
                                />
                            </td>
                            <td class="px-4 py-4 text-sm font-bold text-[#111827]" data-label="#">
                                {{ visibleFrom + index }}
                            </td>
                            <td class="px-5 py-4" data-label="الاسم الكامل للمريض">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#0EA5E9] text-sm font-bold text-white shadow-[0_12px_24px_-18px_rgb(14_165_233_/_0.95)]">
                                        {{ patient.full_name?.charAt(0) ?? '?' }}
                                    </span>
                                    <span class="text-sm font-semibold text-[#111827]">{{ patient.full_name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-sm font-semibold text-[#111827]" data-label="رقم المريض">
                                {{ patient.file_number || '-' }}
                            </td>
                            <td class="px-5 py-4" data-label="الجنس">
                                <span
                                    class="inline-flex min-w-14 items-center justify-center rounded-full px-3 py-1.5 text-xs font-bold"
                                    :class="patientGenderClass(patient.gender)"
                                >
                                    {{ patientGenderLabel(patient.gender) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm text-[#111827]" data-label="العمر">
                                {{ patient.age !== null ? `${patient.age} سنة` : '-' }}
                            </td>
                            <td class="px-5 py-4 text-sm text-[#6C7F95]" data-label="رقم الهاتف">
                                {{ patient.phone ?? 'غير محدد' }}
                            </td>
                            <td class="px-5 py-4 text-sm text-[#111827]" data-label="تاريخ الإضافة">
                                {{ patient.created_at ?? '-' }}
                            </td>
                            <td class="px-5 py-4 md:text-right" data-label="الإجراءات">
                                <div class="hidden items-center justify-end gap-3 md:flex">
                                    <button
                                        v-if="can('patient.delete')"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-[#FF3B30] transition-all duration-150 hover:bg-[#FEF2F2] active:scale-[0.95]"
                                        :aria-label="`حذف ${patient.full_name}`"
                                        :title="'حذف'"
                                        @click="emit('delete', patient)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                    <button
                                        v-if="can('patient.update')"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-[#2563EB] transition-all duration-150 hover:bg-[#EFF6FF] active:scale-[0.95]"
                                        :aria-label="`تعديل ${patient.full_name}`"
                                        :title="'تعديل'"
                                        @click="emit('edit', patient)"
                                    >
                                        <Pencil class="size-4" />
                                    </button>
                                    <Link
                                        v-if="can('patient.view')"
                                        :href="PatientController.show.url(patient.id)"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-[#0EA5E9] transition-all duration-150 hover:bg-[#EAF7FE] active:scale-[0.95]"
                                        :aria-label="`عرض ${patient.full_name}`"
                                        :title="'عرض'"
                                    >
                                        <Eye class="size-4" />
                                    </Link>
                                </div>
                                <div class="flex items-center justify-end md:hidden">
                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-xl border border-[#DDE9F3] bg-white text-[#6C7F95] transition-all duration-150 hover:bg-[#F7FBFE] active:scale-[0.95]"
                                        :aria-label="`إجراءات ${patient.full_name}`"
                                    >
                                        <MoreVertical class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="patients.length === 0">
                            <td :colspan="can('patient.delete') ? 9 : 8" class="px-5">
                                <div class="py-20 text-center">
                                    <div class="mb-4 flex justify-center">
                                        <Users class="size-16 text-[#C6D5E4]" />
                                    </div>
                                    <h3 class="mb-2 text-base font-bold text-[#111827]">لا يوجد مرضى</h3>
                                    <p class="mb-6 text-sm text-[#6C7F95]">جرب تغيير كلمة البحث أو أضف مريضاً جديداً للبدء</p>
                                    <Button
                                        v-if="can('patient.create')"
                                        variant="default"
                                        size="sm"
                                        class="h-10 rounded-xl bg-[#0EA5E9] px-4 text-sm font-bold text-white hover:bg-[#0284C7]"
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
        </section>

        <div class="flex flex-col gap-4 px-2 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-[#E8EEF6] bg-white text-[#93A4B7] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition hover:text-[#075985] disabled:opacity-40"
                        :disabled="currentPage === 1"
                        @click="handlePreviousPage"
                    >
                        <ChevronsRight class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-[#E8EEF6] bg-white text-[#93A4B7] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition hover:text-[#075985] disabled:opacity-40"
                        :disabled="currentPage === 1"
                        @click="handlePreviousPage"
                    >
                        <ChevronRight class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-[#E8EEF6] bg-white text-[#93A4B7] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition hover:text-[#075985] disabled:opacity-40"
                        :disabled="currentPage >= totalPages"
                        @click="handleNextPage"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-[#E8EEF6] bg-white text-[#93A4B7] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition hover:text-[#075985] disabled:opacity-40"
                        :disabled="currentPage >= totalPages"
                        @click="handleNextPage"
                    >
                        <ChevronsLeft class="size-4" />
                    </button>
                </div>

                <span class="text-sm font-semibold text-[#111827]">صفحة {{ currentPage }} من {{ totalPages }}</span>

                <div class="flex items-center gap-3">
                    <select
                        :value="rowsPerPage"
                        class="h-11 rounded-2xl border border-[#DDE9F3] bg-white px-4 text-sm font-semibold text-[#1A2B3F] shadow-[0_10px_22px_-24px_rgb(15_42_71_/_0.4)] transition-colors focus:border-[#0EA5E9] focus:outline-none focus:ring-2 focus:ring-[#0EA5E9]/10"
                        @change="(e) => handleRowsPerPage(Number((e.target as HTMLSelectElement).value))"
                    >
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <span class="text-sm font-semibold text-[#111827]">عدد الصفوف لكل صفحة</span>
                </div>
            </div>

            <p class="text-sm font-medium text-[#6C7F95]">
                عرض {{ visibleFrom }} إلى {{ visibleTo }} من {{ totalRecords }} مريض
            </p>
        </div>
    </div>
</template>
