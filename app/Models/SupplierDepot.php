<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierDepot extends Model
{

    protected $fillable = ['category_id','first_category','second_category','name','generi_name','spec','unit','price',
        'is_otc','upc','approval','manufacturer','term_of_validity','description','cover','images','content_images',
        'reason','status','type','yfyl','syz','syrq','cf','blfy','jj','zysx','ypxhzy','xz','bz','jx','zc','tid',];

    public function category()
    {
        return $this->belongsTo(SupplierCategory::class, "category_id", "id");
    }

    public function first()
    {
        return $this->belongsTo(Category::class, 'first_category', 'id');
    }

    public function second()
    {
        return $this->belongsTo(Category::class, 'second_category', 'id');
    }
}
