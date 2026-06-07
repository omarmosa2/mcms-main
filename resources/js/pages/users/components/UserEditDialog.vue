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
                <DialogTitle class="text-base font-medium text-[#1A1A1A]">تعديل المستخدم</DialogTitle>
                <DialogDescription class="text-sm text-[#6B7280] mt-0.5">تعديل بيانات المستخدم والأدوار</DialogDescription>
            </DialogHeader>

            <Form
                v-if="user"
                v-bind="UserController.update.form(user.id)"
                class="p-6 space-y-4 max-h-[60vh] overflow-y-auto"
                v-slot="{ errors, processing }"
            >
                <div class="flex flex-col gap-1.5">
                    <Label for="edit_name" class="text-sm font-medium text-[#374151]">
                        الاسم الكامل
                        <span class="text-[#DC2626] mr-1">*</span>
                    </Label>
                    <Input
                        id="edit_name"
                        name="name"
                        :default-value="user.name"
                        required
                        class="w-full h-10 rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm text-[#1A1A1A] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10 transition-colors"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="edit_email" class="text-sm font-medium text-[#374151]">
                        البريد الإلكتروني
                        <span class="text-[#DC2626] mr-1">*</span>
                    </Label>
                    <Input
                        id="edit_email"
                        name="email"
                        type="email"
                        :default-value="user.email"
                        required
                        class="w-full h-10 rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm text-[#1A1A1A] placeholder:text-[#9CA3AF] focus:outline-none focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10 transition-colors"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="edit_role_name" class="text-sm font-medium text-[#374151]">
                        الدور
                        <span class="text-[#DC2626] mr-1">*</span>
                    </Label>
                    <select
                        id="edit_role_name"
                        name="role_name"
                        required
                        class="w-full h-10 rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm text-[#6B7280] focus:outline-none focus:border-[#0EA5E9] focus:ring-2 focus:ring-[#0EA5E9]/10 transition-colors appearance-none cursor-pointer"
                    >
                        <option value="">اختر دوراً</option>
                        <option
                            v-for="role in roles"
                            :key="role.id"
                            :value="role.name"
                            :selected="user.role_names.includes(role.name)"
                        >
                            {{ role.name }}
                        </option>
                    </select>
                    <InputError :message="errors.role_name" />
                </div>

                <div class="flex items-center gap-2">
                    <input
                        id="edit_is_active"
                        name="is_active"
                        type="checkbox"
                        value="1"
                        :checked="user.is_active"
                        class="size-4 rounded border-border"
                    />
                    <Label for="edit_is_active" class="text-sm font-normal text-[#374151]">
                        حساب نشط
                    </Label>
                </div>

                <DialogFooter class="flex items-center justify-between p-6 pt-4 gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-9 px-4 rounded-lg text-[#6B7280] text-sm font-medium hover:bg-[#F9FAFB] hover:text-[#374151] transition-colors duration-150 active:scale-[0.98]"
                        :disabled="processing"
                        @click="emit('close')"
                    >
                        إلغاء
                    </Button>
                    <Button
                        type="submit"
                        variant="default"
                        :disabled="processing"
                        class="h-9 px-4 rounded-lg bg-[#0EA5E9] text-white text-sm font-medium hover:bg-[#0284C7] active:scale-[0.98] transition-all duration-150"
                    >
                        حفظ التغييرات
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
