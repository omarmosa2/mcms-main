export type Department = {
    id: number;
    clinic_id: number;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    doctor_profiles_count: number;
    working_hours?: ClinicWorkingHour[];
    created_by: number | null;
    updated_by: number | null;
    creator?: {
        id: number;
        name: string;
    } | null;
    updater?: {
        id: number;
        name: string;
    } | null;
    created_at: string | null;
    updated_at: string | null;
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

export type DepartmentSortField =
    | 'name'
    | 'code'
    | 'is_active'
    | 'doctor_profiles_count'
    | 'created_at';

export type SortDirection = 'asc' | 'desc';
export type ActiveFilter = 'all' | 'active' | 'inactive';
