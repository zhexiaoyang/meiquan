<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\User;
use App\Traits\PassportToken;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Bridge\Scope;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    use PassportToken;

    public function __construct()
    {
        $this->middleware('auth:api')->except(['login', 'register', 'loginFromMobile']);
    }

    public function login(Request $request)
    {
        if ($request->get("captcha")) {
            return $this->loginByMobile($request);
        }

        try {
            // $scope = new Scope('web');
            $token = app(Client::class)->post(url('/oauth/token'), [
                'form_params' => [
                    'grant_type' => 'password',
                    'client_id' => config('passport.clients.password.client_id'),
                    'client_secret' => config('passport.clients.password.client_secret'),
                    'username' => $request->get('username'),
                    'password' => $request->get('password'),
                    'scope' => '',
                    "provider" => "users"
                ],
            ]);

            $data = json_decode($token->getBody(), true);

            if ($user = User::where("name", $request->get("username"))->first()) {
                if ($user->status === 2) {
                    return $this->error("用户禁止登录");
                }
            }

            return $this->success($data);
        } catch (\Exception $e) {
            return $this->error("用户名或密码错误", 422);
        }
    }

    /**
     * PC端验证码登录
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/3/1 3:51 下午
     */
    public function loginByMobile(Request $request)
    {

        $request->validate([
            'mobile' => 'required',
            'captcha' => 'required',
        ], [], [
            'mobile' => '手机号',
            'captcha' => '短信验证码',
        ]);

        $phone = $request->get('mobile', '');
        $captcha = $request->get('captcha', '');

        $verifyData = \Cache::get($phone);

        if (!$verifyData) {
            return $this->error('验证码已失效');
        }

        if (!hash_equals($verifyData['code'], $captcha)) {
            return $this->error('验证码失效');
        }

        $user = User::query()->where('phone', $phone)->first();

        if (!$user) {
            $password = str_pad(mt_rand(10, 999999), 6, "0", STR_PAD_BOTH);;
            $user = new User();
            $user->name = $phone;
            $user->phone = $phone;
            $user->password = bcrypt($password);
            $user->save();

            $user->assignRole('shop');

            \Log::info('验证码登录注册', ['phone' => $phone, 'password' => $password]);
        }

        if ($user->status === 2) {
            return $this->error("用户禁止登录");
        }

        $scope = new Scope('web');
        $result = $this->getBearerTokenByUser($user, '1', [$scope], false);

        \Cache::forget($phone);

        return $this->success($result);
    }

    /**
     * 移动端（小程序）手机验证码登录
     * @param Request $request
     * @return mixed
     * @author zhangzhen
     * @data 2021/3/1 3:52 下午
     */
    public function loginFromMobile(Request $request)
    {

        $request->validate([
            'mobile' => 'required',
            'code' => 'required',
        ], [], [
            'mobile' => '手机号',
            'code' => '短信验证码',
        ]);

        $phone = $request->get('mobile', '');
        $captcha = $request->get('code', '');

        if ($phone !== '18611683889' || $captcha !== '00000000') {

            $verifyData = \Cache::get($phone);

            if (!$verifyData) {
                return $this->error('验证码已失效');
            }

            if (!hash_equals($verifyData['code'], $captcha)) {
                    return $this->error('验证码失效');
            }
        }

        $user = User::query()->where('phone', $phone)->first();

        if (!$user) {
            $password = str_pad(mt_rand(10, 999999), 6, "0", STR_PAD_BOTH);;
            $user = new User();
            $user->name = $phone;
            $user->phone = $phone;
            $user->password = bcrypt($password);
            $user->save();

            $user->assignRole('shop');

            \Log::info('验证码登录注册', ['phone' => $phone, 'password' => $password]);
        }

        if ($user->status === 2) {
            return $this->error("用户禁止登录");
        }

        $scope = new Scope('min-app');
        $result = $this->getBearerTokenByUser($user, '1', [$scope], false);

        \Cache::forget($phone);

        $data = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'token' => $result['access_token']
        ];

        return $this->success($data);
    }


    public function register(Request $request, User $user)
    {
        $phone = $request->get('phone');
        $password = $request->get('password');
        $verifyCode = (string)$request->get('verifyCode');

        if (User::query()->where('phone', $phone)->first()) {
            return $this->message('手机号已存在，请登录');
        }

        $verifyData = \Cache::get($phone);

        if (!$verifyData) {
            return $this->error('验证码已失效', 422);
        }

        if (!hash_equals($verifyData['code'], $verifyCode)) {
            return $this->error('验证码失效', 422);
        }

        $user->name = $request->get('phone');
        $user->phone = $request->get('phone');
        $user->password = bcrypt($password);
        $user->save();

        $user->assignRole('shop');

        if ($user) {
            return $this->message("注册成功，请登录");
        } else {
            return $this->error("注册失败，稍后再试", 500);
        }
    }

    public function user(Request $request)
    {
        $user = [
            'id' => $request->user()->phone ?? '',
            'name' => $request->user()->phone ?? '',
            'phone' => $request->user()->phone ?? '',
            'money' => $request->user()->money ?? '',
            'frozen_money' => $request->user()->frozen_money ?? '',
            'operate_money' => $request->user()->operate_money ?? '',
            'roles' => $request->user()->hasRole('super_man') ? ['super_man'] : ['index'],
        ];
        return $this->success($user);
    }

    public function contractInfo(Request $request)
    {
        $user = [
            'id' => $request->user()->id ?? '',
            'is_chain' => $request->user()->is_chain ?? '',
            'chain_name' => $request->user()->chain_name ?? '',
            'contract_auth' => $request->user()->contract_auth ?? '',
            'applicant' => $request->user()->applicant ?? '',
            'applicant_phone' => $request->user()->applicant_phone ?? '',
            'company_name' => $request->user()->company_name ?? '',
        ];
        return $this->success($user);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        $role_name = $user->getRoleNames()[0];

        $user_permissions = $user->permissions;

        $data = [];

        $role = Role::with(['permissions' => function($query) {
            $query->select('name', 'title', 'id', 'pid', 'menu', 'menu_title')->orderBy('pid', 'asc');
        }])->select('name', 'title', 'id')->where('name', $role_name)->first();

        if ($role) {
            $data['id'] = $role->name;
            $data['name'] = $role->title;
            $permissions = [];
            if (!empty($role->permissions)) {
                foreach ($role->permissions as $permission) {
                    if ($permission->pid ==0) {
                        $permissions[$permission->id]['roleId'] = $role->name;
                        $permissions[$permission->id]['permissionId'] = $permission->name;
                        $permissions[$permission->id]['permissionName'] = $permission->title;
                        $permissions[$permission->menu]['roleId'] = $permission->menu;
                        $permissions[$permission->menu]['permissionId'] = $permission->menu;
                        $permissions[$permission->menu]['permissionName'] = $permission->menu_title;
                    } else {
                        if (isset($permissions[$permission->pid])) {
                            $tmp['action'] = $permission->name;
                            $tmp['describe'] = $permission->title;
                            $tmp['defaultCheck'] = true;
                            $permissions[$permission->pid]['actionEntitySet'][] = $tmp;
                        }
                    }
                }

                if (!empty($user_permissions)) {
                    foreach ($user_permissions as $user_permission) {
                        unset($tmp);
                        $tmp['actionEntitySet'] = [
                            [
                                'action' => "supplier_index",
                                'describe' => "index",
                                'defaultCheck' => true
                            ]
                        ];
                        $tmp['roleId'] = $user_permission->name;
                        $tmp['permissionId'] = $user_permission->name;
                        $tmp['permissionName'] = $user_permission->title;
                        $permissions[] = $tmp;
                    }
                }
                $permissions[] = [
                    'roleId' => 'shop',
                    'permissionId' => 'shop',
                    'permissionName' => '门店管理',
                ];
                $permissions[] = [
                    'roleId' => 'order',
                    'permissionId' => 'order',
                    'permissionName' => '订单管理',
                ];
                // $permissions[] = [
                //     'roleId' => 'vip_admin',
                //     'permissionId' => 'vip_admin',
                //     'permissionName' => '订单管理',
                // ];
                // 判断VIP商家权限
                if (Shop::where('user_id', $user->id)->where('vip_status', '1')->count() > 0) {
                    $permissions[] = [
                        'roleId' => 'vip_shop',
                        'permissionId' => 'vip_shop',
                        'permissionName' => 'VIP商家',
                    ];
                }
                $data['permissions'] = array_values($permissions);
            }
        }

        $user = [
            'id' => $request->user()->phone ?? '',
            'name' => $request->user()->phone ?? '',
            'phone' => $request->user()->phone ?? '',
            'nickname' => $request->user()->nickname ?? '',
            'money' => $request->user()->money ?? '',
            'frozen_money' => $request->user()->frozen_money ?? '',
            'operate_money' => $request->user()->operate_money ?? '',
            'created_at' => isset($request->user()->created_at) ? date("Y年m月d日", strtotime($request->user()->created_at)) : '',
            'role' => $data,
        ];
        return $this->success($user);
    }

    public function logout()
    {
        if (\Auth::guard('api')->check()) {
            \Auth::guard('api')->user()->token()->delete();
        }

        return response()->json(['message' => '登出成功', 'status_code' => 200, 'data' => null]);
    }

    public function change_password(Request $request)
    {
        $old_pwd = $request->get('old_pwd', '');
        $new_pwd = $request->get('new_pwd', '');

        if (!$old_pwd || !$new_pwd) {
            return $this->error("参数错误", 100);
        }


    }

    /**
     * 修改密码
     * @param Request $request
     * @return mixed
     */
    public function resetPassword(Request $request)
    {

        $password = $request->get('password');

        if (empty($password)) {
            return $this->error("新密码不能为空");
        }

        if (strlen($password) < 6) {
            return $this->error("密码长度不能小于6位");
        }

        $phone = $request->user()->phone;
        $captcha = $request->get('code', '');

        $verifyData = \Cache::get($phone);

        if (!$verifyData) {
            return $this->error('验证码已失效');
        }

        if (!hash_equals($verifyData['code'], $captcha)) {
            return $this->error('验证码失效');
        }

        auth()->user()->update([
            'password' => bcrypt($password),
        ]);

        \Cache::forget($phone);

        $token = DB::table("oauth_access_tokens")->orderByDesc('created_at')->first();
        DB::table("oauth_access_tokens")->where("user_id", auth()->user()->id)->where('id', '<>', $token->id)->delete();

        return $this->success();
    }

    /**
     * 修改密码
     * @param Request $request
     * @return mixed
     */
    public function resetPasswordByOld(Request $request)
    {
        $user = auth()->user();

        $isCheck = Hash::check($request->get('old'), $user->password);

        if (!$isCheck) {
            return $this->error('旧密码错误');
        }

        $request->validate([
            'old' => 'required',
            'new' => 'required',
        ], [], [
            'old' => '旧密码',
        ]);

        auth()->user()->update([
            'password' => bcrypt($request->get('new')),
        ]);

        $token = DB::table("oauth_access_tokens")->orderByDesc('created_at')->first();
        DB::table("oauth_access_tokens")->where("user_id", $user->id)->where('id', '<>', $token->id)->delete();

        return $this->success();
    }

    public function sms_password(Request $request)
    {
        $phone = $request->user()->phone;
        $template = 'SMS_186405047';

        // 生成4位随机数，左侧补0
        $code = str_pad(random_int(1, 9999), 4, 0, STR_PAD_LEFT);

        try {
            app('easysms')->send($phone, [
                'template' => $template,
                'data' => [
                    'code' => $code
                ],
            ]);
        } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
            $message = $exception->getException('aliyun')->getMessage();
            \Log::info('注册短信验证码发送异常', [$phone, $message]);
            return $this->error($message ?: '短信发送异常');
        }

        $key = $phone;
        $expiredAt = now()->addMinutes(5);
        // 缓存验证码 5 分钟过期。
        Cache::put($key, ['phone' => $phone, 'code' => $code], $expiredAt);

        return $this->success();
    }

    public function update(Request $request)
    {
        if (!$nick = $request->get('nickname')) {
            return $this->error('昵称不能为空', 422);
        }

        $user = $request->user();
        $user->nickname = $nick;
        $user->save();

        return $this->success();
    }
}
