<?php

namespace App\Providers;

use App\Libraries\DingTalk\DingTalkRobotNotice;
use App\Libraries\Fengniao\Fengniao;
use App\Libraries\Meituan\MeiTuan;
use App\Libraries\Shansong\Shansong;
use App\Libraries\Yaogui\Yaogui;
use Illuminate\Support\ServiceProvider;
use Yansongda\Pay\Pay;

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
        $this->app->singleton('yaogui', function () {
            $config = config('ps.yaogui');
            return new Yaogui($config);
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
        $this->app->singleton('minkang', function () {
            $config = config('ps.minkang');
            return new MeiTuan($config);
        });

        // 钉钉通知
        $this->app->singleton('ding', function () {
            return new DingTalkRobotNotice("f9badd5f617a986f267295afded03ee6c936e5f9fd0e381593b02fce5543c323");
        });

        // 采购微信支付
        $this->app->singleton('pay.wechat_supplier', function () {
            return Pay::wechat(config('pay.wechat_supplier'));
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
