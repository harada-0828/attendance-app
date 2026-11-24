<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider; // ← ここを追加してください！

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
        Carbon::setLocale('ja');
    }
}
