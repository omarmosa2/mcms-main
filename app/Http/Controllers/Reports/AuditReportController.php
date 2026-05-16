<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditReportController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'action' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 25);

        $query = AuditLog::query()
            ->forClinic($clinicId)
            ->with('user')
            ->latest('occurred_at');

        if (isset($validated['from'])) {
            $query->whereDate('occurred_at', '>=', (string) $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('occurred_at', '<=', (string) $validated['to']);
        }

        if (isset($validated['action'])) {
            $query->where('action', (string) $validated['action']);
        }

        if (isset($validated['user_id'])) {
            $query->where('user_id', (int) $validated['user_id']);
        }

        $logs = $query->paginate($perPage);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->transformPaginator($logs),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
                'filters' => [
                    'from' => $validated['from'] ?? null,
                    'to' => $validated['to'] ?? null,
                    'action' => $validated['action'] ?? null,
                    'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                ],
            ]);
        }

        return Inertia::render('reports/Audit', [
            'audit_logs' => [
                'data' => $this->transformPaginator($logs),
                'links' => [
                    'first' => $logs->url(1),
                    'last' => $logs->url($logs->lastPage()),
                    'prev' => $logs->previousPageUrl(),
                    'next' => $logs->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                    'links' => [],
                ],
            ],
            'filters' => [
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'action' => $validated['action'] ?? null,
                'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'action' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $query = AuditLog::query()
            ->forClinic($clinicId)
            ->with('user')
            ->latest('occurred_at');

        if (isset($validated['from'])) {
            $query->whereDate('occurred_at', '>=', (string) $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('occurred_at', '<=', (string) $validated['to']);
        }

        if (isset($validated['action'])) {
            $query->where('action', (string) $validated['action']);
        }

        if (isset($validated['user_id'])) {
            $query->where('user_id', (int) $validated['user_id']);
        }

        $filename = sprintf('audit-export-%s.csv', now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fputcsv($output, ['event_id', 'actor', 'action', 'resource_type', 'resource_id', 'reason', 'occurred_at']);

            foreach ($query->cursor() as $auditLog) {
                fputcsv($output, [
                    $auditLog->id,
                    $auditLog->user?->email,
                    $auditLog->action,
                    $auditLog->auditable_type,
                    $auditLog->auditable_id,
                    $auditLog->metadata['reason'] ?? null,
                    $auditLog->occurred_at?->toISOString(),
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function transformPaginator(LengthAwarePaginator $logs): array
    {
        return collect($logs->items())
            ->map(fn (AuditLog $auditLog): array => [
                'id' => $auditLog->id,
                'actor' => [
                    'id' => $auditLog->user?->id,
                    'name' => $auditLog->user?->name,
                    'email' => $auditLog->user?->email,
                ],
                'action' => $auditLog->action,
                'resource' => [
                    'type' => $auditLog->auditable_type,
                    'id' => $auditLog->auditable_id,
                ],
                'old' => $auditLog->old_values,
                'new' => $auditLog->new_values,
                'reason' => $auditLog->metadata['reason'] ?? null,
                'occurred_at' => $auditLog->occurred_at?->toISOString(),
            ])
            ->values()
            ->all();
    }
}
