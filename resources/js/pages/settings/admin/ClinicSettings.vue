<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { updateClinic, clinic as clinicUrl } from '@/actions/App/Http/Controllers/Admin/AdminSettingsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    settings: {
        name: string | null;
        logo_path: string | null;
        director_name: string | null;
        phone: string | null;
        email: string | null;
        address: string | null;
        invoice_clinic_name: string | null;
        invoice_footer: string | null;
        invoice_default_notes: string | null;
        currency_syp: number;
        currency_try: number;
        currency_usd: number;
        currency_iqd: number;
        thousands_separator: string;
        decimal_places: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'إعدادات المجمع الطبي',
                href: clinicUrl(),
            },
        ],
    },
});

const isDirty = ref(false);

const form = useForm({
    name: props.settings.name ?? '',
    director_name: props.settings.director_name ?? '',
    phone: props.settings.phone ?? '',
    email: props.settings.email ?? '',
    address: props.settings.address ?? '',
    invoice_clinic_name: props.settings.invoice_clinic_name ?? '',
    invoice_footer: props.settings.invoice_footer ?? '',
    invoice_default_notes: props.settings.invoice_default_notes ?? '',
    currency_syp: props.settings.currency_syp ?? 1,
    currency_try: props.settings.currency_try ?? 1,
    currency_usd: props.settings.currency_usd ?? 1,
    currency_iqd: props.settings.currency_iqd ?? 1,
    thousands_separator: props.settings.thousands_separator ?? ',',
    decimal_places: props.settings.decimal_places ?? 2,
});

watch(form, () => {
    isDirty.value = form.isDirty;
}, { deep: true });

function submit() {
    form.put(updateClinic.url(), {
        onSuccess: () => {
            isDirty.value = false;
        },
    });
}
</script>

<template>
    <Head title="إعدادات المجمع الطبي" />

    <div class="space-y-6">
        <div class="glass-panel-soft p-5">
            <Heading
                tone="typographic"
                variant="small"
                title="المجمع الطبي"
                description="إدارة المعلومات الأساسية للمجمع الطبي."
            />
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">المعلومات الأساسية</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="name">اسم المجمع الطبي</Label>
                        <Input id="name" v-model="form.name" placeholder="أدخل اسم المجمع" />
                    </div>
                    <div class="space-y-2">
                        <Label for="director_name">اسم المدير</Label>
                        <Input id="director_name" v-model="form.director_name" placeholder="أدخل اسم المدير" />
                    </div>
                    <div class="space-y-2">
                        <Label for="phone">رقم الهاتف</Label>
                        <Input id="phone" v-model="form.phone" placeholder="أدخل رقم الهاتف" />
                    </div>
                    <div class="space-y-2">
                        <Label for="email">البريد الإلكتروني</Label>
                        <Input id="email" v-model="form.email" type="email" placeholder="أدخل البريد الإلكتروني" />
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="address">العنوان الكامل</Label>
                        <Input id="address" v-model="form.address" placeholder="أدخل العنوان الكامل" />
                    </div>
                </div>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">إعدادات الفواتير</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="invoice_clinic_name">اسم العيادة على الفاتورة</Label>
                        <Input id="invoice_clinic_name" v-model="form.invoice_clinic_name" placeholder="الاسم المعروض على الفواتير" />
                    </div>
                    <div class="space-y-2">
                        <Label for="invoice_footer">نص تذييل الفاتورة</Label>
                        <Input id="invoice_footer" v-model="form.invoice_footer" placeholder="نص التذييل" />
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="invoice_default_notes">ملاحظات الفاتورة الافتراضية</Label>
                        <Input id="invoice_default_notes" v-model="form.invoice_default_notes" placeholder="ملاحظات افتراضية تظهر على كل فاتورة" />
                    </div>
                </div>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">إعدادات العملات</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="currency_syp">الليرة السورية (SYP)</Label>
                        <Input id="currency_syp" v-model="form.currency_syp" type="number" step="0.01" />
                    </div>
                    <div class="space-y-2">
                        <Label for="currency_try">الليرة التركية (TRY)</Label>
                        <Input id="currency_try" v-model="form.currency_try" type="number" step="0.01" />
                    </div>
                    <div class="space-y-2">
                        <Label for="currency_usd">الدولار الأمريكي (USD)</Label>
                        <Input id="currency_usd" v-model="form.currency_usd" type="number" step="0.01" />
                    </div>
                    <div class="space-y-2">
                        <Label for="currency_iqd">الدينار العراقي (IQD)</Label>
                        <Input id="currency_iqd" v-model="form.currency_iqd" type="number" step="0.01" />
                    </div>
                </div>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">تنسيق الأرقام</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="thousands_separator">فاصل الآلاف</Label>
                        <Input id="thousands_separator" v-model="form.thousands_separator" maxlength="1" />
                    </div>
                    <div class="space-y-2">
                        <Label for="decimal_places">عدد الخانات العشرية</Label>
                        <Input id="decimal_places" v-model="form.decimal_places" type="number" min="0" max="6" />
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
