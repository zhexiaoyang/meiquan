<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Admin\ShopExport;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\ShopThreeId;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
    public function index(Request $request)
    {

        $page_size = $request->get('page_size', 10);
        $contract_status = $request->get('contract_status', 0);
        $online_status = $request->get('online_status', 0);
        $shop_status = $request->get('shop_status', 0);
        $query = Shop::with(['online_shop' => function($query) {
            $query->select("shop_id", "contract_status");
        }, 'apply_three_id', 'setting.shop', 'contract','users']);

        // 搜索条件
        if ($shop_id = $request->get('shop_id')) {
            $query->where('id', $shop_id);
        }
        if ($city = $request->get('city')) {
            $query->where('city', 'like', "%{$city}%");
        }
        if ($shop_name = $request->get('shop_name')) {
            $query->where('shop_name', 'like', "%{$shop_name}%");
        }
        if ($contact_name = $request->get('contact_name')) {
            $query->where('contact_name', 'like', "%{$contact_name}%");
        }
        if ($contact_phone = $request->get('contact_phone')) {
            $query->where('contact_phone', 'like', "%{$contact_phone}%");
        }
        if ($manager = $request->get('manager')) {
            $query->whereIn('shop_id', DB::table('user_has_shops')->where('user_id', $manager)->pluck('shop_id'));
        }
        // 合同状态搜索
        if (in_array($contract_status, [1, 2])) {
            $query->whereHas('online_shop', function (Builder $query) use ($contract_status) {
                $query->where('contract_status', $contract_status == 1 ? 0 : 1);
            })->get();
        }
        // 外卖资料状态搜索
        if (in_array($online_status, [1, 2, 3, 4])) {
            if ($online_status == 4) {
                $query->whereDoesntHave('online_shop');
            } else {
                $query->whereHas('online_shop', function (Builder $query) use ($online_status) {
                    if ($online_status == 1) {
                        $query->where('status', '<', 20);
                    }
                    if ($online_status == 2) {
                        $query->where('status',  20);
                    }
                    if ($online_status == 3) {
                        $query->where('status',  40);
                    }
                })->get();
            }
        }
        // 商城认证状态
        if (in_array($shop_status, [1, 2, 3, 4])) {
            if ($shop_status == 1) {
                $query->where('auth',  0);
            }
            if ($shop_status == 2) {
                $query->where('auth',  1);
            }
            if ($shop_status == 3) {
                $query->where('auth',  3);
            }
            if ($shop_status == 4) {
                $query->where('auth',  10);
            }
        }
        // 城市经理
        $managers = User::select('id')->whereHas('roles', function ($query)  {
            $query->where('name', 'city_manager');
        })->where('status', 1)->where('id', '>', 2000)->get()->pluck('id')->toArray();

        $shops = $query->where("status", ">=", 0)->orderBy('id', 'desc')->paginate($page_size);

        $result = [];
        $data = [];

        if (!empty($shops)) {
            $contracts = Contract::select('id', 'name')->get()->toArray();
            foreach ($shops as $shop) {
                $tmp['id'] = $shop->id;
                $tmp['shop_name'] = $shop->shop_name;
                $tmp['shop_address'] = $shop->shop_address;
                $tmp['shop_lng'] = $shop->shop_lng;
                $tmp['shop_lat'] = $shop->shop_lat;
                $tmp['contact_name'] = $shop->contact_name;
                $tmp['contact_phone'] = $shop->contact_phone;
                $tmp['status'] = $shop->status;
                $tmp['shop_id'] = $shop->shop_id;
                $tmp['shop_id_fn'] = $shop->shop_id_fn;
                $tmp['shop_id_sf'] = $shop->shop_id_sf;
                $tmp['shop_id_ss'] = $shop->shop_id_ss;
                $tmp['shop_id_dd'] = $shop->shop_id_dd;
                $tmp['shop_id_mqd'] = $shop->shop_id_mqd;
                $tmp['shop_id_uu'] = $shop->shop_id_uu;
                $tmp['mt_shop_id'] = $shop->mt_shop_id;
                $tmp['city'] = $shop->city;

                // 外卖资料
                $tmp['material'] = $shop->material;
                // 商城
                $tmp['shopping'] = $shop->auth;
                // 三方ID
                $tmp['mtwm'] = $shop->mtwm;
                $tmp['mtwm_status'] = (bool) $shop->mtwm;
                $tmp['mtwm_apply_id'] = $shop->apply_three_id->mtwm ?? '';
                $tmp['mtwm_apply_status'] = (bool) ($shop->apply_three_id->mtwm ?? '');
                $tmp['ele'] = $shop->ele;
                $tmp['ele_status'] = (bool) $shop->ele;
                $tmp['ele_apply_id'] = $shop->apply_three_id->ele ?? '';
                $tmp['ele_apply_status'] = (bool) ($shop->apply_three_id->ele ?? '');
                $tmp['jddj'] = $shop->jddj;
                $tmp['jddj_status'] = (bool) $shop->jddj;
                $tmp['jddj_apply_id'] = $shop->apply_three_id->jddj ?? '';
                $tmp['jddj_apply_status'] = (bool) ($shop->apply_three_id->jddj ?? '');
                // 自动接单
                $tmp['mt_shop_id'] = $shop->mt_shop_id;
                $tmp['mt_shop_id_status'] = (bool) $shop->mt_shop_id;
                $tmp['mt_shop_id_auto_status'] = (bool) $shop->auto_mtwm;
                $tmp['ele_shop_id'] = $shop->ele_shop_id;
                $tmp['ele_shop_id_status'] = (bool) $shop->ele_shop_id;
                $tmp['ele_shop_id_auto_status'] = (bool) $shop->auto_ele;
                // 处方订单
                $tmp['chufang_mt'] = $shop->chufang_mt;
                $tmp['chufang_mt_status'] = (bool) $shop->chufang_mt;
                $tmp['chufang_ele'] = $shop->chufang_ele;
                $tmp['chufang_ele_status'] = (bool) $shop->chufang_ele;
                $tmp['chufang_status'] = $shop->chufang_status === 1;
                // 外卖
                $tmp['waimai_mt'] = $shop->waimai_mt;
                $tmp['waimai_mt_status'] = (bool) $shop->waimai_mt;
                $tmp['waimai_ele'] = $shop->waimai_ele;
                $tmp['waimai_ele_status'] = (bool) $shop->waimai_ele;
                // 合同状态
                $tmp['contract'] = $shop->online_shop->contract_status ?? 0;
                // 城市经理
                // $tmp['manager'] = $shop->manager->nickname ?? '';
                // 仓库
                if (!empty($shop->setting->shop)) {
                    $warehouse_time = explode('-', $shop->setting->warehouse_time);
                    $tmp['warehouse']['stime'] = $warehouse_time[0];
                    $tmp['warehouse']['etime'] = $warehouse_time[1];
                    $tmp['warehouse']['print'] = $shop->setting->print;
                    $tmp['warehouse']['id'] = $shop->setting->shop->id;
                    $tmp['warehouse']['name'] = $shop->setting->shop->shop_name;
                    $tmp['warehouse']['print'] = (bool) $shop->setting->warehouse_print;
                } else {
                    $tmp['warehouse'] = false;
                }
                // 合同
                $contract_data = $contracts;
                foreach ($contract_data as $k => $v) {
                    $contract_data[$k]['status'] = 0;
                    if (!empty($shop->contract)) {
                        foreach ($shop->contract as $item) {
                            if ($v['id'] === $item->contract_id) {
                                $contract_data[$k]['status'] = $item->status;
                            }
                        }
                    }
                }
                unset($shop->contract);
                $tmp['contract'] = $contract_data;
                // 城市经理
                if (!empty($shop->users)) {
                    foreach ($shop->users as $user) {
                        if (in_array($user->id, $managers)) {
                            $tmp['manager'] = $user->nickname ?: $user->username;
                        }
                    }
                }
                // 赋值
                $data[] = $tmp;
            }
        }

        $result['page'] = $shops->currentPage();
        $result['total'] = $shops->total();
        $result['list'] = $data;

        return $this->success($result);
    }

    public function all(Request $request)
    {
        $search_key = $request->get("search_key", "");

        $query = Shop::select("id", "shop_name", "city");

        if ($search_key) {
            $query->where("shop_name", "like", "%{$search_key}%");
        }

        $shops = $query->orderByDesc("id")->get();

        return $this->success($shops);
    }

    /**
     * 保存仓库设置
     * @author zhangzhen
     * @data 2022/2/9 8:53 上午
     */
    public function warehouse(Request $request)
    {
        if (!$shop_id = $request->get('id')) {
            return $this->error('门店ID不能为空');
        }
        if (!$warehouse = $request->get('warehouse')) {
            return $this->error('仓库ID不能为空');
        }
        if (!$stime = $request->get('stime')) {
            return $this->error('起始时间不能为空');
        }
        if (!$etime = $request->get('etime')) {
            return $this->error('结束时间不能为空');
        }

        $print = $request->get('print', false);

        if ($setting = OrderSetting::query()->where("shop_id", $shop_id)->first()) {
            $setting->warehouse = $warehouse;
            $setting->warehouse_print = (bool) $print;
            $setting->warehouse_time = $stime . '-' . $etime;
            $setting->save();
        } else {
            $data = config('ps.shop_setting');
            $data['shop_id'] = $shop_id;
            $data['warehouse'] = $warehouse;
            $data['warehouse_print'] =  (bool) $print;
            $data['warehouse_time'] = $stime . '-' . $etime;
            OrderSetting::create($data);
            // OrderSetting::query()->create([
            //     'shop_id' => $shop_id,
            //     // 延时发送订单，单位：秒
            //     'delay_send' => 60,
            //     // 检查订单重新发送，单位：分钟
            //     'delay_reset' => 8,
            //     // 重新发送是是否保持之前订单呼叫，1 是、2 否
            //     'type' => 1,
            //     // 交通工具（0 未指定，8 汽车）
            //     'tool' => 0,
            //     // 平台
            //     'meituan' => 1,
            //     'fengniao' => 1,
            //     'shansong' => 1,
            //     'shunfeng' => 1,
            //     'dada' => 1,
            //     'dd' => 1,
            //     'uu' => 1,
            //     'meiquanda' => 1,
            //     // 仓库
            //     'warehouse' => $warehouse,
            //     'warehouse_time' => $stime . '-' . $etime,
            // ]);
        }

        return $this->success();
    }

    public function export(Request $request, ShopExport $export)
    {
        $user = $request->user();
        if (!in_array($user->id, [1,32])) {
            return $this->error('没有权限');
        }
        return $export->withRequest();
    }

    /**
     * 审核管理-三方门店ID审核
     * @data 2021/12/1 4:27 下午
     */
    public function apply_three_id_shops(Request $request)
    {
        $query = ShopThreeId::with(['shop' => function ($query) {
            $query->select('id', 'shop_name', 'contact_name', 'contact_phone');
        },'conflict_mt' => function ($query) {
            $query->select('id', 'mtwm', 'shop_name', 'contact_name', 'contact_phone')->where('mtwm', '<>', '');
        },'conflict_ele' => function ($query) {
            $query->select('id', 'ele', 'shop_name', 'contact_name', 'contact_phone')->where('ele', '<>', '');
        }]);

        $data = $query->get();

        return $this->success($data);
    }

    public function apply_three_id_save(Request $request)
    {
        $id = $request->get('id', 0);
        $status = $request->get('status', 0);

        if (!$apply = ShopThreeId::find($id)) {
            return $this->error('门店不存在');
        }

        if ($status == 1) {
            if (!$shop = Shop::query()->find($apply->shop_id)) {
                return $this->error('门店不存在');
            }

            if (($mtwm = $apply->mtwm) && !$shop->mtwm) {
                if ($mtwm && ($_shop = Shop::query()->where('mtwm', $mtwm)->first())) {
                    return $this->error("美团ID已存在：绑定门店名称[{$_shop->shop_name}]");
                }
                $shop->mtwm = $mtwm;
                $shop->chufang_mt = $mtwm;
                // $shop->chufang_status = 2;
            }
            if (($ele = $apply->ele) && !$shop->ele) {
                if ($ele && ($_shop = Shop::query()->where('ele', $ele)->first())) {
                    return $this->error("饿了ID已存在：绑定门店名称[{$_shop->shop_name}]");
                }
                $shop->ele = $ele;
                $shop->chufang_ele = $ele;
                // $shop->chufang_status = 2;
            }
            if (($jddj = $apply->jddj) && !$shop->jddj) {
                $shop->jddj = $jddj;
                $shop->chufang_jddj = $ele;
                // $shop->chufang_status = 2;
            }

            $shop->save();
            $apply->delete();
        } else {
            $apply->delete();
        }

        return $this->success();
    }

    /**
     * 管理员修改三方ID
     * @data 2021/12/1 4:20 下午
     */
    public function update_three(Request $request)
    {
        $shop_id = $request->get('id', 0);

        if (!$shop = Shop::query()->find($shop_id)) {
            return $this->error('门店不存在');
        }


        if (ShopThreeId::where('shop_id', $shop_id)->first()) {
            return $this->error('该门店有待审核ID，请先审核');
        }

        $mtwm = $request->get('mtwm');
        $ele = $request->get('ele');
        $jddj = $request->get('jddj');

        if (!is_null($mtwm)) {
            if ($mtwm && ($_shop = Shop::query()->where('mtwm', $mtwm)->first())) {
                if ($_shop->id != $shop_id) {
                    return $this->error("美团ID已存在：绑定门店名称[{$_shop->shop_name}]");
                }
            }
            $shop->mtwm = $mtwm;
            if ($shop->second_category == 200001) {
                $shop->chufang_mt = $mtwm;
                $shop->chufang_status = 2;
            }
        }
        if (!is_null($ele)) {
            if ($ele && ($_shop = Shop::query()->where('ele', $ele)->first())) {
                if ($_shop->id != $shop_id) {
                    return $this->error("饿了ID已存在：绑定门店名称[{$_shop->shop_name}]");
                }
            }
            $shop->ele = $ele;
            if ($shop->second_category == 200001) {
                $shop->chufang_ele = $ele;
                $shop->chufang_status = 2;
            }
        }
        if (!is_null($jddj)) {
            $shop->jddj = $jddj;
        }

        $shop->save();

        return $this->success($shop);
    }
}
