<?php

namespace ChuangLanSmsApi;

use ChuangLanSmsApi\exception\SmsException;

class Request
{
    /**
     * @var object 对象实例
     */
    protected static $instance = [];

    /**
     * @var array
     */
    private static $hostMap = [
        'inland' => 'smssh1.253.com',
        'abroad' => 'intapi.253.com',
    ];

    private static $config = [
        // 是否发送国际短信
        'is_abroad' => false,
        // 使用https
        'use_https' => false,
    ];

    /**
     * @var array
     */
    protected static $account = [];

    private static $jsonError = 'json解析失败';
    private static $dataError = '返回数据格式错误';

    private static $result;

    private static $urlMap = [
        // 发送国内短信（单条或批量）
        'sendMsg'            => '/msg/send/json',
        // 发送变量短信
        'sendVariableSend'   => '/msg/variable/json',
        // 发送国际短信
        'sendAbroadMsg'      => '/send/json',
        // 批量发送国际短信
        'sendBatchAbroadMsg' => '/send',
    ];

    /**
     * 实例化
     * @param       $account
     * @param       $password
     * @param array $config
     * @return Request
     */
    public static function instance($account, $password, $config = [])
    {
        $instanceKey = md5($account . $password . json_encode($config));
        if (is_null(self::$instance[$instanceKey])) {
            self::$instance[$instanceKey] = new static($account, $password, $config);
        }
        self::$config = array_merge(self::$config, $config);
        return self::$instance[$instanceKey];
    }

    private function __construct($account, $password, $config)
    {
        self::$account['account']  = $account;
        self::$account['password'] = $password;

        if ($config && is_array($config)) {
            self::$config = array_merge(self::$config, $config);
        }
    }

    /**
     * 获取配置
     * @param string $field
     * @return array|mixed
     */
    public function getConfig($field = '')
    {
        return $field ? self::$config[$field] : self::$config;
    }

    /**
     * 拼接地址
     * @param string $action
     * @return string
     */
    public function getApiUrl($action = 'sendMsg')
    {
        $scheme = self::$config['use_https'] ? 'https://' : 'http://';
        $host   = self::$config['is_abroad'] ? self::$hostMap['abroad'] : self::$hostMap['inland'];
        return $scheme . $host . self::$urlMap[$action];
    }

    /**
     * 普通短信,国际短信只有 $msg, $phone 参数有效
     * @param        $msg
     * @param        $phone
     * @param int    $uid
     * @param string $sendTime
     * @param bool   $report
     * @param int    $extend
     * @return bool
     * @throws SmsException
     */
    public function sendMsg($msg, $phone, $uid = 0, $sendTime = '', $report = false, $extend = 0)
    {
        $isBatch = is_array($phone) || strpos($phone, ',');
        if ($this->getConfig('is_abroad')) { //海外
            $params = [
                'msg'    => $msg,
                'mobile' => $phone,
            ];
            $action = $isBatch ? 'sendBatchAbroadMsg' : 'sendAbroadMsg';
        } else {
            $params = [
                'msg'   => $msg,
                'phone' => is_array($phone) ? implode(',', $phone) : $phone,
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
            $action = 'sendMsg';
        }
        $url = $this->getApiUrl($action);
        return $this->curlPost($url, $params);
    }

    /**
     * 变量短信   不支持国外短信
     * @param        $msg
     * @param        $params
     * @param int    $uid
     * @param string $sendTime
     * @param bool   $report
     * @param int    $extend
     * @return bool
     * @throws SmsException
     */
    public function sendVariableSend($msg, $params, $uid = 0, $sendTime = '', $report = false, $extend = 0)
    {
        $params = [
            'msg'    => $msg,
            'params' => is_array($params) ? implode(';', $params) : $params,
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
     * @param       $url
     * @param array $postFields
     * @return bool
     * @throws SmsException
     */
    private function curlPost($url, $postFields = [])
    {
        $postFields = array_merge(self::$account, $postFields);
        $postFields = json_encode($postFields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $ret = curl_exec($ch);
        if ($ret !== false) {
            $result = json_decode($ret, true);
            if (is_null($result)) {
                throw new SmsException(self::$jsonError);
            }
            $code = $result['code'] ?? false;
            if ($code === false) {
                throw new SmsException(self::$dataError);
            }
            if ($code != 0) {
                throw new SmsException($ret);
            }
            self::$result = $result;
            return true;
        }
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            throw new SmsException($error);
        }
    }

    public function getResult()
    {
        return self::$result;
    }
}
