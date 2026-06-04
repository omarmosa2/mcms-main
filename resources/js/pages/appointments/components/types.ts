export type Option = {
    id: number;
    name?: string;
    full_name?: string;
};

export type Appointment = {
    id: number;
    patient_id: number;
    doctor_id: number | null;
    appointment_number: string;
    scheduled_for: string;
    duration_minutes: number;
    status: string;
    cancel_reason: string | null;
    notes: string | null;
    patient?: {
        id?: number;
        full_name?: string;
    };
    doctor?: {
        id?: number;
        name?: string;
    };
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

export type PaginationNavigation = {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
};

export type PaginatedResponse<T> = {
    data: T[];
    links: PaginationNavigation;
    meta: PaginationMeta;
};

export type AppointmentSortField =
    | 'appointment_number'
    | 'scheduled_for'
    | 'duration_minutes'
    | 'status';

export type SortDirection = 'asc' | 'desc';