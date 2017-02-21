<?php
namespace Zb\Controller;
use Common\Controller\CommonController AS Controller;

/**

	控制器层 调用service层

*/
class LoginController extends Controller
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

        $userInfo["token"] = $token;
        return $userInfo;
    }

	/**
     *  用户登陆
     */
    public function login()
    {
        $this->_POST();

        if( empty($_POST['account']) ) 
            return $this->returnErrorNotice("用户名不能为空");
        if( empty($_POST['password']) )
            return $this->returnErrorNotice("密码不能为空");

        $account = $_POST['account'];
        $password = $_POST['password'];

        $userBaseModel = M("zuban_user_base", 0, "DB_DSN");
        $userInfo = $userBaseModel->where(array("account" => $account))->find();
        if( !$userInfo )
            return $this->returnErrorNotice("帐号不存在");
        if( $userInfo['password'] != md5($password) ) 
            return $this->returnErrorNotice("密码错误");

        //登陆成功存token
        $userInfo = $this->updUserInfo( $userInfo );
        return $this->returnSuccess($userInfo);
    }


    /**
        微信登录
    */
    public function wxLogin(){

        if($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->returnErrorNotice('请求不是POST');
        }

        if( empty($_POST['open_id']) ) 
            return $this->returnErrorNotice("微信标示不能为空");

        $openId = $_POST['open_id'];

        $userBaseModel = M("zuban_user_base", 0, "DB_DSN");
        $userInfo = $userBaseModel->where(array("wx_openid" => $openId))->find();

        if(!$userInfo){

            $nowTime = date('Y-m-d H:i:s');
            $userInfo = array("user_id" => $this->create_guid(),
                              "account" => '',
                              "password" => '',
                              "wx_openid" => $openId,
                              "nick_name" => $_POST['nick_name'],
                              "region_code" => $_REQUEST['region_code'] ? $_REQUEST['region_code'] : '',
                              "logitude" => $_REQUEST['logitude'] ? $_REQUEST['logitude'] : '',
                              "latitude" => $_REQUEST['latitude'] ? $_REQUEST['latitude'] : '',
                              "register_time" => $nowTime);

            //这里新增一下数据
            $userBaseModel->add($userInfo);
        }

        if(empty($userInfo["account"])){
            return $this->returnErrorNotice("请绑定手机号", -101);
        }

        //登陆成功存token
        $userInfo = $this->updUserInfo( $userInfo );
        return $this->returnSuccess($userInfo);
    }

    /**
        微信绑定手机号
    */
    public function wxBangDingMobile(){

        if( empty($_POST['open_id']) ) 
            return $this->returnErrorNotice("微信标示不能为空");
        if( empty($_POST['account']) ) 
            return $this->returnErrorNotice("手机号码不能为空");
        if( empty($_POST['code']) ) 
            return $this->returnErrorNotice("验证码不能为空");

        $openId = $_POST['open_id'];
        $account = $_POST['account'];
        $code = $_POST['code'];

        $userBaseModel = M("zuban_user_base", 0, "DB_DSN");
        $userInfo = $userBaseModel->where(array("wx_openid" => $openId))->find();

        if( !$userInfo )
            return $this->returnErrorNotice("帐号不存在");

        //这里检测一下手机号码和验证码是否正确
        $checkRes = $this->checkAccountByCode($account, $code);
        if(!$checkRes)
            return $this->returnErrorNotice("验证码错误");

        $userBaseModel->where(array("wx_openid" => $openId))->save(array("account" => $account));

        //登陆成功存token
        $userInfo = $this->updUserInfo( $userInfo );
        return $this->returnSuccess($userInfo);
    }


}