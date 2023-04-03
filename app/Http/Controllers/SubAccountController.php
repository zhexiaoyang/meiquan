<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubAccountController extends Controller
{
    public function index(Request $request)
    {
        $shops = Shop::select('id', 'shop_name', 'account_id', 'account_name', 'account_pwd')->where('user_id', $request->user()->id)->get();
        return $this->success($shops);
    }

    public function store(Request $request)
    {
        if (!$id = $request->get('id')) {
            return $this->error('参数错误');
        }
        if (!is_numeric($id)) {
            return $this->error('参数错误');
        }
        if (!$shop = Shop::find($id)) {
            return $this->error('门店不存在');
        }
        if ($shop->user_id != $request->user()->id) {
            return $this->error('门店不存在！');
        }
        if ($shop->account_id) {
            return $this->error('子账号已存在，不能重复创建');
        }

        try {
            DB::transaction(function () use ($shop) {
                // 创建账号
                $name = uniqid('mq');
                $password = randomPassword();
                $user = User::create([
                    'name' => $name,
                    'phone' => $name,
                    'account_shop_id' => $shop->id,
                    'nickname' => $shop->shop_name,
                    'password' => bcrypt($password),
                ]);
                // 赋予子账号角色
                $user->assignRole('store');
                if ($shop->auth === 10) {
                    $user->givePermissionTo('supplier');
                }
                // 更改门店子账号字段
                DB::table('shops')->where('id', $shop->id)->update(['account_id' => $user->id, 'account_name' => $name, 'account_pwd' => $password]);
                // 将门店分配到子账号下面
                DB::table('user_has_shops')->insert(['user_id' => $user->id, 'shop_id' => $shop->id]);
            });

        } catch (\Exception $exception) {
            \Log::error("创建子账号失败", [$exception->getMessage(), $exception->getLine(), $exception->getFile(), $shop->id]);
            return $this->error('创建失败，请稍后再试');
        }

        return $this->success();
    }

    public function destroy(Request $request)
    {
        if (!$id = $request->get('id')) {
            return $this->error('参数错误');
        }
        if (!is_numeric($id)) {
            return $this->error('参数错误');
        }
        if (!$shop = Shop::find($id)) {
            return $this->error('门店不存在');
        }
        if ($shop->user_id != $request->user()->id) {
            return $this->error('门店不存在！');
        }
        if (!$shop->account_id) {
            return $this->error('子账号不存在');
        }

        try {
            DB::transaction(function () use ($shop) {
                // 删除子账号用户
                DB::table('users')->where('id', $shop->account_id)->delete();
                // 删除子账号角色
                DB::table('model_has_roles')->where('model_id', $shop->account_id)->delete();
                DB::table('model_has_permissions')->where('model_id', $shop->account_id)->delete();
                // 更改门店子账号字段
                DB::table('shops')->where('id', $shop->id)->update(['account_id' => 0, 'account_pwd' => '', 'account_name' => '']);
                // 删除子账号门店所属
                DB::table('user_has_shops')->where('user_id', $shop->account_id)->delete();
            });

        } catch (\Exception $exception) {
            \Log::error("删除子账号失败", [$exception->getMessage(), $exception->getLine(), $exception->getFile(), $shop->id]);
            return $this->error('删除失败，请稍后再试');
        }

        return $this->success();
    }
}
