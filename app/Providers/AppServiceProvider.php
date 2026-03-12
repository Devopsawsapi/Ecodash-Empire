<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
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
     *
     * Schema::defaultStringLength(191) fixes the "key too long" error
     * on MySQL versions older than 5.7.7 / MariaDB older than 10.2.2.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
