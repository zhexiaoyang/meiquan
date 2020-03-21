<?php

namespace App\Http\Controllers;

use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['login', 'register']);
    }

    public function login(Request $request)
    {
        try {
            $token = app(Client::class)->post(url('/oauth/token'), [
                'form_params' => [
                    'grant_type' => 'password',
                    'client_id' => config('passport.clients.password.client_id'),
                    'client_secret' => config('passport.clients.password.client_secret'),
                    'username' => $request->get('username'),
                    'password' => $request->get('password'),
                    'scope' => '',
                ],
            ]);

            $data = json_decode($token->getBody(), true);

            return $this->success($data);
        } catch (\Exception $e) {
            return $this->error("用户名或密码错误", 400);
        }
    }


    public function register(Request $request, User $user)
    {
        $phone = $request->get('phone');
        $password = $request->get('password');
        $verifyCode = (string)$request->get('verifyCode');

        $verifyData = \Cache::get($phone);

        if (!$verifyData) {
            return $this->error('验证码已失效');
        }

        if (!hash_equals($verifyData['code'], $verifyCode)) {
            return $this->error('验证码失效');
        }

        $user->name = $request->get('phone');
        $user->phone = $request->get('phone');
        $user->password = bcrypt($password);
        $user->save();

        $user->assignRole('shop');

        if ($user) {
            return $this->error("注册成功，请登录", 421);
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
            'roles' => $request->user()->hasRole('super_man') ? ['super_man'] : ['index'],
        ];
        return $this->success($user);
    }

    public function me(Request $request)
    {
        $role_name = $request->user()->getRoleNames()[0];

        $data = [];

        $role = Role::with(['permissions' => function($query) {
            $query->select('name', 'title', 'id', 'pid')->orderBy('pid', 'asc');
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
                    } else {
                        if (isset($permissions[$permission->pid])) {
                            $tmp['action'] = $permission->name;
                            $tmp['describe'] = $permission->title;
                            $tmp['defaultCheck'] = true;
                            $permissions[$permission->pid]['actionEntitySet'][] = $tmp;
                        }
                    }
                }
                $data['permissions'] = array_values($permissions);
            }
        }

        $user = [
            'id' => $request->user()->phone ?? '',
            'name' => $request->user()->phone ?? '',
            'phone' => $request->user()->phone ?? '',
            'money' => $request->user()->money ?? '',
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
        $isCheck = Hash::check($request->get('old_password'), auth()->user()->password);

        if (!$isCheck) {
            return $this->error('旧密码错误');
        }

        $request->validate([
            'old_password' => 'required',
            'password' => 'required|confirmed',
        ], [], [
            'old_password' => '旧密码',
        ]);

        auth()->user()->update([
            'password' => bcrypt($request->get('password')),
        ]);

        return $this->success();
    }
}
