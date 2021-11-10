<?php

namespace App\Libraries\TaoZi\Api;

class Api extends Request
{
    public function order($page = 1, $size = 100, $date = null)
    {
        $date = $date ?? date("Y-m-d", time() - 86400 * 3);

        $data = [
            'pageIndex' => $page,
            'pageSize' => $size,
            'date' => $date,
        ];

        return $this->post('api/opendata/v1/meituan/rpOrderList', $data);
    }
}
