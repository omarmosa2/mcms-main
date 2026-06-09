<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Clock, CalendarDays, Pencil, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import DoctorScheduleController from '@/actions/App/Http/Controllers/DoctorSchedules/DoctorScheduleController';
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
import { Switch } from '@/components/ui/switch';

const props = defineProps<{
    schedules: {
        data: Array<{
            id: number;
            doctor_id: number;
            doctor?: { id: number; name: string };
            day_of_week: number;
            day_name: string;
            start_time: string;
            end_time: string;
            is_available: boolean;
        }>;
    };
    filters: {
        doctor_id?: number | null;
        is_available?: boolean | null;
        per_page: number;
    };
}>();

const doctors = computed(() => {
    const doctorMap = new Map();
    props.schedules.data.forEach((schedule) => {
        if (schedule.doctor && !doctorMap.has(schedule.doctor.id)) {
            doctorMap.set(schedule.doctor.id, schedule.doctor);
        }
    });

    return Array.from(doctorMap.values());
});

const DAYS = [
    { value: 0, label: 'الأحد' },
    { value: 1, label: 'الإثنين' },
    { value: 2, label: 'الثلاثاء' },
    { value: 3, label: 'الأربعاء' },
    { value: 4, label: 'الخميس' },
    { value: 5, label: 'الجمعة' },
    { value: 6, label: 'السبت' },
];

const selectedDay = ref<number | null>(null);
const startTime = ref('09:00');
const endTime = ref('17:00');
const isLoadingHours = ref(false);

