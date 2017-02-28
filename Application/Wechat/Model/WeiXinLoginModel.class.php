<?php
/**
 * Created by PhpStorm.
 * User: whh
 * Date: 9/9/16
 * Time: 4:00 PM
 * desc:微信登陆
 */

namespace Wechat\Model;
use Think\Model;

class WeiXinLoginModel{
    const OAUTH_PREFIX        = 'https://open.weixin.qq.com/connect/oauth2';
    const OAUTH_AUTHORIZE_URL = '/authorize?';
    const API_BASE_URL_PREFIX = 'https://api.weixin.qq.com'; //以下API接口URL需要使用此前缀
    const OAUTH_TOKEN_URL     = '/sns/oauth2/access_token?'; //获取access_token接口url
    const OAUTH_REFRESH_URL   = '/sns/oauth2/refresh_token?'; //刷新access_token有效期接口URL
    const OAUTH_USERINFO_URL  = '/sns/userinfo?';
    const OAUTH_AUTH_URL      = '/sns/auth?';

//    private  $appid     = 'wxc58bff0ef94a2ecc';
//    private  $appsecret = '2e3f7e6642efb15eeb85c52b282a4c17';
    private  $user_token;

    public $errCode = 40001;
    public $errMsg = "no access";

    /*
     * 获取用户openid
     * $param sting $domain
     * $param string $url 回调URL
     * $param string $scope 授权作用域
     * */
    public function getOpenId($domain,$url,$scope='snsapi_userinfo'){

        $domain = str_replace('.','_',$domain);
        if(isset($_COOKIE[$domain])){
            $data = json_decode($_COOKIE[$domain],true);
            //检验授权凭证（access_token）是否有效
            if(self::getOauthAuth($data['access_token'],$data['openid'])){
                return $data;
            }
        }
        //获取access_token
        $result = self::getOauthAccessToken() ;
        $refresh_token = $result["refresh_token"];
        if(!$refresh_token){
            $url = self::getOauthRedirect($url,$state=123,$scope);
            header("Location:$url"); //$url中会带上code
        }
        //刷新access token并续期
        $data = self::getOauthRefreshToken($refresh_token);
        setcookie($domain,'',time()-1);

        setcookie($domain,json_encode($data),time()+7200);

        return $data;


    }

    /**
     * oauth 授权跳转接口
     * @param string $callback 授权后重定向的回调链接地址（回调URL）
     * @param string $state 重定向后会带上state参数，开发者可以填写a-zA-Z0-9的参数值
     * @param string $response_type 返回类型，请填写code
     * @param stirng $appid  公众号的唯一标识
     * @param string $scope 应用授权作用域:snsapi_base （不弹出授权页面，直接跳转，只能获取用户openid）,snsapi_userinfo （弹出授权页面，可通过openid拿到昵称、性别、所在地。并且，即使在未关注的情况下，只要用户授权，也能获取其信息）
     * @return string
     */
    public function getOauthRedirect($callback,$state='',$scope='snsapi_userinfo'){
        return self::OAUTH_PREFIX.self::OAUTH_AUTHORIZE_URL.'appid='.C('APPID_WX').'&redirect_uri='.urlencode($callback).'&response_type=code&scope='.$scope.'&state='.$state.'#wechat_redirect';
    }

