export type PatientVisitHistoryItem = {
    id: number;
    visit_number: string;
    status: string;
    doctor: {
        id: number;
        name: string;
    } | null;
    started_at: string | null;
    in_progress_at: string | null;
    completed_at: string | null;
};

export type PatientAttachment = {
    id: number;
    patient_id: number;
    original_name: string;
    mime_type: string | null;
    extension: string | null;
    size_bytes: number;
    uploaded_at: string | null;
    uploaded_by?: {
        id: number;
        name: string;
        email: string;
    } | null;
    download_url: string;
};

export type Patient = {
    id: number;
    file_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
    date_of_birth: string | null;
    age: number | null;
    gender: string | null;
    phone: string | null;
    email: string | null;
    national_id: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    chronic_conditions: string[];
    allergies: string[];
    current_medications: string[];
    visit_history: PatientVisitHistoryItem[];
    attachments: PatientAttachment[];
    created_at: string | null;
    updated_at: string | null;
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

export type PatientSortField =
    | 'file_number'
    | 'full_name'
    | 'date_of_birth'
    | 'gender'
    | 'phone'
    | 'email'
    | 'created_at';

export type SortDirection = 'asc' | 'desc';

export type ActiveFilter = {
    key: string;
    label: string;
    value: string | null;
};