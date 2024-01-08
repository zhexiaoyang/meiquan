<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class TakeoutShopController extends Controller
{

    public function update_shipping(Request $request)
    {
        if (!$shop = Shop::find($request->get('shop_id', 0))) {
            return $this->error('门店不存在');
        }

        $mt_start = $request->get('mt_start');
        $mt_end = $request->get('mt_end');
        $ele_start = $request->get('ele_start');
        $ele_end = $request->get('ele_end');

        $mt_mes = '';
        $ele_mes = '饿了么不支持修改';
        $update = false;

        if ($shop->waimai_mt) {
            $mt_mes = '美团未更新';
            if ($mt_start && $mt_end) {
                if ($shop->mt_shipping_time != $mt_start . '-' . $mt_end) {
                    if ($shop->meituan_bind_platform == 4) {
                        $meituan = app('minkang');
                    } else {
                        $meituan = app('meiquan');
                    }
                    $mt_res = $meituan->shippingTimeUpdate($shop->waimai_mt, $mt_start . '-' . $mt_end, $shop->meituan_bind_platform == 31);
                    if ($mt_res['data'] == 'ok') {
                        $update = true;
                        $shop->mt_shipping_time = $mt_start . '-' . $mt_end;
                        $mt_mes = '美团更新成功';
                    } else {
                        $mt_mes = '美团更新失败';
                    }
                }
            }
        }

        // if ($shop->waimai_ele) {
        //     $ele_mes = '饿了么未更新';
            // if ($shop->ele_shipping_time && $ele_start && $ele_end) {
            //     if ($shop->ele_shipping_time != $ele_start . '-' . $ele_end) {
            //         $ele = app('ele');
            //         $ele_res = $ele->shippingtimeUpdate($shop->waimai_ele, $ele_start, $ele_end);
            //         if ($ele_res['body']['errno'] == 0) {
            //             $update = true;
            //             $shop->ele_shipping_time = $ele_start . '-' . $ele_end;
            //             $ele_mes = '饿了么更新成功';
            //         } else {
            //             $ele_mes = isset($ele_res['body']['error']) ? '饿了么' . $ele_res['body']['error'] : '饿了么更新失败';
            //         }
                // }
            // }
        // }

        if ($update) {
            $shop->save();
        }

        return $this->success([],$mt_mes . '|' . $ele_mes);
    }

    public function update_meituan_status(Request $request)
    {
        if (!$shop = Shop::find($request->get('shop_id', 0))) {
            return $this->error('门店不存在');
        }

        if ($shop->meituan_bind_platform == 4) {
            $meituan = app('minkang');
        } else {
            $meituan = app('meiquan');
        }
        if ($shop->mt_open == 1) {
            $mt_res = $meituan->shopClose($shop->waimai_mt, $shop->meituan_bind_platform == 31);
        } elseif ($shop->mt_open == 3) {
            $mt_res = $meituan->shopOpen($shop->waimai_mt, $shop->meituan_bind_platform == 31);
        }

        if ($mt_res['data'] == 'ok') {
            $shop->mt_open = $shop->mt_open == 1 ? 3 : 1;
            $shop->save();
        } else {
            return $this->error('操作失败');
        }

        return $this->success();
    }

    public function update_ele_status(Request $request)
    {
        if (!$shop = Shop::find($request->get('shop_id', 0))) {
            return $this->error('门店不存在');
        }

        $ele = app('ele');
        $ele_res = null;

        if ($shop->ele_open == 3) {
            $ele_res = $ele->shopClose($shop->waimai_ele);
        } elseif ($shop->ele_open == 1) {
            $ele_res = $ele->shopOpen($shop->waimai_ele);
        }

        if (isset($ele_res['body']['errno']) && $ele_res['body']['errno'] == 0) {
            $shop->ele_open = $shop->ele_open == 1 ? 3 : 1;
            $shop->save();
        } else {
            \Log::info('update_ele_status操作失败', [$shop->ele_open, $ele_res]);
            return $this->error('操作失败');
        }

        return $this->success();
    }
}
