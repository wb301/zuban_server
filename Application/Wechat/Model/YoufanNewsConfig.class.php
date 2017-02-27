<?php
/**
 * Created by PhpStorm.
 * User: Aaron
 * Date: 5/9/16
 * Time: 2:18 PM
 */

namespace Wechat\Model;


class YoufanNewsConfig extends WxPayConfig
{
    public function __construct()
    {
        $this->config = array(
            'MCHID' => '1345361901',
            'KEY' => '6034e6f15423f86507eg794cf7b0519e',
            'APPID' => 'wxa320b84bf762d6e4',
            'APPSECRET' => 'ce225e4db87c20dbcb619e594624f8c5',
            'SSLCERT_PATH' => APP_PATH.'Wechat/Conf/app/apiclient_cert.pem',
            'SSLKEY_PATH' => APP_PATH.'Wechat/Conf/app/apiclient_key.pem',
            'CURL_PROXY_HOST' => '0.0.0.0',
            'CURL_PROXY_PORT' => 0,
            'REPORT_LEVENL' => 0,
            'NOTIFY_URL' => C('MERCHANT_API_HOST').'/wechat/news/notify',
        );
    }
}