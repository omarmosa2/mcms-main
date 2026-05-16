<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $this->buildContentSecurityPolicy());

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function buildContentSecurityPolicy(): string
    {
        [$viteAssetSources, $viteConnectSources] = $this->viteDevelopmentSources();

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "img-src 'self' data: blob:",
            "font-src 'self' https://fonts.bunny.net https://fonts.gstatic.com data:",
            'style-src '.$this->implodeSources([
                "'self'",
                "'unsafe-inline'",
                'https://fonts.bunny.net',
                ...$viteAssetSources,
            ]),
            'script-src '.$this->implodeSources([
                "'self'",
                "'unsafe-inline'",
                "'unsafe-eval'",
                ...$viteAssetSources,
            ]),
            'connect-src '.$this->implodeSources([
                "'self'",
                'https:',
                'http:',
                'ws:',
                'wss:',
                ...$viteConnectSources,
            ]),
        ]);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function viteDevelopmentSources(): array
    {
        if (! app()->environment(['local', 'testing'])) {
            return [[], []];
        }

        $hotFilePath = public_path('hot');

        if (! is_file($hotFilePath)) {
            return [[], []];
        }

        $hotServerUrl = trim((string) file_get_contents($hotFilePath));

        if ($hotServerUrl === '') {
            return [[], []];
        }

        $parsedHotServerUrl = parse_url($hotServerUrl);

        if (! is_array($parsedHotServerUrl)) {
            return [[], []];
        }

        $scheme = ($parsedHotServerUrl['scheme'] ?? 'http') === 'https' ? 'https' : 'http';
        $socketScheme = $scheme === 'https' ? 'wss' : 'ws';
        $host = isset($parsedHotServerUrl['host']) ? (string) $parsedHotServerUrl['host'] : 'localhost';
        $port = isset($parsedHotServerUrl['port']) ? (int) $parsedHotServerUrl['port'] : 5173;

        $canonicalHost = $this->normalizeHostForCsp($host);

        $canonicalHosts = [
            'localhost',
            '127.0.0.1',
        ];

        if ($canonicalHost !== null) {
            $canonicalHosts[] = $canonicalHost;
        }

        $assetSources = [];
        $connectSources = [];

        foreach ($canonicalHosts as $canonicalHost) {
            $assetSources[] = "{$scheme}://{$canonicalHost}:{$port}";
            $connectSources[] = "{$scheme}://{$canonicalHost}:{$port}";
            $connectSources[] = "{$socketScheme}://{$canonicalHost}:{$port}";
        }

        return [
            $this->uniqueSources([
                "{$scheme}:",
                ...$assetSources,
            ]),
            $this->uniqueSources([
                "{$scheme}:",
                "{$socketScheme}:",
                ...$connectSources,
            ]),
        ];
    }

    private function normalizeHostForCsp(string $host): ?string
    {
        $trimmedHost = trim($host);

        // Browsers reject IPv6 host literals in CSP host-source syntax in practice.
        // We rely on scheme-source (`http:` / `ws:`) for local Vite over IPv6.
        if (str_contains($trimmedHost, ':')) {
            return null;
        }

        return $trimmedHost;
    }

    /**
     * @param  array<int, string>  $sources
     */
    private function implodeSources(array $sources): string
    {
        return implode(' ', $this->uniqueSources($sources));
    }

    /**
     * @param  array<int, string>  $sources
     * @return array<int, string>
     */
    private function uniqueSources(array $sources): array
    {
        return array_values(array_unique(array_filter($sources, static fn ($source) => $source !== null && $source !== '')));
    }
}
