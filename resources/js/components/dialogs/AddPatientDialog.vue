<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
    open: boolean;
    onSuccess?: () => void;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const formResetKey = ref(0);

const inputClass = computed(
    () =>
        'w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors',
);

const resetFormState = (): void => {
    formResetKey.value += 1;
};

const handleSuccess = (): void => {
    resetFormState();
    props.onSuccess?.();
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[520px] bg-card rounded-xl">
            <DialogHeader class="p-6 pb-4 border-b border-border">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-0.5">
                        <DialogTitle class="text-base font-medium text-foreground">
                            إضافة مريض جديد
                        </DialogTitle>
                        <DialogDescription class="text-sm text-muted-foreground">
                            تسجيل بيانات المريض الأساسية
                        </DialogDescription>
                    </div>
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <UserPlus class="size-5" />
                    </div>
                </div>
            </DialogHeader>

            <Form
                :key="formResetKey"
                id="patient-add-form"
                v-bind="PatientController.store.form()"
                class="contents"
                reset-on-success
                @success="handleSuccess"
                v-slot="{ errors, processing }"
            >
                <DialogBody class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="first_name" class="text-sm font-medium text-foreground">
                                الاسم الأول
                                <span class="text-destructive mr-1">*</span>
                            </Label>
                            <Input
                                id="first_name"
                                name="first_name"
                                required
                                placeholder="محمد"
                                :class="inputClass"
                            />
                            <InputError :message="errors.first_name" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="last_name" class="text-sm font-medium text-foreground">
                                اسم العائلة
                                <span class="text-destructive mr-1">*</span>
                            </Label>
                            <Input
                                id="last_name"
                                name="last_name"
                                required
                                placeholder="أحمد"
                                :class="inputClass"
                            />
                            <InputError :message="errors.last_name" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="date_of_birth" class="text-sm font-medium text-foreground">تاريخ الميلاد</Label>
                            <Input
                                id="date_of_birth"
                                name="date_of_birth"
                                type="date"
                                :class="inputClass"
                            />
                            <InputError :message="errors.date_of_birth" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="gender" class="text-sm font-medium text-foreground">الجنس</Label>
                            <select
                                id="gender"
                                name="gender"
                                class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors appearance-none cursor-pointer"
                            >
                                <option value="">غير محدد</option>
                                <option value="male">ذكر</option>
                                <option value="female">أنثى</option>
                                <option value="other">آخر</option>
                            </select>
                            <InputError :message="errors.gender" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="phone" class="text-sm font-medium text-foreground">الهاتف</Label>
                            <Input
                                id="phone"
                                name="phone"
                                placeholder="0599123456"
                                :class="inputClass"
                            />
                            <InputError :message="errors.phone" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="email" class="text-sm font-medium text-foreground">البريد الإلكتروني</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                placeholder="example@domain.com"
                                :class="inputClass"
                            />
                            <InputError :message="errors.email" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="national_id" class="text-sm font-medium text-foreground">رقم الهوية</Label>
                        <Input
                            id="national_id"
                            name="national_id"
                            :class="inputClass"
                        />
                        <InputError :message="errors.national_id" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="notes" class="text-sm font-medium text-foreground">ملاحظات</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            class="w-full rounded-lg border border-input bg-secondary/50 px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors resize-y"
                        ></textarea>
                        <InputError :message="errors.notes" />
                    </div>
                </DialogBody>

                <DialogFooter class="p-6 pt-4 border-t border-border flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-9 px-4 rounded-lg border border-input bg-card text-muted-foreground text-sm font-medium hover:bg-muted hover:text-foreground transition-colors duration-150"
                        :disabled="processing"
                        @click="emit('update:open', false)"
                    >
                        إلغاء
                    </Button>
                    <Button
                        form="patient-add-form"
                        type="submit"
                        variant="default"
                        class="flex-1 h-9 px-4 rounded-lg bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 active:scale-[0.98] transition-all duration-150"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" class="size-4 animate-spin motion-reduce:animate-none motion-reduce:opacity-50" />
                        {{ processing ? 'جارٍ الحفظ...' : 'حفظ المريض' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>