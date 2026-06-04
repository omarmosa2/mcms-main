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
    <section class="rounded-xl border border-dashed border-[#0EA5E9] bg-[#F7FAFD] p-4 mb-4">
        <div v-if="lastCreatedPatientId" class="mb-3 flex items-center gap-2 rounded-lg border border-[#0EA5E9]/20 bg-[#EAF7FE] px-3 py-2">
            <span class="text-xs font-medium text-[#075985]">تم إضافة: {{ lastCreatedPatientName }}</span>
            <Button type="button" variant="ghost" size="sm" class="h-6 px-2 text-xs text-[#0EA5E9] hover:bg-[#D6F0FC]" @click="handleCompleteFile">
                إكمال الملف
            </Button>
        </div>

        <div class="flex items-start gap-2 mb-3">
            <h3 class="text-sm font-medium text-[#075985]"> إضافة سريعة</h3>
            <p class="text-xs text-[#9CA3AF]">الحقول الأساسية فقط</p>
        </div>

        <div class="flex items-end gap-3 mb-3">
            <div class="flex flex-col gap-1 flex-1">
                <Label for="quick_first_name" class="text-xs font-medium text-[#374151]">الاسم الأول *</Label>
                <Input
                    id="quick_first_name"
                    v-model="quickAddFirstName"
                    placeholder="محمد"
                    class="h-9 text-sm bg-white border-[#E5E7EB] rounded-lg focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10"
                    @keydown="handleQuickAddKeyDown"
                />
                <p v-if="quickAddErrors.first_name" class="text-xs text-[#DC2626] mt-0.5">{{ quickAddErrors.first_name[0] }}</p>
            </div>
            <div class="flex flex-col gap-1 flex-1">
                <Label for="quick_last_name" class="text-xs font-medium text-[#374151]">اسم العائلة *</Label>
                <Input
                    id="quick_last_name"
                    v-model="quickAddLastName"
                    placeholder="أحمد"
                    class="h-9 text-sm bg-white border-[#E5E7EB] rounded-lg focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10"
                    @keydown="handleQuickAddKeyDown"
                />
                <p v-if="quickAddErrors.last_name" class="text-xs text-[#DC2626] mt-0.5">{{ quickAddErrors.last_name[0] }}</p>
            </div>
            <div class="flex flex-col gap-1 flex-1">
                <Label for="quick_phone" class="text-xs font-medium text-[#374151]">الهاتف</Label>
                <Input
                    id="quick_phone"
                    v-model="quickAddPhone"
                    placeholder="0599123456"
                    class="h-9 text-sm bg-white border-[#E5E7EB] rounded-lg focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10"
                    @keydown="handleQuickAddKeyDown"
                />
                <p v-if="quickAddErrors.phone" class="text-xs text-[#DC2626] mt-0.5">{{ quickAddErrors.phone[0] }}</p>
            </div>
            <div class="flex flex-col gap-1 flex-1">
                <Label for="quick_gender" class="text-xs font-medium text-[#374151]">الجنس</Label>
                <select
                    id="quick_gender"
                    v-model="quickAddGender"
                    class="h-9 rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm text-[#6B7280] focus:outline-none focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10 transition-colors appearance-none cursor-pointer"
                    @keydown="handleQuickAddKeyDown"
                >
                    <option value="">اختر</option>
                    <option value="male">ذكر</option>
                    <option value="female">أنثى</option>
                </select>
            </div>
            <div class="flex flex-col gap-1 flex-1">
                <Label for="quick_dob" class="text-xs font-medium text-[#374151]">تاريخ الميلاد</Label>
                <Input
                    id="quick_dob"
                    v-model="quickAddDateOfBirth"
                    type="date"
                    class="h-9 text-sm bg-white border-[#E5E7EB] rounded-lg focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10"
                    @keydown="handleQuickAddKeyDown"
                />
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 mt-3">
            <span class="text-xs text-[#9CA3AF] mr-auto">اضغط Enter للحفظ وإضافة آخر</span>
            <Button type="button" variant="ghost" size="sm" class="h-9 px-3 text-xs text-[#6B7280] hover:bg-[#EAF7FE] hover:text-[#075985] rounded-lg" @click="resetQuickAdd">
                مسح
            </Button>
            <Button
                type="button"
                variant="secondary"
                size="sm"
                class="h-9 px-4 text-xs rounded-lg border border-[#E5E7EB] bg-white text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#1A1A1A] transition-all duration-150"
                :disabled="quickAddProcessing || !quickAddFirstName || !quickAddLastName"
                @click="handleQuickAdd(false)"
            >
                حفظ فقط
            </Button>
            <Button
                type="button"
                size="sm"
                class="h-9 px-4 text-xs rounded-lg bg-[#0EA5E9] text-white hover:bg-[#0284C7] active:scale-[0.98] disabled:opacity-40 transition-all duration-150"
                :disabled="quickAddProcessing || !quickAddFirstName || !quickAddLastName"
                @click="handleQuickAdd(true)"
            >
                حفظ وإضافة آخر
            </Button>
        </div>
    </section>
</template>
