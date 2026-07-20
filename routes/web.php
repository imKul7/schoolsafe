<?php

declare(strict_types=1);

use App\Http\Controllers\PickupPersonController;
use App\Http\Controllers\StudentController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

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

        return Inertia::render('welcome');
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
            fn (): Response =>
                Inertia::render('dashboard'),
        )->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Data Siswa
        |--------------------------------------------------------------------------
        */

        Route::controller(StudentController::class)
            ->prefix('students')
            ->name('students.')
            ->group(function (): void {
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

                Route::get(
                    '/{student}',
                    'show',
                )
                    ->whereNumber('student')
                    ->name('show');

                Route::get(
                    '/{student}/edit',
                    'edit',
                )
                    ->whereNumber('student')
                    ->name('edit');

                Route::put(
                    '/{student}',
                    'update',
                )
                    ->whereNumber('student')
                    ->name('update');

                Route::patch(
                    '/{student}/toggle-status',
                    'toggleStatus',
                )
                    ->whereNumber('student')
                    ->name('toggle-status');

                Route::delete(
                    '/{student}',
                    'destroy',
                )
                    ->whereNumber('student')
                    ->name('destroy');
            });

        /*
        |--------------------------------------------------------------------------
        | Data Penjemput
        |--------------------------------------------------------------------------
        */

        Route::controller(PickupPersonController::class)
            ->prefix('pickup-persons')
            ->name('pickup-persons.')
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
                | Route statis diletakkan sebelum /{pickupPerson}.
                |
                */

                Route::get(
                    '/archive',
                    'archive',
                )->name('archive');

                Route::patch(
                    '/archive/{pickupPersonId}/restore',
                    'restore',
                )
                    ->whereNumber('pickupPersonId')
                    ->name('restore');

                Route::delete(
                    '/archive/{pickupPersonId}/force-delete',
                    'forceDelete',
                )
                    ->whereNumber('pickupPersonId')
                    ->name('force-delete');

                /*
                |--------------------------------------------------------------------------
                | Foto Penjemput
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/{pickupPerson}/photo',
                    'uploadPhoto',
                )
                    ->whereNumber('pickupPerson')
                    ->name('photo.store');

                Route::delete(
                    '/{pickupPerson}/photo',
                    'deletePhoto',
                )
                    ->whereNumber('pickupPerson')
                    ->name('photo.destroy');

                /*
                |--------------------------------------------------------------------------
                | Detail dan Pengelolaan Penjemput
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/{pickupPerson}',
                    'show',
                )
                    ->whereNumber('pickupPerson')
                    ->name('show');

                Route::get(
                    '/{pickupPerson}/edit',
                    'edit',
                )
                    ->whereNumber('pickupPerson')
                    ->name('edit');

                Route::put(
                    '/{pickupPerson}',
                    'update',
                )
                    ->whereNumber('pickupPerson')
                    ->name('update');

                Route::patch(
                    '/{pickupPerson}/toggle-status',
                    'toggleStatus',
                )
                    ->whereNumber('pickupPerson')
                    ->name('toggle-status');

                Route::delete(
                    '/{pickupPerson}',
                    'destroy',
                )
                    ->whereNumber('pickupPerson')
                    ->name('destroy');
            });
    });

/*
|--------------------------------------------------------------------------
| Route Pengaturan dan Autentikasi
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';