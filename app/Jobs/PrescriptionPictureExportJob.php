<?php

namespace App\Jobs;

use App\Models\WmPrescriptionDown;
use App\Traits\NoticeTool2;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrescriptionPictureExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NoticeTool2;

    public $timeout = 600;

    public $orders;
    public $log_id;
    public $title;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($orders, $log_id, $title)
    {
        $this->orders = $orders;
        $this->log_id = $log_id;
        $this->title = $title;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Log::info('任务执行一次');
        $orders = $this->orders;
        $log_id = $this->log_id;
        $zip_file = tempnam("/tmp", "prescription-zip-");
        try {
            // 创建临时文件
            WmPrescriptionDown::where('id', $log_id)->update(['status' => 1]);
            $zip = new \ZipArchive;
            if ($zip->open($zip_file, \ZipArchive::CREATE) === TRUE)
            {
                // 批量写入文件
                foreach ($orders as $order) {
                    if ($order->rp_picture) {
                        $name = substr($order->rp_picture, strripos($order->rp_picture, '/') + 1);
                        try {
                            $zip->addFromString('处方图片/' . $name, file_get_contents($order->rp_picture));
                        } catch (\Exception $exception) {
                            $error_data = [
                                'message' => $exception->getMessage(),
                                'file' => $exception->getFile(),
                                'line' => $exception->getLine(),
                                'order' => $order->id,
                                'order_no' => $order->order_id,
                                'rp_picture' => $order->rp_picture,
                            ];
                            $this->ding_error('处方导出任务PrescriptionPictureExportJob出错：' . json_encode($error_data, true));
                        }
                    }
                }
                // 关闭zip文件
                $zip->close();
            }
            $oss = app('oss');
            $dir = 'prescription-zip/' . date('Ym/d/');
            $name = ($this->title ?: time()) . '.zip';
            $res = $oss->putObject('meiquan-file', $dir.$name, file_get_contents($zip_file));
            if (isset($res['info']['url'])) {
                WmPrescriptionDown::where('id', $log_id)->update(['status' => 2, 'url' => $res['info']['url']]);
            }
            // 删除临时文件
            unlink($zip_file);
        } catch (\Exception $exception) {
            \Log::info('下载处方图片JOB执行失败', [$exception->getMessage(),$exception->getLine(),$exception->getFile()]);
            WmPrescriptionDown::where('id', $log_id)->update(['status' => 2]);
            if (is_file($zip_file)) {
                unlink($zip_file);
            }
        }
    }
}
