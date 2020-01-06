<?php

namespace App\Providers;

use App\Libraries\Meituan\MeiTuan;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('meituan', function () {
            $config = config('ps.meituan');
            return new MeiTuan($config);
        });
        $this->app->singleton('yaojite', function () {
            $config = config('ps.yaojite');
            return new MeiTuan($config);
        });
        $this->app->singleton('meiquan', function () {
            $config = config('ps.meiquan');
            return new MeiTuan($config);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
