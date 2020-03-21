<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\Shop;
use App\Models\User;
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

        $search_key = $request->get('search_key', '');

        $query = User::with(['roles']);

        if ($search_key) {
            $query->where(function ($query) use ($search_key) {
                $query->where('name', 'like', "%{$search_key}%")
                    ->orWhere('phone', 'like', "%{$search_key}%");
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate();

        if (!empty($users)) {
            foreach ($users as $user) {
                if ($user->hasRole('super_man')) {
                    $user->is_admin = 1;
                } else {
                    $user->is_admin = 0;
                }
                $user->role_name = $user->roles[0]->title ?? '';
                unset($user->roles);
            }
        }
        return $this->success($users);
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

}
