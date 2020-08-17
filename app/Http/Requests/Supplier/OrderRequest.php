<?php

namespace App\Http\Requests\Supplier;

use App\Http\Requests\Request;
use App\Models\SupplierProduct;
use Illuminate\Validation\Rule;

class OrderRequest extends Request
{

    public function rules()
    {
        return [
            // 判断用户提交的地址 ID 是否存在于数据库并且属于当前用户
            // 后面这个条件非常重要，否则恶意用户可以用不同的地址 ID 不断提交订单来遍历出平台所有用户的收货地址
            'address_id'     => [
                'required',
                // Rule::exists('shops', 'id')->where('user_id', $this->user()->id),
            ],
            'items'  => ['required', 'array'],
            'items.*.product_id' => [ // 检查 items 数组下每一个子数组的 sku_id 参数
                'required',
                function ($attribute, $value, $fail) {
                    if (!$sku = SupplierProduct::find($value)) {
                        return $fail('该商品不存在');
                    }
                    if ($sku->stock === 0) {
                        return $fail('该商品已售完');
                    }
                    // 获取当前索引
                    preg_match('/items\.(\d+)\.product_id/', $attribute, $m);
                    $index = $m[1];
                    // 根据索引找到用户所提交的购买数量
                    $amount = $this->input('items')[$index]['amount'];
                    if ($amount > 0 && $amount > $sku->stock) {
                        return $fail('该商品库存不足');
                    }
                },
            ],
            'items.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages()
    {
        return [
            'address_id.required' => "地址不能为空",
            'address_id.exists' => '地址不存在',
            'items.*.amount.require' => "数量不能为空",
            'items.*.amount.min' => "数量不能小于1"
        ];
    }
}
