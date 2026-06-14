export function appointmentStatusClass(status: string): string {
    if (status === 'completed' || status === 'arrived') {
        return 'border-success/25 bg-success/10 text-success';
    }

    if (status === 'scheduled' || status === 'confirmed') {
        return 'border-info/25 bg-info/10 text-info';
    }

    if (status === 'canceled' || status === 'no_show') {
        return 'border-destructive/25 bg-destructive/10 text-destructive';
    }

    return 'border-border bg-secondary text-muted-foreground';
}

export function appointmentStatusDotClass(status: string): string {
    if (status === 'completed' || status === 'arrived') {
        return 'bg-success';
    }

    if (status === 'scheduled' || status === 'confirmed') {
        return 'bg-info';
    }

    if (status === 'canceled' || status === 'no_show') {
        return 'bg-destructive';
    }

    return 'bg-muted-foreground';
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
