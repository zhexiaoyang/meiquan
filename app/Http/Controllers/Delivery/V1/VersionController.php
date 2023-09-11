<?php

namespace App\Http\Controllers\Delivery\V1;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;

class VersionController extends Controller
{
    public function show(Request $request)
    {
        $platform = intval( $request->get('platform') );
        if (!in_array($platform, [1, 2])) {
            return $this->error('平台错误');
        }
        $version = AppVersion::where('platform', $platform)->where('status', 1)->orderByDesc('id')->first();
        return $this->success($version);
    }
}
