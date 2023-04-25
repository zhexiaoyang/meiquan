<?php

namespace App\Imports;

use App\Exceptions\InvalidRequestException;
use App\Jobs\MedicineImportJob;
use App\Jobs\MedicineUpdateImportJob;
use App\Models\Medicine;
use App\Models\MedicineDepot;
use App\Models\MedicineSyncLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MedicineUpdateImport implements ToCollection, WithHeadingRow, WithValidation, WithCalculatedFormulas
{
    public $shop_id;

    public function collection(Collection $row)
    {
        $row = $row->toArray();
        // 第一行是说明，去掉
        array_shift($row);
        // throw new InvalidRequestException($row[0]['商品条码'], 422);
        if (count($row) > 5000) {
            throw new InvalidRequestException('药品数量不能超过5000', 422);
        }
        if (count($row) < 1) {
            throw new InvalidRequestException('药品数量为空', 422);
        }
        $t_l = count($row);
        \Log::info("总行数：{$t_l}");

        $chongfu = [];

        foreach ($row as $k => $item) {
            $line = $k + 3;
            $update_status = false;
            if (!isset($item['条形码'])) {
                throw new InvalidRequestException("第{$line}行不存在商品条码", 422);
            }
            if (isset($chongfu[$item['条形码']])) {
                throw new InvalidRequestException("第{$line}行条形码与第{$chongfu[$item['条形码']]}行条形码重复", 422);
            }
            if (trim($item['线上销售价格']) !== '') {
                if (!is_numeric($item['线上销售价格'])) {
                    throw new InvalidRequestException("第{$line}行线上销售价格格式不正确", 422);
                }
                if ($item['线上销售价格'] == 0) {
                    throw new InvalidRequestException("第{$line}行线上销售价格不能为0", 422);
                }
                $update_status = true;
            }
            if (trim($item['线下销售价格']) !== '') {
                if (!is_numeric($item['线下销售价格'])) {
                    throw new InvalidRequestException("第{$line}行线下销售价格格式不正确", 422);
                }
                $update_status = true;
            }
            if (trim($item['售卖状态']) !== '') {
                if (!is_numeric($item['售卖状态'])) {
                    throw new InvalidRequestException("第{$line}行售卖状态格式不正确", 422);
                }
                if (!in_array(intval($item['售卖状态']), [0, 1])) {
                    throw new InvalidRequestException("第{$line}行售卖状态错误", 422);
                }
                $update_status = true;
            }
            if (trim($item['成本价格']) !== '') {
                if (!is_numeric($item['成本价格'])) {
                    throw new InvalidRequestException("第{$line}行成本价格格式不正确", 422);
                }
                $update_status = true;
            }
            if (trim($item['库存']) !== '') {
                if (!is_numeric($item['库存'])) {
                    throw new InvalidRequestException("第{$line}行库存格式不正确", 422);
                }
                $update_status = true;
            }
            if (trim($item['排序']) !== '') {
                if (!is_numeric($item['排序'])) {
                    throw new InvalidRequestException("第{$line}行排序格式不正确", 422);
                }
                $update_status = true;
            }
            if ($update_status === false) {
                throw new InvalidRequestException("第{$line}请填写更新内容", 422);
            }
            $chongfu[$item['条形码']] = $line;
        }

        // 添加日志
        $log = MedicineSyncLog::create([
            'shop_id' => $this->shop_id,
            'title' => '批量导入更新药品',
            'log_id' => uniqid(),
            'total' => $t_l,
        ]);

        foreach ($row as $item) {
            $online_status = null;
            $medicine_data = ['upc' => trim($item['条形码'])];
            if (!empty(trim($item['线上销售价格']))) {
                $medicine_data['price'] = trim($item['线上销售价格']);
            }
            if (!empty(trim($item['线下销售价格']))) {
                $medicine_data['down_price'] = trim($item['线下销售价格']);
            }
            if (!empty(trim($item['成本价格']))) {
                $medicine_data['guidance_price'] = trim($item['成本价格']);
            }
            if (!empty(trim($item['库存']))) {
                $medicine_data['stock'] = trim($item['库存']);
            }
            if (in_array(trim($item['售卖状态']), [0, 1])) {
                $online_status = trim($item['售卖状态']);
                if ($online_status == 0) {
                    $medicine_data['online_mt'] = 1;
                    $medicine_data['online_ele'] = 1;
                } else {
                    $medicine_data['online_mt'] = 0;
                    $medicine_data['online_ele'] = 0;
                }
            }
            if (!empty(trim($item['排序']))) {
                $medicine_data['sequence'] = trim($item['排序']);
            }
            MedicineUpdateImportJob::dispatch($this->shop_id, 0, $log->id, $online_status, $medicine_data)->onQueue('medicine');
        }
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function rules(): array
    {
        return [
            '商品名称' => 'required|string',
            '商品条码' => 'required|string',
            '销售价' => 'required|numeric',
            '成本价' => 'required|numeric',
        ];
    }
}
