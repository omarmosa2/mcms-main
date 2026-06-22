<script setup lang="ts">
import { computed } from 'vue';
import { AlertTriangle } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import type { Doctor } from '../types';

const props = defineProps<{
    doctor: Doctor | null;
}>();

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();

const open = computed({
    get: () => props.doctor !== null,
    set: (value: boolean) => {
        if (!value) {
            emit('close');
        }
    },
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-w-md rounded-xl bg-card p-0"
            dir="rtl"
        >
            <DialogHeader class="border-b border-border px-5 py-4 text-right">
                <DialogTitle class="flex items-center gap-2 text-lg font-bold text-rose-600">
                    <AlertTriangle class="size-5" />
                    حذف الطبيب
                </DialogTitle>
                <DialogDescription class="text-muted-foreground">
                    تأكيد حذف الطبيب من النظام.
                </DialogDescription>
            </DialogHeader>

            <div v-if="doctor" class="space-y-3 px-5 py-4 text-right">
                <p class="text-sm text-foreground">
                    هل أنت متأكد من حذف الطبيب
                    <span class="font-bold">{{ doctor.full_name }}</span>؟
                </p>
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
                    سيتم حذف الطبيب وجدول دوامه فقط. العيادة وباقي الأطباء لن يتأثروا.
                </p>
            </div>

            <DialogFooter class="border-t border-border px-5 py-3">
                <Button
                    variant="outline"
                    class="rounded-lg"
                    @click="emit('close')"
                >
                    إلغاء
                </Button>
                <Button
                    variant="destructive"
                    class="rounded-lg"
                    @click="emit('confirm')"
                >
                    تأكيد الحذف
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
