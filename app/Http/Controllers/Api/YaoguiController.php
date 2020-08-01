<?php


namespace App\Http\Controllers\Api;


use App\Jobs\CreateMtOrder;
use App\Jobs\PushDeliveryOrder;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class YaoguiController
{
    public function settlement(Request $request)
    {
        Log::info('药柜-结算订单', $request->all());

        $res = [
            "code" => 200,
            "message" => "SUCCESS"
        ];

        return json_encode($res);
    }

    public function downgrade(Request $request)
    {
        Log::info('药柜-隐私号降级', $request->all());

        $res = [
            "code" => 200,
            "message" => "SUCCESS"
        ];

        return json_encode($res);
    }

    public function create(Request $request)
    {
        Log::info('药柜-创建订单', $request->all());

        $data = $request->get("params");

        if (!empty($data) && count($data) > 20) {
            // 创建订单信息
            $order_data = [
                'delivery_id' => $data['orderNo'],
                'order_id' => $data['orderNo'],
                'shop_id' => $data['appStoreCode'],
                'delivery_service_code' => "4011",
                'receiver_name' => $data['deliveryAddress']['receiverName'] ?? "无名",
                'receiver_address' => $data['deliveryAddress']['receiverAddress'],
                'receiver_phone' => $data['deliveryAddress']['receiverPhone'],
                'receiver_lng' => $data['deliveryAddress']['receiverLongitude'],
                'receiver_lat' => $data['deliveryAddress']['receiverLatitude'],
                'coordinate_type' => 0,
                // 'note' => $data['caution'] ?? "",
                'goods_value' => $data['totalAmount'],
                'goods_weight' => 4.5,
                'type' => 11,
                'status' => 0,
                'order_type' => 0,
                'goods_pickup_info' => $data['takeCode'] ?? substr($data['fourthPartyOrderId'], -6),
            ];

            // 判断收货人姓名
            if (empty($order_data['receiver_name'])) {
                $order_data['receiver_name'] = "无名";
            }

            if (!empty($data['caution'])) {
                $a = $data['caution'];
                $a = preg_replace("/收餐人隐私号 \d+_\d+，手机号 \d+\*\*\*\*\d+/","",$a);
                $a = trim($a);
                $order_data['note'] = $a;
            }

            if (isset($data['deliveryTime']) && $data['deliveryTime'] > (time() + 3610)) {

                $order_data['expected_pickup_time'] = $data['deliveryTime'] - 3600;
                $order_data['expected_delivery_time'] = $data['deliveryTime'];
                $order_data['order_type'] = 1;
                $order_data['status'] = 3;
                // 'expected_pickup_time' => ($data['deliveryTime'] > (time() + 3660)) ? ($data['deliveryTime'] - 3600) : 0,
                // 'expected_delivery_time' => ($data['deliveryTime'] > (time() + 3660)) ? $data['deliveryTime'] : 0,
                // 'order_type' => ($data['deliveryTime'] > (time() + 3660)) ? 1 : 0,

            }

            $order = new Order($order_data);

            if ($order->save()) {
                if (isset($data['details']) && !empty($data['details'])) {
                    foreach ($data['details'] as $detail) {
                        $weight = $detail['weight'] ?? 0;
                        $item['order_id'] = $order->id;
                        $item['goods_id'] = $detail['appGoodsId'] ?? 0;
                        $item['name'] = $this->getName($detail['barcode'] ?? "");
                        $item['upc'] = $detail['barcode'] ?? "";
                        $item['quantity'] = $detail['quantity'];
                        $item['goods_price'] = $detail['activityPrice'];
                        $item['total_price'] = $detail['totalActivityPrice'];
                        $item['weight'] = $weight < 0 ? 0 : $weight;
                        OrderDetail::query()->create($item);
                    }
                }
                if ($order->order_type) {
                    dispatch(new PushDeliveryOrder($order, ($order->expected_delivery_time - time() - 3600)));
                    \Log::info('众柜创建预约订单成功', $order->toArray());

                    $ding_notice = app("ding");

                    $logs = [
                        "des" => "接到预订单",
                        "datetime" => date("Y-m-d H:i:s"),
                        "order_id" => $order->order_id,
                        "status" => $order->status,
                        "ps" => $order->ps
                    ];

                    $ding_notice->sendMarkdownMsgArray("接到预订单", $logs);
                } else {
                    dispatch(new CreateMtOrder($order));
                    \Log::info('众柜创建订单成功', $order->toArray());
                }
            }
        }

        $res = [
            "code" => 200,
            "message" => "SUCCESS"
        ];

        return json_encode($res);
    }

    public function cancel(Request $request)
    {

        $res = ["code" => 200, "message" => "SUCCESS"];

        Log::info('药柜-取消订单', $request->all());

        $data = $request->get("params");

        $order_id = $data['orderNo'] ?? '';

        if ($order_id) {

            $order = Order::query()->where('order_id', $order_id)->first();

            if (!$order) {
                \Log::info('药柜接口取消订单-订单未找到', ['请求参数' => $request->all()]);
            }

            \Log::info('药柜接口取消订单-信息', ['请求参数' => $request->all(), '订单信息' => $order->toArray()]);

            $ps = $order->ps;
            $shop = Shop::query()->find($order->shop_id);

            if ($ps == 1) {
                $meituan = app("meituan");

                $result = $meituan->delete([
                    'delivery_id' => $order->delivery_id,
                    'mt_peisong_id' => $order->peisong_id,
                    'cancel_reason_id' => 399,
                    'cancel_reason' => '其他原因',
                ]);

                if ($result['code'] === 0 && ($order->status < 99)) {
                    if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
                        \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                        \Log::info('美团取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    } else {
                        \Log::info('美团取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    }
                    return json_encode($res);
                } else {
                    \Log::info('美团取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    return json_encode($res);
                }
            } elseif ($ps == 2) {

                $fengniao = app("fengniao");

                $result = $fengniao->cancelOrder([
                    'partner_order_code' => $order->order_id,
                    'order_cancel_reason_code' => 2,
                    'order_cancel_code' => 9,
                    'order_cancel_time' => time() * 1000,
                ]);

                if ($result['code'] == 200 && ($order->status < 99)) {
                    if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
                        \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                        \Log::info('蜂鸟取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    } else {
                        \Log::info('蜂鸟取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    }
                    return json_encode($res);
                } else {
                    \Log::info('蜂鸟取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    return json_encode($res);
                }
            } elseif ($ps == 3 && ($order->status < 99)) {

                $shansong = app("shansong");

                $result = $shansong->cancelOrder($order->peisong_id);

                if ($result['status'] == 200 && ($order->status < 99)) {
                    if (Order::query()->where(['id' => $order->id])->where('status', '<>', 99)->update(['status' => 99])) {
                        \DB::table('users')->where('id', $shop->user_id)->increment('money', $order->money);
                        \Log::info('闪送取消订单成功-将钱返回给用户', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    } else {
                        \Log::info('闪送取消订单成功-将钱返回给用户-失败了', ['order_id' => $order->id, 'money' => $order->money, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    }
                    return json_encode($res);
                } else {
                    \Log::info('闪送取消订单成功-已经是取消状态了', ['order_id' => $order->id, 'shop_id' => $shop->id, 'user_id' => $shop->user_id]);
                    return json_encode($res);
                }
            } else {
                $order->status = 99;
                $order->save();
                \Log::info('药柜取消订单-未配送');
                return json_encode($res);
            }

            \Log::info('药柜取消订单-失败');
        }
    }

    public function urge(Request $request)
    {
        Log::info('药柜-催单', $request->all());

        $res = [
            "code" => 200,
            "message" => "SUCCESS"
        ];

        return json_encode($res);
    }

    public function getName($upc)
    {
        $arr = [
            "6922887440332" => "【金毓婷】左炔诺孕酮片",
            "6933303892017" => "【卡瑞丁】左炔诺孕酮分散片",
            "6944788183361" => "【丹媚】左炔诺孕酮肠溶片",
            "6903281004641" => "【万通】感通片",
            "6970250790027" => "金银花润喉糖",
            "6907911100437" => "【昆药】调经止痛片",
            "6946572600085" => "【康恩贝】肠炎宁片",
            "6940703600197" => "【云丰】清肺抑火片",
            "6924168200451" => "【百灵鸟】维C银翘片(双层片)0.5g*24片",
            "6901070385537" => "【云丰】蒲地蓝消炎片",
            "6907911100628" => "【昆中药】感冒疏风片",
            "6913991300971" => "【新康泰克】通气鼻贴(儿童型)",
            "6922097701513" => "【仁和】布洛芬缓释胶囊",
            "6902329302145" => "【太极】沉香化气片",
            "6907911100505" => "【昆中药】暖胃舒乐片",
            "6912283509122" => "【星鲨】维生素D滴剂(胶囊型)400单位*24粒",
            "6907911200557" => "【昆中药】参苓健脾胃颗粒",
            "6938444900515" => "【大卫】早早孕(HCG)检测试盒",
            "6940756220113" => "【云南白药】云南白药创可贴（轻巧护翼型）",
            "6935675201305" => "【三精】四季感冒胶囊",
            "6940756220007" => "【云南白药】云南白药膏(打孔透气型)",
            "6901424286213" => "【王老吉】润喉糖",
            "6907911100086" => "【昆中药】感冒消炎片",
            "6938007000218" => "【神威】感冒软胶囊",
            "6901070385636" => "【云丰】四季感冒片",
            "6925614224076" => "【斯达舒/修正】维U颠茄铝胶囊Ⅱ",
            "6931452806541" => "【仁和】感冒灵胶囊",
            "6921793019649" => "【特一】止咳宝片",
            "6934327100454" => "【康泰】丁细牙痛胶囊",
            "6901070384073" => "【金熊/云丰】田七痛经胶囊",
            "6925731210280" => "肝胃气痛片",
            "6901070386077" => "【泰邦】轻巧创可贴",
            "6921441868162" => "【葵花】咽炎片",
            "6922552000090" => "【万通】铝碳酸镁咀嚼片",
            "6943750066282" => "【万通】布洛芬片",
            "6930397801901" => "【仁和】清火胶囊",
            "6901070384745" => "【云丰】藿香正气胶囊",
            "6939261900238" => "【三金】西瓜霜清咽含片1.8g*16片",
            "6925265100767" => "【希瓦丁】盐酸西替利嗪片",
            "6901070384615" => "【云丰】黄连上清片",
            "6938751003015" => "【康恩贝】蒙脱石散3g*10袋",
            "6938444900317" => "【大卫】早早孕检测试笔",
            "6900372107342" => "【白云山】【晕动】苯巴比妥东莨菪碱片",
            "6908389180587" => "【药当家】咽炎含片",
            "6925923793195" => "【海氏海诺】早早孕测定试纸(胶体金法)卡型",
            "6901070385414" => "【云丰】健胃消食片",
            "6920560420268" => "【都乐】金嗓子喉片",
            "6903757062045" => "【江中】儿童乳酸菌素片",
            "6925923792068" => "【海氏海诺】创口贴",
            "6926893501827" => "【元和】元和正胃片",
            "6903757060720" => "【江中】健胃消食片",
            "6955833600191" => "【严可芬】开塞露",
            "6901070387272" => "【云丰】妇炎康片",
            "6903757060331" => "【江中】乳酸菌素片",
            "6933132800986" => "【诺捷康】多潘立酮片10mg*30片",
            "6925923793201" => "【海氏海诺】早早孕测定试纸（笔型）",
            "6934403200108" => "【万通】氯雷他定片",
            "6923146198162" => "【杜蕾斯】天然胶乳橡胶避孕套",
            "6939261900757" => "【三金】三金片",
            "6901070386220" => "消炎止咳片",
            "6920991410876" => "【悦而】维生素D滴剂",
            "6907911200496" => "【昆中药】蒲公英颗粒",
            "6901339905216" => "【999皮炎平】复方醋酸地塞米松乳膏",
            "6918163020862" => "【太极】藿香正气口服液10ml*10支",
            "6923251811840" => "【小林】小林冰宝贴",
            "6940467800185" => "【修正】复方金银花颗粒",
            "6924168201830" => "咳速停糖浆",
            "6921882197289" => "【康王】酮康唑洗剂",
            "6903281004979" => "【万通】万通筋骨贴",
            "6926247930136" => "【葵花康宝】小儿氨酚黄那敏颗粒",
            "6946029600095" => "【同仁堂】】益母草颗粒",
            "6901616290028" => "【白云山】开塞露（含甘油）",
            "4547691689702" => "【冈本】oKamoto,冈本OK安全套(超润滑)",
            "6926720801038" => "【999皮炎平】糠酸莫米松凝胶",
            "6923146198063" => "【杜蕾斯】避孕套活力装",
            "6926247820093" => "【小葵花】小儿感冒颗粒",
            "4902510060207" => "【杰士邦】零感超薄",
            "6941914217006" => "【葫芦娃】小儿肺热咳喘颗粒",
            "6923146199275" => "【杜蕾斯】杜蕾斯激情装",
            "6901070385100" => "【云丰】感冒止咳颗粒",
            "6915159000174" => "【太极】川贝清肺糖浆",
            "6902329305177" => "麻仁丸",
            "6907911400360" => "【昆中药】清肺化痰丸",
            "6924147604027" => "【力度伸】维生素C泡腾片",
            "6923146198018" => "【杜蕾斯】杜蕾斯挚爱装",
            "4902510020102" => "【杰士邦】ZERO超薄超润天然胶乳橡胶避孕套",
            "6905227006849" => "【整肠生】地衣芽孢杆菌活菌胶囊",
            "6923146100028" => "【杜蕾斯】天然胶乳橡胶避孕套",
            "6923251811857" => "【小林】小林冰宝贴",
            "6972016580019" => "【修正】肺宁颗粒",
            "6901070386572" => "【白药】麝香跌打风湿膏",
            "6925614224724" => "【修正】麝香壮骨膏",
            "6923251811079" => "【小林冰宝贴】医用退热贴",
            "4547691689719" => "【冈本】然胶乳橡胶避孕套(激薄装)",
            "6923146102046" => "【杜蕾斯】大胆爱吧（亲密薄大胆爱）",
            "4547691770929" => "【冈本】天然胶乳橡胶避孕套（无感透薄）3只",
            "6923251811864" => "【冰宝贴】退热贴",
            "6932564410039" => "【第6感螺纹柠檬香】避孕套",
            "6922867752141" => "【葵花】胃康灵颗粒",
            "6932564410022" => "【第六感】超薄平滑",
            "6932564410015" => "【第6感】颗粒激点",
            "6905227001400" => "【安婷】左炔诺孕酮片",
            "6907911200595" => "【昆中药】止泻利颗粒(冲剂)",
            "4547691777942" => "【岡本】天然乳胶橡胶避孕套",
            "6923146112267" => "【杜蕾斯】天然乳胶橡胶避孕套",
            "6925023250321" => "【安刻】止嗽立效胶囊",
            "6925989489483" => "【雅风宁】齿痛消炎灵颗粒",
            "6911641002312" => "【太极】维生素C咀嚼片",
            "6930175206126" => "【德良方】消炎止咳胶囊",
            "6924561812893" => "【日田】清火栀麦片",
            "6922195920830" => "【太极】安神补心片",
            "6920568406288" => "【羚锐】胃疼宁片",
            "6941507705125" => "【丁桂】医用退热贴",
            "6900372151277" => "【白云山】氢溴酸右美沙芬片",
            "6926921413009" => "【海力医生】氯雷他定片",
            "6905942303582" => "【海王金象】苋菜黄连素胶囊",
            "6901070386091" => "【泰邦】透明防水创可贴",
            "6925923791832" => "【海氏海诺】创口贴",
            "6924370432169" => "【强列】烧烫伤膏",
            "6970350040398" => "【慧宝源】十滴水",
            "6901070384578" => "【云丰】伤风停胶囊",
            "6955647681072" => "【福达康】电子体温计BT-A13",
            "6935485301387" => "【水视界】隐形眼镜护理液",
            "6953775658003" => "【爱立康】红外额温计",
            "6930397801062" => "【伊康美宝】甲硝唑氯已定洗剂",
        ];

        return $arr[$upc] ?? $upc;
    }
}