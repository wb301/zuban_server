<?php
namespace Zb\Controller;
use Common\Controller\CommonController AS Controller;

/**

	控制器层 调用service层

*/
class RegisterController extends Controller
{

	/**
     * 用户名注册
     * @param string $content
     */
    public function registerByMobile()
    {

        if( empty($_REQUEST['account']) ) 
            return $this->returnErrorNotice("手机号不能为空");
        if( empty($_REQUEST['code']) )
            return $this->returnErrorNotice("短信验证码不能为空");
        if( empty($_REQUEST['password']) )
            return $this->returnErrorNotice("密码不能为空");
        if( empty($_REQUEST['nick_name']) )
            return $this->returnErrorNotice("昵称不能为空");

        $account = $_REQUEST['account'];
        $code = $_REQUEST['code'];
        $password = $_REQUEST['password'];
        $nick_name = $_REQUEST['nick_name'];

        //这里检测一下手机号码和验证码是否正确
        $checkRes = $this->checkAccountByCode($account, $code);
        if(!$checkRes)
            return $this->returnErrorNotice("验证码错误");

        $nowTime = date('Y-m-d H:i:s');
        $userInfo = array('user_id' => $this->create_guid(),
        				  'account' => $account,
        				  'password' => md5($password),
        				  'wx_openid' => '',
        				  'nick_name' => $nick_name,
        				  'region_code' => $_REQUEST['region_code'] ? $_REQUEST['region_code'] : '',
        				  'logitude' => $_REQUEST['logitude'] ? $_REQUEST['logitude'] : '',
        				  'latitude' => $_REQUEST['latitude'] ? $_REQUEST['latitude'] : '',
        				  'register_time' => $nowTime
        				  );

        //这里新增一下数据
        $userBaseModel = M("zuban_user_base", '', "DB_DSN");
        $userBaseModel->add($userInfo);

        return $this->returnSuccess(true);
    }

}