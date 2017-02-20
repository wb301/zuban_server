<?php
/**
 * 支付宝支付
 * */
namespace Pay\AliPay;
use Pay\BasePay;

class AliPay extends BasePay
{ 
    const TRADE_SUCCESS = 'TRADE_SUCCESS';
    const TRADE_FINISHED = 'TRADE_FINISHED';
    protected function  __construct()
    {
        $this->config = $this->getConfig('alipay');
        /*$param = $_REQUEST;
        $para_filter = $this->paraFilter($param);

        //对待签名参数数组排序
        $para_sort = $this->argSort($para_filter);
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($para_sort);
        $sign = $this->rsaSign($prestr, $this->config['PRIVATE_KEY_PATH']);
        echo $sign ;exit;*/
    }



    /**
     * @desc 支付回调通知
     * */
    public function notify()
    {
        $request = $_POST;
        //支付回调开始记录请求日志
        $this->logPay('notify channel=zfb request='. json_encode($request));
        //记录通知次数
        $this->addNotifyLog($request['out_trade_no'], $request);

        //验证签名
        $checkSign = $this->checkSign($request);
        if(!$checkSign){
            $this->logPay('notify channel=zfb checkSignReuslt='. $checkSign, 'ERR');
            echo 'fail';
            return false;
        }
        //验证来源
        $checkSource = $this->checkSource($request['notify_id']);
        if(!$checkSource){
            $this->logPay('notify channel=zfb checkSourceReuslt='. $checkSource, 'ERR');
            echo 'fail';
            return false;
        }
        //商户订单号
        $outTradeNo = $request['out_trade_no'];
        //支付宝交易号
        $tradeNo = $request['trade_no'];
        //交易状态
        $tradeStatus = $request['trade_status'];
        //支付价格
        $price = $request['price'];

        if($tradeStatus != self::TRADE_SUCCESS){
            echo 'fail';
            return false;
        }
        $payOrderResult = $this->payOrder($outTradeNo,$price,$tradeNo,'ZFB');
        if($payOrderResult['code'] != 1){
            $this->logPay('notify channel=Ali payOrder fail', 'ERR');
            echo 'fail';
            return false;
        }
        echo 'success';
    }

    private function checkRequestParam()
    {
        if(!$_REQUEST['out_trade_no'] || !$_REQUEST['trade_no'] || !$_REQUEST['price']){
            echo " checkRequestParam fail";
            return false;
        }
        return true;
    }


    /**
     * 获取支付宝远程服务器ATN结果，验证是否是支付宝发来的消息
     * @param notifyId 
     * */
    private function checkSource($notifyId=0)
    {
        $this->logPay('notify channel=Ali checkSource start');
        if(!$notifyId){
            return false;
        }
        $responseTxt = $this->getResponse($notifyId);

        $this->logPay('notify channel=Ali checkSource end');
        if (!preg_match("/true$/i",$responseTxt)) {
            return false;
        } 
        return true;
    }
    private function checkSign($param)
    {

        $this->logPay('notify channel=Ali checkSign start');
        $sign = $param['sign'];

        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($param);

        //对待签名参数数组排序
        $para_sort = $this->argSort($para_filter);
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($para_sort);
        $isSign = $this->rsaVerify($prestr, trim($this->config['PUBLIC_KEY_PATH']), $sign);
        $this->logPay('notify channel=Ali checkSign end');
        return $isSign;
    }

    /**
     * 除去数组中的空值和签名参数
     * @param $para 签名参数组
     * return 去掉空值与签名参数后的新签名参数组
     */
    private function paraFilter($para)
    {
        $paraFilter = array();
        while (list($key, $val) = each($para)) 
        {
            if($key == "sign" || $key == "sign_type" || $val == ""){
                continue;
            }else{
                $paraFilter[$key] = $para[$key];
            }
        }
        return $paraFilter;
    }
    /**
     * 对数组排序
     * @param $para 排序前的数组
     * return 排序后的数组
     */
    private function argSort($para)
    {
        ksort($para);
        reset($para);
        return $para;
    }


    private function createLinkstring($para)
    {
        $arg  = "";
        while(list($key, $val) = each($para))
        {
            $arg.=$key."=".$val."&";
        }
        //去掉最后一个&字符
        $arg = substr($arg,0,count($arg)-2);

        //如果存在转义字符，那么去掉转义
        if(get_magic_quotes_gpc()){
            $arg = stripslashes($arg);
        }
        return $arg;
    }

    /**
     * RSA签名
     * @param $data 待签名数据
     * @param $privateKeyPath 商户私钥文件路径
     * return 签名结果
     */
    private function rsaSign($data, $privateKeyPath) 
    {
        $priKey = file_get_contents($privateKeyPath);
        $res = openssl_get_privatekey($priKey);
        openssl_sign($data, $sign, $res);
        openssl_free_key($res);
        //base64编码
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * RSA验签
     * @param $data 待签名数据
     * @param $aliPublicKeyPath 支付宝的公钥文件路径
     * @param $sign 要校对的的签名结果
     * return 验证结果
     */
    private function rsaVerify($data, $aliPublicKeyPath, $sign)  {
        $pubKey = file_get_contents($aliPublicKeyPath);
        $res = openssl_get_publickey($pubKey);
        $result = (bool)openssl_verify($data, base64_decode($sign), $res);
        openssl_free_key($res);    
        return $result;
    }


    /**
     * 获取远程服务器ATN结果,验证返回URL
     * @param $notify_id 通知校验ID
     * @return 服务器ATN结果
     * 验证结果集：
     * invalid命令参数不对 出现这个错误，请检测返回处理中partner和key是否为空 
     * true 返回正确信息
     * false 请检查防火墙或者是服务器阻止端口问题以及验证时间是否超过一分钟
     */
    private function getResponse($notifyId)
    {
        $transport = strtolower(trim($this->config['TRANSPROT']));
        $partner = trim($this->config['PARTNER']);
        $veryfyUrl = '';
        if($transport == 'https') {
            $veryfyUrl = $this->config['HTTPS_VERIFY_URL'];
        }
        else {
            $veryfyUrl = $this->config['HTTP_VERIFY_URL'];
        }

        $veryfyUrl = $veryfyUrl."partner=" . $partner . "&notify_id=" . $notifyId;
        $this->logPay('notify channel=Ali checkSource verifyUrl='.$veryfyUrl);

        $responseTxt = $this->getHttpResponseGET($veryfyUrl, $this->config['CACERT']);

        $this->logPay('notify channel=Ali checkSource verifyResult='.json_encode($responseTxt));

        return $responseTxt;
    }

    /**
     * 远程获取数据，GET模式
     * @param $url 指定URL完整路径地址
     * @param $cacertUrl 指定当前工作目录绝对路径
     * return 远程输出的数据
     */
    private function getHttpResponseGET($url,$cacert_url) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
        curl_setopt($curl, CURLOPT_CAINFO,$cacert_url);//证书地址
        $responseText = curl_exec($curl);
        //var_dump( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
        curl_close($curl);

        return $responseText;
    }
}

