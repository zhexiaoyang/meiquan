<?php

namespace App\Listeners;

use App\Events\OrderCreate;
use App\Models\WmOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GetRpPicture implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  OrderCreate  $event
     * @return void
     */
    public function handle(OrderCreate $event)
    {
        // 从事件对象中取出对应的订单
        $order = $event->getOrder();
        if (!$order->is_prescription) {
            return ;
        }
        $picture_url = '';
        if ($order->platform === 1) {
            // 美团外卖
            if ($order->from_type !== 4 && $order->from_type !== 31) {
                return ;
            }
            if ($order->from_type === 4) {
                $meituan = app('minkang');
            } else {
                $meituan = app('meiquan');
            }

            $picture_res = $meituan->rp_picture_list($order->app_poi_code, $order->order_id, $order->from_type);
            // \Log::info('aaa', [$picture_res]);
            if (!empty($picture_res['data'][0]['rp_url_list'][0])) {
                $picture_url = $picture_res['data'][0]['rp_url_list'][0];
            }
            $name = "{$order->order_id}.png";
        } elseif ($order->platform === 2) {
            // 饿了么
            $ele = app('ele');
            $picture_res = $ele->rpPictureList($order->order_id);
            \Log::info('aa', [$picture_res]);
            if (!empty($picture_res['body']['data'][0])) {
                $picture_url = $picture_res['body']['data'][0];
            }
            $name = "{$order->order_id}.pdf";
        }

        if ($picture_url && $name) {
            $oss = app('oss');
            $dir = config('aliyun.rp_dir').date('Ym/d/');
            $res = $oss->putObject(config('aliyun.bucket'), $dir.$name, file_get_contents($picture_url));
            if (!empty($res['info'])) {
                $url = 'https://img.meiquanda.com/' . $dir . $name;
                WmOrder::where('id', $order->id)->update([
                    'rp_picture' => $url
                ]);
            }
        } else {
            // TODO2, 暂未获取到处方信息
            \Log::info("获取处方图片监听，暂未获取到处方订单。{$order->order_id}");
        }
    }
}
