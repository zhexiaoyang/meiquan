<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminOnlineShopSettlementExport;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\OnlineShop;
use App\Models\Shop;
use Illuminate\Http\Request;

class OnlineShopController extends Controller
{
    public function index(Request $request)
    {
        $page_size = intval($request->get("page_size", 10)) ?: 10;
        $name = trim($request->get("name", ""));

        $query = OnlineShop::with('contract');

        // 非管理员只能查看所指定的门店
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        // if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        if ($name) {
            $query->where("name", "like", "%{$name}%");
        }

        $shops = $query->orderBy("id", "desc")->paginate($page_size);

        $contracts = Contract::select('id', 'name')->get()->toArray();

        if (!empty($shops)) {
            foreach ($shops as $shop) {
                $data = $contracts;
                foreach ($data as $k => $v) {
                    $data[$k]['status'] = 0;
                    if (!empty($shop->contract)) {
                        foreach ($shop->contract as $item) {
                            if ($v['id'] === $item->contract_id) {
                                $data[$k]['status'] = $item->status;
                            }
                        }
                    }
                }
                unset($shop->contract);
                $shop->contract = $data;
            }
        }

        return $this->page($shops);
    }

    public function info_by_shop_id(Request $request)
    {
        $shop_id = $request->get('shop_id');

        if (!$shop = OnlineShop::where('shop_id', $shop_id)->first()) {
            return $this->error("门店不存在");
        }

        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        // if (!$request->user()->hasRole('super_man')) {
            $where = [
                'user_id' => \Auth::id(),
                'shop_id' => $shop_id,
            ];
            if (!\DB::table('user_has_shops')->where($where)->first()) {
                return $this->error("门店不存在");
            }
        }

        return $this->success($shop);
    }

    public function update_by_shop_id(Request $request)
    {
        $user_id = \Auth::id();
        $shop_id = (int) $request->get('shop_id', 0);

        if (!$shop = OnlineShop::query()->where(['shop_id' => $shop_id])->first()) {
            return $this->error("门店不存在");
        }

        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
        // if (!$request->user()->hasRole('super_man')) {
            $where = [
                'user_id' => \Auth::id(),
                'shop_id' => $shop_id,
            ];
            if (!\DB::table('user_has_shops')->where($where)->first()) {
                return $this->error("门店不存在");
            }
        }

        $data = [];
        $data["is_meituan"] = intval($request->get("is_meituan", 0));
        $data["is_ele"] = intval($request->get("is_ele", 0));
        $data["is_jddj"] = intval($request->get("is_jddj", 0));
        $data["is_btoc"] = intval($request->get("is_btoc", 0));

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

        // if (!$phone = $request->get("phone")) {
        //     return $this->error("客服电话不能为空");
        // }
        // $data["phone"] = $phone;

        if (!$contact_name = $request->get("contact_name")) {
            return $this->error("门店联系人不能为空");
        }
        $data["contact_name"] = $contact_name;

        // if (!$contact_phone = $request->get("contact_phone")) {
        //     return $this->error("门店联系人电话不能为空");
        // }
        // $data["contact_phone"] = $contact_phone;
        //
        // if (!$mobile = $request->get("mobile")) {
        //     return $this->error("接收短信验证码手机号不能为空");
        // }
        // $data["mobile"] = $mobile;

        if (!$mobile = $request->get("bank_phone")) {
            return $this->error("银行开户预留电话不能为空");
        }
        $data["bank_phone"] = $mobile;

        // if (!$business_hours = $request->get("business_hours")) {
        //     return $this->error("营业时间不能为空");
        // }
        // $data["business_hours"] = $business_hours;

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

        // if (!$manager_id = $request->get("manager_id")) {
        //     return $this->error("城市经理不能为空");
        // }
        // if (!$manager = User::query()->find($manager_id)) {
        //     return $this->error("城市经理不存在");
        // }
        // $data["manager_id"] = $manager_id;
        // $data["manager_name"] = $manager->name;
        // $data["manager_phone"] = $manager->phone;

        // if ($manager_name = $request->get("manager_name")) {
        //     $data["manager_name"] = $manager_name;
        // }
        //
        // if ($manager_phone = $request->get("manager_phone")) {
        //     $data["manager_phone"] = $manager_phone;
        // }

        // if ($remark = $request->get("remark")) {
        //     $data["remark"] = $remark;
        // }

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

        // if (!$spjy = $request->get("spjy")) {
        //     return $this->error("食品经营许可证不能为空");
        // }
        // $data["spjy"] = $spjy;
        if ($spjy = $request->get("spjy")) {
            $data["spjy"] = $spjy;
        }

        if (!$ylqx = $request->get("ylqx")) {
            return $this->error("医疗器械许可证不能为空");
        }
        $data["ylqx"] = $ylqx;

        if (!$sfz = $request->get("sfz")) {
            return $this->error("负责人身份证正面不能为空");
        }
        $data["sfz"] = $sfz;

        if (!$sfzbm = $request->get("sfzbm")) {
            return $this->error("负责人身份证背面不能为空");
        }
        $data["sfzbm"] = $sfzbm;

        if (!$sfzsc = $request->get("sfzsc")) {
            return $this->error("负责人手持身份证不能为空");
        }
        $data["sfzsc"] = $sfzsc;

        if (!$sfzscbm = $request->get("sfzscbm")) {
            return $this->error("负责人手持背面身份证不能为空");
        }
        $data["sfzscbm"] = $sfzscbm;

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

        // if (!$spjy_start_time = $request->get("spjy_start_time")) {
        //     return $this->error("食品经营许可证开始时间不能为空");
        // }
        // $data["spjy_start_time"] = $spjy_start_time;
        //
        // if (!$spjy_end_time = $request->get("spjy_end_time")) {
        //     return $this->error("食品经营许可证结束时间不能为空");
        // }
        // $data["spjy_end_time"] = $spjy_end_time;
        if ($spjy_start_time = $request->get("spjy_start_time")) {
            $data["spjy_start_time"] = $spjy_start_time;
        }
        if ($spjy_end_time = $request->get("spjy_end_time")) {
            $data["spjy_end_time"] = $spjy_end_time;
        }

        if (!$ylqx_start_time = $request->get("ylqx_start_time")) {
            return $this->error("医疗器械许可证开始时间不能为空");
        }
        $data["ylqx_start_time"] = $ylqx_start_time;

        if (!$ylqx_end_time = $request->get("ylqx_end_time")) {
            return $this->error("医疗器械许可证结束时间不能为空");
        }
        $data["ylqx_end_time"] = $ylqx_end_time;

        $data['status'] = 10;
        OnlineShop::where('id', $shop->id)->update($data);

        Shop::where('id', $shop_id)->update(['material' => 1]);

        return $this->success();
    }

    public function export(Request $request, AdminOnlineShopSettlementExport $adminOnlineShopSettlementExport)
    {
        return $adminOnlineShopSettlementExport->withRequest($request);
    }
}
