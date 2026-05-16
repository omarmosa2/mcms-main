<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Activity, ShieldCheck, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();

const appName = computed<string>(() => {
    const name = page.props.name;

    if (typeof name === 'string' && name.trim() !== '') {
        return name;
    }

    return 'MCMS';
});

const trustPillars = [
    {
        title: 'سير عمل واعٍ بالأدوار',
        description: 'كل عرض محدود بالصلاحيات لعمليات أكثر أماناً.',
        icon: ShieldCheck,
    },
    {
        title: 'نبض العمليات الحية',
        description:
            'قائمة الانتظار والمواعيد والفواتير في حلقة تحكم واحدة.',
        icon: Activity,
    },
    {
        title: 'تجربة سريرية سريعة',
        description: 'مصمم للاستقبال عالي الحجم وفرق الرعاية.',
        icon: Sparkles,
    },
];
</script>

<template>
    <div class="relative min-h-svh bg-background" dir="rtl">
        <div
            class="relative mx-auto grid min-h-svh w-full max-w-[1480px] gap-5 p-4 sm:p-6 lg:grid-cols-[1.08fr_0.92fr] lg:gap-7 lg:p-8"
        >
            <section
                class="hidden flex-col justify-between rounded-2xl border border-border/70 bg-card p-8 lg:flex"
            >
                <div class="space-y-7">
                    <Link
                        :href="home()"
                        class="inline-flex items-center gap-3 rounded-xl border border-border/70 bg-muted/50 px-4 py-3"
                    >
                        <div class="flex size-10 items-center justify-center rounded-xl bg-primary text-white">
                            <AppLogoIcon class="size-6 fill-current" />
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-sm font-semibold">{{ appName }}</p>
                            <p class="text-[0.68rem] font-semibold tracking-[0.13em] text-muted-foreground uppercase">
                                العمليات السريرية
                            </p>
                        </div>
                    </Link>

                    <div class="space-y-4">
                        <span class="hero-kicker self-start">آمن بالتصميم</span>
                        <h2 class="text-4xl leading-tight font-semibold">
                            مساحة عمل موحدة
                            <span class="text-primary">
                                للعمليات السريرية
                            </span>
                        </h2>
                        <p class="max-w-xl text-sm leading-7 text-muted-foreground">
                            إدارة سير المرضى والمواعيد والزيارات والإيرادات من واجهة متكاملة واحدة، مبنية لفرق حقيقية وضغط حقيقي.
                        </p>
                    </div>
                </div>

                <div class="space-y-3">
                    <article
                        v-for="pillar in trustPillars"
                        :key="pillar.title"
                        class="rounded-xl border border-border/70 bg-muted/30 p-4"
                    >
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 rounded-lg bg-muted p-2 text-primary">
                                <component :is="pillar.icon" class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold">
                                    {{ pillar.title }}
                                </h3>
                                <p class="mt-1 text-xs leading-6 text-muted-foreground">
                                    {{ pillar.description }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="flex items-center justify-center">
                <div class="w-full max-w-[530px] rounded-2xl border border-border/70 bg-card p-6 sm:p-8 lg:p-10">
                    <Link
                        :href="home()"
                        class="mb-7 inline-flex items-center gap-3 lg:mb-8"
                    >
                        <div class="flex size-10 items-center justify-center rounded-xl bg-primary text-white">
                            <AppLogoIcon class="size-6 fill-current" />
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-sm font-semibold">{{ appName }}</p>
                            <p class="text-[0.68rem] font-semibold tracking-[0.13em] text-muted-foreground uppercase">
                                بوابة الدخول
                            </p>
                        </div>
                    </Link>

                    <div class="mb-7 space-y-2 lg:mb-8">
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-sm leading-6 text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <slot />
                </div>
            </section>
        </div>
    </div>
</template>
