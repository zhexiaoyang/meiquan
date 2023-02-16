<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupplierDepot;
use Illuminate\Http\Request;

class SupplierDepotController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);
        $query = SupplierDepot::query()->select('id', 'name', 'upc', 'spec', 'unit', 'cover', 'manufacturer', 'images', 'content_images');
        if ($name = $request->get('name')) {
            $query->where('name', 'like', "%{$name}%");
        }
        if ($upc = $request->get('upc')) {
            $query->where('upc', $upc);
        }
        $data = $query->orderByDesc('id')->paginate($page_size);

        return $this->success($data);
    }

    public function info(Request $request)
    {
        if (!$id = $request->get('id')) {
            return $this->error('参数错误');
        }
        if (!$info = SupplierDepot::find($id)) {
            return $this->error('商品不存在');
        }
        return $this->success($info);
    }

    public function update(Request $request)
    {
        if (!$images = $request->get('images')) {
            return $this->error('商品图片不能为空');
        }
        if (!$name = $request->get('name')) {
            return $this->error('商品名称不能为空');
        }
        $image_data = explode(',', $images);
        if (empty($image_data)) {
            return $this->error('商品图片不能为空');
        }
        $cover = $image_data[0];
        $content_images = $request->get('content_images', '');
        if (!$id = intval($request->get('id'))) {
            return $this->error('参数错误');
        }
        if (!SupplierDepot::find($id)) {
            return $this->error('商品不存在');
        }
        SupplierDepot::query()->where('id', $id)->update([
            'name' => $name,
            'cover' => $cover,
            'images' => $images,
            'content_images' => $content_images
        ]);
        return $this->success();
    }
}
