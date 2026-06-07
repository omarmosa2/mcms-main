<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Security/UserController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Role = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
};

defineProps<{ open: boolean; roles: Role[] }>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[520px] p-0 overflow-hidden">
            <DialogHeader class="p-6 pb-4 border-b border-[#E5E7EB]">
                <DialogTitle class="text-lg font-medium text-[#1A1A1A]">إنشاء مستخدم</DialogTitle>
                <DialogDescription class="text-sm text-[#6B7280] mt-1">إضافة مستخدم جديد إلى النظام</DialogDescription>
            </DialogHeader>

            <Form
                id="user-create-form"
                v-bind="UserController.store.form()"
                class="px-6 py-4 space-y-4 max-h-[60vh] overflow-y-auto"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <div class="flex flex-col gap-1.5">
                    <Label for="name" class="text-sm font-medium text-[#374151]">
                        الاسم الكامل
                        <span class="text-[#DC2626] mr-1">*</span>
                    </Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        placeholder="الاسم الكامل"
                        class="w-full h-10 rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm text-[#1A1A1A] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10 transition-colors"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="email" class="text-sm font-medium text-[#374151]">
                        البريد الإلكتروني
                        <span class="text-[#DC2626] mr-1">*</span>
                    </Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        required
                        placeholder="example@domain.com"
                        class="w-full h-10 rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm text-[#1A1A1A] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10 transition-colors"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="role_name" class="text-sm font-medium text-[#374151]">
                        الدور
                        <span class="text-[#DC2626] mr-1">*</span>
                    </Label>
                    <select
                        id="role_name"
                        name="role_name"
                        required
                        class="w-full h-10 rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm text-[#6B7280] focus:outline-none focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10 transition-colors appearance-none cursor-pointer"
                    >
                        <option value="">اختر دوراً</option>
                        <option
                            v-for="role in roles"
                            :key="role.id"
                            :value="role.name"
                        >
                            {{ role.name }}
                        </option>
                    </select>
                    <InputError :message="errors.role_name" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="password" class="text-sm font-medium text-[#374151]">كلمة المرور</Label>
                    <Input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="اتركه فارغاً للتوليد التلقائي"
                        class="w-full h-10 rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm text-[#1A1A1A] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10 transition-colors"
                    />
                    <InputError :message="errors.password" />
                    <p class="text-xs text-[#9CA3AF]">
                        8 أحرف على الأقل، أو اتركه فارغاً للتوليد التلقائي.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        id="is_active"
                        name="is_active"
                        type="checkbox"
                        value="1"
                        checked
                        class="size-4 rounded border-border"
                    />
                    <Label for="is_active" class="text-sm font-normal text-[#374151]">
                        حساب نشط
                    </Label>
                </div>

                <Button
                    :disabled="processing"
                    variant="default"
                    class="w-full h-10 rounded-lg bg-[#0EA5E9] text-white text-sm font-medium hover:bg-[#0284C7] active:scale-[0.98] disabled:opacity-40 disabled:cursor-not-allowed transition-all duration-150"
                >
                    إنشاء مستخدم
                </Button>
            </Form>

            <DialogFooter class="p-6 pt-4 border-t border-[#E5E7EB]">
                <Button
                    type="button"
                    variant="outline"
                    class="h-9 px-4 rounded-lg border border-[#E5E7EB] bg-white text-[#6B7280] text-sm font-medium hover:bg-[#F9FAFB] hover:text-[#1A1A1A] transition-colors duration-150"
                    @click="emit('update:open', false)"
                >
                    إلغاء
                </Button>
                <Button
                    form="user-create-form"
                    type="submit"
                    variant="default"
                    class="h-9 px-4 rounded-lg bg-[#0EA5E9] text-white text-sm font-medium hover:bg-[#0284C7] active:scale-[0.98] transition-all duration-150"
                >
                    إنشاء مستخدم
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
