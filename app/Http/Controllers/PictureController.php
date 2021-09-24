<?php

namespace App\Http\Controllers;

use App\Libraries\Baidu\Baidu;
use App\Libraries\XunFei\XunFei;
use Illuminate\Http\Request;

class PictureController extends Controller
{
    public function ticket(Request $request)
    {
        $file = $request->file('file')->path();
        $xf = new XunFei();
        $res = $xf->xfyun($file);
        return $this->success($res);
    }
}
