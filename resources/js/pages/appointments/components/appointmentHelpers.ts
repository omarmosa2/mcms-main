export function appointmentStatusClass(status: string): string {
    if (status === 'completed' || status === 'arrived') {
        return 'border-[var(--border-soft)] bg-[var(--accent-mint-soft)] text-[var(--accent-mint-strong)]';
    }

    if (status === 'scheduled' || status === 'confirmed') {
        return 'border-[var(--border-soft)] bg-[var(--accent-teal-soft)] text-[var(--accent-teal-strong)]';
    }

    if (status === 'canceled' || status === 'no_show') {
        return 'border-[var(--border-soft)] bg-[var(--accent-coral-soft)] text-[var(--accent-coral-strong)]';
    }

    return 'border-[var(--border-soft)] bg-[var(--surface-secondary)] text-[var(--surface-contrast-soft)]';
}

export function appointmentStatusDotClass(status: string): string {
    if (status === 'completed' || status === 'arrived') {
        return 'bg-[var(--accent-mint)]';
    }

    if (status === 'scheduled' || status === 'confirmed') {
        return 'bg-[var(--accent-teal)]';
    }

    if (status === 'canceled' || status === 'no_show') {
        return 'bg-[var(--accent-coral)]';
    }

    return 'bg-[var(--surface-contrast-soft)]';
}

export function appointmentStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        scheduled: 'مجدول',
        confirmed: 'مؤكد',
        arrived: 'حاضر',
        completed: 'تم تحويله إلى زيارة',
        canceled: 'ملغي',
        no_show: 'لم يحضر',
    };

    return labels[status] ?? status;
}

export function toDatetimeLocalValue(isoValue: string): string {
    const parsedDate = new Date(isoValue);

    if (Number.isNaN(parsedDate.getTime())) {
        return '';
    }

    const timezoneOffsetInMs = parsedDate.getTimezoneOffset() * 60_000;
    const localDate = new Date(parsedDate.getTime() - timezoneOffsetInMs);

    return localDate.toISOString().slice(0, 16);
}

export function formatTime(iso: string): string {
    const d = new Date(iso);

    return d.toLocaleTimeString('ar-SA', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });
}

export function formatArabicDate(iso: string): string {
    const d = new Date(iso);

    return d.toLocaleDateString('ar-SA', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}
