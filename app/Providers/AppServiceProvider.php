<?php

namespace App\Providers;

use App\Libraries\Fengniao\Fengniao;
use App\Libraries\Meituan\MeiTuan;
use App\Libraries\Shansong\Shansong;
// use App\Libraries\Yaogui\Yaogui;
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
        // $this->app->singleton('yaogui', function () {
        //     $config = config('ps.yaogui');
        //     return new Yaogui($config);
        // });

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
        $this->app->singleton('minkang', function () {
            $config = config('ps.minkang');
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
