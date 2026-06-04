<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import DoctorProfileController from '@/actions/App/Http/Controllers/Doctors/DoctorProfileController';
import { Button } from '@/components/ui/button';
import {
    FilterBar,
    FilterSearch,
    FilterSelect,
} from '@/components/ui/filter';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';

type DoctorProfileStatus = 'active' | 'on_leave' | 'inactive';

type DoctorOption = {
    id: number;
    name: string;
    email: string | null;
};

type DepartmentOption = {
    id: number;
    name: string;
    code: string | null;
    is_active: boolean;
};

type DoctorProfile = {
    id: number;
    clinic_id: number;
    user_id: number;
    department_id: number | null;
    license_number: string | null;
    specialty: string;
    consultation_duration_minutes: number;
    status: DoctorProfileStatus;
    work_schedule: Record<string, unknown> | null;
    bio: string | null;
    user?: DoctorOption | null;
    department?: DepartmentOption | null;
    created_at: string | null;
    updated_at: string | null;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type PaginatedResponse<T> = {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: PaginationMeta;
};

type DoctorProfileSortField = 'specialty' | 'license_number' | 'consultation_duration_minutes' | 'status' | 'created_at';
type SortDirection = 'asc' | 'desc';
type StatusFilter = 'all' | DoctorProfileStatus;

const props = defineProps<{
    doctorProfiles: PaginatedResponse<DoctorProfile>;
    visibleProfiles: DoctorProfile[];
    localSearch: string;
    localStatus: StatusFilter;
    localDepartmentId: number | null;
    localRowsPerPage: number;
    localPage: number;
    localSortBy: DoctorProfileSortField;
    localSortDirection: SortDirection;
    totalLocalPages: number;
    localVisibleFrom: number;
    localVisibleTo: number;
    selectedProfileIds: number[];
    areAllProfilesSelected: boolean;
    canDeleteProfile: boolean;
    activeFilters: { key: string; label: string; value: string | null }[];
    statusOptions: { label: string; value: string }[];
    departmentOptions: { label: string; value: number }[];
}>();

const emit = defineEmits<{
    'update:localSearch': [value: string];
    'update:localStatus': [value: StatusFilter];
    'update:localDepartmentId': [value: number | null];
    'update:localRowsPerPage': [value: number];
    'update:selectedProfileIds': [value: number[]];
    'toggle-sort': [field: DoctorProfileSortField];
    'previous-page': [];
    'next-page': [];
    'reset-filters': [];
    'remove-filter': [key: string];
    'toggle-all-selection': [event: Event];
    'view-profile': [profile: DoctorProfile];
    'edit-profile': [profile: DoctorProfile];
    'delete-profile': [profile: DoctorProfile];
}>();

const { can } = usePermissions();

const statusLabels: Record<DoctorProfileStatus, string> = {
    active: 'نشط',
    on_leave: 'في إجازة',
    inactive: 'غير نشط',
};

const formatStatus = (status: DoctorProfileStatus): string => {
    return statusLabels[status] ?? status.replace('_', ' ');
};

const statusClass = (status: DoctorProfileStatus): string => {
    if (status === 'active') {
        return 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/35 dark:bg-success-500/15 dark:text-success-100';
    }

    if (status === 'on_leave') {
        return 'border-warning-300/70 bg-warning-50 text-warning-800 dark:border-warning-500/35 dark:bg-warning-500/15 dark:text-warning-100';
    }

    return 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/35 dark:bg-destructive/15 dark:text-destructive-foreground';
};

const statusDotClass = (status: DoctorProfileStatus): string => {
    if (status === 'active') {
        return 'bg-success-500';
    }

    if (status === 'on_leave') {
        return 'bg-warning-500';
    }

    return 'bg-destructive';
};

const doctorLabel = (profile: DoctorProfile): string => {
    return profile.user?.name ?? `Doctor #${profile.user_id}`;
};

const departmentLabel = (profile: DoctorProfile): string => {
    if (profile.department === null || profile.department === undefined) {
        return 'غير معين';
    }

    return profile.department.code !== null
        ? `${profile.department.name} (${profile.department.code})`
        : profile.department.name;
};

const sortIconFor = (field: DoctorProfileSortField) => {
    if (props.localSortBy !== field) {
        return ArrowUpDown;
    }

    return props.localSortDirection === 'asc' ? ArrowUp : ArrowDown;
};
</script>

<template>
    <section
        :class="[
            'glass-panel-soft p-5',
            canDeleteProfile ? 'xl:col-span-3' : 'xl:col-span-3',
        ]"
    >
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
            <h3 class="pattern-typographic-title text-[0.76rem]">
                ملفات الأطباء
            </h3>
            <span class="text-xs text-muted-foreground">
                الإجمالي: {{ doctorProfiles.meta.total }}
            </span>
        </div>

        <div class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4">
            <div class="grid gap-3 md:grid-cols-[repeat(4,minmax(0,1fr))] md:items-end">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="doctor_profiles_search">بحث</Label>
                    <FilterSearch
                        id="doctor_profiles_search"
                        :model-value="localSearch"
                        @update:model-value="emit('update:localSearch', $event)"
                        placeholder="طبيب، تخصص، ترخيص، أو قسم"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="doctor_profiles_status">الحالة</Label>
                    <FilterSelect
                        id="doctor_profiles_status"
                        :model-value="localStatus"
                        @update:model-value="emit('update:localStatus', $event)"
                        :options="statusOptions"
                        placeholder="الكل"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="doctor_profiles_department">القسم</Label>
                    <FilterSelect
                        id="doctor_profiles_department"
                        :model-value="localDepartmentId"
                        @update:model-value="emit('update:localDepartmentId', $event)"
                        :options="departmentOptions"
                        placeholder="الكل"
                    />
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-[repeat(2,minmax(0,1fr))] md:items-end">
                <div class="grid gap-2">
                    <Label for="doctor_profiles_per_page">صفوف</Label>
                    <select
                        id="doctor_profiles_per_page"
                        :value="localRowsPerPage"
                        @change="emit('update:localRowsPerPage', Number(($event.target as HTMLSelectElement).value))"
                        class="pattern-field-clay h-9 px-3 py-1.5"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>
            </div>

            <FilterBar
                v-if="activeFilters.length > 0"
                :active-filters="activeFilters"
                @remove="emit('remove-filter', $event)"
                @clear-all="emit('reset-filters')"
            />
        </div>

        <Form
            v-if="canDeleteProfile && selectedProfileIds.length > 0"
            v-bind="DoctorProfileController.bulkDestroy.form()"
            class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
            v-slot="{ processing }"
            @success="emit('update:selectedProfileIds', [])"
        >
            <input
                v-for="profileId in selectedProfileIds"
                :key="`selected-doctor-profile-${profileId}`"
                type="hidden"
                name="ids[]"
                :value="profileId"
            />

            <Button
                type="submit"
                variant="destructive"
                size="sm"
                class="h-8 px-3 text-xs"
                :disabled="processing"
            >
                حذف المحدد ({{ selectedProfileIds.length }})
            </Button>

            <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-8 px-3 text-xs"
                :disabled="processing"
                @click="emit('update:selectedProfileIds', [])"
            >
                إلغاء التحديد
            </Button>
        </Form>

        <div class="overflow-x-auto rounded-2xl border border-border/70">
            <table class="ui-table min-w-full text-sm">
                <thead class="ui-table-head">
                    <tr>
                        <th
                            v-if="canDeleteProfile"
                            class="w-10 px-3 py-2"
                        >
                            <input
                                type="checkbox"
                                class="size-4 rounded border-border"
                                :checked="areAllProfilesSelected"
                                @change="emit('toggle-all-selection', $event)"
                            />
                        </th>
                        <th class="px-3 py-2">الطبيب</th>
                        <th class="px-3 py-2">القسم</th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'specialty')"
                            >
                                التخصص
                                <component
                                    :is="sortIconFor('specialty')"
                                    class="size-3.5"
                                />
                            </button>
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'license_number')"
                            >
                                الترخيص
                                <component
                                    :is="sortIconFor('license_number')"
                                    class="size-3.5"
                                />
                            </button>
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'consultation_duration_minutes')"
                            >
                                المدة
                                <component
                                    :is="sortIconFor('consultation_duration_minutes')"
                                    class="size-3.5"
                                />
                            </button>
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('toggle-sort', 'status')"
                            >
                                الحالة
                                <component
                                    :is="sortIconFor('status')"
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
                        v-for="profile in visibleProfiles"
                        :key="profile.id"
                        class="ui-table-row align-top"
                    >
                        <td
                            v-if="canDeleteProfile"
                            class="px-3 py-2"
                        >
                            <input
                                type="checkbox"
                                class="size-4 rounded border-border"
                                :value="profile.id"
                                :checked="selectedProfileIds.includes(profile.id)"
                                @change="
                                    ($event) => {
                                        const target = $event.target as HTMLInputElement;
                                        if (target.checked) {
                                            emit('update:selectedProfileIds', [...selectedProfileIds, profile.id]);
                                        } else {
                                            emit('update:selectedProfileIds', selectedProfileIds.filter(id => id !== profile.id));
                                    }
                                }"
                            />
                        </td>

                        <td class="px-3 py-2 font-medium">
                            <div class="leading-5">
                                <p class="text-sm font-semibold">
                                    {{ doctorLabel(profile) }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ profile.user?.email ?? '-' }}
                                </p>
                            </div>
                        </td>

                        <td class="px-3 py-2">
                            <span class="text-sm">
                                {{ departmentLabel(profile) }}
                            </span>
                        </td>

                        <td class="px-3 py-2">
                            {{ profile.specialty }}
                        </td>

                        <td class="px-3 py-2">
                            {{ profile.license_number ?? '-' }}
                        </td>

                        <td class="px-3 py-2">
                            {{ profile.consultation_duration_minutes }} دقيقة
                        </td>

                        <td class="px-3 py-2">
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium capitalize"
                                :class="statusClass(profile.status)"
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full"
                                    :class="statusDotClass(profile.status)"
                                ></span>
                                {{ formatStatus(profile.status) }}
                            </span>
                        </td>

                        <td class="px-3 py-2">
                            {{
                                profile.created_at !== null
                                    ? new Date(profile.created_at).toLocaleDateString('ar-SA')
                                    : '-'
                            }}
                        </td>

                        <td class="table-cell-actions px-3 py-2 md:text-start">
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-8 px-3 text-xs"
                                    @click="emit('view-profile', profile)"
                                >
                                    عرض
                                </Button>

                                <Button
                                    v-if="can('doctor_profile.update')"
                                    type="button"
                                    variant="default"
                                    size="sm"
                                    class="h-8 px-3 text-xs"
                                    @click="emit('edit-profile', profile)"
                                >
                                    تعديل
                                </Button>

                                <Button
                                    v-if="can('doctor_profile.delete')"
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    class="h-8 px-3 text-xs"
                                    @click="emit('delete-profile', profile)"
                                >
                                    حذف
                                </Button>
                            </div>
                        </td>
                    </tr>

                    <tr
                        v-if="visibleProfiles.length === 0"
                        class="table-empty-state"
                    >
                        <td
                            :colspan="canDeleteProfile ? 9 : 8"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            لا توجد ملفات أطباء مطابقة.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2"
        >
            <p class="text-xs text-muted-foreground">
                عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من {{ doctorProfiles.meta.total }} سجل
            </p>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 px-3 text-xs"
                    :disabled="localPage === 1"
                    @click="emit('previous-page')"
                >
                    السابق
                </Button>
                <span class="text-xs font-semibold text-foreground/85">
                    صفحة {{ localPage }} / {{ totalLocalPages }}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 px-3 text-xs"
                    :disabled="localPage >= totalLocalPages"
                    @click="emit('next-page')"
                >
                    التالي
                </Button>
            </div>
        </div>
    </section>
</template>