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
        $query = Shop::select('id', 'shop_name', 'wm_shop_name','second_category as category', 'waimai_mt', 'waimai_ele', 'meituan_bind_platform as bind_type','shop_address')->where('user_id', $user->id);
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
                    if ($shop->bind_type == 4) {
                        $shop->type = 1;
                        $shop->type_text = '美团闪购';
                        if (!$minkang) {
                            $minkang = app("minkang");
                        }
                        $mt_res = $minkang->getShopInfoByIds(['app_poi_codes' => $shop->waimai_mt]);
                    } elseif ($shop->bind_type == 31) {
                        $shop->type = 1;
                        $shop->type_text = '美团闪购';
                        if (!$meiquan) {
                            $meiquan = app("meiquan");
                        }
                        $mt_res = $meiquan->getShopInfoByIds(['app_poi_codes' => $shop->waimai_mt]);
                    } elseif ($shop->bind_type == 25) {
                        $shop->type = 2;
                        $shop->type_text = '美团外卖';
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
                    $shop->type = 5;
                    $shop->type_text = '饿了么';
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
                unset($shop->bind_type);
                unset($shop->waimai_mt);
                unset($shop->waimai_ele);
            }
        }
        return $this->success($shops);
    }

    /**
     * 线上店铺-统计
     * @data 2023/8/16 9:46 上午
     */
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

    public function bind_message(Request $request)
    {
        $type = $request->get('type');
        if (!in_array($type, [1,2,5])) {
            return $this->error('类型错误');
        }
        if ($type == 1) {
            $result = [
                'type' => 1,
                'name' => '美团闪购',
                'title' => '绑定美团闪购店铺，自动同步订单到美全达',
                'url' => 'https://open-shangou.meituan.com/erp/login?code=TXYHxuB%2FLfDchwlTIXhL09oW6NV%2FUACqFYqkPvQ5Au043ywld5WQ68G3pO%2BijyRvvQxYQDnUbEIgoUC37o18H3UKSPlIxR2RNzEYXSrauE0%3D&auth_type=oauth&company_name=%E5%90%89%E6%9E%97%E7%9C%81%E7%BE%8E%E5%85%A8%E7%A7%91%E6%8A%80%E6%9C%89%E9%99%90%E8%B4%A3%E4%BB%BB%E5%85%AC%E5%8F%B8=#!/login',
                'text' => '
                    <ul>
                        <li>1.使用美团外卖账号进行授权</li>
                        <li>2.当前授权适用于美团外卖零售品类商户</li>
                        <li>3.支持美全达和其他系统同时使用</li>
                        <li>4.支持图片实时更新及查看预定人信息</li>
                    </ul>
                '
            ];
        } elseif ($type == 2) {
            $result = [
                'type' => 2,
                'name' => '美团外卖',
                'title' => '餐饮授权暂不支持手机端绑定，请电脑端登录操作',
                'url' => 'https://nr.ele.me/eleme_nr_bfe_retail/api_bind_shop#/bindShop?source=C6668C55DC7792FA783B2EEE6D423415FB0F3075D4721105516FC21012833B61&fromSys=2',
                'text' => '
                    <ul>
                        <li>1.使用饿了么账号进行授权</li>
                        <li>2.当前授权适用于饿了么零售品类商户</li>
                        <li>3.支持美全达和其他系统同时使用</li>
                        <li>4.支持图片实时更新及查看预定人信息</li>
                    </ul>
                '
            ];
        } elseif ($type == 5) {
            $result = [
                'type' => 5,
                'name' => '饿了么',
                'title' => '绑定饿了么店铺，自动同步订单到美全达',
                'url' => 'https://nr.ele.me/eleme_nr_bfe_retail/api_bind_shop#/bindShop?source=C6668C55DC7792FA783B2EEE6D423415FB0F3075D4721105516FC21012833B61&fromSys=2',
                'text' => '
                    <ul>
                        <li>1.使用饿了么账号进行授权</li>
                        <li>2.当前授权适用于饿了么零售品类商户</li>
                        <li>3.支持美全达和其他系统同时使用</li>
                        <li>4.支持图片实时更新及查看预定人信息</li>
                    </ul>
                '
            ];
        }
        return $this->success($result);
    }
}
