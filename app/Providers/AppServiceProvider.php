<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (app()->runningInConsole()) return;
        if (app()->environment('production')) URL::forceScheme('https');
        if (session()->has('id')) {
            URL::defaults(['id' => session('id')]);
        }
    }
}
