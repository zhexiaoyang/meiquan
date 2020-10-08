<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\SupplierUser;
use App\Traits\PassportToken;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use PassportToken;


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
                    "provider" => "supplier_users"
                ],
            ]);

            $data = json_decode($token->getBody(), true);

            return $this->success($data);
        } catch (\Exception $e) {
            return $this->error("用户名或密码错误", 400);
        }
    }

    public function me(Request $request)
    {
        $user = $request->user();

        $permission = [
            [
                "roleId"=> "supplier",
                "permissionId"=> "auth",
                "permissionName"=> "认证",
                "actionEntitySet"=> []
            ],
            [
                "roleId"=> "supplier",
                "permissionId"=> "user",
                "permissionName"=> "个人中心",
                "actionEntitySet"=> []
            ]
        ];

        if (SupplierUser::query()->find($user->id)->is_auth) {
            $permission[] = [
                "roleId"=> "supplier",
                "permissionId"=> "supplier",
                "permissionName"=> "编辑",
                "actionEntitySet"=> []
            ];
        }

        $result = [
            "id"=> $user->phone,
            "name"=> $user->name,
            "phone"=> $user->phone,
            "role"=> [
                "id"=> "supplier",
                "name"=> "供货商",
                "permissions"=> $permission
            ]
        ];

        return $this->success($result);
    }

    public function logout(Request $request)
    {
        if (\Auth::guard('supplier')->check()) {
            \Auth::guard('supplier')->user()->token()->delete();
        }

        return $this->success();
    }
}
