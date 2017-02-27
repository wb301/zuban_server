<?php

namespace Wechat\Controller;
use Wechat\Model\WxAppNewsConfig;
use Think\App;
use Think\Controller;
use Think\Log;
use Wechat\Model\YoufanNewsConfig;
use Wechat\Model\YoufanNewsNotify;


class NewsController extends PlatformCommonController
{

    public function preOrder()
    {

        $out_trade_no = $_REQUEST['order_no'] ? $_REQUEST['order_no'] : '';
        if(!$out_trade_no) {
            $this->returnErrorNotice('参数order_no不能为空');
        }
        $body = $_REQUEST['body'] ? $_REQUEST['body'] : $out_trade_no;
        if(!$body) {
            $this->returnErrorNotice('参数body不能为空');
        }
        $total_fee = $_REQUEST['total_fee'] ? $_REQUEST['total_fee'] : 0;
        if(!$total_fee) {
            $this->returnErrorNotice('参数total_fee不能为0');
        }

        $from = 'youfannews';
        $config = new YoufanNewsConfig();
        $notify_url = $config->getNotifyUrl();
        $input = new WxPayUnifiedOrder();
        $input->SetDevice_info('WEB');
        $input->SetBody($body);
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
        Log::record('-------begin wx app youfan news notify-----',Log::INFO,true);
        $notify = new YoufanNewsNotify();
        $config = new YoufanNewsConfig();
        $notify->Handle(false,$config);
        Log::record('-------end wx app youfan news notify-----',Log::INFO,true);
    }

    public function test()
    {
        $key =C('YOUFAN_PAY_ORDER_URL_KEY');
        $url = C('YOUFAN_PAY_ORDER_URL');
        $para = array(
            'orderId'=>'14667561331000568539',
            'price' => 13,
            'payOrder' => '4005782001201606247810321961',
            'key' => $key,
            'from' => 'WX'
        );
        //todo::有范订单处理
        Log::record('api url:'.$url,Log::INFO,true);
        Log::record('api data:'.json_encode($para),Log::INFO,true);
        $url = $url.'&orderId=14667561331000568539'.'&price=13'.'&payOrder=4005782001201606247810321961'.'&key='.$key.'&from=WX';

        $result = http($url,array());
        Log::record('process result:'.$result,Log::INFO,true);

    }
}