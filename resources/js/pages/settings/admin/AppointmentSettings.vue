<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { updateAppointments, appointments as appointmentsUrl } from '@/actions/App/Http/Controllers/Admin/AdminSettingsController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

const props = defineProps<{
    settings: {
        default_duration: number;
        allow_outside_hours: boolean;
        allow_overlapping: boolean;
        max_per_doctor_per_day: number;
        types: Array<{ name: string; is_default: boolean }>;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'إعدادات المواعيد',
                href: appointmentsUrl(),
            },
        ],
    },
});

const isDirty = ref(false);
const newTypeName = ref('');

const form = useForm({
    default_duration: props.settings.default_duration ?? 30,
    allow_outside_hours: props.settings.allow_outside_hours ?? false,
    allow_overlapping: props.settings.allow_overlapping ?? false,
    max_per_doctor_per_day: props.settings.max_per_doctor_per_day ?? 30,
    types: props.settings.types ?? [
        { name: 'فحص أولي', is_default: true },
        { name: 'مراجعة', is_default: true },
    ],
});

watch(form, () => {
    isDirty.value = form.isDirty;
}, { deep: true });

function addType() {
    if (newTypeName.value.trim()) {
        form.types.push({ name: newTypeName.value.trim(), is_default: false });
        newTypeName.value = '';
    }
}

function removeType(index: number) {
    if (!form.types[index].is_default) {
        form.types.splice(index, 1);
    }
}

function submit() {
    form.put(updateAppointments.url(), {
        onSuccess: () => {
            isDirty.value = false;
        },
    });
}
</script>

<template>
    <Head title="إعدادات المواعيد" />

    <div class="space-y-6">
        <div class="glass-panel-soft p-5">
            <Heading
                tone="typographic"
                variant="small"
                title="إعدادات المواعيد"
                description="إدارة إعدادات المواعيد وسلوك النظام."
            />
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">الإعدادات العامة</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="default_duration">المدة الافتراضية للموعد (دقيقة)</Label>
                        <Input id="default_duration" v-model="form.default_duration" type="number" min="5" max="240" />
                    </div>
                    <div class="space-y-2">
                        <Label for="max_per_doctor_per_day">الحد الأقصى للمواعيد لكل طبيب يومياً</Label>
                        <Input id="max_per_doctor_per_day" v-model="form.max_per_doctor_per_day" type="number" min="1" max="200" />
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-border/50 bg-muted/30 p-4">
                    <div>
                        <Label for="allow_outside_hours" class="text-sm font-medium">السماح بالمواعيد خارج ساعات العمل</Label>
                        <p class="text-xs text-muted-foreground">السماح بحجز مواعيد خارج أوقات الدوام الرسمي</p>
                    </div>
                    <Switch id="allow_outside_hours" v-model:checked="form.allow_outside_hours" />
                </div>

                <div class="flex items-center justify-between rounded-xl border border-border/50 bg-muted/30 p-4">
                    <div>
                        <Label for="allow_overlapping" class="text-sm font-medium">السماح بتداخل المواعيد</Label>
                        <p class="text-xs text-muted-foreground">السماح بحجز مواعيد متداخلة لنفس الطبيب</p>
                    </div>
                    <Switch id="allow_overlapping" v-model:checked="form.allow_overlapping" />
                </div>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">أنواع المواعيد</h3>

                <div class="space-y-3">
                    <div
                        v-for="(type, index) in form.types"
                        :key="index"
                        class="flex items-center justify-between rounded-xl border border-border/50 bg-muted/20 p-3"
                    >
                        <span class="text-sm">{{ type.name }}</span>
                        <div class="flex items-center gap-2">
                            <span v-if="type.is_default" class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">افتراضي</span>
                            <Button
                                v-if="!type.is_default"
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="removeType(index)"
                                class="text-destructive hover:text-destructive"
                            >
                                حذف
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Input
                        v-model="newTypeName"
                        placeholder="نوع موعد جديد..."
                        @keyup.enter="addType"
                    />
                    <Button type="button" variant="outline" @click="addType" :disabled="!newTypeName.trim()">
                        إضافة
                    </Button>
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
