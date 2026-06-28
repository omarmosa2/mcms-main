import { ref } from 'vue';
import AppointmentController from '@/actions/App/Http/Controllers/Appointments/AppointmentController';
import type { TodayAvailability } from './types';

type BookingOptionsResponse = {
    data: TodayAvailability;
};

type BookingOptionsFilters = {
    clinicId?: string | number | null;
    doctorId?: string | number | null;
    date?: string | null;
};

export function useAppointmentBookingOptions(initialOptions: TodayAvailability) {
    const bookingOptions = ref<TodayAvailability>(initialOptions);
    const isLoadingBookingOptions = ref(false);

    let abortController: AbortController | null = null;

    const loadBookingOptions = async (
        filters: BookingOptionsFilters = {},
    ): Promise<void> => {
        abortController?.abort();
        abortController = new AbortController();
        isLoadingBookingOptions.value = true;

        const query: Record<string, string> = {};

        if (filters.clinicId) {
            query.clinic_id = String(filters.clinicId);
        }

        if (filters.doctorId) {
            query.doctor_id = String(filters.doctorId);
        }

        if (filters.date) {
            query.date = filters.date;
        }

        try {
            const response = await fetch(
                AppointmentController.bookingOptions.url({ query }),
                {
                    headers: {
                        Accept: 'application/json',
                    },
                    signal: abortController.signal,
                },
            );

            if (!response.ok) {
                return;
            }

            const payload = (await response.json()) as BookingOptionsResponse;
            bookingOptions.value = payload.data;
        } catch (error) {
            if (
                error instanceof DOMException &&
                error.name === 'AbortError'
            ) {
                return;
            }
        } finally {
            isLoadingBookingOptions.value = false;
        }
    };

    return {
        bookingOptions,
        isLoadingBookingOptions,
        loadBookingOptions,
    };
}
