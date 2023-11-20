<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Exports\PrescriptionOrderNewExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exports\PrescriptionOrderExport;
use App\Jobs\PrescriptionPictureExportJob;
use App\Models\Pharmacist;
use App\Models\Shop;
use App\Models\User;
use App\Models\WmOrder;
use App\Models\WmPrescriptionDown;

class PrescriptionController extends Controller
{
    /**
     * 开通处方的门店列表
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2023/11/17 5:03 下午
     */
    public function shops(Request $request)
    {
        $shops = Shop::select('id', 'shop_name')
            ->where('second_category', '200001')
            ->whereIn('id', $request->user()->shops()->pluck('id'))
            ->get();
        // if (!empty($shops)) {
        //     foreach ($shops as $shop) {
        //         unset($shop->prescription);
        //         $shop->prescription = [];
        //     }
        // }
        return $this->success($shops);
    }

    /**
     * 处方列表
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2023/11/17 5:03 下午
     */
    public function index(Request $request)
    {
        // 时间判定
        $date_range = $request->get('date_range', '');
        if (!$date_range) {
            return $this->error('日期范围不能为空');
        }
        $date_arr = explode(',', $date_range);
        if (count($date_arr) !== 2) {
            return $this->error('日期格式不正确');
        }
        $sdate = $date_arr[0];
        $edate = $date_arr[1];
        if ((strtotime($edate) - strtotime($sdate)) >= 86400 * 31) {
            return $this->error('查询时间范围不能超过31天');
        }
        // 其它筛选
        $order_id = $request->get('order_id', '');
        $shop_id = $request->get('shop_id', '');
        $platform = $request->get('platform', '');
        $page_size = $request->get('page_size', 10);
        if ($shop_id) {
            if (!Shop::find($shop_id)) {
                return $this->error('门店不存在');
            }
        }
        // $user_id = $request->user()->id;
        // MedicineSelectShop::updateOrCreate(
        //     [ 'user_id' => $user_id ],
        //     [ 'user_id' => $user_id, 'shop_id' => $shop_id ]
        // );
        $query = WmOrder::select('id', 'order_id', 'wm_shop_name', 'status', 'platform', 'rp_picture', 'ctime')
            // ->where('shop_id', $shop_id)
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
        if ($shop_id) {
            $query->where('shop_id', $shop_id);
        } else {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        $orders = $query->orderByDesc('id')->paginate($page_size);

        if (!empty($orders)) {
            foreach ($orders as $order) {
                // 平台
                if ($order->platform === 1) {
                    $order->platform_text = '美团';
                } elseif ($order->platform === 2) {
                    $order->platform_text = '饿了么';
                } elseif ($order->platform === 3) {
                    $order->platform_text = '线下';
                }
                // 状态
                if ($order->status === 18) {
                    $order->status_text = '已完成';
                } else {
                    $order->status_text = '进行中';
                }
                // 时间
                $order->ctime_text = date("Y-m-d H:i:s");
            }
        }

        return $this->page($orders);
    }

    /**
     * 处方统计
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2023/11/17 5:24 下午
     */
    public function statistics(Request $request)
    {
        // 时间判定
        $date_range = $request->get('date_range', '');
        if (!$date_range) {
            return $this->error('日期范围不能为空');
        }
        $date_arr = explode(',', $date_range);
        if (count($date_arr) !== 2) {
            return $this->error('日期格式不正确');
        }
        $sdate = $date_arr[0];
        $edate = $date_arr[1];
        if ((strtotime($edate) - strtotime($sdate)) >= 86400 * 31) {
            return $this->error('查询时间范围不能超过31天');
        }
        // 其它筛选
        $order_id = $request->get('order_id', '');
        $shop_id = $request->get('shop_id', '');
        $platform = $request->get('platform', '');

        $total_num = 0;
        $total_money = 0;
        $mt_num = 0;
        $mt_money = 0;
        $ele_num = 0;
        $ele_money = 0;
        // $xx_num = 0;
        // $xx_money = 0;

        if ($sdate && $edate && (strtotime($edate) - strtotime($sdate)) < 86400 * 31) {
            $query = WmOrder::select('id', 'platform', 'prescription_fee')
                ->where('is_prescription', 1)
                ->where('ctime', '>=', strtotime($sdate))
                ->where('ctime', '<', strtotime($edate) + 86400);

            if ($order_id) {
                $query->where('order_id', $order_id);
            }
            if ($platform) {
                $query->where('platform', $platform);
            }
            if ($shop_id) {
                $query->where('shop_id', $shop_id);
            } else {
                $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
            }
            $data = $query->get();
            if (!empty($data)) {
                foreach ($data as $v) {
                    $total_money += $v->prescription_fee;
                    $total_num++;
                    if ($v->platform === 1) {
                        $mt_money += $v->prescription_fee;
                        $mt_num++;
                    }
                    if ($v->platform === 2) {
                        $ele_money += $v->prescription_fee;
                        $ele_num++;
                    }
                }
            }
        }

        $res = [
            'total_num' => $total_num,
            'total_money' => (float) sprintf("%.2f", $total_money),
            'mt_num' => $mt_num,
            'mt_money' => (float) sprintf("%.2f", $mt_money),
            'ele_num' => $ele_num,
            'ele_money' => (float) sprintf("%.2f", $ele_money),
            // 'xx_num' => 0,
            // 'xx_money' => 0,
        ];

        return $this->success($res);
    }

    /**
     * 处方订单导出
     * @param Request $request
     * @param PrescriptionOrderNewExport $export
     * @return PrescriptionOrderNewExport
     * @author zhangzhen
     * @data 2023/11/20 11:01 上午
     */
    public function export(Request $request, PrescriptionOrderNewExport $export)
    {
        return $export->withRequest($request);
    }

    /**
     * 处方图片下载
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2023/11/20 11:02 上午
     */
    public function pictureDown(Request $request)
    {
        // 时间判定
        $date_range = $request->get('date_range', '');
        if (!$date_range) {
            return $this->error('日期范围不能为空');
        }
        $date_arr = explode(',', $date_range);
        if (count($date_arr) !== 2) {
            return $this->error('日期格式不正确');
        }
        $sdate = $date_arr[0];
        $edate = $date_arr[1];
        if ((strtotime($edate) - strtotime($sdate)) >= 86400 * 31) {
            return $this->error('查询时间范围不能超过31天');
        }
        // 其它筛选
        $order_id = $request->get('order_id', '');
        $shop_id = $request->get('shop_id', '');
        $platform = $request->get('platform', '');

        if ($shop_id) {
            if (!$shop = Shop::find($shop_id)) {
                return $this->error('门店不存在');
            }
        }
        $query = WmOrder::select('id', 'order_id', 'wm_shop_name', 'status', 'platform', 'rp_picture', 'ctime')
            // ->where('shop_id', $shop_id)
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
        if ($shop_id) {
            $query->where('shop_id', $shop_id);
        } else {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }
        $orders = $query->get();
        if ($orders->isNotEmpty()) {
            \Log::info('任务触发一次');
            $log = WmPrescriptionDown::create([
                'title' => isset($shop) ? $shop->shop_name . '处方图片' : '全部门店处方图片',
                'shop_id' => isset($shop) ? $shop->id : 0,
                'user_id' => $request->user()->id,
                'count' => count($orders),
                'sdate' => $sdate,
                'edate' => $edate,
            ]);
            PrescriptionPictureExportJob::dispatch($orders, $log->id, $log->title);
            return $this->message('创建下载任务成功');
        }
        return $this->error('选择数据中无处方图片', 422);
    }

    /**
     * 处方图片压缩包下载列表
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2023/11/20 11:07 上午
     */
    public function zip(Request $request)
    {
        $data = WmPrescriptionDown::where('user_id', $request->user()->id)->orderByDesc('id')->paginate(10);
        return $this->page($data);
    }
}
