<?php

namespace App\Http\Controllers;

use App\Models\OnlineShop;
use App\Models\Shop;
use App\Models\ShopAuthentication;
use App\Models\User;
use Illuminate\Http\Request;

class OnlineController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $page_size = $request->get("page_size", 10);

        $query = OnlineShop::query()->select("id","name","status","address","reason","contact_name","contact_phone")
            ->where("user_id", $user->id);

        if ($name = $request->get("name")) {
            $query->where("name", "like", "%{$name}%");
        }

        $shops = $query->paginate($page_size);

        return $this->page($shops);
    }

    public function show(Request $request)
    {
        $user = $request->user();

        if (!$shop = OnlineShop::query()->find($request->get("id"))) {
            return $this->error("门店不存在");
        }

        if ($shop->user_id != $user->id) {
            return $this->error("门店不存在");
        }

        return $this->success($shop);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = [];
        $data['user_id'] = $user->id;

        if (!$name = $request->get("name")) {
            return $this->error("门店名称不能为空");
        }
        $data["name"] = $name;

        if (!$category = $request->get("category")) {
            return $this->error("门店一级分类不能为空");
        }
        $data["category"] = $category;

        if (!$category_second = $request->get("category_second")) {
            return $this->error("门店二级分类不能为空");
        }
        $data["category_second"] = $category_second;

        if (!$shop_lng = $request->get("shop_lng")) {
            return $this->error("门店经度不能为空");
        }
        $data["shop_lng"] = $shop_lng;

        if (!$shop_lat = $request->get("shop_lat")) {
            return $this->error("门店纬度不能为空");
        }
        $data["shop_lat"] = $shop_lat;
        $data["city"] = $request->get("city", "");
        $data["citycode"] = $request->get("citycode", "");

        if (!$address = $request->get("address")) {
            return $this->error("门店地址不能为空");
        }
        $data["address"] = $address;

        if (!$phone = $request->get("phone")) {
            return $this->error("客服电话不能为空");
        }
        $data["phone"] = $phone;

        if (!$contact_name = $request->get("contact_name")) {
            return $this->error("门店联系人不能为空");
        }
        $data["contact_name"] = $contact_name;

        if (!$contact_phone = $request->get("contact_phone")) {
            return $this->error("门店联系人电话不能为空");
        }
        $data["contact_phone"] = $contact_phone;

        if (!$mobile = $request->get("mobile")) {
            return $this->error("接收短信验证码手机号不能为空");
        }
        $data["mobile"] = $mobile;

        if (!$business_hours = $request->get("business_hours")) {
            return $this->error("营业时间不能为空");
        }
        $data["business_hours"] = $business_hours;

        if (!$account_no = $request->get("account_no")) {
            return $this->error("打款账号不能为空");
        }
        $data["account_no"] = $account_no;

        if (!$bank_user = $request->get("bank_user")) {
            return $this->error("开户名不能为空");
        }
        $data["bank_user"] = $bank_user;

        if (!$bank = $request->get("bank_name")) {
            return $this->error("开户行不能为空");
        }
        $data["bank_name"] = $bank;

        if ($manager_name = $request->get("manager_name")) {
            $data["manager_name"] = $manager_name;
        }

        if ($manager_phone = $request->get("manager_phone")) {
            $data["manager_phone"] = $manager_phone;
        }

        if ($remark = $request->get("remark")) {
            $data["remark"] = $remark;
        }

        if (!$sqwts = $request->get("sqwts")) {
            return $this->error("授权委托书不能为空");
        }
        $data["sqwts"] = $sqwts;

        if (!$yyzz = $request->get("yyzz")) {
            return $this->error("营业执照不能为空");
        }
        $data["yyzz"] = $yyzz;

        if (!$ypjy = $request->get("ypjy")) {
            return $this->error("药品经营许可证不能为空");
        }
        $data["ypjy"] = $ypjy;

        if (!$spjy = $request->get("spjy")) {
            return $this->error("食品经营许可证不能为空");
        }
        $data["spjy"] = $spjy;

        if (!$ylqx = $request->get("ylqx")) {
            return $this->error("医疗器械许可证不能为空");
        }
        $data["ylqx"] = $ylqx;

        if (!$sfz = $request->get("sfz")) {
            return $this->error("负责人身份证不能为空");
        }
        $data["sfz"] = $sfz;

        if (!$wts = $request->get("wts")) {
            return $this->error("采购委托书不能为空");
        }
        $data["wts"] = $wts;

        if (!$front = $request->get("front")) {
            return $this->error("门脸照片不能为空");
        }
        $data["front"] = $front;

        if (!$environmental = $request->get("environmental")) {
            return $this->error("环境照片不能为空");
        }
        $data["environmental"] = $environmental;

        if (!$yyzz_start_time = $request->get("yyzz_start_time")) {
            return $this->error("营业执照开始时间不能为空");
        }
        $data["yyzz_start_time"] = $yyzz_start_time;

        $chang = $request->get("chang", 0);
        if ($chang === 1) {
            $data["chang"] = 1;
        }

        if (!$chang && !$yyzz_end_time = $request->get("yyzz_end_time")) {
            return $this->error("营业执照结束时间不能为空");
        }
        $data["yyzz_end_time"] = $yyzz_end_time ?? null;

        if (!$ypjy_start_time = $request->get("ypjy_start_time")) {
            return $this->error("药品经营许可证开始时间不能为空");
        }
        $data["ypjy_start_time"] = $ypjy_start_time;

        if (!$ypjy_end_time = $request->get("ypjy_end_time")) {
            return $this->error("药品经营许可证结束时间不能为空");
        }
        $data["ypjy_end_time"] = $ypjy_end_time;

        if (!$spjy_start_time = $request->get("spjy_start_time")) {
            return $this->error("食品经营许可证开始时间不能为空");
        }
        $data["spjy_start_time"] = $spjy_start_time;

        if (!$spjy_end_time = $request->get("spjy_end_time")) {
            return $this->error("食品经营许可证结束时间不能为空");
        }
        $data["spjy_end_time"] = $spjy_end_time;

        if (!$ylqx_start_time = $request->get("ylqx_start_time")) {
            return $this->error("医疗器械许可证开始时间不能为空");
        }
        $data["ylqx_start_time"] = $ylqx_start_time;

        if (!$ylqx_end_time = $request->get("ylqx_end_time")) {
            return $this->error("医疗器械许可证结束时间不能为空");
        }
        $data["ylqx_end_time"] = $ylqx_end_time;

        $shop = OnlineShop::query()->create($data);

        return $this->success();
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = [];
        $data['user_id'] = $user->id;
        $id = (int) $request->get('id', 0);

        if (!$shop = OnlineShop::query()->where(['id' => $id, 'user_id' => $user->id])->first()) {
            return $this->error("门店不存在");
        }

        if ($shop->status != 20) {
            return $this->error("该门店状态不能编辑");
        }

        if (!$name = $request->get("name")) {
            return $this->error("门店名称不能为空");
        }
        $data["name"] = $name;

        if (!$category = $request->get("category")) {
            return $this->error("门店一级分类不能为空");
        }
        $data["category"] = $category;

        if (!$category_second = $request->get("category_second")) {
            return $this->error("门店二级分类不能为空");
        }
        $data["category_second"] = $category_second;

        if (!$shop_lng = $request->get("shop_lng")) {
            return $this->error("门店经度不能为空");
        }
        $data["shop_lng"] = $shop_lng;

        if (!$shop_lat = $request->get("shop_lat")) {
            return $this->error("门店纬度不能为空");
        }
        $data["shop_lat"] = $shop_lat;

        if (!$address = $request->get("address")) {
            return $this->error("门店地址不能为空");
        }
        $data["address"] = $address;

        if (!$phone = $request->get("phone")) {
            return $this->error("客服电话不能为空");
        }
        $data["phone"] = $phone;

        if (!$contact_name = $request->get("contact_name")) {
            return $this->error("门店联系人不能为空");
        }
        $data["contact_name"] = $contact_name;

        if (!$contact_phone = $request->get("contact_phone")) {
            return $this->error("门店联系人电话不能为空");
        }
        $data["contact_phone"] = $contact_phone;

        if (!$mobile = $request->get("mobile")) {
            return $this->error("接收短信验证码手机号不能为空");
        }
        $data["mobile"] = $mobile;

        if (!$business_hours = $request->get("business_hours")) {
            return $this->error("营业时间不能为空");
        }
        $data["business_hours"] = $business_hours;

        if (!$account_no = $request->get("account_no")) {
            return $this->error("打款账号不能为空");
        }
        $data["account_no"] = $account_no;

        if (!$bank_user = $request->get("bank_user")) {
            return $this->error("开户名不能为空");
        }
        $data["bank_user"] = $bank_user;

        if (!$bank = $request->get("bank_name")) {
            return $this->error("开户行不能为空");
        }
        $data["bank_name"] = $bank;

        if ($manager_name = $request->get("manager_name")) {
            $data["manager_name"] = $manager_name;
        }

        if ($manager_phone = $request->get("manager_phone")) {
            $data["manager_phone"] = $manager_phone;
        }

        if ($remark = $request->get("remark")) {
            $data["remark"] = $remark;
        }

        if (!$sqwts = $request->get("sqwts")) {
            return $this->error("授权委托书不能为空");
        }
        $data["sqwts"] = $sqwts;

        if (!$yyzz = $request->get("yyzz")) {
            return $this->error("营业执照不能为空");
        }
        $data["yyzz"] = $yyzz;

        if (!$ypjy = $request->get("ypjy")) {
            return $this->error("药品经营许可证不能为空");
        }
        $data["ypjy"] = $ypjy;

        if (!$spjy = $request->get("spjy")) {
            return $this->error("食品经营许可证不能为空");
        }
        $data["spjy"] = $spjy;

        if (!$ylqx = $request->get("ylqx")) {
            return $this->error("医疗器械许可证不能为空");
        }
        $data["ylqx"] = $ylqx;

        if (!$sfz = $request->get("sfz")) {
            return $this->error("负责人身份证不能为空");
        }
        $data["sfz"] = $sfz;

        if (!$wts = $request->get("wts")) {
            return $this->error("采购委托书不能为空");
        }
        $data["wts"] = $wts;

        if (!$front = $request->get("front")) {
            return $this->error("门脸照片不能为空");
        }
        $data["front"] = $front;

        if (!$environmental = $request->get("environmental")) {
            return $this->error("环境照片不能为空");
        }
        $data["environmental"] = $environmental;

        if (!$yyzz_start_time = $request->get("yyzz_start_time")) {
            return $this->error("营业执照开始时间不能为空");
        }
        $data["yyzz_start_time"] = $yyzz_start_time;

        $chang = $request->get("chang", 0);
        if ($chang === 1) {
            $data["chang"] = 1;
        }

        if (!$chang && !$yyzz_end_time = $request->get("yyzz_end_time")) {
            return $this->error("营业执照结束时间不能为空");
        }
        $data["yyzz_end_time"] = $yyzz_end_time ?? null;

        if (!$ypjy_start_time = $request->get("ypjy_start_time")) {
            return $this->error("药品经营许可证开始时间不能为空");
        }
        $data["ypjy_start_time"] = $ypjy_start_time;

        if (!$ypjy_end_time = $request->get("ypjy_end_time")) {
            return $this->error("药品经营许可证结束时间不能为空");
        }
        $data["ypjy_end_time"] = $ypjy_end_time;

        if (!$spjy_start_time = $request->get("spjy_start_time")) {
            return $this->error("食品经营许可证开始时间不能为空");
        }
        $data["spjy_start_time"] = $spjy_start_time;

        if (!$spjy_end_time = $request->get("spjy_end_time")) {
            return $this->error("食品经营许可证结束时间不能为空");
        }
        $data["spjy_end_time"] = $spjy_end_time;

        if (!$ylqx_start_time = $request->get("ylqx_start_time")) {
            return $this->error("医疗器械许可证开始时间不能为空");
        }
        $data["ylqx_start_time"] = $ylqx_start_time;

        if (!$ylqx_end_time = $request->get("ylqx_end_time")) {
            return $this->error("医疗器械许可证结束时间不能为空");
        }
        $data["ylqx_end_time"] = $ylqx_end_time;

        $data['status'] = 10;
        $shop->update($data);

        return $this->success();
    }

    public function examineList(Request $request)
    {
        $user = $request->user();

        $page_size = $request->get("page_size", 10);
        $status = (int) $request->get("status", 0);

        if (!in_array($status, [0, 10, 20, 30, 40])) {
            return $this->error("状态错误");
        }

        $query = OnlineShop::query()->select("id","name","status","address","reason","contact_name","contact_phone");

        if ($name = $request->get("name")) {
            $query->where("name", "like", "%{$name}%");
        }

        if ($status === 10) {
            $query->where("status", "<=", $status);
        } else {
            $query->where("status", $status);
        }

        $shops = $query->paginate($page_size);

        return $this->page($shops);
    }

    public function examineShow(Request $request)
    {
        $id = $request->get("id", 0);

        if (!$shop = OnlineShop::query()->find($id)) {
            return $this->error("门店不存在");
        }

        return $this->success($shop);
    }

    public function examine(Request $request)
    {
        $status = $request->get("status", 0);
        $reason = $request->get("reason");

        if (!in_array($status, [20, 40])) {
            return $this->error("状态错误");
        }

        if ($status == 20 && !$reason) {
            return $this->error("驳回原因不能为空");
        }

        if (!$shop = OnlineShop::query()->find($request->get("id"))) {
            return $this->error("门店不存在");
        }

        if ($shop->status > 10) {
            return $this->error("门店状态错误，不能审核");
        }

        $shop->status = $status;
        if ($status == 20) {
            $shop->reason = $reason;
            $shop->save();
        }

        if ($status == 40) {

            try {
                \DB::transaction(function () use ($shop) {

                    // 保存审核状态
                    $shop->save();

                    // 保存跑腿门店
                    $paotui = new Shop([
                        'user_id' => $shop->user_id,
                        'own_id' => $shop->user_id,
                        'shop_name' => $shop->name,
                        'category' => $shop->category,
                        'second_category' => $shop->category_second,
                        'contact_name' => $shop->contact_name,
                        'contact_phone' => $shop->contact_phone,
                        'shop_address' => $shop->address,
                        'shop_lng' => $shop->shop_lng,
                        'shop_lat' => $shop->shop_lat,
                        'city' => $shop->city,
                        'citycode' => $shop->citycode,
                        'coordinate_type' => 0,
                        'apply_auth_time' => date("Y-m-d H:i:s"),
                        'auth' => 10
                    ]);
                    $paotui->save();

                    // 保存跑腿门店资质
                    $shop_auth = new ShopAuthentication([
                        'shop_id' => $paotui->id,
                        'chang' => $shop->chang,
                        'yyzz' => $shop->yyzz,
                        'xkz' => $shop->ypjy,
                        'spjy' => $shop->spjy,
                        'ylqx' => $shop->ylqx,
                        'sfz' => $shop->sfz,
                        'wts' => $shop->wts,
                        'yyzz_start_time' => $shop->yyzz_start_time,
                        'yyzz_end_time' => $shop->yyzz_end_time,
                        'ypjy_start_time' => $shop->ypjy_start_time,
                        'ypjy_end_time' => $shop->ypjy_end_time,
                        'spjy_start_time' => $shop->spjy_start_time,
                        'spjy_end_time' => $shop->spjy_end_time,
                        'ylqx_start_time' => $shop->ylqx_start_time,
                        'ylqx_end_time' => $shop->ylqx_end_time,
                        'examine_at' => date("Y-m-d H:i:s")
                    ]);
                    $shop_auth->save();

                    // 查找门店用户
                    $user = User::find($shop->user_id);

                    // 赋予商城权限
                    if ($user && !$user->can("supplier")) {
                        $user->givePermissionTo("supplier");
                    }

                    // 将跑腿门店加入用户
                    $user->shops()->attach($paotui->id);
                });
            } catch (\Exception $exception) {
                return $this->error("审核失败");
            }
        }

        return $this->success();
    }
}
