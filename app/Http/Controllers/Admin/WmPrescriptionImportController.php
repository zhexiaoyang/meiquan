<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\Admin\PrescriptionImport;
use App\Models\WmPrescriptionImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class WmPrescriptionImportController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);

        $data = WmPrescriptionImport::with(['user' => function ($query) {
            $query->select('nickname', 'phone', 'id');
        }])->orderByDesc('id')->paginate($page_size);

        return $this->page($data, [], 'data');
    }

    public function store(Request $request, PrescriptionImport $import)
    {
        Excel::import($import, $request->file('file'));
        return $this->success();
    }
}
