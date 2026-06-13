<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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
import type { Patient } from './types';

const props = defineProps<{
    patient: Patient | null;
}>();

const emit = defineEmits<{
    close: [];
    saved: [];
}>();

const detailedPatient = ref<Patient | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);
const editFormKey = ref(0);

const editChronicConditions = ref<string[]>(['']);
const editAllergies = ref<string[]>(['']);
const editCurrentMedications = ref<string[]>(['']);

const ensureMedicalList = (items: string[] | null | undefined): string[] => {
    const normalized = (items ?? [])
        .map((item) => item.trim())
        .filter((item) => item.length > 0);

    return normalized.length > 0 ? normalized : [''];
};

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

const hydrateMedicalLists = (patient: Patient): void => {
    editChronicConditions.value = ensureMedicalList(patient.chronic_conditions);
    editAllergies.value = ensureMedicalList(patient.allergies);
    editCurrentMedications.value = ensureMedicalList(patient.current_medications);
};

const resetMedicalLists = (): void => {
    editChronicConditions.value = [''];
    editAllergies.value = [''];
    editCurrentMedications.value = [''];
};

const fetchPatientDetails = async (patientId: number): Promise<Patient> => {
    const response = await fetch(PatientController.show.url(patientId), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Failed to load patient details');
    }

    const payload = (await response.json()) as { data?: Patient };

    if (payload.data === undefined) {
        throw new Error('Invalid patient payload');
    }

    return payload.data;
};

watch(
    () => props.patient,
    async (newPatient) => {
        if (newPatient === null) {
            detailedPatient.value = null;
            isLoading.value = false;
            error.value = null;
            editFormKey.value += 1;
            resetMedicalLists();

            return;
        }

        detailedPatient.value = newPatient;
        editFormKey.value += 1;
        hydrateMedicalLists(newPatient);
        isLoading.value = true;
        error.value = null;

        try {
            const fetched = await fetchPatientDetails(newPatient.id);

            if (props.patient?.id === newPatient.id) {
                detailedPatient.value = fetched;
                editFormKey.value += 1;
                hydrateMedicalLists(fetched);
            }
        } catch {
            if (props.patient?.id === newPatient.id) {
                error.value = 'تعذر تحميل الملف الكامل للمريض.';
            }
        } finally {
            if (props.patient?.id === newPatient.id) {
                isLoading.value = false;
            }
        }
    },
    { immediate: true },
);

const handleClose = () => {
    emit('close');
};

const handleSuccess = () => {
    emit('saved');
    emit('close');
};
</script>

