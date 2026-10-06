<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ColorPaletteService;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\MailtrapTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (app()->environment('production') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            URL::forceScheme('https');
        }
        Mail::extend('mailtrap', function ($config) {
            $factory = new MailtrapTransportFactory;

            $inboxId = config('services.mailtrap.inbox_id');
            $scheme = $inboxId ? 'mailtrap+sandbox' : 'mailtrap+api';
            $options = $inboxId ? ['inboxId' => $inboxId] : [];

            return $factory->create(new Dsn(
                $scheme,
                'default',
                config('services.mailtrap.secret'),
                null,
                null,
                $options
            ));
        });

        View::composer('*', function ($view) {
            try {
                if (Schema::hasTable('users')) {
                    $currentUser = Auth::user() ?? User::first();
                    $view->with('currentUser', $currentUser);
                }
                if (Schema::hasTable('tenants')) {
                    $currentTenant = TenantManager::getTenant();
                    $view->with('currentTenant', $currentTenant);

                    $tenantTheme = $currentTenant?->settings['theme'] ?? null;
                    $primaryColor = $tenantTheme['primary_color'] ?? '#4f46e5';
                    $accentColor = $tenantTheme['accent_color'] ?? '#6366f1';
                    $brandShades = $tenantTheme['shades'] ?? ColorPaletteService::generateShades($primaryColor);

                    $view->with('primaryColor', $primaryColor);
                    $view->with('accentColor', $accentColor);
                    $view->with('brandShades', $brandShades);
                }
            } catch (\Throwable $e) {
                $view->with('currentUser', null);
                $view->with('currentTenant', null);
                $view->with('primaryColor', '#4f46e5');
                $view->with('accentColor', '#6366f1');
                $view->with('brandShades', ColorPaletteService::generateShades('#4f46e5'));
            }
        });
    }
}
