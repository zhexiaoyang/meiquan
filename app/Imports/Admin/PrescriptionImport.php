<?php

namespace App\Imports\Admin;

use App\Exceptions\InvalidRequestException;
use App\Jobs\SendSmsNew;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserOperateBalance;
use App\Models\WmPrescription;
use App\Models\WmPrescriptionImport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToArray;

class PrescriptionImport implements ToArray
{

    public function array(array $array)
    {
        array_shift($array);
        if (!empty($array)) {
            $order_ids = [];
            $prescriptions = [];
            $balances = [];
            $users = [];
            $user_money = [];
            $shops = [];
            $import_log = ['count' => 0, 'success' => 0, 'error' => 0, 'exists' => 0, 'text' => '', 'user_id' => Auth::id(), ];
            foreach ($array as $key => $item) {
                if (!$item[3]) {
                    continue;
                }
                if (in_array($item[3], $order_ids)) {
                    $import_log['exists']++;
                    $import_log['text'] .= trim($item[3]) . ',';
                    continue;
                }
                $order_ids[] = $item[3];
                $import_log['count']++;
                $line = $key + 2;
                if (WmPrescription::where('outOrderID', trim($item[3]))->exists()) {
                    $import_log['exists']++;
                    $import_log['text'] .= trim($item[3]) . ',';
                    continue;
                }
                if (!in_array($item[0], ['美团', '饿了么'])) {
                    throw new InvalidRequestException("第{$line}行，参数错误");
                }
                $platform = $item[0] == '美团' ? 1 : 2;
                $shop_id = trim($item[1]);
                Log::info($shop_id);
                $_tmp = [
                    'money' => floatval($item[5]),
                    // 'expend' => $this->expend,
                    // 'income' => $this->income,
                    'status' => 1,
                    'platform' => $platform,
                    'shop_id' => 0,
                    // 'clientID' => $v['clientID'] ?? '',
                    // 'clientName' => $v['clientName'] ?? '',
                    'storeID' => trim($item[1]),
                    'storeName' => $item[2],
                    'outOrderID' => trim(trim($item[3])),
                    // 'outRpId' => $v['outRpId'] ?? '',
                    'outDoctorName' => $item[4] ?? '',
                    'orderStatus' => $item[7] ?? '',
                    'reviewStatus' => $item[6] ?? '',
                    'reason' => '',
                    // 'orderCreateTime' => $v['orderCreateTime'] ?? '',
                    'rpCreateTime' => $item[8],
                ];
                if (isset($shops[$shop_id])) {
                    $shop = $shops[$shop_id];
                } else {
                    $shop = null;
                    if ($platform === 1) {
                        $shop = Shop::select('id','user_id')->where('chufang_mt', $shop_id)->orderByDesc('id')->first();
                    } else {
                        $shop = Shop::select('id','user_id')->where('chufang_ele', $shop_id)->orderByDesc('id')->first();
                    }
                    if ($shop) {
                        $shops[$shop_id] = $shop;
                    } else {
                        // throw new InvalidRequestException("第{$line}行，门店不存在");
                        if ($item[7] == '已完成' || $item[7] == '进行中') {
                            $_tmp['status'] = 2;
                            $_tmp['reason'] = '未开通处方';
                            $import_log['error']++;
                        } else {
                            $import_log['success']++;
                        }
                        $prescriptions[] = $_tmp;
                        continue;
                    }
                }
                $_tmp['shop_id'] = $shop->id;
                // 查找用户
                if (isset($users[$shop->user_id])) {
                    $current_user = $users[$shop->user_id];
                } else {
                    if ($current_user = User::find($shop->user_id)) {
                        $user_money[$current_user->id] = 0;
                        $users[$current_user->id] = $current_user;
                    } else {
                        throw new InvalidRequestException("第{$line}行，门店用户不存在");
                    }
                }
                // 判断扣款
                if ($item[7] != '已完成' && $item[7] != '进行中') {
                    $_tmp['status'] = 1;
                    $_tmp['reason'] = '订单未完成';
                } else {
                    $user_money[$shop->user_id] += floatval($item[5]);
                }
                $import_log['success']++;
                $prescriptions[] = $_tmp;
                // 处方余额记录
                $balances[] = [
                    "user_id" => $shop->user_id,
                    "money" => floatval($item[5]),
                    "type" => 2,
                    "before_money" => $current_user->operate_money - $user_money[$shop->user_id] + $item[5],
                    "after_money" => $current_user->operate_money - $user_money[$shop->user_id],
                    "description" => $item[0] . "处方单:" . trim($item[3]),
                    "shop_id" => $shop->id,
                    // "tid" => $data->id,
                    'order_at' => $item[8]
                ];
            }
            // \Log::info('$shops', $shops);
            // \Log::info('$prescriptions', $prescriptions);
            // \Log::info('$balances', $balances);
            // \Log::info('$user_money', $user_money);
            // \Log::info('$import_log', $import_log);
            unset($order_ids);
            DB::transaction(function () use ($prescriptions, $balances, $user_money, $import_log) {
                $prescriptions_data = array_chunk($prescriptions, 800);
                if (!empty($prescriptions_data)) {
                    foreach ($prescriptions_data as $prescriptions_datum) {
                        WmPrescription::insert($prescriptions_datum);
                    }
                }
                // $balances_data = array_chunk($balances, 800);
                // if (!empty($balances_data)) {
                //     foreach ($balances_data as $balances_datum) {
                //         UserOperateBalance::insert($balances_datum);
                //     }
                // }
                // $import_log_data = array_chunk($import_log, 800);
                // if (!empty($import_log_data)) {
                //     foreach ($import_log_data as $import_log_datum) {
                //         WmPrescriptionImport::create($import_log_datum);
                //     }
                // }
                UserOperateBalance::insert($balances);
                WmPrescriptionImport::create($import_log);
                foreach ($user_money as $user_id => $money) {
                    User::where('id', $user_id)->decrement('operate_money', $money);
                    $_user = User::find($user_id);
                    $phone = $_user->phone;
                    if (strlen($phone) == 13) {
                        $lock = Cache::lock("send_sms_chufang:{$phone}", 86400);
                        if ($lock->get()) {
                            Log::info("处方余额不足发送短信：{$phone}");
                            dispatch(new SendSmsNew($phone, "SMS_267395014", [ 'phone' => '15043264324']));
                        } else {
                            Log::info("今天已经发过短信了：{$phone}");
                        }
                    }
                }
            });
        }
        return true;
    }
}
