<script setup lang="ts">
import { ref } from 'vue';
import PatientController from '@/actions/App/Http/Controllers/Patients/PatientController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/composables/useToast';
import type { Patient } from './types';

const emit = defineEmits<{
    created: [patient: { id: number; name: string; dateOfBirth: string; gender: string; phone: string }];
}>();

const toast = useToast();

const quickAddFirstName = ref('');
const quickAddLastName = ref('');
const quickAddPhone = ref('');
const quickAddGender = ref('');
const quickAddDateOfBirth = ref('');
const quickAddProcessing = ref(false);
const quickAddErrors = ref<Record<string, string>>({});
const lastCreatedPatientId = ref<number | null>(null);
const lastCreatedPatientName = ref<string | null>(null);

const resetQuickAdd = () => {
    quickAddFirstName.value = '';
    quickAddLastName.value = '';
    quickAddPhone.value = '';
    quickAddGender.value = '';
    quickAddDateOfBirth.value = '';
    quickAddErrors.value = {};
};

const handleQuickAdd = async (saveAndAddNext = true) => {
    quickAddProcessing.value = true;
    quickAddErrors.value = {};

    try {
        const formData = new FormData();
        formData.append('first_name', quickAddFirstName.value);
        formData.append('last_name', quickAddLastName.value);
        formData.append('phone', quickAddPhone.value);
        formData.append('gender', quickAddGender.value);
        formData.append('date_of_birth', quickAddDateOfBirth.value);

        const response = await fetch(PatientController.store.url(), {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            if (response.status === 422) {
                const errorData = await response.json();
                quickAddErrors.value = errorData.errors ?? {};
                return;
            }

            toast.error('فشل إضافة المريض');
            return;
        }

        const result = await response.json();

        lastCreatedPatientId.value = result.data?.id ?? null;
        lastCreatedPatientName.value = `${quickAddFirstName.value} ${quickAddLastName.value}`;
        toast.success(`تم إضافة ${lastCreatedPatientName.value} بنجاح`);

        emit('created', {
            id: result.data?.id ?? 0,
            name: lastCreatedPatientName.value,
            dateOfBirth: quickAddDateOfBirth.value,
            gender: quickAddGender.value,
            phone: quickAddPhone.value,
        });

        if (saveAndAddNext) {
            resetQuickAdd();
        }
    } catch {
        toast.error('حدث خطأ أثناء إضافة المريض');
    } finally {
        quickAddProcessing.value = false;
    }
};

const handleQuickAddKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleQuickAdd(true);
    }
};

const handleCompleteFile = () => {
    if (lastCreatedPatientId.value) {
        emit('created', {
            id: lastCreatedPatientId.value,
            name: lastCreatedPatientName.value ?? '',
            dateOfBirth: quickAddDateOfBirth.value,
            gender: quickAddGender.value,
            phone: quickAddPhone.value,
        });
    }
};
</script>

<template>
    <section class="mb-6 rounded-[1.45rem] border border-dashed border-primary/20 bg-card/95 p-5 shadow-card-float">
        <div v-if="lastCreatedPatientId" class="mb-4 flex items-center gap-2 rounded-2xl border border-primary/20 bg-accent px-4 py-3">
            <span class="text-xs font-bold text-accent-foreground">تم إضافة: {{ lastCreatedPatientName }}</span>
            <Button type="button" variant="ghost" size="sm" class="h-8 rounded-xl px-3 text-xs text-primary hover:bg-accent" @click="handleCompleteFile">
                إكمال الملف
            </Button>
        </div>

        <div class="mb-4 flex items-start gap-2">
            <h3 class="text-sm font-bold text-accent-foreground">إضافة سريعة</h3>
            <p class="text-xs text-muted-foreground">الحقول الأساسية فقط</p>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div class="flex flex-col gap-1 flex-1">
                <Label for="quick_first_name" class="text-xs font-medium text-foreground">الاسم الأول *</Label>
                <Input
                    id="quick_first_name"
                    v-model="quickAddFirstName"
                    placeholder="محمد"
                    class="h-10 rounded-xl border-input bg-secondary/50 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10"
                    @keydown="handleQuickAddKeyDown"
                />
                <p v-if="quickAddErrors.first_name" class="text-xs text-destructive mt-0.5">{{ quickAddErrors.first_name[0] }}</p>
            </div>
            <div class="flex flex-col gap-1 flex-1">
                <Label for="quick_last_name" class="text-xs font-medium text-foreground">اسم العائلة *</Label>
                <Input
                    id="quick_last_name"
                    v-model="quickAddLastName"
                    placeholder="أحمد"
                    class="h-10 rounded-xl border-input bg-secondary/50 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10"
                    @keydown="handleQuickAddKeyDown"
                />
                <p v-if="quickAddErrors.last_name" class="text-xs text-destructive mt-0.5">{{ quickAddErrors.last_name[0] }}</p>
            </div>
            <div class="flex flex-col gap-1 flex-1">
                <Label for="quick_phone" class="text-xs font-medium text-foreground">الهاتف</Label>
                <Input
                    id="quick_phone"
                    v-model="quickAddPhone"
                    placeholder="0599123456"
                    class="h-10 rounded-xl border-input bg-secondary/50 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10"
                    @keydown="handleQuickAddKeyDown"
                />
                <p v-if="quickAddErrors.phone" class="text-xs text-destructive mt-0.5">{{ quickAddErrors.phone[0] }}</p>
            </div>
            <div class="flex flex-col gap-1 flex-1">
                <Label for="quick_gender" class="text-xs font-medium text-foreground">الجنس</Label>
                <select
                    id="quick_gender"
                    v-model="quickAddGender"
                    class="h-10 cursor-pointer appearance-none rounded-xl border border-input bg-secondary/50 px-3 text-sm text-muted-foreground transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10"
                    @keydown="handleQuickAddKeyDown"
                >
                    <option value="">اختر</option>
                    <option value="male">ذكر</option>
                    <option value="female">أنثى</option>
                </select>
            </div>
            <div class="flex flex-col gap-1 flex-1">
                <Label for="quick_dob" class="text-xs font-medium text-foreground">تاريخ الميلاد</Label>
                <Input
                    id="quick_dob"
                    v-model="quickAddDateOfBirth"
                    type="date"
                    class="h-10 rounded-xl border-input bg-secondary/50 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10"
                    @keydown="handleQuickAddKeyDown"
                />
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
            <span class="text-xs text-muted-foreground mr-auto">اضغط Enter للحفظ وإضافة آخر</span>
            <Button type="button" variant="ghost" size="sm" class="h-9 rounded-xl px-3 text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground" @click="resetQuickAdd">
                مسح
            </Button>
            <Button
                type="button"
                variant="secondary"
                size="sm"
                class="h-9 rounded-xl border border-input bg-card px-4 text-xs text-muted-foreground transition-all duration-150 hover:bg-secondary hover:text-foreground"
                :disabled="quickAddProcessing || !quickAddFirstName || !quickAddLastName"
                @click="handleQuickAdd(false)"
            >
                حفظ فقط
            </Button>
            <Button
                type="button"
                size="sm"
                class="h-9 rounded-xl bg-primary px-4 text-xs text-primary-foreground transition-all duration-150 hover:bg-primary/90 active:scale-[0.98] disabled:opacity-40"
                :disabled="quickAddProcessing || !quickAddFirstName || !quickAddLastName"
                @click="handleQuickAdd(true)"
            >
                حفظ وإضافة آخر
            </Button>
        </div>
    </section>
</template>
