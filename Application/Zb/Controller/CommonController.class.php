<?php
namespace Zb\Controller;
use Common\Controller\CommonController AS Controller;

/**

	控制器层 调用service层

*/
class CommonController extends Controller
{

    /**
     * version：2.0.0
     * info：检测用户id web用
     * params:user_Id，fileName
     * return:
     */
    protected function checkUserId($user_Id, $fileName = null, $isNotice = false)
    {

        $userModel = M('zuban_user_base','','DB_DSN');
        $userInfo = $userModel->where("`user_id` = '$user_Id' ")->select();
        if (!$userInfo || count($userInfo) <= 0) {
            if ($isNotice) {
                if ($fileName) {
                    $data[$fileName] = C('CODE_LOGIN_ERROR');
                    $data['message'] = "用户标识错误!";
                    $this->returnSuccess($data);
                } else {
                    return $this->returnErrorNotice("用户标识错误!");
                }
            } else {
                $data = array();
                if ($fileName) {
                    $data[$fileName] = C('CODE_LOGIN_ERROR');
                } else {
                    $data = C('CODE_LOGIN_ERROR');
                }
                return $this->returnSuccess($data);
            }
        }
        return $userInfo[0];
    }

    /**
     * info：token验证
     * params:token
     * return:
     */
    protected function checkToken($isNotice = 1)
    {
        $token=isset($_REQUEST['token'])?$_REQUEST['token']:'';
        $userInfoModel = M('zuban_user_info', '', 'DB_DSN');
        $userInfo = $userInfoModel->where("`token` = '$token' ")->field("`user_id`,`device`,`logitude`,`latitude`")->select();
        if (!$userInfo || count($userInfo) <= 0) {
            if ($isNotice) {
                return $this->returnErrorNotice("用户标识错误!");
            } else {
                return $this->returnSuccess(array());
            }
        }
        return $userInfo[0];
    }


    /**
     * info：获取价格信息
     * params:productList
     * return:array
     */
    public function  getProductPrice($productList){

        $proCodeList = array();
        foreach ($productList AS $key => $value) {
            $productList[$key]['price'] = 0;
            array_push($proCodeList, $value['product_sys_code']);
        }
        $proCodeListStr = getListString($proCodeList);
        $productGoodsModel = M('zuban_product_goods', '', 'DB_DSN');
        $productRs = $productGoodsModel->where("`product_sys_code` IN ($proCodeListStr)")->field("`price_type`,`product_sys_code`,`price`,`status`,`product_name`")->select();
        if (count($productRs) > 0) {
        
            foreach ($productList AS $key => $value) {
                $proCode = $value['product_sys_code'];
                    foreach ($productRs AS $k => $v) {
                        if ($v['product_sys_code'] == $proCode) {
                            $productList[$key]['price'] = $v['price'];
                            $productList[$key]['product_name'] = $v['product_name'];
                            $productList[$key]['price_type'] = $v['price_type'];
                            $productList[$key]['status'] = $v['status'];
                        }
                    }
                }
            }

    }

	public function sendMobileCode(){

		$result = $this->phoneCode();
		echo json_encode($result);
	}

