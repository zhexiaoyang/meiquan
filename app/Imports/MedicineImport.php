<?php

namespace App\Imports;

use App\Exceptions\InvalidRequestException;
use App\Jobs\MedicineImportJob;
use App\Models\Medicine;
use App\Models\MedicineDepot;
use App\Models\MedicineSyncLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MedicineImport implements ToCollection, WithHeadingRow, WithValidation, WithCalculatedFormulas
{
    public $shop_id;

    public function collection(Collection $row)
    {
        $row = $row->toArray();
        // 第一行是说明，去掉
        array_shift($row);
        // throw new InvalidRequestException($row[0]['条形码'], 422);
        if (count($row) > 5000) {
            throw new InvalidRequestException('药品数量不能超过5000', 422);
        }
        if (count($row) < 1) {
            throw new InvalidRequestException('药品数量为空', 422);
        }

        $chongfu = [];
        $chongfu_store_id = [];

        foreach ($row as $k => $item) {
            // 行数+3
            $line = $k + 3;
            if (isset($chongfu_store_id[$item['商家商品编码']])) {
                throw new InvalidRequestException("第{$line}行商家商品编码与第{$chongfu[$item['商家商品编码']]}行商家商品编码重复", 422);
            }
            if (!isset($item['条形码'])) {
                throw new InvalidRequestException("第{$line}行不存在条形码", 422);
            }
            // if (!empty(trim($item['商家商品编码']))) {
            //     if ($m = Medicine::select('store_id', 'upc')->where('store_id', trim($item['商家商品编码']))->first()) {
            //         if ($m->upc != trim($item['商家商品编码'])) {
            //             throw new InvalidRequestException("第{$line}行商家商品编码已存在，绑定商品条码：" . $m->upc, 422);
            //         }
            //     }
            // }
            if (isset($chongfu[$item['条形码']])) {
                throw new InvalidRequestException("第{$line}行条形码与第{$chongfu[$item['条形码']]}行条形码重复", 422);
            }
            if (empty($item['条形码'])) {
                throw new InvalidRequestException("第{$line}行条形码不能为空", 422);
            }
            if (!isset($item['线上销售价格'])) {
                throw new InvalidRequestException("第{$line}行不存在线上销售价格", 422);
            }
            if (!is_numeric(trim($item['线上销售价格']))) {
                throw new InvalidRequestException("第{$line}行线上销售价格，格式不正确", 422);
            }
            if ($item['线上销售价格'] < 0) {
                throw new InvalidRequestException("第{$line}行线上销售价格，不能小于0", 422);
            }
            if (!isset($item['成本价格'])) {
                throw new InvalidRequestException("第{$line}行不存在成本价格", 422);
            }
            if (!is_numeric(trim($item['成本价格']))) {
                throw new InvalidRequestException("第{$line}行成本价格，格式不正确", 422);
            }
            if ($item['成本价格'] < 0) {
                throw new InvalidRequestException("第{$line}行成本价格，不能小于0", 422);
            }
            if (!isset($item['库存'])) {
                throw new InvalidRequestException("第{$line}行不存在库存", 422);
            }
            if (!is_numeric(trim($item['库存']))) {
                throw new InvalidRequestException("第{$line}行库存格式不正确", 422);
            }
            $chongfu[$item['条形码']] = $line;
            // $chongfu[$item['商家商品编码']] = $line;
            if (!empty(trim($item['商家商品编码']))) {
                $chongfu[$item['商家商品编码']] = $line;
            }
        }
        // throw new InvalidRequestException("拦截拦截拦截", 422);

        // 添加日志
        $log = MedicineSyncLog::create([
            'shop_id' => $this->shop_id,
            'title' => '批量导入中台商品',
            'platform' => 0,
            'log_id' => uniqid(),
            'total' => count($row),
            'success' => 0,
            'fail' => 0,
            'error' => 0,
        ]);
        foreach ($row as $item) {
            // 线下售价-非必填
            $down_price = 0;
            if (isset($item['线下销售价格']) && is_numeric(trim($item['线下销售价格']))) {
                $down_price = trim($item['线下销售价格']);
            }
            // 排序-非必填
            $sequence = 1000;
            if (isset($item['排序']) && is_numeric(trim($item['排序'])) && trim($item['排序']) >= 0) {
                $sequence = trim($item['排序']);
            }

            $medicine_data = [
                'store_id' => trim($item['商家商品编码']),
                'name' => trim($item['商品名称']),
                'upc' => trim($item['条形码']),
                'stock' => trim($item['库存']),
                'price' => trim($item['线上销售价格']),
                'down_price' => $down_price,
                'guidance_price' => trim($item['成本价格']),
                'sequence' => $sequence,
            ];
            MedicineImportJob::dispatch($this->shop_id, $medicine_data, $log->id, $log->total)->onQueue('medicine');
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
            '条形码' => 'required|string',
            '销售价' => 'required|numeric',
            '成本价' => 'required|numeric',
        ];
    }
}
