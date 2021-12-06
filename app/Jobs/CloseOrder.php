<?php

namespace App\Jobs;

use App\Models\SupplierOrder;
use App\Models\User;
use App\Models\UserFrozenBalance;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

// 代表这个类需要被放到队列中执行，而不是触发时立即执行
class CloseOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;

    public function __construct(SupplierOrder $order, $delay)
    {
        $this->order = $order;
        // 设置延迟的时间，delay() 方法的参数代表多少秒之后执行
        $this->delay($delay);
    }

    // 定义这个任务类具体的执行逻辑
    // 当队列处理器从队列中取出任务时，会调用 handle() 方法
    public function handle()
    {
        \Log::info("[商城订单-检查支付，订单号：{$this->order->no}]-开始");
        // 判断对应的订单是否已经被支付
        // 如果已经支付则不需要关闭订单，直接退出
        if ($this->order->paid_at) {
            \Log::info("[商城订单-检查支付，订单号：{$this->order->no}]-已支付，结束");
            return;
        }
        // 通过事务执行 sql
        try {
            \DB::transaction(function() {
                // 将订单的 closed 字段标记为 true，即关闭订单
                \Log::info("[商城订单-检查支付，订单号：{$this->order->no}]-未支付，操作取消订单");
                if ($this->order->frozen_fee > 0 && $this->order->status === 0) {
                    \Log::info("[商城订单-检查支付，订单号：{$this->order->no}]-未支付，操作取消订单，冻结余额退款");
                    if ($user = User::query()->find($this->order->user_id)) {
                        // 订单未支付-自动取消-冻结余额返回
                        \DB::table('users')->where('id', $this->order->user_id)->increment('frozen_money', $this->order->frozen_fee);
                        $logs = new UserFrozenBalance([
                            "user_id" => $this->order->user_id,
                            "money" => $this->order->frozen_fee,
                            "type" => 1,
                            "before_money" => $user->frozen_money,
                            "after_money" => $user->frozen_money + $this->order->frozen_fee,
                            "description" => "商城订单取消退款：{$this->order->no}",
                            "tid" => $this->order->id
                        ]);
                        $logs->save();
                    }
                }
                $this->order->update(['status' => 90,'cancel_reason' => '超时未支付','cancel_at' => date("Y-m-d H:i:s"),]);
                // 循环遍历订单中的商品 SKU，将订单中的数量加回到 SKU 的库存中去
                foreach ($this->order->items as $item) {
                    $item->product->addStock($item->amount);
                }
            });
        } catch (\Exception $e) {
            $message = [
                $e->getCode(),
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ];
            \Log::info("[商城订单-检查支付，订单号：{$this->order->no}]-未支付，操作取消订单，失败", $message);
            $logs = [
                "des" => "[商城订单-检查支付]取消失败",
                "id" => $this->order->id,
                "order_id" => $this->order->no
            ];
            $dd = app("ding");
            $dd->sendMarkdownMsgArray("商城订单检查支付，订单号：{$this->order->no}]-取消失败", $logs);
        }
    }
}
