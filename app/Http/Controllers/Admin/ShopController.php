<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NoPermissionException;
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
        }, 'apply_three_id', 'setting.shop', 'contract','users','user']);

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
        // 判断角色
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            // if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('id', $request->user()->shops()->pluck('id'));
        }

        $shops = $query->where("status", ">=", 0)->orderBy('id', 'desc')->paginate($page_size);

        $result = [];
        $data = [];

        if (!empty($shops)) {
            $contracts = Contract::select('id', 'name')->get()->toArray();
            foreach ($shops as $shop) {
                $tmp['id'] = $shop->id;
                $tmp['username'] = $shop->user->name ?? '';
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

                // 佣金设置
                $tmp['commission_mt'] = $shop->commission_mt;
                $tmp['commission_ele'] = $shop->commission_ele;
                // 跑腿加价
                $tmp['running_add'] = $shop->running_add;
                $tmp['running_manager_add'] = $shop->running_manager_add;
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
                $tmp['manager'] = '';
                $tmp['manager_id'] = 0;
                if (!empty($shop->users)) {
                    foreach ($shop->users as $user) {
                        if (in_array($user->id, $managers)) {
                            $tmp['manager'] = $user->nickname ?: $user->username;
                            $tmp['manager_id'] = $user->id;
                        }
                    }
                }
                // VIP\ERP
                // $tmp['is_vip'] = $shop->vip_status;
                // $tmp['is_erp'] = $shop->erp ?? 0;
                $tmp['is_erp'] = $shop->erp_status === 1;
                $tmp['erp_status'] = $shop->erp_status;
                $tmp['vip_status_new'] = $shop->vip_status_new;
                // 赋值
                $data[] = $tmp;
            }
        }

        return $this->page($shops, $data, 'data');
    }

    public function all(Request $request)
    {
        $search_key = $request->get("search_key", "");

        $query = Shop::with(['manager' => function ($query) {
            $query->select('id', 'nickname');
        }])->select("id", "shop_name", "city","mtwm","ele","manager_id");

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
        $warehouse = $request->get('warehouse');
        if (is_null($warehouse) || !is_numeric($warehouse)) {
            return $this->error('仓库ID不能为空');
        }
        if (!$stime = $request->get('stime')) {
            return $this->error('起始时间不能为空');
        }
        if (!$etime = $request->get('etime')) {
            return $this->error('结束时间不能为空');
        }

        $print = $request->get('print', false);

        if ($setting = OrderSetting::where("shop_id", $shop_id)->first()) {
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
            // OrderSetting::create([
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
     * 审核管理-三方门店ID审核列表
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

    /**
     * 审核管理-三方门店ID审核操作
     * @data 2021/12/1 4:27 下午
     */
    public function apply_three_id_save(Request $request)
    {
        $id = $request->get('id', 0);
        $status = $request->get('status', 0);

        if (!$apply = ShopThreeId::find($id)) {
            return $this->error('门店不存在');
        }

        if ($status == 1) {
            if (!$shop = Shop::find($apply->shop_id)) {
                return $this->error('门店不存在');
            }

            if (($mtwm = $apply->mtwm) && !$shop->mtwm) {
                if ($mtwm && ($_shop = Shop::where('mtwm', $mtwm)->first())) {
                    return $this->error("美团ID已存在：绑定门店名称[{$_shop->shop_name}]");
                }
                $shop->mtwm = $mtwm;
                $shop->chufang_mt = $mtwm;

                $params = ['app_poi_codes' => $mtwm];
                $mk = app('minkang');
                $mk_shops = $mk->getShops($params);
                if (!empty($mk_shops['data'])) {
                    $shop->waimai_mt = $mtwm;
                    $shop->meituan_bind_platform = 4;
                }
                $mq = app('meiquan');
                $mq_shops = $mq->getShops($params);
                if (!empty($mq_shops['data'])) {
                    $shop->waimai_mt = $mtwm;
                    $shop->meituan_bind_platform = 31;
                }
            }
            if (($ele = $apply->ele) && !$shop->ele) {
                if ($ele && ($_shop = Shop::where('ele', $ele)->first())) {
                    return $this->error("饿了ID已存在：绑定门店名称[{$_shop->shop_name}]");
                }
                $shop->ele = $ele;
                $shop->chufang_ele = $ele;

                $e = app('ele');
                $data = $e->shopInfo($ele);
                if (isset($data['body']['errno']) && $data['body']['errno'] === 0) {
                    $shop->waimai_ele = $ele;
                }
            }
            if (($jddj = $apply->jddj) && !$shop->jddj) {
                $shop->jddj = $jddj;
                $shop->chufang_jddj = $ele;
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
        if (!$request->user()->hasAllPermissions(['update_three_id', 'admin_shop_shop'])) {
            throw new NoPermissionException();
        }
        $shop_id = $request->get('id', 0);

        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }

        if (ShopThreeId::where('shop_id', $shop_id)->first()) {
            return $this->error('该门店有待审核ID，请先审核');
        }

        $mtwm = $request->get('mtwm');
        $ele = $request->get('ele');
        $jddj = $request->get('jddj');

        if (!is_null($mtwm)) {
            if ($mtwm && ($_shop = Shop::where('mtwm', $mtwm)->first())) {
                if ($_shop->id != $shop_id) {
                    return $this->error("美团ID已存在：绑定门店名称[{$_shop->shop_name}]");
                }
            }
            $shop->mtwm = $mtwm;
            if ($shop->second_category == 200001) {
                $shop->chufang_mt = $mtwm;
            }

            $params = ['app_poi_codes' => $mtwm];
            $mk = app('minkang');
            $mk_shops = $mk->getShops($params);
            if (!empty($mk_shops['data'])) {
                $mt_shop_name = $mk_shops[0]['name'] ?? '';
                if ($mt_shop_name) {
                    $shop->wm_shop_name = $mt_shop_name;
                    $shop->mt_shop_name = $mt_shop_name;
                }
                $shop->waimai_mt = $mtwm;
                $shop->meituan_bind_platform = 4;
            }
            $mq = app('meiquan');
            $mq_shops = $mq->getShops($params);
            if (!empty($mq_shops['data'])) {
                $mt_shop_name = $mq_shops[0]['name'] ?? '';
                if ($mt_shop_name) {
                    $shop->wm_shop_name = $mt_shop_name;
                    $shop->mt_shop_name = $mt_shop_name;
                }
                $shop->waimai_mt = $mtwm;
                $shop->meituan_bind_platform = 31;
            }
        }
        if (!is_null($ele)) {
            if ($ele && ($_shop = Shop::where('ele', $ele)->first())) {
                if ($_shop->id != $shop_id) {
                    return $this->error("饿了ID已存在：绑定门店名称[{$_shop->shop_name}]");
                }
            }
            $shop->ele = $ele;
            if ($shop->second_category == 200001) {
                $shop->chufang_ele = $ele;
            }

            $e = app('ele');
            $data = $e->shopInfo($ele);
            if (isset($data['body']['errno']) && $data['body']['errno'] === 0) {
                $ele_shop_name = $data['body']['data']['name'] ?? '';
                if (!$shop->wm_shop_name) {
                    $shop->wm_shop_name = $ele_shop_name;
                }
                $shop->ele_shop_name = $ele_shop_name;
                $shop->waimai_ele = $ele;
            }
        }
        if (!is_null($jddj)) {
            $shop->jddj = $jddj;
        }

        $shop->save();

        return $this->success($shop);
    }

    public function moneyAdd(Request $request)
    {
        if (!$shop = Shop::find($request->get('id', 0))) {
            return $this->error('门店不存在');
        }

        $running_add = floatval($request->get('running_add', 0));
        $running_manager_add = floatval($request->get('running_manager_add', 0));

        if ($running_manager_add > $running_add) {
            return $this->error('经理抽佣不能大于加价金额');
        }

        $shop->running_add = $running_add;
        $shop->running_manager_add = $running_manager_add;
        $shop->save();

        return $this->success();
    }

    /**
     * 更改门店城市经理
     * @data 2022/5/1 1:07 下午
     */
    public function manager_update(Request $request)
    {
        if (!$shop = Shop::find($request->get('shop_id'))) {
            return $this->error('门店不存在');
        }
        if (!$user = User::find($request->get('user_id'))) {
            return $this->error('用户不存在');
        }
        if (!$user->hasRole('city_manager')) {
            return $this->error('选择的不是城市经理');
        }

        $manager_ids = DB::table('model_has_roles')->where('role_id', 4)->pluck('model_id')->toArray();
        DB::transaction(function () use ($manager_ids, $shop, $user) {
            DB::table('user_has_shops')->where('shop_id', $shop->id)->whereIn('user_id', $manager_ids)->delete();
            // DB::table('shops')->where('shop_id', $shop->id)->update(['manager_id' => $user->id]);
            $shop->manager_id = $user->id;
            $shop->save();
            DB::table('user_has_shops')->insert(['shop_id' => $shop->id, 'user_id' => $user->id]);
        });
        return $this->success($manager_ids);
    }

    public function transfer(Request $request)
    {
        if (!$request->user()->hasRole('super_man')) {
            return $this->error('无权限此操作');
        }
        if (!$new = $request->get('new')) {
            return $this->error('目标账号不能为空');
        }
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('目标账号不能为空');
        }
        if (!$user = User::where('name', $new)->first()) {
            return $this->error('目标用户不存在，请核对');
        }
        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }
        if ($shop->user_id == $user->id) {
            return $this->error('该门店已在此账号下，无需转移');
        }

        try {
            DB::transaction(function () use ($user, $shop, $request) {
                $old_user_id = $shop->user_id;
                $new_user_id = $user->id;
                // 日志
                DB::table('shop_transfers')->insert([
                    'shop_id' => $shop->id,
                    'old_user_id' => $old_user_id,
                    'new_user_id' => $new_user_id,
                    'action_user_id' => $request->user()->id,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                ]);
                // 修改门店
                DB::table('shops')->where('id', $shop->id)->update(['user_id' => $user->id, 'own_id' => $user->id]);
                // $shop->user_id = $new_user_id;
                // $shop->own_id = $new_user_id;
                // $shop->save();
                // 修改权限
                DB::table('user_has_shops')->where('shop_id', $shop->id)
                    ->where('user_id', $old_user_id)->update(['user_id' => $user->id]);
                // 修改外卖资料
                DB::table('online_shops')->where('shop_id', $shop->id)->update(['user_id' => $user->id]);
                // 修改跑腿订单
                DB::table('orders')->where('shop_id', $shop->id)->update(['user_id' => $user->id]);
            });
        } catch (\Exception $exception) {
            return $this->error('操作失败，请稍后再试');
        }
        return $this->success();
    }

    /**
     * ERP状态切换
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2023/5/20 10:25 下午
     */
    public function erpStatus(Request $request)
    {
        if (!$shop = Shop::find($request->get('shop_id', 0))) {
            return $this->error('门店不存在');
        }
        if ($shop->erp_status != 1) {
            $shop->erp_status = 1;
        } else {
            $shop->erp_status = 2;
        }
        $shop->save();

        return $this->success();
    }

    public function vipStatus(Request $request)
    {
        if (!$shop = Shop::find($request->get('shop_id', 0))) {
            return $this->error('门店不存在');
        }
        if ($shop->vip_status_new != 1) {
            $shop->vip_status_new = 1;
        } else {
            $shop->vip_status_new = 0;
        }
        $shop->save();

        return $this->success();
    }

    /**
     * 佣金设置
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2023/6/6 5:01 下午
     */
    public function commissionSet(Request $request)
    {
        if (!$shop = Shop::find($request->get('id', 0))) {
            return $this->error('门店不存在');
        }

        $commission_mt = floatval($request->get('commission_mt', 0));
        $commission_ele = floatval($request->get('commission_ele', 0));

        if ($commission_mt < 0 || $commission_mt > 100) {
            return $this->error('请正确填写美团代运营费率');
        }
        if ($commission_ele < 0 || $commission_ele > 100) {
            return $this->error('请正确填写饿了么代运营费率');
        }

        $shop->commission_mt = $commission_mt;
        $shop->commission_ele = $commission_ele;
        $shop->save();

        return $this->success();
    }
}
