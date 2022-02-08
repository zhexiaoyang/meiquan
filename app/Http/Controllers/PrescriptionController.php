<?php

namespace App\Http\Controllers;

use App\Exports\PrescriptionOrderExport;
use App\Models\ContractOrder;
use App\Models\Pharmacist;
use App\Models\Shop;
use App\Models\User;
use App\Models\WmPrescription;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $order_id = $request->get('order_id', '');
        $shop_id = $request->get('shop_id', '');
        $platform = $request->get('platform', '');
        $stime = $request->get('stime', '');
        $etime = $request->get('etime', '');

        $query = WmPrescription::query();
        $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));

        if ($order_id) {
            $query->where('outOrderID', $order_id);
        }
        if ($shop_id) {
            $query->where('shop_id', $shop_id);
        }
        if ($platform) {
            $query->where('platform', $platform);
        }
        if ($stime) {
            $query->where('rpCreateTime', '>=', $stime);
        }
        if ($etime) {
            $query->where('rpCreateTime', '<', date("Y-m-d", strtotime($etime) + 86400));
        }

        $data = $query->paginate($page_size);

        return $this->page($data);
    }

    public function export(Request $request, PrescriptionOrderExport $export)
    {
        return $export->withRequest($request);
    }

    public function statistics(Request $request)
    {
        $order_id = $request->get('order_id', '');
        $shop_id = $request->get('shop_id', '');
        $platform = $request->get('platform', '');
        $stime = $request->get('stime', '');
        $etime = $request->get('etime', '');

        $query = WmPrescription::query();

        if ($order_id) {
            $query->where('outOrderID', $order_id);
        }
        if ($shop_id) {
            $query->where('storeID', $shop_id);
        } else {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }
        if ($platform) {
            $query->where('platform', $platform);
        }
        if ($stime) {
            $query->where('rpCreateTime', '>=', $stime);
        }
        if ($etime) {
            $query->where('rpCreateTime', '<', date("Y-m-d", strtotime($etime) + 86400));
        }

        $data = $query->get();

        $total_num = 0;
        $total_money = 0;
        $mt_num = 0;
        $mt_money = 0;
        $ele_num = 0;
        $ele_money = 0;
        $xx_num = 0;
        $xx_money = 0;

        if (!empty($data)) {
            foreach ($data as $v) {
                $total_num++;
                $total_money += 1.5;
                if ($v->platform === 1) {
                    $mt_num++;
                    if ($v->orderStatus != '已取消') {
                        $mt_money += 1.5;
                    }
                }
                if ($v->platform === 2) {
                    $ele_num++;
                    if ($v->orderStatus != '已取消') {
                        $ele_money += 1.5;
                    }
                }
                if ($v->platform === 3) {
                    $xx_num++;
                    if ($v->reviewStatus == '审方通过' || $v->reviewStatus == '处方签章完成') {
                        $xx_money += 1;
                    }
                }
            }
        }

        $res = [
            'total_num' => $total_num,
            'total_money' => $total_money,
            'mt_num' => $mt_num,
            'mt_money' => $mt_money,
            'ele_num' => $ele_num,
            'ele_money' => $ele_money,
            'xx_num' => $xx_num,
            'xx_money' => $xx_money,
        ];

        return $this->success($res);
    }

    public function shops(Request $request)
    {
        $shops = Shop::with('prescription', 'pharmacists')->select('id', 'shop_name')
            ->where('second_category', '200001')
            ->whereIn('id', $request->user()->shops()->pluck('id'))
            ->get();
        return $this->success($shops);
    }

    public function down(Request $request)
    {
        $id = $request->get('id', 0);

        if (!$pharmacist = Pharmacist::query()->find($id)) {
            return $this->error('药师不存在');
        }

        $shop_id = $pharmacist->shop_id;

        if (!$shop = Shop::query()->find($shop_id)) {
            return $this->error('门店不存在');
        }

        if (!$contract = ContractOrder::where('shop_id', $shop_id)->where('contract_id', 4)->first()) {
            return $this->error('该门店未签署线下处方合同');
        }

        if ($contract->status != 1) {
            return $this->error('该门店处方合同未签署完成');
        }

        $user = User::find($request->user()->id);

        if ($user->operate_money <= 1) {
            return $this->error('处方余额不足，请先充值');
        }

        // $prescription = WmPrescription::query()->create([
        //     'shop_id' => $shop->id,
        //     'storeName' => $shop->shop_name,
        //     'platform' => 3,
        //     'orderCreateTime' => date("Y-m-d H:i:s"),
        // ]);

        $t = app('taozi_xia');
        $res = $t->create_order($request->user(), $shop, $pharmacist);

        if (!isset($res['data']['url']) || empty($res['data']['url'])) {
            return $this->alert('开方失败，请稍后再试');
        }

        return $this->success(['url' => $res['data']['url'] ?? '']);
    }
}
