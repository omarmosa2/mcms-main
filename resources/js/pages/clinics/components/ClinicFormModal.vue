<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Building2, Save, X } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import ClinicController from '@/actions/App/Http/Controllers/Clinics/ClinicController';
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
import type { Clinic, ClinicWorkingDay, ClinicWorkingHour } from './types';

const props = defineProps<{
    open: boolean;
    department?: Clinic | null;
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

const indexToDay: Record<number, ClinicWorkingDay> = {
    6: 'saturday',
    0: 'sunday',
    1: 'monday',
    2: 'tuesday',
    3: 'wednesday',
    4: 'thursday',
    5: 'friday',
};

const normalizeWorkingHours = (
    rows: ClinicWorkingHour[] | undefined,
): ClinicWorkingHour[] => {
    const rowMap = new Map<string, ClinicWorkingHour>();

    for (const row of rows ?? []) {
        const key =
            typeof row.day_of_week === 'number'
                ? (indexToDay[row.day_of_week] ?? String(row.day_of_week))
                : String(row.day_of_week);

        rowMap.set(key, row);
    }

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

const isEditing = computed(
    () => props.department !== null && props.department !== undefined,
);
const title = computed(() =>
    isEditing.value ? 'تعديل العيادة' : 'إضافة عيادة جديدة',
);

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
            working_hours: normalizeWorkingHours(
                props.department?.working_hours,
            ),
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

const submit = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            close();
        },
    };

    if (props.department) {
        form.put(ClinicController.update.url(props.department.id), options);

        return;
    }

    form.post(ClinicController.store.url(), options);
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            size="2xl"
            class="max-h-[92vh] bg-card p-0"
            dir="rtl"
            @escape-key-down="close"
            @interact-outside="close"
        >
            <DialogHeader class="border-b border-border px-6 py-5 text-right">
                <div class="flex items-start gap-3 pl-10">
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Building2 class="size-6" />
                    </span>
                    <div class="min-w-0">
                        <DialogTitle
                            class="truncate text-2xl font-black text-foreground"
                        >
                            {{ title }}
                        </DialogTitle>
                        <DialogDescription
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            أدخل بيانات العيادة وحدد أيام وساعات الدوام المتاحة
                            للحجز.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form @submit.prevent="submit">
                <div class="max-h-[68vh] space-y-5 overflow-y-auto p-6">
                    <section
                        class="rounded-xl border border-border bg-muted/40 p-4"
                    >
                        <h3 class="mb-4 text-sm font-black text-foreground">
                            البيانات الأساسية
                        </h3>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="clinic_name">اسم العيادة</Label>
                                <Input
                                    id="clinic_name"
                                    v-model="form.name"
                                    class="h-11 rounded-lg"
                                    placeholder="مثال: عيادة الأسنان"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="clinic_code">
                                    الرمز
                                    <span class="font-normal text-muted-foreground">(اختياري)</span>
                                </Label>
                                <Input
                                    id="clinic_code"
                                    v-model="form.code"
                                    class="h-11 rounded-lg"
                                    placeholder="مثال: DENT (يُولّد تلقائيًا عند تركه فارغًا)"
                                />
                                <InputError :message="form.errors.code" />
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="clinic_description">الوصف</Label>
                                <textarea
                                    id="clinic_description"
                                    v-model="form.description"
                                    rows="3"
                                    class="w-full resize-y rounded-lg border border-input bg-card px-4 py-3 text-sm text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/10 focus:outline-none"
                                    placeholder="وصف مختصر عن العيادة"
                                ></textarea>
                                <InputError
                                    :message="form.errors.description"
                                />
                            </div>

                            <label
                                class="flex items-center justify-between gap-4 rounded-lg border border-border/70 bg-card px-4 py-3 md:col-span-2"
                            >
                                <span>
                                    <span
                                        class="block text-sm font-bold text-foreground"
                                    >
                                        حالة العيادة
                                    </span>
                                    <span
                                        class="block text-xs font-medium text-muted-foreground"
                                    >
                                        العيادات غير النشطة لا تظهر كخيار عمل
                                        فعال.
                                    </span>
                                </span>
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="size-5 rounded border-input text-primary focus:ring-primary/20"
                                />
                            </label>
                            <InputError :message="form.errors.is_active" />
                        </div>
                    </section>

                    <ClinicWorkingHoursSelector
                        v-model="form.working_hours"
                        :errors="form.errors"
                    />
                </div>

                <DialogFooter class="border-t border-border px-6 py-4">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="close"
                    >
                        <X class="size-4" />
                        إلغاء
                    </Button>
                    <Button
                        type="submit"
                        class="bg-primary text-primary-foreground hover:bg-primary/90"
                        :disabled="form.processing"
                    >
                        <Save class="size-4" />
                        {{ form.processing ? 'جار الحفظ...' : 'حفظ العيادة' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
