<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Admin\PrescriptionOrderExport;
use App\Exports\PrescriptionShopExport;
use App\Http\Controllers\Controller;
use App\Jobs\SendSmsNew;
use App\Models\Shop;
use App\Models\UserOperateBalance;
use App\Models\WmPrescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrescriptionController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $order_id = $request->get('order_id', '');
        $shop_id = $request->get('shop_id', '');
        $platform = $request->get('platform', '');
        $stime = $request->get('stime', '');
        $etime = $request->get('etime', '');
        $status = $request->get('status', '');
        $mtwm = $request->get('mtwm', '');

        $query = WmPrescription::query();

        if ($order_id) {
            $query->where('outOrderID', $order_id);
        }
        if ($shop_id) {
            $query->where('shop_id', $shop_id);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($platform) {
            $query->where('platform', $platform);
        }
        if ($stime) {
            $query->where('rpCreateTime', '>=', $stime);
        }
        if ($etime) {
            $query->where('rpCreateTime', '<', date("Y-m-d", strtotime($etime) + 86400));
        }
        if ($mtwm) {
            $query->where('storeID', $mtwm);
        }

        $data = $query->paginate($page_size);

        return $this->page($data, [], 'data');
    }

    public function export(Request $request, PrescriptionOrderExport $export)
    {
        return $export->withRequest($request);
    }

    public function statistics(Request $request)
    {
        $order_id = $request->get('order_id', '');
        $platform = $request->get('platform', '');
        $shop_id = $request->get('shop_id', '');
        $stime = $request->get('stime', '');
        $etime = $request->get('etime', '');
        $status = $request->get('status', '');

        $query = WmPrescription::query();

        if ($order_id) {
            $query->where('outOrderID', $order_id);
        }
        if ($shop_id) {
            $query->where('shop_id', $shop_id);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($platform) {
            $query->where('platform', $platform);
        }
        if ($stime) {
            $query->where('rpCreateTime', '>=', $stime);
        }
        if ($etime) {
            $query->where('rpCreateTime', '<', date("Y-m-d", strtotime($etime) + 86400));
        }

        $data = $query->get();

        $total_num = 0;
        $total_money = 0;
        $mt_num = 0;
        $mt_money = 0;
        $ele_num = 0;
        $ele_money = 0;
        $xx_num = 0;
        $xx_money = 0;

        if (!empty($data)) {
            foreach ($data as $v) {
                $total_num++;
                $total_money += 1.5;
                if ($v->platform === 1) {
                    $mt_num++;
                    $mt_money += 1.5;
                }
                if ($v->platform === 2) {
                    $ele_num++;
                    $mt_money += 1.5;
                }
                if ($v->platform === 3) {
                    $xx_num++;
                    $mt_money += 1.5;
                }
            }
        }

        $res = [
            'total_num' => $total_num,
            'total_money' => $total_money,
            'mt_num' => $mt_num,
            'mt_money' => $mt_money,
            'ele_num' => $ele_num,
            'ele_money' => $ele_money,
            'xx_num' => $xx_num,
            'xx_money' => $xx_money,
        ];

        return $this->success($res);
    }

    public function shop_statistics()
    {
        $up = Shop::with('own')->where('second_category', '200001')->whereHas('own', function ($query) {
            $query->where('operate_money', '<', 50);
        })->where('chufang_status', 1)->where('status', '>', 0)->count();

        $down = Shop::with('own')->where(function ($query) {
            $query->where('chufang_mt', '<>', '')->orWhere('chufang_ele', '<>', '')->orWhere('jddj', '<>', '');
        })->whereHas('own', function ($query) {
            $query->where('operate_money', '>=', 50);
        })->where('chufang_status', 2)->where('status', '>', 0)->count();

        // 未绑定
        $unbind = 0;
        $unbind_ele = 0;
        // 已解绑
        $unbound = 0;
        $unbound_ele = 0;

        $shops = Shop::where('user_id', '>', 0)->where('second_category', '200001')->where('chufang_status', '>', 0)->where('chufang_status', 1)->get();
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                if ($shop->waimai_mt === '') {
                    if ($shop->unbind_date) {
                        $unbind++;
                    } else {
                        $unbound++;
                    }
                }
                if ($shop->waimai_ele === '') {
                    if ($shop->unbind_ele_date) {
                        $unbind_ele++;
                    } else {
                        $unbound_ele++;
                    }
                }
            }
        }

        $total = $up + $down;

        $res = [
            'total' => $total,
            'up' => $up,
            'down' => $down,
            'unbind' => $unbind,
            'unbound' => $unbound,
            'unbind_ele' => $unbind_ele,
            'unbound_ele' => $unbound_ele,
        ];

        return $this->status($res);
    }

    /**
     * 处方管理门店列表
     * @data 2021/11/19 3:23 下午
     */
    public function shop(Request $request)
    {
        $query = DB::table('shops')->leftJoin('users', 'shops.own_id', '=', 'users.id')
            ->select('users.id as uid','users.phone','users.operate_money','users.id','shops.id',
                'prescription_cost', 'prescription_channel','prescription_cost_ele', 'prescription_channel_ele',
                'shops.own_id','shops.shop_name','shops.mtwm','shops.ele','shops.jddj','shops.chufang_status as status',
                'waimai_ele', 'waimai_mt', 'chufang_mt', 'chufang_ele')
            ->where('shops.user_id', '>', 0)
            ->where('shops.chufang_status', '>', 0)
            ->where('shops.second_category', '200001');

        if ($status = $request->get('status')) {
            if (in_array($status, [1, 2])) {
                $query->where('shops.chufang_status', $status);
            }
            if ($status == 3) {
                $query->where('shops.chufang_status', '>', 0);
            }
            if ($status == 4) {
                $query->where('shops.chufang_status', 0);
            }
        }
        if ($name = $request->get('name')) {
            $query->where('shops.shop_name', 'like', "%{$name}%");
        }
        $channel = $request->get('channel', '');
        if ($channel) {
            $query->where('prescription_channel', $channel);
        }
        $channel_ele = $request->get('channel_ele', '');
        if ($channel_ele) {
            $query->where('prescription_channel_ele', $channel_ele);
        }
        if ($phone = $request->get('phone')) {
            $query->where('users.phone', 'like', "%{$phone}%");
        }
        if ($start = $request->get('start')) {
            $query->where('users.operate_money', '>=', $start);
        }
        if ($end = $request->get('end')) {
            $query->where('users.operate_money', '<', $end);
        }
        $bind_status = $request->get('bind_status', '');
        if ($bind_status) {
            if ($bind_status == 1) {
                $query->where('waimai_mt', '<>', '');
            } else if ($bind_status == 2) {
                $query->where('waimai_mt', '');
            } else if ($bind_status == 3) {
                $query->where('waimai_ele', '<>', '');
            } else if ($bind_status == 4) {
                $query->where('waimai_ele', '');
            } else if ($bind_status == 5) {
                // 美团已解绑但上线
                $query->where('waimai_mt', '')
                    ->whereNotNull('unbind_date')
                    ->where('chufang_status', 1);
            } else if ($bind_status == 6) {
                // 美团未绑定但上线
                $query->where('waimai_mt', '')
                    ->whereNull('unbind_date')
                    ->where('chufang_status', 1);
            } else if ($bind_status == 7) {
                // 饿了么已解绑但上线
                $query->where('waimai_ele', '')
                    ->whereNotNull('unbind_ele_date')
                    ->where('chufang_status', 1);
            } else if ($bind_status == 8) {
                // 饿了么未绑定但上线
                $query->where('waimai_ele', '')
                    ->whereNull('unbind_ele_date')
                    ->where('chufang_status', 1);
            }
        }

        $order_key = $request->get('order_key');
        $order = $request->get('order');
        if ($order_key && $order) {
            if ($order_key == 'uid') {
                if ($order == 'descend') {
                    $query->orderByDesc('users.id');
                } else {
                    $query->orderBy('users.id');
                }
            }
            if ($order_key == 'operate_money') {
                if ($order == 'descend') {
                    $query->orderByDesc('users.operate_money');
                } else {
                    $query->orderBy('users.operate_money');
                }
            }
        } else {
            $query->orderByDesc('shops.id');
        }

        // $query->orderByDesc('shops.id');

        $data = $query->paginate($request->get('page_size', 10));

        return $this->page($data);
    }

    public function shop_all(Request $request)
    {
        $data = [];
        if ($name = $request->get('name')) {
            // $data = DB::table('shops')->leftJoin('users', 'shops.own_id', '=', 'users.id')
            //     ->select('users.id as uid','users.phone','users.operate_money','users.id','shops.id',
            //         'prescription_cost', 'prescription_channel',
            //         'shops.own_id','shops.shop_name','shops.mtwm','shops.ele','shops.jddj','shops.chufang_status as status')
            //     ->where('shops.user_id', '>', 0)
            //     ->where('shops.chufang_status', '>', 0)
            //     ->where('shops.second_category', '200001')
            //     ->where('shops.shop_name', 'like', "%{$name}%")
            //     ->orderByDesc('shops.id')->limit(30)->get();

            $data = WmPrescription::select("storeName")
                ->where('storeName', 'like', "%{$name}%")
                ->groupBy("storeName")->get();
        }
        return $this->success($data);
    }

    /**
     * 重新结算未结算订单
     * @data 2021/12/3 8:51 上午
     */
    public function again()
    {
        $prescription_again_lock = Cache::lock("prescription_again_lock", 600);
        if (!$prescription_again_lock->get()) {
            return $this->error('刚刚已经处理过了，请等待！');
        }
        $data = WmPrescription::where('status', 2)->get();
        if (!empty($data)) {
            foreach ($data as $v) {
                if ($v->platform == 1) {
                    $shop = Shop::select('id','user_id')->where('chufang_mt', $v->storeID)->first();
                    if ($shop) {
                        if ($shop->user_id) {
                            DB::transaction(function () use ($v, $shop) {
                                $money = $v->money;
                                $order_id = $v->outOrderID;
                                $current_user = DB::table('users')->find($shop->user_id);
                                if ($v['orderStatus'] == '已完成' || $v['orderStatus'] == '进行中') {
                                    UserOperateBalance::create([
                                        "user_id" => $current_user->id,
                                        "money" => $money,
                                        "type" => 2,
                                        "before_money" => $current_user->operate_money,
                                        "after_money" => ($current_user->operate_money - $money),
                                        "description" => "处方单审方：" . $order_id,
                                        "shop_id" => $shop->id,
                                        "tid" => $v->id,
                                        'order_at' => $v['rpCreateTime']
                                    ]);
                                    // 减去用户运营余额
                                    DB::table('users')->where('id', $current_user->id)->decrement('operate_money', $money);
                                    DB::table('wm_prescriptions')->where('id', $v->id)->update(['status' => 1, 'reason' => '']);
                                    $_user = DB::table('users')->find($current_user->id);
                                    if ($_user->operate_money < 50) {
                                        $phone = $_user->phone;
                                        $lock = Cache::lock("send_sms_chufang:{$phone}", 86400);
                                        if ($lock->get()) {
                                            Log::info("处方余额不足发送短信：{$phone}");
                                            dispatch(new SendSmsNew($phone, "SMS_267395014", [ 'phone' => '15043264324']));
                                        } else {
                                            Log::info("今天已经发过短信了：{$phone}");
                                        }
                                    }
                                }
                            });
                        } else {
                            WmPrescription::where('id', $v->id)->update(['reason' => '门店已删除']);
                        }
                    } else {
                        WmPrescription::where('id', $v->id)->update(['reason' => '通过美团门店ID找不到门店']);
                    }
                } else {
                    $shop = Shop::select('id','user_id')->where('chufang_ele', $v->storeID)->first();
                    if (!$shop) {
                        $shop = Shop::select('id','user_id')->where('waimai_ele_kl', $v->storeID)->first();
                    }
                    if ($shop) {
                        if ($shop->user_id) {
                            DB::transaction(function () use ($v, $shop) {
                                $money = $v->money;
                                $order_id = $v->outOrderID;
                                $current_user = DB::table('users')->find($shop->user_id);
                                if ($v['orderStatus'] == '已处理' || $v['orderStatus'] == '已完成' || $v['orderStatus'] == '进行中') {
                                    UserOperateBalance::create([
                                        "user_id" => $current_user->id,
                                        "money" => $money,
                                        "type" => 2,
                                        "before_money" => $current_user->operate_money,
                                        "after_money" => ($current_user->operate_money - $money),
                                        "description" => "处方单审方：" . $order_id,
                                        "shop_id" => $shop->id,
                                        "tid" => $v->id,
                                        'order_at' => $v['doctorTime']
                                    ]);
                                    // 减去用户运营余额
                                    DB::table('users')->where('id', $current_user->id)->decrement('operate_money', $money);
                                    DB::table('wm_prescriptions')->where('id', $v->id)->update(['status' => 1, 'reason' => '']);
                                    $_user = DB::table('users')->find($current_user->id);
                                    if ($_user->operate_money < 50) {
                                        $phone = $_user->phone;
                                        $lock = Cache::lock("send_sms_chufang:{$phone}", 86400);
                                        if ($lock->get()) {
                                            Log::info("处方余额不足发送短信：{$phone}");
                                            dispatch(new SendSmsNew($phone, "SMS_267395014", [ 'phone' => '15043264324']));
                                        } else {
                                            Log::info("今天已经发过短信了：{$phone}");
                                        }
                                    }
                                }
                            });
                        } else {
                            WmPrescription::where('id', $v->id)->update(['reason' => '门店已删除']);
                        }
                    } else {
                        WmPrescription::where('id', $v->id)->update(['reason' => '通过饿了么门店ID找不到门店']);
                    }
                }
            }
        }
        return $this->success();
    }

    /**
     * 处方门店列表-导出
     * @data 2021/11/20 10:27 下午
     */
    public function shop_export(Request $request, PrescriptionShopExport $export)
    {
        return $export->withRequest($request);
    }

    /**
     * 处方门店修改状态
     * @data 2021/11/19 10:26 下午
     */
    public function shop_update(Request $request)
    {
        // if (!$shop = Shop::find($request->get('id', 0))) {
        //     return $this->error('门店不存在');
        // }

        $status = $request->get('status', 0);
        $ids = $request->get('ids');

        \Log::info("处方门店状态变更", [
            '操作人ID' => $request->user()->id,
            '状态' => $status,
            '门店ID' => $ids,
        ]);

        if (empty($ids)) {
            return $this->success();
        }

        if ($status === 1) {
            Shop::whereIn('id', $ids)->update(['chufang_status' => 1]);
        }
        if ($status === 2) {
            Shop::whereIn('id', $ids)->update(['chufang_status' => 2]);
        }
        if ($status === 3) {
            Shop::whereIn('id', $ids)->update(['chufang_status' => 1]);
        }
        // $shop->save();

        return $this->success();
    }

    /**
     * 处方门店-关闭处方
     * @data 2021/11/19 10:26 下午
     */
    public function shop_delete(Request $request)
    {
        if (!$shop = Shop::find($request->get('id', 0))) {
            return $this->error('门店不存在');
        }
        $shop->chufang_mt = '';
        $shop->chufang_ele = '';
        $shop->chufang_jddj = '';
        $shop->chufang_status = 0;
        $shop->save();

        return $this->success();
    }

    public function shop_cost(Request $request)
    {
        if (!$shop = Shop::find($request->get('id', 0))) {
            return $this->error('门店不存在');
        }
        $shop->prescription_cost = floatval($request->get('cost', 0));
        $shop->prescription_channel = intval($request->get('channel', 0));
        $shop->prescription_cost_ele = floatval($request->get('cost_ele', 0));
        $shop->prescription_channel_ele = intval($request->get('channel_ele', 0));
        $shop->save();

        return $this->success();
    }

    public function delete(Request $request)
    {
        if ($id = $request->get('id')) {
            if ($prescription = WmPrescription::find($id)) {
                if ($prescription->status == 2) {
                    $prescription->delete();
                }
            }
        } elseif ($shop_id = $request->get('shop_id')) {
            WmPrescription::where('storeID', $shop_id)->where('status', 2)->delete();
        }

        return $this->success();
    }
}
