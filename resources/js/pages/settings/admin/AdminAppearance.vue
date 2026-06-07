<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { updateAppearance, appearance as appearanceUrl } from '@/actions/App/Http/Controllers/Admin/AdminSettingsController';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const props = defineProps<{
    settings: {
        theme: string;
        primary_color: string;
        language: string;
        font_size: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'مظهر النظام',
                href: appearanceUrl(),
            },
        ],
    },
});

const isDirty = ref(false);

const form = useForm({
    theme: props.settings.theme ?? 'system',
    primary_color: props.settings.primary_color ?? '#0EA5E9',
    language: props.settings.language ?? 'ar',
    font_size: props.settings.font_size ?? 'medium',
});

watch(form, () => {
    isDirty.value = form.isDirty;
}, { deep: true });

const fontSizeOptions = [
    { value: 'small', label: 'صغير' },
    { value: 'medium', label: 'متوسط' },
    { value: 'large', label: 'كبير' },
];

const languageOptions = [
    { value: 'ar', label: 'العربية' },
    { value: 'en', label: 'English (قريباً)' },
];

function submit() {
    form.put(updateAppearance.url(), {
        onSuccess: () => {
            isDirty.value = false;
        },
    });
}
</script>

<template>
    <Head title="مظهر النظام" />

    <div class="space-y-6">
        <div class="glass-panel-soft p-5">
            <Heading
                tone="typographic"
                variant="small"
                title="إعدادات المظهر"
                description="تخصيص مظهر النظام واللغة وحجم الخط."
            />
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">السمة</h3>
                <AppearanceTabs />
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">اللون الأساسي</h3>

                <div class="flex items-center gap-4">
                    <div class="space-y-2">
                        <Label for="primary_color">اختر اللون الأساسي</Label>
                        <div class="flex items-center gap-2">
                            <input
                                id="primary_color_picker"
                                type="color"
                                :value="form.primary_color"
                                @input="form.primary_color = ($event.target as HTMLInputElement).value"
                                class="h-9 w-12 cursor-pointer rounded-lg border border-border/50"
                            />
                            <Input
                                id="primary_color"
                                v-model="form.primary_color"
                                placeholder="#0EA5E9"
                                class="w-32"
                            />
                        </div>
                    </div>

                    <div class="mt-4 h-16 w-16 rounded-2xl shadow-lg" :style="{ backgroundColor: form.primary_color }" />
                </div>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">اللغة والخط</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>اللغة</Label>
                        <Select v-model="form.language">
                            <SelectTrigger>
                                <SelectValue placeholder="اختر اللغة" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="lang in languageOptions" :key="lang.value" :value="lang.value">
                                    {{ lang.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label>حجم الخط</Label>
                        <Select v-model="form.font_size">
                            <SelectTrigger>
                                <SelectValue placeholder="اختر حجم الخط" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="size in fontSizeOptions" :key="size.value" :value="size.value">
                                    {{ size.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>

            <div class="glass-panel-soft space-y-5 p-5">
                <h3 class="text-sm font-semibold text-foreground">معاينة مباشرة</h3>
                <div
                    class="rounded-2xl border border-border/50 p-6"
                    :style="{ fontSize: form.font_size === 'small' ? '12px' : form.font_size === 'large' ? '18px' : '14px' }"
                >
                    <div class="mb-3 flex items-center gap-3">
                        <div class="h-8 w-8 rounded-full" :style="{ backgroundColor: form.primary_color }" />
                        <span class="font-semibold" :style="{ color: form.primary_color }">معاينة النص</span>
                    </div>
                    <p class="text-muted-foreground">
                        هذه معاينة مباشرة للإعدادات المحددة. سيتم تطبيق التغييرات على كامل النظام بعد الحفظ.
                    </p>
                    <div class="mt-3 flex gap-2">
                        <span class="rounded-lg px-3 py-1 text-white text-sm" :style="{ backgroundColor: form.primary_color }">زر أساسي</span>
                        <span class="rounded-lg border px-3 py-1 text-sm" :style="{ borderColor: form.primary_color, color: form.primary_color }">زر ثانوي</span>
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
