<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $search_key = $request->get('search_key', '');
        $status = $request->get('status', null);

        $query = Permission::with(['actions' => function ($query) {
            $query->select('id', 'pid', 'name', 'title');
        }])->select('id', 'name', 'title', 'status')->where(['status' => 1, 'pid' => 0]);

        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
                $query->where('name', 'like', "%{$search_key}%")
                    ->orWhere('title', 'like', "%{$search_key}%");
            });
        }

        if (!is_null($status)) {
            $query->where('status', $status);
        }

        $permissions = $query->orderBy('id', 'desc')->paginate($page_size);

        return $this->success($permissions);
    }
}
