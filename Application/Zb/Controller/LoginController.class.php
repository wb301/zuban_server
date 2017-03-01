<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;

/**

	控制器层 调用service层

*/
class LoginController extends CommonController
{

    /**
        生成用户token
    */
    protected function updUserInfo($userInfo){

        $nowTime = date('Y-m-d H:i:s');
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

        //这里需要修改一下用户发布信息的经纬度
        $productModel = M('zuban_product_goods','','DB_DSN');
        $productSaveArr = array("logitude" => $userBase["logitude"], "latitude" => $userBase["latitude"]);
        $productModel->where(array("user_id" => $userInfo["user_id"]))->save($productSaveArr);

        $userInfo["token"] = $token;
        return $userInfo;
    }

	/**
     *  用户登陆
     */
    public function login()
    {
        $this->_POST();
        $keyAry = array(
            'account' => "用户名不能为空",
            'password' => "密码不能为空",
            'openId' => ""
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        $account = $parameters['account'];
        $password = $parameters['password'];
        $openId = $parameters['openId'];

        $userBaseModel = M("zuban_user_base", 0, "DB_DSN");
        $userInfo = $userBaseModel->where(array("account" => $account))->find();
        if( !$userInfo )
            return $this->returnErrorNotice("帐号不存在");
        if( $userInfo['password'] != md5($password) ) 
            return $this->returnErrorNotice("密码错误");

        //登陆成功存token
        $userInfo = $this->updUserInfoByOpendId($userInfo, $openId);
        $userInfo = $this->updUserInfo( $userInfo );
        return $this->returnSuccess($userInfo);
    }


    /**
        微信登录
    */
    public function wxLogin(){

        $this->_POST();
        $keyAry = array(
            'open_id' => "微信标示不能为空",
            'region_code' => "",
            'logitude' => "",
            'latitude' => ""
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        $openId = $parameters['open_id'];
        $userBaseModel = M("zuban_user_base", 0, "DB_DSN");
        $userInfo = $userBaseModel->where(array("wx_openid" => $openId))->find();

        if(empty($userInfo["account"]) || empty($userInfo["password"])){
            return $this->returnErrorNotice("请前往注册页", -101);
        }

        //登陆成功存token
        $userInfo = $this->updUserInfo( $userInfo );
        return $this->returnSuccess($userInfo);
    }



}