<?php

namespace App\Http\Controllers;

use App\Imports\MedicineImport;
use App\Jobs\MedicineSyncJob;
use App\Models\Medicine;
use App\Models\MedicineSyncLog;
use App\Models\Shop;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MedicineController extends Controller
{
    public function shops(Request $request)
    {
        $query = Shop::select('id', 'shop_name')->where('second_category', 200001)->where('status', '>=', 0);
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            // \Log::info("没有全部门店权限");
            $query->whereIn('id', $request->user()->shops()->pluck('id'));
        }
        if ($name = $request->get('name')) {
            $shops = $query->where('shop_name', 'like', "%{$name}%")->orderBy('id')->limit(30)->get();
        } else {
            $shops = $query->orderBy('id')->limit(15)->get();
        }

        return $this->success($shops);
    }

    public function sync_log(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->success();
        }
        $logs = MedicineSyncLog::where('shop_id', $shop_id)->orderByDesc('id')->limit(2)->get();

        return $this->success($logs);
    }

    public function product(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!Shop::find($shop_id)) {
            return $this->error('门店错误.');
        }
        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($shop_id, $request->user()->shops()->pluck('id'))) {
                return $this->error('门店错误');
            }
        }

        $query = Medicine::where('shop_id', $shop_id);

        if ($name = $request->get('name')) {
            $query->where('name', 'like', "%{$name}%");
        }
        if ($upc = $request->get('upc')) {
            $query->where('upc', $upc);
        }
        if ($mt = $request->get('mt')) {
            $query->where('mt_status', $mt);
        }
        if ($ele = $request->get('ele')) {
            $query->where('ele_status', $ele);
        }
        if ($id = $request->get('id')) {
            $query->where('id', $id);
        }
        if ($category_id = $request->get('category_id')) {
            $query->whereHas('categories', function ($query) use ($category_id) {
                $query->where('category_id', $category_id);
            });
        }

        $data =$query->paginate($request->get('page_size', 10));

        return $this->page($data, [],'data');
    }

    public function import(Request $request, MedicineImport $import)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        $import->shop_id = $shop_id;
        Excel::import($import, $request->file('file'));
        return $this->success();
    }

    public function sync(Request $request)
    {
        if (!$shop_id = $request->get('shop_id')) {
            return $this->error('请选择门店');
        }
        if (!$platform = $request->get('platform')) {
            return $this->error('请选择同步平台');
        }
        if (!in_array($platform, [1,2])) {
            return $this->error('同步平台选择错误');
        }

        if (!$shop = Shop::find($shop_id)) {
            return $this->error('门店不存在，请核对');
        }

        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
            if (!in_array($shop_id, $request->user()->shops()->pluck('id'))) {
                return $this->error('门店不存在');
            }
            // if ($shop->user_id !== $request->user()->id) {
            //     return $this->error('门店不存在');
            // }
        }

        MedicineSyncJob::dispatch($shop, $platform);

        return $this->success();
    }
}
