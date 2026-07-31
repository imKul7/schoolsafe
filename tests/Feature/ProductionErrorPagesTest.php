<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionErrorPagesTest extends TestCase
{
    public function test_custom_production_error_pages_render_safe_localized_responses(): void
    {
        config([
            'app.debug' => false,
        ]);

        $this->registerErrorPageTestRoute();

        $expectedPages = [
            403 => [
                'title' => 'Akses ditolak',

                'status' => 'Permintaan tidak diizinkan',
            ],

            404 => [
                'title' => 'Halaman tidak ditemukan',

                'status' => 'Alamat tidak tersedia',
            ],

            419 => [
                'title' => 'Sesi telah berakhir',

                'status' => 'Sesi keamanan kedaluwarsa',
            ],

            429 => [
                'title' => 'Terlalu banyak permintaan',

                'status' => 'Batas permintaan tercapai',
            ],

            500 => [
                'title' => 'Terjadi gangguan pada sistem',

                'status' => 'Kesalahan internal',
            ],

            503 => [
                'title' => 'Layanan sedang tidak tersedia',

                'status' => 'Pemeliharaan atau gangguan sementara',
            ],
        ];

        foreach (
            $expectedPages as $statusCode => $expectedPage
        ) {
            $response =
                $this->get(
                    sprintf(
                        '/__tests/production-error-pages/%d',
                        $statusCode,
                    ),
                );

            $response
                ->assertStatus(
                    $statusCode,
                )
                ->assertSeeText(
                    (string) $statusCode,
                )
                ->assertSeeText(
                    $expectedPage[
                        'title'
                    ],
                )
                ->assertSeeText(
                    $expectedPage[
                        'status'
                    ],
                )
                ->assertSeeText(
                    'SchoolSafe',
                )
                ->assertSee(
                    'content="noindex, nofollow, noarchive"',
                    false,
                )
                ->assertSee(
                    'content="no-referrer"',
                    false,
                );

            $content =
                (string) $response
                    ->getContent();

            foreach (
                $this->sensitiveDebugMarkers() as $sensitiveDebugMarker
            ) {
                $this->assertStringNotContainsString(
                    $sensitiveDebugMarker,
                    $content,
                    sprintf(
                        'Halaman error HTTP %d tidak boleh memuat penanda debug [%s].',
                        $statusCode,
                        $sensitiveDebugMarker,
                    ),
                );
            }
        }
    }

    public function test_error_page_templates_are_complete_and_self_contained(): void
    {
        $errorViewDirectory =
            resource_path(
                'views/errors',
            );

        $expectedFiles = [
            'layout.blade.php',
            '403.blade.php',
            '404.blade.php',
            '419.blade.php',
            '429.blade.php',
            '500.blade.php',
            '503.blade.php',
        ];

        foreach (
            $expectedFiles as $expectedFile
        ) {
            $absolutePath =
                $errorViewDirectory
                .DIRECTORY_SEPARATOR
                .$expectedFile;

            $this->assertFileExists(
                $absolutePath,
                sprintf(
                    'Template error wajib tidak ditemukan: [%s].',
                    $expectedFile,
                ),
            );

            $source =
                file_get_contents(
                    $absolutePath,
                );

            $this->assertIsString(
                $source,
            );

            $this->assertNotSame(
                '',
                trim(
                    $source,
                ),
                sprintf(
                    'Template error [%s] tidak boleh kosong.',
                    $expectedFile,
                ),
            );

            foreach (
                $this->sensitiveDebugMarkers() as $sensitiveDebugMarker
            ) {
                $this->assertStringNotContainsString(
                    $sensitiveDebugMarker,
                    $source,
                    sprintf(
                        'Template error [%s] tidak boleh memuat penanda sensitif [%s].',
                        $expectedFile,
                        $sensitiveDebugMarker,
                    ),
                );
            }
        }

        $layoutSource =
            file_get_contents(
                $errorViewDirectory
                .DIRECTORY_SEPARATOR
                .'layout.blade.php',
            );

        $this->assertIsString(
            $layoutSource,
        );

        /*
         * Halaman error kritis tidak boleh bergantung pada hasil build
         * frontend, autentikasi, session, atau named route.
         */
        $forbiddenRuntimeDependencies = [
            '@vite',
            'Vite::',
            'asset(',
            'mix(',
            'route(',
            'auth(',
            'session(',
            'Auth::',
        ];

        foreach (
            $forbiddenRuntimeDependencies as $forbiddenRuntimeDependency
        ) {
            $this->assertStringNotContainsString(
                $forbiddenRuntimeDependency,
                $layoutSource,
                sprintf(
                    'Layout error harus mandiri dan tidak boleh menggunakan [%s].',
                    $forbiddenRuntimeDependency,
                ),
            );
        }

        foreach (
            [
                403,
                404,
                419,
                429,
                500,
                503,
            ] as $statusCode
        ) {
            $pageSource =
                file_get_contents(
                    $errorViewDirectory
                    .DIRECTORY_SEPARATOR
                    .$statusCode
                    .'.blade.php',
                );

            $this->assertIsString(
                $pageSource,
            );

            $this->assertStringContainsString(
                "@extends('errors.layout')",
                $pageSource,
                sprintf(
                    'Template error [%d] wajib menggunakan layout error bersama.',
                    $statusCode,
                ),
            );

            $this->assertStringContainsString(
                sprintf(
                    "@section('code', '%d')",
                    $statusCode,
                ),
                $pageSource,
                sprintf(
                    'Template error [%d] wajib mendeklarasikan kode HTTP yang benar.',
                    $statusCode,
                ),
            );
        }
    }

    private function registerErrorPageTestRoute(): void
    {
        Route::get(
            '/__tests/production-error-pages/{statusCode}',
            static function (
                string $statusCode,
            ): void {
                abort(
                    (int) $statusCode,
                );
            },
        )
            ->whereIn(
                'statusCode',
                [
                    '403',
                    '404',
                    '419',
                    '429',
                    '500',
                    '503',
                ],
            );
    }

    /**
     * @return array<int, string>
     */
    private function sensitiveDebugMarkers(): array
    {
        return [
            'APP_KEY',
            'DB_PASSWORD',
            'SQLSTATE',
            'Stack trace',
            'stack trace',
            'vendor\\laravel',
            'vendor/laravel',
            'storage\\framework',
            'Whoops',
            'Illuminate\\',
            'Symfony\\Component',
            'C:\\xampp\\htdocs',
        ];
    }
}
