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

    public function index()
    {
        $users = User::query()->orderBy('id', 'desc')->paginate();
        if (!empty($users)) {
            foreach ($users as $user) {
                if ($user->hasRole('super_man')) {
                    $user->is_admin = 1;
                } else {
                    $user->is_admin = 0;
                }
            }
        }
        return $this->success($users);
    }

    public function show(User $user)
    {
        $user->load(['shops']);
        $user->user_shops = Arr::pluck($user->shops, 'id');
        unset($user->shops);
        $shops = Shop::select('id','shop_name')->where('user_id', 0)->orWhereIn('id', $user->user_shops)->get();
        return $this->success(compact(['user', 'shops']));
    }

    public function store(Request $request)
    {
//        $name = $request->get('username', '');
//        $email = $request->get('email', '');
        $phone = $request->get('phone', '');
        $password = $request->get('password', '654321');

        if ($phone && $password) {
            \DB::transaction(function () use ($phone, $password, $request) {
                $user = User::create([
                    'name' => $phone,
//                'email' => $email,
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

    public function update(User $user, Request $request)
    {

        \DB::transaction(function () use ($user, $request) {
            $user->shops()->update(['user_id' => 0]);
            if (!empty($request->user_shop)) {
                Shop::whereIn('id', $request->user_shop)->update(['user_id' => $user->id]);
            }
        });
        return $this->success([]);

    }

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
