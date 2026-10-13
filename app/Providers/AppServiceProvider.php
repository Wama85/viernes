<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Auditoria\Models\Auditoria;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
{
    Event::listen(Login::class, function ($evento) {
        Auditoria::registrar('LOGIN', $evento->user, null, null, $evento->user->getKey());
    });

    Event::listen(Logout::class, function ($evento) {
        if ($evento->user) {
            Auditoria::registrar('LOGOUT', $evento->user, null, null, $evento->user->getKey());
        }
    });
}
}
