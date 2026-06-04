export type Option = {
    id: number;
    full_name?: string;
    appointment_number?: string;
    name?: string;
};

export type QueueEntry = {
    id: number;
    appointment_id: number | null;
    patient_id: number;
    assigned_doctor_id: number | null;
    queue_date: string;
    queue_number: number;
    priority: number;
    status: string;
    notes: string | null;
    checked_in_at: string | null;
    called_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    patient?: {
        id?: number;
        full_name?: string;
    };
    appointment?: {
        id?: number;
        appointment_number?: string;
    };
    assigned_doctor?: {
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

export type QueueSortField =
    | 'queue_number'
    | 'queue_date'
    | 'priority'
    | 'status'
    | 'checked_in_at';

export type SortDirection = 'asc' | 'desc';

export type ActiveFilter = {
    key: string;
    label: string;
    value: string | null;
};