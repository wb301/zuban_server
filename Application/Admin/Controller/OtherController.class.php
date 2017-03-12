<?php
namespace Admin\Controller;
use Admin\Controller\AdminCommonController;

class OtherController extends AdminCommonController
{
	public function getHistoryList()
	{
		$adminCode = C('MAX_BOSS_CODE');
		$this->setPageRow();

		$historyModel = M("admin_operation_history", 0, "DB_DSN");

		$this->pageAry['total'] = intval($historyModel->where("`admin_code` = '$adminCode'")->count());

		if($this->pageAry['total'] <= 0){
            return $this->pageAry;
        }

        $this->pageAry['list'] = $historyModel->where("`admin_code` = '$adminCode'")->page($this->page,$this->row)->select();

        $this->returnSuccess($this->pageAry);
	}
}