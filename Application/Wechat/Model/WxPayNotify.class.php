<?php

namespace Wechat\Model;
use Think\Log;
use Wechat\Model\WxPayApi;

/**
 * 
 * 回调基础类
 * @author widyhu
 *
 */
class WxPayNotify extends WxPayNotifyReply
{
	/**
	 * 
	 * 回调入口
	 * @param bool $needSign  是否需要签名输出
	 */
	final public function Handle($needSign = true,$config=null)
	{
        Log::record('begin handle notify',Log::DEBUG,true);
        Log::record('request is:'.json_encode($_REQUEST),Log::DEBUG,true);
        Log::record(isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : 'there is no http_raw_post_data',Log::DEBUG,true);
		$msg = "OK";
		//当返回false的时候，表示notify中调用NotifyCallBack回调失败获取签名校验失败，此时直接回复失败
		$result = WxPayApi::notify(array($this, 'NotifyCallBack'), $msg,$config);
		$result = true; //TODO... 这里先不判断
		if($result == false){
            Log::record('return false in handle',Log::DEBUG,true);
			$this->SetReturn_code("FAIL");
			$this->SetReturn_msg($msg);
			$this->ReplyNotify(false);
			return;
		} else {
            Log::record('return true in handle',Log::DEBUG,true);
			//该分支在成功回调到NotifyCallBack方法，处理完成之后流程
			$this->SetReturn_code("SUCCESS");
			$this->SetReturn_msg("OK");
		}
		//这里处理成功后的逻辑,调用http更新订单状态
		$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
		try {
            Log::record('wxpay results init to get result',Log::DEBUG,true);
			$jsonResult = WxPayResults::Init($xml,$config);
            Log::record('wxpay result is:'.json_encode($jsonResult),Log::DEBUG,true);
            $this->processOrder($jsonResult);
		} catch (WxPayException $e){
			$e->errorMessage();
			return false;
		}

		$this->ReplyNotify($needSign);
	}
	
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
		//TODO 用户基础该类之后需要重写该方法，成功的时候返回true，失败返回false
		return true;
	}

    public function processOrder($data)
    {
        Log::record('process order',Log::DEBUG,true);
        return true;
    }
	
	/**
	 * 
	 * notify回调方法，该方法中需要赋值需要输出的参数,不可重写
	 * @param array $data
	 * @return true回调出来完成不需要继续回调，false回调处理未完成需要继续回调
	 */
	final public function NotifyCallBack($data)
	{
		$msg = "OK";
		$result = $this->NotifyProcess($data, $msg);
		Log::record('notify callback:'.$result,Log::DEBUG,true);
		if($result == true){
			$this->SetReturn_code("SUCCESS");
			$this->SetReturn_msg("OK");
		} else {
			$this->SetReturn_code("FAIL");
			$this->SetReturn_msg($msg);
		}
		return $result;
	}
	
	/**
	 * 
	 * 回复通知
	 * @param bool $needSign 是否需要签名输出
	 */
	final private function ReplyNotify($needSign = true)
	{
		//如果需要签名
		if($needSign == true && $this->GetReturn_code() == "SUCCESS")
		{
			$this->SetSign();
		}
		WxPayApi::replyNotify($this->ToXml());
	}
}