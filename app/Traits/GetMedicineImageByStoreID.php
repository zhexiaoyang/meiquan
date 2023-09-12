<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait GetMedicineImageByStoreID
{
    public function getImageByStoreId($platform, $type, $shop_id, $food_id, $mt_spu_id)
    {
        if ($platform == 1) {
            if ($type === 3 || $type == 4 || $type == 31) {
                $mt = '';
                if ($type === 3) {
                    $mt = app('jay');
                } elseif ($type === 4) {
                    $mt = app('minkang');
                } elseif ($type === 31) {
                    $mt = app('meiquan');
                }
                $product = $mt->retail_get($shop_id, $food_id);
                if (!empty($product['data']['picture'])) {
                    $pictures = explode(',', $product['data']['picture']);
                    return $pictures[0] ?? '';
                }
            } elseif ($type == 35) {
                $mt = app('mtkf');
                $product = $mt->wmoper_food_info($food_id, $shop_id);
                if (!empty($product['data']['pictures'])) {
                    $pictures = explode(',', $product['data']['pictures']);
                    return $pictures[0] ?? '';
                }
            }
        } elseif ($platform == 2) {
            $ele = app('ele');
            $product = $ele->skuList($shop_id, '', $mt_spu_id);
            if (!empty($product['body']['data']['list'][0]['photos'])) {
                return $product['body']['data']['list'][0]['photos'][0]['url'] ?? '';
            }
        }

        return false;
    }
}
