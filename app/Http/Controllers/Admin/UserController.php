<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidRequestException;
use App\Exports\UserBalanceExport;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\UserOperateBalance;
use App\Models\UserWebIm;
use Illuminate\Http\Request;
use App\Models\Deposit;
use App\Models\User;
use App\Models\UserReturn;
use App\Models\UserFrozenBalance;
use App\Models\UserMoneyBalance;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function im_index(Request $request)
    {
        $page_size = $request->get('page_size', 15);
        $name = $request->get('name', '');
        $phone = $request->get('phone', '');
        $nickname = $request->get('nickname', '');
        $search_key_shop = $request->get('shop', '');

        $query = User::with(['shops', 'im'])->select("id","name","phone","nickname","created_at", "status");

        if ($name) {
            $query->where('name', 'like', "%{$name}%");
        }

        if ($phone) {
            $query->where('phone', 'like', "%{$phone}%");
        }

        if ($nickname) {
            $query->where('nickname', 'like', "%{$nickname}%");
        }

        if ($search_key_shop) {
            $query->whereHas("my_shops", function ($query) use ($search_key_shop) {
                $query->where('shop_name', 'like', "%{$search_key_shop}%");
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate($page_size);

        if (!empty($users)) {
            foreach ($users as $user) {
                $shops = [];
                if (!empty($user->my_shops)) {
                    foreach ($user->my_shops as $shop) {
                        $shops[] = [
                            "id" => $shop->id,
                            "mt_shop_id" => $shop->mt_shop_id,
                            "shop_name" => $shop->shop_name,
                            "contact_name" => $shop->contact_name,
                            "contact_phone" => $shop->contact_phone,
                            "shop_address" => $shop->shop_address,
                        ];
                    }
                }
                unset($user->my_shops);
                $shop_ids = [];
                if (!empty($user->shops)) {
                    foreach ($user->shops as $v) {
                        $shop_ids[] = (string) $v->id;
                    }
                }
                unset($user->shops);
                $user->my_shops = $shops;
                $user->shops = $shop_ids;

                // 权限
                // if (!empty($user->im->auth)) {
                //     if ($user->im->)
                //     $user->im->auth = explode(',', $user->im->auth);
                // }
            }
        }


        return $this->page($users, false, 'data');
    }

    public function im_update(Request $request)
    {
        if (!$user_id = $request->get('user_id')) {
            return $this->error('用户不存在');
        }
        if (!$auth = $request->get('auth')) {
            return $this->error('权限不能为空');
        }
        if (!$description = $request->get('description')) {
            return $this->error('描述不能为空');
        }
        if (!in_array($auth, [1, 21])) {
            return $this->error('权限不存在');
        }
        if (!$user = User::find($user_id)) {
            return $this->error('用户不存在!');
        }

        if ($im = UserWebIm::where('user_id', $user_id)->first()) {
            $im->auth = $auth;
            $im->description = $description;
            $im->update([
                'auth' => $auth,
                'description' => $description,
            ]);
        } else {
            UserWebIm::create([
                'user_id' => $user_id,
                'auth' => $auth,
                'description' => $description,
            ]);
        }

        return $this->success();
    }

    public function im_delete(Request $request)
    {
        if (!$user_id = $request->get('user_id')) {
            return $this->error('用户不存在');
        }
        if (!$user = User::find($user_id)) {
            return $this->error('用户不存在!');
        }
        UserWebIm::where('user_id', $user_id)->delete();

        return $this->success();
    }

    /**
     * 用户管理-统计数据
     */
    public function statistics()
    {
        $running_total = User::query()->sum("money");
        $shopping_total = User::query()->sum("frozen_money");
        $operate_total = User::query()->sum("operate_money");
        $running_number = User::query()->where('money', '>', 0)->count();
        $shopping_number = User::query()->where('frozen_money', '>', 0)->count();
        $operate_number = User::query()->where('operate_money', '>', 0)->count();

        $where_t = [
            ['status', 1],
            ['paid_at', '>=', date("Y-m-d")],
            ['paid_at', '<', date("Y-m-d",strtotime("+1 day"))],
        ];
        $where_y = [
            ['status', 1],
            ['paid_at', '>=', date("Y-m-d",strtotime("-1 day"))],
            ['paid_at', '<', date("Y-m-d")],
        ];
        $today_running = Deposit::query()->where($where_t)->where("type", 1)->sum('amount');
        $today_shopping = Deposit::query()->where($where_t)->where("type", 2)->sum('amount');
        $today_operate = Deposit::query()->where($where_t)->where("type", 3)->sum('amount');
        $yesterday_running = Deposit::query()->where($where_y)->where("type", 1)->sum('amount');
        $yesterday_shopping = Deposit::query()->where($where_y)->where("type", 2)->sum('amount');
        $yesterday_operate = Deposit::query()->where($where_y)->where("type", 3)->sum('amount');

        $data = [
            'running_total' => $running_total,
            'shopping_total' => $shopping_total,
            'operate_total' => $operate_total,
            'running_number' => $running_number,
            'shopping_number' => $shopping_number,
            'operate_number' => $operate_number,
            'today_running' => $today_running,
            'today_shopping' => $today_shopping,
            'today_operate' => $today_operate,
            'yesterday_running' => $yesterday_running,
            'yesterday_shopping' => $yesterday_shopping,
            'yesterday_operate' => $yesterday_operate,
        ];

        return $this->success($data);
    }

    /**
     * 用户管理-用户明细
     */
    public function balance(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $type = $request->get('type', 1);
        $start_date = $request->get('start_date', '');
        $end_date = $request->get('end_date', '');

        if (!$user_id = $request->get("user_id")) {
            return $this->error("用户ID不能为空");
        }

        if ($type == 1) {
            $query = UserMoneyBalance::query();
        } elseif ($type == 2) {
            $query = UserFrozenBalance::query();
        } elseif ($type == 3) {
            $query = UserOperateBalance::query();
        }

        if ($start_date) {
            $query->where("created_at", ">=", $start_date);
        }

        if ($end_date) {
            $query->where("created_at", "<", date("Y-m-d", strtotime($end_date) + 86400));
        }

        $data = $query->where("user_id", $user_id)->orderByDesc("id")->paginate($page_size);


        return $this->page($data);
    }

    /**
     * 用户管理-用户导出
     */
    public function balanceExport(Request $request, UserBalanceExport $balanceExport)
    {
        return $balanceExport->withRequest($request);
    }

    /**
     * 用户管理-添加用户
     */
    public function store(Request $request)
    {
        $name = $request->get('name', '');
        $phone = $request->get('phone', '');
        $nickname = $request->get('nickname', '');
        $password = $request->get('password', '654321');
        $role = $request->get('role', '');

        if (!$name) {
            return $this->error("用户名不能为空");
        }

        if (!$password) {
            return $this->error("密码不能为空");
        }

        if (($role === 'shop') && !$phone) {
            if ($phone && (strlen($phone) !== 11) || (substr($phone, 0, 1) != 1)) {
                return $this->error("手机号格式不正确");
            }
            return $this->error("商户必须填写手机号");
        }

        if (strlen($password) < 6) {
            return $this->error("密码长度不能小于6");
        }

        if (!in_array($role, ['shop', 'city_manager'])) {
            return $this->error("角色错误");
        }

        if (User::query()->where('name', $name)->first()) {
            return $this->error("用户名已存在");
        }

        if ($phone && User::query()->where('phone', $phone)->first()) {
            return $this->error("手机号已存在");
        }

        \DB::transaction(function () use ($nickname, $name, $phone, $password, $request, $role) {
            $data = [
                'name' => $name,
                'phone' => $phone,
                'nickname' => $nickname,
                'password' => bcrypt($password),
            ];
            $user = User::create($data);
            $user->assignRole($role);

            if ($user->id && $role === 'city_manager') {
                $user->shops()->sync($request->user_shop);
            }

            Shop::whereIn('id', $request->user_shop)->update(['manager_id' => $user->id]);
        });
        return $this->success();

        // return $this->error("创建失败");
    }

    /**
     * 用户管理-禁用用户
     */
    public function disable(Request $request)
    {
        $user_id = $request->get("id", 0);
        $status = $request->get("status", 1);

        if (!$user = User::find($user_id)) {
            return $this->error("用户不存在");
        }

        $user->update(['status' => $status === 1 ? 2 : 1]);

        if ($status === 1) {
            // 禁用用户删除登录信息
            DB::table("oauth_access_tokens")->where("user_id", $user_id)->delete();
        }

        return $this->success();
    }

    /**
     * 用户管理-设置运营经理
     */
    public function operate_update(Request $request)
    {
        $user_id = $request->get("id", 0);

        if (!$user = User::find($user_id)) {
            return $this->error("用户不存在");
        }

        if ($user->is_operate == 1) {
            if (Shop::where('operate_id', $user->id)->count() > 0) {
                return $this->error("该运营经理有运营门店不能取消角色");
            }
            $user->update(['is_operate' => 0]);
        } else {
            $user->update(['is_operate' => 1]);
        }


        return $this->success();
    }

    /**
     * 用户管理-设置内勤经理
     */
    public function internal_update(Request $request)
    {
        $user_id = $request->get("id", 0);

        if (!$user = User::find($user_id)) {
            return $this->error("用户不存在");
        }

        if ($user->is_internal == 1) {
            if (Shop::where('internal_id', $user->id)->count() > 0) {
                return $this->error("该内勤经理有VIP门店不能取消角色");
            }
            $user->update(['is_internal' => 0]);
        } else {
            $user->update(['is_internal' => 1]);
        }

        return $this->success();
    }

    public function operate_index(Request $request)
    {
        $users = User::query()->select('id','name','nickname','phone')->where('is_operate',1)->get();

        return $this->success($users);
    }

    public function internal_index(Request $request)
    {
        $users = User::query()->select('id','name','nickname','phone')->where('is_internal',1)->get();

        return $this->success($users);
    }

    /**
     * 用户管理-更新用户
     */
    public function update(Request $request)
    {
        $user_id = $request->get("user_id", 0);
        $phone = $request->get("phone", '');
        $nickname = $request->get("nickname", '');
        $shops = $request->get("shops", []);

        if (!$user = User::find($user_id)) {
            return $this->error("用户不存在");
        }

        if ($phone && User::where("phone", $phone)->first()) {
            return $this->error("手机号已存在");
        }

        if (!is_array($shops)) {
            return $this->error("参数错误");
        }

        if ($user->hasRole('city_manager')) {
            $manager_ids = User::whereHas("roles", function ($query) {
                $query->where('name', 'city_manager');
            })->orderByDesc('id')->pluck("id")->toArray();
            $record = DB::table('user_has_shops')->whereIn('user_id', $manager_ids)
                ->whereIn('shop_id', $shops)->where('user_id', '<>', $user->id)->first();
            if ($record) {
                $_shop = Shop::find($record->shop_id);
                $_user = User::find($record->user_id);
                $_name = $_user->nickname ?: $_user->name;
                return $this->error("门店「{$_shop->shop_name}」已在经理「{$_name}」账号下，请核对再试", 422);
            }
        }

        $user->phone = $phone;
        $user->nickname = $nickname;
        $user->save();

        $user->shops()->sync($shops);
        Shop::where('manager_id', $user->id)->update(['manager_id' => 0]);
        Shop::whereIn('id', $shops)->update(['manager_id' => $user->id]);

        return $this->success();
    }

    /**
     * 用户管理-城市经理返佣
     */
    public function returnStore(Request $request)
    {
        $user_id = $request->get("id", 0);

        if (!$user = User::find($user_id)) {
            return $this->error("用户不存在");
        }

        $data = [
            'user_id' => $user_id,
            'running_type' => $request->get('running_type', 1),
            'running_value1' => $request->get('running_value1', 0),
            'running_value2' => $request->get('running_value2', 0),
            'shop_type' => $request->get('shop_type', 1),
            'shop_value1' => $request->get('shop_value1', 0),
            'shop_value2' => $request->get('shop_value2', 0),
        ];

        if ($user_return = UserReturn::where("user_id", $user_id)->first()) {
            $user_return->update($data);
        } else {
            UserReturn::create($data);
        }

        return $this->success();
    }

    /**
     * 用户管理-城市经理返佣-显示
     */
    public function returnShow(Request $request)
    {
        $user_id = $request->get("id", 0);
        $user_return = UserReturn::where("user_id", $user_id)->first();

        return $this->success($user_return ?: []);
    }

    /**
     * 清空用户跑腿余额
     * @data 2021/11/12 2:34 下午
     */
    public function money_clear(Request $request)
    {
        $user_id = $request->get("id", 0);
        $type = $request->get("type", 0);

        if (!in_array($type, [1, 2, 3])) {
            return $this->error('请选择清空的账户');
        }

        if (!$description = $request->get('description')) {
            return $this->error('原因不能为空');
        }

        if (!$user = User::find($user_id)) {
            return $this->error("用户不存在");
        }

        // 跑腿余额
        if ($type === 1) {
            if ($user->money == 0) {
                return $this->alert('用户余额已经是 0 了！');
            }

            if ($user->money < 0) {
                return $this->error('用户余额小于 0，不能清空！');
            }

            try {
                DB::transaction(function () use ($user, $description) {
                    if (!DB::table('users')->where('id', $user->id)->where('money', $user->money)->update(['money' => 0])) {
                        throw new InvalidRequestException('操作失败，请稍后再试！', 422);
                    }
                    $log = [
                        'user_id' => $user->id,
                        'money' => $user->money,
                        'type' => 2,
                        'before_money' => $user->money,
                        'after_money' => 0,
                        'description' => $description
                    ];
                    UserMoneyBalance::create($log);
                });
            } catch (\Exception $e) {
                return $this->alert('操作失败，请稍后再试！');
            }
        }

        // 商城余额
        if ($type === 2) {
            if ($user->frozen_money == 0) {
                return $this->alert('用户余额已经是 0 了！');
            }

            if ($user->frozen_money < 0) {
                return $this->error('用户余额小于 0，不能清空！');
            }

            try {
                DB::transaction(function () use ($user, $description) {
                    if (!DB::table('users')->where('id', $user->id)->where('frozen_money', $user->frozen_money)->update(['frozen_money' => 0])) {
                        throw new InvalidRequestException('操作失败，请稍后再试！', 422);
                    }
                    $log = [
                        'user_id' => $user->id,
                        'money' => $user->frozen_money,
                        'type' => 2,
                        'before_money' => $user->frozen_money,
                        'after_money' => 0,
                        'description' => $description
                    ];
                    UserFrozenBalance::create($log);
                });
            } catch (\Exception $e) {
                return $this->alert('操作失败，请稍后再试！');
            }
        }

        // 运营余额
        if ($type === 3) {
            if ($user->operate_money == 0) {
                return $this->alert('用户余额已经是 0 了！');
            }

            if ($user->operate_money < 0) {
                return $this->error('用户余额小于 0，不能清空！');
            }

            try {
                DB::transaction(function () use ($user, $description) {
                    if (!DB::table('users')->where('id', $user->id)->where('operate_money', $user->operate_money)->update(['operate_money' => 0])) {
                        throw new InvalidRequestException('操作失败，请稍后再试！', 422);
                    }
                    $log = [
                        'user_id' => $user->id,
                        'money' => $user->operate_money,
                        'type' => 2,
                        'before_money' => $user->operate_money,
                        'after_money' => 0,
                        'description' => $description
                    ];
                    UserOperateBalance::create($log);
                });
            } catch (\Exception $e) {
                return $this->alert('操作失败，请稍后再试！');
            }
        }

        return $this->success('操作成功');
    }

    public function resetPassword(Request $request)
    {
        if (!$request->user()->hasRole('super_admin')) {
            return $this->error('请求错误');
        }

        if (!$user = User::find($request->get('id'))) {
            return $this->error('用户不存在');
        }

        $str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < 6; $i++) {
            $password .= $str[rand(0, 61)];
        }

        $user->password = bcrypt($password);
        $user->save();

        return $this->success(['password' => $password]);
    }
}