watch(selectedDay, async (newDay) => {
    if (newDay === null) {
        return;
    }

    isLoadingHours.value = true;

    try {
        const response = await fetch(`/doctor-schedules/clinic-hours?day_of_week=${newDay}`, {
            headers: {
                'X-Inertia': 'true',
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (data.start_time && data.end_time) {
            startTime.value = data.start_time.substring(0, 5);
            endTime.value = data.end_time.substring(0, 5);
        }
    } catch (error) {
        console.error('Failed to fetch clinic hours:', error);
    } finally {
        isLoadingHours.value = false;
    }
});

const editingSchedule = ref<{
    id: number;
    doctor_id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    is_available: boolean;
} | null>(null);
const isEditDialogOpen = ref(false);

const openEditDialog = (schedule: (typeof props.schedules.data)[number]) => {
    editingSchedule.value = {
        id: schedule.id,
        doctor_id: schedule.doctor_id,
        day_of_week: schedule.day_of_week,
        start_time: schedule.start_time,
        end_time: schedule.end_time,
        is_available: schedule.is_available,
    };
    isEditDialogOpen.value = true;
};

const closeEditDialog = () => {
    editingSchedule.value = null;
    isEditDialogOpen.value = false;
};

const groupedSchedules = computed(() => {
    const grouped: Record<number, typeof props.schedules.data> = {};
    props.schedules.data.forEach((schedule) => {
        if (!grouped[schedule.doctor_id]) {
            grouped[schedule.doctor_id] = [];
        }

        grouped[schedule.doctor_id].push(schedule);
    });

    return grouped;
});

function deleteSchedule(scheduleId: number) {
    if (!confirm('هل أنت متأكد من حذف هذا الجدول؟')) {
        return;
    }

    router.delete(DoctorScheduleController.destroy(scheduleId).url(), {
        onSuccess: () => {
            toast.success('تم حذف جدول الدوام بنجاح');
        },
    });
}

function formatTime(time: string): string {
    if (!time) {
        return '';
    }

    const [hours, minutes] = time.split(':');
    const h = parseInt(hours, 10);
    const period = h >= 12 ? 'م' : 'ص';
    const displayHours = h % 12 || 12;

    return `${displayHours}:${minutes} ${period}`;
}
</script>

<template>
    <Head title="جداول دوام الأطباء" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="page-title">جداول دوام الأطباء</h1>
                <p class="mt-1 text-sm text-muted-foreground">إدارة أوقات دوام الأطباء لكل يوم من أيام الأسبوع</p>
            </div>
        </div>

        <!-- إضافة جدول جديد -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Plus class="size-4 text-[var(--accent-mint)]" />
                    إضافة جدول دوام
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="DoctorScheduleController.store.form()"
                    class="flex flex-wrap items-end gap-4"
                    @success="toast.success('تم إضافة جدول الدوام بنجاح')"
                    v-slot="{ errors, processing }"
                >
                    <div class="flex-1 min-w-[200px]">
                        <Label>الطبيب</Label>
                        <select
                            name="doctor_id"
                            class="pattern-field-clay h-9 w-full px-3 py-1.5"
                        >
                            <option value="">اختر الطبيب</option>
                            <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">
                                {{ doctor.name }}
                            </option>
                        </select>
                        <InputError :message="errors.doctor_id" />
                    </div>

                    <div class="w-[160px]">
                        <Label>اليوم</Label>
                        <select
                            v-model="selectedDay"
                            name="day_of_week"
                            class="pattern-field-clay h-9 w-full px-3 py-1.5"
                        >
                            <option value="">اختر اليوم</option>
                            <option v-for="day in DAYS" :key="day.value" :value="day.value">
                                {{ day.label }}
                            </option>
                        </select>
                        <InputError :message="errors.day_of_week" />
                    </div>

                    <div class="w-[140px]">
                        <Label>وقت البدء</Label>
                        <Input v-model="startTime" name="start_time" type="time" :disabled="isLoadingHours" />
                        <InputError :message="errors.start_time" />
                    </div>

                    <div class="w-[140px]">
                        <Label>وقت الانتهاء</Label>
                        <Input v-model="endTime" name="end_time" type="time" :disabled="isLoadingHours" />
                        <InputError :message="errors.end_time" />
                    </div>

                    <div class="flex items-center gap-2 pb-1">
                        <Switch name="is_available" :default-value="true" />
                        <Label class="text-sm">متاح</Label>
                    </div>

                    <Button type="submit" :disabled="processing || isLoadingHours">
                        <Save class="size-4 ms-2" />
                        حفظ
                    </Button>
                </Form>
            </CardContent>
        </Card>

        <!-- الجداول الحالية -->
        <div v-for="(schedules, doctorId) in groupedSchedules" :key="doctorId" class="space-y-3">
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <CalendarDays class="size-4 text-[var(--accent-teal)]" />
                        {{ schedules[0]?.doctor?.name || `طبيب #${doctorId}` }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="ui-table w-full">
                            <thead>
                                <tr>
                                    <th class="text-start">اليوم</th>
                                    <th class="text-start">وقت البدء</th>
                                    <th class="text-start">وقت الانتهاء</th>
                                    <th class="text-start">الحالة</th>
                                    <th class="text-end">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="schedule in schedules" :key="schedule.id" class="ui-table-row">
                                    <td data-label="اليوم" class="font-medium">{{ schedule.day_name }}</td>
                                    <td data-label="وقت البدء" class="tabular-nums">
                                        {{ formatTime(schedule.start_time) }}
                                    </td>
                                    <td data-label="وقت الانتهاء" class="tabular-nums">
                                        {{ formatTime(schedule.end_time) }}
                                    </td>
                                    <td data-label="الحالة">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="schedule.is_available
                                                ? 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400'
                                                : 'bg-muted text-muted-foreground'"
                                        >
                                            <span class="size-1.5 rounded-full" :class="schedule.is_available ? 'bg-success-500' : 'bg-muted-foreground'" />
                                            {{ schedule.is_available ? 'متاح' : 'غير متاح' }}
                                        </span>
                                    </td>
                                    <td data-label="إجراءات" class="table-cell-actions">
                                        <div class="flex items-center justify-end gap-2">
                                            <Button
                                                variant="neumorphic"
                                                size="icon"
                                                class="size-8"
                                                @click="openEditDialog(schedule)"
                                            >
                                                <Pencil class="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-8 text-destructive hover:text-destructive"
                                                @click="deleteSchedule(schedule.id)"
                                            >
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-if="Object.keys(groupedSchedules).length === 0" class="flex flex-col items-center justify-center py-12 text-center">
            <Clock class="size-12 text-muted-foreground/40" />
            <h3 class="mt-4 text-lg font-semibold">لا توجد جداول دوام</h3>
            <p class="mt-1 text-sm text-muted-foreground">ابدأ بإضافة جدول دوام للأطباء باستخدام النموذج أعلاه</p>
        </div>

        <!-- Dialog تعديل الجدول -->
        <Dialog :open="isEditDialogOpen" @update:open="(open) => !open && closeEditDialog()">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>تعديل جدول الدوام</DialogTitle>
                    <DialogDescription>تحديث أوقات دوام الطبيب.</DialogDescription>
                </DialogHeader>

                <Form
                    v-if="editingSchedule"
                    v-bind="DoctorScheduleController.update.form(editingSchedule.id)"
                    class="space-y-4"
                    @success="closeEditDialog"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label>الطبيب</Label>
                        <select
                            name="doctor_id"
                            class="pattern-field-clay h-9 w-full px-3 py-1.5"
                        >
                            <option value="">اختر الطبيب</option>
                            <option
                                v-for="doctor in doctors"
                                :key="doctor.id"
                                :value="doctor.id"
                                :selected="editingSchedule.doctor_id === doctor.id"
                            >
                                {{ doctor.name }}
                            </option>
                        </select>
                        <InputError :message="errors.doctor_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label>اليوم</Label>
                        <select
                            name="day_of_week"
                            class="pattern-field-clay h-9 w-full px-3 py-1.5"
                        >
                            <option
                                v-for="day in DAYS"
                                :key="day.value"
                                :value="day.value"
                                :selected="editingSchedule.day_of_week === day.value"
                            >
                                {{ day.label }}
                            </option>
                        </select>
                        <InputError :message="errors.day_of_week" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>وقت البدء</Label>
                            <Input name="start_time" type="time" :default-value="editingSchedule.start_time" />
                            <InputError :message="errors.start_time" />
                        </div>
                        <div class="grid gap-2">
                            <Label>وقت الانتهاء</Label>
                            <Input name="end_time" type="time" :default-value="editingSchedule.end_time" />
                            <InputError :message="errors.end_time" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Switch name="is_available" :default-value="editingSchedule.is_available" />
                        <Label class="text-sm">متاح</Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" @click="closeEditDialog()">
                            إلغاء
                        </Button>
                        <Button type="submit" variant="clay" :disabled="processing">
                            حفظ التغييرات
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
