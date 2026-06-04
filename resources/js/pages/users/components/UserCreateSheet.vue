<script setup lang="ts">
import UserController from '@/actions/App/Http/Controllers/Security/UserController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

type Role = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
};

const props = defineProps<{ open: boolean; roles: Role[] }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-[520px] p-0 overflow-hidden">
            <DialogHeader class="p-6 pb-4 border-b border-border/60">
                <DialogTitle>إنشاء مستخدم</DialogTitle>
                <DialogDescription>إضافة مستخدم جديد إلى النظام.</DialogDescription>
            </DialogHeader>

            <Form
                id="user-create-form"
                v-bind="UserController.store.form()"
                class="px-6 py-4 space-y-4 max-h-[60vh] overflow-y-auto"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">الاسم الكامل</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        placeholder="الاسم الكامل"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">البريد الإلكتروني</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        required
                        placeholder="example@domain.com"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="role_name">الدور</Label>
                    <select
                        id="role_name"
                        name="role_name"
                        required
                        class="pattern-field-clay h-9 px-3 py-1.5"
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

                <div class="grid gap-2">
                    <Label for="password">كلمة المرور</Label>
                    <Input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="اتركه فارغاً للتوليد التلقائي"
                        class="pattern-field-clay"
                    />
                    <InputError :message="errors.password" />
                    <p class="text-xs text-muted-foreground">
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
                    <Label for="is_active" class="text-sm font-normal">
                        حساب نشط
                    </Label>
                </div>

                <Button :disabled="processing" variant="clay" class="w-full">
                    <Plus class="ms-2 size-4" />
                    إنشاء مستخدم
                </Button>
            </Form>

            <DialogFooter class="p-6 pt-4 border-t border-border/60">
                <Button
                    type="button"
                    variant="outline"
                    @click="emit('update:open', false)"
                >
                    إلغاء
                </Button>
                <Button
                    form="user-create-form"
                    type="submit"
                    variant="default"
                >
                    <Plus class="ms-2 size-4" />
                    إنشاء مستخدم
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
