import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ClinicFormModal from '@/pages/departments/components/ClinicFormModal.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: vi.fn((defaults: Record<string, unknown>) => ({
        ...defaults,
        errors: {},
        processing: false,
        clearErrors: vi.fn(),
        defaults: vi.fn(),
        reset: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
    })),
}));

vi.mock('lucide-vue-next', () => ({
    Clock: {
        template: '<svg />',
    },
}));

const mountModal = () => mount(ClinicFormModal, {
    props: {
        open: true,
        department: null,
    },
    global: {
        stubs: {
            Button: {
                props: ['type'],
                template: '<button :type="type ?? \'button\'"><slot /></button>',
            },
            ClinicWorkingHoursSelector: {
                template: '<div data-testid="working-hours-selector" />',
            },
            Dialog: {
                emits: ['update:open'],
                props: ['open'],
                template: `
                    <div data-testid="dialog">
                        <button type="button" data-testid="dialog-close" @click="$emit('update:open', false)">close</button>
                        <slot />
                    </div>
                `,
            },
            DialogContent: {
                emits: ['escapeKeyDown', 'interactOutside'],
                template: `
                    <div data-testid="dialog-content">
                        <button type="button" data-testid="outside-close" @click="$emit('interactOutside', new Event('interactOutside'))">outside</button>
                        <button type="button" data-testid="escape-close" @click="$emit('escapeKeyDown', new Event('escapeKeyDown'))">escape</button>
                        <slot />
                    </div>
                `,
            },
            DialogDescription: {
                template: '<p><slot /></p>',
            },
            DialogFooter: {
                template: '<footer><slot /></footer>',
            },
            DialogHeader: {
                template: '<header><slot /></header>',
            },
            DialogTitle: {
                template: '<h2><slot /></h2>',
            },
            Input: {
                emits: ['update:modelValue'],
                props: ['modelValue'],
                template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
            },
            InputError: {
                template: '<p />',
            },
            Label: {
                template: '<label><slot /></label>',
            },
        },
    },
});

describe('ClinicFormModal', () => {
    it('closes when the dialog requests a close', async () => {
        const wrapper = mountModal();

        await wrapper.find('[data-testid="dialog-close"]').trigger('click');

        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('closes when interacting outside the dialog content', async () => {
        const wrapper = mountModal();

        await wrapper.find('[data-testid="outside-close"]').trigger('click');

        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('closes from the footer cancel button', async () => {
        const wrapper = mountModal();

        await wrapper.find('form button[type="button"]').trigger('click');

        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });
});
