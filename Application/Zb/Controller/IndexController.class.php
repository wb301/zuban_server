<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
class IndexController extends CommonController {
    public function index(){
        $nowTime = date('Y-m-d');
        $userInfo = array(
            'user_id' => 111,
            'account' => 111,
            'password' => md5(11122),
            'wx_openid' => '',
            'nick_name' => 111,
            'register_time' => $nowTime
        );

        //这里新增一下数据
        $userBaseModel = M("zuban_user_base", '', "DB_DSN");
        $userBaseModel->add($userInfo);
       }
}