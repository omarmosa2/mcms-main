<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

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
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>تفاصيل المستخدم</DialogTitle>
                <DialogDescription>{{ user?.name }} - {{ user?.email }}</DialogDescription>
            </DialogHeader>

            <div v-if="user" class="grid gap-4">
                <dl class="grid gap-3 rounded-xl border border-border/70 bg-background/55 p-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الاسم الكامل</dt>
                        <dd class="text-sm font-semibold">{{ user.name }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">البريد الإلكتروني</dt>
                        <dd class="text-sm">{{ user.email }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الحالة</dt>
                        <dd>
                            <span
                                :class="[
                                    user.is_active
                                        ? 'border-success-300/70 bg-success-50 text-success-800 dark:border-success-500/35 dark:bg-success-500/15 dark:text-success-100'
                                        : 'border-destructive/70 bg-destructive/10 text-destructive dark:border-destructive/35 dark:bg-destructive/15 dark:text-destructive-foreground',
                                ]"
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full"
                                    :class="user.is_active ? 'bg-success-500' : 'bg-destructive'"
                                ></span>
                                {{ user.is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                        </dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">مدير النظام</dt>
                        <dd class="text-sm">{{ user.is_super_admin ? 'نعم' : 'لا' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">المصادقة الثنائية</dt>
                        <dd class="text-sm">{{ user.two_factor_enabled ? 'مفعّلة' : 'غير مفعّلة' }}</dd>
                    </div>
                    <div class="space-y-1">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">تاريخ الإنشاء</dt>
                        <dd class="text-sm">{{ user.created_at ?? '-' }}</dd>
                    </div>
                    <div class="space-y-1 sm:col-span-2">
                        <dt class="text-[0.65rem] font-semibold tracking-normal text-muted-foreground uppercase">الأدوار</dt>
                        <dd>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="roleName in user.role_names"
                                    :key="roleName"
                                    class="rounded-full border border-border/70 bg-background/80 px-2 py-0.5 text-xs"
                                >
                                    {{ roleName }}
                                </span>
                                <span v-if="user.role_names.length === 0" class="text-sm text-muted-foreground">لا توجد أدوار</span>
                            </div>
                        </dd>
                    </div>
                </dl>
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="emit('close')">إغلاق</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
