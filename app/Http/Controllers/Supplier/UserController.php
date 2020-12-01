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

        if ($telephone = $request->get("telephone")) {
            $user->telephone = $telephone;
        }

        if ($yyzz = $request->get("yyzz")) {
            $user->yyzz = $yyzz;
            $user->auth_at = date("Y-m-d H:i:s");
        }

        if ($ypjy = $request->get("ypjy")) {
            $user->ypjy = $ypjy;
        }

        if ($spjy = $request->get("spjy")) {
            $user->spjy = $spjy;
        }

        if ($ylqx = $request->get("ylqx")) {
            $user->ylqx = $ylqx;
        }

        if ($ndbg = $request->get("ndbg")) {
            $user->ndbg = $ndbg;
        }

        if ($elqx = $request->get("elqx")) {
            $user->elqx = $elqx;
        }

        if ($khxk = $request->get("khxk")) {
            $user->khxk = $khxk;
        }

        if ($nsrdj = $request->get("nsrdj")) {
            $user->nsrdj = $nsrdj;
        }

        if ($hggh = $request->get("hggh")) {
            $user->hggh = $hggh;
        }

        if ($qygl = $request->get("qygl")) {
            $user->qygl = $qygl;
        }

        if ($kpxx = $request->get("kpxx")) {
            $user->kpxx = $kpxx;
        }

        if ($ypgxht = $request->get("ypgxht")) {
            $user->ypgxht = $ypgxht;
        }

        if ($zlbzxys = $request->get("zlbzxys")) {
            $user->zlbzxys = $zlbzxys;
        }

        if ($xssqwts = $request->get("xssqwts")) {
            $user->xssqwts = $xssqwts;
        }

        if ($yzymbab = $request->get("yzymbab")) {
            $user->yzymbab = $yzymbab;
        }

        if ($shtxd = $request->get("shtxd")) {
            $user->shtxd = $shtxd;
        }

        if ($yyzz_start_time = $request->get("yyzz_start_time")) {
            $user->yyzz_start_time = $yyzz_start_time;
        }

        if ($yyzz_end_time = $request->get("yyzz_end_time")) {
            $user->yyzz_end_time = $yyzz_end_time;
        }

        if ($ypjy_start_time = $request->get("ypjy_start_time")) {
            $user->ypjy_start_time = $ypjy_start_time;
        }

        if ($ypjy_end_time = $request->get("ypjy_end_time")) {
            $user->ypjy_end_time = $ypjy_end_time;
        }

        if ($spjy_start_time = $request->get("spjy_start_time")) {
            $user->spjy_start_time = $spjy_start_time;
        }

        if ($spjy_end_time = $request->get("spjy_end_time")) {
            $user->spjy_end_time = $spjy_end_time;
        }

        if ($ylqx_start_time = $request->get("ylqx_start_time")) {
            $user->ylqx_start_time = $ylqx_start_time;
        }

        if ($ylqx_end_time = $request->get("ylqx_end_time")) {
            $user->ylqx_end_time = $ylqx_end_time;
        }

        if ($description = $request->get("description")) {
            $user->description = $description;
        }

        if ($notice = $request->get("notice")) {
            $user->notice = $notice;
        }

        if ($chang = $request->get("chang")) {
            $user->chang = $chang;
        }

        $user->save();

        return $this->success();
    }
}
