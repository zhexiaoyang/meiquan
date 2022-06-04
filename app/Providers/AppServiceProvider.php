<?php

namespace App\Providers;

use App\Libraries\DaDa\DaDa;
use App\Libraries\DingTalk\DingTalkRobotNotice;
use App\Libraries\Ele\Ele;
use App\Libraries\Fengniao\Fengniao;
use App\Libraries\MeiQuanDa\MeiQuanDa;
use App\Libraries\Meituan\MeiTuan;
use App\Libraries\MeiTuanKaiFang\MeiTuanKaiFang;
use App\Libraries\Shansong\Shansong;
use App\Libraries\Shunfeng\Shunfeng;
use App\Libraries\ShunfengService\ShunfengService;
use App\Libraries\TaoZi\TaoZi;
use App\Libraries\Uu\Uu;
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
        // 饿了么
        $this->app->singleton('uu', function () {
            $config = config('ps.uu');
            return new Uu($config);
        });
        // 饿了么
        $this->app->singleton('ele', function () {
            $config = config('ps.ele');
            return new Ele($config);
        });
        // 达达
        $this->app->singleton('dada', function () {
            $config = config('ps.dada');
            return new DaDa($config);
        });
        // 美全达跑腿
        $this->app->singleton('meiquanda', function () {
            $config = config('ps.meiquanda');
            return new MeiQuanDa($config);
        });
        // 美团跑腿
        $this->app->singleton('meituan', function () {
            $config = config('ps.meituan');
            return new MeiTuan($config);
        });
        // 蜂鸟配送
        $this->app->singleton('fengniao', function () {
            $config = config('ps.fengniao');
            return new Fengniao($config);
        });
        // 闪送配送
        $this->app->singleton('shansong', function () {
            $config = config('ps.shansong');
            return new Shansong($config);
        });
        // 药柜
        $this->app->singleton('yaogui', function () {
            $config = config('ps.yaogui');
            return new Yaogui($config);
        });
        // 药及特
        $this->app->singleton('yaojite', function () {
            $config = config('ps.yaojite');
            return new MeiTuan($config);
        });
        // 毛绒熊
        $this->app->singleton('mrx', function () {
            $config = config('ps.mrx');
            return new MeiTuan($config);
        });
        // 洁爱眼
        $this->app->singleton('jay', function () {
            $config = config('ps.jay');
            return new MeiTuan($config);
        });
        // 民康
        $this->app->singleton('minkang', function () {
            $config = config('ps.minkang');
            return new MeiTuan($config);
        });
        // 民康
        $this->app->singleton('jilin', function () {
            $config = config('ps.jilin');
            return new MeiTuan($config);
        });
        // 寝趣
        $this->app->singleton('qinqu', function () {
            $config = config('ps.qinqu');
            return new MeiTuan($config);
        });
        // 美全服务商
        $this->app->singleton('meiquan', function () {
            $config = config('ps.meiquan');
            return new MeiTuan($config);
        });
        // 顺丰
        $this->app->singleton('shunfeng', function () {
            $config = config('ps.shunfeng');
            return new Shunfeng($config);
        });
        // 顺丰
        $this->app->singleton('shunfengservice', function () {
            $config = config('ps.shunfengservice');
            return new ShunfengService($config);
        });
        // 桃子
        $this->app->singleton('taozi', function () {
            $config = config('ps.taozi');
            return new TaoZi($config);
        });
        // 桃子
        $this->app->singleton('taozi_xia', function () {
            $config = config('ps.taozi_xia');
            return new TaoZi($config);
        });

        // 钉钉通知
        $this->app->singleton('ding', function () {
            return new DingTalkRobotNotice("f9badd5f617a986f267295afded03ee6c936e5f9fd0e381593b02fce5543c323");
        });

        // 采购微信支付
        $this->app->singleton('pay.wechat_supplier', function () {
            return Pay::wechat(config('pay.wechat_supplier'));
        });

        $this->app->singleton('mtkf', function () {
            return new MeiTuanKaiFang(['app_id' => 106791, 'app_key' => 'lq1gtktmr3ofrjny', 'url' => 'https://api-open-cater.meituan.com/']);
            // return new MeiTuanKaiFang(['app_id' => 106792, 'app_key' => '36cvt5p8joq0jiiw', 'url' => 'https://api-open-cater.meituan.com/']);
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
