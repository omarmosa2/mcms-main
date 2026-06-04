export const visitStatusClass = (status: string): string => {
    if (status === 'started') {
        return 'border-[var(--border-soft)] bg-[var(--accent-teal-soft)] text-[var(--accent-teal-strong)]';
    }

    if (status === 'in_progress') {
        return 'border-[var(--border-soft)] bg-[var(--accent-coral-soft)] text-[var(--accent-coral-strong)]';
    }

    if (status === 'completed') {
        return 'border-[var(--border-soft)] bg-[var(--accent-mint-soft)] text-[var(--accent-mint-strong)]';
    }

    if (status === 'canceled') {
        return 'border-[var(--border-soft)] bg-[var(--accent-coral-soft)] text-[var(--accent-coral-strong)]';
    }

    return 'border-[var(--border-soft)] bg-[var(--surface-secondary)] text-[var(--surface-contrast-soft)]';
};

export const visitStatusDotClass = (status: string): string => {
    if (status === 'completed') {
        return 'bg-[var(--accent-mint)]';
    }

    if (status === 'started') {
        return 'bg-[var(--accent-teal)]';
    }

    if (status === 'in_progress') {
        return 'bg-[var(--accent-coral)]';
    }

    if (status === 'canceled') {
        return 'bg-[var(--accent-coral)]';
    }

    return 'bg-[var(--surface-contrast-soft)]';
};

export const visitStatusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        started: 'بدأت',
        in_progress: 'قيد التنفيذ',
        completed: 'مكتملة',
        canceled: 'ملغاة',
    };

    return labels[status] ?? status;
};

export const formatDateTime = (value: string | null): string => {
    if (value === null) {
        return '-';
    }

    return new Date(value).toLocaleString('ar-SA');
};