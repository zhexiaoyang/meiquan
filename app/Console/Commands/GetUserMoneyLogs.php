<?php

namespace App\Console\Commands;

use App\Models\UserMoneyBalance;
use Illuminate\Console\Command;

class GetUserMoneyLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get-user-money-log';

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
        $start_date = '2021-11-25';
        // $date_arr = [];
        // array_push($date_arr, $start_date);
        // while (strtotime($start_date) < strtotime(date("Y-m-d"))) {
        //     $start_date = date("Y-m-d", strtotime($start_date) + 86400);
        //     array_push($date_arr, $start_date);
        // }

        $stime = date("Y-m-d", strtotime($start_date) + 86400);
        $etime = date("Y-m-d", strtotime($start_date) + 86400 * 2);
        $logs = UserMoneyBalance::query()
            ->where('created_at', '>=', $stime)
            ->where('created_at', '<', $etime)
            ->groupBy('user_id')->get();
        \Log::info("aa", $logs);
    }
}
