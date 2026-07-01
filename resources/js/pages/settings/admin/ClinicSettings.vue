<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { updateClinic, clinic as clinicUrl } from '@/actions/App/Http/Controllers/Admin/AdminSettingsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

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
        currency: string;
        thousands_separator: string;
        decimal_places: number;
    };
    currencyOptions: Array<{
        value: string;
        label: string;
    }>;
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
    currency: props.settings.currency ?? props.currencyOptions[0]?.value ?? '',
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
                        <Label for="phone">رقم الهاتف</Label>
                        <Input id="phone" v-model="form.phone" placeholder="أدخل رقم الهاتف" />
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label for="address">العنوان الكامل</Label>
                        <Input id="address" v-model="form.address" placeholder="أدخل العنوان الكامل" />
                    </div>
                </div>
            </div>
            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">إعدادات العملات</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Select v-model="form.currency">
                            <SelectTrigger>
                                <SelectValue placeholder="???? ??????" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in currencyOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
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