    /**
     * 通过code获取Access Token
     * @return array {access_token,expires_in,refresh_token,openid,scope}
     */
    public function getOauthAccessToken(){
        $code = isset($_GET['code'])?$_GET['code']:'';
        if (!$code) return false;
        $result = self::http_get(self::API_BASE_URL_PREFIX.self::OAUTH_TOKEN_URL.'appid='.C('APPID_WX').'&secret='.C('APPSECRET_WX').'&code='.$code.'&grant_type=authorization_code');
        if ($result)
        {
            $json = json_decode($result,true);
            if (!$json || !empty($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            $this->user_token = $json['access_token'];
            return $json;
        }
        return false;
    }
    /**
     * 刷新access token并续期
     * @param string $refresh_token
     * @return json:array{access_token,expires_in,refresh_token,openid,scope}
     */
    public function getOauthRefreshToken($refresh_token){
        $result = self::http_get(self::API_BASE_URL_PREFIX.self::OAUTH_REFRESH_URL.'appid='.C('APPID_WX').'&grant_type=refresh_token&refresh_token='.$refresh_token);
        if ($result)
        {
            $json = json_decode($result,true);
            if (!$json || !empty($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            $this->user_token = $json['access_token'];
            return $json;
        }
        return false;
    }

    /**
     * 获取授权后的用户资料
     * @param string $access_token
     * @param string $openid
     * @return array {openid,nickname,sex,province,city,country,headimgurl,privilege,unionid]}
     * 注意：unionid字段 只有在用户将公众号绑定到微信开放平台账号后，才会出现。建议调用前用isset()检测一下
     */
    public function getOauthUserinfo($access_token,$openid){
        $result = self::http_get(self::API_BASE_URL_PREFIX.self::OAUTH_USERINFO_URL.'access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN');
        if ($result)
        {
            $json = json_decode($result,true);
            if (!$json || !empty($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }
    /**
     * 检验授权凭证是否有效
     * @param string $access_token
     * @param string $openid
     * @return boolean 是否有效
     */
    public function getOauthAuth($access_token,$openid){
        $result = self::http_get(self::API_BASE_URL_PREFIX.self::OAUTH_AUTH_URL.'access_token='.$access_token.'&openid='.$openid);
        if ($result)
        {
            $json = json_decode($result,true);
            if (!$json || !empty($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            } else{
                if ($json['errcode']==0) {
                    return true;
                }
            }

        }
        return false;
    }

    /**
     * 第三方登陆
     */
    public function loginByOauth($data)
    {
        $account = $data['openid'];
        $nowTime = date('Y-m-d H:i:s');
        $userBaseModel = M("zuban_user_base", '', "DB_DSN");
        $userInfo = $userBaseModel->where(array("account" => $account))->find();
        if(!$userInfo){
            $nickName = $data['nickname'];
            $headPortrait = $data['headimgurl'];
            $sex = $data['sex'] == 1?'男':'女'; //1:男，2:女
            $province = $data['province']; //省份
            $city = $data['city']; //城市
            $country = $data['country']; //国家
            $nowTime = date('Y-m-d H:i:s');
            $userInfo = array('user_id' => create_guid(),
                'account' => $account,
                'password' => '',
                'head_img' => $headPortrait,
                'wx_openid' => $account,
                'region_code' => '',
                'nick_name' => $nickName,
                'logitude' =>  '',
                'latitude' =>  '',
                'register_time' => $nowTime
            );
            //这里新增一下数据
            $userBaseModel->add($userInfo);
        }
        $token = md5($userInfo['user_id'].time());
        $userBase['token'] = $token;
        $userBase["update_time"] = $nowTime;
        $userBase["device"] = $_REQUEST['device'] ? $_REQUEST['device'] : '';
        $userBase["version"] = $_REQUEST['version'] ? $_REQUEST['version'] : '';
        $userBase["app_name"] = $_REQUEST['app_name'] ? $_REQUEST['app_name'] : '';
        $userBase["os_mode"] = $_REQUEST['os_mode'] ? $_REQUEST['os_mode'] : '';
        $userBase["logitude"] = $_REQUEST['logitude'] ? $_REQUEST['logitude'] : '';
        $userBase["latitude"] = $_REQUEST['latitude'] ? $_REQUEST['latitude'] : '';
        $userInfoModel = M("zuban_user_info", 0, "DB_DSN");
        $userRes = $userInfoModel->where(array("user_id" => $userInfo["user_id"]))->find();
        if( !$userRes ){
            $userBase["user_id"] = $userInfo["user_id"];
            $userInfoModel->add($userBase);
        }else{
            $userInfoModel->where(array("user_id" => $userInfo["user_id"]))->save($userBase);
        }
        $userInfo['openid']=$data['openid'];
        $userInfo['token']=$token;
        return $userInfo;

    }
    /**
     * 生成新的密码
     * @return string
     */
    public function generatePassword($password)
    {
        $key = C('UNICO_SERVICE_KEY');
        $pwd = md5("$password-$key");
        return $pwd;
    }
    /**
     * 生成线下会员卡
     * @param $id
     */
    public function createMemberCode($id)
    {
        //todo::迁服务，这边的逻辑不再使用
    }
    /**
     * 当用户注册成功后要处理的步骤
     * @param $user
     * @return bool
     */
    public function afterRegisterSuccess($userId)
    {

        return true;
    }

    public function afterLoginSuccess($userId)
    {

        return true;
    }

    /**
     * GET 请求
     * @param string $url
     */
    private function http_get($url){
        $oCurl = curl_init();
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if(intval($aStatus["http_code"])==200){
            return $sContent;
        }else{
            return false;
        }
    }

    /**
     * POST 请求
     * @param string $url
     * @param array $param
     * @param boolean $post_file 是否文件上传
     * @return string content
     */
    private function http_post($url,$param,$post_file=false){
        $oCurl = curl_init();
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        if (is_string($param) || $post_file) {
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach($param as $key=>$val){
                $aPOST[] = $key."=".urlencode($val);
            }
            $strPOST =  join("&", $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($oCurl, CURLOPT_POST,true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS,$strPOST);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if(intval($aStatus["http_code"])==200){
            return $sContent;
        }else{
            return false;
        }
    }

    /**
     * 发送HTTP请求方法
     * @param  string $url    请求URL
     * @param  array  $params 请求参数
     * @param  string $method 请求方法GET/POST
     * @return array  $data   响应数据
     */
    function curl($url, $params, $method = 'GET', $header = array(), $multi = false){
        $opts = array(
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => $header
        );
        /* 根据请求类型设置特定参数 */
        switch(strtoupper($method)){
            case 'GET':
                $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
                break;
            case 'POST':
                //判断是否传输文件
                $params = $multi ? $params : http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            default:
                throw new Exception('不支持的请求方式！');
        }
        /* 初始化并执行curl请求 */
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $data  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if($error) throw new Exception('请求发生错误：' . $error);
        return  $data;
    }

}