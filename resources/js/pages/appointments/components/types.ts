export type Option = {
    id: number;
    name?: string;
    full_name?: string;
    file_number?: string | null;
    phone?: string | null;
    date_of_birth?: string | null;
    age?: number | null;
    department_id?: number | null;
    specialty?: string | null;
    department?: {
        id: number;
        name: string;
    } | null;
};

export type DepartmentOption = {
    id: number;
    name: string;
};

export type ClinicWorkingDay =
    | 'saturday'
    | 'sunday'
    | 'monday'
    | 'tuesday'
    | 'wednesday'
    | 'thursday'
    | 'friday';

export type ClinicWorkingHour = {
    day_of_week: ClinicWorkingDay;
    is_active: boolean;
    start_time: string | null;
    end_time: string | null;
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
        first_name?: string;
        last_name?: string;
        full_name?: string;
        file_number?: string | null;
        phone?: string | null;
        date_of_birth?: string | null;
        age?: number | null;
    };
    doctor?: {
        id?: number;
        name?: string;
        specialty?: string | null;
        department?: {
            id: number;
            name: string;
        } | null;
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
