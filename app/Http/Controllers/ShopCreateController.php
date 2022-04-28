<?php

namespace App\Http\Controllers;

use App\Models\ShopCreate;
use Illuminate\Http\Request;

class ShopCreateController extends Controller
{
    public function info(Request $request)
    {
        $info = ShopCreate::where('user_id', \Auth::id())->where('status', 0)->first();

        return $this->success($info ?: []);
    }

    public function save(Request $request)
    {
        $step = $request->get('step');

        if (!in_array($step, [0, 1, 2])) {
            return $this->error('参数错误');
        }

        $info = ShopCreate::where('user_id', \Auth::id())->where('status', 0)->first();

        if ($step == 0) {
            $data = ['user_id' => \Auth::id()];
            if (!$shop_name = $request->get('shop_name')) {
                return $this->error('门店名称不能为空');
            }
            $data['shop_name'] = $shop_name;
            if (!$shop_category = $request->get('shop_category')) {
                return $this->error('门店分类不能为空');
            }
            $data['category'] = $shop_category[0];
            $data['second_category'] = $shop_category[1];
            if (!$yyzz_img = $request->get('yyzz_img')) {
                return $this->error('营业执照不能为空');
            }
            $data['yyzz_img'] = $yyzz_img;
            if (!$yyzz = $request->get('yyzz')) {
                return $this->error('营业执照编号不能为空');
            }
            $data['yyzz'] = $yyzz;
            if (!$yyzz_name = $request->get('yyzz_name')) {
                return $this->error('营业执照名称不能为空');
            }
            $data['yyzz_name'] = $yyzz_name;
            if (!$contact_name = $request->get('contact_name')) {
                return $this->error('门店联系人不能为空');
            }
            $data['contact_name'] = $contact_name;
            if (!$contact_phone = $request->get('contact_phone')) {
                return $this->error('门店联系人电话不能为空');
            }
            $data['contact_phone'] = $contact_phone;
            if (!$address = $request->get('address')) {
                return $this->error('门店地址不能为空');
            }
            $data['address'] = $address;
            if (!$shop_lng = $request->get('shop_lng')) {
                return $this->error('门店定位不能为空，请点击地图选址获取定位');
            }
            $data['shop_lng'] = $shop_lng;
            if (!$shop_lat = $request->get('shop_lat')) {
                return $this->error('门店定位不能为空，请点击地图选址获取定位');
            }
            $data['shop_lat'] = $shop_lat;
            if (!$province = $request->get('province')) {
                return $this->error('门店定位不能为空，请点击地图选址获取定位');
            }
            $data['province'] = $province;
            if (!$city = $request->get('city')) {
                return $this->error('门店定位不能为空，请点击地图选址获取定位');
            }
            $data['city'] = $city;
            if (!$district = $request->get('district')) {
                return $this->error('门店定位不能为空，请点击地图选址获取定位');
            }
            $data['district'] = $district;
            if (!$citycode = $request->get('citycode')) {
                return $this->error('门店定位不能为空，请点击地图选址获取定位');
            }
            $data['citycode'] = $citycode;
            if ($info) {
                ShopCreate::where('id', $info->id)->update($data);
            } else {
                $data['step'] = 1;
                ShopCreate::create($data);
            }
        } elseif ($step == 1) {
            if (!$info) {
                return $this->error('第1步未保存，请刷新后再试');
            }
            if (!$sqwts = $request->get('sqwts')) {
                return $this->error('授权委托书不能为空');
            }
            $data['sqwts'] = $sqwts;
            if (!$info->sqwts) {
                $data['step'] = 2;
            }
            ShopCreate::where('id', $info->id)->update($data);
        } elseif ($step == 2) {
            if (!$info) {
                return $this->error('第1步未保存，请刷新后再试');
            }
            if (!$info->sqwts) {
                return $this->error('第2步未保存，请刷新后再试');
            }
            if (!$mt_shop_id = $request->get('mt_shop_id')) {
                return $this->error('授权委托书不能为空');
            }
            $data['mt_shop_id'] = $mt_shop_id;
            $data['step'] = 3;
            $data['status'] = 1;
            ShopCreate::where('id', $info->id)->update($data);
        }

        return $this->success();
    }
}
