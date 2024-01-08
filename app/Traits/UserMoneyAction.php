<?php


namespace App\Traits;


use App\Libraries\DingTalk\DingTalkRobotNotice;
use App\Models\UserMoneyBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait UserMoneyAction
{
    /**
     * @param $user_id   // 用户ID
     * @param $money
     * @param $description
     * @param int $shop_id
     * @param int $order_id
     * @param int $type2 // 类型2：2 处方费，3 代运营服务费
     * @param int $tid   // 三方ID
     * @return false
     * @author zhangzhen
     * @data dateTime
     * 运营服务费-扣款
     */
    public function operateDecrement($user_id, $money, $description, int $shop_id = 0, int $order_id = 0, int $type2 = 0, int $tid = 0): bool
    {
        // 更改信息，扣款
        try {
            // 查找扣款用户，为了记录余额日志
            $current_user = DB::table('users')->find($user_id);
            // 操作减余额
            DB::table('users')->where('id', $user_id)->decrement('money', $money);
            // 创建余额记录
            UserMoneyBalance::create([
                "user_id" => $user_id,
                "money" => $money,
                "type" => 2,
                "type2" => $type2,
                "before_money" => $current_user->money,
                "after_money" => ($current_user->money - $money),
                "description" => $description,
                "shop_id" => $shop_id,
                "order_id" => $order_id,
                "tid" => $tid
            ]);
        } catch (\Exception $e) {
            $message = [
                $user_id,
                $money,
                $description,
                $shop_id,
                $order_id,
                $type2,
                $tid,
                [
                    $e->getCode(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getMessage()
                ]
            ];
            Log::info('运营服务费扣款失败，原因：', $message);
            return false;
        }
        return true;
    }

    /**
     * @param $user_id   // 用户ID
     * @param $money
     * @param $description
     * @param int $shop_id
     * @param int $order_id
     * @param int $type2 // 类型2：2 处方费，3 代运营服务费
     * @param int $tid   // 三方ID
     * @return false
     * @author zhangzhen
     * @data dateTime
     * 运营服务费-增加余额
     */
    public function operateIncrement($user_id, $money, $description, int $shop_id = 0, int $order_id = 0, int $type2 = 0, int $tid = 0): bool
    {
        // 更改信息，扣款
        try {
            // 查找扣款用户，为了记录余额日志
            $current_user = DB::table('users')->find($user_id);
            // 操作减余额
            DB::table('users')->where('id', $user_id)->increment('money', $money);
            // 创建余额记录
            UserMoneyBalance::create([
                "user_id" => $user_id,
                "money" => $money,
                "type" => 1,
                "type2" => $type2,
                "before_money" => $current_user->money,
                "after_money" => ($current_user->money + $money),
                "description" => $description,
                "shop_id" => $shop_id,
                "order_id" => $order_id,
                "tid" => $tid,
            ]);
        } catch (\Exception $e) {
            $message = [
                $user_id,
                $money,
                $description,
                $shop_id,
                $order_id,
                $type2,
                $tid,
                [
                    $e->getCode(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getMessage()
                ]
            ];
            Log::info('运营服务费增加失败，原因：', $message);
            return false;
        }
        return true;
    }

    public function ding($message)
    {
        $ding = new DingTalkRobotNotice("24913c5a9aa834c9418c61a316bdd6c74e830b1bd4c05907529287a65695f74b");
        $ding->sendTextMsg(date("Y-m-d H:i:s") . '|' . $message);
    }
}
