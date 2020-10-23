<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * 获取当前登录供货商用户信息
     * @return mixed
     */
    public function show()
    {
        $user = Auth::user();

        unset($user->password);
        unset($user->created_at);
        unset($user->updated_at);
        unset($user->remember_token);

        return $this->success($user);
    }

    /**
     * 保存当前登录供货商用户信息
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if ($avatar = $request->get("avatar")) {
            $user->avatar = $avatar;
        }

        if ($name = $request->get("name")) {
            $user->name = $name;
        }

        if ($yyzz = $request->get("yyzz")) {
            $user->yyzz = $yyzz;
        }

        if ($ypjy = $request->get("ypjy")) {
            $user->ypjy = $ypjy;
        }

        if ($description = $request->get("description")) {
            $user->description = $description;
        }

        $user->save();

        return $this->success();
    }
}
