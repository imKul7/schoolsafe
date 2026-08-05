<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GateFaceVerificationController;
use App\Http\Controllers\GatePickupEventController;
use App\Http\Controllers\PickupPersonController;
use App\Http\Controllers\StudentController;
use App\Http\Middleware\PreventSensitiveResponseCaching;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/*
|--------------------------------------------------------------------------
| Batas Parameter ID
|--------------------------------------------------------------------------
|
| Seluruh parameter ID dibatasi ke integer positif yang masih berada dalam
| rentang PHP_INT_MAX pada lingkungan 64-bit. Pembatasan ini mencegah nilai
| digit-only yang terlalu besar lolos ke controller bertipe int dan memicu
| TypeError dengan respons 500.
|
*/

$positiveIntegerRoutePattern =
    '(?:'
    .'[1-9][0-9]{0,17}'
    .'|[1-8][0-9]{18}'
    .'|9[01][0-9]{17}'
    .'|92[01][0-9]{16}'
    .'|922[0-2][0-9]{15}'
    .'|9223[0-2][0-9]{14}'
    .'|92233[0-6][0-9]{13}'
    .'|922337[01][0-9]{12}'
    .'|92233720[0-2][0-9]{10}'
    .'|922337203[0-5][0-9]{9}'
    .'|9223372036[0-7][0-9]{8}'
    .'|92233720368[0-4][0-9]{7}'
    .'|922337203685[0-3][0-9]{6}'
    .'|9223372036854[0-6][0-9]{5}'
    .'|92233720368547[0-6][0-9]{4}'
    .'|922337203685477[0-4][0-9]{3}'
    .'|9223372036854775[0-7][0-9]{2}'
    .'|922337203685477580[0-7]'
    .')';

foreach (
    [
        'pickupEvent',
        'pickupEventStudent',
        'student',
        'pickupPerson',
        'pickupPersonId',
    ] as $routeParameter
) {
    Route::pattern(
        $routeParameter,
        $positiveIntegerRoutePattern,
    );
}

/*
|--------------------------------------------------------------------------
| Halaman Utama
|--------------------------------------------------------------------------
*/

Route::get(
    '/',
    function (): Response|RedirectResponse {
        if (Auth::check()) {
            return redirect()
                ->route('dashboard');
        }

        return Inertia::render(
            'welcome',
        );
    },
)->name('home');

/*
|--------------------------------------------------------------------------
| Halaman yang Memerlukan Login
|--------------------------------------------------------------------------
*/

