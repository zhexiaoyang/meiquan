<?php

namespace App\Http\Controllers\Supplier;

use App\Models\SupplierUser;
use App\Models\SupplierUserBalance;
use App\Models\SupplierWithdrawal;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class WithdrawalController extends Controller
{
    /**
     * 余额提现列表
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/1/22 6:06 下午
     */
    public function index(Request $request)
    {
        $page_size = intval($request->get("page_size", 10)) ?: 10;

        $user = Auth::user();

        $data = SupplierWithdrawal::select()
            ->where(["user_id" => $user->id])->orderBy("id","desc")->paginate($page_size);

        return $this->page($data);
    }

    /**
     * 申请提现
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/1/24 2:12 下午
     */
    public function store(Request $request)
    {
        $money = floatval($request->get("money", 0));
        \Log::info("[供货端余额提现]-[申请提现]-[金额：{$money}]");

        if ($money <= 0) {
            return $this->error("提现金额不正确");
        }

        $user = Auth::user();

        $user = SupplierUser::query()->find($user->id);

        if ($user->money < $money) {
            \Log::info("[供货端余额提现]-[申请提现]-提现金额超出当前余额");
            return $this->error("提现金额超出当前余额");
        }

        try {
            \DB::transaction(function () use ($user, $money) {
                $before_money = $user->money;
                $after_money = $user->money - $money;
                // 减记录
                $user->where("money", $user->money)->update(["money" => $after_money]);

                // 提现记录
                $ti = [
                    "user_id" => $user->id,
                    "money" => $money,
                    "description" => "余额提现"
                ];
                $withdrawal = SupplierWithdrawal::query()->create($ti);

                // 余额记录
                $yu = [
                    "user_id" => $user->id,
                    "type" => 2,
                    "money" => $money,
                    "before_money" => $before_money,
                    "after_money" => $after_money,
                    "description" => "余额提现",
                    "tid" => $withdrawal->id
                ];
                SupplierUserBalance::query()->create($yu);
                \Log::info("[供货端余额提现]-[申请提现]-事务提交成功");
            });
        } catch (\Exception $e) {
            $message = [
                $e->getCode(),
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ];
            \Log::info("[供货端余额提现]-[申请提现]-事务提交失败", $message);
            return $this->error("提现失败，请稍后再试");
        }

        // if (!$user->where("money", $user->money)->update(["money" => ($user->money - $money)])) {
        //     \Log::info("[供货端余额提现]-[申请提现]-扣款失败");
        //     return $this->error("提现金额超出当前余额");
        // }
        //
        // SupplierWithdrawal::query()->create($data);
        // \Log::info("[供货端余额提现]-[申请提现]-增加记录成功");

        return $this->success();
    }
}
