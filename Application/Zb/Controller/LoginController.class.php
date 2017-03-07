<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;

/**

	控制器层 调用service层

*/
class LoginController extends CommonController
{
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

}