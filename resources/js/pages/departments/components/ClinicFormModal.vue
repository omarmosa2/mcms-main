<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import DepartmentController from '@/actions/App/Http/Controllers/Departments/DepartmentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import ClinicWorkingHoursSelector from './ClinicWorkingHoursSelector.vue';
import type { ClinicWorkingDay, ClinicWorkingHour, Department } from './types';

const props = defineProps<{
    open: boolean;
    department?: Department | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const weekDays: ClinicWorkingDay[] = [
    'saturday',
    'sunday',
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
];

const emptyWorkingHours = (): ClinicWorkingHour[] =>
    weekDays.map((day) => ({
        day_of_week: day,
        is_active: false,
        start_time: null,
        end_time: null,
    }));

const normalizeWorkingHours = (
    rows: ClinicWorkingHour[] | undefined,
): ClinicWorkingHour[] => {
    const rowMap = new Map((rows ?? []).map((row) => [row.day_of_week, row]));

    return weekDays.map((day) => ({
        day_of_week: day,
        is_active: rowMap.get(day)?.is_active ?? false,
        start_time: rowMap.get(day)?.start_time ?? null,
        end_time: rowMap.get(day)?.end_time ?? null,
    }));
};

const form = useForm({
    name: '',
    code: '',
    description: '',
    is_active: true,
    working_hours: emptyWorkingHours(),
});

const isEditing = computed(() => props.department !== null && props.department !== undefined);
const title = computed(() => (isEditing.value ? 'تعديل العيادة' : 'إضافة عيادة جديدة'));

watch(
    () => [props.open, props.department] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.defaults({
            name: props.department?.name ?? '',
            code: props.department?.code ?? '',
            description: props.department?.description ?? '',
            is_active: props.department?.is_active ?? true,
            working_hours: normalizeWorkingHours(props.department?.working_hours),
        });
        form.reset();
    },
    { immediate: true },
);

const close = (): void => {
    emit('update:open', false);
};

const handleOpenChange = (value: boolean): void => {
    if (!value) {
        close();
        return;
    }

    emit('update:open', true);
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            close();
        },
    };

    if (props.department) {
        form.put(DepartmentController.update.url(props.department.id), options);
        return;
    }

    form.post(DepartmentController.store.url(), options);
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            class="max-h-[90vh] overflow-y-auto sm:max-w-3xl"
            dir="rtl"
            @escape-key-down="close"
            @interact-outside="close"
        >
            <DialogHeader class="text-right">
                <DialogTitle class="text-2xl font-bold text-foreground">{{ title }}</DialogTitle>
                <DialogDescription class="text-muted-foreground">
                    أدخل بيانات العيادة وحدد أيام وساعات الدوام المتاحة للحجز.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="clinic_name">اسم العيادة</Label>
                        <Input
                            id="clinic_name"
                            v-model="form.name"
                            class="h-12 rounded-2xl"
                            placeholder="مثال: عيادة الأسنان"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="clinic_code">الرمز</Label>
                        <Input
                            id="clinic_code"
                            v-model="form.code"
                            class="h-12 rounded-2xl"
                            placeholder="مثال: DENT"
                        />
                        <InputError :message="form.errors.code" />
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label for="clinic_description">الوصف</Label>
                        <textarea
                            id="clinic_description"
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-2xl border border-input bg-secondary/50 px-4 py-3 text-sm text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                            placeholder="وصف مختصر عن العيادة"
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <label class="flex items-center justify-between rounded-2xl border border-border bg-muted p-4 md:col-span-2">
                        <span>
                            <span class="block text-sm font-bold text-foreground">حالة العيادة</span>
                            <span class="block text-xs font-medium text-muted-foreground">العيادات غير النشطة لا تظهر كخيار عمل فعال.</span>
                        </span>
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-5 rounded border-input text-primary focus:ring-primary/20"
                        />
                    </label>
                </section>

                <ClinicWorkingHoursSelector v-model="form.working_hours" :errors="form.errors" />

                <DialogFooter class="gap-2 sm:justify-start">
                    <Button
                        type="submit"
                        class="h-11 rounded-2xl bg-primary px-6 font-bold text-primary-foreground hover:bg-primary/90"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'جار الحفظ...' : 'حفظ العيادة' }}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-11 rounded-2xl px-5"
                        @click="close"
                    >
                        إلغاء
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
