<?php

namespace App\Http\Controllers;

use App\Models\Shop;
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
            $query->where('rpCreateTime', '<', date("Y-m-d", strtotime($stime) + 86400));
        }

        $data = $query->paginate($page_size);

        return $this->page($data);
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
            $query->where('rpCreateTime', '<', date("Y-m-d", strtotime($stime) + 86400));
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
                    $mt_money += 1.5;
                }
                if ($v->platform === 2) {
                    $ele_num++;
                    $mt_money += 1.5;
                }
                if ($v->platform === 3) {
                    $xx_num++;
                    $mt_money += 1.5;
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
        $shops = Shop::query()->select('id', 'shop_name')
            ->where('chufang_mt', '<>', '')
            ->whereIn('id', $request->user()->shops()->pluck('id'))
            ->get();
        return $this->success($shops);
    }

    public function down(Request $request)
    {
        if (!$shop = Shop::query()->find($request->get('id', 0))) {
            return $this->error('门店不存在');
        }

        $prescription = WmPrescription::query()->create([
            'storeName' => $shop->shop_name,
            'platform' => 3,
        ]);

        $t = app('taozi_xia');
        $res = $t->create_order($request->user(), $shop, $prescription);

        if (!isset($res['data']['url']) || empty($res['data']['url'])) {
            return $this->alert('开方失败，请稍后再试');
        }

        return $this->success(['url' => $res['data']['url'] ?? '']);
    }
}
