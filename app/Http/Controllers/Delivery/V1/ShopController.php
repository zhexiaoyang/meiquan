<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /**
     * 用户创建的门店列表
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->account_shop_id) {
            $shops = Shop::select('id', 'shop_name', 'running_select as checked','shop_lng','shop_lat','shop_address',
                'contact_name','contact_phone')->where('id', $user->account_shop_id)->get();
        } else {
            $shops = Shop::select('id', 'shop_name', 'running_select as checked','shop_lng','shop_lat','shop_address',
                'contact_name','contact_phone')->where('user_id', $user->id)->get();
        }

        if (!empty($shops)) {
            // 默认选择门店ID
            $select_id = 0;
            foreach ($shops as $shop) {
                if (!$shop->wm_shop_name) {
                    $shop->wm_shop_name = $shop->shop_name;
                }
                if ($shop->checked) {
                    if ($select_id) {
                        // 已经有默认值了
                        $shop->checked = 0;
                    } else {
                        // 设置默认选择门店ID
                        $select_id = $shop->id;
                    }
                }
            }
            if (!$select_id) {
                // 没有门店选择，默认第一个选中
                $shops[0]->checked = 1;
            }
        }

        return $this->success($shops);
    }

    /**
     * 店铺分类
     * @data 2023/8/15 10:42 下午
     */
    public function category()
    {
        return $this->success(config('ps.shop_category_list'));
    }

    /**
     * 线上店铺
     * @data 2023/8/15 10:42 下午
     */
    public function takeout(Request $request)
    {
        // 类型（1 美团闪购，2 美团外卖，5 饿了么）
        if (!$type = $request->get('type')) {
            return $this->error('平台类型不能为空');
        }
        if (!in_array($type, [1,2,5])) {
            return $this->error('平台类型错误');
        }
        $user = $request->user();
        $query = Shop::select('id', 'shop_name', 'wm_shop_name','second_category as category', 'waimai_mt', 'waimai_ele', 'meituan_bind_platform as type','shop_address')->where('user_id', $user->id);
        if ($type == 1) {
            $query->where('waimai_mt', '<>', '')->whereIn('meituan_bind_platform', [4, 31]);
        } elseif ($type == 2) {
            $query->where('waimai_mt', '<>', '')->where('meituan_bind_platform', 25);
        } elseif ($type == 5) {
            $query->where('waimai_ele', '<>', '');
        }
        $shops = $query->get();
        $minkang = null;
        $meiquan = null;
        $canyin = null;
        $ele = null;
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                if (!$shop->wm_shop_name) {
                    $shop->wm_shop_name = $shop->shop_name;
                }
                $shop->category_text = config('ps.shop_category_map')[$shop->category] ?? '其它';
                if ($type == 1 || $type == 2) {
                    if ($shop->type == 4) {
                        if (!$minkang) {
                            $minkang = app("minkang");
                        }
                        $mt_res = $minkang->getShopInfoByIds(['app_poi_codes' => $shop->waimai_mt]);
                    } elseif ($type == 31) {
                        if (!$meiquan) {
                            $meiquan = app("meiquan");
                        }
                        $mt_res = $meiquan->getShopInfoByIds(['app_poi_codes' => $shop->waimai_mt]);
                    } elseif ($type == 31) {
                        if (!$canyin) {
                            $canyin = app("mtkf");
                        }
                        $mt_res = $canyin->ng_shop_info($shop->waimai_mt);
                    }
                    if (!empty($mt_res['data'][0])) {
                        if ($mt_res['data'][0]['is_online'] == 1) {
                            if ($mt_res['data'][0]['open_level'] == 1) {
                                $shop->status = 1;
                                $shop->status_text = '营业中';
                            } else {
                                $shop->status = 0;
                                $shop->status_text = '休息中';
                            }
                        } else {
                            if ($mt_res['data'][0]['is_online'] == 0) {
                                $shop->status = 0;
                                $shop->status_text = '下线';
                            } elseif ($mt_res['data'][0]['is_online'] == 2) {
                                $shop->status = 1;
                                $shop->status_text = '上单中';
                            } elseif ($mt_res['data'][0]['is_online'] == 3) {
                                $shop->status = 0;
                                $shop->status_text = '待审核';
                            }
                        }
                    } else {
                        $shop->status = 0;
                        $shop->status_text = '未知状态';
                    }
                } elseif ($type == 5) {
                    if (!$ele) {
                        $ele = app("ele");
                    }
                    $ele_res = $ele->shopBusstatus($shop->waimai_ele);
                    if (isset($ele_res['body']['data']['shop_busstatus'])) {
                        if ($ele_res['body']['data']['shop_busstatus'] == 1) {
                            $shop->status = 0;
                            $shop->status_text = '休息中';
                        } elseif ($ele_res['body']['data']['shop_busstatus'] == 2) {
                            $shop->status = 1;
                            $shop->status_text = '可预订';
                        } elseif ($ele_res['body']['data']['shop_busstatus'] == 3) {
                            $shop->status = 1;
                            $shop->status_text = '营业中';
                        } elseif ($ele_res['body']['data']['shop_busstatus'] == 4) {
                            $shop->status = 0;
                            $shop->status_text = '暂停营业';
                        }
                    } else {
                        $shop->status = 0;
                        $shop->status_text = '未知状态';
                    }
                }
                if (in_array($type, [1,2])) {
                    $shop->waimai_id = $shop->waimai_mt;
                } elseif ($type == 5) {
                    $shop->waimai_id = $shop->waimai_ele;
                }
                unset($shop->waimai_mt);
                unset($shop->waimai_ele);
            }
        }
        return $this->success($shops);
    }

    public function takeout_statistics(Request $request)
    {
        $sg = 0;
        $wm = 0;
        $ele = 0;
        $user = $request->user();
        $shops = Shop::select('waimai_mt', 'waimai_ele','meituan_bind_platform')->where('user_id', $user->id)->get();
        if (!empty($shops)) {
            foreach ($shops as $shop) {
                if ($shop->waimai_mt) {
                    if ($shop->meituan_bind_platform == 25) {
                        $wm++;
                    } else {
                        $sg++;
                    }
                }
                if ($shop->waimai_ele) {
                    $ele++;
                }
            }
        }
        $result = [
            [
                'type' => 1,
                'name' => '美团闪购',
                'count' => $sg
            ],
            [
                'type' => 2,
                'name' => '美团外卖',
                'count' => $wm
            ],
            [
                'type' => 5,
                'name' => '饿了么',
                'count' => $ele
            ],
        ];
        return $this->success($result);
    }
}
