<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Account\DeviceSessionsController;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::get('settings/devices', [DeviceSessionsController::class, 'index'])->name('devices.index');
    Route::delete('settings/devices/others', [DeviceSessionsController::class, 'revokeOthers'])->name('devices.revoke-others');
    Route::delete('settings/devices', [DeviceSessionsController::class, 'revokeAll'])->name('devices.revoke-all');
    Route::delete('settings/devices/{deviceSession}', [DeviceSessionsController::class, 'revoke'])->whereNumber('deviceSession')->name('devices.revoke');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
