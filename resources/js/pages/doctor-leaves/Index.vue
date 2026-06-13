<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Ban, CalendarOff, Clock, Pencil, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import DoctorLeaveController from '@/actions/App/Http/Controllers/DoctorLeaves/DoctorLeaveController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type DoctorOption = {
    id: number;
    name: string | null;
    department_id: number | null;
    department: { id: number; name: string } | null;
};

type DepartmentOption = {
    id: number;
    name: string;
};

type DoctorLeave = {
    id: number;
    doctor_id: number;
    doctor: { id: number; name: string } | null;
    department_id: number;
    department: { id: number; name: string } | null;
    type: 'full_day' | 'hourly';
    leave_date: string;
    start_time: string | null;
    end_time: string | null;
    reason: string | null;
    status: 'active' | 'canceled';
    appointments_count: number;
};

type PaginatedLeaves = {
    data: DoctorLeave[];
};

const props = defineProps<{
    leaves: PaginatedLeaves;
    doctors: DoctorOption[];
    departments: DepartmentOption[];
    filters: {
        doctor_id: number | null;
        department_id: number | null;
        status: string | null;
        date_from: string | null;
        date_to: string | null;
        per_page: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'إجازات الأطباء',
                href: DoctorLeaveController.index(),
            },
        ],
    },
});

const selectedDoctorId = ref('');
const selectedDepartmentId = ref('');
const leaveType = ref<'full_day' | 'hourly'>('full_day');
const editingLeave = ref<DoctorLeave | null>(null);
const isEditDialogOpen = ref(false);

const doctorOptions = computed(() =>
    props.doctors.map((doctor) => ({
        ...doctor,
        label: doctor.department?.name
            ? `${doctor.name} - ${doctor.department.name}`
            : (doctor.name ?? `#${doctor.id}`),
    })),
);

watch(selectedDoctorId, (doctorId) => {
    if (!doctorId) {
        selectedDepartmentId.value = '';
        return;
    }

    const doctor = props.doctors.find(
        (item) => String(item.id) === String(doctorId),
    );

    if (doctor?.department_id != null) {
        selectedDepartmentId.value = String(doctor.department_id);
    }
});

function openEditDialog(leave: DoctorLeave): void {
    editingLeave.value = leave;
    isEditDialogOpen.value = true;
}

function closeEditDialog(): void {
    editingLeave.value = null;
    isEditDialogOpen.value = false;
}

function cancelLeave(leave: DoctorLeave): void {
    router.patch(
        DoctorLeaveController.cancel.url(leave.id),
        {},
        {
            onSuccess: () => toast.success('تم إلغاء الإجازة بنجاح'),
        },
    );
}

function deleteLeave(leave: DoctorLeave): void {
    router.delete(DoctorLeaveController.destroy.url(leave.id), {
        onSuccess: () => toast.success('تم حذف الإجازة بنجاح'),
    });
}

function typeLabel(type: DoctorLeave['type']): string {
    return type === 'full_day' ? 'كاملة' : 'ساعية';
}

function statusLabel(status: DoctorLeave['status']): string {
    return status === 'active' ? 'نشطة' : 'ملغاة';
}

function formatTime(time: string | null): string {
    if (!time) {
        return '-';
    }

    return time.substring(0, 5);
}
</script>

