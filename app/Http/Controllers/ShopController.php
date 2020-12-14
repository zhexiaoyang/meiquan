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

        $res = [];

        $res['page'] = $shops->currentPage();
        $res['current_page'] = $shops->currentPage();
        $res['total'] = $shops->total();
        $res['page_total'] = $shops->lastPage();
        $res['last_page'] = $shops->lastPage();
        $res['data'] = $shops->items();

        return $this->success($res);
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

        $shop->user_id = $user->id;
        $shop->own_id = $user->id;

        if ($shop->save()) {
            $user->shops()->attach($shop->id);
            return $this->success([]);
        }

        return $this->error("创建失败");
    }

    // /**
    //  * 保存待审核门店
    //  * @param Request $request
    //  * @param Shop $shop
    //  * @return mixed
    //  */
    // public function storeShop(Request $request, Shop $shop)
    // {
    //     $shop->fill($request->all());
    //
    //     if (!$shop->shop_id) {
    //         unset($shop->shop_id);
    //     }
    //
    //     $shop->status = 100;
    //     $shop->user_id = auth()->user()->id;
    //
    //     if ($shop->save()) {
    //         if (!$shop->shop_id) {
    //             $shop->shop_id = $shop->id;
    //             $shop->save();
    //         }
    //         // dispatch(new CreateMtShop($shop));
    //         return $this->success([]);
    //     }
    //
    //     return $this->error("创建失败");
    // }

    /**
     * 门店详情
     * @param Shop $shop
     * @return mixed
     */
    public function show(Shop $shop)
    {
        // $meituan = app("meituan");

        // return $meituan->shopInfo(['shop_id' => $shop->shop_id]);

        // [{"beginTime":"11:29","endTime":"12:29"}]

        $shop->beginTime = $shop->business_hours[0]['beginTime'] ?? '-';
        $shop->endTime = $shop->business_hours[0]['endTime'] ?? '-';

        return $this->success($shop);
    }

    /**
     * 配送范围获取
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
            // 'lng' => $shop->shop_lng,
            // 'lat' => $shop->shop_lat,
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

    public function examine(Shop $shop)
    {
        $shop->status = 1;
        $shop->save();

        dispatch(new CreateMtShop($shop));

        return $this->success('审核成功');
    }

    // public function examine(Request $request, Shop $shop, EasySms $easySms)
    // {
    //     $meituan = app("meituan");
    //     $params = [
    //         'shop_id' => $shop->shop_id,
    //         'shop_name' => $shop->shop_name,
    //         'category' => $shop->category,
    //         'second_category' => $shop->second_category,
    //         'contact_name' => (string) $shop->contact_name,
    //         'contact_phone' => $shop->contact_phone,
    //         'shop_address' => $shop->shop_address,
    //         'shop_lng' => ceil($shop->shop_lng * 1000000),
    //         'shop_lat' => ceil($shop->shop_lat * 1000000),
    //         'coordinate_type' => $shop->coordinate_type,
    //         'delivery_service_codes' => "4011",
    //         'business_hours' => json_encode($shop->business_hours),
    //     ];
    //     $result = $meituan->shopCreate($params);
    //     if (!isset($result['code']) || $result['code'] != 0) {
    //         $shop->status = 0;
    //         $shop->save();
    //
    //         $shop->load(['user']);
    //         $phone =  $shop->user->phone ?? 0;
    //
    //         if ($phone) {
    //             try {
    //                 $easySms->send($phone, [
    //                     'content'  =>  "您的验证码是{$code}。如非本人操作，请忽略本短信"
    //                 ]);
    //             } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
    //                 \Log::info('审核通过发送短信异常', [$phone]);
    //             }
    //         }
    //         return $this->success('审核成功');
    //     }
    //
    //     return $this->error('审核失败');
    // }

    /**
     * 用户所属门店列表
     * @param Request $request
     * @return mixed
     */
    public function myShops(Request $request)
    {
        $user = Auth::user();

        $status = $request->get("auth", 0);

        $my_shops = $user->my_shops()->where('auth', $status)->get();

        $request = [];

        if (!empty($my_shops)) {
            foreach ($my_shops as $my_shop) {
                $tmp['id'] = $my_shop->id;
                $tmp['shop_name'] = $my_shop->shop_name;
                $tmp['shop_address'] = $my_shop->shop_address;
                $request[] = $tmp;
            }
        }

        return $this->success($request);
    }

    /**
     * 提交认证门店
     * @param Request $request
     * @return mixed
     */
    public function shopAuth(Request $request)
    {
        if (!$shop = Shop::query()->find($request->get("shop_id"))) {
            return $this->error("门店不存在");
        }

        if (!$shop_auth = ShopAuthentication::query()->where("shop_id", $shop->id)->first()) {
            $shop_auth = new ShopAuthentication();
        }

        $chang = $request->get("chang", 0);

        if (!$yyzz = $request->get('yyzz')) {
            return $this->error("请上传营业执照");
        }
        if (!$ypjy = $request->get('ypjy')) {
            return $this->error("请上传药品经营许可证");
        }
        if (!$spjy = $request->get('spjy')) {
            return $this->error("请上传食品经营许可证");
        }
        if (!$ylqx = $request->get('ylqx')) {
            return $this->error("请上传器械证");
        }
        if (!$sfz = $request->get('sfz')) {
            return $this->error("请上传身份证");
        }
        if (!$wts = $request->get('wts')) {
            return $this->error("请上传委托书");
        }
        if (!$yyzz_start_time = $request->get('yyzz_start_time')) {
            return $this->error("请选择营业执照开始时间");
        }
        if (!$chang && (!$yyzz_end_time = $request->get('yyzz_end_time'))) {
            return $this->error("请选择营业执照结束时间");
        }
        if (!$ypjy_start_time = $request->get('ypjy_start_time')) {
            return $this->error("请选择药品经营许可证开始时间");
        }
        if (!$ypjy_end_time = $request->get('ypjy_end_time')) {
            return $this->error("请选择药品经营许可证结束时间");
        }
        if (!$spjy_start_time = $request->get('spjy_start_time')) {
            return $this->error("请选择食品经营许可证开始时间");
        }
        if (!$spjy_end_time = $request->get('spjy_end_time')) {
            return $this->error("请选择食品经营许可证结束时间");
        }
        if (!$ylqx_start_time = $request->get('ylqx_start_time')) {
            return $this->error("请选择器械证开始时间");
        }
        if (!$ylqx_end_time = $request->get('ylqx_end_time')) {
            return $this->error("请选择器械证结束时间");
        }

        $shop_auth->shop_id = $shop->id;
        $shop_auth->chang = $chang;
        $shop_auth->yyzz = $yyzz;
        $shop_auth->xkz = $ypjy;
        $shop_auth->spjy = $spjy;
        $shop_auth->ylqx = $ylqx;
        $shop_auth->sfz = $sfz;
        $shop_auth->wts = $wts;
        $shop_auth->yyzz_start_time = $yyzz_start_time;
        $shop_auth->yyzz_end_time = $yyzz_end_time ?? null;
        $shop_auth->ypjy_start_time = $ypjy_start_time;
        $shop_auth->ypjy_end_time = $ypjy_end_time;
        $shop_auth->spjy_start_time = $spjy_start_time;
        $shop_auth->spjy_end_time = $spjy_end_time;
        $shop_auth->ylqx_start_time = $ylqx_start_time;
        $shop_auth->ylqx_end_time = $ylqx_end_time;


        if ($shop_auth->save()) {
            $shop->auth = 1;
            $shop->apply_auth_time = date("Y-m-d H:i:s");
            $shop->save();
        }

        return $this->success();
    }

    /**
     * 管理员审核门店
     * @param Request $request
     * @return mixed
     */
    public function shopExamineSuccess(Request $request)
    {
        if (!$shop = Shop::query()->find($request->get("shop_id"))) {
            return $this->error("门店不存在");
        }

        if (!$shop_auth = ShopAuthentication::query()->where("shop_id", $shop->id)->first()) {
            return $this->error("门店未提交资质");
        }

        $shop->update(['auth' => 10]);
        $shop_auth->update(['examine_user_id' => Auth::user()->id, 'examine_at' => date("Y-m-d H:i:s")]);

        $user = User::find($shop->own_id);

        if ($user && !$user->can("supplier")) {
            $user->givePermissionTo("supplier");
        }

        // $user->
        $user->assignRole('shop');

        return $this->success();
    }

    /**
     * 提交认证列表
     * @param Request $request
     * @return mixed
     */
    public function shopAuthList(Request $request)
    {
        $search_key = $request->get('search_key', '');

        $shops = [];

        $user = Auth::user();

        $query = Shop::with("auth_shop")->where("own_id", $user->id)->where("auth", ">", 0);

        if ($search_key) {
            $query->where("shop_name", "like", "{$search_key}");
        }

        $data = $query->get();

        if (!empty($data)) {
            foreach ($data as $v) {
                if ($v->auth_shop) {
                    $tmp['id'] = $v->id;
                    $tmp['shop_name'] = $v->shop_name;
                    $tmp['auth'] = $v->auth;
                    $tmp['yyzz'] = $v->auth_shop->yyzz;
                    $tmp['xkz'] = $v->auth_shop->xkz;
                    $tmp['sfz'] = $v->auth_shop->sfz;
                    $tmp['wts'] = $v->auth_shop->wts;
                    $tmp['examine_at'] = $v->auth_shop->examine_at ? date("Y-m-d H:i:s", strtotime($v->auth_shop->examine_at)) : "";
                    $tmp['created_at'] = date("Y-m-d H:i:s", strtotime($v->auth_shop->created_at));
                    $shops[] = $tmp;
                }
            }
        }

        return $this->success($shops);
    }

    /**
     * 认证成功的门店列表
     * @return mixed
     * @author zhangzhen
     * @data 2020/11/4 12:52 上午
     */
    public function shopAuthSuccessList(Request $request)
    {
        $receive_shop_id = $request->user()->receive_shop_id;

        $user = Auth::user();

        $shops = Shop::query()->select("id", "shop_name", "shop_address")->where("own_id", $user->id)
            ->where("auth", 10)->get();

        if (!empty($shops)) {
            foreach ($shops as $shop) {
                if ($shop->id === $receive_shop_id) {
                    $shop->is_select = 1;
                } else {
                    $shop->is_select = 0;
                }
            }
        }

        return $this->success($shops);
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
     * 管理员审核门店-列表
     * @param Request $request
     * @return mixed
     */
    public function shopExamineAuthList(Request $request)
    {
        $search_key = $request->get('search_key', '');

        $shops = [];

        $query = Shop::with("auth_shop")->where("auth", 1);

        if ($search_key) {
            $query->where("shop_name", "like", "{$search_key}");
        }

        $data = $query->get();

        if (!empty($data)) {
            foreach ($data as $v) {
                if ($v->auth_shop) {
                    $tmp['id'] = $v->id;
                    $tmp['shop_name'] = $v->shop_name;
                    $tmp['auth'] = $v->auth;
                    $tmp['yyzz'] = $v->auth_shop->yyzz;
                    $tmp['xkz'] = $v->auth_shop->xkz;
                    $tmp['sfz'] = $v->auth_shop->sfz;
                    $tmp['wts'] = $v->auth_shop->wts;
                    $tmp['examine_at'] = $v->auth_shop->examine_at ? date("Y-m-d H:i:s", strtotime($v->auth_shop->examine_at)) : "";
                    $tmp['created_at'] = date("Y-m-d H:i:s", strtotime($v->auth_shop->created_at));
                    $shops[] = $tmp;
                }
            }
        }

        return $this->success($shops);
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
}
