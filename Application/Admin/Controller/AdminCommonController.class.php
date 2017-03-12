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
}