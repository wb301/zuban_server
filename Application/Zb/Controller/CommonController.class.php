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
        $token=$_REQUEST['token'];
        $userInfoModel = M('zuban_user_info', '', 'DB_DSN');
        $userInfo = $userInfoModel->where("`token` = '$token' ")->feild("`user_id`,`device`,`logitude`,`latitude`")->select();
        if (!$userInfo || count($userInfo) <= 0) {
            if ($isNotice) {
                return $this->returnErrorNotice("用户标识错误!");
            } else {
                return $this->returnSuccess(array());
            }
        }
        return $userInfo[0];
    }


}