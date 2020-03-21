<?php

namespace App\Models;

class Permission extends \Spatie\Permission\Models\Permission
{
    protected $fillable = ['pid', 'name', 'title', 'guard_name', 'status'];

    public function actions()
    {
        return $this->hasMany(Permission::class, 'pid');
    }
}
