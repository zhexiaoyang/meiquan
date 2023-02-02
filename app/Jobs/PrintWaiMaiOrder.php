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
                if (isset($item['upc']) && isset($printer->upc) && $printer->upc) {
                    // $content .= $item['upc'].'<BR>';
                    $content .= $this->bar_code($item['upc']).'<BR>';
                }
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

    public function bar_code($strnum)
    {
        $chr = '';
        $codeB = array("\x30","\x31","\x32","\x33","\x34","\x35","\x36","\x37","\x38","\x39");//匹配字符集B
        $codeC = array("\x00","\x01","\x02","\x03","\x04","\x05","\x06","\x07","\x08","\x09","\x0A","\x0B","\x0C","\x0D","\x0E","\x0F","\x10","\x11","\x12","\x13","\x14","\x15","\x16","\x17","\x18","\x19","\x1A","\x1B","\x1C","\x1D","\x1E","\x1F","\x20","\x21","\x22","\x23","\x24","\x25","\x26","\x27","\x28","\x29","\x2A","\x2B","\x2C","\x2D","\x2E","\x2F","\x30","\x31","\x32","\x33","\x34","\x35","\x36","\x37","\x38","\x39","\x3A","\x3B","\x3C","\x3D","\x3E","\x3F","\x40","\x41","\x42","\x43","\x44","\x45","\x46","\x47","\x48","\x49","\x4A","\x4B","\x4C","\x4D","\x4E","\x4F","\x50","\x51","\x52","\x53","\x54","\x55","\x56","\x57","\x58","\x59","\x5A","\x5B","\x5C","\x5D","\x5E","\x5F","\x60","\x61","\x62","\x63");//匹配字符集C
        $length = strlen($strnum);
        $b=array();
        $b[0] = "\x1b";
        $b[1] = "\x64";
        $b[2] = "\x02";
        $b[3] = "\x1d";
        $b[4] = "\x48";
        $b[5] = "\x32";//条形码显示控制，\x32上图下字，\x31上字下图，\x30只显示条形码
        $b[6] = "\x1d";
        $b[7] = "\x68";
        $b[8] = "\x50";// \x30 设置条形码高度，7F是最大的高度
        $b[9] = "\x1d";
        $b[10] = "\x77";
        $b[11] = "\x02";// \x01 设置条形码宽度,1-6
        $b[12] = "\x1d";
        $b[13] = "\x6b";
        $b[14] = "\x49";//选择条形码类型code128,code39,codabar等等
        $b[15]  = chr($length + 2);
        $b[16] = "\x7b";
        $b[17] = "\x42";//选择字符集
        if($length > 14 && is_numeric($strnum)){//大于14个字符,且为纯数字的进来这个区间
            $b[17] = "\x43";
            $j = 0;
            $key = 18;
            $ss = $length/2;//初始化数组长度
            if($length%2 == 1){//判断条形码为单数
                $ss = $ss-0.5;
            }
            for ($i = 0; $i < $ss; $i++){
                $temp = substr($strnum,$j,2);
                $iindex = intval($temp);
                $j = $j+2;
                if($iindex == 0){
                    $chr = '';
                    if($b[$key + $i-1] == '0' && $b[$key + $i-2] == '0'){//判断前面的为字符集B,此时不需要转换字符集
                        $b[$key + $i] = $codeB[0];
                        $b[$key + $i + 1] = $codeB[0];
                        $key += 1;
                    }else{
                        if($b[$key + $i-1] == 'C' && $b[$key + $i-2] == '{'){//判断前面的为字符集C时转换字符集B
                            $b[$key + $i - 2] = "\x7b";
                            $b[$key + $i - 1] = "\x42";
                            $b[$key + $i] = $codeB[0];
                            $b[$key + $i + 1] = $codeB[0];
                            $key += 1;
                        }else{
                            $b[$key + $i] = "\x7b";
                            $b[$key + $i + 1] = "\x42";
                            $b[$key + $i + 2] = $codeB[0];
                            $b[$key + $i + 3] = $codeB[0];
                            $key += 3;
                        }
                    }
                }else{
                    if($b[$key + $i-1] == '0' && $b[$key + $i-2] == '0' && $chr != 'chr'){//判断前面的为字符集B,此时要转换字符集C
                        $b[$key + $i] = "\x7b";
                        $b[$key + $i + 1] = "\x43";
                        $b[$key + $i + 2] = $codeC[$iindex];
                        $key += 2;
                    }else{
                        $chr = '';
                        $b[$key + $i] = $codeC[$iindex];
                        if($iindex == 48) $chr = 'chr';//判断chr(48)等于0的情况
                    }
                }
            }
            @$lastkey = end(array_keys($b));//取得数组的最后一个元素的键
            if($length % 2 > 0){
                $lastnum = substr($strnum,-1);//取得字符串的最后一个数字
                if($b[$lastkey] == '0' && $b[$lastkey-1] == '0'){//判断前面的为字符集B,此时不需要转换字符集
                    $b[$lastkey + 1] = $codeB[$lastnum];
                }else{
                    $b[$lastkey + 1] = "\x7b";
                    $b[$lastkey + 2] = "\x42";
                    $b[$lastkey + 3] = $codeB[$lastnum];
                }
            }
            @$b[15] = chr(end(array_keys($b)) - 15);//得出条形码长度
            $str = implode("",$b);
        }else{//1-14个字符的纯数字和非纯数字的条形码进来这个区间，支持数字，大小写字母，特殊字符例如:  !@#$%^&*()-=+_
            $str = "\x1b\x64\x02\x1d\x48\x32\x1d\x68\x50\x1d\x77\x02\x1d\x6b\x49".chr($length + 2)."\x7b\x42".$strnum;
        }
        return $str;
    }
}
