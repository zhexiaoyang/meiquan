<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopShipper;
use App\Models\ShopShipperUnbound;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShipperController extends Controller
{
    // use LogTool;
    // public $prefix = '运力模块';

    public function add(Request $request)
    {
        $platform = $request->get('platform');
        $type = $request->get('type');

        if (!in_array($platform, [1,2,3,4,5,6,7])) {
            return $this->error('所选运力不存在');
        }
        if (!in_array($type, [1,2])) {
            return $this->error('运力类型选择错误');
        }
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('门店选择错误');
        }

        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (\Auth::id() != $shop->own_id) {
                return $this->error('无权限操作此门店');
            }
        }

        if ($type == 1) {
            switch ($platform) {
                case 1:
                    return $this->add_meituan($shop);
                case 2:
                    return $this->add_fengniao($shop);
                case 3:
                    return $this->add_shansong($shop);
                case 5:
                    return $this->add_dada($shop);
                case 6:
                    return $this->add_uu($shop);
                case 7:
                    return $this->add_shunfeng($shop);
                default:
                    return $this->error('所选运力不存在');
            }
        }
        return $this->success();
    }

    /**
     * 添加美团门店
     * @data 2022/5/25 8:50 上午
     */
    public function add_meituan(Shop $shop)
    {
        if ($shipper = ShopShipperUnbound::where('shop_id', $shop->id)->where('platform', 1)->first()) {
            $shop->shop_id = $shipper->three_id;
            $shop->save();
            return $this->success();
        }
        $meituan = app("meituan");
        $res = $meituan->shopInfo(['shop_id' => $shop->id]);
        if (isset($res['code']) && $res['code'] == 0) {
            $shop->shop_id = $shop->id;
            $shop->save();
            return $this->success();
        }
        $result = $meituan->shopCreate($shop);
        if (isset($result['code']) && $result['code'] == 0) {
            return $this->success('创建成功，等待审核');
        }
        \Log::info("创建门店失败-美团", [$result]);
        return $this->error($result['message'] ?? '添加失败');
    }

    /**
     * 添加蜂鸟门店
     * @data 2022/5/25 8:50 上午
     */
    public function add_fengniao(Shop $shop)
    {
        if ($shipper = ShopShipperUnbound::where('shop_id', $shop->id)->where('platform', 2)->first()) {
            $shop->shop_id_fn = $shipper->three_id;
            $shop->save();
            return $this->success();
        }
        $fengniao = app("fengniao");
        $res = $fengniao->getShop($shop->id);
        if (isset($res['data']) && count($res['data']) > 0) {
            $shop->shop_id_fn = $shop->id;
            $shop->save();
            return $this->success();
        }
        $result = $fengniao->createShop($shop);
        if (isset($result['code']) && $result['code'] == 200) {
            return $this->success('创建成功，等待审核');
        }
        \Log::info("创建门店失败-蜂鸟", [$result]);
        return $this->error($result['msg'] ?? '添加失败');
    }

    /**
     * 添加闪送门店
     * @data 2022/5/25 8:50 上午
     */
    public function add_shansong(Shop $shop)
    {
        if ($shipper = ShopShipperUnbound::where('shop_id', $shop->id)->where('platform', 3)->first()) {
            $shop->shop_id_ss = $shipper->three_id;
            $shop->save();
            return $this->success();
        }
        $shansong = app("shansong");
        $result = $shansong->createShop($shop);
        if (isset($result['status']) && $result['status'] == 200) {
            $shop->shop_id_ss = $result['data'];
            $shop->save();
            return $this->success();
        }
        \Log::info("创建门店失败-闪送", [$result]);
        return $this->error('添加失败');
    }

    /**
     * 添加达达门店
     * @data 2022/5/25 8:50 上午
     */
    public function add_dada(Shop $shop)
    {
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
        }
        \Log::info("创建门店失败-达达", [$result]);
        return $this->error('添加失败');
    }

    /**
     * 添加UU门店
     * @data 2022/5/25 8:50 上午
     */
    public function add_uu(Shop $shop)
    {
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
        }
        return $this->error('当前城市无法开通UU');
    }

    /**
     * 添加顺丰门店
     * @data 2022/5/25 8:50 上午
     */
    public function add_shunfeng(Shop $shop)
    {
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
        }
        return $this->error('当前城市无法开通顺丰');
    }

    public function delete(Request $request)
    {
        if (!$shop = Shop::find($request->get('shop_id', 0))) {
            return $this->error('门店不存在');
        }

        $platform = $request->get('platform');
        $type = $request->get('type');

        if (!in_array($platform, [1,2,3,4,5,6,7])) {
            return $this->error('所选运力不存在');
        }
        if (!in_array($type, [1,2])) {
            return $this->error('操作不存在');
        }

        if ($type == 1) {
            $three_id = '';
            switch ($platform) {
                case 1:
                    $three_id = $shop->shop_id;
                    $shop->shop_id = '';
                    break;
                case 2:
                    $three_id = $shop->shop_id_fn;
                    $shop->shop_id_fn = '';
                    break;
                case 3:
                    $three_id = $shop->shop_id_ss;
                    $shop->shop_id_ss = '';
                    break;
                case 4:
                    $three_id = $shop->shop_id_mqd;
                    $shop->shop_id_mqd = '';
                    break;
                case 5:
                    $three_id = $shop->shop_id_dd;
                    $shop->shop_id_dd = '';
                    break;
                case 6:
                    $three_id = $shop->shop_id_uu;
                    $shop->shop_id_uu = '';
                    break;
                case 7:
                    $three_id = $shop->shop_id_sf;
                    $shop->shop_id_sf = '';
                    break;
            }
            $shop->save();
            if (!ShopShipperUnbound::where('shop_id', $shop->id)->where('platform', $platform)->first()) {
                ShopShipperUnbound::query()->create([
                    'shop_id' => $shop->id,
                    'user_id' => $shop->user_id,
                    'platform' => $platform,
                    'three_id' => $three_id,
                ]);
            }
        } else {
            if ($platform == 3) {
                if ($shipper = ShopShipper::where('shop_id', $shop->id)->where('platform', 3)->first()) {
                    $this->log('用户解绑自有闪送', $shipper->toArray());
                    $shipper->delete();
                    $old_key = 'ss:shop:auth:' . $shipper->shop_id;
                    $old_key_ref = 'ss:shop:auth:ref:' . $shipper->shop_id;
                    Cache::forget($old_key);
                    Cache::forget($old_key_ref);
                }
            } else if ($platform == 5) {
                if ($shipper = ShopShipper::where('shop_id', $shop->id)->where('platform', 5)->first()) {
                    $this->log('用户解绑自有达达', $shipper->toArray());
                    $shipper->delete();;
                }
            }
        }

        return $this->success();
    }

    public function get_dada_auth_url(Request $request)
    {
        if (!$shop = Shop::find($request->get('shop_id', 0))) {
            return $this->error('门店不存在');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (\Auth::id() != $shop->own_id) {
                return $this->error('无权限操作此门店');
            }
        }

        $dada = app("dada");
        $ticket_res = $dada->get_code();
        $ticket = $ticket_res['result'] ?? '';
        if (!$ticket) {
            return $this->error('请求达达失败，请稍后再试');
        }

        return $this->success(['url' => $dada->get_url($shop->id, $ticket)]);
    }
}
