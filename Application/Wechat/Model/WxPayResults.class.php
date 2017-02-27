<?php
/**
 * Created by PhpStorm.
 * User: Aaron
 * Date: 4/15/16
 * Time: 3:48 PM
 */

namespace Wechat\Model;

use Think\Log;
use Wechat\Model\WxPayException;
/**
 *
 * 接口调用结果类
 * @author widyhu
 *
 */
class WxPayResults extends WxPayDataBase
{
    /**
     *
     * 检测签名
     */
    public function CheckSign()
    {
        Log::record('----------begin check sign---------',Log::DEBUG,true);
        //fix异常
        if(!$this->IsSignSet()){
            throw new WxPayException("签名错误！");
        }


        $sign = $this->MakeSign();
        Log::record('check sign whether equal:'.$sign.'='.$this->GetSign(),Log::DEBUG,true);
        if($this->GetSign() == $sign){
            Log::record('sign is same',Log::DEBUG,true);
            return true;
        }
        Log::record('sign is different',Log::DEBUG,true);
        throw new WxPayException("签名错误！");
    }

    /**
     *
     * 使用数组初始化
     * @param array $array
     */
    public function FromArray($array)
    {
        $this->values = $array;
    }

    /**
     *
     * 使用数组初始化对象
     * @param array $array
     * @param 是否检测签名 $noCheckSign
     */
    public static function InitFromArray($array, $noCheckSign = false)
    {
        $obj = new self();
        $obj->FromArray($array);
        if($noCheckSign == false){
            $obj->CheckSign();
        }
        return $obj;
    }

    /**
     *
     * 设置参数
     * @param string $key
     * @param string $value
     */
    public function SetData($key, $value)
    {
        $this->values[$key] = $value;
    }

    /**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
    public static function Init($xml,$config=null)
    {

        Log::record('init begin:'.json_encode($config),Log::DEBUG,true);
        $obj = new self();
        if($config) {
            $obj->setConfig($config);
        }
        $obj->FromXml($xml);
        //fix bug 2015-06-29
        if($obj->values['return_code'] != 'SUCCESS'){
            return $obj->GetValues();
        }
        Log::record('return code:'.$obj->values['return_code'],Log::DEBUG,true);
        $result = $obj->CheckSign();
        Log::record('check sign:'.($result ? 'success' : 'fail'),Log::DEBUG,true);
        return $obj->GetValues();
    }
}