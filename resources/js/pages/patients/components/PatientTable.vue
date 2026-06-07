<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Eye,
    Filter,
    Pencil,
    Trash2,
    Users,
} from 'lucide-vue-next';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch } from '@/components/ui/filter';
import { usePermissions } from '@/composables/usePermissions';
import type {
    ActiveFilter,
    Patient,
    PatientSortField,
    SortDirection,
} from './types';

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
    activeFilters: ActiveFilter[];
}>();

const emit = defineEmits<{
    'update:search': [value: string];
    'update:rowsPerPage': [value: number];
    'previous-page': [];
    'next-page': [];
    sort: [field: PatientSortField];
    'remove-filter': [key: string];
    'clear-filters': [];
    delete: [patient: Patient];
    edit: [patient: Patient];
    'toggle-quick-add': [];
}>();

const { can } = usePermissions();

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

const formatDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};
</script>

<template>
    <div class="space-y-4">
        <section
            class="rounded-2xl border border-border/60 bg-card p-4 shadow-sm"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <FilterSearch
                    :model-value="search"
                    placeholder="البحث في جميع بيانات المريض..."
                    class="flex-1"
                    @update:model-value="handleSearch"
                />
                <FilterSearch
                    :model-value="search"
                    placeholder="البحث برقم المريض..."
                    class="sm:w-64"
                    @update:model-value="handleSearch"
                />
                <button
                    type="button"
                    class="inline-flex h-9 shrink-0 items-center justify-center gap-2 rounded-lg border border-border/60 bg-background px-4 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                >
                    <Filter class="size-3.5" />
                    تصفية
                </button>
            </div>

            <FilterBar
                v-if="activeFilters.length > 0"
                class="mt-3"
                :active-filters="activeFilters"
                @remove="handleRemoveFilter"
                @clear-all="handleClearFilters"
            />
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm"
        >
            <div class="w-full overflow-x-auto">
                <table class="w-full border-separate border-spacing-0">
                    <thead>
                        <tr class="border-b border-border/60 bg-muted/40">
                            <th
                                class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground"
                            >
                                #
                            </th>
                            <th
                                class="cursor-pointer px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground transition-colors select-none hover:text-foreground"
                                @click="handleSort('full_name')"
                            >
                                <div class="flex items-center gap-1">
                                    الاسم الكامل للمريض
                                    <span class="text-[10px] opacity-50"
                                        >↕</span
                                    >
                                </div>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground transition-colors select-none hover:text-foreground"
                                @click="handleSort('file_number')"
                            >
                                <div class="flex items-center gap-1">
                                    رقم المريض
                                    <span class="text-[10px] opacity-50"
                                        >↕</span
                                    >
                                </div>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground transition-colors select-none hover:text-foreground"
                                @click="handleSort('gender')"
                            >
                                <div class="flex items-center gap-1">
                                    الجنس
                                    <span class="text-[10px] opacity-50"
                                        >↕</span
                                    >
                                </div>
                            </th>
                            <th
                                class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground"
                            >
                                العمر
                            </th>
                            <th
                                class="cursor-pointer px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground transition-colors select-none hover:text-foreground"
                                @click="handleSort('phone')"
                            >
                                <div class="flex items-center gap-1">
                                    رقم الهاتف
                                    <span class="text-[10px] opacity-50"
                                        >↕</span
                                    >
                                </div>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground transition-colors select-none hover:text-foreground"
                                @click="handleSort('created_at')"
                            >
                                <div class="flex items-center gap-1">
                                    تاريخ الإضافة
                                    <span class="text-[10px] opacity-50"
                                        >↕</span
                                    >
                                </div>
                            </th>
                            <th
                                class="px-3 py-2.5 text-right text-xs font-semibold text-muted-foreground"
                            >
                                الإجراءات
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(patient, index) in patients"
                            :key="patient.id"
                            class="group border-b border-border/40 transition-colors last:border-b-0 hover:bg-muted/30"
                        >
                            <td
                                class="px-3 py-2.5 text-sm font-medium text-muted-foreground"
                                data-label="#"
                            >
                                {{ visibleFrom + index }}
                            </td>
                            <td
                                class="px-3 py-2.5"
                                data-label="الاسم الكامل للمريض"
                            >
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                                    >
                                        {{
                                            patient.full_name?.charAt(0) ?? '?'
                                        }}
                                    </span>
                                    <span
                                        class="truncate text-sm font-medium text-foreground"
                                        >{{ patient.full_name }}</span
                                    >
                                </div>
                            </td>
                            <td
                                class="px-3 py-2.5 text-sm whitespace-nowrap text-foreground"
                                data-label="رقم المريض"
                            >
                                {{ patient.file_number || '-' }}
                            </td>
                            <td class="px-3 py-2.5" data-label="الجنس">
                                <span
                                    class="inline-flex min-w-12 items-center justify-center rounded-full px-2 py-0.5 text-[0.7rem] font-medium"
                                    :class="patientGenderClass(patient.gender)"
                                >
                                    {{ patientGenderLabel(patient.gender) }}
                                </span>
                            </td>
                            <td
                                class="px-3 py-2.5 text-sm whitespace-nowrap text-muted-foreground"
                                data-label="العمر"
                            >
                                {{
                                    patient.age !== null
                                        ? `${patient.age} سنة`
                                        : '-'
                                }}
                            </td>
                            <td
                                class="px-3 py-2.5 text-sm whitespace-nowrap text-muted-foreground"
                                data-label="رقم الهاتف"
                            >
                                {{ patient.phone ?? 'غير محدد' }}
                            </td>
                            <td
                                class="px-3 py-2.5 text-sm whitespace-nowrap text-muted-foreground"
                                data-label="تاريخ الإضافة"
                            >
                                {{ formatDate(patient.created_at) }}
                            </td>
                            <td class="px-3 py-2.5" data-label="الإجراءات">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <button
                                        v-if="can('patient.delete')"
                                        type="button"
                                        class="inline-flex size-7 items-center justify-center rounded-md text-destructive transition-colors hover:bg-destructive/10"
                                        :aria-label="`حذف ${patient.full_name}`"
                                        :title="'حذف'"
                                        @click="emit('delete', patient)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                    <button
                                        v-if="can('patient.update')"
                                        type="button"
                                        class="inline-flex size-7 items-center justify-center rounded-md text-primary transition-colors hover:bg-primary/10"
                                        :aria-label="`تعديل ${patient.full_name}`"
                                        :title="'تعديل'"
                                        @click="emit('edit', patient)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </button>
                                    <Link
                                        v-if="can('patient.view')"
                                        :href="
                                            PatientController.show.url(
                                                patient.id,
                                            )
                                        "
                                        class="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted"
                                        :aria-label="`عرض ${patient.full_name}`"
                                        :title="'عرض'"
                                    >
                                        <Eye class="size-3.5" />
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="patients.length === 0">
                            <td colspan="8" class="px-5">
                                <div class="py-16 text-center">
                                    <Users
                                        class="mx-auto mb-3 size-12 text-muted-foreground/40"
                                    />
                                    <h3
                                        class="mb-1 text-sm font-semibold text-foreground"
                                    >
                                        لا يوجد مرضى
                                    </h3>
                                    <p
                                        class="mb-4 text-xs text-muted-foreground"
                                    >
                                        جرب تغيير كلمة البحث أو أضف مريضاً
                                        جديداً
                                    </p>
                                    <Button
                                        v-if="can('patient.create')"
                                        variant="default"
                                        size="sm"
                                        class="h-9 rounded-lg px-4 text-xs"
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

        <div
            class="flex flex-col items-center justify-between gap-3 px-1 sm:flex-row"
        >
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-border/60 bg-background text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40"
                        :disabled="currentPage === 1"
                        @click="handlePreviousPage"
                    >
                        <ChevronsRight class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-border/60 bg-background text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40"
                        :disabled="currentPage === 1"
                        @click="handlePreviousPage"
                    >
                        <ChevronRight class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-border/60 bg-background text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40"
                        :disabled="currentPage >= totalPages"
                        @click="handleNextPage"
                    >
                        <ChevronLeft class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-border/60 bg-background text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40"
                        :disabled="currentPage >= totalPages"
                        @click="handleNextPage"
                    >
                        <ChevronsLeft class="size-3.5" />
                    </button>
                </div>

                <span class="text-xs text-muted-foreground"
                    >صفحة {{ currentPage }} من {{ totalPages }}</span
                >

                <div class="flex items-center gap-2">
                    <select
                        :value="rowsPerPage"
                        class="h-8 rounded-lg border border-border/60 bg-background px-2 text-xs text-foreground transition-colors focus:border-ring focus:ring-1 focus:ring-ring/20 focus:outline-none"
                        @change="
                            (e) =>
                                handleRowsPerPage(
                                    Number(
                                        (e.target as HTMLSelectElement).value,
                                    ),
                                )
                        "
                    >
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <span class="text-xs text-muted-foreground">صف</span>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                عرض {{ visibleFrom }} إلى {{ visibleTo }} من
                {{ totalRecords }} مريض
            </p>
        </div>
    </div>
</template>
