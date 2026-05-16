<?php

namespace Tests\Feature\Frontend;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersMiddlewareTest extends TestCase
{
    private const string TEST_ROUTE = '/_security-headers-test';

    private string $hotFilePath;

    private bool $hotFileExistedBeforeTest = false;

    private string $hotFileOriginalContents = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotFilePath = public_path('hot');
        $this->hotFileExistedBeforeTest = is_file($this->hotFilePath);

        if ($this->hotFileExistedBeforeTest) {
            $this->hotFileOriginalContents = (string) file_get_contents($this->hotFilePath);
        }

        Route::middleware('web')->get(self::TEST_ROUTE, static fn () => response('ok'));
    }

    protected function tearDown(): void
    {
        if ($this->hotFileExistedBeforeTest) {
            file_put_contents($this->hotFilePath, $this->hotFileOriginalContents);
        } elseif (is_file($this->hotFilePath)) {
            unlink($this->hotFilePath);
        }

        parent::tearDown();
    }

    public function test_content_security_policy_includes_vite_sources_when_hot_file_exists(): void
    {
        file_put_contents($this->hotFilePath, 'http://[::1]:5173');

        $response = $this->get(self::TEST_ROUTE);

        $contentSecurityPolicy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval' http:", $contentSecurityPolicy);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net http:", $contentSecurityPolicy);
        $this->assertStringContainsString('connect-src', $contentSecurityPolicy);
        $this->assertStringContainsString('ws:', $contentSecurityPolicy);
        $this->assertStringNotContainsString('[::1]:5173', $contentSecurityPolicy);
    }

    public function test_content_security_policy_excludes_vite_sources_when_hot_file_is_missing(): void
    {
        if (is_file($this->hotFilePath)) {
            unlink($this->hotFilePath);
        }

        $response = $this->get(self::TEST_ROUTE);

        $contentSecurityPolicy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval' http:", $contentSecurityPolicy);
        $this->assertStringNotContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net http:", $contentSecurityPolicy);
        $this->assertStringNotContainsString('[::1]:5173', $contentSecurityPolicy);
    }
}
