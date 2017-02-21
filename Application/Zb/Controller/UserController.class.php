<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;

/**

	控制器层 调用service层

*/
class UserController extends CommonController
{

    /**
     * 获取用户信息
     * @param $token    用户标示
     */
    public function getUserInfo(){

        $userInfo = $this->checkToken();
        $userBase = $this->checkUserId($userInfo["user_id"]);
        $userBase["money"] = $this->getUserMoneyInfo($userInfo["user_id"]);

        return $this->returnSuccess($userBase);
    }

    /**
     * 修改用户信息
     * @param $token    用户标示
     */
    public function updUserInfo(){

        $this->_POST();
        $keyAry = array(
            'head_img' => "",
            'nick_name' => "",
            'age' => "",
            'height' => "",
            'weight' => "",
            'professional' => "",
            'qualifications' => ""
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        $userInfo = $this->checkToken();
        $whereArr = array("user_id" => $userInfo["user_id"]);
        $saveArr = array();

        if( isset($parameters['head_img']) )
            $saveArr["head_img"] = $parameters['head_img'];
        if( isset($parameters['nick_name']) )
            $saveArr["nick_name"] = $parameters['nick_name'];
        if( isset($parameters['age']) )
            $saveArr["age"] = $parameters['age'];
        if( isset($parameters['height']) )
            $saveArr["height"] = $parameters['height'];
        if( isset($parameters['weight']) )
            $saveArr["weight"] = $parameters['weight'];
        if( isset($parameters['professional']) )
            $saveArr["professional"] = $parameters['professional'];
        if( isset($parameters['qualifications']) )
            $saveArr["qualifications"] = $parameters['qualifications'];

        $userBaseModel = M("zuban_user_base", '', "DB_DSN");
        if(count($saveArr) > 0){
            $userBaseModel->where($whereArr)->save($saveArr);
        }

        $userInfo = $userBaseModel->where($whereArr)->find();
        return $this->returnSuccess($userInfo);
    }


    /**
     * 阿里云 短信验证
     * @param $phone    手机号码
     * @param null $mobile_code     验证码
     * @param null $from   短信来源 1.注册时获取的验证码，2.手机号已注册的情况下，获取的验证码
     */
    public function sendMobileCode(){

        $this->_POST();
        $keyAry = array(
            'mobile' => "手机号码不能为空",
            'from' => ""
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        $phone = $parameters["mobile"];
        $from = $parameters["from"] ? $parameters["from"] : 1;

        $mobile_code = $this->random(6,1);
        if(!$template_code){$template_code='';}
        $target = "https://sms.aliyuncs.com/?";
        // 注意使用GMT时间
        date_default_timezone_set("GMT");
        $dateTimeFormat = 'Y-m-d\TH:i:s\Z'; // ISO8601规范
        $accessKeyId = 'LTAIGPskV7XIy0QL';      // 这里填写您的Access Key ID
        $accessKeySecret = 'Rztpe5ie0WZ4Sq4wgdxhVXXNAhmGQ0';  // 这里填写您的Access Key Secret
        $ParamString="{'code':'$mobile_code','time':'3'}";
        $data = array(
            // 公共参数
            'SignName'=> "签名名称",
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
        $data['Signature'] = $this->computeSignature($data, $accessKeySecret);
        // 发送请求
        $result = $this->https_request($target.http_build_query($data));
        $this->saveAccountByCode($phone, $mobile_code, $from);
        return $this->returnSuccess(true);
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
        // echo "<br>".$stringToSign."<br>";
        // 计算签名，注意accessKeySecret后面要加上字符'&'
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . '&', true));
        return $signature;
    }


}