Route::middleware('auth')
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/dashboard',
            DashboardController::class,
        )
            ->middleware(
                PreventSensitiveResponseCaching::class,
            )
            ->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Modul Gerbang
        |--------------------------------------------------------------------------
        */

        Route::prefix('gate')
            ->name('gate.')
            ->middleware(
                'role:gate,'
                    .User::ROLE_SCHOOL_ADMIN
                    .','
                    .User::ROLE_GATE_OFFICER,
            )
            ->group(function (): void {
                /*
                |--------------------------------------------------------------------------
                | Verifikasi Wajah
                |--------------------------------------------------------------------------
                */

                Route::controller(
                    GateFaceVerificationController::class,
                )
                    ->prefix('face-verification')
                    ->name('face-verification.')
                    ->group(function (): void {
                        /*
                         * Halaman utama kamera verifikasi.
                         */
                        Route::get(
                            '/',
                            'index',
                        )->name('index');

                        /*
                         * Membuat challenge liveness satu kali.
                         */
                        Route::post(
                            '/challenge',
                            'challenge',
                        )
                            ->middleware(
                                'throttle:20,1',
                            )
                            ->name('challenge');

                        /*
                         * Memproses pencocokan wajah penjemput.
                         */
                        Route::post(
                            '/',
                            'verify',
                        )
                            ->middleware(
                                'throttle:30,1',
                            )
                            ->name('verify');
                    });

                /*
                |--------------------------------------------------------------------------
                | Transaksi Penjemputan
                |--------------------------------------------------------------------------
                */

                Route::controller(
                    GatePickupEventController::class,
                )
                    ->prefix('pickup-events')
                    ->name('pickup-events.')
                    ->middleware(
                        PreventSensitiveResponseCaching::class,
                    )
                    ->group(function (): void {
                        /*
                        |--------------------------------------------------------------------------
                        | Riwayat Gerbang
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            '/',
                            'index',
                        )->name('index');

                        /*
                        |--------------------------------------------------------------------------
                        | Konfirmasi Penjemputan
                        |--------------------------------------------------------------------------
                        |
                        | Endpoint menerima hasil verifikasi wajah yang berhasil,
                        | daftar siswa yang dipilih, dan UUID idempotency.
                        |
                        */

                        Route::post(
                            '/',
                            'store',
                        )
                            ->middleware(
                                'throttle:30,1',
                            )
                            ->name('store');

                        /*
                        |--------------------------------------------------------------------------
                        | Pembatalan Siswa
                        |--------------------------------------------------------------------------
                        |
                        | Route paling spesifik ditempatkan sebelum route detail.
                        |
                        */

                        Route::patch(
                            '/{pickupEvent}/students/{pickupEventStudent}/cancel',
                            'cancelStudent',
                        )
                            ->middleware(
                                'throttle:20,1',
                            )
                            ->name(
                                'students.cancel',
                            );

                        /*
                        |--------------------------------------------------------------------------
                        | Pembatalan Transaksi
                        |--------------------------------------------------------------------------
                        */

                        Route::patch(
                            '/{pickupEvent}/cancel',
                            'cancel',
                        )
                            ->middleware(
                                'throttle:20,1',
                            )
                            ->name('cancel');

                        /*
                        |--------------------------------------------------------------------------
                        | Detail Transaksi
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            '/{pickupEvent}',
                            'show',
                        )
                            ->middleware(
                                'throttle:60,1',
                            )
                            ->name('show');
                    });
            });

        /*
        |--------------------------------------------------------------------------
        | Data Siswa
        |--------------------------------------------------------------------------
        */

        Route::controller(
            StudentController::class,
        )
            ->prefix('students')
            ->name('students.')
            ->middleware(
                'role:students,'
                    .User::ROLE_SCHOOL_ADMIN
                    .','
                    .User::ROLE_TEACHER,
            )
            ->group(function (): void {
                /*
                |--------------------------------------------------------------------------
                | Daftar dan Tambah Siswa
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/',
                    'index',
                )->name('index');

                Route::get(
                    '/create',
                    'create',
                )->name('create');

                Route::post(
                    '/',
                    'store',
                )->name('store');

                /*
                |--------------------------------------------------------------------------
                | Detail dan Pengelolaan Siswa
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/{student}',
                    'show',
                )->name('show');

                Route::get(
                    '/{student}/edit',
                    'edit',
                )->name('edit');

                Route::put(
                    '/{student}',
                    'update',
                )->name('update');

                Route::patch(
                    '/{student}/toggle-status',
                    'toggleStatus',
                )->name(
                    'toggle-status',
                );

                Route::delete(
                    '/{student}',
                    'destroy',
                )->name('destroy');
            });

        /*
        |--------------------------------------------------------------------------
        | Data Penjemput
        |--------------------------------------------------------------------------
        */

        Route::controller(
            PickupPersonController::class,
        )
            ->prefix('pickup-persons')
            ->name('pickup-persons.')
            ->middleware(
                'role:pickup-persons,'
                    .User::ROLE_SCHOOL_ADMIN
                    .','
                    .User::ROLE_GATE_OFFICER
                    .','
                    .User::ROLE_TEACHER,
            )
            ->group(function (): void {
                /*
                |--------------------------------------------------------------------------
                | Daftar dan Tambah Penjemput
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/',
                    'index',
                )->name('index');

                Route::get(
                    '/create',
                    'create',
                )->name('create');

                Route::post(
                    '/',
                    'store',
                )->name('store');

                /*
                |--------------------------------------------------------------------------
                | Arsip Penjemput
                |--------------------------------------------------------------------------
                |
                | Route statis ditempatkan sebelum /{pickupPerson} agar kata
                | "archive" tidak pernah dianggap sebagai identifier penjemput.
                |
                */

                Route::get(
                    '/archive',
                    'archive',
                )->name('archive');

                Route::patch(
                    '/archive/{pickupPersonId}/restore',
                    'restore',
                )->name('restore');

                Route::delete(
                    '/archive/{pickupPersonId}/force-delete',
                    'forceDelete',
                )->name(
                    'force-delete',
                );

                /*
                |--------------------------------------------------------------------------
                | Foto Penjemput
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/{pickupPerson}/photo',
                    'uploadPhoto',
                )->name(
                    'photo.store',
                );

                Route::delete(
                    '/{pickupPerson}/photo',
                    'deletePhoto',
                )->name(
                    'photo.destroy',
                );

                /*
                |--------------------------------------------------------------------------
                | Registrasi Biometrik Wajah
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/{pickupPerson}/face/register',
                    'registerFace',
                )->name(
                    'face.register',
                );

                Route::delete(
                    '/{pickupPerson}/face',
                    'revokeFace',
                )->name(
                    'face.revoke',
                );

                /*
                |--------------------------------------------------------------------------
                | Detail dan Pengelolaan Penjemput
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/{pickupPerson}',
                    'show',
                )->name('show');

                Route::get(
                    '/{pickupPerson}/edit',
                    'edit',
                )->name('edit');

                Route::put(
                    '/{pickupPerson}',
                    'update',
                )->name('update');

                Route::patch(
                    '/{pickupPerson}/toggle-status',
                    'toggleStatus',
                )->name(
                    'toggle-status',
                );

                Route::delete(
                    '/{pickupPerson}',
                    'destroy',
                )->name('destroy');
            });
    });

/*
|--------------------------------------------------------------------------
| Route Pengaturan dan Autentikasi
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
require __DIR__.'/profile.php';
require __DIR__.'/auth.php';
