<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CityManager;
use Illuminate\Http\Request;

class CityManagerController extends Controller
{
    public function index(Request $request)
    {
        $page_size = $request->get("page_size", 10);
        $name = $request->get("name", "");
        $phone = $request->get("phone", "");
        $status = $request->get("status");

        $query = CityManager::query();

        if (!is_null($status) && in_array($status, [0, 1])) {
            $query->where("status", $status);
        }

        if ($name) {
            $query->where("name", $name);
        }

        if ($phone) {
            $query->where("phone", $phone);
        }

        if (!in_array($page_size, [10,20,30,50,100])) {
            $page_size = 10;
        }

        $data = $query->paginate($page_size);

        return $this->page($data);
    }

    public function store(Request $request)
    {
        $name = $request->get("name", "");
        $phone = $request->get("phone", "");
        $status = $request->get("status", 0);

        if (!$name) {
            return $this->error("姓名不能为空");
        }

        if (!$phone) {
            return $this->error("电话不能为空");
        }

        if (strlen($phone) !== 11) {
            return $this->error("电话格式不正确");
        }

        if ($status !== 1) {
            $status = 0;
        }

        CityManager::query()->create(["name" => $name, "phone" => $phone, "status" => $status]);

        return $this->success();
    }

    public function update(CityManager $cityManager, Request $request)
    {
        $name = $request->get("name", "");
        $phone = $request->get("phone", "");
        $status = $request->get("status", 0);

        if (!$name) {
            return $this->error("姓名不能为空");
        }

        if (!$phone) {
            return $this->error("电话不能为空");
        }

        if (strlen($phone) !== 11) {
            return $this->error("电话格式不正确");
        }

        if ($status !== 1) {
            $status = 0;
        }

        $cityManager->update(["name" => $name, "phone" => $phone, "status" => $status]);

        return $this->success();
    }

    public function destroy(CityManager $cityManager)
    {
        $cityManager->delete();

        return $this->success();
    }

    public function show(CityManager $cityManager)
    {

        return $this->success($cityManager);
    }
}
