import type { Page, Router } from '@inertiajs/vue3';
import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            settings: {
                currency: string;
            };
            auth: Auth;
            branding: {
                company_name: string | null;
                logo_path: string | null;
                theme_tokens: Record<string, string> | null;
                locale_default: string;
                domain: string | null;
            };
            localization: {
                locale: string;
                direction: 'ltr' | 'rtl';
            };
            security: {
                can_manage_policies: boolean;
                policy: Record<string, unknown> | null;
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
