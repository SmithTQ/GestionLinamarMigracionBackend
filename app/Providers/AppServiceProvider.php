<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        if (app()->environment('testing') && (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        )) {
            throw new \LogicException('Las pruebas solo pueden ejecutarse con SQLite en memoria.');
        }

        RateLimiter::for('login', function (Request $request): Limit {
            $login = strtolower(trim((string) $request->input('login')));

            return Limit::perMinute(5)->by($login.'|'.$request->ip());
        });
        RateLimiter::for('public-form', function (Request $request): Limit {
            return Limit::perMinute(20)->by($request->route('publicKey').'|'.$request->ip());
        });
    }
}
