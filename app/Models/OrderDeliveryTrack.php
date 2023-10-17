<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDeliveryTrack extends Model
{
    const TRACK_STATUS_CREATING = '下单成功';
    const TRACK_STATUS_WAITING = '等待骑手接单';
    const TRACK_STATUS_RECEIVING = '接单成功';
    const TRACK_STATUS_PICKING = '已到店';
    const TRACK_STATUS_TRANSFER = '已转单';
    const TRACK_STATUS_DELIVERING = '配送中';
    const TRACK_STATUS_FINISH = '配送完成';
    const TRACK_STATUS_RETURN = '商家退回签收';
    const TRACK_STATUS_CANCEL = '配送已取消';

    const TRACK_DESCRIPTION_RECEIVING = '配送员已接单，正赶往商家';
    const TRACK_DESCRIPTION_TRANSFER = '配送员已更换';
    const TRACK_DESCRIPTION_PICKING = '配送员已就位，请出货';
    const TRACK_DESCRIPTION_DELIVERING = '配送员已取货，正赶往目的地';
    const TRACK_DESCRIPTION_FINISH = '客户已收到商品';
    const TRACK_DESCRIPTION_FINISH2 = '商家已收到商品';

    protected $fillable = ['delivery_id','order_id','wm_id','status','status_des','delivery_name','delivery_phone',
        'delivery_lng','delivery_lat','description'];

}
