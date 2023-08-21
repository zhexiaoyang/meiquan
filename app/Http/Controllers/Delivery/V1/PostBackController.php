<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\Shop;
use App\Models\WmOrder;
use Illuminate\Http\Request;

class PostBackController extends Controller
{
    public function shops(Request $request)
    {
        $user = $request->user();
        $list = [];
        $all = 0;
        $ok = 0;
        $not_ok = 0;
        $shops = Shop::select('id', 'shop_name', 'wm_shop_name', 'meituan_bind_platform','waimai_mt')
            ->where('waimai_mt', '<>', '')->where('user_id', $user->id)->get();

        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $all++;
                $ok++;
                // $not_ok++;
                $list[] = [
                    'id' => $shop->id,
                    'name' => $shop->wm_shop_name ?: $shop->shop_name,
                    'bind_type' => $shop->meituan_bind_platform,
                    'bind_text' => config('ps.meituan_bind_platform')[$shop->meituan_bind_platform],
                    'today_num' => 0,
                    'yesterday_num' => 0,
                ];
            }
        }
        $result = [
            'all' => $all,
            'ok' => $ok,
            'not_ok' => $not_ok,
            'list' => $list
        ];
        return $this->success($result);
    }

    public function order_statistics(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('门店不存在');
        }
        $user = $request->user();
        if (!Shop::where('user_id', $user->id)->find($shop_id)) {
            return $this->error('门店不存在!');
        }
        $shop_id = 2597;
        $result = [
            'wait' => WmOrder::select('id')->where('shop_id', $shop_id)->where('created_at', '>=', date("Y-m-d"))->where('ignore', 0)->where('post_back', 0)->count(),
            'uploading' => 0,
            'fail' => WmOrder::select('id')->where('shop_id', $shop_id)->where('created_at', '>=', date("Y-m-d"))->where('ignore', 1)->count(),
            'success' => WmOrder::select('id')->where('shop_id', $shop_id)->where('created_at', '>=', date("Y-m-d"))->where('ignore', 0)->where('post_back', 1)->count(),
        ];
        return $this->success($result);
    }

    public function orders(Request $request)
    {
        if (!$type = (int) $request->get('type')) {
            return $this->error('类型不能为空');
        }
        if (!in_array($type, [1,2,3,4])) {
            return $this->error('类型错误');
        }
        if ($type == 2) {
            return $this->success();
        }
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('门店不存在');
        }
        $user = $request->user();
        if (!Shop::where('user_id', $user->id)->find($shop_id)) {
            return $this->error('门店不存在!');
        }
        $shop_id = 2597;
        $page_size = $request->get('page_size', 10);

        $query = WmOrder::with(['deliveries' => function ($query) {
            $query->select('id', 'order_id', 'wm_id', 'three_order_no', 'status', 'track', 'platform as logistic_type',
                'money', 'updated_at','delivery_name','delivery_phone');
            $query->with(['tracks' => function ($query) {
                $query->select('id', 'delivery_id', 'status', 'status_des', 'description', 'created_at');
            }]);
        }, 'running' => function ($query) {
            $query->select('id', 'wm_id','courier_name', 'courier_phone','status','platform as logistic_type');
        }])->select('id','order_id','shop_id','wm_shop_name as wm_poi_name','recipient_name as receiver_name','recipient_phone as receiver_phone','recipient_address as receiver_address',
            'caution','day_seq','platform','status','created_at','delivery_time', 'estimate_arrival_time')
            ->where('shop_id', $shop_id)
            ->where('status', '<=', 18)
            ->where('created_at', '>', date("Y-m-d"));

        if ($type === 1) {
            $query->where('ignore', 0)->where('post_back', 0);
        } elseif ($type === 2) {
        } elseif ($type === 3) {
            $query->where('ignore', 1);
        } elseif ($type === 4) {
            $query->where('post_back', 1);
        }

        $orders = $query->paginate($page_size);
        if (!empty($orders)) {
            foreach ($orders as $order) {
                // 预约单
                $order->delivery_time = 0;
                // 收货尾号
                $order->receiver_phone_end = '';
                // 订单标题
                $order->title = Order::setAppSearchOrderTitle($order->delivery_time ?? 0, $order->estimate_arrival_time ?? 0, $order->running);
                // 状态描述
                $order->status_title = '';
                $order->status_description = '';
                if (in_array($order->running->status, [20,50,60,70])) {
                    $order->status_title = OrderDelivery::$delivery_status_order_list_title_map[$order->running->status] ?? '其它';
                    if ($order->running->status === 20) {
                        $order->status_description = '下单成功';
                    } else {
                        $status_description_platform = OrderDelivery::$delivery_platform_map[$order->running->logistic_type];
                        $order->status_description = "[{$status_description_platform}] {$order->running->courier_name} {$order->running->courier_phone}";
                    }
                }
                preg_match_all('/收货人隐私号.*\*\*\*\*(\d\d\d\d)/', $order->caution, $preg_result);
                if (!empty($preg_result[0][0])) {
                    $order->caution = preg_replace('/收货人隐私号.*\*\*\*\*(\d\d\d\d)/', $order->caution, '');
                }
            }
        }
        return $this->page($orders);
    }
}
