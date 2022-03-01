<?php

namespace App\Http\Controllers;

use App\Exports\UserExport;
use App\Models\Deposit;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserFrozenBalance;
use App\Models\UserMoneyBalance;
use App\Models\UserOperateBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Yansongda\Pay\Pay;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * 用户列表
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 15);
        $date = $request->get("end", date("Y-m-d"));

        $role = $request->get('role');
        $name = $request->get('name', '');
        $status = $request->get('status');
        $phone = $request->get('phone', '');
        $nickname = $request->get('nickname', '');
        $search_key_shop = $request->get('shop', '');

        $query = User::with(['roles', 'my_shops', 'shops'])
            ->select("id","name","phone","nickname","money","frozen_money","operate_money","created_at",
                "is_operate","is_chain","chain_name","status");

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

        if (in_array($role, [1,2,3,4])) {
            $query->whereHas('roles', function ($query) use ($role) {
                switch ($role) {
                    case 1:
                        $query->where('name', 'super_man');
                        break;
                    case 2:
                        $query->where('name', 'city_manager');
                        break;
                    case 3:
                        $query->where('name', 'finance');
                        break;
                    case 4:
                        $query->where('name', 'shop');
                        break;
                }
            });
        }

        if (in_array($status, [1, 2])) {
            $query->where('status', $status);
        }

        $users = $query->orderBy('id', 'desc')->paginate($page_size);


        if (!empty($users)) {
            foreach ($users as $user) {
                if ($user->hasRole('super_man')) {
                    $user->is_admin = 1;
                } else {
                    $user->is_admin = 0;
                }
                $user->role_name = $user->roles[0]->title ?? '';
                unset($user->roles);

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
            }
        }

        if (!empty($users)) {
            $date = date("Y-m-d", strtotime($date) + 86400);
            foreach ($users as $user) {
                $frozen = UserFrozenBalance::query()
                    ->where("user_id", $user->id)
                    ->where("created_at", "<", $date)->orderByDesc("id")->first();
                $money = UserMoneyBalance::query()
                    ->where("user_id", $user->id)
                    ->where("created_at", "<", $date)->orderByDesc("id")->first();
                $operate = UserOperateBalance::query()
                    ->where("user_id", $user->id)
                    ->where("created_at", "<", $date)->orderByDesc("id")->first();
                if ($money) {
                    $user->after_money = $money->after_money;
                } else {
                    $user->after_money = $user->money;
                }
                if ($frozen) {
                    $user->after_frozen_money = $frozen->after_money;
                } else {
                    $user->after_frozen_money = $user->frozen_money;
                }
                if ($operate) {
                    $user->after_operate_money = $operate->after_money;
                } else {
                    $user->after_operate_money = $user->operate_money;
                }
            }
        }

        return $this->page($users);
    }

    public function chain(Request $request)
    {
        if (!$user = User::query()->find(intval($request->get("id", 0)))) {
            return $this->error("用户不存在");
        }

        if ($user->is_chain === 1) {
            $user->is_chain = 0;
            $user->chain_name = "";
            $user->save();
        } else {
            if (!$chain_name = $request->get("chain_name", "")) {
                return $this->error("连锁店名称错误");
            }
            $user->is_chain = 1;
            $user->chain_name = $chain_name;
            $user->save();
        }

        return $this->success();
    }

    /**
     * 用户详情
     * @param User $user
     * @return mixed
     */
    public function show(User $user)
    {
        $user->load(['shops', 'roles']);
        $user->user_shops = Arr::pluck($user->shops, 'id');

        $user->role_id = $user->roles[0]->id ?? 0;

        unset($user->shops);
        unset($user->roles);
        $shops = Shop::select('id','shop_name')->where('user_id', 0)->orWhereIn('id', $user->user_shops)->get();
        if (!empty($shops)) {
            foreach ($shops as $v) {
                $v->key = (string) $v->id;
                $v->title = (string) $v->shop_name;
            }
        }
        return $this->success(compact(['user', 'shops']));
    }

    /**
     * 添加用户
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $phone = $request->get('phone', '');
        $password = $request->get('password', '654321');

        if ($phone && $password) {
            \DB::transaction(function () use ($phone, $password, $request) {
                $user = User::create([
                    'name' => $phone,
                    'phone' => $phone,
                    'password' => bcrypt($password),
                ]);

                if ($user->id) {
                    $user->shops()->update(['user_id' => 0]);
                    if (!empty($request->user_shop)) {
                        Shop::whereIn('id', $request->user_shop)->update(['user_id' => $user->id]);
                    }
                }
            });
            return $this->success([]);
        }

        return $this->error("创建失败");
    }

    /**
     * 更新用户
     * @param User $user
     * @param Request $request
     * @return mixed
     */
    public function update(User $user, Request $request)
    {
        if ($phone = $request->get('phone')) {
            $user->phone = $phone;
            $user->save();
        }

        $user->load(['roles']);

        $user_role_id = $user->roles[0]->id ?? 0;
        $role_id = $request->get('role_id');

        if ($role_id && ($user_role_id != $role_id)) {
            $user->syncRoles([$role_id]);
        }

        \DB::transaction(function () use ($user, $request) {
            $user->shops()->update(['user_id' => 0]);
            if (!empty($request->user_shop)) {
                Shop::whereIn('id', $request->user_shop)->update(['user_id' => $user->id]);
            }
        });
        return $this->success([]);

    }

    /**
     * 手动充值
     * @param Request $request
     * @return mixed
     */
    public function recharge(Request $request)
    {
        $user_id = $request->get('user_id', 0);
        $amount = $request->get('amount', 0);
        if (!$user_id || !$amount) {
            return $this->error('非法参数');
        }
        if ( !$user = User::find($user_id) ) {
            return $this->error('用户不存在');
        }

        \DB::transaction(function () use ($user, $amount) {
            $deposit = new Deposit([
                'pay_method' => 3,
                'status' => 1,
                'paid_at' => date("Y-m-d H:i:s"),
                'amount' => $amount,
            ]);
            $deposit->user()->associate($user);
            $deposit->save();
            $user->money += $amount;
            $user->save();
        });

        return $this->success([]);
    }

    /**
     * 充值列表
     * @param Request $request
     * @return mixed
     */
    public function rechargeList(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $search_key = $request->get('search_key', '');
        $query = Deposit::with(['user' => function($query) {
            $query->select('id','phone');
        }])->where('status', 1);
        if ($search_key) {
            $query->where(function($query) use ($search_key) {
                $query->where('no', 'like', "%{$search_key}%")->orWhereHas('user', function ($query) use($search_key) {
                    $query->where('phone', 'like', "%{$search_key}%");
                });
            });
        }
        $recharges = $query->orderBy('id', 'desc')->paginate($page_size);
        return $this->success($recharges);
    }

    public function export(Request $request, UserExport $userExport)
    {
        return $userExport->withRequest($request);
    }

}
