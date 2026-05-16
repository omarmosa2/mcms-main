<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Visit;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class MetricsController extends Controller
{
    public function index(): Response
    {
        $metrics = [];

        $metrics[] = $this->gauge('mcms_total_patients', Patient::query()->count(), 'Total number of patients');
        $metrics[] = $this->gauge('mcms_active_visits', Visit::query()->whereIn('status', ['started', 'in_progress'])->count(), 'Number of active visits');
        $metrics[] = $this->gauge('mcms_pending_queue', QueueEntry::query()->where('queue_date', today())->where('status', 'waiting')->count(), 'Number of patients waiting in queue');
        $metrics[] = $this->gauge('mcms_today_appointments', Appointment::query()->whereDate('scheduled_for', today())->count(), 'Number of appointments today');
        $metrics[] = $this->gauge('mcms_outstanding_invoices', Invoice::query()->whereIn('status', ['issued', 'partially_paid'])->count(), 'Number of outstanding invoices');

        $cacheHits = Cache::get('mcms_cache_hits', 0);
        $cacheMisses = Cache::get('mcms_cache_misses', 0);
        $metrics[] = $this->counter('mcms_cache_hits_total', $cacheHits, 'Total cache hits');
        $metrics[] = $this->counter('mcms_cache_misses_total', $cacheMisses, 'Total cache misses');

        $metrics[] = $this->info('mcms_app_info', 1, 'Application info', [
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
        ]);

        return response(implode("\n", $metrics)."\n", 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }

    private function gauge(string $name, float|int $value, string $help): string
    {
        return "# HELP {$name} {$help}\n# TYPE {$name} gauge\n{$name} {$value}";
    }

    private function counter(string $name, float|int $value, string $help): string
    {
        return "# HELP {$name} {$help}\n# TYPE {$name} counter\n{$name} {$value}";
    }

    private function info(string $name, float|int $value, string $help, array $labels): string
    {
        $labelStr = implode(',', array_map(fn ($k, $v) => "{$k}=\"{$v}\"", array_keys($labels), array_values($labels)));

        return "# HELP {$name} {$help}\n# TYPE {$name} gauge\n{$name}{{$labelStr}} {$value}";
    }
}
