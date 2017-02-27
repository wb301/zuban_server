<?php
/**
 * Created by PhpStorm.
 * User: Aaron
 * Date: 5/9/16
 * Time: 2:18 PM
 */

namespace Wechat\Model;


use Platform\Model\OrderModel;
use Platform\Model\UserModel;

class YoufanWapConfig extends WxPayConfig
{
    public function __construct()
    {
        $this->config = array(
            'MCHID' => C('WX_MCHID'),
            'KEY' => C('WX_KEY'),
            'APPID' => C('WX_APPID'),
            'APPSECRET' => C('WX_APPSECRET'),
            'SSLCERT_PATH' => APP_PATH.'Wechat/Conf/'.C('WX_SSLCERT_PATH'),
            'SSLKEY_PATH' => APP_PATH.'Wechat/Conf/'.C('WX_SSLKEY_PATH'),
            'CURL_PROXY_HOST' => '0.0.0.0',
            'CURL_PROXY_PORT' => 0,
            'REPORT_LEVENL' => 0,
            'NOTIFY_URL' => C('MERCHANT_API_HOST').'/wechat/index/notify',
        );
    }

    public function getSuccessUrl($orderNo=null)
    {
        $userId = '';
        $token = '';
        if($orderNo) {
            $order = OrderModel::infoByPayOrderNumber($orderNo);
            if(!empty($order)) {
                $userId = $order['user_id'];
                $user = UserModel::info($userId);
                if(!empty($user)) {
                    $token = $user['token'];
                }
            }
            $url = MERCHANT_HOST.'/wap-shop/order-list.html?order_type=99&order_no='.$orderNo.'&userID='.$userId.'&unico_token='.$token;
        }else {
            $url = MERCHANT_HOST.'/wap-shop/order-list.html?order_type=99';
        }

        return $url;
    }

    public function getCancelUrl()
    {
        $url = MERCHANT_HOST.'/wap-shop/order-list.html?order_type=0';
        return $url;
    }

    public function getNotifyUrl()
    {
        return $this->config['NOTIFY_URL'];
    }
}