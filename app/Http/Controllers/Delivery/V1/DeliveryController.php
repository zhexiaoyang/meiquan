<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Libraries\DaDaService\DaDaService;
use App\Libraries\ShanSongService\ShanSongService;
use App\Models\OrderSetting;
use App\Models\Shop;
use App\Models\ShopShipper;
use App\Models\ShopShipperUnbound;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    /**
     * 运力列表
     * @data 2023/8/17 6:58 下午
     */
    public function index(Request $request)
    {
        if (!$shop_id = (int) $request->get('shop_id')) {
            return $this->error('门店不存在');
        }
        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在!');
        }
        $off_arr = [];
        if ($setting = OrderSetting::where('shop_id', $shop->id)->first()) {
            if (!$setting->shansong) {
                $off_arr[] = 3;
            }
            if (!$setting->dada) {
                $off_arr[] = 5;
            }
            if (!$setting->uu) {
                $off_arr[] = 6;
            }
            if (!$setting->shunfeng) {
                $off_arr[] = 7;
            }
            if (!$setting->zhongbao) {
                $off_arr[] = 8;
            }
        }

        $bound = [];
        $unbound = [];
        $shippers = $shop->shippers;
        if (!empty($shippers)) {
            foreach ($shippers as $shipper) {
                $bound[$shipper->platform] = [
                    'platform' => $shipper->platform,
                    'status' => !in_array($shipper->platform, $off_arr) ? 1 : 2,
                    'platform_text' => config('ps.delivery_map')[$shipper->platform],
                    'type' => 2, 'platform_id' => $shipper->three_id
                ];
            }
        }
        if ($shop->shop_id_ss) {
            $bound[3] = ['platform' => 3, 'platform_text' => config('ps.delivery_map')[3], 'status' => !in_array(3, $off_arr) ? 1 : 2, 'type' => 1, 'platform_id' => $shop->shop_id_ss];
        }
        if ($shop->shop_id_dd) {
            $bound[5] = ['platform' => 5, 'platform_text' => config('ps.delivery_map')[5], 'status' => !in_array(5, $off_arr) ? 1 : 2, 'type' => 1, 'platform_id' => $shop->shop_id_dd];
        }
        if ($shop->shop_id_uu) {
            $bound[6] = ['platform' => 6, 'platform_text' => config('ps.delivery_map')[6], 'status' => !in_array(6, $off_arr) ? 1 : 2, 'type' => 1, 'platform_id' => $shop->shop_id_uu];
        }
        if ($shop->shop_id_sf) {
            $bound[7] = ['platform' => 7, 'platform_text' => config('ps.delivery_map')[7], 'status' => !in_array(7, $off_arr) ? 1 : 2, 'type' => 1, 'platform_id' => $shop->shop_id_sf];
        }
        if ($shop->shop_id_zb) {
            $bound[8] = ['platform' => 8, 'platform_text' => config('ps.delivery_map')[8], 'status' => !in_array(8, $off_arr) ? 1 : 2, 'type' => 1, 'platform_id' => $shop->shop_id_zb];
        }

        if (!isset($bound[3])) {
            $unbound[] = [
                'platform' => 3,
                'platform_text' => config('ps.delivery_map')[3],
                'channel' => [
                    [
                        'type' => 1,
                        'type_text' => '聚合送',
                        'button_text' => '了解并开通',
                        'url' => '',
                        'title' => '聚合送平台配送账号',
                        'description' => '点击开始授权，开通后需在美全达充值即可发的，无需额外支付发单服务费'
                    ],
                    [
                        'type' => 2,
                        'type_text' => '自有运力',
                        'button_text' => '开始授权',
                        'url' => "https://open.ishansong.com/auth?response_type=code&client_id=ssM486SGDiFhoNiA6&state=mq&scope=shop_open_api&thirdStoreId={$shop_id}&redirect_uri=https://psapi.meiquanda.com/api/callback/shansong/auth",
                        'title' => '已有闪送账号',
                        'description' => '点击开始授权，跳转到平台页面进行绑定'
                    ]
                ]
            ];
        }
        if (!isset($bound[5])) {
            $dada_channel = [
                [
                    'type' => 1,
                    'type_text' => '聚合送',
                    'button_text' => '了解并开通',
                    'url' => '',
                    'title' => '聚合送平台配送账号',
                    'description' => '点击开始授权，开通后需在美全达充值即可发的，无需额外支付发单服务费'
                ]
            ];
            $dada = new DaDaService(config('ps.dada'));
            $ticket_res = $dada->get_code();
            $ticket = $ticket_res['result'] ?? '';
            if ($ticket) {
                $dada_channel[] = [
                    'type' => 2,
                    'type_text' => '自有运力',
                    'button_text' => '开始授权',
                    'url' => $dada->get_url($shop->id, $ticket),
                    'title' => '已有达达账号',
                    'description' => '点击开始授权，跳转到平台页面进行绑定'
                ];
            }
            $unbound[] = [
                'platform' => 5,
                'platform_text' => config('ps.delivery_map')[5],
                'channel' => $dada_channel
            ];
        }
        if (!isset($bound[6])) {
            $unbound[] = [
                'platform' => 6,
                'platform_text' => config('ps.delivery_map')[6],
                'channel' => [
                    [
                        'type' => 1,
                        'type_text' => '聚合送',
                        'button_text' => '了解并开通',
                        'url' => '',
                        'title' => '聚合送平台配送账号',
                        'description' => '点击开始授权，开通后需在美全达充值即可发的，无需额外支付发单服务费'
                    ]
                ]
            ];
        }
        if (!isset($bound[7])) {
            $unbound[] = [
                'platform' => 7,
                'platform_text' => config('ps.delivery_map')[7],
                'channel' => [
                    [
                        'type' => 1,
                        'type_text' => '聚合送',
                        'button_text' => '了解并开通',
                        'url' => '',
                        'title' => '聚合送平台配送账号',
                        'description' => '点击开始授权，开通后需在美全达充值即可发的，无需额外支付发单服务费'
                    ],
                    [
                        'type' => 2,
                        'type_text' => '自有运力',
                        'button_text' => '开始授权',
                        'url' => "https://openic.sf-express.com/artascope/cx/receipt/getpage/product/artascope/page/storeBinding?dev_id=1633621660&out_shop_id={$shop_id}&type=1",
                        'title' => '已有顺丰账号',
                        'description' => '点击开始授权，跳转到平台页面进行绑定'
                    ]
                ]
            ];
        }

        $result = [
            'bound' => array_values($bound),
            'unbound' => array_values($unbound),
        ];
        return $this->success($result);
    }

    /**
     * 开通运力
     * @data 2023/8/17 6:58 下午
     */
    public function activate(Request $request)
    {
        if (!$shop_id = (int) $request->get('shop_id')) {
            return $this->error('门店不存在');
        }
        if (!$platform = (int) $request->get('platform')) {
            return $this->error('请选择开通平台');
        }
        if (!in_array($platform, [3,5,6,7])) {
            return $this->error('请选择开通平台');
        }
        $user = $request->user();
        if (!$shop = Shop::where('user_id',$user->id)->find($shop_id)) {
            return $this->error('门店不存在!');
        }
        $shipper_platforms = ShopShipper::where('shop_id', $shop_id)->get()->pluck('platform')->toArray();
        if ($platform === 3) {
            if ($shop->shop_id_ss || in_array(3, $shipper_platforms)) {
                return $this->success();
            }
            if ($shipper = ShopShipperUnbound::where('shop_id', $shop->id)->where('platform', 3)->first()) {
                // 已有门店
                $shop->shop_id_ss = $shipper->three_id;
                $shop->save();
                return $this->success();
            }
            // 没有门店
            $shansong = app("shansong");
            $result = $shansong->createShop($shop);
            if (isset($result['status']) && $result['status'] == 200) {
                $shop->shop_id_ss = $result['data'];
                $shop->save();
                return $this->success();
            } else {
                return $this->error($result['msg'] ?? '开通失败');
            }
        }
        if ($platform === 5) {
            if ($shop->shop_id_dd) {
                return $this->success();
            }
            if ($shipper = ShopShipperUnbound::where('shop_id', $shop->id)->where('platform', 5)->first()) {
                $shop->shop_id_dd = $shipper->three_id;
                $shop->save();
                return $this->success();
            }
            $dada = app("dada");
            $result = $dada->createShop($shop);
            if (isset($result['code']) && $result['code'] == 0) {
                $shop->shop_id_dd = $shop->id;
                $shop->save();
                return $this->success();
            } else {
                return $this->error($result['msg'] ?? '开通失败');
            }
        }
        if ($platform === 6) {
            if ($shop->shop_id_uu) {
                return $this->success();
            }
            if ($shipper = ShopShipperUnbound::where('shop_id', $shop->id)->where('platform', 6)->first()) {
                $shop->shop_id_uu = $shipper->three_id;
                $shop->save();
                return $this->success();
            }
            $city = $shop->city;
            $data = ["阿坝藏族羌族自治州","阿拉善盟","阿勒泰地区","安康市","安庆市","安阳市","鞍山市","巴中市","白城市","蚌埠市","包头市",
                "宝鸡市","保定市","保山市","北海市","北京市","本溪市","滨州市","亳州市","博尔塔拉蒙古自治州","沧州市","昌吉回族自治州",
                "长沙市","长治市","常德市","常州市","朝阳市","郴州市","成都市","承德市","赤峰市","滁州市","楚雄彝族自治州","达州市",
                "大理白族自治州","大连市","大庆市","大同市","丹东市","德州市","东莞市","东营市","鄂尔多斯市","恩施土家族苗族自治州",
                "防城港市","佛山市","阜阳市","赣州市","广安市","贵阳市","桂林市","哈密市","海口市","邯郸市","汉中市","杭州市","合肥市",
                "和田市","河源市","菏泽市","鹤壁市","衡水市","衡阳市","红河哈尼族彝族自治州","呼和浩特市","怀化市","淮安市","淮北市","淮南市",
                "黄冈市","黄山市","惠州市","吉安市","济南市","济宁市","济源市","嘉兴市","江门市","焦作市","揭阳市","金华市","晋城市","荆州市",
                "景德镇市","九江市","酒泉市","喀什市","开封市","克拉玛依市","巴音郭楞蒙古自治州","伊犁哈萨克自治州","昆明市","廊坊市","乐山市",
                "丽江市","连云港市","凉山彝族自治州","聊城市","临沧市","临汾市","临沂市","柳州市","六安市","六盘水市","龙岩市","娄底市",
                "泸州市","洛阳市","茂名市","梅州市","绵阳市","内江市","南昌市","南充市","南京市","南宁市","南阳市","宁德市","攀枝花市",
                "盘锦市","平顶山市","萍乡市","莆田市","濮阳市","黔东南苗族侗族自治州","钦州市","秦皇岛市","青岛市","清远市","庆阳市","曲靖市",
                "泉州市","日照市","三门峡市","三亚市","汕头市","汕尾市","商丘市","上饶市","邵阳市","深圳市","十堰市","石河子市","石家庄市",
                "石嘴山市","普洱市","宿迁市","宿州市","随州市","遂宁市","塔城地区","太原市","泰州市","唐山市","天津市","天水市","铜川市",
                "铜仁市","吐鲁番市","潍坊市","渭南市","温州市","文山壮族苗族自治州","乌鲁木齐市","吴忠市","芜湖市","五家渠市","武汉市",
                "西安市","西宁市","西双版纳傣族自治州","锡林郭勒盟","厦门市","仙桃市","咸宁市","咸阳市","湘潭市","襄阳市","孝感市","新乡市",
                "新余市","信阳市","邢台市","徐州市","许昌市","延安市","延边朝鲜族自治州","盐城市","扬州市","阳江市","伊宁市","宜宾市",
                "宜春市","益阳市","银川市","营口市","永州市","榆林市","玉林市","玉溪市","岳阳市","云浮市","运城市","枣庄市","湛江市",
                "张家界市","张家口市","张掖市","彰化县","漳州市","昭通市","郑州市","中山市","中卫市","周口市","株洲市","驻马店市","淄博市",
                "自贡市","固始县","漯河市","遵义市","三沙市","红原县","黑水县","罗定市","盱眙县","夏津县","任丘市","沭阳县","鄱阳县","韩城市",
                "瑞金市","瓦房店市","建水县","奎屯市","安溪县","弥勒市","瑞昌市","老河口市","睢宁县","耒阳市","瑞丽市","麻城市","都匀市",
                "滕州市","仙游县","霍邱县","莒县","天长市","敦煌市"];
            if (in_array($city, $data)) {
                $shop->shop_id_uu = $shop->id;
                $shop->save();
                return $this->success();
            } else {
                return $this->error('该城市不支持UU聚合运力');
            }
        }
        if ($platform === 7) {
            if ($shop->shop_id_sf || in_array(7, $shipper_platforms)) {
                return $this->success();
            }
            if ($shipper = ShopShipperUnbound::where('shop_id', $shop->id)->where('platform', 7)->first()) {
                $shop->shop_id_sf = $shipper->three_id;
                $shop->save();
                return $this->success();
            }
            $code = intval($shop->citycode);
            $city_codes = [351,357,477,472,475,22,315,335,313,311,766,757,756,750,758,474,478,899,534,532,531,539,632,
                633,818,816,833,831,871,953,951,356,358,913,915,713,717,27,746,577,574,579,576,573,572,554,551,557,558,
                591,752,471,20,917,516,512,535,533,536,316,731,719,393,371,21,571,575,354,379,459,769,28,29,760,23,755,
                451,432,431];
            if (in_array($code, $city_codes)) {
                $shop->shop_id_sf = $shop->id;
                $shop->save();
                return $this->success();
            } else {
                return $this->error('该城市不支持顺丰聚合运力');
            }
        }
        return $this->error('开通失败');
    }

    public function update_status(Request $request)
    {
        if (!$shop_id = (int) $request->get('shop_id')) {
            return $this->error('门店不存在');
        }
        if (!$platform = (int) $request->get('platform')) {
            return $this->error('请选择操作平台');
        }
        if (!in_array($platform, [3,5,6,7,8])) {
            return $this->error('请选择操作平台');
        }
        // 1 开启，2 关闭
        if (!$status = (int) $request->get('status')) {
            return $this->error('请选择操作');
        }
        if (!in_array($status, [1,2])) {
            return $this->error('请选择操作平台');
        }
        $user = $request->user();
        if (!Shop::select('id')->where('user_id', $user->id)->find($shop_id)) {
            return $this->error('门店不存在!');
        }
        if (!$setting = OrderSetting::where('shop_id', $shop_id)->first()) {
            $default = config('ps.shop_setting');
            $data = [
                'shop_id' => $shop_id,
                'call' => $default['call'],
                'delay_send' => $default['delay_send'],
                'delay_reset' => $default['delay_reset'],
                'type' => $default['type'],
            ];
            $setting = new OrderSetting($data);
        }
        if ($platform === 3) {
            $setting->shansong = $status === 1 ? 1: 0;
        }
        if ($platform === 5) {
            $setting->dada = $status === 1 ? 1: 0;
        }
        if ($platform === 6) {
            $setting->uu = $status === 1 ? 1: 0;
        }
        if ($platform === 7) {
            $setting->meiquanda = $status === 1 ? 1: 0;
        }
        if ($platform === 8) {
            $setting->zhongbao = $status === 1 ? 1: 0;
        }
        $setting->save();
        return $this->success();
    }

    /**
     * 三方配送充值
     * @data 2023/8/18 9:38 下午
     */
    public function three_account(Request $request)
    {
        $shop_id = (int) $request->get('shop_id');
        $platform = (int) $request->get('platform');
        $user = $request->user();
        $shop_query = Shop::select('id', 'shop_name')->where('user_id', $user->id);
        if ($shop_id) {
            $shop_query->where('id', $shop_id);
        }
        $shops = $shop_query->get();
        $shop_id_map = $shops->pluck('shop_name', 'id')->toArray();
        $shop_ids = $shops->pluck('id')->toArray();
        $shipper_query = ShopShipper::whereIn('shop_id', $shop_ids);
        if ($platform) {
            $shipper_query->where('platform', $platform);
        }
        $shippers = $shipper_query->get();

        $shipper_result = [];
        if (!empty($shippers)) {
            // $shipper_result = [];
            foreach ($shippers as $shipper) {
                if (!in_array($shipper->platform, [3,5,7])) {
                    continue;
                }
                if (isset($shipper_result[$shipper->three_id])) {
                    $shipper_result[$shipper->three_id]['shops'][] = [
                        'shop_id' => $shipper->shop_id,
                        'shop_name' => $shop_id_map[$shipper->shop_id],
                        'selected' => 0,
                    ];
                    continue;
                }
                $level_url = '';
                $level_point = '';
                $level_desc = '';
                $money = 0;
                $recharge_url = '';
                if ($shipper->platform == 3) {
                    $shansong = new ShanSongService(config('ps.shansongservice'));
                    $shansong_res = $shansong->getUserAccount($shipper->access_token);
                    if (isset($shansong_res['data']['balance'])) {
                        $money = $shansong_res['data']['balance'] / 100;
                    } else {
                        $money = '查询失败';
                    }
                    $recharge_url = $shansong->getH5Recharge($shipper->access_token, $shipper->three_id);
                } elseif ($shipper->platform == 5) {
                    $config = config('ps.dada');
                    $config['source_id'] = $shipper->source_id;
                    $dada = new DaDaService($config);
                    $dada_res = $dada->getUserAccount($shipper->three_id);
                    // $recharge_url = $dada->getH5Recharge($shipper->access_token, $shipper->three_id);
                    $recharge_url = '';
                    if (isset($dada_res['result']['deliverBalance'])) {
                        $money = $dada_res['result']['deliverBalance'];
                    } else {
                        $money = '查询失败';
                    }
                } elseif ($shipper->platform == 7) {
                    $sf = app('shunfengservice');
                    $balance_res = $sf->getShopAccountBalance($shipper->three_id);
                    if (isset($balance_res['result']['balance'])) {
                        $money = $balance_res['result']['balance'] / 100;
                    } else {
                        $money = '查询失败';
                    }
                    $shop_info_res = $sf->getShopInfo($shipper->three_id);
                    if (isset($shop_info_res['result']['level_info']) && is_array($shop_info_res['result']['level_info'])) {
                        $level_url = $shop_info_res['result']['level_info']['level_info_h5'];
                        $level_point = $shop_info_res['result']['level_info']['level_points'];
                        $level_desc = $shop_info_res['result']['level_info']['level_desc'];
                    }
                    $recharge_url = $sf->getH5Recharge($shipper->three_id);
                }
                $shipper_result[$shipper->three_id] = [
                    'platform' => $shipper->platform,
                    'platform_text' => config('ps.delivery_map')[$shipper->platform],
                    'level_url' => $level_url,
                    'level_point' => $level_point,
                    'level_desc' => $level_desc,
                    'three_id' => $shipper->three_id,
                    'money' => $money,
                    'week_money' => 0,
                    'recharge_url' => $recharge_url,
                    'shops' => [
                        [
                            'shop_id' => $shipper->shop_id,
                            'shop_name' => $shop_id_map[$shipper->shop_id],
                            'selected' => 1,
                        ]
                    ],
                ];
            }
        }
        $result = array_values($shipper_result);
        return $this->success($result);
    }

    /**
     * 三方配送所有门店
     * @data 2023/8/18 9:39 下午
     */
    public function three_shop(Request $request)
    {
        $user = $request->user();
        $shops = [];
        $shop_ids = ShopShipper::where('user_id', $user->id)->groupBy('shop_id')->pluck('shop_id')->toArray();
        if (!empty($shop_ids)) {
            $shops = Shop::select('id', 'shop_name')->whereIn('id', $shop_ids)->where('user_id', $user->id)->get();
        }
        return $this->success($shops);
    }

    /**
     * 三方配送所有平台
     * @data 2023/8/18 9:39 下午
     */
    public function three_platform(Request $request)
    {
        $user = $request->user();
        $platforms = [];
        $shippers = ShopShipper::where('user_id', $user->id)->groupBy('platform')->pluck('platform')->toArray();
        if (!empty($shippers)) {
            foreach ($shippers as $shipper) {
                if (isset(config('ps.delivery_map')[$shipper])) {
                    $platforms[] = [
                        'platform' => $shipper,
                        'platform_text' => config('ps.delivery_map')[$shipper],
                    ];
                }
            }
        }
        return $this->success($platforms);
    }

    public function get_dada_url(Request $request)
    {
        if (!$shop_id = (int) $request->get('shop_id')) {
            return $this->error('门店不存在');
        }
        $amount =  (int) $request->get('amount');
        if ($amount < 1) {
            return $this->error('充值金额不能小于1元');
        }
        $user = $request->user();
        if (!$shop = Shop::select('id')->where('user_id', $user->id)->find($shop_id)) {
            return $this->error('门店不存在!');
        }
        if (!$shipper = ShopShipper::where('shop_id', $shop->id)->where('platform', 5)->first()) {
            return $this->error('未开通达达自有运力');
        }
        $config = config('ps.dada');
        $config['source_id'] = $shipper->source_id;
        $dada = new DaDaService($config);
        $recharge_url = $dada->getH5Recharge($shipper->three_id, $amount);
        if (!empty($recharge_url['result'])) {
            $url = $recharge_url['result'];
        } else {
            return $this->error($recharge_url['msg'] ?? '请稍后再试');
        }
        return $this->success(['url' => $url]);
    }
}
