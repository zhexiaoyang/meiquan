<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineSyncLogItem extends Model
{
    const MEDICINE_NO_FOND = '药品不存在。';
    const MEDICINE_NO_SYNC_MEITUAN = '药品未同步美团外卖。';
    const MEDICINE_NO_SYNC_FAIL_MEITUAN = '药品同步美团外卖失败。';
    const MEDICINE_NO_SYNC_ELE = '药品未同步饿了么。';
    const MEDICINE_NO_SYNC_FAIL_ELE = '药品同步饿了么失败。';
    const NOT_BIND_MEITUAN = '门店未绑定美团外卖。';
    const NOT_BIND_ELE = '门店未绑定饿了。';

    protected $table = 'wm_medicine_sync_log_items';

    protected $fillable = ['log_id', 'name', 'msg', 'upc'];
}
