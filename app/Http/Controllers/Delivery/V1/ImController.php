<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\ImMessage;
use App\Models\ImMessageItem;
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
        $query = ImMessage::select('id', 'title', 'image', 'ctime', 'is_read')->where('user_id', $user_id);
        if ($shop_id = $request->get('shop_id')) {
            $query->where('shop_id', $shop_id);
        }
        $data = $query->orderByDesc('updated_at')->paginate($page_size);

        if ($data->isNotEmpty()) {
            foreach ($data as $v) {
                $v->time_ago = tranTime($v->ctime);
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
}
