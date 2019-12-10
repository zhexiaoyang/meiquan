<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index()
    {
        $users = User::query()->orderBy('id', 'desc')->paginate();
        return $this->success($users);
    }

    public function show(User $user)
    {
        return $this->success($user);
    }

    public function store(Request $request)
    {
        $name = $request->get('username', '');
        $email = $request->get('email', '');
        $phone = $request->get('phone', '');
        $password = $request->get('password', '654321');

        if ($name) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => bcrypt($password),
            ]);

            if ($user->id) {
                return $this->success([]);
            }
        }

        return $this->error("创建失败");
    }

}
