<?php

namespace App\Console\Commands;

use App\Models\MeituanShangouToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckMeiTuanShanGouTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check-meituan-shangou-tokens';

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
        $tokens = MeituanShangouToken::where('id', '>', 277)->get();
        if (!empty($tokens)) {
            $meituan = app('meiquan');
            foreach ($tokens as $token) {
                $this->info(json_encode($token, true));
                $shop_id = $token->shop_id;
                $key = 'mtwm:shop:auth:' . $shop_id;
                $key_ref = 'mtwm:shop:auth:ref:' . $shop_id;
                $res = $meituan->waimaiAuthorizeRef($token->refresh_token);
                $this->info('返回：：' . json_encode($res, true));
                if (isset($res['status']) && $res['status'] == 0) {
                    $access_token = $res['access_token'];
                    $refresh_token = $res['refresh_token'];
                    $expires_in = $res['expires_in'];
                    Cache::put($key, $access_token, 2505600);
                    Cache::put($key_ref, $refresh_token, 7689600);
                    MeituanShangouToken::where('shop_id', $shop_id)->update([
                        'access_token' => $res['access_token'],
                        'refresh_token' => $res['refresh_token'],
                        'expires_at' => date("Y-m-d H:i:s", time() + $expires_in),
                        'expires_in' => $expires_in,
                    ]);
                    $this->info('---成功');
                } else {
                    $this->info('---失败');
                }
                // sleep(5);
            }
        }
    }
}
