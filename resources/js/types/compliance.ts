export type AuditEventPayload = {
    id: number;
    actor: {
        id: number | null;
        name: string | null;
        email: string | null;
    };
    action: string;
    resource: {
        type: string | null;
        id: number | null;
    };
    old: Record<string, unknown> | null;
    new: Record<string, unknown> | null;
    reason: string | null;
    occurred_at: string | null;
};