<template>
    <Head title="إجازات الأطباء" />

    <div class="space-y-6" dir="rtl">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="page-title">إجازات الأطباء</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    إدارة الإجازات الكاملة والساعية بدون تعديل الدوام الأساسي
                    للطبيب.
                </p>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Plus class="size-4 text-[var(--accent-mint)]" />
                    إضافة إجازة
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="DoctorLeaveController.store.form()"
                    class="grid gap-4 lg:grid-cols-6"
                    @success="toast.success('تمت إضافة الإجازة بنجاح')"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2 lg:col-span-2">
                        <Label>الطبيب</Label>
                        <select
                            v-model="selectedDoctorId"
                            name="doctor_id"
                            class="pattern-field-clay h-10 w-full px-3"
                        >
                            <option value="">اختر الطبيب</option>
                            <option
                                v-for="doctor in doctorOptions"
                                :key="doctor.id"
                                :value="doctor.id"
                            >
                                {{ doctor.label }}
                            </option>
                        </select>
                        <InputError :message="errors.doctor_id" />
                    </div>

                    <div class="grid gap-2 lg:col-span-2">
                        <Label>العيادة</Label>
                        <select
                            v-model="selectedDepartmentId"
                            name="department_id"
                            class="pattern-field-clay h-10 w-full px-3"
                            
                        >
                            <option value="">اختر العيادة</option>
                            <option
                                v-for="department in departments"
                                :key="department.id"
                                :value="department.id"
                            >
                                {{ department.name }}
                            </option>
                        </select>
                        <InputError :message="errors.department_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label>نوع الإجازة</Label>
                        <select
                            v-model="leaveType"
                            name="type"
                            class="pattern-field-clay h-10 w-full px-3"
                        >
                            <option value="full_day">كاملة</option>
                            <option value="hourly">ساعية</option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label>التاريخ</Label>
                        <Input name="leave_date" type="date" />
                        <InputError :message="errors.leave_date" />
                    </div>

                    <div v-if="leaveType === 'hourly'" class="grid gap-2">
                        <Label>وقت البداية</Label>
                        <Input name="start_time" type="time" />
                        <InputError :message="errors.start_time" />
                    </div>

                    <div v-if="leaveType === 'hourly'" class="grid gap-2">
                        <Label>وقت النهاية</Label>
                        <Input name="end_time" type="time" />
                        <InputError :message="errors.end_time" />
                    </div>

                    <div class="grid gap-2 lg:col-span-4">
                        <Label>السبب</Label>
                        <Input name="reason" placeholder="اختياري" />
                        <InputError :message="errors.reason" />
                    </div>

                    <div class="flex items-end">
                        <Button
                            type="submit"
                            :disabled="processing"
                            class="h-10"
                        >
                            <Save class="ms-2 size-4" />
                            حفظ
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <CalendarOff class="size-4 text-[var(--accent-teal)]" />
                    الإجازات المسجلة
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="ui-table w-full">
                        <thead>
                            <tr>
                                <th class="text-start">الطبيب</th>
                                <th class="text-start">العيادة</th>
                                <th class="text-start">النوع</th>
                                <th class="text-start">التاريخ</th>
                                <th class="text-start">الفترة</th>
                                <th class="text-start">الحالة</th>
                                <th class="text-start">تنبيه</th>
                                <th class="text-end">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="leave in leaves.data"
                                :key="leave.id"
                                class="ui-table-row"
                            >
                                <td class="font-medium">
                                    {{ leave.doctor?.name ?? '-' }}
                                </td>
                                <td>{{ leave.department?.name ?? '-' }}</td>
                                <td>{{ typeLabel(leave.type) }}</td>
                                <td>{{ leave.leave_date }}</td>
                                <td class="tabular-nums">
                                    <span v-if="leave.type === 'hourly'"
                                        >{{ formatTime(leave.start_time) }} -
                                        {{ formatTime(leave.end_time) }}</span
                                    >
                                    <span v-else>طوال اليوم</span>
                                </td>
                                <td>
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            leave.status === 'active'
                                                ? 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{ statusLabel(leave.status) }}
                                    </span>
                                </td>
                                <td>
                                    <span
                                        v-if="leave.appointments_count > 0"
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
                                    >
                                        <Clock class="size-3" />
                                        {{ leave.appointments_count }} موعد
                                        للمراجعة
                                    </span>
                                    <span
                                        v-else
                                        class="text-sm text-muted-foreground"
                                        >-</span
                                    >
                                </td>
                                <td class="table-cell-actions">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Button
                                            variant="neumorphic"
                                            size="icon"
                                            class="size-8"
                                            @click="openEditDialog(leave)"
                                        >
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            v-if="leave.status === 'active'"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-destructive hover:text-destructive"
                                            @click="cancelLeave(leave)"
                                        >
                                            <Ban class="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-destructive hover:text-destructive"
                                            @click="deleteLeave(leave)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="leaves.data.length === 0"
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    لا توجد إجازات مسجلة حتى الآن.
                </div>
            </CardContent>
        </Card>

        <Dialog
            :open="isEditDialogOpen"
            @update:open="(open) => !open && closeEditDialog()"
        >
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>تعديل الإجازة</DialogTitle>
                    <DialogDescription
                        >تحديث نوع الإجازة أو تاريخها أو الفترة
                        الساعية.</DialogDescription
                    >
                </DialogHeader>

                <Form
                    v-if="editingLeave"
                    v-bind="DoctorLeaveController.update.form(editingLeave.id)"
                    class="grid gap-4 md:grid-cols-2"
                    @success="closeEditDialog"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label>الطبيب</Label>
                        <select
                            name="doctor_id"
                            class="pattern-field-clay h-10 w-full px-3"
                        >
                            <option
                                v-for="doctor in doctorOptions"
                                :key="doctor.id"
                                :value="doctor.id"
                                :selected="editingLeave.doctor_id === doctor.id"
                            >
                                {{ doctor.label }}
                            </option>
                        </select>
                        <InputError :message="errors.doctor_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label>العيادة</Label>
                        <select
                            name="department_id"
                            class="pattern-field-clay h-10 w-full px-3"
                        >
                            <option
                                v-for="department in departments"
                                :key="department.id"
                                :value="department.id"
                                :selected="
                                    editingLeave.department_id === department.id
                                "
                            >
                                {{ department.name }}
                            </option>
                        </select>
                        <InputError :message="errors.department_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label>نوع الإجازة</Label>
                        <select
                            name="type"
                            class="pattern-field-clay h-10 w-full px-3"
                        >
                            <option
                                value="full_day"
                                :selected="editingLeave.type === 'full_day'"
                            >
                                كاملة
                            </option>
                            <option
                                value="hourly"
                                :selected="editingLeave.type === 'hourly'"
                            >
                                ساعية
                            </option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label>التاريخ</Label>
                        <Input
                            name="leave_date"
                            type="date"
                            :default-value="editingLeave.leave_date"
                        />
                        <InputError :message="errors.leave_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label>وقت البداية</Label>
                        <Input
                            name="start_time"
                            type="time"
                            :default-value="
                                editingLeave.start_time ?? undefined
                            "
                        />
                        <InputError :message="errors.start_time" />
                    </div>

                    <div class="grid gap-2">
                        <Label>وقت النهاية</Label>
                        <Input
                            name="end_time"
                            type="time"
                            :default-value="editingLeave.end_time ?? undefined"
                        />
                        <InputError :message="errors.end_time" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label>السبب</Label>
                        <Input
                            name="reason"
                            :default-value="editingLeave.reason ?? ''"
                        />
                        <InputError :message="errors.reason" />
                    </div>

                    <DialogFooter class="md:col-span-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="closeEditDialog()"
                            >إلغاء</Button
                        >
                        <Button
                            type="submit"
                            variant="clay"
                            :disabled="processing"
                            >حفظ التغييرات</Button
                        >
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
