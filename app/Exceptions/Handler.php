<?php

namespace App\Exceptions;

use App\Libraries\DingTalk\DingTalkRobotNotice;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        InvalidRequestException::class,
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
        $dingding = new DingTalkRobotNotice("6b2970a007b44c10557169885adadb05bb5f5f1fbe6d7485e2dcf53a0602e096");
        $fe = FlattenException::create($exception);
        $StatusCode = $fe->getStatusCode();
        if ( $StatusCode != 404 && $StatusCode !=401 && $StatusCode != 405){
            $logs = [
                "\n\n错误编码" => $fe->getStatusCode(),
                "\n\n错误内容" => $fe->getMessage(),
                "\n\n文件名" => $fe->getFile(),
                "\n\n报错行" => $fe->getLine(),
                "\n\n时间" => date("Y-m-d H:i:s"),
            ];
            $dingding->sendMarkdownMsgArray("中台异常", $logs);
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
