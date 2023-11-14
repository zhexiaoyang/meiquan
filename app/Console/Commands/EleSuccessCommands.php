<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EleSuccessCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ele-success';

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
        $shop_ids = ['1101804118', '1151134404', '1173994056', '200000136542', '1100877403', '506118437'];
        $ele = app('ele');
        foreach ($shop_ids as $shop_id) {
            for ($i = 1; $i <= 100; $i++) {
                $res = $ele->getSkuList($shop_id, $i, 10);
                if (!empty($res['body']['data']['list']) && is_array($res['body']['data']['list'])) {
                    $this->info(count($res['body']['data']['list']));
                } else {
                    break;
                }
            }
        }
    }
}
