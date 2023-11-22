<?php

namespace App\Http\Controllers\Api\Waimai\Ele;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\LogTool;
use App\Traits\NoticeTool;

class MessageController extends Controller
{
    use NoticeTool, LogTool;

    public function index(Request $request)
    {
        $result = [
            'message' => 'ok',
        ];
        return $this->success($result);
    }

}
