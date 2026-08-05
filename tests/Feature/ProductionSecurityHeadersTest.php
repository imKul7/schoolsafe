<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('applies production security headers to web responses', function (): void {
    config()->set('app.env', 'production');

    Route::middleware('web')->group(function (): void {
        Route::get('/production-security-headers-test', function (): string {
            return 'ok';
        });
    });

    $this
        ->get('/production-security-headers-test')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
