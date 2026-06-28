export type DoctorGender = 'male' | 'female';

export type CompensationType = 'percentage' | 'fixed_weekly' | 'fixed_monthly';

export type Clinic = {
    id: number;
    name: string;
    code: string | null;
    is_active: boolean;
    working_hours: ClinicWorkingHour[];
};

export type ClinicWorkingHour = {
    day_of_week: number;
    is_active: boolean;
    start_time: string | null;
    end_time: string | null;
};

export type DoctorSchedule = {
    id?: number;
    doctor_profile_id?: number;
    clinic_id?: number;
    day_of_week: number;
    day_name?: string;
    start_time: string | null;
    end_time: string | null;
    is_available: boolean;
};

export type Doctor = {
    id: number;
    clinic_id: number;
    clinic?: { id: number; name: string; code: string | null } | null;
    user_id: number | null;
    full_name: string;
    gender: DoctorGender | null;
    specialty: string;
    phone: string | null;
    email: string | null;
    username: string | null;
    employment_start_date: string | null;
    compensation_type: CompensationType;
    compensation_value: number | null;
    percentage_value: number | null;
    fixed_weekly_amount: number | null;
    fixed_monthly_amount: number | null;
    currency: string;
    is_active: boolean;
    notes: string | null;
    schedules: DoctorSchedule[];
    created_at: string | null;
    updated_at: string | null;
};

export type DoctorFormData = {
    clinic_id: number | '';
    user_id: number | null;
    full_name: string;
    gender: DoctorGender;
    specialty: string;
    phone: string;
    email: string;
    username: string;
    password: string;
    employment_start_date: string;
    compensation_type: CompensationType;
    compensation_value: string;
    percentage_value: string;
    fixed_weekly_amount: string;
    fixed_monthly_amount: string;
    currency: string;
    is_active: boolean;
    notes: string;
    schedules: DoctorSchedule[];
};

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

export type PaginatedResponse<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: PaginationMeta;
};

export type DoctorFilters = {
    search: string | null;
    clinic_id: number | null;
    is_active: boolean | null;
    per_page: number;
};

export const DAY_NAMES: Record<number, string> = {
    0: 'الأحد',
    1: 'الإثنين',
    2: 'الثلاثاء',
    3: 'الأربعاء',
    4: 'الخميس',
    5: 'الجمعة',
    6: 'السبت',
};

export const ORDERED_DAYS = [6, 0, 1, 2, 3, 4, 5] as const;
