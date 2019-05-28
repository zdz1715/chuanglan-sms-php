<?php
namespace ChuangLanSmsApi;

class Request
{
    /**
     * @var object 对象实例
     */
    protected static $instance;

    private static $host = 'http://smssh1.253.com';

    /**
     * @var 参数
     */
    protected static $account = [];

    private static $error;

    private static $result;

    private static $url = [
        'sendMsg'   => '/msg/send/json',
        // 发送变量短信
        'sendVariableSend'   => '/msg/variable/json'
    ];

    /**
     *  实例化
     * @param $account
     * @param $password
     * @param $host
     * @return Request|object
     */
    public static function instance($account, $password, $host = '')
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($account, $password, $host);
        }
        return self::$instance;
    }

    private function __construct($account, $password, $host = '') {
        self::$account['account'] = $account;
        self::$account['password'] = $password;
        $host && self::$host = $host;
    }

    /**
     * @param $msg
     * @param $params
     * @param int $uid
     * @param string $sendTime
     * @param bool $report
     * @param int $extend
     * @return bool
     */
    public function sendVariableSend($msg, $params, $uid = 0, $sendTime = '', $report = false, $extend = 0) {
        $params = [
            'msg'       => $msg,
            'params'     => is_array($params) ? implode(',', $params) : $params
        ];
        if ($sendTime) {
            $params['sendtime'] = $sendTime;
        }
        if ($report) {
            $params['report'] = $report;
        }
        if ($uid) {
            $params['uid'] = $uid;
        }
        if ($extend) {
            $params['extend'] = $extend;
        }


        $url = $this->getUrl('sendVariableSend');
        return $this->curlPost($url, $params);
    }

    /**
     *  发送短信
     * @param $msg
     * @param $phone
     * @param int $uid
     * @param string $sendTime
     * @param bool $report
     * @param int $extend
     * @return bool
     */
    public function sendMsg($msg, $phone, $uid = 0, $sendTime = '', $report = false, $extend = 0) {
        $params = [
            'msg'       => $msg,
            'phone'     => is_array($phone) ? implode(',', $phone) : $phone
        ];
        if ($sendTime) {
            $params['sendtime'] = $sendTime;
        }
        if ($report) {
            $params['report'] = $report;
        }
        if ($uid) {
            $params['uid'] = $uid;
        }
        if ($extend) {
            $params['extend'] = $extend;
        }


        $url = $this->getUrl();
        return $this->curlPost($url, $params);
    }

    private function getUrl($type = 'senMsg') {
        return self::$host . (self::$url[$type] ?? self::$url['sendMsg']);
    }

    private function curlPost($url, $postFields = []) {
        self::$error = '';

        $postFields = array_merge(self::$account, $postFields);

        $postFields = json_encode($postFields);

        $ch = curl_init ();
        curl_setopt( $ch, CURLOPT_URL, $url);
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8'
            )
        );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt( $ch, CURLOPT_TIMEOUT,1);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);

        $ret = curl_exec ( $ch );
        if ($ret === false) {
            self::$error = curl_error($ch);
        } else {
            $rsp  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($rsp != 200) {
                self::$error = curl_error($ch);
            } else {
                self::$result = json_decode($ret, true);
            }
        }
        curl_close($ch);
        if (self::$error) {
            return false;
        }
        return true;
    }

    public function getError() {
        return self::$error;
    }

    public function getResult() {
        return self::$result;
    }
}