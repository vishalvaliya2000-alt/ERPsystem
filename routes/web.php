<?php

use App\Http\Controllers\Central\HomeController;
use App\Http\Controllers\Central\RegistrationController;
use App\Http\Controllers\Central\SuperAdminController;
use App\Http\Controllers\Central\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Platform Routes (erpsaas.in / erpsaas.localhost)
|--------------------------------------------------------------------------
| These routes handle public marketing, tenant registration, workspace lookup,
| and central super-admin management.
*/

$centralDomains = config('tenancy.central_domains') ?: [
    'erpsaas.in',
    'localhost',
    '127.0.0.1',
    'erpsaas.localhost',
];

foreach ($centralDomains as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('central.home');

        Route::get('/register', [RegistrationController::class, 'show'])->name('central.register');
        Route::post('/register', [RegistrationController::class, 'store'])->name('central.register.store')->middleware('throttle:10,1');
        Route::post('/api/check-subdomain', [RegistrationController::class, 'checkSubdomain'])->name('central.check-subdomain')->middleware('throttle:60,1');

        Route::get('/find-workspace', [WorkspaceController::class, 'show'])->name('central.find-workspace');
        Route::post('/find-workspace', [WorkspaceController::class, 'lookup'])->name('central.find-workspace.lookup');

        Route::get('/login', function () {
            return redirect()->route('central.find-workspace');
        })->name('central.login');

        // Central Super-Admin
        Route::prefix('super-admin')->group(function () {
            Route::get('/', [SuperAdminController::class, 'dashboard'])->name('central.superadmin.dashboard');
            Route::get('/tenants', [SuperAdminController::class, 'tenants'])->name('central.superadmin.tenants');
            Route::post('/tenants/{id}/toggle', [SuperAdminController::class, 'toggleStatus'])->name('central.superadmin.tenants.toggle');
            Route::post('/tenants/{id}/impersonate', [SuperAdminController::class, 'impersonate'])->name('central.superadmin.tenants.impersonate');
        });
    });
}
