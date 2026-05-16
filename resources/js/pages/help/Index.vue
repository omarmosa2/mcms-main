<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, FileText, HelpCircle, Mail, MessageSquare, Search, Settings, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import InternalPageHero from '@/components/InternalPageHero.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type HelpArticle = {
    slug: string;
    title: string;
    excerpt: string;
    category: string;
    icon: string;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Help Center', href: '/help' },
        ],
    },
});

const searchQuery = ref('');

const categories = [
    { name: 'Getting Started', icon: BookOpen, description: 'Learn the basics of using MCMS', count: 5 },
    { name: 'Patient Management', icon: Users, description: 'Manage patient records and data', count: 8 },
    { name: 'Appointments & Queue', icon: HelpCircle, description: 'Schedule and manage appointments', count: 6 },
    { name: 'Billing & Invoices', icon: FileText, description: 'Handle billing and payments', count: 7 },
    { name: 'Settings & Configuration', icon: Settings, description: 'Customize your system settings', count: 4 },
    { name: 'Support', icon: Mail, description: 'Contact support and get help', count: 3 },
];

const articles: HelpArticle[] = [
    { slug: 'getting-started', title: 'Getting Started with MCMS', excerpt: 'Learn how to navigate the dashboard and access key features.', category: 'Getting Started', icon: 'BookOpen' },
    { slug: 'creating-patients', title: 'Creating Your First Patient Record', excerpt: 'Step-by-step guide to adding new patients to the system.', category: 'Patient Management', icon: 'Users' },
    { slug: 'managing-queue', title: 'Managing the Patient Queue', excerpt: 'How to enqueue patients, call next, and manage queue status.', category: 'Appointments & Queue', icon: 'HelpCircle' },
    { slug: 'generating-invoices', title: 'Generating Invoices', excerpt: 'Create and manage invoices for patient services.', category: 'Billing & Invoices', icon: 'FileText' },
    { slug: 'notification-settings', title: 'Configuring Notifications', excerpt: 'Set up email and SMS notifications for important events.', category: 'Settings & Configuration', icon: 'Settings' },
    { slug: 'import-export-data', title: 'Importing and Exporting Data', excerpt: 'Bulk import patient records and export data for reporting.', category: 'Patient Management', icon: 'Users' },
    { slug: 'user-roles', title: 'Understanding User Roles', excerpt: 'Learn about different user roles and their permissions.', category: 'Settings & Configuration', icon: 'Settings' },
    { slug: 'contact-support', title: 'Contacting Support', excerpt: 'How to reach our support team for assistance.', category: 'Support', icon: 'Mail' },
];

const filteredArticles = (query: string): HelpArticle[] => {
    if (query.trim() === '') {
        return articles;
    }

    const lowerQuery = query.toLowerCase();

    return articles.filter(
        (article) =>
            article.title.toLowerCase().includes(lowerQuery) ||
            article.excerpt.toLowerCase().includes(lowerQuery) ||
            article.category.toLowerCase().includes(lowerQuery),
    );
};
</script>

<template>
    <Head title="Help Center" />

    <div class="mx-auto w-full max-w-[1680px] space-y-5 p-4 md:p-6">
        <InternalPageHero
            kicker="Need help?"
            title="Help Center"
            description="Find answers to common questions, learn how to use features, and get support when you need it."
            :metrics="[
                { label: 'Articles', value: String(articles.length), hint: 'Knowledge base articles' },
                { label: 'Categories', value: String(categories.length), hint: 'Organized by topic' },
            ]"
        />

        <section class="glass-panel-soft p-5">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    placeholder="Search for help articles..."
                    class="pattern-field-clay pl-10"
                />
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="category in categories"
                :key="category.name"
                class="glass-panel-soft p-5 transition-all hover-lift"
            >
                <div class="flex items-start gap-3">
                    <component :is="category.icon" class="mt-0.5 size-5 text-muted-foreground" />
                    <div>
                        <h3 class="text-sm font-semibold">{{ category.name }}</h3>
                        <p class="mt-1 text-xs text-muted-foreground">{{ category.description }}</p>
                        <p class="mt-2 text-xs font-medium text-primary">{{ category.count }} articles</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="glass-panel-soft p-5">
            <h3 class="pattern-typographic-title mb-4 text-[0.76rem]">
                Popular Articles
            </h3>

            <div class="space-y-3">
                <Link
                    v-for="article in filteredArticles(searchQuery)"
                    :key="article.slug"
                    :href="`/help/${article.slug}`"
                    class="flex items-start gap-3 rounded-xl border border-border/60 bg-background/40 p-4 transition-colors hover:bg-background/60"
                >
                    <HelpCircle class="mt-0.5 size-4 text-muted-foreground" />
                    <div class="flex-1">
                        <h4 class="text-sm font-semibold">{{ article.title }}</h4>
                        <p class="mt-1 text-xs text-muted-foreground">{{ article.excerpt }}</p>
                        <span class="mt-2 inline-block rounded-full border border-border/60 bg-background/80 px-2.5 py-0.5 text-[0.65rem] font-medium text-muted-foreground">
                            {{ article.category }}
                        </span>
                    </div>
                </Link>

                <div v-if="filteredArticles(searchQuery).length === 0" class="py-8 text-center text-muted-foreground">
                    <MessageSquare class="mx-auto mb-2 size-8" />
                    <p class="text-sm">No articles found matching your search.</p>
                </div>
            </div>
        </section>

        <section class="glass-panel-soft p-5">
            <h3 class="pattern-typographic-title mb-4 text-[0.76rem]">
                Still need help?
            </h3>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-border/60 bg-background/40 p-4">
                    <Mail class="mb-2 size-5 text-muted-foreground" />
                    <h4 class="text-sm font-semibold">Email Support</h4>
                    <p class="mt-1 text-xs text-muted-foreground">Send us an email and we'll respond within 24 hours.</p>
                    <Button variant="neumorphic" size="sm" class="mt-3">
                        Contact via Email
                    </Button>
                </div>

                <div class="rounded-xl border border-border/60 bg-background/40 p-4">
                    <MessageSquare class="mb-2 size-5 text-muted-foreground" />
                    <h4 class="text-sm font-semibold">Live Chat</h4>
                    <p class="mt-1 text-xs text-muted-foreground">Chat with our support team in real-time during business hours.</p>
                    <Button variant="neumorphic" size="sm" class="mt-3">
                        Start Chat
                    </Button>
                </div>
            </div>
        </section>
    </div>
</template>
