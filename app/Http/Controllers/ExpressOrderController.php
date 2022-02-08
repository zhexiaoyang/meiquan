<?php

namespace App\Http\Controllers;

use App\Libraries\KuaiDi\KuaiDi;
use App\Models\ExpressOrder;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpressOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = ExpressOrder::with(['shop' => function($query) {
            $query->select('id', 'shop_name', 'contact_name', 'contact_phone', 'shop_address');
        }, 'logs'])->where('user_id', $request->user()->id);

        if ($order_id = $request->get('order_id')) {
            $query->where('order_id', 'like', "%{$order_id}%");
        }

        $data = $query->orderByDesc('id')->paginate($request->get('page_size', 10));
        return $this->page($data);
    }

    public function show(Request $request, ExpressOrder $expressOrder)
    {
        return $this->success($expressOrder);
    }

    public function store(Request $request)
    {
        $request->validate([
            // 'shop_id' => 'required',
            'send_name' => 'required',
            'send_phone' => 'required',
            'send_city_data' => 'required',
            'send_address' => 'required',
            'receive_name' => 'required',
            'receive_phone' => 'required',
            'receive_city_data' => 'required',
            // 'province' => 'required',
            // 'city' => 'required',
            // 'area' => 'required',
            'address' => 'required',
        ], [], [
            // 'shop_id' => '发单门店',
            'send_name' => '寄件人',
            'send_phone' => '寄件人电话',
            'send_city_data' => '寄件城市',
            'send_address' => '寄件详细地址',
            'receive_name' => '收货人',
            'receive_phone' => '收货人电话',
            // 'province' => '地区',
            // 'city' => '地区',
            // 'area' => '地区',
            'receive_city_data' => '收货人城市',
            'address' => '收件详细地址',
        ]);
        $user = $request->user();
        if ($user->money < 20) {
            return $this->error('跑腿余额小于20元，不能发单');
        }
        $data = $request->only('shop_id','receive_name','receive_phone','address','goods','platform','send_name','send_phone','send_address');
        $send_city_data = $request->get('send_city_data');
        if (!empty($send_city_data) && count($send_city_data) === 3) {
            $data['send_province'] = $send_city_data[0];
            $data['send_city'] = $send_city_data[1];
            $data['send_area'] = $send_city_data[2];
        } else {
            return $this->error('发单城市错误');
        }
        $receive_city_data = $request->get('receive_city_data');
        if (!empty($receive_city_data) && count($receive_city_data) === 3) {
            $data['province'] = $receive_city_data[0];
            $data['city'] = $receive_city_data[1];
            $data['area'] = $receive_city_data[2];
        } else {
            return $this->error('发单城市错误');
        }
        // return $data;
        // if (!$shop = Shop::find($data['shop_id'] ?? 0)) {
        //     return $this->error('门店不存在');
        // }
        $data['user_id'] = $user->id;
        $order = ExpressOrder::create($data);
        $kuaidi = New KuaiDi(config('kuaidi'));
        $res = $kuaidi->create_order($order);
        if ($res['returnCode'] == 200) {
            // ExpressOrder::where('id', $order->id)->update(['order_id' => $res['data']['orderId'], 'task_id' => $res['data']['taskId']]);
            $order->task_id = $res['data']['taskId'];
            $order->order_id = $res['data']['orderId'];
            $order->save();
        } else {
            $order->delete();
        }
        return $this->success();
    }

    public function destroy(Request $request, ExpressOrder $expressOrder)
    {
        $kuaidi = New KuaiDi(config('kuaidi'));
        $res = $kuaidi->cancel_order($expressOrder);

        if ($res['returnCode'] != 200) {
            return $this->error($res['message'] ?? '取消失败');
        }

        // $expressOrder->update(['status' => 99]);

        return $this->success();
    }

    public function shops(Request $request)
    {
        $shops = Shop::select("id", "shop_name as name","shop_address as address","contact_name as user","contact_phone as phone","province","city","district as area")
            ->where("own_id", Auth::id())->get();

        $order = ExpressOrder::query()->where('user_id', $request->user()->id)->orderByDesc('id')->first();
        $id = $order->shop_id ?? 0;

        if (!empty($shops)) {
            foreach ($shops as $key => $shop) {
                $address = $shop->address;
                if (mb_strstr($address, $shop->area)) {
                    $shop->address = mb_substr(mb_strstr($address, $shop->area), mb_strlen($shop->area));
                }
                if ($id === 0) {
                    $shop->select = $key == 0 ? 1 : 0;
                } else {
                    if ($shop->id == $id) {
                        $shop->select = 1;
                    } else {
                        $shop->select = 0;
                    }
                }
            }
        }

        return $this->success($shops);
    }

    public function pre_order(Request $request)
    {
        $platform = $request->get('platform');
        $send = $request->get('send');
        $receive = $request->get('receive');
        $weight = $request->get('weight', 1);

        $res = [
            'money' => 0,
            'disabled' => true,
            'display' => true,
            'msg' => ''
        ];


        if (empty($send) || empty($receive)) {
            return $this->success($res);
        }

        if ($platform == 1) {
            $city = DB::table('express_cities')
                ->where('send_province', str_replace(['市','省'], '', $send[0]))
                ->where('receive_province', str_replace(['市','省'], '', $receive[0]))
                ->where('platform', 1)
                ->first();
            if (!empty($city->v1)) {
                $v1 = $city->v1;
                $v2 = $city->v2;
                $v3 = $city->v3;
                $weight = $weight < 1 ? 1 : $weight;
                if ($weight <= 1) {
                    $res['money'] = $v1;
                    $res['msg'] = "1kg以内首重{$v1}元";
                } elseif ($weight > 1 && $weight <= 3) {
                    $res['money'] = $v2;
                    $res['msg'] = "3kg以内首重{$v2}元";
                } elseif ($weight >= 3) {
                    $res['money'] = $v2 + ($weight - 3) * $v3;
                    $res['msg'] = "3kg以内首重{$v2}元，续重 {$v3}元/kg";
                }

                $res['disabled'] = false;
            } else {
                $res['disabled'] = true;
                $res['display'] = false;
            }
        } elseif ($platform == 4) {
            $city = DB::table('express_cities')
                ->where('send_city', str_replace(['市','省'], '', $send[1] == '市辖区' ? $send[0] : $send[1]))
                ->where('receive_city', str_replace(['市','省'], '', $receive[1] == '市辖区' ? $receive[0] : $receive[1]))
                ->where('platform', 4)
                ->first();
            if (!empty($city->v1)) {
                $v1 = $city->v1;
                $v2 = $city->v2;

                $res['money'] = $v1 + ($weight - 1) * $v2;
                $res['msg'] = "首重{$v1}元，续重 {$v2}元/kg";

                $res['disabled'] = false;
            } else {
                $res['disabled'] = true;
                $res['display'] = false;
            }
        } else {
            $res['disabled'] = false;
            $res['display'] = false;
        }

        return $this->success($res);
    }
}

