<script setup lang="ts">
import UserController from '@/actions/App/Http/Controllers/Security/UserController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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

const props = defineProps<{ user: User | null; roles: Role[] }>();
const emit = defineEmits<{ close: [] }>();
</script>

<template>
    <Dialog :open="user !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>تعديل المستخدم</DialogTitle>
                <DialogDescription>تعديل بيانات المستخدم والأدوار.</DialogDescription>
            </DialogHeader>

            <Form
                v-if="user"
                v-bind="UserController.update.form(user.id)"
                class="space-y-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="edit_name">الاسم الكامل</Label>
                    <Input
                        id="edit_name"
                        name="name"
                        :default-value="user.name"
                        required
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit_email">البريد الإلكتروني</Label>
                    <Input
                        id="edit_email"
                        name="email"
                        type="email"
                        :default-value="user.email"
                        required
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit_role_name">الدور</Label>
                    <select
                        id="edit_role_name"
                        name="role_name"
                        required
                        class="pattern-field-clay h-9 px-3 py-1.5"
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
                    <Label for="edit_is_active" class="text-sm font-normal">
                        حساب نشط
                    </Label>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="emit('close')">إلغاء</Button>
                    <Button type="submit" variant="clay" :disabled="processing">حفظ التغييرات</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
