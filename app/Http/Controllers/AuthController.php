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

    public function register(Request $request)
    {
        $user = User::create([
            'name' => $request->get('username'),
            'email' => $request->get('email'),
            'password' => bcrypt($request->get('password')),
        ]);


        $token = $user->createToken('Laravel Password Grant Client')->accessToken;

        return response()->json([
            'token' => $token,
        ]);
    }

    public function user(Request $request)
    {
        return $this->success($request->user());
    }

    public function logout()
    {
        if (\Auth::guard('api')->check()) {
            \Auth::guard('api')->user()->token()->delete();
        }

        return response()->json(['message' => '登出成功', 'status_code' => 200, 'data' => null]);
    }
}
