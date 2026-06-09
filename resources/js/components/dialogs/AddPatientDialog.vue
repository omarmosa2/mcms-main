<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription, DialogBody } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
    open: boolean;
    onSuccess: () => void;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const loading = ref(false);
const formData = ref({
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    date_of_birth: '',
    gender: '',
    national_id: '',
    notes: '',
});
const errors = ref<Record<string, string[]>>({});
const firstInputRef = ref<HTMLElement | null>(null);

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            formData.value = {
                first_name: '',
                last_name: '',
                phone: '',
                email: '',
                date_of_birth: '',
                gender: '',
                national_id: '',
                notes: '',
            };
            errors.value = {};
            await nextTick();
            firstInputRef.value?.focus();
        }
    },
);

const handleSubmit = async () => {
    errors.value = {};

    if (!formData.value.first_name) {
        errors.value.first_name = ['الاسم الأول مطلوب'];
    }

    if (!formData.value.last_name) {
        errors.value.last_name = ['اسم العائلة مطلوب'];
    }

    if (Object.keys(errors.value).length > 0) {
return;
}

    loading.value = true;

    try {
        const response = await fetch('/patients', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(formData.value),
        });

        if (!response.ok) {
            if (response.status === 422) {
                const errorData = await response.json();
                errors.value = errorData.errors ?? {};

                return;
            }

            throw new Error('فشل إضافة المريض');
        }

        props.onSuccess();
        emit('update:open', false);
    } catch (error) {
        console.error('AddPatientDialog error:', error);
    } finally {
        loading.value = false;
    }
};

const handleClose = () => {
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent size="lg" :close-on-overlay="!loading">
            <DialogHeader>
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <DialogTitle class="text-base font-medium text-[#1A1A1A]">إضافة مريض جديد</DialogTitle>
                        <DialogDescription class="mt-1 text-[13px] font-normal text-[#6B7280] line-clamp-1">
                            تسجيل بيانات المريض الأساسية
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <DialogBody>
                <div class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <Label for="first_name" class="text-[13px] font-medium text-[#374151]">
                                الاسم الأول
                                <span class="text-[#DC2626]">*</span>
                            </Label>
                            <Input
                                id="first_name"
                                v-model="formData.first_name"
                                placeholder="محمد"
                                class="h-10"
                                :class="{ 'border-[#DC2626]': errors.first_name }"
                                :ref="(el: any) => (firstInputRef = el?.$el || el)"
                            />
                            <InputError v-if="errors.first_name" :message="errors.first_name[0]" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="last_name" class="text-[13px] font-medium text-[#374151]">
                                اسم العائلة
                                <span class="text-[#DC2626]">*</span>
                            </Label>
                            <Input
                                id="last_name"
                                v-model="formData.last_name"
                                placeholder="أحمد"
                                class="h-10"
                                :class="{ 'border-[#DC2626]': errors.last_name }"
                            />
                            <InputError v-if="errors.last_name" :message="errors.last_name[0]" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <Label for="phone" class="text-[13px] font-medium text-[#374151]">الهاتف</Label>
                            <Input
                                id="phone"
                                v-model="formData.phone"
                                placeholder="0599123456"
                                class="h-10"
                            />
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="email" class="text-[13px] font-medium text-[#374151]">البريد الإلكتروني</Label>
                            <Input
                                id="email"
                                v-model="formData.email"
                                type="email"
                                placeholder="example@domain.com"
                                class="h-10"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <Label for="date_of_birth" class="text-[13px] font-medium text-[#374151]">تاريخ الميلاد</Label>
                            <Input
                                id="date_of_birth"
                                v-model="formData.date_of_birth"
                                type="date"
                                class="h-10"
                            />
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="gender" class="text-[13px] font-medium text-foreground">الجنس</Label>
                            <select
                                id="gender"
                                v-model="formData.gender"
                                class="h-10 w-full rounded-xl border border-input bg-secondary/50 px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/15"
                            >
                                <option value="">اختر</option>
                                <option value="male">ذكر</option>
                                <option value="female">أنثى</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="national_id" class="text-[13px] font-medium text-foreground">رقم الهوية</Label>
                        <Input
                            id="national_id"
                            v-model="formData.national_id"
                            class="h-10"
                        />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="notes" class="text-[13px] font-medium text-foreground">ملاحظات</Label>
                        <textarea
                            id="notes"
                            v-model="formData.notes"
                            rows="3"
                            class="w-full rounded-xl border border-input bg-secondary/50 px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/15"
                        />
                    </div>
                </div>
            </DialogBody>

            <DialogFooter>
                <Button
                    type="button"
                    variant="default"
                    :disabled="loading"
                    @click="handleSubmit"
                >
                    <Spinner v-if="loading" class="me-2 h-4 w-4" />
                    {{ loading ? 'جارٍ الحفظ...' : 'حفظ' }}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#374151]"
                    :disabled="loading"
                    @click="handleClose"
                >
                    إلغاء
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
