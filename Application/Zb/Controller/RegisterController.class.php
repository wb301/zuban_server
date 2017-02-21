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

        if($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->returnErrorNotice('请求不是POST');
        }

        if( empty($_POST['account']) ) 
            return $this->returnErrorNotice("手机号不能为空");
        if( empty($_POST['code']) )
            return $this->returnErrorNotice("短信验证码不能为空");
        if( empty($_POST['password']) )
            return $this->returnErrorNotice("密码不能为空");
        if( empty($_POST['nick_name']) )
            return $this->returnErrorNotice("昵称不能为空");
        if( empty($_POST['region_code']) )
            return $this->returnErrorNotice("地区不能为空");

        $account = $_POST['account'];
        $code = $_POST['code'];
        $password = $_POST['password'];
        $nick_name = $_POST['nick_name'];
        $region_code = $_POST['region_code'];

        //这里检测一下手机号码和验证码是否正确
        $checkRes = $this->checkAccountByCode($account, $code);
        if(!$checkRes)
            return $this->returnErrorNotice("验证码错误");

        $userBaseModel = M("zuban_user_base", '', "DB_DSN");
        $userInfo = $userBaseModel->where(array("account" => $account))->find();
        if($userInfo)
            return $this->returnErrorNotice("该账号已存在");

        $nowTime = date('Y-m-d H:i:s');
        $userInfo = array('user_id' => $this->create_guid(),
        				  'account' => $account,
        				  'password' => md5($password),
        				  'head_img' => $_POST['head_img'] ? $_POST['head_img'] : 'https://ss3.bdstatic.com/70cFv8Sh_Q1YnxGkpoWK1HF6hhy/it/u=3443117432,1239143495&fm=21&gp=0.jpg',
                          'wx_openid' => '',
        				  'nick_name' => $nick_name,
        				  'region_code' => $region_code,
        				  'logitude' => $_POST['logitude'] ? $_POST['logitude'] : '',
        				  'latitude' => $_POST['latitude'] ? $_POST['latitude'] : '',
        				  'register_time' => $nowTime
        				  );

        //这里新增一下数据
        $userBaseModel->add($userInfo);

        return $this->returnSuccess(true);
    }

}