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

    public function xunfei_yyzz(Request $request)
    {
        if (!$url = $request->get('url')) {
            return $this->error('图片不能为空', 422);
        }
        $xf = new XunFei();
        $res = $xf->yyzz($url);

        $data = [
            'name' => $res['data']['biz_license_company_name'] ?? '',
            'code' => $res['data']['biz_license_credit_code'] ?? '',
        ];
        return $this->success($data);
    }
}
