<?php

namespace App\Exceptions;

use App\Libraries\DingTalk\DingTalkRobotNotice;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Exceptions\OAuthServerException;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        NoPermissionException::class,
        InvalidRequestException::class,
        OAuthServerException::class,
        AuthenticationException::class,
        ValidationException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        // 钉钉通知状态
        $notice_status = false;
        // 判断异常码
        $fe = FlattenException::create($exception);
        $StatusCode = $fe->getStatusCode();
        if ( $StatusCode != 401 && $StatusCode !=403 && $StatusCode != 404 && $StatusCode != 405 && $StatusCode != 429){
            $notice_status = true;
        }
        // 判断异常是否需要通知
        foreach ($this->dontReport as $report){
            if ($exception instanceof $report){
                $notice_status = false;
            }
        }

        // 钉钉通知
        if ($notice_status) {
            $dingding = new DingTalkRobotNotice("6b2970a007b44c10557169885adadb05bb5f5f1fbe6d7485e2dcf53a0602e096");
            $logs = [
                "\n\n错误编码" => $fe->getStatusCode(),
                "\n\n错误内容" => $fe->getMessage(),
                "\n\n文件名" => $fe->getFile(),
                "\n\n报错行" => $fe->getLine(),
                "\n\n时间" => date("Y-m-d H:i:s"),
            ];
            $dingding->sendMarkdownMsgArray("中台异常", $logs);
            \Log::info("异常全部信息", [$fe->getClass(),$fe->toArray()]);
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        // 将方法拦截到自己的ExceptionReport
        $reporter = ExceptionReport::make($exception);

        if ($reporter->shouldReturn()){
            return $reporter->report();
        }

        return parent::render($request, $exception);
    }
}
