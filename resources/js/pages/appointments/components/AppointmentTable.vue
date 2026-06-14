<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    CalendarSearch,
} from 'lucide-vue-next';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import { Button } from '@/components/ui/button';
import { FilterBar, FilterSearch, FilterSelect } from '@/components/ui/filter';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    appointmentStatusClass,
    appointmentStatusDotClass,
    appointmentStatusLabel,
} from './appointmentHelpers';
import type {
    Appointment,
    AppointmentSortField,
    Option,
    PaginatedResponse,
    SortDirection,
} from './types';

const props = defineProps<{
    appointments: PaginatedResponse<Appointment>;
    localSearch: string;
    localStatus: string;
    localDoctorId: string;
    localDepartmentId: string;
    localDateFrom: string;
    localDateTo: string;
    localRowsPerPage: number;
    localPage: number;
    sortBy: AppointmentSortField;
    sortDirection: SortDirection;
    localVisibleFrom: number;
    localVisibleTo: number;
    totalLocalPages: number;
    statusOptions: { label: string; value: string }[];
    doctorOptions: { label: string; value: string }[];
    departmentOptions: { label: string; value: string }[];
    activeFilters: { key: string; label: string; value: string | null }[];
    canEditAppointment: boolean;
    canDeleteAppointment: boolean;
    canUpdateStatus: boolean;
    total: number;
    patients: Option[];
    doctors: Option[];
    isDoctor: boolean;
}>();

const emit = defineEmits([
    'search',
    'status',
    'doctor',
    'department',
    'date-from',
    'date-to',
    'rows-per-page',
    'page',
    'remove-filter',
    'clear-filters',
    'sort',
    'view',
    'edit',
    'delete',
    'status-transition-success',
    'status-transition-error',
]);

const transitionStatuses = ['confirmed', 'arrived', 'canceled', 'no_show'];

const formatDate = (iso: string): string => {
    return new Date(iso).toLocaleDateString('ar-SA');
};

const formatTime = (iso: string): string => {
    return new Date(iso).toLocaleTimeString('ar-SA', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });
};

const sortIconFor = (field: AppointmentSortField) => {
    if (props.sortBy !== field) {
        return ArrowUpDown;
    }

    return props.sortDirection === 'asc' ? ArrowUp : ArrowDown;
};
</script>

