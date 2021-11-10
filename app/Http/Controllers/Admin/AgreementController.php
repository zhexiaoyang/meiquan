<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use Illuminate\Http\Request;

class AgreementController extends Controller
{
    public function index()
    {
        $agreements = Agreement::query()->orderBy('sort')->get();

        return $this->success($agreements);
    }

    public function show(Agreement $agreement)
    {
        return $this->success($agreement);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'url' => 'required',
            'sort' => 'required',
            'status' => 'required',
        ]);

        $data = $request->only('title','url','sort','status');
        $data['date'] = date("Y-m-d");

        Agreement::query()->create($data);

        return $this->success();
    }

    public function update(Agreement $agreement, Request $request)
    {
        $request->validate([
            'title' => 'required',
            'url' => 'required',
            'sort' => 'required',
            'status' => 'required',
        ]);

        $data = $request->only('title','url','sort','status');
        $data['date'] = date("Y-m-d");

        $agreement->update($data);

        return $this->success();
    }

    public function destroy(Agreement $agreement)
    {
        $agreement->delete();

        return $this->success();
    }
}