	/**
     * 阿里云 短信验证
     * @param $phone    手机号码
     * @param null $mobile_code     验证码
     * @param null $from   短信来源 1.注册时获取的验证码，2.手机号已注册的情况下，获取的验证码
     */
    public function phoneCode($phone="18221830467",$mobile_code=null,$from=1){

        if (!$mobile_code){$mobile_code = $this->random(6,1);}
        if(!$template_code){$template_code='';}
        $target = "https://sms.aliyuncs.com/?";
        // 注意使用GMT时间
        date_default_timezone_set("GMT");
        $dateTimeFormat = 'Y-m-d\TH:i:s\Z'; // ISO8601规范
        $accessKeyId = 'LTAIGPskV7XIy0QL';      // 这里填写您的Access Key ID
        $accessKeySecret = 'Rztpe5ie0WZ4Sq4wgdxhVXXNAhmGQ0';  // 这里填写您的Access Key Secret
        // $ParamString="{\"code\":\"".strval($mobile_code)."\",\"time\":\"3\"}";
        $ParamString = json_encode(array("code" => $mobile_code, "time" => 3));
        $data = array(
            // 公共参数
            'SignName'=> "租伴网",
            'Format' => "XML",
            'Version' => "2016-09-27",
            'AccessKeyId' => $accessKeyId,
            'SignatureVersion' => "1.0",
            'SignatureMethod' => "HMAC-SHA1",
            'SignatureNonce'=> uniqid(),
            'Timestamp' => date($dateTimeFormat),
            // 接口参数
            'Action' => "SingleSendSms",
            'TemplateCode' => "SMS_48470040",
            'RecNum' => $phone,
            'ParamString' => $ParamString
        );

        // 计算签名并把签名结果加入请求参数
        //echo $data['Version']."<br>";
        //echo $data['Timestamp']."<br>";
        $data['Signature'] = $this->computeSignature($data, $accessKeySecret);
        // 发送请求
        // $result = $this->https_request($target.http_build_query($data));

        $nowTime = date('Y-m-d H:i:s');
        $validationArr = array("account" => $phone,
        					   "code" => $mobile_code,
        					   "create_time" => $nowTime,
        					   "update_time" => $nowTime,
        					   "status" => 0,
        					   "from" => $from);
        $validationModel = M("zuban_sms_validation", 0, "DB_DSN");
        $validationModel->add($validationArr);

        $result = $this->xml_to_array($this->https_request($target.http_build_query($data)));
        print_r($result);
    }
    public function https_request($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl);
        if (curl_errno($curl)) {return 'ERROR '.curl_error($curl);}
        curl_close($curl);
        return $data;
    }
    public function xml_to_array($xml){
        $reg = "/<(\w+)[^>]*>([\\x00-\\xFF]*)<\\/\\1>/";
        if(preg_match_all($reg, $xml, $matches)){
            $count = count($matches[0]);
            for($i = 0; $i < $count; $i++){
                $subxml= $matches[2][$i];
                $key = $matches[1][$i];
                if(preg_match( $reg, $subxml )){
                    $arr[$key] = $this->xml_to_array( $subxml );
                }else{
                    $arr[$key] = $subxml;
                }
            }
        }
        return @$arr;
    }
    public function random($length = 6 , $numeric = 0) {
        PHP_VERSION < '4.2.0' && mt_srand((double)microtime() * 1000000);
        if($numeric) {
            $hash = sprintf('%0'.$length.'d', mt_rand(0, pow(10, $length) - 1));
        } else {
            $hash = '';
            /* $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789abcdefghjkmnpqrstuvwxyz';*/
            $chars = '0123456789';
            $max = strlen($chars) - 1;
            for($i = 0; $i < $length; $i++) {
                $hash .= $chars[mt_rand(0, $max)];
            }
        }
        return $hash;
    }
    public function percentEncode($str)
    {
        // 使用urlencode编码后，将"+","*","%7E"做替换即满足ECS API规定的编码规范
        $res = urlencode($str);
        $res = preg_replace('/\+/', '%20', $res);
        $res = preg_replace('/\*/', '%2A', $res);
        $res = preg_replace('/%7E/', '~', $res);
        return $res;
    }
 
 
    public function computeSignature($parameters, $accessKeySecret)
    {
        // 将参数Key按字典顺序排序
        ksort($parameters);
        // 生成规范化请求字符串
        $canonicalizedQueryString = '';
        foreach($parameters as $key => $value)
        {
            $canonicalizedQueryString .= '&' . $this->percentEncode($key)
                . '=' . $this->percentEncode($value);
        }
        // 生成用于计算签名的字符串 stringToSign
        $stringToSign = 'GET&%2F&' . $this->percentencode(substr($canonicalizedQueryString, 1));
        //echo "<br>".$stringToSign."<br>";
        // 计算签名，注意accessKeySecret后面要加上字符'&'
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . '&', true));
        return $signature;
    }

>>>>>>> 手机注册   手机登录  微信登录  微信绑定手机号码
}