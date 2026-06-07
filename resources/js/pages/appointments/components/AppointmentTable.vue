<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
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
    PaginatedResponse,
    SortDirection,
    Option,
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
    selectedAppointmentIds: number[];
    deletableAppointmentIds: number[];
    areAllDeletableAppointmentsSelected: boolean;
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
    'update:selectedAppointmentIds',
    'remove-filter',
    'clear-filters',
    'clear-selection',
    'toggle-select-all',
    'previous-page',
    'next-page',
    'sort',
    'view',
    'edit',
    'delete',
    'bulk-delete',
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
    <div class="glass-panel-soft p-5">
        <div
            class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b pb-3"
        >
            <h3 class="pattern-typographic-title text-[0.76rem]">
                جميع المواعيد
            </h3>
            <span class="text-xs text-muted-foreground">
                الإجمالي: {{ appointments.meta.total }}
            </span>
        </div>

        <div
            class="space-y-3 rounded-2xl border border-border/70 bg-background/60 p-4"
        >
            <div class="grid gap-3 md:items-end lg:grid-cols-4">
                <div class="grid gap-2 lg:col-span-2">
                    <Label for="appointments_search">بحث</Label>
                    <FilterSearch
                        id="appointments_search"
                        :model-value="localSearch"
                        @update:model-value="emit('search', $event)"
                        placeholder="رقم الموعد، المريض، رقم الملف، الطبيب"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="appointments_status">الحالة</Label>
                    <FilterSelect
                        id="appointments_status"
                        :model-value="localStatus"
                        @update:model-value="emit('status', $event)"
                        :options="statusOptions"
                        placeholder="جميع الحالات"
                    />
                </div>

                <div v-if="!isDoctor" class="grid gap-2">
                    <Label for="appointments_doctor">الطبيب</Label>
                    <FilterSelect
                        id="appointments_doctor"
                        :model-value="localDoctorId"
                        @update:model-value="emit('doctor', $event)"
                        :options="doctorOptions"
                        placeholder="كل الأطباء"
                    />
                </div>
            </div>

            <div class="grid gap-3 md:items-end lg:grid-cols-4">
                <div v-if="!isDoctor" class="grid gap-2">
                    <Label for="appointments_department">العيادة</Label>
                    <FilterSelect
                        id="appointments_department"
                        :model-value="localDepartmentId"
                        @update:model-value="emit('department', $event)"
                        :options="departmentOptions"
                        placeholder="كل العيادات"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="appointments_date_from">من تاريخ</Label>
                    <Input
                        id="appointments_date_from"
                        type="date"
                        :model-value="localDateFrom"
                        class="pattern-field-clay"
                        @update:model-value="
                            emit('date-from', String($event ?? ''))
                        "
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="appointments_date_to">إلى تاريخ</Label>
                    <Input
                        id="appointments_date_to"
                        type="date"
                        :model-value="localDateTo"
                        class="pattern-field-clay"
                        @update:model-value="
                            emit('date-to', String($event ?? ''))
                        "
                    />
                </div>

                <div class="grid gap-2 md:max-w-44">
                    <Label for="appointments_per_page">صفوف لكل صفحة</Label>
                    <select
                        id="appointments_per_page"
                        :value="localRowsPerPage"
                        @change="
                            emit(
                                'rows-per-page',
                                Number(
                                    ($event.target as HTMLSelectElement).value,
                                ),
                            )
                        "
                        class="pattern-field-clay h-10 px-3 py-1.5"
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
                @remove="emit('remove-filter', $event)"
                @clear-all="emit('clear-filters')"
            />
        </div>

        <div
            v-if="canDeleteAppointment && selectedAppointmentIds.length > 0"
            class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3"
        >
            <Button
                type="button"
                variant="destructive"
                size="sm"
                class="min-h-[44px]"
                @click="emit('bulk-delete')"
            >
                حذف المحدد ({{ selectedAppointmentIds.length }})
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="min-h-[44px]"
                @click="emit('update:selectedAppointmentIds', [])"
            >
                إلغاء التحديد
            </Button>
        </div>

        <div class="ui-table-shell">
            <table class="ui-table md:min-w-[1080px]">
                <thead>
                    <tr>
                        <th v-if="canDeleteAppointment" class="px-3 py-2">
                            <input
                                type="checkbox"
                                class="size-4 rounded border-border"
                                :checked="areAllDeletableAppointmentsSelected"
                                @change="emit('toggle-select-all', $event)"
                            />
                        </th>
                        <th class="px-3 py-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 font-semibold transition hover:text-foreground"
                                @click="emit('sort', 'appointment_number')"
                            >
                                رقم الموعد
                                <component
                                    :is="sortIconFor('appointment_number')"
                                    class="size-3.5"
                                />
                            </button>
                        </th>
                        <th class="px-3 py-2">
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
                        <th class="px-3 py-2">الوقت</th>
                        <th class="px-3 py-2">المريض</th>
                        <th class="px-3 py-2">رقم الملف</th>
                        <th class="px-3 py-2">العيادة</th>
                        <th class="px-3 py-2">الطبيب</th>
                        <th class="px-3 py-2">
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
                        <th class="px-3 py-2 text-right">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="appointment in appointments.data"
                        :key="appointment.id"
                        class="ui-table-row align-top"
                    >
                        <td
                            v-if="canDeleteAppointment"
                            class="px-3 py-2"
                            data-label="تحديد"
                        >
                            <input
                                v-if="appointment.status === 'scheduled'"
                                :checked="
                                    selectedAppointmentIds.includes(
                                        appointment.id,
                                    )
                                "
                                type="checkbox"
                                class="size-4 rounded border-border"
                                :value="appointment.id"
                                @change="
                                    ($event) => {
                                        const checked = (
                                            $event.target as HTMLInputElement
                                        ).checked;
                                        if (checked) {
                                            emit(
                                                'update:selectedAppointmentIds',
                                                [
                                                    ...selectedAppointmentIds,
                                                    appointment.id,
                                                ],
                                            );
                                        } else {
                                            emit(
                                                'update:selectedAppointmentIds',
                                                selectedAppointmentIds.filter(
                                                    (id) =>
                                                        id !== appointment.id,
                                                ),
                                            );
                                        }
                                    }
                                "
                            />
                        </td>
                        <td
                            class="px-3 py-2 font-medium"
                            data-label="رقم الموعد"
                        >
                            {{ appointment.appointment_number }}
                        </td>
                        <td class="px-3 py-2" data-label="التاريخ">
                            {{ formatDate(appointment.scheduled_for) }}
                        </td>
                        <td class="px-3 py-2" data-label="الوقت">
                            {{ formatTime(appointment.scheduled_for) }}
                        </td>
                        <td class="px-3 py-2" data-label="المريض">
                            <div class="font-medium text-foreground">
                                {{ appointment.patient?.full_name ?? '-' }}
                            </div>
                        </td>
                        <td class="px-3 py-2" data-label="رقم الملف">
                            {{ appointment.patient?.file_number ?? '-' }}
                        </td>
                        <td class="px-3 py-2" data-label="العيادة">
                            {{ appointment.doctor?.department?.name ?? '-' }}
                        </td>
                        <td class="px-3 py-2" data-label="الطبيب">
                            <div class="font-medium text-foreground">
                                {{ appointment.doctor?.name ?? '-' }}
                            </div>
                            <div
                                v-if="appointment.doctor?.specialty"
                                class="text-xs text-muted-foreground"
                            >
                                {{ appointment.doctor.specialty }}
                            </div>
                        </td>
                        <td class="px-3 py-2" data-label="الحالة">
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium capitalize"
                                :class="
                                    appointmentStatusClass(appointment.status)
                                "
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="
                                        appointmentStatusDotClass(
                                            appointment.status,
                                        )
                                    "
                                ></span>
                                {{ appointmentStatusLabel(appointment.status) }}
                            </span>
                        </td>
                        <td
                            class="table-cell-actions px-3 py-2 md:text-right"
                            data-label="الإجراءات"
                        >
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-10 px-3 text-xs"
                                    @click="emit('view', appointment)"
                                >
                                    عرض
                                </Button>
                                <Button
                                    v-if="canEditAppointment"
                                    type="button"
                                    variant="default"
                                    size="sm"
                                    class="h-10 px-3 text-xs"
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
                                    class="flex items-center gap-2"
                                    v-slot="{ processing }"
                                    @success="emit('status-transition-success')"
                                    @error="emit('status-transition-error')"
                                >
                                    <select
                                        name="status"
                                        class="pattern-field-clay h-10 px-2 py-1 text-xs"
                                    >
                                        <option value="">تغيير الحالة</option>
                                        <option
                                            v-for="status in transitionStatuses"
                                            :key="status"
                                            :value="status"
                                        >
                                            {{ appointmentStatusLabel(status) }}
                                        </option>
                                    </select>
                                    <Input
                                        name="cancel_reason"
                                        placeholder="سبب الإلغاء"
                                        class="pattern-field-clay h-10 w-36 px-2 py-1 text-xs"
                                    />
                                    <Button
                                        type="submit"
                                        variant="default"
                                        size="sm"
                                        class="h-10 px-2 text-xs"
                                        :disabled="processing"
                                    >
                                        تطبيق
                                    </Button>
                                </Form>
                                <Button
                                    v-if="canDeleteAppointment"
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    class="h-10 px-3 text-xs"
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
                            :colspan="canDeleteAppointment ? 10 : 9"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            لا توجد مواعيد تطابق عوامل التصفية الحالية.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-background/60 px-3 py-2"
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
                    class="h-10 px-3 text-xs"
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
                    class="h-10 px-3 text-xs"
                    :disabled="localPage >= totalLocalPages"
                    @click="emit('page', localPage + 1)"
                >
                    التالي
                </Button>
            </div>
        </div>
    </div>
</template>
