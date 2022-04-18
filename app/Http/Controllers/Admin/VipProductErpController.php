<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopErpSetting;
use App\Models\VipProduct;
use App\Models\VipProductException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VipProductErpController extends Controller
{
    public $success_num = 0;
    public $error_num = 0;

    public function erp_cost(VipProduct $vipProduct)
    {
        if (!$setting = ShopErpSetting::where('shop_id', $vipProduct->shop_id)->first()) {
            return $this->error('该门店未对接ERP');
        }
        switch ($setting->type) {
            case 1:
                return $this->wanxiang($vipProduct, $setting);
        }
    }

    public function erp_cost_all(Request $request)
    {
        if (!$setting = ShopErpSetting::where('shop_id', $request->get('shop_id', 0))->first()) {
            return $this->error('该门店未对接ERP');
        }
        switch ($setting->type) {
            case 1:
                return $this->wanxiang_all($setting);
        }
    }

    public function wanxiang_all(ShopErpSetting $setting)
    {
        VipProduct::query()->where('shop_id', $setting->shop_id)->chunk(200, function ($products) use ($setting) {
            $product_ids = $products->pluck('upc')->toArray();
            $product_in = implode("','", $product_ids);
            \Log::info('同步ERP成本价条码1', [$product_in]);
            $data = DB::connection('wanxiang_haidian')
                ->select("SELECT 药品ID as id,进价 as cost,upc FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'{$setting->sync_erp_shop_id}' AND [upc] IN ('{$product_in}')");
            $upcs = [];
            \Log::info('同步ERP成本价条码2', [$data]);
            if (!empty($data)) {
                foreach ($data as $v) {
                    $upcs[$v->upc] = $v->cost;
                }
            }
            $error_data = [];
            foreach ($products as $product) {
                $error = false;
                if (isset($upcs[$product->upc])) {
                    $cost = floatval($upcs[$product->upc]);
                    $product->cost = $cost;
                    $product->save();
                    if ($cost == 0) {
                        $error = true;
                        $data[] = [
                            'product_id' => $product->id,
                            'shop_id' => $product->shop_id,
                            'platform_id' => $product->platform_id,
                            'shop_name' => $product->shop_name,
                            'app_medicine_code' => $product->app_medicine_code,
                            'name' => $product->name,
                            'spec' => $product->spec,
                            'upc' => $product->upc,
                            'price' => $product->price,
                            'cost' => $product->cost,
                            'platform' => $product->platform,
                            'error_type' => 1,
                            'error' => '成本价为0',
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                        ];
                    } else if ($cost >= $product->price) {
                        $error = true;
                        $error_data[] = [
                            'product_id' => $product->id,
                            'shop_id' => $product->shop_id,
                            'platform_id' => $product->platform_id,
                            'shop_name' => $product->shop_name,
                            'app_medicine_code' => $product->app_medicine_code,
                            'name' => $product->name,
                            'spec' => $product->spec,
                            'upc' => $product->upc,
                            'price' => $product->price,
                            'cost' => $product->cost,
                            'platform' => $product->platform,
                            'error_type' => 2,
                            'error' => '成本价大于等于销售价',
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                        ];
                    }
                } else {
                    $error = true;
                    $error_data[] = [
                        'product_id' => $product->id,
                        'shop_id' => $product->shop_id,
                        'platform_id' => $product->platform_id,
                        'shop_name' => $product->shop_name,
                        'app_medicine_code' => $product->app_medicine_code,
                        'name' => $product->name,
                        'spec' => $product->spec,
                        'upc' => $product->upc,
                        'price' => $product->price,
                        'cost' => $product->cost,
                        'platform' => $product->platform,
                        'error_type' => 3,
                        'error' => 'ERP中商品未找到',
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                    ];
                }
                if ($error) {
                    $this->error_num += 1;
                } else {
                    $this->success_num += 1;
                }
            }
            if (!empty($error_data)) {
                VipProductException::insert($data);
            }
        });
        return $this->success([],"同步完成:成功 {$this->success_num},异常 {$this->error_num}");
    }

    public function wanxiang(VipProduct $product, ShopErpSetting $setting)
    {
        $exception_status = false;
        $exception_data = [
            'product_id' => $product->id,
            'shop_id' => $product->shop_id,
            'platform_id' => $product->platform_id,
            'shop_name' => $product->shop_name,
            'app_medicine_code' => $product->app_medicine_code,
            'name' => $product->name,
            'spec' => $product->spec,
            'upc' => $product->upc,
            'price' => $product->price,
            'cost' => $product->cost,
            'platform' => $product->platform,
            'error_type' => 3,
            'error' => 'ERP中商品未找到',
            // 'created_at' => date("Y-m-d H:i:s"),
            // 'updated_at' => date("Y-m-d H:i:s"),
        ];

        $data = DB::connection('wanxiang_haidian')
            ->select("SELECT 药品ID as id,进价 as cost FROM [dbo].[v_store_m_mtxs] WHERE [门店ID] = N'{$setting->sync_erp_shop_id}' AND [upc] = N'{$product->upc}'");
        \Log::info('单个药品同步ERP成本价', $data);

        if (!empty($data)) {
            foreach ($data as $v) {
                $cost = floatval($v->cost);
                $product->cost = $cost;
                $product->save();
                if ($cost == 0) {
                    $exception_data['error_type'] = '成本价为0';
                    $exception_data['error'] = 1;
                } else if ($cost >= $product->price) {
                    $exception_data['error_type'] = '成本价大于等于销售价';
                    $exception_data['error'] = 2;
                }
            }
        } else {
            $exception_status = true;
        }
        if ($exception_status) {
            VipProductException::create($exception_data);
            return $this->error($exception_data['error_type']);
        }

        return $this->success();
    }
}
