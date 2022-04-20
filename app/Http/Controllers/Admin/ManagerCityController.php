<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManagerCity;
use App\Models\User;
use Illuminate\Http\Request;

class ManagerCityController extends Controller
{
    public function index(Request $request)
    {
        $user_id = $request->get('user_id', 0);

        if (!$user = User::find($user_id)) {
            return $this->error('用户不存在');
        }

        if (!$user->hasRole('city_manager')) {
            return $this->error('该用户不是城市经理');
        }

        $data = ManagerCity::where('user_id', $user->id)->pluck('city');

        return $this->success($data);
    }

    public function store(Request $request)
    {
        if (!$city = $request->get('city')) {
            return $this->error('城市不存在');
        }

        $user_id = $request->get('user_id', 0);

        if (!$user = User::find($user_id)) {
            return $this->error('用户不存在');
        }

        if (!$user->hasRole('city_manager')) {
            return $this->error('该用户不是城市经理');
        }

        if ($city_data = ManagerCity::where('city', $city)->first()) {
            $city_user = User::find($city_data->user_id);
            $nickname = $city_user->nickname ?: $city_user->name;
            return $this->error("{$city}已有运营经理:{$nickname}");
        }

        ManagerCity::create([
            'user_id' => $user_id,
            'city' => $city
        ]);

        return $this->success();
    }

    public function destroy( Request $request)
    {
        if (!$city = $request->get('city')) {
            return $this->error('城市不存在');
        }

        $user_id = $request->get('user_id', 0);

        if (!$user = User::find($user_id)) {
            return $this->error('用户不存在');
        }

        if (!$user->hasRole('city_manager')) {
            return $this->error('该用户不是城市经理');
        }

        ManagerCity::where('city', $city)->where('user_id', $user_id)->delete();

        return $this->success();
    }
}
