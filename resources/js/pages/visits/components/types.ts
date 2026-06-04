export type Option = {
    id: number;
    full_name?: string;
    appointment_number?: string;
    label?: string;
    name?: string;
};

export type Visit = {
    id: number;
    queue_entry_id: number | null;
    appointment_id: number | null;
    patient_id: number;
    doctor_id: number | null;
    visit_number: string;
    status: string;
    started_at: string | null;
    in_progress_at: string | null;
    completed_at: string | null;
    chief_complaint: string | null;
    clinical_notes: string | null;
    diagnosis_notes: string | null;
    treatment_plan: string | null;
    patient?: {
        id?: number;
        full_name?: string;
    };
    doctor?: {
        id?: number;
        name?: string;
    };
    appointment?: {
        id?: number;
        appointment_number?: string;
    };
    queue_entry?: {
        id?: number;
        queue_number?: number;
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

export type VisitSortField = 'visit_number' | 'status' | 'started_at' | 'completed_at';

export type SortDirection = 'asc' | 'desc';

export type KanbanColumn = {
    key: string;
    label: string;
    dotColor: string;
    headerBg: string;
};

export type ActiveFilter = {
    key: string;
    label: string;
    value: string | null;
};