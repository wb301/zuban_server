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

class YoufanDaigouConfig extends YoufanWapConfig
{
    public function __construct()
    {
        parent::__construct();
        $this->setNotifyUrl(C('MERCHANT_API_HOST').'/wechat/daigou/notify');
    }

    public function getSuccessUrl($orderNo)
    {
        $userId = '';
        $token = '';
        $order = OrderModel::infoByPayOrderNumber($orderNo);
        if(!empty($order)) {
            $userId = $order['user_id'];
            $user = UserModel::info($userId);
            if(!empty($user)) {
                $token = $user['token'];
            }
        }
        $url = MERCHANT_HOST.'/wap-shop/order-list.html?order_type=99&order_no='.$orderNo.'&userID='.$userId.'&unico_token='.$token;
        return $url;
    }

    public function getCancelUrl()
    {
        $url = MERCHANT_HOST.'/wap-shop/order-list.html?order_type=99';
        return $url;
    }
}