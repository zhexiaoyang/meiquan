<?php

namespace App\Http\Controllers;

use App\Imports\MedicineImport;
use App\Models\Medicine;
use App\Models\Shop;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MedicineController extends Controller
{
    public function shops(Request $request)
    {
        $shops = Shop::query()->select('id', 'shop_name')
            // ->where('')
            ->where('user_id', $request->user()->id)
            ->orderBy('id')->get();

        return $this->success($shops);
    }

    public function product(Request $request)
    {
        $user_id = $request->user()->id;
        if (!$shop_id = $request->get('shop_id')) {
            if ($shop = Shop::where('user_id', $user_id)->orderBy('id')->first()) {
                $shop_id = $shop->id;
            } else {
                return $this->error('暂无门店，请先创建门店');
            }
        } else {
            if (!Shop::where('id', $shop_id)->where('user_id', $user_id)->first()) {
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
        if ($id = $request->get('id')) {
            $query->where('id', $id);
        }
        if ($category_id = $request->get('category_id')) {
            $query->whereHas('category', function ($query) use ($category_id) {
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
        $res = Excel::import($import, $request->file('file'));
        \Log::info("12312", [$res]);
        return back()->with('success', '导入成功');
    }
}
