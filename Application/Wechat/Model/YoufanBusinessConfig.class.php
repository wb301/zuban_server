<?php
/**
 * Created by PhpStorm.
 * User: Aaron
 * Date: 5/9/16
 * Time: 2:18 PM
 */

namespace Wechat\Model;


class YoufanBusinessConfig extends WxPayConfig
{
    public function __construct()
    {
        $this->config = array(
            'MCHID' => '1339013901',
            'KEY' => '7022e7f12343f87507gf784cf6b0419f',
            'APPID' => 'wx0d24ad047114a5e9',
            'APPSECRET' => '7096f6547f896f379a749dd4e2338328',
            'SSLCERT_PATH' => APP_PATH.'Wechat/Conf/business/apiclient_cert.pem',
            'SSLKEY_PATH' => APP_PATH.'Wechat/Conf/business/apiclient_key.pem',
            'CURL_PROXY_HOST' => '0.0.0.0',
            'CURL_PROXY_PORT' => 0,
            'REPORT_LEVENL' => 0,
            'NOTIFY_URL' => C('MERCHANT_API_HOST').'/wechat/business/notify',
        );
    }
}