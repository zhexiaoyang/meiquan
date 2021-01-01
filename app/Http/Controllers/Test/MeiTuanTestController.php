<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Models\MkShop;
use Illuminate\Http\Request;

class MeiTuanTestController extends Controller
{

    private $meituan;

    public function __construct()
    {
        $this->meituan = app('minkang');
    }

    public function shopIdList()
    {
        $res = $this->meituan->getShopIds();

        if (!empty($ids = $res['data'])) {
            $data = array_chunk($ids, 200);
            foreach ($data as $v) {
                $shops = [];
                $params['app_poi_codes'] = implode(",", $v);
                $res = $this->meituan->getShopInfoByIds($params);
                if (!empty($res['data'])) {
                    foreach ($res['data'] as $shop) {
                        $tmp['app_poi_code'] = $shop['app_poi_code'];
                        $tmp['name'] = $shop['name'];
                        $tmp['address'] = $shop['address'];
                        $tmp['longitude'] = $shop['longitude']/1000000;
                        $tmp['latitude'] = $shop['latitude']/1000000;
                        $tmp['pic_url'] = $shop['pic_url'] ?? '';
                        $tmp['pic_url_large'] = $shop['pic_url_large'] ?? "";
                        $shops[] = $tmp;
                    }
                }
                if (!empty($shops)) {
                    MkShop::query()->insert($shops);
                }
            }
            return true;
        }

        return false;
    }

}
