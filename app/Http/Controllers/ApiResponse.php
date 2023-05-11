<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Response;

trait ApiResponse
{

    /**
     * @param $data
     * @param $code
     * @param array $header
     * @return mixed
     */
    public function respond($data, $code, $header = [])
    {

        // return Response::json($data, $code, $header);
        return Response::json($data, $code, $header)->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param null $data
     * @param string $message
     * @param int $code
     * @param int $http_code
     * @return mixed
     */
    public function status($data = null, $message = "成功", $code = 0, $http_code = 200)
    {

        $response = [
            'code' => $code,
            'message' => $message,
        ];

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        return $this->respond($response, $http_code);

    }

    /**
     * @param array $data
     * @param string $message
     * @param int $code
     * @param int $http_code
     * @return mixed
     */
    public function success($data = [], $message = "成功", $code = 0, $http_code = 200)
    {

        return $this->status($data, $message, $code, $http_code);

    }

    public function page(LengthAwarePaginator $page, $data = [], $key = 'list', $message = "成功", $code = 0, $http_code = 200)
    {
        $res['page'] = $page->currentPage();
        $res['current_page'] = $page->currentPage();
        $res['total'] = $page->total();
        $res['page_total'] = $page->lastPage();
        $res['last_page'] = $page->lastPage();
        $res[$key] = $data ?: $page->items();

        return $this->status($res, $message, $code, $http_code);

    }

    /**
     * @param string $message
     * @param int $code
     * @param int $http_code
     * @return mixed
     */
    public function message($message = "成功", $code = 0, $http_code = 200)
    {

        return $this->status([], $message, $code, $http_code);

    }

    /**
     * @param $message
     * @param $code
     * @param int $http_code
     * @return mixed
     */
    public function error($message, $code=400, $http_code = 200)
    {
        return $this->status(null, $message, $code, $http_code);
    }

    /**
     * @param $message
     * @param $code
     * @param int $http_code
     * @return mixed
     */
    public function alert($message, $code=422, $http_code = 200)
    {
        return $this->status(null, $message, $code, $http_code);
    }

}
