<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Department } from './types';

defineProps<{
    open: boolean;
    department: Department | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const handleClose = () => {
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="(val: boolean) => !val && handleClose()">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{ department?.name ?? 'تفاصيل القسم' }}
                </DialogTitle>
                <DialogDescription>
                    ملف القسم الكامل وسياق الملكية.
                </DialogDescription>
            </DialogHeader>

            <dl
                v-if="department"
                class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2"
            >
                <div class="space-y-1">
                    <dt
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        الرمز
                    </dt>
                    <dd class="text-sm">
                        {{ department.code ?? '-' }}
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        الحالة
                    </dt>
                    <dd class="text-sm capitalize">
                        {{ department.is_active ? 'نشط' : 'غير نشط' }}
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        الأطباء المرتبطون
                    </dt>
                    <dd class="text-sm">
                        {{ department.doctor_profiles_count }}
                    </dd>
                </div>
                <div class="space-y-1">
                    <dt
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        آخر تحديث بواسطة
                    </dt>
                    <dd class="text-sm">
                        {{ department.updater?.name ?? '-' }}
                    </dd>
                </div>
                <div class="space-y-1 sm:col-span-2">
                    <dt
                        class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase"
                    >
                        الوصف
                    </dt>
                    <dd class="text-sm leading-6 text-muted-foreground">
                        {{ department.description ?? 'لا يوجد وصف' }}
                    </dd>
                </div>
            </dl>

            <DialogFooter>
                <Button
                    type="button"
                    variant="neumorphic"
                    @click="handleClose"
                >
                    إغلاق
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>