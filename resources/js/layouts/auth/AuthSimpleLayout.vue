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
            'المواعيد والفواتير في حلقة تحكم واحدة.',
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
<section class="flex min-h-svh items-center justify-center">
    <div
        class="w-full max-w-[530px] rounded-2xl border border-border/70 bg-card p-6 shadow-xl sm:p-8 lg:p-10"
    >
        <Link
            :href="home()"
            class="mb-7 flex items-center justify-center gap-3 lg:mb-8"
        >
            <div class="flex size-10 items-center justify-center rounded-xl bg-primary text-white">
                <AppLogoIcon class="size-6 fill-current" />
            </div>

            <div class="space-y-0.5 text-center">
                <p class="text-sm font-semibold">{{ appName }}</p>

                <p
                    class="text-[0.68rem] font-semibold tracking-normal text-muted-foreground uppercase"
                >
                    بوابة الدخول
                </p>
            </div>
        </Link>

        <div class="mb-7 space-y-2 text-center lg:mb-8">
            <h1 class="text-2xl font-semibold tracking-normal">
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
</template>
