<?php
namespace Admin\Controller;
use Common\Controller\CommonController;

/**

	控制器层 调用service层

*/
class AdminCommonController extends CommonController
{

	//操作记录插入
	protected function insertHistory($adminCode,$operation,$remark)
	{
		// $operation = "模块_方法_表_id";
		M("admin_operation_history", 0, "DB_DSN")->add(array(
			'admin_code' => $adminCode,
			'operation' => $operation,
			'remark' => $remark,
			'create_time' => date('Y-m-d H:i:s')
		));
	}

	/**
     * info：token验证
     * params:token
     * return:
     */
    protected function checkToken($checkManager = 0, $isNotice = 1)
    {
        $token=isset($_REQUEST['token'])?$_REQUEST['token']:'';
        $userInfoModel = M('admin_region_manager', '', 'DB_DSN');
        $userList = $userInfoModel->where("`token` = '$token' ")->select();
        if (!$userList || count($userList) <= 0) {
            if ($isNotice) {
                return $this->returnErrorNotice("用户标识错误!", -999);
            } else {
                return null;
            }
        }

        $userInfo = $userList[0];
        if($checkManager){
        	$managerType = $userInfo["manager_type"];
	        if($managerType <= 0){
	            $this->returnErrorNotice('权限不足!');
	        }
        }
        return $userInfo;
    }
}