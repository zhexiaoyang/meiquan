<?php

namespace App\Http\Controllers;

use App\Jobs\PrintWaiMaiOrder;
use App\Libraries\Feie\Feie;
use App\Models\Shop;
use App\Models\WmOrder;
use App\Models\WmPrinter;
use Illuminate\Http\Request;

class WmOrderController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get('page_size', 10);

        $query = WmOrder::with(['items' => function ($query) {
            $query->select('id', 'order_id', 'food_name', 'quantity', 'price', 'upc');
        }]);

        $query->whereIn('shop_id', $request->user()->shops()->pluck('id'));

        if ($status = $request->get('status', 0)) {
            $query->where('status', $status);
        }
        if ($channel = $request->get('channel', 0)) {
            $query->where('channel', $channel);
        }
        if ($way = $request->get('way', 0)) {
            $query->where('way', $way);
        }
        if ($platform = $request->get('platform', 0)) {
            $query->where('platform', $platform);
        }
        if ($order_id = $request->get('order_id', '')) {
            $query->where('order_id', 'like', "%{$order_id}%");
        }
        if ($name = $request->get('name', '')) {
            $query->where('recipient_name', $name);
        }
        if ($phone = $request->get('phone', '')) {
            $query->where('recipient_phone', $phone);
        }

        $data = $query->orderByDesc('id')->paginate($page_size);

        return $this->page($data);
    }

    public function show(Request $request)
    {
        if (!$order = WmOrder::with('items')->find($request->get('order_id', 0))) {
            return $this->error('订单不存在');
        }

        return $this->success($order);
    }

    public function print_list(Request $request)
    {
        $page_size = $request->get('page_size', '');

        $query = WmPrinter::with(['shop' => function ($query) {
            $query->select('id', 'shop_name');
        }]);

        if ($name = $request->get('name', '')) {
            $query->where('name', 'like', "{$name}");
        }
        if ($sn = $request->get('sn', '')) {
            $query->where('sn', $sn);
        }

        $query->whereIn('shop_id', Shop::where('own_id', $request->user()->id)->pluck('id'));

        $data = $query->orderByDesc('id')->paginate($page_size);

        return $this->page($data);
    }

    public function print_add(Request $request)
    {
        if (!$key = $request->get('key', 0)) {
            return $this->error('打印机key不能为空');
        }

        if (!$sn = $request->get('sn', 0)) {
            return $this->error('打印机sn不能为空');
        }

        if (!$platform = $request->get('platform', 0)) {
            return $this->error('请选择打印机平台');
        }

        if (!$shop_id = $request->get('shop_id', 0)) {
            return $this->error('门店不存在');
        }

        if (!in_array($shop_id, Shop::where('own_id', $request->user()->id)->pluck('id'))) {
            return $this->error('门店不存在！');
        }

        $number = $request->get('number', 1);

        if (!in_array($number, [1,2,3,4])) {
            $number = 1;
        }

        if (WmPrinter::query()->where('key',$key)->orWhere('sn', $sn)->first()) {
            return $this->error('KEY或者SN已经存在，不能重复添加！');
        }

        $name = $request->get('name', '');

        $content = $sn . '#' . $key;

        if ($name) {
            $content .= '#' . $name;
        }

        $f = new Feie();
        $res = $f->print_add($content);

        if (!empty($res['data']['no']) && (count($res['data']['no']) > 0)) {
            $message = $res['data']['no'][0];
            $message = strstr($message, '错误：');
            $message = mb_substr($message, 0, -1);
            return $this->success([], $message, 422);
        }

        WmPrinter::query()->create(compact('shop_id', 'name', 'key', 'sn', 'platform', 'number'));

        return $this->success();

    }

    public function print_update(Request $request)
    {
        $number = $request->get('number', 1);
        $name = $request->get('name', '');

        if (!$shop_id = $request->get('shop_id', 0)) {
            return $this->error('门店不存在');
        }

        if (!in_array($shop_id, Shop::where('own_id', $request->user()->id)->pluck('id'))) {
            return $this->error('门店不存在！');
        }

        if (!in_array($number, [1,2,3,4])) {
            $number = 1;
        }

        if (!$printer = WmPrinter::find($request->get('id', 0))) {
            return $this->error('参数错误，请稍后再试');
        }

        if (!Shop::where('id', $printer->shop_id)->where('own_id', $request->user()->id)->first()) {
            return $this->error('参数错误，请稍后再试');
        }

        $printer->name = $name;
        $printer->number = $number;
        $printer->shop_id = $shop_id;
        $printer->save();

        return $this->success();
    }

    public function print_del(Request $request)
    {
        if (!$printer = WmPrinter::find($request->get('id', 0))) {
            return $this->error('打印机不存在');
        }

        $f = new Feie();
        $res = $f->print_del($printer->sn);

        if (!isset($res['ret']) || $res['ret'] !== 0) {
            return $this->error('打印机删除失败');
        }

        $printer->delete();

        return $this->success();
    }

    public function print_clear(Request $request)
    {
        if (!$printer = WmPrinter::find($request->get('id', 0))) {
            return $this->error('打印机不存在');
        }

        if (!in_array($printer->shop_id, Shop::where('own_id', $request->user()->id)->pluck('id'))) {
            return $this->error('打印机不存在！');
        }

        $f = new Feie();
        $res = $f->print_clear($printer->sn);

        if (!isset($res['ret']) || $res['ret'] !== 0) {
            return $this->error('清空待打印失败');
        }

        return $this->success();
    }

    public function print_shops(Request $request)
    {
        $query = Shop::query()->select('id', 'shop_name')
            ->where(function ($query) {
                $query->where('waimai_mt', "<>", "")->orWhere('waimai_ele', "<>", "");
            });
        $query->whereIn('id', $request->user()->shops()->pluck('id'));

        return $this->success($query->get());
    }

    public function print_order(Request $request)
    {
        if (!$order = WmOrder::find($request->get('order_id', 0))) {
            return $this->error("订单不存在");
        }

        if ($print = WmPrinter::where('shop_id', $order->shop_id)->first()) {
            dispatch(new PrintWaiMaiOrder($order, $print));
        }

        return $this->success();
    }
}
