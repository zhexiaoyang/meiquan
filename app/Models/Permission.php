<?php

namespace App\Models;

class Permission extends \Spatie\Permission\Models\Permission
{
    public function actions()
    {
        return $this->hasMany(Permission::class, 'pid');
    }
}
