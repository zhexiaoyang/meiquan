<?php

namespace App\Http\Controllers;

use App\Exports\WmMedicineExport;
use App\Exports\WmMedicineLogExport;
use App\Models\MedicineSyncLog;
use Illuminate\Http\Request;

class MedicineSyncLogController extends Controller
{
    public function index(Request $request)
    {
        $query = MedicineSyncLog::query();

        if (!$request->user()->hasPermissionTo('currency_shop_all')) {
                // \Log::info("没有全部门店权限");
                // if (!$request->user()->hasRole('super_man')) {
            $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));
        }

        $data = $query->orderByDesc('id')->paginate($request->get('page_size', 10));

        return $this->success($data);
    }

    public function export(Request $request, WmMedicineLogExport $export)
    {
        if (!$id = $request->get('id')) {
            return $this->error('请选择要导出的日志');
        }
        return $export->withRequest($id);
    }
}