<template>
    <section class="glass-panel-soft overflow-hidden">
        <div
            class="flex flex-col gap-3 border-b border-border/70 bg-secondary/35 px-5 py-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div>
                <h2 class="text-base font-bold text-foreground">
                    جميع المواعيد
                </h2>
                <p class="text-xs text-muted-foreground">
                    بحث وتصفية ومتابعة حالات الحجوزات.
                </p>
            </div>
            <span
                class="inline-flex w-fit items-center gap-2 rounded-xl border border-border bg-background px-3 py-1.5 text-xs text-muted-foreground"
            >
                <CalendarSearch class="size-3.5 text-primary" />
                الإجمالي: {{ appointments.meta.total }}
            </span>
        </div>

        <div class="space-y-4 p-5">
            <div class="rounded-2xl border border-border bg-secondary/30 p-4">
                <div class="grid gap-3 lg:grid-cols-12 lg:items-end">
                    <div class="grid gap-2 lg:col-span-4">
                        <Label for="appointments_search">بحث</Label>
                        <FilterSearch
                            id="appointments_search"
                            :model-value="localSearch"
                            placeholder="رقم الموعد، المريض، رقم الملف، الطبيب"
                            @update:model-value="emit('search', $event)"
                        />
                    </div>

                    <div class="grid gap-2 lg:col-span-2">
                        <Label for="appointments_status">الحالة</Label>
                        <FilterSelect
                            id="appointments_status"
                            :model-value="localStatus"
                            :options="statusOptions"
                            placeholder="جميع الحالات"
                            @update:model-value="emit('status', $event)"
                        />
                    </div>

                    <div v-if="!isDoctor" class="grid gap-2 lg:col-span-2">
                        <Label for="appointments_doctor">الطبيب</Label>
                        <FilterSelect
                            id="appointments_doctor"
                            :model-value="localDoctorId"
                            :options="doctorOptions"
                            placeholder="كل الأطباء"
                            @update:model-value="emit('doctor', $event)"
                        />
                    </div>

                    <div v-if="!isDoctor" class="grid gap-2 lg:col-span-2">
                        <Label for="appointments_department">العيادة</Label>
                        <FilterSelect
                            id="appointments_department"
                            :model-value="localDepartmentId"
                            :options="departmentOptions"
                            placeholder="كل العيادات"
                            @update:model-value="emit('department', $event)"
                        />
                    </div>

                    <div class="grid gap-2 lg:col-span-1">
                        <Label for="appointments_date_from">من</Label>
                        <Input
                            id="appointments_date_from"
                            type="date"
                            :model-value="localDateFrom"
                            class="pattern-field-clay h-10"
                            @update:model-value="
                                emit('date-from', String($event ?? ''))
                            "
                        />
                    </div>

                    <div class="grid gap-2 lg:col-span-1">
                        <Label for="appointments_date_to">إلى</Label>
                        <Input
                            id="appointments_date_to"
                            type="date"
                            :model-value="localDateTo"
                            class="pattern-field-clay h-10"
                            @update:model-value="
                                emit('date-to', String($event ?? ''))
                            "
                        />
                    </div>
                </div>

                <div
                    class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                >
                    <FilterBar
                        v-if="activeFilters.length > 0"
                        :active-filters="activeFilters"
                        @remove="emit('remove-filter', $event)"
                        @clear-all="emit('clear-filters')"
                    />
                    <div class="grid w-full gap-2 sm:ms-auto sm:w-44">
                        <Label for="appointments_per_page">
                            صفوف لكل صفحة
                        </Label>
                        <select
                            id="appointments_per_page"
                            :value="localRowsPerPage"
                            class="pattern-field-clay h-10 px-3 py-1.5"
                            @change="
                                emit(
                                    'rows-per-page',
                                    Number(
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    ),
                                )
                            "
                        >
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="ui-table-shell overflow-hidden">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="px-3 py-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="emit('sort', 'scheduled_for')"
                                >
                                    التاريخ
                                    <component
                                        :is="sortIconFor('scheduled_for')"
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th class="px-3 py-3">الوقت</th>
                            <th class="px-3 py-3">المريض</th>
                            <th class="px-3 py-3">رقم الملف</th>
                            <th class="px-3 py-3">العيادة</th>
                            <th class="px-3 py-3">الطبيب</th>
                            <th class="px-3 py-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                    @click="emit('sort', 'status')"
                                >
                                    الحالة
                                    <component
                                        :is="sortIconFor('status')"
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th class="px-3 py-3 text-right">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="appointment in appointments.data"
                            :key="appointment.id"
                            class="ui-table-row align-top"
                        >
                            <td class="px-3 py-3" data-label="التاريخ">
                                {{ formatDate(appointment.scheduled_for) }}
                            </td>
                            <td
                                class="px-3 py-3 tabular-nums"
                                data-label="الوقت"
                            >
                                {{ formatTime(appointment.scheduled_for) }}
                            </td>
                            <td class="px-3 py-3" data-label="المريض">
                                <div class="font-semibold text-foreground">
                                    {{ appointment.patient?.full_name ?? '-' }}
                                </div>
                            </td>
                            <td class="px-3 py-3" data-label="رقم الملف">
                                {{ appointment.patient?.file_number ?? '-' }}
                            </td>
                            <td class="px-3 py-3" data-label="العيادة">
                                {{
                                    appointment.doctor?.department?.name ?? '-'
                                }}
                            </td>
                            <td class="px-3 py-3" data-label="الطبيب">
                                <div class="font-semibold text-foreground">
                                    {{ appointment.doctor?.name ?? '-' }}
                                </div>
                                <div
                                    v-if="appointment.doctor?.specialty"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ appointment.doctor.specialty }}
                                </div>
                            </td>
                            <td class="px-3 py-3" data-label="الحالة">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        appointmentStatusClass(
                                            appointment.status,
                                        )
                                    "
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="
                                            appointmentStatusDotClass(
                                                appointment.status,
                                            )
                                        "
                                    />
                                    {{
                                        appointmentStatusLabel(
                                            appointment.status,
                                        )
                                    }}
                                </span>
                            </td>
                            <td
                                class="table-cell-actions px-3 py-3 md:text-right"
                                data-label="الإجراءات"
                            >
                                <div class="flex flex-wrap items-center justify-end gap-1">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 rounded-lg px-2 text-[0.68rem]"
                                        @click="emit('view', appointment)"
                                    >
                                        عرض
                                    </Button>
                                    <Button
                                        v-if="canEditAppointment"
                                        type="button"
                                        variant="default"
                                        size="sm"
                                        class="h-8 rounded-lg px-2 text-[0.68rem]"
                                        @click="emit('edit', appointment)"
                                    >
                                        تعديل
                                    </Button>
                                    <Form
                                        v-if="canUpdateStatus"
                                        v-bind="
                                            AppointmentController.transitionStatus.form(
                                                appointment.id,
                                            )
                                        "
                                        class="inline-flex"
                                        v-slot="{ processing }"
                                        @success="
                                            emit('status-transition-success')
                                        "
                                        @error="emit('status-transition-error')"
                                    >
                                        <select
                                            name="status"
                                            class="pattern-field-clay h-8 w-24 rounded-lg px-1.5 py-0.5 text-[0.68rem]"
                                        >
                                            <option value="">
                                                الحالة
                                            </option>
                                            <option
                                                v-for="status in transitionStatuses"
                                                :key="status"
                                                :value="status"
                                            >
                                                {{
                                                    appointmentStatusLabel(
                                                        status,
                                                    )
                                                }}
                                            </option>
                                        </select>
                                        <input
                                            type="hidden"
                                            name="cancel_reason"
                                            value=""
                                        />
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 rounded-lg px-1.5 text-[0.68rem]"
                                            :disabled="processing"
                                        >
                                            ✓
                                        </Button>
                                    </Form>
                                    <Button
                                        v-if="canDeleteAppointment"
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        class="h-8 rounded-lg px-1.5 text-[0.68rem] text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        @click="emit('delete', appointment)"
                                    >
                                        حذف
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-if="appointments.data.length === 0"
                            class="table-empty-state"
                        >
                            <td
                                colspan="8"
                                class="px-3 py-12 text-center"
                            >
                                <div
                                    class="mx-auto flex max-w-md flex-col items-center gap-2 text-muted-foreground"
                                >
                                    <CalendarSearch
                                        class="size-10 opacity-50"
                                    />
                                    <p
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        لا توجد مواعيد مطابقة
                                    </p>
                                    <p class="text-xs">
                                        جرّب تعديل الفلاتر أو مسحها لعرض نتائج
                                        أكثر.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 rounded-2xl border border-border bg-secondary/30 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-xs text-muted-foreground">
                    عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من
                    {{ appointments.meta.total }} سجل
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-9 rounded-xl px-3 text-xs"
                        :disabled="localPage === 1"
                        @click="emit('page', localPage - 1)"
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
                        class="h-9 rounded-xl px-3 text-xs"
                        :disabled="localPage >= totalLocalPages"
                        @click="emit('page', localPage + 1)"
                    >
                        التالي
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
