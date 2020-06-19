<?php

namespace App\Http\Controllers;

use App\Jobs\CreateMtShop;
use App\Models\Shop;
use App\Models\ShopRange;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
        if ($request->user()->hasRole('super_man')) {
            $shop = Shop::select('id','shop_id','shop_name')->where('status', 40)->get();
        } else {
            $shop = $request->user()->shops()->select('id','shop_id','shop_name')->where('status', 40)->get();
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
        $shop->fill($request->all());

        if (!$shop->shop_id) {
            unset($shop->shop_id);
        }

        $shop->user_id = auth()->user()->id;

        if ($shop->save()) {
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
        // if (!$shop->range) {
        //     $meituan = app("meituan");
        //     $res = $meituan->getShopArea(['delivery_service_code' => 4011, 'shop_id' => $shop->shop_id]);
        //     if (isset($res['data']['scope'])) {
        //         $scope = [];
        //         $range = json_decode($res['data']['scope'], true);
        //         if (!empty($range)) {
        //             foreach ($range as $k => $v) {
        //                 $tmp[] = $v['y'];
        //                 $tmp[] = $v['x'];
        //                 $scope[] = $tmp;
        //                 unset($tmp);
        //             }
        //         }
        //         ShopRange::query()->create(['shop_id' => $shop->id, 'range' => json_encode($scope)]);
        //         $shop->load('range');
        //     }
        // }

        $data = [
            'id' => $shop->id,
            'lng' => $shop->shop_lng,
            'lat' => $shop->shop_lat,
            // 'range' => isset($shop->range->range) ? json_decode($shop->range->range) : [],
            'range' => [],
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
}
