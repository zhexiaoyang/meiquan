<?php

namespace App\Http\Controllers;

use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['login']);
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

//    public function register(Request $request)
//    {
//        $user = User::create([
//            'name' => $request->get('username'),
//            'email' => $request->get('email'),
//            'password' => bcrypt($request->get('password')),
//        ]);
//
//
//        $token = $user->createToken('Laravel Password Grant Client')->accessToken;
//
//        return response()->json([
//            'token' => $token,
//        ]);
//    }

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
