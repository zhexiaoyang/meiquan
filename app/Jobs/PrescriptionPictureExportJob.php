<?php

namespace App\Jobs;

use App\Models\WmPrescriptionDown;
use App\Traits\NoticeTool2;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class PrescriptionPictureExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NoticeTool2;

    public $timeout = 600;

    public $orders;
    public $log_id;
    public $title;
    public $sign;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($orders, $log_id, $title, $sign = '')
    {
        $this->orders = $orders;
        $this->log_id = $log_id;
        $this->title = $title;
        $this->sign = $sign;
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
                // $redis_key = 'shop_pharmacists';
                $redis_shenhe = 'shop_pharmacists';
                $redis_tiaoji = 'shop_pharmacists_tiaoji';
                $redis_hedui = 'shop_pharmacists_hedui';
                $redis_fayao = 'shop_pharmacists_fayao';
                // 批量写入文件t
                foreach ($orders as $order) {
                    if ($order->rp_picture) {
                        $name = substr($order->rp_picture, strripos($order->rp_picture, '/') + 1);
                        $shenhe = Redis::hget($redis_shenhe, $order->shop_id);
                        $tiaoji = Redis::hget($redis_tiaoji, $order->shop_id);
                        $hedui = Redis::hget($redis_hedui, $order->shop_id);
                        $fayao = Redis::hget($redis_fayao, $order->shop_id);
                        try {
                            if ($this->sign && ($shenhe || $tiaoji || $hedui || $fayao)) {
                                if ($order->platform == 1) {
                                    $imageData = file_get_contents($order->rp_picture);
                                    $imageResource = imagecreatefromstring($imageData);
                                    // 设置文字内容和字体样式
                                    $font = storage_path('font.ttf');
                                    // 设置文字颜色为黑色
                                    $textColor = imagecolorallocate($imageResource, 0, 0, 0);
                                    // 在画布上绘制文字
                                    if ($shenhe) {
                                        imagettftext($imageResource, 20, 0, 177, 870, $textColor, $font, $shenhe);
                                    }
                                    if ($tiaoji) {
                                        imagettftext($imageResource, 20, 0, 345, 870, $textColor, $font, $tiaoji);
                                    }
                                    if ($hedui) {
                                        imagettftext($imageResource, 20, 0, 534, 870, $textColor, $font, $hedui);
                                    }
                                    if ($fayao) {
                                        imagettftext($imageResource, 20, 0, 720, 870, $textColor, $font, $fayao);
                                    }
                                    $file_name = '/tmp/circle' . rand(1000000, 9999999) . '.png';
                                    imagepng($imageResource, $file_name);
                                    $zip->addFromString('处方图片/' . $name, file_get_contents($file_name));
                                    // 释放资源
                                    unlink($file_name);
                                    imagedestroy($imageResource);
                                } else if ($order->platform == 2) {
                                    $pdf_file = $order->rp_picture;
                                    $im = new \Imagick();
                                    //设置分辨率
                                    $im->setResolution(150, 150);
                                    //设置压缩质量，1-100,100为最高
                                    $im->setCompressionQuality(100);
                                    //读取文件
                                    $im->readImage($pdf_file);
                                    foreach ($im as $Var) {
                                        $blankPage = new \Imagick();
                                        //一张白纸，作为背景
                                        $blankPage->newPseudoImage(840, 980, "canvas:white");
                                        $blankPage->addImage($Var);
                                        $blankPage = $blankPage->mergeImageLayers(11);
                                        //设置图片格式
                                        $blankPage->setImageFormat('png');
                                        $tmp_file_name = storage_path("prescription/" . rand(1000, 9999) . ".png");
                                        $blankPage->writeImage($tmp_file_name);
                                        $blankPage->destroy();
                                        // 写入签名
                                        $shenhe = '张一';
                                        $tiaoji = '张二';
                                        $hedui = '张三';
                                        $fayao = '张四';
                                        $imageData = file_get_contents($tmp_file_name);
                                        $imageResource = imagecreatefromstring($imageData);
                                        // 设置文字内容和字体样式
                                        $font = storage_path('font.ttf');
                                        // 设置文字颜色为黑色
                                        $textColor = imagecolorallocate($imageResource, 0, 0, 0);
                                        // 在画布上绘制文字
                                        if ($shenhe) {
                                            imagettftext($imageResource, 20, 0, 700, 990, $textColor, $font, $shenhe);
                                        }
                                        if ($tiaoji) {
                                            imagettftext($imageResource, 20, 0, 215, 990, $textColor, $font, $tiaoji);
                                        }
                                        if ($hedui && $fayao) {
                                            imagettftext($imageResource, 20, 0, 466, 990, $textColor, $font, $hedui . '/' . $fayao);
                                        } else {
                                            if ($hedui) {
                                                imagettftext($imageResource, 20, 0, 466, 990, $textColor, $font, $hedui);
                                            }
                                            if ($fayao) {
                                                imagettftext($imageResource, 20, 0, 466, 990, $textColor, $font, $fayao);
                                            }
                                        }
                                        $file_name = storage_path("prescription/" . rand(1000000, 99999999) . ".png");
                                        imagepng($imageResource, $file_name);
                                        $zip->addFromString('处方图片/' . $name, file_get_contents($file_name));
                                        // 释放资源
                                        unlink($file_name);
                                        unlink($tmp_file_name);
                                        imagedestroy($imageResource);

                                    }
                                    $im->destroy();
                                }
                            } else {
                                $zip->addFromString('处方图片/' . $name, file_get_contents($order->rp_picture));
                            }
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
            $name = ($this->title ? $this->title . time() : '处方图片' . time()) . '.zip';
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
