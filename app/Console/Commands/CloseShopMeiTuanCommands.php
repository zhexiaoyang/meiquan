<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CloseShopMeiTuanCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'close-shop-meituan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $shops = [
            [
                'mtid' => '7500629',
                'type' => 31
            ],
            [
                'mtid' => '8903095',
                'type' => 4
            ],
            [
                'mtid' => '9512164',
                'type' => 4
            ],
            [
                'mtid' => '9634484',
                'type' => 4
            ],
            [
                'mtid' => '10088128',
                'type' => 4
            ],
            [
                'mtid' => '10135017',
                'type' => 4
            ],
            [
                'mtid' => '10693444',
                'type' => 4
            ],
            [
                'mtid' => '10886903',
                'type' => 4
            ],
            [
                'mtid' => '11626215',
                'type' => 4
            ],
            [
                'mtid' => '11781797',
                'type' => 4
            ],
            [
                'mtid' => '9869351',
                'type' => 4
            ],
            [
                'mtid' => '9650425',
                'type' => 4
            ],
            [
                'mtid' => '10637831',
                'type' => 4
            ],
        ];
        $minkang = app('minkang');
        $shangou = app('meiquan');
        foreach ($shops as $shop) {
            if ($shop['type'] === 4) {
                $rest_shop = $minkang->shopClose($shop['mtid'], false);
            } else {
                $rest_shop = $shangou->shopClose($shop['mtid'], true);
            }
            \Log::info('1分钟置休返回结果：' . json_encode($rest_shop, JSON_UNESCAPED_UNICODE));
        }
    }
}
