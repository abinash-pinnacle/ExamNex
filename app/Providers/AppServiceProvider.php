<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
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
        // Timestamps are stored in UTC; display them in India time (IST).
        // Use ->ist() anywhere a date is shown to a user: $date?->ist()->format(...)
        Carbon::macro('ist', function () {
            /** @var Carbon $this */
            return $this->copy()->setTimezone('Asia/Kolkata');
        });
    }
}
