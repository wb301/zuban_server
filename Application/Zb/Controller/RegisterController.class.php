<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;

/**

	控制器层 调用service层

*/
class RegisterController extends CommonController
{

	/**
     * 用户名注册
     * @param string $content
     */
    public function registerByMobile()
    {

        $this->_POST();
        $keyAry = array(
            'account' => "手机号不能为空",
            'code' => "短信验证码不能为空",
            'password' => "密码不能为空",
            'region_code' => "地区不能为空",
            'region_name' => "地区名称不能为空",
            'head_img' => "",
            'nick_name' => "",
            'logitude' => "",
            'latitude' => ""
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        $account = $parameters['account'];
        $code = $parameters['code'];
        $password = $parameters['password'];
        $region_code = $parameters['region_code'];
        $region_name = $parameters['region_name'];

        //这里检测一下手机号码和验证码是否正确
        $checkRes = $this->checkAccountByCode($account, $code);
        if(!$checkRes)
            return $this->returnErrorNotice("验证码错误");

        $userBaseModel = M("zuban_user_base", '', "DB_DSN");
        $userInfo = $userBaseModel->where(array("account" => $account))->find();
        if($userInfo)
            return $this->returnErrorNotice("该账号已存在");

        $nowTime = date('Y-m-d H:i:s');
        $userInfo = array('user_id' => create_guid(),
        				  'account' => $account,
        				  'password' => md5($password),
        				  'head_img' => $parameters['head_img'] ? $parameters['head_img'] : 'https://ss3.bdstatic.com/70cFv8Sh_Q1YnxGkpoWK1HF6hhy/it/u=3443117432,1239143495&fm=21&gp=0.jpg',
                          'wx_openid' => '',
        				  'region_code' => $region_code,
                          'region_name' => $region_name,
                          'nick_name' => $parameters['nick_name'] ? $parameters['nick_name'] : '',
        				  'logitude' => $parameters['logitude'] ? $parameters['logitude'] : '',
        				  'latitude' => $parameters['latitude'] ? $parameters['latitude'] : '',
        				  'register_time' => $nowTime
        				  );
        //这里新增一下数据
        $userBaseModel->add($userInfo);

        return $this->returnSuccess(true);
    }

}