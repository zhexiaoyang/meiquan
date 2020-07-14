<?php


namespace App\Libraries\DingTalk;


use GuzzleHttp\Client;

class DingTalkRobotNotice
{
    /**
     * @var string
     */
    public $accessToken;
    /**
     * @var string
     */
    public $apiUrl = 'https://oapi.dingtalk.com/robot/send';
    /**
     * @var array
     */
    public $guzzleOptions = [];
    /**
     * @var array
     */
    public $msgTypeList = ['text', 'link', 'markdown', 'actionCard', 'feedCard'];

    /**
     * DingTalkRobotNotice constructor.
     * @param $accessToken
     * @throws \Exception
     */
    public function __construct($accessToken)
    {
        if (!$accessToken) {
            throw new \Exception('The "accessToken" property must be set.');
        }
        $this->accessToken = $accessToken;
    }

    /**
     * @return Client
     */
    public function getHttpClient()
    {
        return new Client($this->guzzleOptions);
    }

    /**
     * @param array $options
     */
    public function setGuzzleOptions(array $options)
    {
        $this->guzzleOptions = $options;
    }

    /**
     * 发送文本消息
     * @param $content
     * @param array $atMobiles
     * @param bool $isAtAll
     * @return bool|string
     * @throws \Exception
     */
    public function sendTextMsg($content, array $atMobiles = [], $isAtAll = false)
    {
        $query = [
            'msgtype' => 'text',
            'text' => [
                'content' => $content,
            ],
            'at' => [
                'isAtAll' => $isAtAll
            ],
        ];
        if (is_array($atMobiles) && count($atMobiles) > 0) {
            $query['at']['atMobiles'] = $atMobiles;
        }
        return $this->sendMsg($query);
    }

    /**
     * 发送链接
     * @param $title
     * @param $text
     * @param string $picUrl
     * @param $messageUrl
     * @return bool|string
     * @throws \Exception
     */
    public function sendLinkMsg($title, $text, $picUrl = '', $messageUrl)
    {
        $query = [
            'msgtype' => 'link',
            'link' => [
                'title' => $title,
                'text' => $text,
                'picUrl' => $picUrl,
                'messageUrl' => $messageUrl
            ],
        ];
        return $this->sendMsg($query);
    }

    /**
     * 发送MarkDown 消息
     * @param $title
     * @param $content
     * @param array $atMobiles
     * @param bool $isAtAll
     * @return bool|string
     * @throws \Exception
     */
    public function sendMarkdownMsg($title, $content, array $atMobiles = [], $isAtAll = false)
    {
        $query = [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $title,
                'text' => $content,
            ],
            'at' => [
                'isAtAll' => $isAtAll
            ],
        ];
        if (is_array($atMobiles) && count($atMobiles) > 0) {
            $query['at']['atMobiles'] = $atMobiles;
        }
        return $this->sendMsg($query);
    }

    public function sendMarkdownMsgArray($title, $content_arr = [], array $atMobiles = [], $isAtAll = false)
    {
        $content = $title;

        if (!empty($content_arr)) {
            foreach ($content_arr as $k => $v) {
                $content .= '。' . $k . '：' .$v;
            }
        }

        $query = [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $title,
                'text' => $content,
            ],
            'at' => [
                'isAtAll' => $isAtAll
            ],
        ];
        if (is_array($atMobiles) && count($atMobiles) > 0) {
            $query['at']['atMobiles'] = $atMobiles;
        }
        return $this->sendMsg($query);
    }

    /**
     * 整体跳转ActionCard类型
     * @param $title
     * @param $content
     * @param $singleURL
     * @param int $hideAvatar
     * @param int $btnOrientation
     * @param string $singleTitle
     * @return mixed
     * @throws \Exception
     */
    public function sendActionCardMsg($title, $content, $singleURL, $hideAvatar = 0, $btnOrientation = 0, $singleTitle = '阅读原文')
    {
        $query = [
            'msgtype' => 'actionCard',
            'actionCard' => [
                'title' => $title,
                'text' => $content,
                'hideAvatar' => $hideAvatar,
                'btnOrientation' => $btnOrientation,
                'singleTitle' => $singleTitle,
                'singleURL' => $singleURL
            ],
        ];
        return $this->sendMsg($query);
    }

    /**
     * 独立跳转ActionCard类型
     * @param $title
     * @param $content
     * @param int $hideAvatar
     * @param int $btnOrientation
     * @param array $btns
     * @return mixed
     * @throws \Exception
     */
    public function sendSingleActionCardMsg($title, $content, $hideAvatar = 0, $btnOrientation = 0, array $btns = [])
    {
        $query = [
            'msgtype' => 'actionCard',
            'actionCard' => [
                'title' => $title,
                'text' => $content,
                'hideAvatar' => $hideAvatar,
                'btnOrientation' => $btnOrientation,
                'btns' => $btns
            ],
        ];
        return $this->sendMsg($query);
    }

    /**
     * FeedCard类型
     * @param array $links
     * @return mixed
     * @throws \Exception
     */
    public function sendFeedCardMsg(array $links = [])
    {
        if (!\is_array($links)) {
            throw new \Exception('this data must be array');
        }
        if (count($links) == count($links, 1)) {
            throw new \Exception('this data must be dyadic array');
        }
        $query = [
            'msgtype' => 'feedCard',
            'feedCard' => [
                'links' => $links
            ],
        ];
        return $this->sendMsg($query);
    }

    /**
     * @param array $msgData
     * @return bool|string
     * @throws \Exception
     */
    public function sendMsg(array $msgData = [])
    {
        try {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl  . "?access_token=" . $this->accessToken);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array ('Content-Type: application/json;charset=utf-8'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($msgData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
            // curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
            // curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
            $data = curl_exec($ch);
            curl_close($ch);
            return $data;


        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
}