<?php

declare(strict_types=1);

use App\Http\Controllers\AccountProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/profile',
        [AccountProfileController::class, 'show'],
    )->name('account-profile.show');

    Route::post(
        '/profile/photo',
        [AccountProfileController::class, 'storePhoto'],
    )->name('account-profile.photo.store');

    Route::delete(
        '/profile/photo',
        [AccountProfileController::class, 'destroyPhoto'],
    )->name('account-profile.photo.destroy');
});
