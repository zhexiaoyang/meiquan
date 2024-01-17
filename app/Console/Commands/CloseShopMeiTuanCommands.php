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
            // 第二批
            [
                'mtid' => '11074951',
                'type' => 31
            ],
            [
                'mtid' => '5469242',
                'type' => 31
            ],
            [
                'mtid' => '8428252',
                'type' => 31
            ],
            [
                'mtid' => '7500765',
                'type' => 31
            ],
            [
                'mtid' => '7500613',
                'type' => 31
            ],
            [
                'mtid' => '7500625',
                'type' => 31
            ],
            [
                'mtid' => '5603642',
                'type' => 31
            ],
            [
                'mtid' => '9829336',
                'type' => 31
            ],
            [
                'mtid' => '8428259',
                'type' => 31
            ],
            [
                'mtid' => '5910547',
                'type' => 31
            ],
            [
                'mtid' => '8428285',
                'type' => 31
            ],
            [
                'mtid' => '9687539',
                'type' => 4
            ],
            [
                'mtid' => '8428255',
                'type' => 31
            ],
            [
                'mtid' => '10466830',
                'type' => 31
            ],
            [
                'mtid' => '9162827',
                'type' => 4
            ],
            [
                'mtid' => '6085877',
                'type' => 31
            ],
            [
                'mtid' => '9096058',
                'type' => 4
            ],
            [
                'mtid' => '8506812',
                'type' => 4
            ],
            [
                'mtid' => '8927015',
                'type' => 4
            ],
            [
                'mtid' => '8856828',
                'type' => 4
            ],
            [
                'mtid' => '9162829',
                'type' => 4
            ],
            [
                'mtid' => '8986460',
                'type' => 4
            ],
            [
                'mtid' => '8986452',
                'type' => 4
            ],
            [
                'mtid' => '8717680',
                'type' => 4
            ],
            [
                'mtid' => '9196733',
                'type' => 4
            ],
            [
                'mtid' => '8927387',
                'type' => 4
            ],
            [
                'mtid' => '9217338',
                'type' => 4
            ],
            [
                'mtid' => '8927389',
                'type' => 4
            ],
            [
                'mtid' => '9020733',
                'type' => 4
            ],
            [
                'mtid' => '8708754',
                'type' => 4
            ],
            [
                'mtid' => '9213398',
                'type' => 4
            ],
            [
                'mtid' => '9099345',
                'type' => 31
            ],
            [
                'mtid' => '8926890',
                'type' => 4
            ],
            [
                'mtid' => '8889292',
                'type' => 4
            ],
            [
                'mtid' => '9162835',
                'type' => 4
            ],
            [
                'mtid' => '9307485',
                'type' => 4
            ],
            [
                'mtid' => '8779245',
                'type' => 4
            ],
            [
                'mtid' => '8732810',
                'type' => 4
            ],
            [
                'mtid' => '9304470',
                'type' => 4
            ],
            [
                'mtid' => '8924567',
                'type' => 4
            ],
            [
                'mtid' => '9213397',
                'type' => 4
            ],
            [
                'mtid' => '8927020',
                'type' => 4
            ],
            [
                'mtid' => '9414343',
                'type' => 4
            ],
            [
                'mtid' => '9587196',
                'type' => 4
            ],
            [
                'mtid' => '9636548',
                'type' => 4
            ],
            [
                'mtid' => '9590465',
                'type' => 4
            ],
            [
                'mtid' => '10366090',
                'type' => 4
            ],
            [
                'mtid' => '9636127',
                'type' => 4
            ],
            [
                'mtid' => '9439070',
                'type' => 4
            ],
            [
                'mtid' => '9636125',
                'type' => 4
            ],
            [
                'mtid' => '9562398',
                'type' => 4
            ],
            [
                'mtid' => '9634482',
                'type' => 4
            ],
            [
                'mtid' => '9341297',
                'type' => 4
            ],
            [
                'mtid' => '9562381',
                'type' => 4
            ],
            [
                'mtid' => '9636132',
                'type' => 4
            ],
            [
                'mtid' => '9415044',
                'type' => 4
            ],
            [
                'mtid' => '9439019',
                'type' => 4
            ],
            [
                'mtid' => '9439071',
                'type' => 4
            ],
            [
                'mtid' => '9561920',
                'type' => 4
            ],
            [
                'mtid' => '9636134',
                'type' => 4
            ],
            [
                'mtid' => '9562746',
                'type' => 4
            ],
            [
                'mtid' => '9536469',
                'type' => 4
            ],
            [
                'mtid' => '9555485',
                'type' => 4
            ],
            [
                'mtid' => '9636126',
                'type' => 4
            ],
            [
                'mtid' => '9426474',
                'type' => 4
            ],
            [
                'mtid' => '9634483',
                'type' => 4
            ],
            [
                'mtid' => '9426693',
                'type' => 4
            ],
            [
                'mtid' => '9734712',
                'type' => 4
            ],
            [
                'mtid' => '9821984',
                'type' => 4
            ],
            [
                'mtid' => '9843075',
                'type' => 4
            ],
            [
                'mtid' => '9790332',
                'type' => 4
            ],
            [
                'mtid' => '9981136',
                'type' => 4
            ],
            [
                'mtid' => '10068722',
                'type' => 4
            ],
            [
                'mtid' => '10102658',
                'type' => 4
            ],
            [
                'mtid' => '10795912',
                'type' => 4
            ],
            [
                'mtid' => '10233735',
                'type' => 4
            ],
            [
                'mtid' => '11442853',
                'type' => 4
            ],
            [
                'mtid' => '10652322',
                'type' => 4
            ],
            [
                'mtid' => '11780280',
                'type' => 4
            ],
            [
                'mtid' => '10515199',
                'type' => 4
            ],
            [
                'mtid' => '10938430',
                'type' => 4
            ],
            [
                'mtid' => '10567622',
                'type' => 4
            ],
            [
                'mtid' => '10827453',
                'type' => 4
            ],
            [
                'mtid' => '10493928',
                'type' => 4
            ],
            [
                'mtid' => '11807059',
                'type' => 4
            ],
            [
                'mtid' => '10493925',
                'type' => 4
            ],
            [
                'mtid' => '10264497',
                'type' => 4
            ],
            [
                'mtid' => '10710641',
                'type' => 4
            ],
            [
                'mtid' => '10690066',
                'type' => 4
            ],
            [
                'mtid' => '10515204',
                'type' => 4
            ],
            [
                'mtid' => '10433974',
                'type' => 4
            ],
            [
                'mtid' => '11634472',
                'type' => 4
            ],
            [
                'mtid' => '10627490',
                'type' => 4
            ],
            [
                'mtid' => '10627424',
                'type' => 4
            ],
            [
                'mtid' => '11332220',
                'type' => 4
            ],
            [
                'mtid' => '11318611',
                'type' => 4
            ],
            [
                'mtid' => '11055383',
                'type' => 4
            ],
            [
                'mtid' => '10886903',
                'type' => 4
            ],
            [
                'mtid' => '10579270',
                'type' => 4
            ],
            [
                'mtid' => '11373646',
                'type' => 4
            ],
            [
                'mtid' => '11875908',
                'type' => 31
            ],
            [
                'mtid' => '10227553',
                'type' => 4
            ],
            [
                'mtid' => '10897448',
                'type' => 4
            ],
            [
                'mtid' => '10493927',
                'type' => 4
            ],
            [
                'mtid' => '11332679',
                'type' => 4
            ],
            [
                'mtid' => '10788009',
                'type' => 4
            ],
            [
                'mtid' => '11781797',
                'type' => 4
            ],
            [
                'mtid' => '10652324',
                'type' => 4
            ],
            [
                'mtid' => '11373641',
                'type' => 4
            ],
            [
                'mtid' => '10516285',
                'type' => 4
            ],
            [
                'mtid' => '11875913',
                'type' => 31
            ],
            [
                'mtid' => '11336583',
                'type' => 4
            ],
            [
                'mtid' => '11393330',
                'type' => 4
            ],
            [
                'mtid' => '11889581',
                'type' => 31
            ],
            [
                'mtid' => '11658779',
                'type' => 4
            ],
            [
                'mtid' => '10802954',
                'type' => 4
            ],
            [
                'mtid' => '11292157',
                'type' => 4
            ],
            [
                'mtid' => '11806930',
                'type' => 4
            ],
            [
                'mtid' => '10278887',
                'type' => 4
            ],
            [
                'mtid' => '10578534',
                'type' => 4
            ],
            [
                'mtid' => '9740590',
                'type' => 4
            ],
            [
                'mtid' => '9752910',
                'type' => 4
            ],
            [
                'mtid' => '9821984',
                'type' => 4
            ],
            [
                'mtid' => '9989924',
                'type' => 4
            ],
            [
                'mtid' => '11723551',
                'type' => 4
            ],
            [
                'mtid' => '10600809',
                'type' => 4
            ],
            [
                'mtid' => '10666965',
                'type' => 4
            ],
            [
                'mtid' => '10237783',
                'type' => 4
            ],
            [
                'mtid' => '11600693',
                'type' => 4
            ],
            [
                'mtid' => '10938427',
                'type' => 4
            ],
            [
                'mtid' => '11332223',
                'type' => 4
            ],
            [
                'mtid' => '11875100',
                'type' => 4
            ],
            [
                'mtid' => '10693444',
                'type' => 4
            ],
            [
                'mtid' => '10541209',
                'type' => 4
            ],
            [
                'mtid' => '10309492',
                'type' => 4
            ],
            [
                'mtid' => '9784892',
                'type' => 4
            ],
            [
                'mtid' => '9805729',
                'type' => 4
            ],
            [
                'mtid' => '10327466',
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
            \Log::info($shop['mtid'] . '|1分钟置休返回结果：' . json_encode($rest_shop, JSON_UNESCAPED_UNICODE));
        }
    }
}
