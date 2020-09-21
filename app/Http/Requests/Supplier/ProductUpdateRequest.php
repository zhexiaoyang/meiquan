<?php

namespace App\Http\Requests\Supplier;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends Request
{
    public function rules()
    {
        return [
            // 'id' => 'bail|required|numeric|exists:supplier_products,id',
            'id' => [
                'required',
                Rule::exists('supplier_products', 'id')->where('user_id', $this->user()->id),
            ],
            'status' => 'nullable|in:0,10,20',
            'price' => 'bail|required|numeric|min:0.01',
            'stock' => 'bail|required|numeric'
        ];
    }

    public function messages()
    {
        return [
            'id.required' => '药品不能为空',
            'id.numeric' => '药品格式不正确',
            'id.exists' => '药品不存在',
            'status.in' => '药品状态格式不正确',
            'price.required' => '药品价格不能为空',
            'price.numeric' => '药品价格格式不正确',
            'price.min' => '药品价格不能小于等于0',
            'stock.required' => '药品库存不能为空',
            'stock.numeric' => '药品库存格式不正确'
        ];
    }
}
