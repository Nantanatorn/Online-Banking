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
        // จำกัดการลอง login 5 ครั้ง/นาที ต่อ (userid + IP) กันเดา PIN
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('userid') . '|' . $request->ip());
        });
    }
}
