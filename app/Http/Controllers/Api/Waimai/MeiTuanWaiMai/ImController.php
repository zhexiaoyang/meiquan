<?php

namespace App\Http\Controllers\Api\Waimai\MeiTuanWaiMai;

use App\Models\ImMessage;
use App\Models\ImMessageItem;
use App\Models\Shop;
use App\Models\WmOrder;
use App\Task\TakeoutImMessageTask;
use App\Traits\LogTool2;
use App\Traits\NoticeTool2;
use Hhxsv5\LaravelS\Swoole\Task\Task;
use Illuminate\Http\Request;

class ImController
{
    use LogTool2, NoticeTool2;

    public $prefix_title = '[美团外卖回调&###]';

    public function create(Request $request, $platform)
    {
        if (!$biz_type = (int) $request->get("biz_type")) {
            return json_encode(['data' => 'ok']);
        }
        if ($biz_type === 2) {
            return json_encode(['data' => 'ok']);
        }
        $this->log_tool2_prefix = str_replace('###', get_meituan_develop_platform($platform) . "&Im消息推送|biz_type:{$biz_type}", $this->prefix_title);
        $this->notice_tool2_prefix = str_replace('###', get_meituan_develop_platform($platform) . "&Im消息推送|biz_type:{$biz_type}", $this->prefix_title);
        $this->log_info('全部参数', $request->all());
        $push_content_str = urldecode($request->get('push_content', ''));
        $push_content = json_decode($push_content_str, true);
        $this->log_info('美团外卖统一接口');
        $app_id = (int) $push_content['app_id'];
        if (!in_array($app_id, [5172, 6167])) {
            return json_encode(['data' => 'ok']);
        }
        // 美团门店ID
        $app_poi_code = $push_content['app_poi_code'];
        if (!$shop = Shop::select('id', 'user_id', 'waimai_mt')->where('waimai_mt', $app_poi_code)->first()) {
            return json_encode(['data' => 'ok']);
        }
        if (!$shop->user_id) {
            return json_encode(['data' => 'ok']);
        }
        // key
        $key = '';
        if ($app_id === 5172) {
            $key = substr(config("ps.minkang.secret"), 0, 16);
        } else if ($app_id === 6167) {
            $key = substr(config("ps.meiquan.secret"), 0, 16);
        }
        // 消息发送时间,10位时间戳
        $ctime = $push_content['cts'];
        // 消息id，确保消息唯一性，发送消息时，为三方的消息id，接收消息时，为美团的消息id
        $msg_id = $push_content['msg_id'];
        // 消息发送方，1：商家，2：用户
        $msg_source = (int) $push_content['msg_source'];
        // 消息类型，1：文字，2：图片，3：语音，注意b2c不支持语音，4：商品卡片，发送商品卡片类型则不关注msg_content，5：订单卡片类型商家只能接收消息，不支持给用户发送消息，只支持单聊 11：群文字，12：群图片，13：群语音，注意b2c不支持语音，14：群商品卡片 其中商品卡片单次最多传7个商品
        $msg_type = (int) $push_content['msg_type'];
        $order_id = $push_content['order_id'] ?? '';
        // 用户id，单聊时必传
        $open_user_id = $push_content['open_user_id'] ?? 0;
        // 群聊id，发送群聊消息时必传
        $group_id = $push_content['group_id'] ?? 0;
        // 开放平台侧商品标识（无须加密）
        $app_spu_codes = $push_content['app_spu_codes'] ?? '';
        // 消息内容
        $msg_content = $push_content['msg_content'] ?? '';
        if (!empty($msg_content)) {
            $msg_content = mb_convert_encoding(openssl_decrypt($msg_content, 'AES-128-CBC', $key, 0, $key), 'UTF-8');
        }
        $this->log_info("美团门店ID:{$app_poi_code}");
        $name = '匿名';
        $day_seq = 0;
        if ($order_id) {
            if ($order = WmOrder::select('id','order_id','day_seq', 'recipient_name')->where('order_id', $order_id)->first()) {
                $name = $order->recipient_name ?: '匿名';
                $day_seq = $order->day_seq;
            }
        }
        $content = '';
        switch ($msg_type) {
            case 1:
                $content = $msg_content;
                break;
            case 2:
                $content = '[图片]';
                break;
            case 3:
                $content = '[语音]';
                break;
            case 4:
                $content = '[商品]';
                break;
            case 5:
                $content = '[订单]';
                break;

        }
        if ($message = ImMessage::where('order_id', $order_id)->first()) {
            $message->update([
                'msg_id' => $msg_id,
                'msg_content' => $content,
                'is_read' => 0,
                'open_user_id' => $open_user_id,
                'ctime' => $ctime,
            ]);
        } else if (($open_user_id != -1) && $message = ImMessage::where('open_user_id', $open_user_id)->first()) {
            $message->update([
                'msg_id' => $msg_id,
                'msg_content' => $content,
                'is_read' => 0,
                'is_reply' => $msg_source === 1 ? 1 : 0,
                'open_user_id' => $open_user_id,
                'ctime' => $ctime,
            ]);
        } else {
            $message = ImMessage::create([
                'shop_id' => $shop->id,
                'user_id' => $shop->user_id,
                'app_id' => $app_id,
                'app_poi_code' => $app_poi_code,
                'order_id' => $order_id,
                'msg_id' => $msg_id,
                'msg_content' => $content,
                'biz_type' => $biz_type,
                'is_read' => 0,
                'is_reply' => $msg_source === 1 ? 1 : 0,
                'day_seq' => $day_seq,
                'name' => $name,
                'title' => ($day_seq ? '#' . $day_seq : '') . $name,
                'image' => mb_substr($name, 0, 1),
                'group_id' => $group_id,
                'open_user_id' => $open_user_id,
                'ctime' => $ctime,
            ]);
        }
        ImMessageItem::create([
            'message_id' => $message->id,
            'msg_id' => $msg_id,
            'msg_type' => $msg_type,
            'msg_source' => $msg_source,
            'msg_content' => $msg_content,
            'open_user_id' => $open_user_id,
            'group_id' => $group_id,
            'app_spu_codes' => $app_spu_codes,
            'ctime' => $ctime,
            'is_read' => 0,
        ]);
        Task::deliver(new TakeoutImMessageTask((int) $message->id, (int) $message->user_id), true);
        return json_encode(['data' => 'ok']);
    }
}
