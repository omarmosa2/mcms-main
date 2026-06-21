export type DoctorProfileStatus = 'active' | 'on_leave' | 'inactive';
export type DoctorGender = 'male' | 'female';
export type CompensationType = 'percentage' | 'weekly' | 'monthly';

export type DoctorUser = {
    id: number;
    name: string;
    email: string | null;
    is_active: boolean;
};

export type ClinicSelectOption = {
    id: number;
    name: string;
    code: string | null;
    is_active: boolean;
    working_hours: ClinicWorkingHour[];
};

export type ClinicOption = {
    id: number;
    name: string | null;
};

export type WorkingHour = {
    day_of_week: number;
    is_active: boolean;
    start_time: string | null;
    end_time: string | null;
};

export type ClinicWorkingHour = {
    day_of_week: number;
    is_active: boolean;
    start_time: string | null;
    end_time: string | null;
};

export type DoctorProfile = {
    id: number;
    clinic_id: number;
    user_id: number;
    gender: DoctorGender | null;
    phone: string | null;
    work_start_date: string | null;
    license_number: string | null;
    specialty: string;
    consultation_duration_minutes: number;
    status: DoctorProfileStatus;
    compensation_type: CompensationType | null;
    compensation_value: string | number | null;
    work_schedule: Record<string, unknown> | null;
    working_hours: WorkingHour[];
    bio: string | null;
    user?: DoctorUser | null;
    clinic?: ClinicSelectOption | null;
    created_at: string | null;
    updated_at: string | null;
};

export type DoctorProfileStats = {
    total_doctors: number;
    active_doctors: number;
    on_leave_doctors: number;
    inactive_doctors: number;
    clinics_with_doctors: number;
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
