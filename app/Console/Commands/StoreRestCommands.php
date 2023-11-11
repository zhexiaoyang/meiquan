<?php

namespace App\Console\Commands;

use App\Jobs\StoreRestJob;
use App\Models\Shop;
use Illuminate\Console\Command;

class StoreRestCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store-rest';

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
        $shops = Shop::whereHas("user", function ($query) {
            $query->select('id', 'operate_money')->where('operate_money', '<', 0);
        })->select('id','user_id','yunying_status')->where('yunying_status', 1)->get();

        if (!empty($shops)) {
            foreach ($shops as $shop) {
                StoreRestJob::dispatch($shop->id);
            }
        }
    }
}
