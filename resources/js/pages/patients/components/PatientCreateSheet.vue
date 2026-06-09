<script setup lang="ts">
import { ref } from 'vue';
import { Form } from '@inertiajs/vue3';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const createChronicConditions = ref<string[]>(['']);
const createAllergies = ref<string[]>(['']);
const createCurrentMedications = ref<string[]>(['']);

const addMedicalItem = (collection: string[]): void => {
    collection.push('');
};

const removeMedicalItem = (collection: string[], index: number): void => {
    if (collection.length <= 1) {
        collection.splice(0, collection.length, '');
        return;
    }

    collection.splice(index, 1);
};

const resetCreateMedicalLists = (): void => {
    createChronicConditions.value = [''];
    createAllergies.value = [''];
    createCurrentMedications.value = [''];
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[520px] p-0 overflow-hidden">
            <DialogHeader class="p-6 pb-4 border-b border-[#E5E7EB]">
                <DialogTitle class="text-lg font-medium text-[#1A1A1A]">مريض جديد</DialogTitle>
                <DialogDescription class="text-sm text-[#6B7280] mt-1">تسجيل مريض جديد في النظام</DialogDescription>
            </DialogHeader>

            <Form
                id="patient-create-form"
                v-bind="PatientController.store.form()"
                class="px-6 py-4 space-y-4 max-h-[60vh] overflow-y-auto"
                reset-on-success
                @success="resetCreateMedicalLists"
                v-slot="{ errors, processing }"
            >
                <div class="flex flex-col gap-1.5">
                    <Label for="file_number" class="text-sm font-medium text-[#374151]">
                        رقم الملف
                        <span class="text-xs text-[#9CA3AF]">(يُولّد تلقائياً إذا ترك فارغاً)</span>
                    </Label>
                    <Input
                        id="file_number"
                        name="file_number"
                        type="number"
                        min="1"
                        placeholder="1"
                        class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                    />
                    <InputError :message="errors.file_number" />
                </div>

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
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
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
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
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
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
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

                <div class="flex flex-col gap-1.5">
                    <Label for="phone" class="text-sm font-medium text-foreground">الهاتف</Label>
                    <Input
                        id="phone"
                        name="phone"
                        class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="email" class="text-sm font-medium text-foreground">البريد الإلكتروني</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="national_id" class="text-sm font-medium text-foreground">رقم الهوية</Label>
                    <Input
                        id="national_id"
                        name="national_id"
                        class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                    />
                    <InputError :message="errors.national_id" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="notes" class="text-sm font-medium text-foreground">ملاحظات</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        class="w-full rounded-lg border border-input bg-secondary/50 px-3 py-2 text-sm text-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors resize-y"
                    ></textarea>
                    <InputError :message="errors.notes" />
                </div>

                <div class="flex flex-col gap-2 rounded-xl border border-border bg-muted p-3">
                    <div class="flex items-center justify-between gap-2">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">أمراض مزمنة</Label>
                        <Button type="button" size="sm" variant="outline" class="h-9 px-3 text-xs rounded-lg border border-input bg-card text-muted-foreground hover:bg-muted hover:text-foreground" @click="addMedicalItem(createChronicConditions)">إضافة</Button>
                    </div>
                    <div v-for="(item, index) in createChronicConditions" :key="`create-chronic-${index}`" class="flex items-center gap-2">
                        <Input
                            :name="`chronic_conditions[${index}]`"
                            v-model="createChronicConditions[index]"
                            placeholder="اسم المرض"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <Button type="button" size="sm" variant="ghost" class="h-9 px-3 text-xs text-muted-foreground hover:bg-muted hover:text-foreground" @click="removeMedicalItem(createChronicConditions, index)">حذف</Button>
                    </div>
                    <InputError :message="errors.chronic_conditions" />
                </div>

                <div class="flex flex-col gap-2 rounded-xl border border-border bg-muted p-3">
                    <div class="flex items-center justify-between gap-2">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">حساسية</Label>
                        <Button type="button" size="sm" variant="outline" class="h-9 px-3 text-xs rounded-lg border border-input bg-card text-muted-foreground hover:bg-muted hover:text-foreground" @click="addMedicalItem(createAllergies)">إضافة</Button>
                    </div>
                    <div v-for="(item, index) in createAllergies" :key="`create-allergy-${index}`" class="flex items-center gap-2">
                        <Input
                            :name="`allergies[${index}]`"
                            v-model="createAllergies[index]"
                            placeholder="اسم الحساسية"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <Button type="button" size="sm" variant="ghost" class="h-9 px-3 text-xs text-muted-foreground hover:bg-muted hover:text-foreground" @click="removeMedicalItem(createAllergies, index)">حذف</Button>
                    </div>
                    <InputError :message="errors.allergies" />
                </div>

                <div class="flex flex-col gap-2 rounded-xl border border-border bg-muted p-3">
                    <div class="flex items-center justify-between gap-2">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">أدوية حالية</Label>
                        <Button type="button" size="sm" variant="outline" class="h-9 px-3 text-xs rounded-lg border border-input bg-card text-muted-foreground hover:bg-muted hover:text-foreground" @click="addMedicalItem(createCurrentMedications)">إضافة</Button>
                    </div>
                    <div v-for="(item, index) in createCurrentMedications" :key="`create-medication-${index}`" class="flex items-center gap-2">
                        <Input
                            :name="`current_medications[${index}]`"
                            v-model="createCurrentMedications[index]"
                            placeholder="اسم الدواء"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <Button type="button" size="sm" variant="ghost" class="h-9 px-3 text-xs text-muted-foreground hover:bg-muted hover:text-foreground" @click="removeMedicalItem(createCurrentMedications, index)">حذف</Button>
                    </div>
                    <InputError :message="errors.current_medications" />
                </div>

                <Button
                    :disabled="processing"
                    variant="default"
                    class="w-full h-10 rounded-lg bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 active:scale-[0.98] disabled:opacity-40 disabled:cursor-not-allowed transition-all duration-150"
                >
                    إنشاء مريض
                </Button>
            </Form>

            <DialogFooter class="p-6 pt-4 border-t border-border">
                <Button
                    type="button"
                    variant="outline"
                    class="h-9 px-4 rounded-lg border border-input bg-card text-muted-foreground text-sm font-medium hover:bg-muted hover:text-foreground transition-colors duration-150"
                    @click="emit('update:open', false)"
                >
                    إلغاء
                </Button>
                <Button
                    form="patient-create-form"
                    type="submit"
                    variant="default"
                    class="h-9 px-4 rounded-lg bg-primary text-primary-foreground text-sm font-medium hover:bg-primary/90 active:scale-[0.98] transition-all duration-150"
                >
                    إنشاء مريض
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
