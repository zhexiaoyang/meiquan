<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidRequestException;
use App\Exports\UserBalanceExport;
use App\Http\Controllers\Controller;
use App\Models\UserOperateBalance;
use Illuminate\Http\Request;
use App\Models\Deposit;
use App\Models\User;
use App\Models\UserReturn;
use App\Models\UserFrozenBalance;
use App\Models\UserMoneyBalance;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * 用户管理-统计数据
     */
    public function statistics()
    {
        $running_total = User::query()->sum("money");
        $shopping_total = User::query()->sum("frozen_money");
        $running_number = User::query()->where('money', '>', 0)->count();
        $shopping_number = User::query()->where('frozen_money', '>', 0)->count();

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
        $yesterday_running = Deposit::query()->where($where_y)->where("type", 1)->sum('amount');
        $yesterday_shopping = Deposit::query()->where($where_y)->where("type", 2)->sum('amount');

        $data = [
            'running_total' => $running_total,
            'shopping_total' => $shopping_total,
            'running_number' => $running_number,
            'shopping_number' => $shopping_number,
            'today_running' => $today_running,
            'today_shopping' => $today_shopping,
            'yesterday_running' => $yesterday_running,
            'yesterday_shopping' => $yesterday_shopping,
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

        $user->phone = $phone;
        $user->nickname = $nickname;
        $user->save();

        $user->shops()->sync($shops);

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
}
