<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CreateMtShop;
use App\Models\ManagerCity;
use App\Models\OnlineShop;
use App\Models\Shop;
use App\Models\ShopCreate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamineShopCreateController extends Controller
{
    public function index(Request $request)
    {
        $query = ShopCreate::where('status', 1);

        if ($name = $request->get('name')) {
            $query->where('shop_name', 'like', "%{$name}%");
        }

        $data = $query->paginate($request->get("page_size"));

        return $this->page($data, [], 'data');
    }

    public function update(Request $request)
    {
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
        if (!$sqwts = $request->get('sqwts')) {
            return $this->error('授权委托书不能为空');
        }
        $data['sqwts'] = $sqwts;
        if (!$mt_shop_id = $request->get('mt_shop_id')) {
            return $this->error('美团ID不能为空');
        }
        $data['mt_shop_id'] = $mt_shop_id;

        ShopCreate::where('id', $request->get('id', 0))->update($data);

        return $this->success();
    }

    public function adopt(Request $request)
    {
        if (!$info = ShopCreate:: find($request->get('id', 0))) {
            return $this->error('参数错误');
        }

        if ($info->status !== 1) {
            return $this->error('状态不正确，不能通过');
        }

        if ($info->step !== 3) {
            return $this->error('状态不正确，不能通过');
        }

        if ($_shop = Shop::where('yyzz', $info->yyzz)->first()) {
            return $this->error("该营业执照已存在，请核对，绑定门店名称[{$_shop->shop_name}]", 422);
        }

        DB::transaction(function () use ($info) {
            ShopCreate::where('id', $info->id)->update([
                'status' => 2,
                'adopt_at' => date("Y-m-d H:i:s")
            ]);
            $manager_id = 2415;
            $city = ManagerCity::where('city', $info->city ?? '')->first();
            if ($city) {
                $manager_id = $city->user_id;
            }
            $shop = Shop::create([
                'user_id' => $info->user_id,
                'own_id' => $info->user_id,
                'manager_id' => $manager_id,
                'shop_name' => $info->shop_name,
                'contact_name' => $info->contact_name,
                'category' => $info->category,
                'second_category' => $info->second_category,
                'contact_phone' => $info->contact_phone,
                'delivery_service_code' => $info->delivery_service_code,
                'shop_address' => $info->address,
                'shop_lng' => $info->shop_lng,
                'shop_lat' => $info->shop_lat,
                'yyzz' => $info->yyzz,
                'yyzz_img' => $info->yyzz_img,
                'yyzz_name' => $info->yyzz_name,
                'province' => $info->province,
                'city' => $info->city,
                'district' => $info->district,
                'citycode' => $info->citycode,
                'mtwm' => $info->mt_shop_id,
                'chufang_mt' => $info->mt_shop_id,
                'status' => 40,
                'material' => 10,
            ]);
            if ($manager = User::find($manager_id)) {
                $manager->shops()->attach($shop);
            }
            dispatch(new CreateMtShop($shop));
            OnlineShop::create([
                'is_meituan' => 1,
                'user_id' => $info->user_id,
                'shop_id' => $shop->id,
                'name' => $info->shop_name,
                'contact_name' => $info->contact_name,
                'sqwts' => $info->sqwts,
                'yyzz' => $info->yyzz_img,
                'category' => $info->category,
                'category_second' => $info->second_category,
                'address' => $info->address,
                'shop_lng' => $info->shop_lng,
                'shop_lat' => $info->shop_lat,
                'city' => $info->city,
                'status' => 40,
            ]);
        });

        return $this->success();
    }
}
