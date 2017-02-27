<?php
/**
 * Created by PhpStorm.
 * User: Aaron
 * Date: 5/9/16
 * Time: 2:18 PM
 */

namespace Wechat\Model;


class YoufanMerchantChargeConfig extends YoufanWapConfig
{
    public function __construct()
    {
        parent::__construct();
        $this->setNotifyUrl(C('MERCHANT_API_HOST').'/wechat/index/merchantChargeNotify');
    }

    public function getSuccessUrl($orderNo='')
    {
        $url = MERCHANT_HOST.'/wap-shop/pay-success.html?order_no='.$orderNo;
        return $url;
    }

    public function getCancelUrl()
    {
        $url = MERCHANT_HOST.'/wap-shop/pay-cashier.html?';
        return $url;
    }
}