<?php

namespace App\Http\Controllers;

use App\Exports\PrescriptionOrderExport;
use App\Jobs\PrescriptionPictureExportJob;
use App\Models\ContractOrder;
use App\Models\Pharmacist;
use App\Models\Shop;
use App\Models\User;
use App\Models\WmOrder;
use App\Models\WmPrescription;
use App\Models\WmPrescriptionDown;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'sdate'   => 'required|date_format:Y-m-d,Y-m-d',
            'edate'   => 'required|date_format:Y-m-d,Y-m-d',
            'shop_id'   => 'required',
        ], [
            'sdate.required'   => '开始日期不能为空',
            'sdate.date_format'   => '开始日期格式不正确',
            'edate.required'   => '结束时间不能为空',
            'edate.date_format'   => '结束时间格式不正确',
            'shop_id.required'   => '请选择要下载处方图片的门店',
        ]);
        $order_id = $request->get('order_id', '');
        $shop_id = $request->get('shop_id', '');
        $platform = $request->get('platform', '');
        $sdate = $request->get('sdate', '');
        $edate = $request->get('edate', '');
        $page_size = $request->get('page_size', 10);
        if ((strtotime($edate) - strtotime($sdate)) >= 86400 * 31) {
            return $this->error('查询时间范围不能超过31天');
        }
        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }
        $query = WmOrder::select('id', 'order_id', 'wm_shop_name', 'status', 'platform', 'rp_picture', 'ctime')
            ->where('shop_id', $shop_id)
            ->where('is_prescription', 1)
            // ->where('rp_picture', '<>', '')
            ->where('ctime', '>=', strtotime($sdate))
            ->where('ctime', '<', strtotime($edate) + 86400);
        if ($order_id) {
            $query->where('order_id', $order_id);
        }
        if ($platform) {
            $query->where('platform', $platform);
        }

        $orders = $query->orderByDesc('id')->paginate($page_size);

        return $this->page($orders);



        // $page_size = $request->get('page_size', 10);
        // $order_id = $request->get('order_id', '');
        // $shop_id = $request->get('shop_id', '');
        // $platform = $request->get('platform', '');
        // $stime = $request->get('stime', '');
        // $etime = $request->get('etime', '');
        //
        // $query = WmPrescription::query();
        // $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        //
        // if ($order_id) {
        //     $query->where('outOrderID', $order_id);
        // }
        // if ($shop_id) {
        //     $query->where('shop_id', $shop_id);
        // }
        // if ($platform) {
        //     $query->where('platform', $platform);
        // }
        // if ($stime) {
        //     $query->where('rpCreateTime', '>=', $stime);
        // }
        // if ($etime) {
        //     $query->where('rpCreateTime', '<', date("Y-m-d", strtotime($etime) + 86400));
        // }
        //
        // $data = $query->paginate($page_size);
        //
        // return $this->page($data);
    }

    public function export(Request $request, PrescriptionOrderExport $export)
    {
        return $export->withRequest($request);
    }

    public function pictureDown(Request $request)
    {
        $request->validate([
            'sdate'   => 'required|date_format:Y-m-d,Y-m-d',
            'edate'   => 'required|date_format:Y-m-d,Y-m-d',
            'shop_id'   => 'required',
        ], [
            'sdate.required'   => '开始日期不能为空',
            'sdate.date_format'   => '开始日期格式不正确',
            'edate.required'   => '结束时间不能为空',
            'edate.date_format'   => '结束时间格式不正确',
            'shop_id.required'   => '请选择要下载处方图片的门店',
        ]);
        $order_id = $request->get('order_id', '');
        $shop_id = $request->get('shop_id', '');
        $platform = $request->get('platform', '');
        $sdate = $request->get('sdate', '');
        $edate = $request->get('edate', '');
        if ((strtotime($edate) - strtotime($sdate)) >= 86400 * 31) {
            return $this->error('下载时间范围不能超过31天');
        }
        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在');
        }
        $query = WmOrder::select('id', 'order_id', 'wm_shop_name', 'status', 'platform', 'rp_picture', 'ctime')
            ->where('shop_id', $shop_id)
            ->where('is_prescription', 1)
            ->where('rp_picture', '<>', '')
            ->where('ctime', '>=', strtotime($sdate))
            ->where('ctime', '<', strtotime($edate) + 86400);
        if ($order_id) {
            $query->where('order_id', $order_id);
        }
        if ($platform) {
            $query->where('platform', $platform);
        }
        $orders = $query->get();
        if ($orders->isNotEmpty()) {
            \Log::info('任务触发一次');
            $log = WmPrescriptionDown::create([
                'title' => $shop->shop_name . '处方图片',
                'shop_id' => $shop->id,
                'user_id' => $request->user()->id,
                'sdate' => $sdate,
                'edate' => $edate,
            ]);
            PrescriptionPictureExportJob::dispatch($orders, $log->id, $log->title);
            return $this->message('创建下载任务成功');
        }
        return $this->error('选择数据中无处方图片', 422);
    }

    public function statistics(Request $request)
    {
        $order_id = $request->get('order_id', '');
        $shop_id = $request->get('shop_id', '');
        $platform = $request->get('platform', '');
        $sdate = $request->get('sdate', '');
        $edate = $request->get('edate', '');

        $total_num = 0;
        $total_money = 0;
        $mt_num = 0;
        $mt_money = 0;
        $ele_num = 0;
        $ele_money = 0;
        $xx_num = 0;
        $xx_money = 0;

        if ($sdate && $edate && $shop_id && (strtotime($edate) - strtotime($sdate)) < 86400 * 31) {
            $query = WmOrder::select('id', 'platform')
                ->where('shop_id', $shop_id)
                ->where('is_prescription', 1)
                ->where('ctime', '>=', strtotime($sdate))
                ->where('ctime', '<', strtotime($edate) + 86400);

            if ($order_id) {
                $query->where('outOrderID', $order_id);
            }
            if ($platform) {
                $query->where('platform', $platform);
            }
            $data = $query->get();
            if (!empty($data)) {
                foreach ($data as $v) {
                    $total_num++;
                    if ($v->platform === 1) {
                        $mt_num++;
                    }
                    if ($v->platform === 2) {
                        $ele_num++;
                    }
                }
            }
        }

        $res = [
            'total_num' => $total_num,
            'total_money' => $total_money,
            'mt_num' => $mt_num,
            'mt_money' => 0,
            'ele_num' => $ele_num,
            'ele_money' => 0,
            'xx_num' => 0,
            'xx_money' => 0,
        ];

        return $this->success($res);
    }

    public function shops(Request $request)
    {
        $shops = Shop::with('prescription', 'pharmacists')->select('id', 'shop_name')
            ->where('second_category', '200001')
            ->whereIn('id', $request->user()->shops()->pluck('id'))
            ->get();
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                unset($shop->prescription);
                $shop->prescription = [];
            }
        }
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

        // if (!$contract = ContractOrder::where('shop_id', $shop_id)->where('contract_id', 4)->first()) {
        //     return $this->error('该门店未签署线下处方合同');
        // }
        //
        // if ($contract->status != 1) {
        //     return $this->error('该门店处方合同未签署完成');
        // }

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

    public function zip(Request $request)
    {
        $data = WmPrescriptionDown::where('user_id', $request->user()->id)->orderByDesc('id')->paginate(2);
        return $this->page($data, [],'data');
    }
}
