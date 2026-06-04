export type Option = {
    id: number;
    full_name?: string;
    appointment_number?: string;
    visit_number?: string;
};

export type InvoiceItem = {
    id: number;
    description: string;
    line_total: number;
};

export type Payment = {
    id: number;
    status: string;
    method: string | null;
    amount: number;
    refund_amount: number | null;
    paid_at?: string | null;
    refunded_at?: string | null;
};

export type Invoice = {
    id: number;
    patient_id: number;
    visit_id: number | null;
    appointment_id: number | null;
    invoice_number: string;
    status: string;
    issued_at: string | null;
    due_at: string | null;
    subtotal_amount: number;
    discount_amount: number;
    tax_amount: number;
    total_amount: number;
    paid_amount: number;
    balance_amount: number;
    notes: string | null;
    items?: InvoiceItem[];
    patient?: {
        id?: number;
        full_name?: string;
    };
    payments?: Payment[];
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

export type InvoiceSortField =
    | 'invoice_number'
    | 'status'
    | 'issued_at'
    | 'due_at'
    | 'total_amount'
    | 'balance_amount';

export type SortDirection = 'asc' | 'desc';