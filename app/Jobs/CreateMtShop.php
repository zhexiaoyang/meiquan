<?php

namespace App\Jobs;

use App\Models\Shop;
use function GuzzleHttp\Psr7\str;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateMtShop implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $shop;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->shop->shop_id) {
            $meituan = app("meituan");
            $result = $meituan->shopCreate($this->shop);
            if (isset($result['code']) && $result['code'] == 0) {
                if ($this->shop->status < 10) {
                    $this->shop->save();
                }
            }
        }

        if (!$this->shop->shop_id_fn) {
            $fengniao = app("fengniao");
            $result = $fengniao->createShop($this->shop);
            if (isset($result['code']) && $result['code'] == 200) {
                if ($this->shop->status < 10) {
                }
            }
        }

        if (!$this->shop->shop_id_ss) {
            $shansong = app("shansong");
            $result = $shansong->createShop($this->shop);
            if (isset($result['status']) && $result['status'] == 200) {
                if ($this->shop->status < 40) {
                    $this->shop->status = 40;
                }
                $this->shop->shop_id_ss = $result['data'];
                $this->shop->save();
            }
        }

        if (!$this->shop->shop_id_dd) {
            $dada = app("dada");
            $result = $dada->createShop($this->shop);
            if (isset($result['code']) && $result['code'] == 0) {
                if ($this->shop->status < 40) {
                    $this->shop->status = 40;
                }
                $this->shop->shop_id_dd = $this->shop->id;
                $this->shop->save();
            }
        }

        if (!$this->shop->shop_id_uu) {
            $city = $this->shop->city;
            $data = ["阿坝藏族羌族自治州","阿拉善盟","阿勒泰地区","安康市","安庆市","安阳市","鞍山市","巴中市","白城市","蚌埠市","包头市",
                "宝鸡市","保定市","保山市","北海市","北京市","本溪市","滨州市","亳州市","博尔塔拉蒙古自治州","沧州市","昌吉回族自治州",
                "长沙市","长治市","常德市","常州市","朝阳市","郴州市","成都市","承德市","赤峰市","滁州市","楚雄彝族自治州","达州市",
                "大理白族自治州","大连市","大庆市","大同市","丹东市","德州市","东莞市","东营市","鄂尔多斯市","恩施土家族苗族自治州",
                "防城港市","佛山市","阜阳市","赣州市","广安市","贵阳市","桂林市","哈密市","海口市","邯郸市","汉中市","杭州市","合肥市",
                "和田市","河源市","菏泽市","鹤壁市","衡水市","衡阳市","红河哈尼族彝族自治州","呼和浩特市","怀化市","淮安市","淮北市","淮南市",
                "黄冈市","黄山市","惠州市","吉安市","济南市","济宁市","济源市","嘉兴市","江门市","焦作市","揭阳市","金华市","晋城市","荆州市",
                "景德镇市","九江市","酒泉市","喀什市","开封市","克拉玛依市","巴音郭楞蒙古自治州","伊犁哈萨克自治州","昆明市","廊坊市","乐山市",
                "丽江市","连云港市","凉山彝族自治州","聊城市","临沧市","临汾市","临沂市","柳州市","六安市","六盘水市","龙岩市","娄底市",
                "泸州市","洛阳市","茂名市","梅州市","绵阳市","内江市","南昌市","南充市","南京市","南宁市","南阳市","宁德市","攀枝花市",
                "盘锦市","平顶山市","萍乡市","莆田市","濮阳市","黔东南苗族侗族自治州","钦州市","秦皇岛市","青岛市","清远市","庆阳市","曲靖市",
                "泉州市","日照市","三门峡市","三亚市","汕头市","汕尾市","商丘市","上饶市","邵阳市","深圳市","十堰市","石河子市","石家庄市",
                "石嘴山市","普洱市","宿迁市","宿州市","随州市","遂宁市","塔城地区","太原市","泰州市","唐山市","天津市","天水市","铜川市",
                "铜仁市","吐鲁番市","潍坊市","渭南市","温州市","文山壮族苗族自治州","乌鲁木齐市","吴忠市","芜湖市","五家渠市","武汉市",
                "西安市","西宁市","西双版纳傣族自治州","锡林郭勒盟","厦门市","仙桃市","咸宁市","咸阳市","湘潭市","襄阳市","孝感市","新乡市",
                "新余市","信阳市","邢台市","徐州市","许昌市","延安市","延边朝鲜族自治州","盐城市","扬州市","阳江市","伊宁市","宜宾市",
                "宜春市","益阳市","银川市","营口市","永州市","榆林市","玉林市","玉溪市","岳阳市","云浮市","运城市","枣庄市","湛江市",
                "张家界市","张家口市","张掖市","彰化县","漳州市","昭通市","郑州市","中山市","中卫市","周口市","株洲市","驻马店市","淄博市",
                "自贡市","固始县","漯河市","遵义市","三沙市","红原县","黑水县","罗定市","盱眙县","夏津县","任丘市","沭阳县","鄱阳县","韩城市",
                "瑞金市","瓦房店市","建水县","奎屯市","安溪县","弥勒市","瑞昌市","老河口市","睢宁县","耒阳市","瑞丽市","麻城市","都匀市",
                "滕州市","仙游县","霍邱县","莒县","天长市","敦煌市"];
            if (in_array($city, $data)) {
                $this->shop->shop_id_uu = $this->shop->id;
                $this->shop->save();
            }
        }

        if (!$this->shop->shop_id_sf) {
            $code = intval($this->shop->citycode);
            $city_codes = [23,28,29,431,432,451,755,760,769,459,379,354,575,571,371,21,393,719,731,536,533,535,512,516,917,20];
            if (in_array($code, $city_codes)) {
                $this->shop->shop_id_sf = $this->shop->id;
                $this->shop->save();
            }
        }
    }
}
