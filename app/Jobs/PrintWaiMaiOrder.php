<?php

namespace App\Jobs;

use App\Libraries\DingTalk\DingTalkRobotNotice;
use App\Libraries\Feie\Feie;
use App\Models\WmOrder;
use App\Models\WmPrinter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrintWaiMaiOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $order_id;
    private $printer;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $order_id, WmPrinter $printer)
    {
        $this->order_id = $order_id;
        $this->printer = $printer;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        sleep(2);
        if (!$order = WmOrder::find($this->order_id)) {
            $ding = new DingTalkRobotNotice("6b2970a007b44c10557169885adadb05bb5f5f1fbe6d7485e2dcf53a0602e096");
            $ding->sendTextMsg("打印订单不存在|订单号：{$this->order_id}|打印机：{$this->printer->id}");
        }
        $printer = $this->printer;

        $platform = [ '', '美团外卖', '饿了么'];
        $content = "<CB>#{$order->day_seq} {$platform[$order->platform]}</CB><BR>";
        $content .= "<C>{$order->wm_shop_name}</C><BR>";
        if ($order->delivery_time > 0) {
            $delivery_time = date("Y-m-d H:i:s", $order->delivery_time);
            $content .= "<C><BOLD>【预约单】</BOLD></C><BR>";
            $content .= "<C><BOLD>送达时间：{$delivery_time}</BOLD></C><BR>";
        } else {
            $content .= "<C><BOLD>【立即送达】</BOLD></C><BR>";
        }
        $content .= '--------------------------------<BR>';
        $content .= "<B>备注：{$order->caution}</B><BR>";
        $content .= '--------------------------------<BR>';
        $content .= "<B>{$order->recipient_name}</B><BR>";
        $content .= "<B>{$order->recipient_phone}</B><BR>";
        $content .= "<B>{$order->recipient_address}</B><BR>";

        // $content .= "<B>件商品</B><BR>";

        $order->load("items");
        // $content .= '名称            单价  数量 金额<BR>';
        $content .= '--------------------------------<BR>';
        $content .= '名称                   数量 单价<BR>';
        $content .= '--------------------------------<BR>';
        $total_num = 0;
        if (!empty($order->items)) {
            $A = 24;
            $B = 5;
            $C = 2;
            $D = 5;
            foreach ($order->items as $item) {
                $name = $item['food_name'];
                $price = $item['price'];
                $num = $item['quantity'];
                $prices = $item['price']*$item['quantity'];
                $total_num += $num;
                $kw3 = '';
                $kw1 = '';
                $kw2 = '';
                $kw4 = '';
                $str = $name;
                $blankNum = $A;//名称控制为14个字节
                $lan = mb_strlen($str,'utf-8');
                $m = 0;
                $j=1;
                $blankNum++;
                $result = array();
                // if(strlen($price) < $B){
                //     $k1 = $B - strlen($price);
                //     for($q=0;$q<$k1;$q++){
                //         $kw1 .= ' ';
                //     }
                //     $price = $price.$kw1;
                // }
                if(strlen($num) < $C){
                    $k2 = $C - strlen($num);
                    for($q=0;$q<$k2;$q++){
                        $kw2 .= ' ';
                    }
                    $num = $num.$kw2;
                }
                // if(strlen($prices) < $D){
                //     $k3 = $D - strlen($prices);
                //     for($q=0;$q<$k3;$q++){
                //         $kw4 .= ' ';
                //     }
                //     $prices = $prices.$kw4;
                // }
                if(strlen($price) < $D){
                    $k1 = $D - strlen($price);
                    for($q=0;$q<$k1;$q++){
                        $kw1 .= ' ';
                    }
                    $price = $price.$kw1;
                }
                for ($i=0;$i<$lan;$i++){
                    $new = mb_substr($str,$m,$j,'utf-8');
                    $j++;
                    if(mb_strwidth($new,'utf-8')<$blankNum) {
                        if($m+$j>$lan) {
                            $m = $m+$j;
                            $tail = $new;
                            $lenght = iconv("UTF-8", "GBK//IGNORE", $new);
                            $k = $A - strlen($lenght);
                            for($q=0;$q<$k;$q++){
                                $kw3 .= ' ';
                            }
                            if($m==$j){
                                $tail .= $kw3.' '.$num.' '.$price;
                            }else{
                                $tail .= $kw3.'<BR>';
                            }
                            break;
                        }else{
                            $next_new = mb_substr($str,$m,$j,'utf-8');
                            if(mb_strwidth($next_new,'utf-8')<$blankNum) continue;
                            else{
                                $m = $i+1;
                                $result[] = $new;
                                $j=1;
                            }
                        }
                    }
                }
                $head = '';
                foreach ($result as $key=>$value) {
                    if($key < 1){
                        $v_lenght = iconv("UTF-8", "GBK//IGNORE", $value);
                        $v_lenght = strlen($v_lenght);
                        if($v_lenght == 13) $value = $value." ";
                        $head .= $value.' '.$num.' '.$price;
                    }else{
                        $head .= $value.'<BR>';
                    }
                }
                $content .= $head.$tail;
                @$nums += $prices;
            }
        }

        // 其它费用
        $order->load("receives");
        // $content .= '名称            单价  数量 金额<BR>';
        $content .= '--------------------------------<BR>';
        $content .= '其它费用                        <BR>';
        $content .= '--------------------------------<BR>';
        if (!empty($order->receives)) {
            $A = 24;
            $B = 5;
            $C = 2;
            $D = 5;
            foreach ($order->receives as $item) {
                // \Log::info($item['comment']);
                $name = $item['comment'];
                $price = '-' . $item['money'];
                $num = '';
                $kw3 = '';
                $kw1 = '';
                $kw2 = '  ';
                $kw4 = '';
                $str = $name;
                $blankNum = $A;//名称控制为14个字节
                $lan = mb_strlen($str,'utf-8');
                $m = 0;
                $j=1;
                $blankNum++;
                $result = array();
                if(strlen($price) < $D){
                    $k1 = $D - strlen($price);
                    for($q=0;$q<$k1;$q++){
                        $kw1 .= ' ';
                    }
                    $price = $price.$kw1;
                }
                for ($i=0;$i<$lan;$i++){
                    $new = mb_substr($str,$m,$j,'utf-8');
                    $j++;
                    if(mb_strwidth($new,'utf-8')<$blankNum) {
                        if($m+$j>$lan) {
                            $m = $m+$j;
                            $tail = $new;
                            $lenght = iconv("UTF-8", "GBK//IGNORE", $new);
                            $k = $A - strlen($lenght);
                            for($q=0;$q<$k;$q++){
                                $kw3 .= ' ';
                            }
                            if($m==$j){
                                $tail .= $kw3.' '.$num.' '.$price;
                            }else{
                                $tail .= $kw3.'<BR>';
                            }
                            break;
                        }else{
                            $next_new = mb_substr($str,$m,$j,'utf-8');
                            if(mb_strwidth($next_new,'utf-8')<$blankNum) continue;
                            else{
                                $m = $i+1;
                                $result[] = $new;
                                $j=1;
                            }
                        }
                    }
                }
                $head = '';
                foreach ($result as $key=>$value) {
                    if($key < 1){
                        $v_lenght = iconv("UTF-8", "GBK//IGNORE", $value);
                        $v_lenght = strlen($v_lenght);
                        if($v_lenght == 13) $value = $value." ";
                        $head .= $value.' '.$num.' '.$price;
                    }else{
                        $head .= $value.'<BR>';
                    }
                }
                $content .= $head.$tail;
            }
        }
        $content .= "<BR><BOLD>总件数：{$total_num}</BOLD><BR>";
        $content .= '--------------------------------<BR>';
        $content .= "订单编号：{$order->order_id}<BR>";
        $ctime = date("Y-m-d H:i:s", $order->ctime);
        $ptime = date("Y-m-d H:i:s");
        $content .= "下单时间：{$ctime}<BR>";
        $content .= "打印时间：{$ptime}<BR>";
        $content .= '<BR>';
        $content .= '<BR>';

        $f = new Feie();
        $f->print_msg($printer->sn, $content, $printer->number);
    }
}
