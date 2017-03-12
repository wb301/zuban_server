<?php
namespace Admin\Controller;
use Admin\Controller\AdminCommonController;

class WithdrawController extends AdminCommonController
{

    /**
        =======================   平台的操作   =======================
    */

    /**
        获取提现申请列表
    */
    public function getWithdrawList(){

        $status = $_REQUEST["status"] ? $_REQUEST["status"] : 0;

        //获取自己的信息
        $userBase = $this->checkToken(1);
        $region_code = $parameters["region_code"];

        $whereArr = array();
        if($status > 0){
            $whereArr["status"] = $status;
        }
        
        $withdrawModel = M("zuban_user_withdraw_history", 0, "DB_DSN");
        $this->pageAry["total"] = $withdrawModel->where($whereArr)->count();
        if($this->pageAry["total"] > 0){

            $this->setPageRow();
            $this->pageAry["list"] = $withdrawModel->where($whereArr)->order("id DESC")->page($this->page, $this->row)->select();
            $this->pageAry["list"] = $this->pageAry["list"] ? $this->pageAry["list"] : array();
        }

        return $this->returnSuccess($this->pageAry);
    }

    /**
        修改地区管理员账号状态
    */
    public function updWithdrawStatus(){

        $this->_POST();
        $keyAry = array(
            'id' => "用户标识不能为空",
            'status' => "提现状态不能为空"
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        //获取自己的信息
        $userBase = $this->checkToken(1);
        $adminCode = $userBase["admin_code"];
        $id = $parameters["id"];
        $status = $parameters["status"];

        $withdrawModel = M("zuban_user_withdraw_history", 0, "DB_DSN");
        $whereArr = array("id" => $parameters["id"]);
        $oldStatus = $withdrawModel->where($whereArr)->getField("status");

        if($oldStatus != 2){
            $this->returnErrorNotice('该笔提现申请已处理!');
        }

        if($status == 1){
            $remark = "提现成功";
        }else{
            $remark = "提现失败";
        }
        $saveArr = array("status" => $status, "remark" => $remark);
        $withdrawModel->where($whereArr)->save($saveArr);

        $remark = "修改提现[".$id."],生成新数据:".json_encode($saveArr);
        $operation = "Withdraw-updWithdrawStatus-zuban_user_withdraw_history-".$id;
        $this->insertHistory($adminCode,$operation,$remark);

        return $this->returnSuccess(true);
    }

}