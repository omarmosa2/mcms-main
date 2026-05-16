import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useDirection() {
    const page = usePage();

    const isRtl = computed(() => {
        const localization = page.props.localization as { direction?: 'ltr' | 'rtl' } | undefined;

        return localization?.direction === 'rtl';
    });

    const direction = computed(() => (isRtl.value ? 'rtl' : 'ltr'));

    return { isRtl, direction };
}
