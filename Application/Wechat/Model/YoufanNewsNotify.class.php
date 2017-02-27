<?php

namespace Wechat\Model;
use Platform\Model\OrderModel;
use Think\Log;

/**
 * 
 * 有范资讯微信支付回调基础类
 * @author jiyongcheng
 *
 */
class YoufanNewsNotify extends WxPayNotify
{
	
	/**
	 * 
	 * 回调方法入口，子类可重写该方法
	 * 注意：
	 * 1、微信回调超时时间为2s，建议用户使用异步处理流程，确认成功之后立刻回复微信服务器
	 * 2、微信服务器在调用失败或者接到回包为非确认包的时候，会发起重试，需确保你的回调是可以重入
	 * @param array $data 回调解释出的参数
	 * @param string $msg 如果回调处理失败，可以将错误信息输出到该方法
	 * @return true回调出来完成不需要继续回调，false回调处理未完成需要继续回调
	 */
    public function NotifyProcess($data, &$msg)
    {
        Log::record("call back:" . json_encode($data),Log::DEBUG,true);
        $notfiyOutput = array();

        if(!array_key_exists("transaction_id", $data)){
            $msg = "输入参数不正确";
            return false;
        }

        return true;
    }

    public function processOrder($data)
    {
        Log::record('order info:'.$data['out_trade_no'],Log::INFO,true);
        $key =C('YOUFAN_PAY_ORDER_URL_KEY');
        $url = C('YOUFAN_PAY_ORDER_URL');
        $para = array(
            'orderId'=>$data['out_trade_no'],
            'price' => $data['total_fee']/100,
            'payOrder' => $data['transaction_id'],
            'key' => $key,
            'from' => 'WX'
        );

        Log::record('api data:'.json_encode($para),Log::INFO,true);
        $url = $url.'&orderId='.$para['orderId'].'&price='.$para['price'].'&payOrder='.$para['payOrder'].'&key='.$key.'&from=WX';
        Log::record('api url:'.$url,Log::INFO,true);
        $result = http($url,array());
        Log::record('process result:'.$result,Log::INFO,true);
    }
}