<?php

namespace App\Providers;

use App\Libraries\Fengniao\Fengniao;
use App\Libraries\Meituan\MeiTuan;
use App\Libraries\Shansong\Shansong;
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
        $this->app->singleton('fengniao', function () {
            $config = config('ps.fengniao');
            return new Fengniao($config);
        });
        $this->app->singleton('shansong', function () {
            $config = config('ps.shansong');
            return new Shansong($config);
        });

        $this->app->singleton('yaojite', function () {
            $config = config('ps.yaojite');
            return new MeiTuan($config);
        });
        $this->app->singleton('mrx', function () {
            $config = config('ps.mrx');
            return new MeiTuan($config);
        });
        $this->app->singleton('jay', function () {
            $config = config('ps.jay');
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
