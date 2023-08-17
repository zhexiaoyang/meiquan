<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Libraries\DaDaService\DaDaService;
use App\Models\OrderSetting;
use App\Models\Shop;
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
        $shippers = $shop->shippers();
        if (!empty($shippers)) {
            foreach ($shippers as $shipper) {
                $bound[$shipper->platform] = [
                    'platform' => $shipper->platform,
                    'status' => (int) !in_array($shipper->platform, $off_arr),
                    'platform_text' => config('ps.delivery_map')[$shipper->platform],
                    'type' => 2, 'platform_id' => $shipper->three_id
                ];
            }
        }
        if ($shop->shop_id_ss) {
            $bound[3] = ['platform' => 3, 'platform_text' => config('ps.delivery_map')[3], 'status' => (int) !in_array(3, $off_arr), 'type' => 1, 'platform_id' => $shop->shop_id_ss];
        }
        if ($shop->shop_id_dd) {
            $bound[5] = ['platform' => 5, 'platform_text' => config('ps.delivery_map')[5], 'status' => (int) !in_array(5, $off_arr), 'type' => 1, 'platform_id' => $shop->shop_id_dd];
        }
        if ($shop->shop_id_uu) {
            $bound[6] = ['platform' => 6, 'platform_text' => config('ps.delivery_map')[6], 'status' => (int) !in_array(6, $off_arr), 'type' => 1, 'platform_id' => $shop->shop_id_uu];
        }
        if ($shop->shop_id_sf) {
            $bound[7] = ['platform' => 7, 'platform_text' => config('ps.delivery_map')[7], 'status' => (int) !in_array(7, $off_arr), 'type' => 1, 'platform_id' => $shop->shop_id_sf];
        }
        if ($shop->shop_id_zb) {
            $bound[8] = ['platform' => 8, 'platform_text' => config('ps.delivery_map')[8], 'status' => (int) !in_array(8, $off_arr), 'type' => 1, 'platform_id' => $shop->shop_id_zb];
        }

        if (!isset($bound[3])) {
            $unbound[] = [
                'platform' => 3,
                'platform_text' => config('ps.delivery_map')[3],
                'channel' => [
                    [
                        'type' => 1,
                        'button_text' => '了解开通',
                        'url' => '',
                        'title' => '聚合送平台配送账号',
                        'description' => '点击开始授权，开通后需在美全达充值即可发的，无需额外支付发单服务费'
                    ],
                    [
                        'type' => 2,
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
                    'button_text' => '了解开通',
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
                        'button_text' => '了解开通',
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
                        'button_text' => '了解开通',
                        'url' => '',
                        'title' => '聚合送平台配送账号',
                        'description' => '点击开始授权，开通后需在美全达充值即可发的，无需额外支付发单服务费'
                    ],
                    [
                        'type' => 2,
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
        if ($platform === 3) {
            if ($shop->shop_id_ss) {
                return $this->success();
            }
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
            if ($shop->shop_id_sf) {
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
}
