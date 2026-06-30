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
import ClinicController from '@/actions/App/Http/Controllers/Clinics/ClinicController';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch, FilterSelect } from '@/components/ui/filter';
import type {
    ActiveFilter,
    Clinic,
    ClinicSortField,
    SortDirection,
} from './types';

const search = defineModel<string>('search', { default: '' });
const activeFilter = defineModel<ActiveFilter>('activeFilter', {
    default: 'all',
});
const rowsPerPage = defineModel<number>('rowsPerPage', { default: 15 });
const selectedIds = defineModel<number[]>('selectedIds', { default: () => [] });

const props = defineProps<{
    clinics: Clinic[];
    page: number;
    totalPages: number;
    visibleFrom: number;
    visibleTo: number;
    totalClinics: number;
    sortBy: ClinicSortField;
    sortDirection: SortDirection;
    areAllSelected: boolean;
    canDelete: boolean;
    canUpdate: boolean;
}>();

const emit = defineEmits<{
    'toggle-sort': [field: ClinicSortField];
    'previous-page': [];
    'next-page': [];
    'reset-filters': [];
    'toggle-all-selection': [event: Event];
    'clear-selection': [];
    view: [clinic: Clinic];
    edit: [clinic: Clinic];
    delete: [clinic: Clinic];
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

const activeHoursCount = (clinic: Clinic): number => {
    return clinic.working_hours?.filter((row) => row.is_active).length ?? 0;
};

const statusClass = (isActive: boolean): string => {
    return isActive
        ? 'bg-accent text-primary'
        : 'bg-muted text-muted-foreground';
};

const sortMark = (field: ClinicSortField): string => {
    if (props.sortBy !== field) {
        return '↕';
    }

    return props.sortDirection === 'asc' ? '↑' : '↓';
};
</script>

<template>
    <div class="space-y-8">
        <section
            class="rounded-[1.45rem] border border-border bg-card/95 p-6 shadow-card-float"
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
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl border border-border bg-card px-5 text-sm font-semibold text-foreground shadow-sm transition-all duration-200 hover:bg-muted hover:text-accent-foreground"
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
            v-bind="ClinicController.bulkDestroy.form()"
            class="flex items-center gap-3 rounded-2xl border border-destructive/30 bg-destructive/10 p-4 shadow-card"
            v-slot="{ processing }"
        >
            <input
                v-for="departmentId in selectedIds"
                :key="`selected-clinic-${departmentId}`"
                type="hidden"
                name="ids[]"
                :value="departmentId"
            />

            <p class="flex-1 text-sm font-semibold text-destructive">
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
                class="h-9 rounded-xl px-4 text-xs text-muted-foreground hover:bg-muted"
                @click="emit('clear-selection')"
            >
                إلغاء
            </Button>
        </Form>

        <section
            class="overflow-hidden rounded-[1.35rem] border border-border bg-card/95 shadow-card-float"
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
                        <tr class="h-16 bg-muted">
                            <th
                                class="px-2 py-3 text-center text-sm font-bold text-foreground"
                            >
                                #
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-foreground transition-colors select-none hover:text-primary"
                                @click="emit('toggle-sort', 'name')"
                            >
                                اسم العيادة
                                <span class="ms-1 text-muted-foreground/50">{{
                                    sortMark('name')
                                }}</span>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-foreground transition-colors select-none hover:text-primary"
                                @click="emit('toggle-sort', 'code')"
                            >
                                الرمز
                                <span class="ms-1 text-muted-foreground/50">{{
                                    sortMark('code')
                                }}</span>
                            </th>
                            <!-- <th class="min-w-64 px-5 py-3 text-right text-sm font-bold text-foreground">الوصف</th> -->
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-foreground transition-colors select-none hover:text-primary"
                                @click="
                                    emit('toggle-sort', 'doctors_count')
                                "
                            >
                                الأطباء
                                <span class="ms-1 text-muted-foreground/50">{{
                                    sortMark('doctors_count')
                                }}</span>
                            </th>
                            <th
                                class="px-3 py-3 text-center text-sm font-bold text-foreground"
                            >
                                أيام الدوام
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-foreground transition-colors select-none hover:text-primary"
                                @click="emit('toggle-sort', 'is_active')"
                            >
                                الحالة
                                <span class="ms-1 text-muted-foreground/50">{{
                                    sortMark('is_active')
                                }}</span>
                            </th>
                            <th
                                class="cursor-pointer px-3 py-3 text-center text-sm font-bold text-foreground transition-colors select-none hover:text-primary"
                                @click="emit('toggle-sort', 'created_at')"
                            >
                                تاريخ الإضافة
                                <span class="ms-1 text-muted-foreground/50">{{
                                    sortMark('created_at')
                                }}</span>
                            </th>
                            <th
                                class="px-3 py-3 text-center text-sm font-bold text-foreground"
                            >
                                الإجراءات
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(clinic, index) in clinics"
                            :key="clinic.id"
                            class="group h-20 border-b border-border transition-all duration-150 last:border-b-0 hover:bg-muted/50"
                        >
                            <td
                                class="px-2 py-4 text-center text-sm font-bold text-foreground"
                            >
                                {{ visibleFrom + index }}
                            </td>
                            <td class="px-3 py-4 text-center">
                                <div
                                    class="flex items-center justify-center gap-3"
                                >
                                    <span
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground shadow-primary/20"
                                    >
                                        {{ clinic.name.charAt(0) }}
                                    </span>
                                    <span
                                        class="min-w-0 truncate text-sm font-semibold text-foreground"
                                        >{{ clinic.name }}</span
                                    >
                                </div>
                            </td>
                            <td
                                class="px-3 py-4 text-center text-sm font-semibold text-foreground"
                            >
                                {{ clinic.code ?? '-' }}
                            </td>
                            <!-- <td class="max-w-xs px-5 py-4 text-sm text-muted-foreground">
                                <span class="line-clamp-2">{{ department.description ?? '-' }}</span>
                            </td> -->
                            <td class="px-3 py-4 text-center">
                                <span
                                    class="inline-flex min-w-10 items-center justify-center rounded-full bg-muted px-3 py-1.5 text-xs font-bold text-foreground"
                                >
                                     {{ clinic.doctors_count }}
                                </span>
                            </td>
                            <td class="px-3 py-4 text-center">
                                <span
                                    class="inline-flex items-center justify-center gap-2 rounded-full bg-accent px-3 py-1.5 text-xs font-bold text-primary"
                                >
                                    <Clock class="size-3.5" />
                                     {{ activeHoursCount(clinic) }} أيام
                                </span>
                            </td>
                            <td class="px-3 py-4 text-center">
                                <span
                                    class="inline-flex min-w-20 items-center justify-center rounded-full px-3 py-1.5 text-xs font-bold"
                                    :class="statusClass(clinic.is_active)"
                                >
                                    {{
                                        clinic.is_active
                                            ? 'نشطة'
                                            : 'غير نشطة'
                                    }}
                                </span>
                            </td>
                            <td
                                class="px-3 py-4 text-center text-sm text-foreground"
                            >
                                {{
                                    clinic.created_at !== null
                                        ? new Date(
                                              clinic.created_at,
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
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-destructive transition-all duration-150 hover:bg-destructive/10 active:scale-[0.95]"
                                        title="حذف"
                                        @click="emit('delete', clinic)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                    <button
                                        v-if="canUpdate"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-primary transition-all duration-150 hover:bg-primary/10 active:scale-[0.95]"
                                        title="تعديل"
                                        @click="emit('edit', clinic)"
                                    >
                                        <Pencil class="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-primary transition-all duration-150 hover:bg-accent active:scale-[0.95]"
                                        title="عرض"
                                        @click="emit('view', clinic)"
                                    >
                                        <Eye class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="clinics.length === 0">
                            <td colspan="8" class="px-5">
                                <div class="py-20 text-center">
                                    <h3
                                        class="mb-2 text-base font-bold text-foreground"
                                    >
                                        لا توجد عيادات
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
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
                        class="inline-flex size-10 items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-sm transition hover:text-accent-foreground disabled:opacity-40"
                        :disabled="page === 1"
                        @click="emit('previous-page')"
                    >
                        <ChevronsRight class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-sm transition hover:text-accent-foreground disabled:opacity-40"
                        :disabled="page === 1"
                        @click="emit('previous-page')"
                    >
                        <ChevronRight class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-sm transition hover:text-accent-foreground disabled:opacity-40"
                        :disabled="page >= totalPages"
                        @click="emit('next-page')"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-sm transition hover:text-accent-foreground disabled:opacity-40"
                        :disabled="page >= totalPages"
                        @click="emit('next-page')"
                    >
                        <ChevronsLeft class="size-4" />
                    </button>
                </div>

                <span class="text-sm font-semibold text-foreground"
                    >صفحة {{ page }} من {{ totalPages }}</span
                >

                <div class="flex items-center gap-3">
                    <select
                        v-model.number="rowsPerPage"
                        class="h-11 rounded-2xl border border-input bg-card px-4 text-sm font-semibold text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/10 focus:outline-none"
                    >
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <span class="text-sm font-semibold text-foreground"
                        >عدد الصفوف لكل صفحة</span
                    >
                </div>
            </div>

            <p class="text-sm font-medium text-muted-foreground">
                عرض {{ visibleFrom }} إلى {{ visibleTo }} من
                {{ totalClinics }} عيادة
            </p>
        </div>
    </div>
</template>
