<?php

namespace Wechat\Controller;
use Common\Controller\PlatformCommonController;
use Platform\Controller\PaymentController;
use Platform\Model\OrderModel;
use Wechat\Model\WxAppBusinessConfig;
use Wechat\Model\WxPayDataBase;
use Wechat\Model\WxPayUnifiedOrder;
use Think\App;
use Think\Controller;
use Think\Log;
use Wechat\Model\WxPayApi;
use Wechat\Model\YoufanBusinessConfig;
use Wechat\Model\YoufanBusinessNotify;
use Wechat\Model\YoufanDaigouConfig;
use Wechat\Model\YoufanDaigouNotify;
use Wechat\Model\YoufanMerchantChargeConfig;

class DaigouController extends PaymentController
{

    public function preOrder()
    {
        $isWx = 1;
        $para = $this->checkParameters($isWx);
        $config = new YoufanDaigouConfig();
        try {
            $out_trade_no = $para['order_no'];
            $total_fee = $para['total_fee'];

            //判断该订单是否在系统里面有
            $order = OrderModel::infoByPayOrderNumber($out_trade_no);
            if(empty($order)) {
                throw new Exception('该订单有异常');
            }

            $returnPage = $config->getSuccessUrl();
            //主要是检查这个订单如果已经支付成功了，就不需要再支付了，直接跳转支付成功页面
            $needPay = $this->checkOrderNeedPay($out_trade_no);
            Log::record('need pay it :'.($needPay? 1: 0),Log::DEBUG,true);

            if(!$needPay) {
                throw new Exception('该订单已经支付过了');
            }

            //主要是检查微信认证的code是否已经存在，如果存在的话说明用户又提交了一次支付请求，直接跳转支付成功页面
            $codeExist = $this->checkWxCodeExist($out_trade_no);
            if($codeExist) {
                //如果用户再一次会到提交支付页面，我们直接让他到订单列表页面
                throw new Exception('微信认证code已经存在');
            }
        }catch (Exception $e) {
            $this->returnErrorNotice($e->getMessage());
        }

        $out_trade_no = $para['order_no'];
        $total_fee = $para['total_fee'];


        $from = 'youfandaigou';
        $notify_url = $config->getNotifyUrl();
        $input = new WxPayUnifiedOrder();
        $input->SetDevice_info('WEB');
        $input->SetBody($out_trade_no);
//        $input->SetDetail('');
        $input->SetAttach($from);
        $input->SetOut_trade_no($out_trade_no);
//        $input->SetFee_type('CNY');
        $input->SetTotal_fee($total_fee);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
//        $input->SetGoods_tag(""); //商品标记,不是必须
        $input->SetNotify_url($notify_url); //通知地址, 必须填写
        $input->SetTrade_type("APP"); //交易类型, 必须填写
        $input->config = $config;
        $order = WxPayApi::unifiedOrder($input);
        $timeStamp = time();
        if(!empty($order)) {
            $appId = $order['appid'];
            $partnerId = $order['mch_id'];
            $prepayId = $order['prepay_id'];
            $nonceStr = $order['nonce_str'];
            //文档比较坑，文档上面是大写的参数，但是这里应该写成小写
            $xml = "<xml><appid><![CDATA[$appId]]></appid><partnerid><![CDATA[$partnerId]]></partnerid><prepayid><![CDATA[$prepayId]]></prepayid><noncestr><![CDATA[$nonceStr]]></noncestr><timestamp><![CDATA[$timeStamp]]></timestamp><package><![CDATA[Sign=WXPay]]></package></xml>";
            Log::record($xml,Log::DEBUG,true);
            $data = new WxPayDataBase();
            $data->setConfig($config);
            $data->FromXml($xml);
            $data->SetSign();
            $sign = $data->GetSign();
            $order['sign'] = $sign;
            $order['timeStamp'] = $timeStamp;
        }else {
            $order = array();
            Log::record('get prepay order failed',Log::DEBUG,true);
        }

        $this->returnSuccess($order);
    }

    public function notify()
    {
        Log::record('-------begin wx app youfan daigou notify-----',Log::INFO,true);
        $notify = new YoufanDaigouNotify();
        $config = new YoufanDaigouConfig();
        $notify->Handle(false,$config);
        Log::record('-------end wx app youfan daigou notify-----',Log::INFO,true);
    }
}