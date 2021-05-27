<?php

namespace App\Http\Controllers;

use App\Jobs\CreateMtShop;
use App\Models\Shop;
use App\Models\ShopAuthentication;
use App\Models\ShopRange;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Overtrue\EasySms\EasySms;

class ShopController extends Controller
{
    public function __construct()
    {
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
        $query = Shop::query();
        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
               $query->where('shop_id', 'like', "%{$search_key}%")
                   ->orWhere('shop_name', 'like', "%{$search_key}%")
                   ->orWhere('contact_name', 'like', "%{$search_key}%")
                   ->orWhere('contact_phone', 'like', "%{$search_key}%");
            });
        }
        if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('id', $request->user()->shops()->pluck('id'));
        }
        $shops = $query->orderBy('id', 'desc')->paginate($page_size);

        $result = [];
        $data = [];

        if (!empty($shops)) {
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
                $tmp['mt_shop_id'] = $shop->mt_shop_id;
                $tmp['city'] = $shop->city;

                // 外卖资料
                $tmp['material'] = $shop->material;
                // 商城
                $tmp['shopping'] = $shop->auth;
                // 自动接单
                $tmp['mtwm'] = (bool) $shop->mtwm;
                $tmp['auto'] = (bool) $shop->mt_shop_id;
                $data[] = $tmp;
            }
        }

        $result['page'] = $shops->currentPage();
        $result['total'] = $shops->total();
        $result['list'] = $data;

        return $this->success($result);
    }

    public function update(Shop $shop, Request $request)
    {
        if (!$id = intval($request->get("id"))) {
            return $this->error("门店不存在");
        }
        if ($id != $shop->id) {
            return $this->error("参数错误");
        }
        if (!$contact_name = trim($request->get("contact_name", ""))){
            return $this->error("联系人不能为空");
        }
        $shop->contact_name = $contact_name;
        if (!$contact_phone = trim($request->get("contact_phone", ""))){
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
        $query = Shop::query()->select("id", "shop_name");

        if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('id', $request->user()->shops()->pluck('id'));
        }

        $shops = $query->orderBy('id', 'desc')->get();

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
        $query = Shop::query()->select('id','shop_id','shop_name')->where('status', 40);

        if ($request->user()->hasRole('super_man')) {
            $shop = $query->get();
        } else {
            $shop = $query->whereIn("id", $request->user()->shops()->pluck("id"))->get();
        }
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
        $user = Auth::user();

        $shop->fill($request->all());

        if (!$shop->shop_id) {
            unset($shop->shop_id);
        }

        $shop->status = 20;
        $shop->user_id = $user->id;
        $shop->own_id = $user->id;

        if ($shop->save()) {
            $user->shops()->attach($shop->id);
            \Log::info("创建门店-自动审核");
            dispatch(new CreateMtShop($shop));
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
            ShopRange::query()->create(['shop_id' => $shop->id, 'range' => '', 'range_fn' => '']);
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
        if (!$shop = Shop::query()->where('shop_id', $request->get('shop_id', 0))->first()) {
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
                ShopRange::query()->create(['shop_id' => $shop->id, 'range' => json_encode($scope)]);
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

        if (!$shop = Shop::query()->where(['id' => $shop_id, 'user_id' => $user->id])->first()) {
            return $this->error("门店不存在");
        }

        $user->shop_id = $shop_id;
        $user->save();

        return $this->success();
    }

    /**
     * 绑定门店-自动发单
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2020/11/4 7:53 上午
     */
    public function binding(Request $request)
    {
        if (!$shop = Shop::query()->find($request->get("shop_id", 0))) {
            return $this->error("门店不存在");
        }

        if ($mtwm = intval($request->get("mtwm", 0))) {
            $shop->mtwm = intval($mtwm);
            $shop->save();
        }

        return $this->success();
    }

    /**
     * 关闭门店自动发单
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data dateTime
     */
    public function closeAuto(Request $request)
    {
        if (!$shop = Shop::query()->find($request->get("shop_id", 0))) {
            return $this->error("门店不存在");
        }

        $shop->mtwm = 0;
        $shop->mt_shop_id = 0;
        $shop->save();

        return $this->success();
    }

    public function platform(Shop $shop)
    {

        $result = [
            'mt' => $shop->shop_id ?? 0,
            'fn' => $shop->shop_id_fn ?? 0,
            'ss' => $shop->shop_id_ss ?? 0,
            'sf' => $shop->shop_id_sf ?? 0,
            'dd' => $shop->shop_id_dd ?? 0,
            'mqd' => $shop->shop_id_mqd ?? 0
        ];

        return $this->success($result);
    }
}
