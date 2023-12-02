<?php

namespace App\Http\Controllers;

use App\Jobs\CreateMtShop;
use App\Libraries\MeiTuanKaiFang\Tool;
use App\Models\Contract;
use App\Models\ManagerCity;
use App\Models\Shop;
use App\Models\ShopRange;
use App\Models\ShopThreeId;
use App\Models\User;
use App\Traits\NoticeTool2;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{

    use NoticeTool2;

    public function __construct()
    {
        $this->notice_tool2_prefix = '门店管理';
        $this->middleware('auth:api');
    }

    /**
     * 门店列表
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $search_key = $request->get('search_key', '');
        $contract_status = $request->get('contract_status', 0);
        $online_status = $request->get('online_status', 0);
        $shop_status = $request->get('shop_status', 0);
        $query = Shop::with(['online_shop' => function($query) {
            $query->select("shop_id", "contract_status");
        }, 'users', 'apply_three_id', 'manager', 'contract','user','shippers']);

        // 关键字搜索
        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
               $query->where('shop_id', 'like', "%{$search_key}%")
                   ->orWhere('id', 'like', "%{$search_key}%")
                   ->orWhere('shop_name', 'like', "%{$search_key}%")
                   ->orWhere('city', 'like', "%{$search_key}%")
                   ->orWhere('waimai_ele', 'like', "%{$search_key}%")
                   ->orWhere('waimai_mt', 'like', "%{$search_key}%")
                   ->orWhere('mtwm', 'like', "%{$search_key}%")
                   ->orWhere('ele', 'like', "%{$search_key}%")
                   ->orWhere('contact_name', 'like', "%{$search_key}%")
                   ->orWhere('contact_phone', 'like', "%{$search_key}%");
            });
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

        // 判断角色
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            // \Log::info("没有全部门店权限");
        // if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('id', $request->user()->shops()->pluck('id'));
        }
        $shops = $query->where("status", ">=", 0)->orderBy('id', 'desc')->paginate($page_size);
        // return $shops;

        // 城市经理
        // $managers = User::select('id')->whereHas('roles', function ($query)  {
        //     $query->where('name', 'city_manager');
        // })->where('status', 1)->where('id', '>', 2000)->get()->pluck('id')->toArray();

        $result = [];
        $data = [];
        $meiquan = null;
        $minkang = null;
        $canyin = null;
        $ele = null;

        if (!empty($shops)) {
            $contracts = Contract::select('id', 'name')->get()->toArray();
            foreach ($shops as $shop) {
                $tmp['id'] = $shop->id;
                $tmp['category'] = $shop->category;
                $tmp['category_second'] = $shop->second_category;
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
                $tmp['shop_id_zb'] = $shop->shop_id_zb;
                $tmp['mt_shop_id'] = $shop->mt_shop_id;
                $tmp['city'] = $shop->city;
                $tmp['mt_name'] = $shop->mt_name;
                $tmp['mt_shipping_time'] = $shop->mt_shipping_time;
                $tmp['mt_open'] = $shop->mt_open;
                $tmp['mt_online'] = $shop->mt_online;
                $tmp['ele_name'] = $shop->ele_name;
                $tmp['ele_shipping_time'] = $shop->ele_shipping_time;
                $tmp['ele_open'] = $shop->ele_open;
                $tmp['mt_jie'] = $shop->mt_jie;
                $tmp['print_auto'] = $shop->print_auto;

                // ------------------------------- 查询门店状态 -------------------------------
                if ($shop->waimai_mt) {
                    if ($shop->meituan_bind_platform === 4) {
                        if (!$minkang) {
                            $minkang = app('minkang');
                        }
                        $shop_status_params = ['app_poi_codes' => $shop->waimai_mt];
                        $mt_res = $minkang->getShopInfoByIds($shop_status_params);
                        if (isset($mt_res['data'][0])) {
                            // \Log::info('aaa', $mt_res['data'][0]);
                            $tmp['mt_name'] = $mt_res['data'][0]['name'];
                            $tmp['mt_shipping_time'] = $mt_res['data'][0]['shipping_time'];
                            $tmp['mt_open'] = $mt_res['data'][0]['open_level'];
                            $tmp['mt_online'] = $mt_res['data'][0]['is_online'];
                        } else {
                            $tmp['mt_shipping_time'] = '未获取到门店信息';
                        }
                    } else if ($shop->meituan_bind_platform === 31) {
                        if (!$meiquan) {
                            $meiquan = app('meiquan');
                        }
                        $shop_status_params = ['app_poi_codes' => $shop->waimai_mt, 'access_token' => $meiquan->getShopToken($shop->waimai_mt)];
                        $mt_res = $meiquan->getShopInfoByIds($shop_status_params);
                        // \Log::info('aaaaaaaaa', $mt_res);
                        if (isset($mt_res['data'][0])) {
                            $tmp['mt_name'] = $mt_res['data'][0]['name'];
                            $tmp['mt_shipping_time'] = $mt_res['data'][0]['shipping_time'];
                            $tmp['mt_open'] = $mt_res['data'][0]['open_level'];
                            $tmp['mt_online'] = $mt_res['data'][0]['is_online'];
                        } else {
                            $tmp['mt_shipping_time'] = '未获取到门店信息';
                        }
                    } else if ($shop->meituan_bind_platform === 25) {
                        if (!$canyin) {
                            $canyin = app('mtkf');
                        }
                        $shop_status_params = ['epoiIds' => $shop->waimai_mt];
                        $mt_res = $canyin->poi_mget($shop_status_params, $shop->waimai_mt);
                        // \Log::info('bbbbbbbbbbbb', $mt_res);
                        if (isset($mt_res['data'][0])) {
                            $tmp['mt_name'] = $mt_res['data'][0]['name'];
                            $tmp['mt_shipping_time'] = $mt_res['data'][0]['shipping_time'];
                            $tmp['mt_open'] = $mt_res['data'][0]['open_level'];
                            $tmp['mt_online'] = $mt_res['data'][0]['is_online'];
                        } else {
                            $tmp['mt_shipping_time'] = '未获取到门店信息';
                        }
                    }
                }
                if ($shop->waimai_ele) {
                    if (!$ele) {
                        $ele = app('ele');
                    }
                    $ele_res = $ele->shopInfo($shop->waimai_ele);
                    $ele_res2 = $ele->shopBusstatus($shop->waimai_ele);
                    // \Log::info('cccccc', [$ele_res]);
                    if (!empty($ele_res['body']['data']['business_time2']['normal_business_time_list'][0]['business_hour'])) {
                        $ele_time_list = $ele_res['body']['data']['business_time2']['normal_business_time_list'][0]['business_hour'];
                        if (isset($ele_time_list['type']) && !empty($ele_time_list['ranges'])) {
                            $tmp['ele_name'] = $ele_res['body']['data']['supplier_name'];
                            $tmp['ele_shipping_time'] = $ele_time_list['ranges'][0]['start_time'] . '-' . $ele_time_list['ranges'][0]['end_time'];
                            $tmp['ele_open'] = $ele_res2['body']['data']['shop_busstatus'] ?? 1;
                        }
                    }
                }
                // ------------------------------- 查询门店状态 -------------------------------

                // 跑腿平台
                $shippers = [];
                if (!empty($shop->shippers)) {
                    foreach ($shop->shippers as $shipper) {
                        $shippers[$shipper->platform] = ['platform' => $shipper->platform, 'type' => 2, 'platform_id' => $shipper->three_id];
                    }
                }
                if ($shop->shop_id) {
                    $shippers[1] = ['platform' => 1, 'type' => 1, 'platform_id' => $shop->shop_id];
                }
                if ($shop->shop_id_fn) {
                    $shippers[2] = ['platform' => 2, 'type' => 1, 'platform_id' => $shop->shop_id_fn];
                }
                if ($shop->shop_id_ss) {
                    $shippers[3] = ['platform' => 3, 'type' => 1, 'platform_id' => $shop->shop_id_ss];
                }
                if ($shop->shop_id_mqd) {
                    $shippers[4] = ['platform' => 4, 'type' => 1, 'platform_id' => $shop->shop_id_mqd];
                }
                if ($shop->shop_id_dd) {
                    $shippers[5] = ['platform' => 5, 'type' => 1, 'platform_id' => $shop->shop_id_dd];
                }
                if ($shop->shop_id_uu) {
                    $shippers[6] = ['platform' => 6, 'type' => 1, 'platform_id' => $shop->shop_id_uu];
                }
                if ($shop->shop_id_sf) {
                    $shippers[7] = ['platform' => 7, 'type' => 1, 'platform_id' => $shop->shop_id_sf];
                }
                $tmp['shippers'] = array_values($shippers);

                // 外卖资料
                $tmp['material'] = $shop->material;
                // 商城
                $tmp['shopping'] = $shop->auth;
                // 三方ID
                $tmp['meituan_bind_platform'] = $shop->meituan_bind_platform;
                $tmp['mtwm'] = $shop->mtwm ?: $shop->waimai_mt;
                $tmp['mtwm_status'] = (bool) $shop->mtwm;
                $tmp['mtwm_apply_id'] = $shop->apply_three_id->mtwm ?? '';
                $tmp['mtwm_apply_status'] = (bool) ($shop->apply_three_id->mtwm ?? '');
                $tmp['ele'] = $shop->waimai_ele ?: $shop->ele;
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
                $tmp['manager'] = $shop->manager->nickname ?? '';
                // 城市经理
                // if (!empty($shop->users)) {
                //     foreach ($shop->users as $user) {
                //         if (in_array($user->id, $managers)) {
                //             $tmp['manager'] = $user->nickname ?: $user->username;
                //         }
                //     }
                // }
                // VIP\ERP
                $tmp['is_vip'] = $shop->vip_status;
                $tmp['vip_status_new'] = $shop->vip_status_new;
                $tmp['is_erp'] = $shop->erp_status === 1;
                // 门店建店人信息
                // $tmp['running_money'] = $shop->user->money ?? 0;
                // $tmp['operate_money'] = $shop->user->operate_money ?? 0;
                $tmp['running_money'] = (float) isset($shop->user->money) ? $shop->user->money : 0;
                $tmp['operate_money'] = (float) isset($shop->user->operate_money) ? $shop->user->operate_money : 0;
                // 赋值
                $data[] = $tmp;
            }
        }

        $result['page'] = $shops->currentPage();
        $result['current_page'] = $shops->currentPage();
        $result['total'] = $shops->total();
        $result['page_total'] = $shops->lastPage();
        $result['last_page'] = $shops->lastPage();
        $result['list'] = $data;
        $result['data'] = $data;
        return $this->success($result);
        // return $this->page($shops, $data, 'data');
    }

    public function update(Shop $shop, Request $request)
    {
        if (!$id = intval($request->get("id"))) {
            return $this->error("门店不存在");
        }
        if ($id != $shop->id) {
            return $this->error("参数错误");
        }

        if (!$shop_name = trim($request->get("shop_name", ""))){
            return $this->error("门店名称不能为空");
        }
        $shop->shop_name = $shop_name;

        if (!$contact_name = trim($request->get("contact_name", ""))){
            return $this->error("联系人不能为空");
        }
        $shop->contact_name = $contact_name;

        $contact_phone = trim($request->get("contact_phone", ""));
        $contact_phone = str_replace(' ', '', $contact_phone);
        if (!$contact_phone){
            return $this->error("联系人电话不能为空");
        }
        $shop->contact_phone = $contact_phone;

        if (!$shop_address = trim($request->get("shop_address", ""))){
            return $this->error("门店地址不能为空");
        }
        $shop->shop_address = $shop_address;

        if (!$shop_lng = trim($request->get("shop_lng", ""))){
            return $this->error("经度不能为空");
        }
        $shop->shop_lng = $shop_lng;

        if (!$shop_lat = trim($request->get("shop_lat", ""))){
            return $this->error("纬度不能为空");
        }
        $shop->shop_lat = $shop_lat;

        if ($shop->save()) {
            if ($shop->shop_id_fn) {
                $fengniao = app("fengniao");
                $fengniao->updateShop($shop);
            }
            if ($shop->shop_id) {
                $meituan = app("meituan");
                $meituan->shopUpdate($shop);
            }
            if ($shop->shop_id_ss) {
                $shansong = app("shansong");
                $shansong->updateShop($shop);
            }
            if ($shop->shop_id_dd) {
                $dada = app("dada");
                $dada->updateShop($shop);
            }

            return $this->success();
        }

        return $this->error("修改失败，请稍后再试");
    }

    /**
     * 能看到的所有门店
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data dateTime
     */
    public function shopAll(Request $request)
    {
        $query = Shop::select("id", "shop_name");

        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        // if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('id', $request->user()->shops()->pluck('id'));
        }

        $shops = $query->orderBy('id', 'desc')->get();

        return $this->success($shops);
    }

    public function get_shop_search(Request $request)
    {
        $shops = [];
        $name = $request->get('name');

        if ($name) {
            $query = Shop::select("id", "shop_name")->where('status', '>', 0);

            $query->where('shop_name', 'like', "%{$name}%");

            if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            // if (!$request->user()->hasRole('super_man')) {
                $query->whereIn('id', $request->user()->shops()->pluck('id'));
            }

            $shops = $query->orderBy('id', 'desc')->get();
        }

        return $this->success($shops);
    }

    // 添加用户返回没有绑定的门店
    public function wei()
    {
        $wei = Shop::select('id','shop_name')->where('user_id', 0)->get();
        if (!empty($wei)) {
            foreach ($wei as $v) {
                $v->key = (string) $v->id;
                $v->title = (string) $v->shop_name;
            }
        }
        return $this->success($wei);
    }

    // 返回用户下所有可发单的门店
    public function all(Request $request)
    {
        $query = Shop::select('id','shop_id','shop_name','contact_name','contact_phone','shop_address')
            ->where('status', 40);

        // if ($request->user()->hasRole('super_man')) {
        //     $shop = $query->get();
        // } else {
        //     $shop = $query->whereIn("id", $request->user()->shops()->pluck("id"))->get();
        // }
        $shop = $query->whereIn("id", $request->user()->shops()->pluck("id"))->get();

        return $this->success($shop);
    }

    /**
     * 保存门店
     * @param Request $request
     * @param Shop $shop
     * @return mixed
     */
    public function store(Request $request, Shop $shop)
    {
        if (!$yyzz = $request->get('yyzz')) {
            return $this->error('营业执照编号不能为空', 422);
        }

        if ($_shop = Shop::where('yyzz', $yyzz)->first()) {
            return $this->error("该营业执照已存在，请核对，绑定门店名称[{$_shop->shop_name}]", 422);
        }

        $user = Auth::user();
        $shop->fill($request->all());


        // $contact_phone = str_replace(' ', '', $contact_phone);
        // $shop->contact_phone = str_replace(' ', '', $shop->contact_phone);

        if (!$request->get("city")) {
            $lng = $request->get("shop_lng");
            $lat = $request->get("shop_lat");
            $url="http://restapi.amap.com/v3/geocode/regeo?output=json&location=".$lng.",".$lat."&key=59c3b9c0a69978649edb06bbaccccbe9";
            if($result=file_get_contents($url)) {
                $result = json_decode($result, true);
                if (!empty($result['status']) && $result['status'] == 1) {
                    // \Log::info("门店城市信息返回", [$result]);
                    $shop->province = $result['regeocode']['addressComponent']['province'] ?? '';
                    $shop->district = $result['regeocode']['addressComponent']['district'] ?? '';
                    $shop->city = $result['regeocode']['addressComponent']['city'] ?: $result['regeocode']['addressComponent']['province'];
                    $shop->citycode = $result['regeocode']['addressComponent']['citycode'];
                    $shop->area = $result['regeocode']['addressComponent']['district'] ?: '';
                }
            }
        }

        if (!$shop->shop_id) {
            unset($shop->shop_id);
        }

        if ($shop->citycode == '1433') {
            $shop->citycode = '0433';
        }

        if ($shop->citycode == '1558') {
            $shop->citycode = '0558';
        }

        $shop->status = 40;
        $shop->user_id = $user->id;
        $shop->own_id = $user->id;
        $shop->contact_phone = str_replace(' ', '', $shop->contact_phone);

        // 城市经理
        $manager_id = 2415;
        $city = ManagerCity::where('city', $shop->city ?? '')->first();
        if ($city) {
            $manager_id = $city->user_id;
        }
        $shop->manager_id = $manager_id;

        if ($shop->save()) {
            $user->shops()->attach($shop->id);
            dispatch(new CreateMtShop($shop));
            if ($shop->manager_id && ($manager = User::find($shop->manager_id))) {
                $manager->shops()->attach($shop);
            }
            return $this->success([]);
        }

        return $this->error("创建失败");
    }

    /**
     * 门店详情
     * @param Shop $shop
     * @return mixed
     */
    public function show(Shop $shop)
    {
        $res = [
            "id" => $shop->id,
            "shop_name" => $shop->shop_name,
            "category" => $shop->category,
            "second_category" => $shop->second_category,
            "contact_name" => $shop->contact_name,
            "contact_phone" => $shop->contact_phone,
            "yyzz" => $shop->yyzz,
            "yyzz_name" => $shop->yyzz_name,
            "yyzz_img" => $shop->yyzz_img,
            "shop_lng" => $shop->shop_lng,
            "shop_lat" => $shop->shop_lat,
            "shop_address" => $shop->shop_address,
            "shop_id" => $shop->shop_id,
            "shop_id_fn" => $shop->shop_id_fn,
            "shop_id_sf" => $shop->shop_id_sf,
            "shop_id_ss" => $shop->shop_id_ss,
            "city" => $shop->city,
            "material" => $shop->material,
            "shopping" => $shop->auth,
            "mtwm" => (bool) $shop->mtwm,
            "auto" => (bool) $shop->mt_shop_id,
            "status" => $shop->status,
            "manager" => $shop->manager->nickname ?? '',
            // "manager_phone" => $shop->manager->phone ?? '',
            "created_at" => date("Y-m-d H:i:s", strtotime($shop->created_at)),
        ];

        return $this->success($res);
    }

    /**
     * 门店配送范围
     */
    public function range(Shop $shop)
    {
        $resrange = [];
        if (!$shop->range) {
            ShopRange::create(['shop_id' => $shop->id, 'range' => '', 'range_fn' => '']);
        }

        $shop->load('range');
        if ($shop->range) {
            if ($shop->shop_id) {
                if (!$shop->range->range) {
                    $meituan = app("meituan");
                    $res = $meituan->getShopArea(['delivery_service_code' => 4011, 'shop_id' => $shop->shop_id]);
                    if (isset($res['data']['scope'])) {
                        $scope = [];
                        $range = json_decode($res['data']['scope'], true);
                        if (!empty($range)) {
                            foreach ($range as $k => $v) {
                                $tmp[] = $v['y'];
                                $tmp[] = $v['x'];
                                $scope[] = $tmp;
                                unset($tmp);
                            }
                        }
                        $shop->range->range = json_encode($scope);
                        $shop->range->save();
                        $_range['name'] = '美团';
                        $_range['range'] = $scope;
                        $resrange[] = $_range;
                    }
                } else {
                    $_range['name'] = '美团';
                    $_range['range'] = json_decode($shop->range->range);
                    $resrange[] = $_range;
                }
            }
            if ($shop->shop_id_fn) {
                if (!$shop->range->range_fn) {
                    $fengniao = app("fengniao");
                    $resfn = $fengniao->getArea($shop->shop_id_fn);
                    if (isset($resfn['data']['range_list'][0]['ranges'])) {
                        $scope = [];
                        // $range = json_decode($res['data']['scope'], true);
                        if (!empty($resfn['data']['range_list'][0]['ranges'])) {
                            foreach ($resfn['data']['range_list'][0]['ranges'] as $k => $v) {
                                $tmp[] = (float) $v['longitude'];
                                $tmp[] = (float) $v['latitude'];
                                $scope[] = $tmp;
                                unset($tmp);
                            }
                        }
                        $shop->range->range_fn = json_encode($scope);
                        $shop->range->save();
                        $_range['name'] = '蜂鸟';
                        $_range['range'] = $scope;
                        $resrange[] = $_range;
                    }
                } else {
                    $_range['name'] = '蜂鸟';
                    $_range['range'] = json_decode($shop->range->range_fn);
                    $resrange[] = $_range;
                }
            }
        }

        $data = [
            'id' => $shop->id,
            'lng' => $shop->shop_lng,
            'lat' => $shop->shop_lat,
            'shop' => [$shop->shop_lng, $shop->shop_lat],
            'range' => $resrange,
        ];

        return $this->success($data);
    }

    /**
     * 配送范围获取
     */
    public function rangeByShopId(Request $request)
    {
        if (!$shop = Shop::where('shop_id', $request->get('shop_id', 0))->first()) {
            return $this->error('门店不存在');
        }

        if (!$shop->range) {
            $meituan = app("meituan");
            $res = $meituan->getShopArea(['delivery_service_code' => 4011, 'shop_id' => $shop->shop_id]);
            if (isset($res['data']['scope'])) {
                $scope = [];
                $range = json_decode($res['data']['scope'], true);
                if (!empty($range)) {
                    foreach ($range as $k => $v) {
                        $tmp[] = $v['y'];
                        $tmp[] = $v['x'];
                        $scope[] = $tmp;
                        unset($tmp);
                    }
                }
                ShopRange::create(['shop_id' => $shop->id, 'range' => json_encode($scope)]);
                $shop->load('range');
            }
        }

        $data = [
            'id' => $shop->id,
            'lng' => $shop->shop_lng,
            'lat' => $shop->shop_lat,
            'range' => isset($shop->range->range) ? json_decode($shop->range->range) : [],
        ];

        return $this->success($data);
    }

    /**
     * 同步药剂特药店
     * @param Request $request
     * @return mixed
     */
    public function sync(Request $request)
    {
        $type = $request->get('type', 0);
        $shop_id = $request->get('shop_id', 0);
        $cat = $request->get('category', 0);

        \Log::info('message',[$type,$cat,gettype($type),gettype($cat)]);

        if (!$type || !$shop_id || !$cat) {
            return $this->error('参数错误');
        }

        $category = 0;
        $second_category = 0;

        if (intval($cat) === 2) {
            $second_category = 240001;
        }

        if (intval($type) === 1) {
            $category = 200;
            $second_category = 200001;
        } elseif (intval($type) === 2) {
            $category = 240;
        }

        $meituan = app("yaojite");
        $shop_id = str_replace(' ', '', $shop_id);
        $shop_id = str_replace('，', ',', $shop_id);
        $res = $meituan->getShops(['app_poi_codes' => $shop_id]);
        if (!empty($res) && !empty($res['data'])) {
            $success = [];
            $ps = app("meituan");
            foreach ($res['data'] as $shop_res) {
                if (Shop::where('shop_id', $shop_res['app_poi_code'])->first()) {
                    continue;
                }
                $business_hours = [];
                if (isset($shop_res['shipping_time'])) {
                    $hours_data = explode(';;', $shop_res['shipping_time']);
                    if (!empty($hours_data)) {
                        foreach ($hours_data as $hours) {
                            $_tmp = explode('-', $hours);
                            array_push($business_hours, ['beginTime' => $_tmp[0], 'endTime' => $_tmp[1]]);
                        }
                    }
                }
                $shop = new Shop([
                    'shop_id' => $shop_res['app_poi_code'],
                    'shop_name' => $shop_res['name'],
                    'category' => $category,
                    'second_category' => $second_category,
                    'contact_name' => '臧润书',
                    'contact_phone' => '13843209606',
                    'shop_address' => $shop_res['address'],
                    'shop_lng' => $shop_res['longitude'] / 1000000,
                    'shop_lat' => $shop_res['latitude'] / 1000000,
                    'coordinate_type' => 0,
                    'business_hours' => empty($business_hours) ? [['beginTime' => '00:00', 'endTime' => '24:00']] : $business_hours,
                ]);
                if ($shop->save()) {
                    if (!$shop->shop_id) {
                        $shop->shop_id = $shop->id;
                        $shop->save();
                    }
                    array_push($success, $shop->shop_id);
                    dispatch(new CreateMtShop($shop));
                }
            }
            return $this->success($success);
        }
        return $this->error('未获取到门店');
    }

    /**
     * 审核门店
     * @param Shop $shop
     * @return mixed
     * @author zhangzhen
     * @data dateTime
     */
    public function examine(Shop $shop)
    {
        // $shop->status = 20;
        // $shop->save();

        dispatch(new CreateMtShop($shop));

        return $this->success('审核成功');
    }

    /**
     * 设置默认收货门店
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2020/12/9 2:36 下午
     */
    public function setUserShop(Request $request)
    {
        $user = Auth::user();

        if (!$shop_id = $request->get("shop_id")) {
            return $this->error("参数错误");
        }

        if (!$shop = Shop::where(['id' => $shop_id, 'user_id' => $user->id])->first()) {
            return $this->error("门店不存在");
        }

        $user->shop_id = $shop_id;
        $user->save();

        return $this->success();
    }

    /**
     * 设置默认发单门店
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2020/12/9 2:36 下午
     */
    public function setRunningShop(Request $request)
    {
        $user = Auth::user();

        if (!$shop_id = $request->get("shop_id")) {
            return $this->error("参数错误");
        }

        if (!$shop = Shop::where(['id' => $shop_id, 'own_id' => $user->id])->first()) {
            return $this->error("门店不存在");
        }

        Shop::where(['user_id' => $user->id])->update(['running_select' => 0]);

        $shop->running_select = 1;
        $shop->save();


        return $this->success();
    }

    /**
     * 绑定门店-外卖订单
     */
    public function bindingTakeout(Request $request)
    {
        if (!$shop = Shop::find($request->get("shop_id", 0))) {
            return $this->error("门店不存在");
        }

        if ($mtwm = $request->get("mtwm", '')) {
            if ($_shop = Shop::where('waimai_mt', $mtwm)->first()) {
                return $this->error("美团ID已存在：绑定门店名称[{$_shop->shop_name}]");
            }
            $status = false;
            $params = ['app_poi_codes' => $mtwm];

            $mk = app('minkang');
            $mk_shops = $mk->getShops($params);
            if (!$status && !empty($mk_shops['data'])) {
                $mt_shop_name = $mk_shops['data'][0]['name'] ?? '';
                if ($mt_shop_name) {
                    $shop->wm_shop_name = $mt_shop_name;
                    $shop->mt_shop_name = $mt_shop_name;
                }
                $shop->waimai_mt = $mtwm;
                $shop->meituan_bind_platform = 4;
                $shop->save();
                return $this->success();
            }
            $mq = app('meiquan');
            $mq_shops = $mq->getShops($params);
            if (!$status && !empty($mq_shops['data'])) {
                $mt_shop_name = $mq_shops['data'][0]['name'] ?? '';
                if ($mt_shop_name) {
                    $shop->wm_shop_name = $mt_shop_name;
                    $shop->mt_shop_name = $mt_shop_name;
                }
                $shop->waimai_mt = $mtwm;
                $shop->meituan_bind_platform = 31;
                $shop->save();
                return $this->success();
            }
            // $qq = app('qinqu');
            // $qq_shops = $qq->getShops($params);
            // if (!$status && !empty($qq_shops['data'])) {
            //     $shop->waimai_mt = $mtwm;
            //     $shop->meituan_bind_platform = 5;
            //     $shop->save();
            //     return $this->success();
            // }
            return $this->error('该门店没有授权,请参考说明授权');
        }

        if ($ele = $request->get("ele", '')) {
            if ($_shop = Shop::where('waimai_ele', $ele)->first()) {
                return $this->error("饿了么ID已存在：绑定门店名称[{$_shop->shop_name}]");
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
                $shop->save();
                return $this->success();
            }
            return $this->error('该门店没有授权,请参考说明授权');
        }

        return $this->success();
    }

    public function bindingChufang(Request $request)
    {
        if (!$shop = Shop::find($request->get("shop_id", 0))) {
            return $this->error("门店不存在");
        }

        if ($mtwm = $request->get("mtwm", '')) {
            $shop->mtwm_cf = $mtwm;
            $shop->save();
        }

        if ($ele = $request->get("ele", '')) {
            $shop->ele_cf = $ele;
            $shop->save();
        }

        return $this->success();
    }

    /**
     * 绑定门店-自动接单
     */
    public function binding(Request $request)
    {
        $mtwm = $request->get("mtwm", '');
        $ele = $request->get("ele", '');

        if (!$shop = Shop::find($request->get("shop_id", 0))) {
            return $this->error("门店不存在");
        }

        if ($mtwm) {
            if ($_shop = Shop::where('mt_shop_id', $mtwm)->first()) {
                return $this->error("美团ID已存在：绑定门店名称[{$_shop->shop_name}]");
            }
            if ($_shop = Shop::where('auto_mtwm', $mtwm)->first()) {
                return $this->error("美团ID已存在：绑定门店名称[{$_shop->shop_name}]");
            }
        }
        if ($ele) {
            if ($_shop = Shop::where('ele_shop_id', $ele)->first()) {
                return $this->error("饿了ID已存在：绑定门店名称[{$_shop->shop_name}]");
            }
            if ($_shop = Shop::where('auto_ele', $ele)->first()) {
                return $this->error("饿了ID已存在：绑定门店名称[{$_shop->shop_name}]");
            }
        }

        // if ((mb_strstr($shop->shop_name, '寝趣') !== false) || (mb_strstr($shop->shop_name, '唤趣') !== false)) {
        if ((mb_strstr($shop->shop_name, '寝趣') !== false)) {
            if ($mtwm) {
                $status = false;
                $params = ['app_poi_codes' => $mtwm];

                $mk = app('minkang');
                $mk_shops = $mk->getShops($params);
                if (!$status && !empty($mk_shops['data'])) {
                    $shop->auto_mtwm = $mtwm;
                    $shop->mt_shop_id = $mtwm;
                    $shop->save();
                    return $this->success();
                }

                $mq = app('meiquan');
                $mq_shops = $mq->getShops($params);
                if (!$status && !empty($mq_shops['data'])) {
                    $shop->auto_mtwm = $mtwm;
                    $shop->mt_shop_id = $mtwm;
                    $shop->save();
                    return $this->success();
                }

                $qq = app('qinqu');
                $qq_shops = $qq->getShops($params);
                if (!$status && !empty($qq_shops['data'])) {
                    $shop->auto_mtwm = $mtwm;
                    $shop->mt_shop_id = $mtwm;
                    $shop->save();
                    return $this->success();
                }
                return $this->error('该门店没有授权开放平台,请先授权。如是民康门店,请联系技术处理');
            }

            if ($ele) {
                $e = app('ele');
                $data = $e->shopInfo($ele);
                if (isset($data['body']['errno']) && $data['body']['errno'] === 0) {
                    $shop->auto_ele = $ele;
                    $shop->ele_shop_id = $ele;
                    $shop->save();
                    return $this->success();
                }
                return $this->error('该门店没有授权开放平台,请先授权');
            }
        } else {

            if ($mtwm) {
                $shop->auto_mtwm = $mtwm;
                $shop->save();
            }

            if ($ele) {
                $shop->auto_ele = $ele;
                $shop->save();
            }
        }

        return $this->success();
    }

    /**
     * 关闭门店自动发单
     */
    public function closeAuto(Request $request)
    {
        if (!$shop = Shop::find($request->get("shop_id", 0))) {
            return $this->error("门店不存在");
        }

        $platform = $request->get("platform", 1);

        if ($platform == 1) {
            $shop->auto_mtwm = '';
            $shop->mt_shop_id = '';
        } else {
            $shop->auto_ele = '';
            $shop->ele_shop_id = '';
        }

        $shop->save();

        return $this->success();
    }

    /**
     * 关闭门店自动发单
     */
    public function openAuto(Request $request)
    {
        if (!$shop = Shop::find($request->get("shop_id", 0))) {
            return $this->error("门店不存在");
        }

        $platform = $request->get("platform", 1);

        if ($platform == 1) {
            $shop->auto_mtwm = '';
            $shop->mt_shop_id = $shop->waimai_mt;
        } else {
            $shop->auto_ele = '';
            $shop->ele_shop_id = $shop->waimai_ele;
        }
        $shop->save();

        return $this->success();
    }

    /**
     * 删除门店
     * @param Shop $shop
     * @return mixed
     * @author zhangzhen
     * @data 2021/6/7 10:19 下午
     */
    public function delete(Shop $shop, Request $request)
    {
        if ($shop->mt_shop_id) {
            return $this->error("请先关闭美团自动接单");
        }
        if ($shop->ele_shop_id) {
            return $this->error("请先关闭饿了么自动接单");
        }

        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            return $this->error("无权限操作");
        }
        $user_id = Auth::id();
        if ($user_id != 1 && $user_id !== 32 && $user_id !== 4478) {
            return $this->error("无权限操作");
        }
        $this->ding_error("用户ID:$user_id|操作删除门店|" . json_encode($shop));

        $shop->user_id = 0;
        $shop->own_id = 0;
        $shop->status = -1;
        $shop->mt_shop_id = '';
        $shop->ele_shop_id = '';
        $shop->chufang_mt = '';
        $shop->chufang_ele = '';
        $shop->waimai_mt = '';
        $shop->waimai_ele = '';
        $shop->mtwm = '';
        $shop->ele = '';
        $shop->auto_mtwm = '';
        $shop->auto_ele = '';
        $shop->yyzz = '';

        if ($shop->save()) {
            DB::table("user_has_shops")->where("shop_id", $shop->id)->delete();
            DB::table("shop_three_ids")->where("shop_id", $shop->id)->delete();
        }

        return $this->success();
    }

    public function platform(Shop $shop)
    {
        $shop->load('shippers');

        $result = [
            'mt' => $shop->shop_id ?? 0,
            'fn' => $shop->shop_id_fn ?? 0,
            'ss' => $shop->shop_id_ss ?? 0,
            'sf' => $shop->shop_id_sf ?? 0,
            'dd' => $shop->shop_id_dd ?? 0,
            'uu' => $shop->shop_id_uu ?? 0,
            'mqd' => $shop->shop_id_mqd ?? 0,
            'zb' => $shop->shop_id_zb ?? 0
        ];

        if (!empty($shop->shippers)) {
            foreach ($shop->shippers as $shipper) {
                if ($shipper->platform == 3) {
                    $result['ss'] = $shipper->three_id;
                }
                if ($shipper->platform == 5) {
                    $result['dd'] = $shipper->three_id;
                }
                if ($shipper->platform == 7) {
                    $result['sf'] = $shipper->three_id;
                }
            }
        }

        return $this->success($result);
    }

    public function runningAuthAll(Request $request)
    {
        $user = $request->user();

        $data = Shop::select("id","shop_name as name","shop_address as address","shop_id","shop_id_fn","shop_id_ss",
            "shop_id_dd","shop_id_mqd","shop_id_uu","shop_id_sf","city","status","mt_shop_id","ele_shop_id", "running_select",
        "mtwm","ele")
            ->where("user_id", $user->id)->get();

        return $this->success($data);
    }

    public function update_three_id(Request $request)
    {
        $shop_id = $request->get('id', 0);

        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }

        // 判断角色
        if (!$request->user()->hasRole('city_manager') && !$request->user()->hasRole('currency_shop_all')) {
            if (($shop->own_id != Auth::user()->id)) {
                return $this->error('门店不存在');
            }
        }

        if (!$apply = ShopThreeId::where('shop_id', $shop_id)->first()) {
            $apply = new ShopThreeId();
            $apply->shop_id = $shop_id;
        }

        if (($mtwm = $request->get('mtwm', '')) && !$shop->mtwm) {
            $apply->mtwm = $mtwm;
        }
        if (($ele = $request->get('ele', '')) && !$shop->ele) {
            $apply->ele = $ele;
        }
        if (($jddj = $request->get('jddj', '')) && !$shop->jddj) {
            $apply->jddj = $jddj;
        }

        $apply->save();

        return $this->success($shop);
    }

    public function shop_auth_meituan_canyin(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('参数错误');
        }

        if (!$shop = Shop::select('id', 'waimai_mt', 'mtwm')->find($shop_id)) {
            return $this->error('门店不存在');
        }

        if (!$shop->waimai_mt) {
            return $this->error('该门店已绑定');
        }

        if (!$shop->mtwm) {
            return $this->error('请先录入美团ID后再操作');
        }

        $type = $request->get("type");

        if ($type == 2) {
            $url = Tool::releasebinding($shop_id);
        } else {
            $url = Tool::binding($shop_id);
        }


        return $this->success(['url' => $url]);
    }

    /**
     * 门店设置（美团自动接单）
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2023/2/9 3:27 下午
     */
    public function setting(Request $request)
    {
        $mt_auto = $request->get('mtAuto');
        $print_auto = $request->get('print_auto');
        if (!in_array($mt_auto, [1, 2])) {
            return $this->error('请选择美团自动接单状态');
        }
        if (!in_array($print_auto, [1, 2])) {
            return $this->error('请选择本地自动打印状态');
        }
        if (!$shop = Shop::find(intval($request->get('id')))) {
            return $this->error('门店不存在');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if ($shop->user_id !== $request->user()->id && $shop->account_id !== $request->user()->id ) {
                return $this->error('门店不存在!');
            }
        }
        $shop->mt_jie = $mt_auto;
        $shop->print_auto = $print_auto;
        $shop->save();
        return $this->success();
    }
}
