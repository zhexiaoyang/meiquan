<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * 角色列表
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $search_key = $request->get('search_key', '');

        $query = Role::query()->select('id', 'name', 'title');

        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
                $query->where('name', 'like', "%{$search_key}%")
                    ->orWhere('title', 'like', "%{$search_key}%");
            });
        }

        $roles = $query->orderBy('id', 'desc')->paginate($page_size);

        return $this->success($roles);
    }

    /**
     * 添加用户
     * @param Request $request
     * @param Role $role
     * @return mixed
     */
    public function store(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string',
            'title' => 'required|string',
        ],[
            'name.required' => '唯一标识码 不能为空',
            'title.required' => '权限名称 不能为空'
        ]);

        $permissions = $request->get('permissions', []);

        $role->name = $request->get('name');
        $role->title = $request->get('title');
        $role->guard_name = 'api';
        $role->save();

        $role->syncPermissions($permissions);

        return $this->success();
    }

    /**
     * 更新角色
     * @param Request $request
     * @param Role $role
     * @return mixed
     */
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string',
            'title' => 'required|string',
        ],[
            'name.required' => '唯一标识码 不能为空',
            'title.required' => '权限名称 不能为空'
        ]);

        $permissions = $request->get('permissions', []);

        $role->name = $request->get('name');
        $role->title = $request->get('title');
        $role->save();

        $role->syncPermissions($permissions);

        return $this->success();
    }

    /**
     * 角色详情
     * @param Role $role
     * @return mixed
     */
    public function show(Role $role)
    {
        $role->load('permissions');

        $user_permissions = $role->permissions->pluck('id')->toArray();

        unset($role->permissions);

        $permissions = Permission::with(['actions' => function ($query) {
            $query->select('id', 'pid', 'title');
        }])->select('id', 'title')->where(['status' => 1, 'pid' => 0])
            ->orderBy('id', 'desc')->get();

        if (!empty($permissions)) {
            foreach ($permissions as $permission) {
                $selected = [];
                if (in_array($permission->id, $user_permissions)) {
                    // $selected[] = $permission->id;
                    if (!empty($permission->actions)) {
                        foreach ($permission->actions as $action) {
                            if (in_array($action->id, $user_permissions)) {
                                $selected[] = $action->id;
                            }
                        }
                    }
                }
                $permission->selected = $selected;
            }
        }

        $role->permissions = $permissions;

        return $this->success($role);
    }

    /**
     * 全部角色
     * @return mixed
     */
    public function all()
    {
        $roles = Role::query()->select('id', 'title')->get();

        return $this->success($roles);
    }
}