<template>
    <Dialog :open="patient !== null" @update:open="(open) => !open && handleClose()">
        <DialogContent class="max-w-[520px] bg-card rounded-xl">
            <DialogHeader class="p-6 pb-4 border-b border-border">
                <DialogTitle class="text-base font-medium text-foreground">تعديل بيانات المريض</DialogTitle>
                <DialogDescription class="text-sm text-muted-foreground mt-0.5">تحديث بيانات المريض</DialogDescription>
            </DialogHeader>

            <DialogBody class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                <p v-if="isLoading" class="rounded-lg border border-border/70 bg-muted px-3 py-2 text-sm text-muted-foreground">جاري تحميل تفاصيل المريض...</p>
                <p v-if="error !== null" class="rounded-lg border border-destructive/35 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</p>

                <Form
                    v-if="detailedPatient"
                    :key="editFormKey"
                    v-bind="PatientController.update.form(detailedPatient.id)"
                    class="space-y-4"
                    :options="{ preserveScroll: true }"
                    @success="handleSuccess"
                    v-slot="{ errors, processing }"
                >
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <Label for="edit_patient_first_name" class="text-sm font-medium text-foreground">الاسم الأول</Label>
                        <Input
                            id="edit_patient_first_name"
                            name="first_name"
                            :default-value="detailedPatient.first_name"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <InputError :message="errors.first_name" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="edit_patient_last_name" class="text-sm font-medium text-foreground">اسم العائلة</Label>
                        <Input
                            id="edit_patient_last_name"
                            name="last_name"
                            :default-value="detailedPatient.last_name"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <InputError :message="errors.last_name" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <Label for="edit_patient_dob" class="text-sm font-medium text-foreground">تاريخ الميلاد</Label>
                        <Input
                            id="edit_patient_dob"
                            name="date_of_birth"
                            type="date"
                            :default-value="detailedPatient.date_of_birth ?? ''"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <InputError :message="errors.date_of_birth" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="edit_patient_gender" class="text-sm font-medium text-foreground">الجنس</Label>
                        <select
                            id="edit_patient_gender"
                            name="gender"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors appearance-none cursor-pointer"
                            :value="detailedPatient.gender ?? ''"
                        >
                            <option value="">غير محدد</option>
                            <option value="male">ذكر</option>
                            <option value="female">أنثى</option>
                            <option value="other">آخر</option>
                        </select>
                        <InputError :message="errors.gender" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="edit_patient_phone" class="text-sm font-medium text-foreground">الهاتف</Label>
                        <Input
                            id="edit_patient_phone"
                            name="phone"
                            :default-value="detailedPatient.phone ?? ''"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <InputError :message="errors.phone" />
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="edit_patient_email" class="text-sm font-medium text-foreground">البريد الإلكتروني</Label>
                    <Input
                        id="edit_patient_email"
                        name="email"
                        type="email"
                        :default-value="detailedPatient.email ?? ''"
                        class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <Label for="edit_patient_emergency_name" class="text-sm font-medium text-foreground">اسم جهة اتصال الطوارئ</Label>
                        <Input
                            id="edit_patient_emergency_name"
                            name="emergency_contact_name"
                            :default-value="detailedPatient.emergency_contact_name ?? ''"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <InputError :message="errors.emergency_contact_name" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="edit_patient_emergency_phone" class="text-sm font-medium text-foreground">هاتف جهة اتصال الطوارئ</Label>
                        <Input
                            id="edit_patient_emergency_phone"
                            name="emergency_contact_phone"
                            :default-value="detailedPatient.emergency_contact_phone ?? ''"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <InputError :message="errors.emergency_contact_phone" />
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="edit_patient_notes" class="text-sm font-medium text-foreground">ملاحظات</Label>
                    <textarea
                        id="edit_patient_notes"
                        name="notes"
                        rows="3"
                        class="w-full h-auto rounded-lg border border-input bg-secondary/50 px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors resize-y"
                        :value="detailedPatient.notes ?? ''"
                    ></textarea>
                    <InputError :message="errors.notes" />
                </div>

                <div class="flex flex-col gap-2 rounded-xl border border-border bg-muted p-3">
                    <div class="flex items-center justify-between gap-2">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">أمراض مزمنة</Label>
                        <Button type="button" size="sm" variant="outline" class="h-9 px-3 text-xs rounded-lg border border-input bg-card text-muted-foreground hover:bg-muted hover:text-foreground" @click="addMedicalItem(editChronicConditions)">إضافة</Button>
                    </div>
                    <div v-for="(item, index) in editChronicConditions" :key="`edit-chronic-${index}`" class="flex items-center gap-2">
                        <Input
                            :name="`chronic_conditions[${index}]`"
                            v-model="editChronicConditions[index]"
                            placeholder="اسم المرض"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <Button type="button" size="sm" variant="ghost" class="h-9 px-3 text-xs text-muted-foreground hover:bg-muted hover:text-foreground" @click="removeMedicalItem(editChronicConditions, index)">حذف</Button>
                    </div>
                    <InputError :message="errors.chronic_conditions" />
                </div>

                <div class="flex flex-col gap-2 rounded-xl border border-border bg-muted p-3">
                    <div class="flex items-center justify-between gap-2">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">حساسية</Label>
                        <Button type="button" size="sm" variant="outline" class="h-9 px-3 text-xs rounded-lg border border-input bg-card text-muted-foreground hover:bg-muted hover:text-foreground" @click="addMedicalItem(editAllergies)">إضافة</Button>
                    </div>
                    <div v-for="(item, index) in editAllergies" :key="`edit-allergy-${index}`" class="flex items-center gap-2">
                        <Input
                            :name="`allergies[${index}]`"
                            v-model="editAllergies[index]"
                            placeholder="اسم الحساسية"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <Button type="button" size="sm" variant="ghost" class="h-9 px-3 text-xs text-muted-foreground hover:bg-muted hover:text-foreground" @click="removeMedicalItem(editAllergies, index)">حذف</Button>
                    </div>
                    <InputError :message="errors.allergies" />
                </div>

                <div class="flex flex-col gap-2 rounded-xl border border-border bg-muted p-3">
                    <div class="flex items-center justify-between gap-2">
                        <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">أدوية حالية</Label>
                        <Button type="button" size="sm" variant="outline" class="h-9 px-3 text-xs rounded-lg border border-input bg-card text-muted-foreground hover:bg-muted hover:text-foreground" @click="addMedicalItem(editCurrentMedications)">إضافة</Button>
                    </div>
                    <div v-for="(item, index) in editCurrentMedications" :key="`edit-medication-${index}`" class="flex items-center gap-2">
                        <Input
                            :name="`current_medications[${index}]`"
                            v-model="editCurrentMedications[index]"
                            placeholder="اسم الدواء"
                            class="w-full h-10 rounded-lg border border-input bg-secondary/50 px-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-colors"
                        />
                        <Button type="button" size="sm" variant="ghost" class="h-9 px-3 text-xs text-muted-foreground hover:bg-muted hover:text-foreground" @click="removeMedicalItem(editCurrentMedications, index)">حذف</Button>
                    </div>
                    <InputError :message="errors.current_medications" />
                </div>

                    <DialogFooter class="flex items-center justify-between p-6 pt-4 gap-2">
                        <Button type="button" variant="ghost" class="h-9 px-4 rounded-lg text-muted-foreground text-sm font-medium hover:bg-muted hover:text-foreground transition-colors duration-150 active:scale-[0.98]" :disabled="processing" @click="handleClose">إلغاء</Button>
                        <Button
                            type="submit"
                            variant="default"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" class="size-4 animate-spin motion-reduce:animate-none motion-reduce:opacity-50" />
                            {{ processing ? 'جارٍ الحفظ...' : 'حفظ التغييرات' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogBody>
        </DialogContent>
    </Dialog>
</template>
