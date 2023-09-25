<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\QuickReply;
use Illuminate\Http\Request;

class QuickReplyController extends Controller
{
    public function index(Request $request)
    {
        $user_id = $request->user()->id;
        $page_size = $request->get('page_size', 20);
        if (!is_numeric($page_size) || $page_size < 20) {
            $page_size = 20;
        }
        $query = QuickReply::select('id', 'text')->where('user_id', $user_id);
        if ($shop_id = $request->get('shop_id')) {
            $query->where('shop_id', $shop_id);
        }
        $data = $query->orderByDesc('id')->paginate($page_size);

        return $this->page($data);
    }

    public function show(Request $request)
    {
        if (!$id = $request->get('id')) {
            return $this->error('ID不能为空');
        }
        if (!$quick_reply = QuickReply::find($id)) {
            return $this->error('内容不存在');
        }
        $user_id = $request->user()->id;
        if ($quick_reply->user_id != $user_id) {
            return $this->error('内容不存在!');
        }

        return $this->success($quick_reply);
    }
    public function store(Request $request)
    {
        if (!$text = $request->get('text')) {
            return $this->error('内容不能为空');
        }
        $user_id = $request->user()->id;

        $quick_reply = QuickReply::create([
            'user_id' => $user_id,
            'text' => $text,
        ]);

        return $this->success(['id' => $quick_reply->id]);
    }

    public function update(Request $request)
    {
        if (!$id = $request->get('id')) {
            return $this->error('ID不能为空');
        }
        if (!$text = $request->get('text')) {
            return $this->error('内容不能为空');
        }
        if (!$quick_reply = QuickReply::find($id)) {
            return $this->error('内容不存在');
        }
        $user_id = $request->user()->id;
        if ($quick_reply->user_id != $user_id) {
            return $this->error('内容不存在!');
        }

        $quick_reply->update([
            'text' => $text,
        ]);

        return $this->success(['id' => $quick_reply->id]);
    }

    public function destroy(Request $request)
    {
        if (!$id = $request->get('id')) {
            return $this->error('ID不能为空');
        }
        if (!$quick_reply = QuickReply::find($id)) {
            return $this->error('内容不存在');
        }
        $user_id = $request->user()->id;
        if ($quick_reply->user_id != $user_id) {
            return $this->error('内容不存在!');
        }
        $quick_reply->delete();

        return $this->success('删除成功');
    }
}
