<?php

namespace App\Http\Controllers;

use App\Events\OrderCreate;
use App\Exports\WmOrdersExport;
use App\Jobs\PrintWaiMaiOrder;
use App\Libraries\Feie\Feie;
use App\Models\Shop;
use App\Models\WmOrder;
use App\Models\WmOrderExtra;
use App\Models\WmPrinter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class WmOrderController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $exception = $request->get('exception', 0);

        if (!$sdate = $request->get('sdate')) {
            $sdate = date("Y-m-d");
        }
        if (!$edate = $request->get('edate')) {
            $edate = date("Y-m-d");
        }
        if ((strtotime($edate) - strtotime($sdate)) / 86400 > 31) {
            return $this->error('时间范围不能超过31天');
        }

        $query = WmOrder::with(['items' => function ($query) {
            $query->select('id', 'order_id', 'food_name', 'quantity', 'price', 'upc', 'vip_cost');
        }, 'receives', 'running' => function ($query) {
            $query->with(['logs' => function ($q) {
                $q->orderByDesc('id');
            }])->select('id', 'wm_id', 'courier_name', 'courier_phone', 'status');
        }, 'shop' => function ($query) {
            $query->select('id', 'shop_lng', 'shop_lat');
        }])->select('id','platform','day_seq','shop_id','is_prescription','order_id','delivery_time','estimate_arrival_time',
            'status','recipient_name','recipient_phone','is_poi_first_order','way','recipient_address_detail','wm_shop_name',
            'ctime','caution','print_number','poi_receive','vip_cost','running_fee','prescription_fee','operate_service_fee','operate_service_fee_status');

        $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));

        $query->where('created_at', '>=', $sdate)->where('created_at', '<', date("Y-m-d", strtotime($edate) + 86400));

        if ($exception) {
            if ($exception == 2) {
                $query->where('vip_cost', '<=', 0);
            } elseif ($exception == 1) {
                // $query->where(DB::raw('poi_receive') - DB::raw('vip_cost') - DB::raw('running_fee') - DB::raw('prescription_fee'), '<', 0);
                $query->where(DB::raw("poi_receive - vip_cost - running_fee - prescription_fee"), '<', 0);
            }
        }

        if ($status = $request->get('status', 0)) {
            $query->where('status', $status);
        }
        if ($channel = $request->get('channel', 0)) {
            $query->where('channel', $channel);
        }
        if ($way = $request->get('way', 0)) {
            $query->where('way', $way);
        }
        if ($platform = $request->get('platform', 0)) {
            $query->where('platform', $platform);
        }
        if ($order_id = $request->get('order_id', '')) {
            $query->where('order_id', 'like', "{$order_id}%");
        }
        if ($name = $request->get('name', '')) {
            $query->where('recipient_name', $name);
        }
        if ($phone = $request->get('phone', '')) {
            $query->where('recipient_phone', $phone);
        }

        $data = $query->orderByDesc('id')->paginate($page_size);

        if (!empty($data)) {
            foreach ($data as $order) {
                // $order->ctime = date("Y-m-d H:i:s", $order->ctime);
                // $order->estimate_arrival_time = date("Y-m-d H:i:s", $order->estimate_arrival_time);
                $ping_fee = 0;
                $poi_fee = 0;
                if (!empty($order->receives)) {
                    foreach ($order->receives as $receive) {
                        if ($receive->type == 1) {
                            $ping_fee += $receive->money;
                        } else {
                            $poi_fee += $receive->money;
                        }
                    }
                }
                $order->ping_fee = $ping_fee;
                $order->poi_fee = $poi_fee;
            }
        }

        return $this->page($data);
    }

    public function show(Request $request)
    {
        if (!$order = WmOrder::with('items')->find($request->get('order_id', 0))) {
            return $this->error('订单不存在');
        }

        return $this->success($order);
    }

    public function print_list(Request $request)
    {
        $page_size = $request->get('page_size', '');

        $query = WmPrinter::with(['shop' => function ($query) {
            $query->select('id', 'shop_name');
        }]);

        if ($name = $request->get('name', '')) {
            $query->where('name', 'like', "{$name}");
        }
        if ($sn = $request->get('sn', '')) {
            $query->where('sn', $sn);
        }

        $query->whereIn('shop_id', Shop::where('own_id', $request->user()->id)->pluck('id'));

        $data = $query->orderByDesc('id')->paginate($page_size);

        return $this->page($data);
    }

    public function print_add(Request $request)
    {
        if (!$key = $request->get('key', 0)) {
            return $this->error('打印机key不能为空');
        }

        if (!$sn = $request->get('sn', 0)) {
            return $this->error('打印机sn不能为空');
        }

        if (!$platform = $request->get('platform', 0)) {
            return $this->error('请选择打印机平台');
        }

        if (!$shop_id = $request->get('shop_id', 0)) {
            return $this->error('门店不存在');
        }

        if (!in_array($shop_id, Shop::where('own_id', $request->user()->id)->pluck('id')->toArray())) {
            return $this->error('门店不存在！');
        }

        $number = $request->get('number', 1);

        if (!in_array($number, [1,2,3,4])) {
            $number = 1;
        }

        $name = $request->get('name', '');

        if (!WmPrinter::query()->where('key',$key)->orWhere('sn', $sn)->first()) {
            // return $this->error('KEY或者SN已经存在，不能重复添加！');

            $content = $sn . '#' . $key;

            if ($name) {
                $content .= '#' . $name;
            }

            $f = new Feie();
            $res = $f->print_add($content);

            if (!empty($res['data']['no']) && (count($res['data']['no']) > 0)) {
                $message = $res['data']['no'][0];
                $message = strstr($message, '错误：');
                $message = mb_substr($message, 0, -1);
                return $this->success([], $message, 422);
            }
        }

        WmPrinter::query()->create(compact('shop_id', 'name', 'key', 'sn', 'platform', 'number'));

        return $this->success();

    }

    public function print_update(Request $request)
    {
        $number = $request->get('number', 1);
        $name = $request->get('name', '');

        if (!$shop_id = $request->get('shop_id', 0)) {
            return $this->error('门店不存在');
        }

        if (!in_array($shop_id, Shop::where('own_id', $request->user()->id)->pluck('id')->toArray())) {
            return $this->error('门店不存在！');
        }

        if (!in_array($number, [1,2,3,4])) {
            $number = 1;
        }

        if (!$printer = WmPrinter::find($request->get('id', 0))) {
            return $this->error('参数错误，请稍后再试');
        }

        if (!Shop::where('id', $printer->shop_id)->where('own_id', $request->user()->id)->first()) {
            return $this->error('参数错误，请稍后再试');
        }

        $printer->name = $name;
        $printer->number = $number;
        $printer->shop_id = $shop_id;
        $printer->save();

        return $this->success();
    }

    public function print_del(Request $request)
    {
        if (!$printer = WmPrinter::find($request->get('id', 0))) {
            return $this->error('打印机不存在');
        }

        if (WmPrinter::query()->where('key',$printer->key)->count() === 1) {
            $f = new Feie();
            $res = $f->print_del($printer->sn);

            if (!isset($res['ret']) || $res['ret'] !== 0) {
                return $this->error('打印机删除失败');
            }
        }

        $printer->delete();

        return $this->success();
    }

    public function print_clear(Request $request)
    {
        if (!$printer = WmPrinter::find($request->get('id', 0))) {
            return $this->error('打印机不存在');
        }

        if (!in_array($printer->shop_id, Shop::where('own_id', $request->user()->id)->pluck('id'))) {
            return $this->error('打印机不存在！');
        }

        $f = new Feie();
        $res = $f->print_clear($printer->sn);

        if (!isset($res['ret']) || $res['ret'] !== 0) {
            return $this->error('清空待打印失败');
        }

        return $this->success();
    }

    public function print_shops(Request $request)
    {
        $query = Shop::query()->select('id', 'shop_name');
            // ->where(function ($query) {
            //     $query->where('waimai_mt', "<>", "")->orWhere('waimai_ele', "<>", "");
            // });
        $query->whereIn('id', $request->user()->shops()->pluck('id'));

        return $this->success($query->get());
    }

    public function print_order(Request $request)
    {
        if (!$order = WmOrder::find($request->get('order_id', 0))) {
            return $this->error("订单不存在");
        }

        if (!$print = WmPrinter::where('shop_id', $order->shop_id)->first()) {
            return $this->error("该订单门店没有绑定打印机");
        }

        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if ($order->user_id !=  $request->user()->id) {
                return $this->error('无权限操作此订单');
            }
        }

        dispatch(new PrintWaiMaiOrder($order->id, $print));

        return $this->success();
    }

    public function print_info(Request $request)
    {
        $user_id = $request->user()->id;
        if (!$order = WmOrder::find($request->get('order_id', 0))) {
            return $this->error("订单不存在");
        }
        if (!$request->user()->hasRole('super_man')) {
            if ($order->user_id !== $user_id && $order->shop_id !== $request->user()->account_shop_id) {
                return $this->error("订单不存在");
            }
        }

        $items = [];
        $receives = [];
        $total_num = 0;
        if (!empty($order->items)) {
            foreach ($order->items as $item) {
                $items[] = [
                    'id' => $item->id,
                    'name' => $item->food_name,
                    'upc' => $item->upc,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                ];
                $total_num += $item->quantity;
            }
        }
        // if (!empty($order->receives)) {
        //     foreach ($order->receives as $receive) {
        //         if ($receive->type === 2) {
        //             $receives[] = [
        //                 'id' => $receive->id,
        //                 'comment' => $receive->comment,
        //                 'money' => $receive->money,
        //             ];
        //         }
        //     }
        // }

        $extras = [];
        $extra_num = 0;
        $extra_data = WmOrderExtra::where('order_id', $order->id)->whereIn('type', [4,5,23])->get();
        if (!empty($extra_data)) {
            foreach ($extra_data as $extra_datum) {
                $extra_num += $extra_datum->gift_num;
                $extras[] = [
                    'id' => $extra_datum->id,
                    'name' => $extra_datum->gift_name,
                    'num' => $extra_datum->gift_num,
                ];
            }
        }

        $platform = [ '', '美团外卖', '饿了么'];
        $data = [
            'order_id' => $order->order_id,
            'day_seq' => $order->day_seq,
            'platform' => $platform[$order->platform],
            'wm_shop_name' => $order->wm_shop_name,
            'recipient_name' => $order->recipient_name,
            'recipient_phone' => $order->recipient_phone,
            'recipient_address' => $order->recipient_address,
            'caution' => $order->caution,
            'total_num' => $total_num,
            'ctime' => date("Y-m-d H:i:s", $order->ctime),
            'ptime' => date("Y-m-d H:i:s"),
            'send' => $order->delivery_time > 0 ? "【预约单】" . date("m-d H:i", $order->delivery_time) . '送达' : '【立即送达】',
            'items' => $items,
            'extras' => $extras,
            'extra_num' => $extra_num,
            'receives' => []
        ];
        $order->increment('print_number');
        return $this->success($data);
    }

    public function print_auto_switch(Request $request)
    {
        $user_id = $request->user()->id;
        $off = 0;
        $shops = Shop::where('user_id', $user_id)->where('print_auto', 1)->get();
        $shop_ids = [];
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                if ($shop->waimai_mt || $shop->waimai_ele) {
                    $shop_ids[] = $shop->id;
                }
            }
        }
        if (!empty($shop_ids)) {
            $off = 1;
        }
        return $this->success(['off' => $off]);
    }

    public function printer_one(Request $request)
    {
        // \Log::info('account_shop_id:' . $request->user()->account_shop_id);
        $redis_key = 'print_order_' . $request->user()->id;
        if (is_null(Redis::get($redis_key))) {
            // Redis::del($redis_key);
            return $this->success();
        }
        // \Log::info('获取到Redis', [$redis_res]);
        $redis_res = Redis::decr($redis_key);
        if ($redis_res <= 0) {
            Redis::del($redis_key);
        }
        if ($request->user()->account_shop_id) {
            // 子账号
            $user_id = $request->user()->id;
            $shops = Shop::select('id','waimai_mt','waimai_ele')->where('account_id', $user_id)->where('print_auto', 1)->get();
        } else {
            $user_id = $request->user()->id;
            $shops = Shop::select('id','waimai_mt','waimai_ele')->where('user_id', $user_id)->where('print_auto', 1)->where('account_id', 0)->get();
        }
        // $shops = Shop::where('user_id', 779)->where('print_auto', 2)->get();
        $shop_ids = [];
        // $order_data = [];
        // $order_id = '';
        $data = [];
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                if ($shop->waimai_mt || $shop->waimai_ele) {
                    $shop_ids[] = $shop->id;
                }
            }
        }

        // if ($user_id !== 1 && $user_id !== 32) {
        //     $order = WmOrder::whereIn('shop_id', $shop_ids)->where('print_number', 0)
        //         ->where('created_at', '>', date("Y-m-d h:i:s", strtotime("+10 minutes")))->orderBy('id')->first();
        // } else {
        //     $order = WmOrder::whereIn('shop_id', $shop_ids)->where('print_number', 0)->orderBy('id')->first();
        // }
        $order = WmOrder::whereIn('shop_id', $shop_ids)->where('print_number', 0)
            ->where('created_at', '>', date("Y-m-d h:i:s", strtotime("-2 minutes")))->orderBy('id')->first();

        if ($order) {
            $items = [];
            $receives = [];
            $total_num = 0;
            if (!empty($order->items)) {
                foreach ($order->items as $item) {
                    $items[] = [
                        'id' => $item->id,
                        'name' => $item->food_name,
                        'upc' => $item->upc,
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                    ];
                    $total_num += $item->quantity;
                }
            }
            // if (!empty($order->receives)) {
            //     foreach ($order->receives as $receive) {
            //         if ($receive->type === 2) {
            //             $receives[] = [
            //                 'id' => $receive->id,
            //                 'comment' => $receive->comment,
            //                 'money' => $receive->money,
            //             ];
            //         }
            //     }
            // }

            $extras = [];
            $extra_num = 0;
            $extra_data = WmOrderExtra::where('order_id', $order->id)->whereIn('type', [4,5,23])->get();
            if (!empty($extra_data)) {
                foreach ($extra_data as $extra_datum) {
                    $extra_num += $extra_datum->gift_num;
                    $extras[] = [
                        'id' => $extra_datum->id,
                        'name' => $extra_datum->gift_name,
                        'num' => $extra_datum->gift_num,
                    ];
                }
            }

            $platform = [ '', '美团外卖', '饿了么'];
            $data = [
                'order_id' => $order->order_id,
                'day_seq' => $order->day_seq,
                'platform' => $platform[$order->platform],
                'wm_shop_name' => $order->wm_shop_name,
                'recipient_name' => $order->recipient_name,
                'recipient_phone' => $order->recipient_phone,
                'recipient_address' => $order->recipient_address,
                'caution' => $order->caution,
                'total_num' => $total_num,
                'ctime' => date("Y-m-d H:i:s", $order->ctime),
                'ptime' => date("Y-m-d H:i:s"),
                'send' => $order->delivery_time > 0 ? "【预约单】" . date("m-d H:i", $order->delivery_time) . '送达' : '【立即送达】',
                'items' => $items,
                'extras' => $extras,
                'extra_num' => $extra_num,
                'receives' => []
            ];
            $order->increment('print_number');
        }

        return $this->success($data);
    }

    /**
     * 查看处方图片
     * @author zhangzhen
     * @data 2023/3/1 1:10 上午
     */
    public function getRpPicture(Request $request)
    {
        if (!$order_id = $request->get('order_id')) {
            return $this->error('订单不存在');
        }
        if (!$order = WmOrder::find($order_id)) {
            return $this->error('订单不存在!');
        }
        if (!$order->is_prescription) {
            return $this->error('非处方单');
        }
        if ($order->platform === 1) {
            if ($order->from_type !== 4 && $order->from_type !== 31) {
                return $this->error('该门店绑定餐饮，暂时无法获取处方信息');
            }
        }
        $rp_picture = $order->rp_picture;
        if (!$rp_picture) {
            event(new OrderCreate($order));
            // sleep(2);
            return $this->error('正在获取处方信息，请稍后查看');
        }
        return $this->success(['rp_picture' => $rp_picture]);
    }

    public function export(Request $request, WmOrdersExport $export)
    {
        if (!$sdate = $request->get('sdate')) {
            $sdate = date("Y-m-d");
        }
        if (!$edate = $request->get('edate')) {
            $edate = date("Y-m-d");
        }
        if ((strtotime($edate) - strtotime($sdate)) / 86400 > 31) {
            return $this->error('时间范围不能超过31天');
        }

        return $export->withRequest($request);
    }
}
