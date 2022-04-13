<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * 权限列表
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $search_key = $request->get('search_key', '');
        $status = $request->get('status', null);

        $query = Permission::with(['actions' => function ($query) {
            $query->select('id', 'pid', 'name', 'title');
        }])->select('id', 'name', 'title', 'menu_title', 'status')->where(['pid' => 0]);

        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
                $query->where('name', 'like', "%{$search_key}%")
                    ->orWhere('title', 'like', "%{$search_key}%");
            });
        }

        if (!is_null($status)) {
            $query->where('status', $status);
        }

        $permissions = $query->where('is_display', 1)->orderBy('sort')->paginate($page_size);

        return $this->success($permissions);
    }

    /**
     * 添加权限
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'title' => 'required|string',
        ],[
            'name.required' => '唯一标识码 不能为空',
            'title.required' => '权限名称 不能为空'
        ]);

        $actions = $request->get('actions', []);
        $pid = $request->get('pid', 0);

        $permission = Permission::query()->create([
            'pid' => $pid,
            'name' => $request->get('name', ''),
            'title' => $request->get('title', ''),
            'guard_name' => 'api',
        ]);

        if (!empty($actions)) {
            foreach ($actions as $action) {
                $data = [
                    'pid' => $permission->id,
                    'name' => $permission->name . '_' . $action,
                    'title' => $action,
                    'guard_name' => 'api',
                ];
                Permission::query()->create($data);
            }
        }

        return $this->success();
    }

    /**
     * 更新权限
     * @param Request $request
     * @param Permission $permission
     * @return mixed
     */
    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => 'required|string',
            'title' => 'required|string',
        ],[
            'name.required' => '唯一标识码 不能为空',
            'title.required' => '权限名称 不能为空'
        ]);

        $actions = $request->get('actions', []);
        $new_names = [];

        if (!empty($actions)) {
            foreach ($actions as $action) {
                $new_names[] = $permission->name . '_' . $action;
            }
        }

        $permission->name = $request->get('name');
        $permission->title = $request->get('title');
        $permission->save();

        $old_names = Permission::query()->where('pid', $permission->id)->pluck('name')->toArray();


        foreach ($old_names as $old_name) {
            if (!in_array($old_name, $new_names)) {
                Permission::query()->where('name', $old_name)->delete();
            }
        }

        foreach ($actions as $action) {
            if (!in_array($permission->name . '_' . $action, $old_names)) {
                $data = [
                    'pid' => $permission->id,
                    'name' => $permission->name . '_' . $action,
                    'title' => $action,
                    'guard_name' => 'api',
                ];
                Permission::query()->create($data);
            }
        }

        return $this->success();
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();
        return $this->success();
    }

    /**
     * 所有权限-树形返回
     * @return mixed
     */
    public function all()
    {

        $permissions = Permission::with(['actions' => function ($query) {
            $query->select('id', 'pid', 'title');
        }])->select('id', 'title')->where(['status' => 1, 'pid' => 0])
            ->orderBy('id', 'desc')->get();

        if (!empty($permissions)) {
            foreach ($permissions as $permission) {
                $permission->selected = [];
            }
        }

        return $this->success($permissions);
    }
}
