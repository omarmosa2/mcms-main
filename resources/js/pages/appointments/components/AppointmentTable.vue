<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    CalendarSearch,
    Check,
    Eye,
    Filter,
    IdCard,
    Pencil,
    Search,
    Stethoscope,
    Trash2,
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
    'start-visit',
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
            class="flex flex-col gap-3 border-b border-border/60 bg-secondary/30 px-5 py-3.5 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <CalendarSearch class="size-4.5" />
                </div>
                <div>
                    <h2 class="text-sm font-bold text-foreground">
                        جميع المواعيد
                    </h2>
                    <p class="text-[0.72rem] text-muted-foreground">
                        بحث وتصفية ومتابعة حالات الحجوزات
                    </p>
                </div>
            </div>
            <span
                class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1 text-[0.72rem] font-medium text-muted-foreground"
            >
                <CalendarSearch class="size-3.5 text-primary" />
                الإجمالي: {{ appointments.meta.total }}
            </span>
        </div>

        <div class="space-y-4 p-5">
            <div class="rounded-2xl border border-border/60 bg-secondary/20 p-4">
                <div class="grid gap-3 lg:grid-cols-12 lg:items-end">
                    <div class="grid gap-1.5 lg:col-span-4">
                        <Label
                            for="appointments_search"
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <Search class="size-3.5 text-primary" />
                            بحث
                        </Label>
                        <FilterSearch
                            id="appointments_search"
                            :model-value="localSearch"
                            placeholder="رقم الموعد، المريض، رقم الملف، الطبيب"
                            class="h-10 rounded-xl"
                            @update:model-value="emit('search', $event)"
                        />
                    </div>

                    <div class="grid gap-1.5 lg:col-span-2">
                        <Label
                            for="appointments_status"
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <Filter class="size-3.5 text-primary" />
                            الحالة
                        </Label>
                        <FilterSelect
                            id="appointments_status"
                            :model-value="localStatus"
                            :options="statusOptions"
                            placeholder="جميع الحالات"
                            class="h-10 rounded-xl"
                            @update:model-value="emit('status', $event)"
                        />
                    </div>

                    <div v-if="!isDoctor" class="grid gap-1.5 lg:col-span-2">
                        <Label
                            for="appointments_doctor"
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <Filter class="size-3.5 text-primary" />
                            الطبيب
                        </Label>
                        <FilterSelect
                            id="appointments_doctor"
                            :model-value="localDoctorId"
                            :options="doctorOptions"
                            placeholder="كل الأطباء"
                            class="h-10 rounded-xl"
                            @update:model-value="emit('doctor', $event)"
                        />
                    </div>

                    <div v-if="!isDoctor" class="grid gap-1.5 lg:col-span-2">
                        <Label
                            for="appointments_department"
                            class="flex items-center gap-1 text-xs font-semibold text-foreground"
                        >
                            <Filter class="size-3.5 text-primary" />
                            العيادة
                        </Label>
                        <FilterSelect
                            id="appointments_department"
                            :model-value="localDepartmentId"
                            :options="departmentOptions"
                            placeholder="كل العيادات"
                            class="h-10 rounded-xl"
                            @update:model-value="emit('department', $event)"
                        />
                    </div>

                    <div class="grid gap-1.5 lg:col-span-1">
                        <Label
                            for="appointments_date_from"
                            class="text-xs font-semibold text-foreground"
                        >
                            من
                        </Label>
                        <Input
                            id="appointments_date_from"
                            type="date"
                            :model-value="localDateFrom"
                            class="pattern-field-clay h-10 rounded-xl"
                            @update:model-value="
                                emit('date-from', String($event ?? ''))
                            "
                        />
                    </div>

                    <div class="grid gap-1.5 lg:col-span-1">
                        <Label
                            for="appointments_date_to"
                            class="text-xs font-semibold text-foreground"
                        >
                            إلى
                        </Label>
                        <Input
                            id="appointments_date_to"
                            type="date"
                            :model-value="localDateTo"
                            class="pattern-field-clay h-10 rounded-xl"
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
                    <div class="grid w-full gap-1.5 sm:ms-auto sm:w-40">
                        <Label
                            for="appointments_per_page"
                            class="text-xs font-semibold text-foreground"
                        >
                            صفوف لكل صفحة
                        </Label>
                        <select
                            id="appointments_per_page"
                            :value="localRowsPerPage"
                            class="pattern-field-clay h-10 cursor-pointer rounded-xl px-3 py-1.5"
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

            <div class="overflow-hidden rounded-2xl border border-border/60">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-border/60 bg-secondary/40">
                            <th
                                class="px-4 py-3 text-right text-[0.72rem] font-semibold text-muted-foreground"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 transition hover:text-foreground"
                                    @click="emit('sort', 'scheduled_for')"
                                >
                                    التاريخ
                                    <component
                                        :is="sortIconFor('scheduled_for')"
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th
                                class="px-4 py-3 text-right text-[0.72rem] font-semibold text-muted-foreground"
                            >
                                الوقت
                            </th>
                            <th
                                class="px-4 py-3 text-right text-[0.72rem] font-semibold text-muted-foreground"
                            >
                                المريض
                            </th>
                            <th
                                class="px-4 py-3 text-right text-[0.72rem] font-semibold text-muted-foreground"
                            >
                                رقم الملف
                            </th>
                            <th
                                class="px-4 py-3 text-right text-[0.72rem] font-semibold text-muted-foreground"
                            >
                                العيادة
                            </th>
                            <th
                                class="px-4 py-3 text-right text-[0.72rem] font-semibold text-muted-foreground"
                            >
                                الطبيب
                            </th>
                            <th
                                class="px-4 py-3 text-right text-[0.72rem] font-semibold text-muted-foreground"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 transition hover:text-foreground"
                                    @click="emit('sort', 'status')"
                                >
                                    الحالة
                                    <component
                                        :is="sortIconFor('status')"
                                        class="size-3.5"
                                    />
                                </button>
                            </th>
                            <th
                                class="px-4 py-3 text-left text-[0.72rem] font-semibold text-muted-foreground"
                            >
                                الإجراءات
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="appointment in appointments.data"
                            :key="appointment.id"
                            class="border-b border-border/40 transition-colors last:border-0 hover:bg-secondary/30"
                        >
                            <td
                                class="px-4 py-3 text-sm"
                                data-label="التاريخ"
                            >
                                {{ formatDate(appointment.scheduled_for) }}
                            </td>
                            <td
                                class="px-4 py-3 text-sm tabular-nums"
                                data-label="الوقت"
                            >
                                {{ formatTime(appointment.scheduled_for) }}
                            </td>
                            <td class="px-4 py-3" data-label="المريض">
                                <div class="text-sm font-semibold text-foreground">
                                    {{ appointment.patient?.full_name ?? '-' }}
                                </div>
                            </td>
                            <td
                                class="px-4 py-3 text-sm tabular-nums"
                                data-label="رقم الملف"
                            >
                                {{ appointment.patient?.file_number ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm" data-label="العيادة">
                                {{
                                    appointment.doctor?.department?.name ?? '-'
                                }}
                            </td>
                            <td class="px-4 py-3" data-label="الطبيب">
                                <div class="text-sm font-semibold text-foreground">
                                    {{ appointment.doctor?.name ?? '-' }}
                                </div>
                                <div
                                    v-if="appointment.doctor?.specialty"
                                    class="text-[0.68rem] text-muted-foreground"
                                >
                                    {{ appointment.doctor.specialty }}
                                </div>
                            </td>
                            <td class="px-4 py-3" data-label="الحالة">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[0.72rem] font-medium"
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
                                class="px-4 py-3"
                                data-label="الإجراءات"
                            >
                                <div
                                    class="flex flex-wrap items-center gap-1"
                                >
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 rounded-lg text-success"
                                        title="بدء الزيارة"
                                        @click="emit('start-visit', appointment)"
                                    >
                                        <Stethoscope class="size-3.5" />
                                    </Button>
                                    <Link
                                        v-if="appointment.patient?.id"
                                        :href="`/patients/${appointment.patient.id}/card`"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100"
                                        title="بطاقة المريض"
                                    >
                                        <IdCard class="size-3.5" />
                                    </Link>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 rounded-lg"
                                        aria-label="عرض الموعد"
                                        @click="emit('view', appointment)"
                                    >
                                        <Eye class="size-3.5" />
                                    </Button>
                                    <Button
                                        v-if="canEditAppointment"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 rounded-lg"
                                        aria-label="تعديل الموعد"
                                        @click="emit('edit', appointment)"
                                    >
                                        <Pencil class="size-3.5" />
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
                                            class="pattern-field-clay h-8 w-24 cursor-pointer rounded-lg px-1.5 py-0.5 text-[0.68rem]"
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
                                            size="icon"
                                            class="size-8 rounded-lg text-success"
                                            aria-label="تحديث الحالة"
                                            :disabled="processing"
                                        >
                                            <Check class="size-3.5" />
                                        </Button>
                                    </Form>
                                    <Button
                                        v-if="canDeleteAppointment"
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="size-8 rounded-lg text-destructive hover:bg-destructive/10"
                                        aria-label="حذف الموعد"
                                        @click="emit('delete', appointment)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="appointments.data.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-14 text-center"
                            >
                                <div
                                    class="mx-auto flex max-w-md flex-col items-center gap-2 text-muted-foreground"
                                >
                                    <div
                                        class="flex size-14 items-center justify-center rounded-2xl bg-background shadow-sm"
                                    >
                                        <CalendarSearch class="size-7" />
                                    </div>
                                    <p
                                        class="text-sm font-bold text-foreground"
                                    >
                                        لا توجد مواعيد مطابقة
                                    </p>
                                    <p class="text-xs">
                                        جرّب تعديل الفلاتر أو مسحها لعرض نتائج
                                        أكثر
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 rounded-2xl border border-border/60 bg-secondary/20 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-[0.72rem] text-muted-foreground">
                    عرض {{ localVisibleFrom }}-{{ localVisibleTo }} من
                    {{ appointments.meta.total }} سجل
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 rounded-lg px-3 text-[0.72rem]"
                        :disabled="localPage === 1"
                        @click="emit('page', localPage - 1)"
                    >
                        السابق
                    </Button>
                    <span
                        class="text-[0.72rem] font-semibold text-foreground/85"
                    >
                        صفحة {{ localPage }} / {{ totalLocalPages }}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 rounded-lg px-3 text-[0.72rem]"
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
