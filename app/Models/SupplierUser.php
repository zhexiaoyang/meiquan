<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SMartins\PassportMultiauth\HasMultiAuthApiTokens;

class SupplierUser extends Authenticatable
{
    use Notifiable, HasMultiAuthApiTokens;


    public function findForPassport($username)
    {
        return self::where(['phone' => $username])->first();
    }
}
