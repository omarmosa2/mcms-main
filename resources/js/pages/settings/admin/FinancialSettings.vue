<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { updateFinancial, financial as financialUrl } from '@/actions/App/Http/Controllers/Admin/AdminSettingsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const props = defineProps<{
    settings: {
        payment_methods: string[];
        salary_generation_day: number;
        salary_due_date: number;
        doctor_earning_mode: string;
        currency_display_format: string;
        rounding_rule: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'الإعدادات المالية',
                href: financialUrl(),
            },
        ],
    },
});

const isDirty = ref(false);

const paymentMethodOptions = [
    { value: 'cash', label: 'نقدي' },
    { value: 'bank_transfer', label: 'تحويل بنكي' },
    { value: 'card', label: 'بطاقة دفع' },
];

const form = useForm({
    payment_methods: props.settings.payment_methods ?? ['cash', 'bank_transfer', 'card'],
    salary_generation_day: props.settings.salary_generation_day ?? 1,
    salary_due_date: props.settings.salary_due_date ?? 5,
    doctor_earning_mode: props.settings.doctor_earning_mode ?? 'appointment_only',
    currency_display_format: props.settings.currency_display_format ?? 'symbol',
    rounding_rule: props.settings.rounding_rule ?? 'none',
});

watch(form, () => {
    isDirty.value = form.isDirty;
}, { deep: true });

function togglePaymentMethod(method: string) {
    const index = form.payment_methods.indexOf(method);

    if (index > -1) {
        if (form.payment_methods.length > 1) {
            form.payment_methods.splice(index, 1);
        }
    } else {
        form.payment_methods.push(method);
    }
}

function submit() {
    form.put(updateFinancial.url(), {
        onSuccess: () => {
            isDirty.value = false;
        },
    });
}
</script>

<template>
    <Head title="الإعدادات المالية" />

    <div class="space-y-6">
        <div class="glass-panel-soft p-5">
            <Heading
                tone="typographic"
                variant="small"
                title="الإعدادات المالية"
                description="التحكم في الحسابات والمدفوعات والرواتب."
            />
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">طرق الدفع</h3>

                <div class="flex flex-wrap gap-3">
                    <div
                        v-for="method in paymentMethodOptions"
                        :key="method.value"
                        class="flex items-center gap-2 rounded-xl border border-border/50 bg-muted/20 p-3"
                    >
                        <Checkbox
                            :id="method.value"
                            :checked="form.payment_methods.includes(method.value)"
                            @update:checked="togglePaymentMethod(method.value)"
                        />
                        <Label :for="method.value" class="cursor-pointer text-sm">{{ method.label }}</Label>
                    </div>
                </div>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">إعدادات الرواتب</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="salary_generation_day">يوم إنشاء الرواتب الشهرية</Label>
                        <Input id="salary_generation_day" v-model="form.salary_generation_day" type="number" min="1" max="28" />
                    </div>
                    <div class="space-y-2">
                        <Label for="salary_due_date">موعد استحقاق الراتب</Label>
                        <Input id="salary_due_date" v-model="form.salary_due_date" type="number" min="1" max="28" />
                    </div>
                </div>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">إعدادات أرباح الأطباء</h3>

                <div class="space-y-2">
                    <Label>طريقة حساب أرباح الطبيب</Label>
                    <Select v-model="form.doctor_earning_mode">
                        <SelectTrigger>
                            <SelectValue placeholder="اختر الطريقة" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="appointment_only">من قيمة الموعد فقط</SelectItem>
                            <SelectItem value="appointment_and_procedures">من قيمة الموعد والإجراءات</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">الدقة المالية</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>تنسيق عرض العملة</Label>
                        <Select v-model="form.currency_display_format">
                            <SelectTrigger>
                                <SelectValue placeholder="اختر التنسيق" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="symbol">رمز العملة</SelectItem>
                                <SelectItem value="code">كود العملة</SelectItem>
                                <SelectItem value="name">اسم العملة</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label>قاعدة التقريب</Label>
                        <Select v-model="form.rounding_rule">
                            <SelectTrigger>
                                <SelectValue placeholder="اختر القاعدة" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">بدون تقريب</SelectItem>
                                <SelectItem value="round_up">تقريب لأعلى</SelectItem>
                                <SelectItem value="round_down">تقريب لأسفل</SelectItem>
                                <SelectItem value="round_nearest">تقريب لأقرب</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <span v-if="isDirty" class="text-sm text-amber-600">لديك تغييرات غير محفوظة</span>
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'جاري الحفظ...' : 'حفظ الإعدادات' }}
                </Button>
            </div>
        </form>
    </div>
</template>
