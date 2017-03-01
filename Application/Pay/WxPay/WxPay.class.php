<?php
/**
 * 支付宝支付
 * */
namespace Pay\WxPay;
use Pay\BasePay;
class WxPay extends BasePay 
{
    protected function  __construct()
    {
        $this->config = $this->getConfig('wx');
    }

    /**
     * @desc 支付回调通知
     * */
    public function notify()
    {
        
        $result = array(
            'return_code' => 'FAIL',
            'return_msg' => '',
        );
        $requestXml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $this->logPay('notify channel=wx request='. $requestXml);
        //将微信返回的xml转换成数组
        $request = $this->xmlstr_to_array($requestXml);

        //记录通知次数
        $this->addNotifyLog($request['out_trade_no'], $request);

        $returnCode = $request['return_code'];
        $resultCode = $request['result_code'];

        //check params
        $checkNotifyParam = $this->checkNotifyParam($request);
        if(!$checkNotifyParam){
            $this->logPay('notify channel=wx checkNotifyParam err', 'ERR');
            $result['return_msg'] = '参数不合法';
            $xmlResult = $this->arrayToXml($result);
            echo $xmlResult;
            return;        
        } 
        //checksign
        $checkSign = $this->checkSign($request);
        if(!$checkSign){
            $this->logPay('notify channel=wx checkSign err', 'ERR');
            $result['return_msg'] = '验证签名失败';
            $xmlResult = $this->arrayToXml($result);
            echo $xmlResult;
            return;
        }
        $checkTransactionId = $this->checkTransactionId($request['transaction_id']);
        //验证transaction_id是否合法
        if(!$checkTransactionId){
            $this->logPay('notify channel=wx checkTransactionId err', 'ERR');
            $result['return_msg'] = 'transaction_id不合法';
            $xmlResult = $this->arrayToXml($result);
            echo $xmlResult;
            return;

        }
        if($returnCode !='SUCCESS' || $resultCode!='SUCCESS'){
            $this->logPay('notify channel=wx resultCode err', 'ERR');
            $result['return_msg'] = '通知结果 returnCode='. $returnCode.' resultCode='. $resultCode;
            $xmlResult = $this->arrayToXml($result);
            echo $xmlResult;
            return;        
        }

        $orderNo = $request['out_trade_no'];
        $price = $request['total_fee'] / 100; //将分转成元
        $tradeNo = $request['transaction_id'];
        $payOrderResult = $this->payOrder($orderNo, $price, $tradeNo, 'wx');        
        if($payOrderResult['code'] != 1){
            $this->logPay('notify channel=wx payOrderResult= '. json_encode($payOrderResult), 'ERR');
            $result['return_msg'] = 'payOrder失败';
            $xmlResult = $this->arrayToXml($result);
            echo $xmlResult;
            return;        
        }
        $result['return_code'] = 'SUCCESS';
        $xmlResult = $this->arrayToXml($result);
        echo $xmlResult;
    }

    /**
     * @desc 微信预支付
     * @param out_trade_no 订单号
     * @param total_fee 支付金额（分）
     * todo user check 
     * */
    public function prePay()
    {
        $time = time();
        $nonceStr = $this->getRandChar(32);          //随机字符串，不长于32位。
        $partnerId = $this->config['MCHID']; //商户号
        $result = array(
            'isSuccess' => false,
            'prePayId' => '',
            'nonceStr' => $nonceStr,
            'sign' => '',
            'appid' => $this->config["APPID"],
            'partnerid' => $partnerId,
            'timeStamp' => $time,
        );
        $request = $_REQUEST;
        $this->logPay('prePay channel=wx request='. json_encode($request));
        if(!$this->checkPrePayParam($request)){
            $this->logPay('prePay channel=wx checkPrePayParam' , 'ERR');
            echo json_encode($result);    
            return;
        }
        $data = array();

        $data["appid"] = $this->config["APPID"]; //公众账号ID
        $data["body"] = '租伴网订单'.$request['out_trade_no']; //商品描述 eg '有范订单11312321321312'
        $data["mch_id"] = $partnerId;
        $data["openid"] = $request['openid'];//微信的openid
        //$data["openid"] = 'oUt4luIpi7rr_i41zZ0p6orWPoQw';//微信的openid
        $data["nonce_str"] = $nonceStr;          
        $data["notify_url"] = $this->config['NOTIFY_URL']; //异步通知地址
        $data["out_trade_no"] = $request['out_trade_no']; //订单号
        $data["spbill_create_ip"] = $this->get_client_ip(); //客户端ip
        $data["total_fee"] = $request['total_fee'];  //支付金额（分）
        $data["trade_type"] = "JSAPI";
        $data['sign'] = $this->makeSign($data); //md5签名

        $xml = $this->arrayToXml($data); //转换成xml对象

        //调用统一下单接口
        $url = $this->config['PRE_PAY_URL'];
        $response = $this->postXmlCurl($xml, $url);
        if(!$response){
            echo json_encode($response);
            return ;
        }
        //将微信返回的结果xml转成数组
        $response =  $this->xmlstr_to_array($response);
        if($response['return_code']!= 'SUCCESS' && $response['result_code']!='SUCCESS'){
            echo json_encode($result);
            return;
        }
        $result['isSuccess'] = true;
        $result['prePayId'] = $response['prepay_id'];
        $package='prepay_id='.$response['prepay_id'];
        $result['package'] = $package;
        //签名sign
        $paramPaySign = array(
            'appId' => $this->config["APPID"],
            'timeStamp' => $time,
            'nonceStr' => $nonceStr,
            'package' => $package,
            'signType' => 'MD5',
        );
        $result['sign'] = $this->makeSign($paramPaySign);
        echo json_encode($result);
    }

