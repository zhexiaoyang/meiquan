<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $phone;
    protected $template;
    protected $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($phone, $template, $data)
    {
        $this->phone = $phone;
        $this->template = $template;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            app('easysms')->send($this->phone, [
                'template' => $this->template,
                'data' => [
                    'name' => $this->data[0],
                    'number' => $this->data[1]
                ],
            ]);
        } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
            $message = $exception->getException('aliyun')->getMessage();
            \Log::info('送短信失败：', [$this->phone, $this->template, $this->data]);
        }
    }
}
