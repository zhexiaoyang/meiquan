<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    protected $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'phone','money','shop_id','frozen_money','nickname','status','is_operate'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function findForPassport($username)
    {
//        filter_var($username, FILTER_VALIDATE_EMAIL) ?
//            $credentials['email'] = $username :
//            $credentials['phone'] = $username;

        return self::where(['name' => $username])->first();
    }

    public function deposit()
    {
        return $this->hasMany(Deposit::class);
    }

    public function shops()
    {
        // return $this->hasMany(Shop::class);
        return $this->belongsToMany(Shop::class, "user_has_shops", "user_id", "shop_id");
    }

    public function my_shops()
    {
        return $this->hasMany(Shop::class, 'own_id', 'id');
    }

    public function carts()
    {
        return $this->hasMany(SupplierCart::class);
    }

    public function commission()
    {
        return $this->hasOne(UserReturn::class);
    }

    public function getReceiveShopIdAttribute()
    {
        $shop_id = $this->shop_id ?? 0;
        \Log::info("getReceiveShopIdAttribute", [$shop_id]);

        if (!$shop = Shop::query()->where(['own_id' => $this->id, 'id' => $shop_id, 'auth' => 10])->first()) {
            $shop_id = 0;
        }

        if (!$shop_id) {
            if ($shop = Shop::query()->where(['own_id' => $this->id, 'auth' => 10])->orderBy('id')->first()) {
                $shop_id = $shop->id;
                $this->shop_id = $shop->id;
                $this->save();
            }
        }

        return $shop_id;
    }
}
