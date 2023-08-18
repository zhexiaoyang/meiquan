<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\UserMoneyBalance;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * 当前登录用户信息
     * @data 2023/8/16 12:11 上午
     */
    public function user_info(Request $request)
    {
        $user = $request->user();
        $user_info = [
            'id' => $user->id,
            'username' => $user->name,
            'phone' => $user->phone,
            'nickname' => $user->nickname,
            'money' => $user->money,
        ];

        return $this->success($user_info);
    }

    public function money_balance(Request $request)
    {
        // 1 充值记录，2 消费记录
        $money_type = (int) $request->get('money_type', 0);
        if (!in_array($money_type, [1, 2])) {
            return $this->error('记录类型错误');
        }
        // 10 本月，20 上个月，30 三个月，80 自定义
        $date_type = (int) $request->get('date_type', 0);
        if (!in_array($date_type, [10, 20, 30, 80])) {
            return $this->error('日期类型不正确');
        }
        $date_range = $request->get('date_range', '');
        // 日期搜索判断
        $start_date = '';
        $end_date = '';
        if ($date_type === 10) {
            $start_date = date("Y-m-01");
            $end_date = date("Y-m-t");
        } elseif ($date_type === 20) {
            $start_date = date("Y-m-01", strtotime("-1 month"));
            $end_date = date("Y-m-t", strtotime("-1 month"));
        } elseif ($date_type === 30) {
            $start_date = date("Y-m-01", strtotime("-2 month"));
            $end_date = date("Y-m-t");
        } elseif ($date_type === 80) {
            if (!$date_range) {
                return $this->error('日期范围不能为空');
            }
            $date_arr = explode(',', $date_range);
            if (count($date_arr) !== 2) {
                return $this->error('日期格式不正确');
            }
            $start_date = $date_arr[0];
            $end_date = $date_arr[1];
            if ($start_date !== date("Y-m-d", strtotime($start_date))) {
                return $this->error('日期格式不正确');
            }
            if ($end_date !== date("Y-m-d", strtotime($end_date))) {
                return $this->error('日期格式不正确');
            }
            if ((strtotime($end_date) - strtotime($start_date)) / 86400 > 93) {
                return $this->error('时间范围不能超过3个月');
            }
        }

        $user = $request->user();
        $page_size = $request->get('page_size', 10);
        $where = [
            ['created_at', '>=', $start_date],
            ['created_at', '<', date("Y-m-d", strtotime($end_date) + 86400)],
            ['user_id', '=', $user->id],
            ['type', '=', $money_type],
        ];

        $data = UserMoneyBalance::select('id', 'description', 'money as amount', 'created_at')->where($where)->orderByDesc('id')->paginate($page_size);

        return $this->page($data);
    }
}
