<?php

namespace App\Http\Requests\Supplier;

use App\Http\Requests\Request;

class ProductRequest extends Request
{
    public function rules()
    {
        return [
            'category_id' => 'bail|required|numeric|exists:supplier_categories,id',
            'name' => 'bail|required|min:2|max:100',
            'price' => 'bail|required|numeric|min:0.01',
            'stock' => 'bail|required|numeric',
//            'spec' => 'bail|required|min:1|max:100',
//            'unit' => 'bail|required|max:100',
//            'is_otc' => 'required|integer|in:1,0',
//            'upc' => 'bail|required|min:2|max:100|unique:supplier_depots,upc',
//            'approval' => 'bail|required|min:2|max:100',
            'cover' => 'bail|required|min:2|max:255',
            'images' => 'nullable|array',
        ];
    }

    public function messages()
    {
        return [
            'category_id.required' => '药品分类不能为空',
            'category_id.numeric' => '药品分类格式不正确',
            'category_id.exists' => '药品分类不存在',
            'name.required' => '药品名称不能为空',
            'name.min' => '药品名称不能小于2个文字',
            'name.max' => '药品名称不能大于100个文字',
            'price.required' => '药品价格不能为空',
            'price.numeric' => '药品价格格式不正确',
            'price.min' => '药品价格不能小于等于0',
            'stock.required' => '药品库存不能为空',
            'stock.numeric' => '药品库存格式不正确',
//            'spec.required' => '药品规格不能为空',
//            'spec.min' => '药品规格不能小于1个文字',
//            'spec.max' => '药品规格不能大于100个文字',
//            'unit.required' => '药品单位不能为空',
//            'unit.max' => '药品单位不能大于100个文字',
//            'is_otc.required' => '是否OTC不能为空',
//            'is_otc.integer' => '是否OTC格式不正确',
//            'is_otc.in' => '是否OTC格式不正确',
//            'upc.required' => '条形码不能为空',
//            'upc.min' => '条形码不能小于2个文字',
//            'upc.max' => '条形码不能大于100个文字',
//            'upc.unique' => '条形码已存在，请在品库中添加该商品',
//            'description.string' => '详情图片格式不正确',
//            'approval.required' => '国药准字号不能为空',
//            'approval.min' => '国药准字号不能小于2个文字',
//            'approval.max' => '国药准字号不能大于100个文字',
            'cover.required' => '封面图片不能为空',
            'cover.min' => '封面图片不能小于2个文字',
            'cover.max' => '封面图片不能大于100个文字',
            'images' => '展示图片格式不正确',
        ];
    }
}
