<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErpAccessKey extends Model
{
    protected $fillable = ["title", "description"];

    public function shops()
    {
        return $this->hasMany(ErpAccessShop::class, 'access_id');
    }

    protected static function boot()
    {
        parent::boot();
        // 监听模型创建事件，在写入数据库之前触发
        static::creating(function ($model) {
            if (!$model->access_key) {
                $model->access_key = static::findAvailableAccessKey();
                if (!$model->access_key) {
                    return false;
                }
            }
            if (!$model->access_secret) {
                $model->access_secret = static::findAvailableAccessSecret();
                if (!$model->access_secret) {
                    return false;
                }
            }
        });
    }

    public static function findAvailableAccessKey()
    {
        // access_key
        for ($i = 0; $i < 20; $i++) {
            $access_key = static::randStr(8, 2);
            if (!static::query()->where('access_key', $access_key)->exists()) {
                return $access_key;
            }
        }
        return false;
    }

    public static function findAvailableAccessSecret()
    {
        // access_key
        for ($i = 0; $i < 20; $i++) {
            $access_secret = static::randStr(32);
            if (!static::query()->where('access_secret', $access_secret)->exists()) {
                return $access_secret;
            }
        }
        return false;
    }

    public static function randStr($length = 32, $type = 1){
        //字符组合
        $str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($type === 2) {
            $str = '123456789';
        }
        $len = strlen($str)-1;
        $randstr = '';
        for ($i=0;$i<$length;$i++) {
            $num=mt_rand(0,$len);
            $randstr .= $str[$num];
        }
        return $randstr;
    }
}