    /** 
     * @desc 检查预支付参数
     * todo check token out_trade_no
     * */
    private function checkPrePayParam($param)
    {
        if(empty($param['out_trade_no'])){
            return false;
        }  
        return true;
    }
    /**
     * 生成签名
    */
    private function makeSign($parameters)
    {
        foreach($parameters as $k=>$v)
        {
            if($k == "sign" || $v == "" || is_array($v)){
                unset($parameters[$k]);
            }
        }
        //print_r($parameters);
        //签名步骤一：按字典序排序参数
        ksort($parameters);
        $string = $this->formatBizQueryParaMap($parameters, false);
        //echo "【string】 =".$string."</br>";
        //签名步骤二：在string后加入KEY
        $string = $string."&key=".$this->config['KEY'];
        //签名步骤三：MD5加密
        $result = strtoupper(md5($string));
        return $result;
    }

    //获取指定长度的随机字符串
    private function getRandChar($length)
    {
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol)-1;

        for($i=0;$i<$length;$i++){
            $str.=$strPol[rand(0,$max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }
        return $str;
    }

    //数组转xml
    private function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
             if (is_numeric($val))
             {
                $xml.="<".$key.">".$val."</".$key.">"; 

             }
             else
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";  
        }
        $xml.="</xml>";
        return $xml; 
    }

    /**
     * @desc cpost https请求，CURLOPT_POSTFIELDS xml格式
     * */
    private function postXmlCurl($xml,$url,$second=30)
    {
        $this->logPay('postXmlCurl start xml='. $xml .' url='.$url );
        //初始化curl        
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);

        $this->logPay(' postXmlCurl result '. $data);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        }else { 
            $error = curl_errno($ch);
            curl_close($ch);

            $this->logPay('postXmlCurl error '. $error, 'ERR');
            return false;
        }
    }

    /*
        获取当前服务器的IP
    */
    private function get_client_ip()
    {
        if ($_SERVER['REMOTE_ADDR']) {
        $cip = $_SERVER['REMOTE_ADDR'];
        } elseif (getenv("REMOTE_ADDR")) {
        $cip = getenv("REMOTE_ADDR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
        $cip = getenv("HTTP_CLIENT_IP");
        } else {
        $cip = "unknown";
        }
        return $cip;
    }

    //将数组转成uri字符串
    private function formatBizQueryParaMap($paraMap, $urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v)
        {
            if($urlencode)
            {
               $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar;
        if (strlen($buff) > 0) 
        {
            $reqPar = substr($buff, 0, strlen($buff)-1);
        }
        return $reqPar;
    }

    /**
    xml转成数组
    */
    private function xmlstr_to_array($xmlstr) 
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xmlstr);
        return $this->domnode_to_array($doc->documentElement);
    }

    private function domnode_to_array($node) 
    {
        $output = array();
        switch ($node->nodeType) {
        case XML_CDATA_SECTION_NODE:
        case XML_TEXT_NODE:
            $output = trim($node->textContent);
            break;
        case XML_ELEMENT_NODE:
            for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) {
                $child = $node->childNodes->item($i);
                $v = $this->domnode_to_array($child);
                if(isset($child->tagName)) {
                    $t = $child->tagName;
                    if(!isset($output[$t])) {
                        $output[$t] = array();
                    }
                    $output[$t][] = $v;
                }
                elseif($v) {
                    $output = (string) $v;
                }
            }
            if(is_array($output)) {
                if($node->attributes->length) {
                    $a = array();
                    foreach($node->attributes as $attrName => $attrNode) {
                        $a[$attrName] = (string) $attrNode->value;
                    }
                    $output['@attributes'] = $a;
                }
                foreach ($output as $t => $v) {
                    if(is_array($v) && count($v)==1 && $t!='@attributes') {
                        $output[$t] = $v[0];
                    }
                }
            }
            break;
        }
        return $output;
    }

    private function checkSign($params)
    {
        $sign = $params['sign'];
        $tmpSign = $this->makeSign($params);
        if($tmpSign != $sign){
            return false;
        }
        return true;
    }
    /**
     * 格式化参数格式化成url参数
     */
    private function ToUrlParams()
    {
        $buff = "";
        foreach ($this->values as $k => $v)
        {
            if($k != "sign" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }
    
    private function checkNotifyParam($params)
    {
        if(!$params['out_trade_no'] || !$params['total_fee'] || !$params['transaction_id']){
            return false;
        }
        return true;
    }

    private function checkTransactionId($transactionId)
    {
        $this->logPay('notify channel=wx queryOrder start');
        $data = array();

        $nonceStr = $this->getRandChar(32);          //随机字符串，不长于32位。
        $data["appid"] = $this->config["APPID"]; //公众账号ID
        $data["mch_id"] = $this->config['MCHID']; //商户号
        $data["transaction_id"] = $transactionId; //商户号
        $data["nonce_str"] = $nonceStr;          
        $data['sign'] = $this->makeSign($data); //md5签名

        $xml = $this->arrayToXml($data); //转换成xml对象
        $url =  $this->config['QUERY_URL'];


        $response = $this->postXmlCurl($xml, $url);
        if(!$response){
            $this->logPay('notify channel=wx queryOrder err', 'ERR');
            return false;
        }
        $response =  $this->xmlstr_to_array($response);
        if($response['return_code']!= 'SUCCESS' && $response['result_code']!='SUCCESS'){
            $this->logPay('notify channel=wx queryOrder err', 'ERR');
            return false;
        }
        $this->logPay('notify channel=wx queryOrder end');
        return true;
    }

}

