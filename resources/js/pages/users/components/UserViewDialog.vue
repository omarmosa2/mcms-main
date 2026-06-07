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

type User = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    is_super_admin: boolean;
    roles: string[];
    role_names: string[];
    two_factor_enabled: boolean;
    created_at: string | null;
};

type Role = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
};

defineProps<{ user: User | null; roles: Role[] }>();

const emit = defineEmits<{ close: [] }>();
</script>

<template>
    <Dialog :open="user !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent class="max-w-[520px] bg-white rounded-xl">
            <DialogHeader class="p-6 pb-4 border-b border-[#E5E7EB]">
                <DialogTitle class="text-base font-medium text-[#1A1A1A]">تفاصيل المستخدم</DialogTitle>
                <DialogDescription class="text-sm text-[#6B7280] mt-0.5">{{ user?.name }} - {{ user?.email }}</DialogDescription>
            </DialogHeader>

            <div v-if="user" class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                <div class="divide-y divide-[#E5E7EB] rounded-xl border border-[#E5E7EB] bg-white">
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">الاسم الكامل</span>
                        <span class="flex-1 text-sm font-medium text-[#1A1A1A]">{{ user.name }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">البريد الإلكتروني</span>
                        <span class="flex-1 text-sm text-[#6B7280]">{{ user.email }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">الحالة</span>
                        <span class="flex-1">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold"
                                :class="user.is_active ? 'bg-[#DBEAFE] text-[#1D4ED8]' : 'bg-[#F4F7FA] text-[#6B7280]'"
                            >
                                {{ user.is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">مدير النظام</span>
                        <span class="flex-1 text-sm text-[#6B7280]">{{ user.is_super_admin ? 'نعم' : 'لا' }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">المصادقة الثنائية</span>
                        <span class="flex-1 text-sm text-[#6B7280]">{{ user.two_factor_enabled ? 'مفعّلة' : 'غير مفعّلة' }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">تاريخ الإنشاء</span>
                        <span class="flex-1 text-sm text-[#6B7280]">{{ user.created_at ?? '-' }}</span>
                    </div>
                    <div class="flex items-start py-3 px-4">
                        <span class="w-1/3 text-sm text-[#9CA3AF] shrink-0">الأدوار</span>
                        <span class="flex-1">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="roleName in user.role_names"
                                    :key="roleName"
                                    class="inline-flex items-center rounded-full bg-[#F4F7FA] px-2.5 py-1 text-xs font-bold text-[#111827]"
                                >
                                    {{ roleName }}
                                </span>
                                <span v-if="user.role_names.length === 0" class="text-sm text-[#9CA3AF]">لا توجد أدوار</span>
                            </div>
                        </span>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex items-center justify-between p-6 pt-4 border-t border-[#E5E7EB]">
                <Button
                    type="button"
                    variant="ghost"
                    class="h-9 px-4 rounded-lg text-[#6B7280] text-sm font-medium hover:bg-[#F9FAFB] hover:text-[#374151] transition-colors duration-150 active:scale-[0.98]"
                    @click="emit('close')"
                >
                    إغلاق
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
