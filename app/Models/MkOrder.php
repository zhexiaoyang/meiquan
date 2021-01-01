<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MkOrder extends Model
{
    protected $fillable = ["order_id","wm_order_id_view","order_tag_list","app_poi_code","wm_poi_name","wm_poi_address",
        "wm_poi_phone","recipient_address","recipient_phone","backup_recipient_phone","recipient_name","shipping_fee",
        "total","original_price","goods_price","caution","shipper_phone","status","ctime","utime","delivery_time",
        "is_third_shipping","pick_type","latitude","longitude","day_seq","logistics_code","package_bag_money",
        "package_bag_money_yuan","total_weight","poi_receive_detail_yuan","extras","sku_benefit_detail",
        "mt_created_at","mt_updated_at"];
}
