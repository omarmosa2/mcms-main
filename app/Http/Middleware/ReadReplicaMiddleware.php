<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReadReplicaMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isReadReplicaEnabled()) {
            return $next($request);
        }

        if ($this->isWriteRequest($request)) {
            DB::connection()->pdo('write');
        }

        $response = $next($request);

        if ($this->isWriteRequest($request) || $this->hasModifyingSession($request)) {
            DB::connection()->pdo('write');
        }

        return $response;
    }

    private function isReadReplicaEnabled(): bool
    {
        return config('database.replica.enabled', false);
    }

    private function isWriteRequest(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    private function hasModifyingSession(Request $request): bool
    {
        return $request->session()->has('last_write_at')
            && now()->diffInSeconds($request->session()->get('last_write_at')) < 5;
    }
}
