<?php

namespace App\Exceptions;

use App\Http\Controllers\ApiResponse;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExceptionReport
{
    use ApiResponse;

    /**
     * @var Exception
     */
    public $exception;
    /**
     * @var Request
     */
    public $request;

    /**
     * @var
     */
    protected $report;

    /**
     * ExceptionReport constructor.
     * @param Request $request
     * @param Exception $exception
     */
    function __construct(Request $request, Exception $exception)
    {
        $this->request = $request;
        $this->exception = $exception;
    }

    /**
     * @var array
     */
    public $doReport = [
        AuthenticationException::class => ['未授权',401],
        ModelNotFoundException::class => ['该模型未找到',404],
        NotFoundHttpException::class => ['请求错误',404],
        UnauthorizedException::class => ['没有权限',403],
        HttpException::class => ['请求第三方失败',500],
        MessageException::class => ['错误，稍后再试',500],
        ValidationException::class => ['参数错误', 422],
        PermissionAlreadyExists::class => ['权限已存在', 422],
        RoleAlreadyExists::class => ['角色已存在', 422],
        MethodNotAllowedHttpException::class => ['请求方法错误', 405],
        QueryException::class => ['系统错误', 600],
    ];

    /**
     * @return bool
     */
    public function shouldReturn(){

        if (!$this->exception instanceof MethodNotAllowedHttpException){
            // 请求方法错误不做下面判断
            if (! ($this->request->wantsJson() || $this->request->ajax())){
                return false;
            }
        }

        // 钉钉异常通知
        if ($this->exception->getCode() >= 500) {
            $ding_notice = app("ding");
            $logs = [
                "\n\n请求地址" => $this->request->url(),
                "\n\n请求参数" => json_encode($this->request->all()),
                "\n\n头部信息" => json_encode($this->request->header()),
                "\n\n错误信息" => $this->exception->getMessage(),
                "\n\n文件名" => $this->exception->getFile(),
                "\n\n文件行" => $this->exception->getLine(),
                "\n\n时间" => date("Y-m-d H:i:s")
            ];
            $ding_notice->sendMarkdownMsgArray("跑腿系统异常", $logs);
        }

        foreach (array_keys($this->doReport) as $report){

            if ($this->exception instanceof $report){
                if($this->exception instanceof ValidationException){
                    $this->message = array_values($this->exception->errors())[0][0];
                }

                $this->report = $report;
                return true;
            }
        }

        return false;

    }

    /**
     * @param Exception $e
     * @return static
     */
    public static function make(Exception $e){

        return new static(\request(),$e);
    }

    /**
     * @return mixed
     */
    public function report(){

        $message = $this->doReport[$this->report];

        if ($this->exception instanceof MessageException) {
            $message[0] = $this->exception->getMessage() ?? $message[0];
        }

        return $this->error($this->message ?? $message[0],$message[1]);

    }

}
