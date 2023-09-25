<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\ImMessage;
use App\Models\ImMessageItem;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\Request;

class ImController extends Controller
{
    public function index(Request $request)
    {
        $user_id = $request->user()->id;
        $page_size = $request->get('page_size', 20);
        if (!is_numeric($page_size) || $page_size < 20) {
            $page_size = 20;
        }
        $query = ImMessage::select('id', 'title', 'image', 'ctime', 'is_read', 'msg_content', 'type', 'is_reply')->where('user_id', $user_id);
        if ($shop_id = $request->get('shop_id')) {
            $query->where('shop_id', $shop_id);
        }
        $type = (int) $request->get('type');
        if ($type === 1) {
            $query->where('is_read', 0);
        } elseif ($type === 2) {
            $query->where('is_read', 1)->where('is_read', 0);
        }
        $data = $query->orderByDesc('updated_at')->paginate($page_size);

        if ($data->isNotEmpty()) {
            foreach ($data as $v) {
                $v->time_ago = tranTime($v->ctime);
                $v->sex = 0;
                if (mb_strpos($v->title, '先生') > 0) {
                    $v->sex = 1;
                }
                if (mb_strpos($v->title, '女士') > 0) {
                    $v->sex = 2;
                }
            }
        }

        return $this->page($data);
    }

    public function show(Request $request)
    {
        if (!$message_id = $request->get('message_id')) {
            return $this->error('消息不存在');
        }
        if (!$message = ImMessage::find($message_id)) {
            return $this->error('消息不存在');
        }
        $user_id = $request->user()->id;
        if ($message->user_id != $user_id) {
            return $this->error('消息不存在!');
        }
        $page_size = $request->get('page_size', 20);
        if (!is_numeric($page_size) || $page_size < 10) {
            $page_size = 10;
        }
        $data = ImMessageItem::select('id','msg_type','msg_source','msg_content','ctime','is_read')
            ->where('message_id', $message_id)->orderByDesc('id')->paginate($page_size);
        $message->update([
            'is_read' => 1
        ]);
        return $this->page($data);
    }

    public function order_show(Request $request)
    {
        if (!$message_id = $request->get('message_id')) {
            return $this->error('消息不存在');
        }
        if (!$message = ImMessage::find($message_id)) {
            return $this->error('消息不存在');
        }
        $user_id = $request->user()->id;
        if ($message->user_id != $user_id) {
            return $this->error('消息不存在!');
        }
        $data = [
            'title' => $message->title,
            'shop_name' => '',
            'phone' => '',
            'order_id' => 0,
            'order_status' => ''
        ];
        if ($order = Order::select('id','status','caution')->where('order_id', $message->order_id)->first()) {
            $phone = '';
            // 正则匹配电话尾号，去掉默认备注
            preg_match_all('/收货人隐私号.*\*\*\*\*(\d\d\d\d)/', $order->caution, $preg_result);
            if (!empty($preg_result[1][0])) {
                $phone = "(尾号{$preg_result[1][0]})";
            }
            $data = [
                'title' => $message->title,
                'shop_name' => 'wm_poi_name',
                'phone' => $phone,
                'order_id' => $order->id,
                'order_status' => config('ps.delivery_order_status')[$order->status] ?? ''
            ];
        }
        return $this->success($data);
    }

    public function shops(Request $request)
    {
        $user_id = $request->user()->id;
        $shops = Shop::where('user_id', $user_id)->get();
        $mt = [];
        $ele = [];
        if ($shops->isNotEmpty()) {
            foreach ($shops as $shop) {
                if ($shop->waimai_mt) {
                    $mt[] = [
                        'shop_id' => $shop->id,
                        'name' => $shop->mt_shop_name ?: $shop->shop_name,
                        'platform' => 1
                    ];
                }
                if ($shop->waimai_ele) {
                    $ele[] = [
                        'shop_id' => $shop->id,
                        'name' => $shop->ele_shop_name ?: $shop->shop_name,
                        'platform' => 2
                    ];
                }
            }
        }

        $data = [];
        if (!empty($mt)) {
            $data[] = [
                'name' => '美团外卖',
                'shops' => $mt,
            ];
        }
        if (!empty($ele)) {
            $data[] = [
                'name' => '饿了么',
                'shops' => $ele,
            ];
        }

        return $this->success($data);
    }

    public function set_read(Request $request)
    {
        $user_id = $request->user()->id;
        ImMessage::where('user_id', $user_id)->update(['is_read' => 1]);
        return $this->success();
    }
}
