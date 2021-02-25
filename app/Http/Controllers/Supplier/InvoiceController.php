<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\SupplierInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);

        $user = Auth::user();

        $list = SupplierInvoice::where("user_id", $user->id)->orderBy("id", "desc")->paginate($page_size);

        return $this->page($list);
    }

    public function store(Request $request)
    {

    }
}
