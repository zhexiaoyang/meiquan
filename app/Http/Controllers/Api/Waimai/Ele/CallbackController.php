<?php

namespace App\Http\Controllers\Api\Waimai\Ele;

use App\Http\Controllers\Controller;
use App\Http\Requests\Request;
use App\Traits\LogTool;
use App\Traits\NoticeTool;

class CallbackController extends Controller
{
    use NoticeTool, LogTool;

    public function index(Request $request)
    {
        $result = [
            'id' => 'fbcae8d5-5708-4aef-8bea-6d0dc83f1740',
            'result' => null,
            'error' => null,
        ];
        return $this->success($result);
    }

}
