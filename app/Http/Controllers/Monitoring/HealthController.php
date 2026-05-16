<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'disk' => $this->checkDisk(),
        ];

        $overallStatus = 'healthy';

        foreach ($checks as $check) {
            if ($check['status'] === 'degraded') {
                $overallStatus = 'degraded';
            }

            if ($check['status'] === 'unhealthy') {
                $overallStatus = 'unhealthy';
                break;
            }
        }

        $statusCode = match ($overallStatus) {
            'healthy' => 200,
            'degraded' => 200,
            'unhealthy' => 503,
        };

        return response()->json([
            'status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $statusCode);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'duration_ms' => $duration,
                'connection' => DB::connection()->getDriverName(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_'.time();
            Cache::put($key, 'ok', 10);
            $result = Cache::get($key);
            Cache::forget($key);
            $duration = round((microtime(true) - $start) * 1000, 2);

            if ($result === 'ok') {
                return [
                    'status' => 'healthy',
                    'duration_ms' => $duration,
                    'driver' => config('cache.default'),
                ];
            }

            return [
                'status' => 'degraded',
                'error' => 'Cache read mismatch',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkDisk(): array
    {
        try {
            $path = storage_path('framework/cache');

            if (! is_dir($path)) {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Storage directory not found',
                ];
            }

            $freeSpace = disk_free_space($path);
            $totalSpace = disk_total_space($path);
            $usedPercentage = round((1 - $freeSpace / $totalSpace) * 100, 2);

            $status = $usedPercentage > 90 ? 'unhealthy' : ($usedPercentage > 80 ? 'degraded' : 'healthy');

            return [
                'status' => $status,
                'disk_used_percentage' => $usedPercentage,
                'disk_free_bytes' => $freeSpace,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}
