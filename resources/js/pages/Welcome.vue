<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarClock,
    CircleCheck,
    LayoutGrid,
    ShieldCheck,
    Stethoscope,
} from 'lucide-vue-next';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const capabilities = [
    {
        title: 'سجلات المرضى',
        description:
            'سجلات مركزية وبيانات ديموغرافية ومتابعة كاملة لدورة حياة المريض.',
        icon: Stethoscope,
    },
    {
        title: 'المواعيد وقائمة الانتظار',
        description:
            'إدارة المواعيد وسير الانتظار مع انتقالات حالة سريعة.',
        icon: CalendarClock,
    },
    {
        title: 'الفواتير والتقارير',
        description:
            'تتبع الفواتير والمدفوعات والأداء المالي في مكان واحد.',
        icon: LayoutGrid,
    },
];

const trustSignals = [
    'تنقل وإجراءات مبنية على الصلاحيات',
    'مصمم للاستقبال عالي الحجم والفرق السريرية',
    'تصميم يضمن سرعة الانضمام والاتساق اليومي',
];
</script>

<template>
    <Head title="MCMS" />

    <div class="relative min-h-svh bg-background" dir="rtl">
        <div class="relative mx-auto w-full max-w-[1480px] px-4 pb-8 sm:px-6 lg:px-8">
            <header class="flex flex-wrap items-center justify-between gap-3 py-5 sm:py-6">
                <div class="inline-flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary text-white">
                        <ShieldCheck class="size-5" />
                    </div>
                    <div class="space-y-0.5">
                        <p class="text-sm font-semibold">MCMS</p>
                        <p class="text-[0.65rem] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                            منصة العمليات السريرية
                        </p>
                    </div>
                </div>

                <nav class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex h-9 items-center rounded-lg border border-border bg-card px-4 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                    >
                        لوحة التحكم
                    </Link>

                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-9 items-center rounded-lg border border-border bg-card px-4 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            تسجيل الدخول
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="inline-flex h-9 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-95"
                        >
                            حساب جديد
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr] lg:gap-8">
                <section class="flex flex-col justify-center py-8 lg:py-12">
                    <span class="hero-kicker self-start">صُمم للسرعة والوضوح</span>

                    <h1 class="mt-4 max-w-3xl text-3xl leading-tight font-semibold tracking-tight sm:text-4xl lg:text-5xl">
                        منصة موحدة لإدارة
                        <span class="font-bold text-primary">العمليات السريرية</span>
                        للفرق التي تعمل بسرعة.
                    </h1>

                    <p class="mt-4 max-w-2xl text-sm leading-7 text-muted-foreground sm:text-base">
                        يجمع MCMS الجدولة وسير المرضى والزيارات والفواتير والتقارير في مساحة عمل واحدة واضحة، لتمكين فريقك من اتخاذ قرارات أسرع بتدخل أقل.
                    </p>

                    <div class="mt-7 flex flex-wrap items-center gap-3 sm:mt-8">
                        <Link
                            :href="$page.props.auth.user ? dashboard() : login()"
                            class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-95"
                        >
                            {{ $page.props.auth.user ? 'الانتقال للوحة التحكم' : 'ابدأ بتسجيل الدخول' }}
                            <ArrowLeft class="size-4" />
                        </Link>
                        <Link
                            v-if="!$page.props.auth.user && canRegister"
                            :href="register()"
                            class="inline-flex h-10 items-center rounded-lg border border-border bg-card px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            تسجيل حساب جديد
                        </Link>
                    </div>
                </section>

                <section class="space-y-4 py-4">
                    <h2 class="text-lg font-semibold">لماذا تختار MCMS</h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        توازن بين التحكم التشغيلي والوضوح البصري، مع كل وحدة مضبوطة لضغط العيادة اليومي.
                    </p>

                    <div class="space-y-3">
                        <article
                            v-for="capability in capabilities"
                            :key="capability.title"
                            class="rounded-xl border border-border/70 bg-card p-4"
                        >
                            <div class="flex items-start gap-3">
                                <div class="rounded-lg bg-muted p-2 text-primary">
                                    <component :is="capability.icon" class="size-4" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold">
                                        {{ capability.title }}
                                    </h3>
                                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                                        {{ capability.description }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="rounded-xl border border-border/70 bg-card p-4">
                        <p class="text-[0.67rem] font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                            الثقة والحوكمة
                        </p>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="signal in trustSignals"
                                :key="signal"
                                class="flex items-start gap-2 text-sm text-muted-foreground"
                            >
                                <CircleCheck class="mt-0.5 size-4 shrink-0 text-success-500" />
                                <span>{{ signal }}</span>
                            </li>
                        </ul>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>
