<?php

namespace Wechat\Model;
use Platform\Model\OrderModel;
use Think\Log;

/**
 * 
 * 快闪支付微信成功回调
 * @author jiyongcheng
 *
 */
class YoufanQuickPayNotify extends WxPayNotify
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
        //查询订单，判断订单真实性
//        if(!$this->Queryorder($data["transaction_id"])){
//            $msg = "订单查询失败";
//            return false;
//        }

        return true;
    }

    public function processOrder($data)
    {
        Log::record('order info:'.$data['out_trade_no'],Log::INFO,true);
        $para = array(
            'order_no'=>$data['out_trade_no'],
            'price' => $data['total_fee']/100,
            'transaction_no' => $data['transaction_id'],
            'from' => 'WX'
        );
        //todo::快闪支付
    }

    //查询订单
    public function Queryorder($transaction_id)
    {
        Log::record("begin query order:" . $transaction_id,Log::DEBUG,true);
        $input = new WxPayOrderQuery();
        $config = new WxWapPayConfig();
        $input->setConfig($config);
        $input->SetTransaction_id($transaction_id);
        $result = WxPayApi::orderQuery($input);
        Log::record("query:" . json_encode($result),Log::DEBUG,true);
        if(array_key_exists("return_code", $result)
            && array_key_exists("result_code", $result)
            && $result["return_code"] == "SUCCESS"
            && $result["result_code"] == "SUCCESS")
        {
            return true;
        }
        return false;
    }

    public function processQuickPayOrderSuccess()
    {
        $url = mb_url('Platform','Quickpay/Quick','updateQuickOrder');
        $parameters = array(
            'is_success'=>0,
            'order_no'=>$_REQUEST['out_trade_no'],//YouFan订单号order_no
            'notice_trade_no' => $_REQUEST['transaction_id'],//微信交易号
            'pay_remark' => '',
            'pay_price' => $_REQUEST['total_fee']/100,
            'pay_user_id' => 'user_id',
            'from_type' => 'WX',
            'trade_status' => $_REQUEST['trade_state']
        );

        $ret = http($url, $parameters, 'POST');
        $result = json_decode($ret,true);
        Log::record('result of process order success:'.$ret,Log::DEBUG);

        if($result['status'] == -1) {
            return false;
        }else {
            return true;
        }
    }

    public function processQuickPayOrderFailed()
    {
        Log::record('-------begin process order failed------:',Log::INFO,true);
        $url = mb_url('Platform','Quickpay/Quick','updateQuickOrder');
        $parameters = array(
            'is_success'=>0,
            'order_no'=>$_REQUEST['out_trade_no']
        );

        $ret = http($url, $parameters, 'POST');
        //$result = json_decode($ret,true);
        Log::record('-------end process order failed------:'.$ret,Log::INFO,true);
    }
}