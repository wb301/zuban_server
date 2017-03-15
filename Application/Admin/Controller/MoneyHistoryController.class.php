<?php
namespace Admin\Controller;
use Admin\Controller\AdminCommonController;

class MoneyHistoryController extends AdminCommonController
{

    /**
        =======================   平台的操作   =======================
    */

    /**
        获取提现申请列表
    */
    public function getMoneyHistoryList(){

        $status = $_REQUEST["status"] ? $_REQUEST["status"] : 0;

        //获取自己的信息
        $userBase = $this->checkToken(1);

        $whereArr = array();
        if($status > 0){
            $whereArr["status"] = $status;
        }
        
        $moneyHistoryModel = M("admin_region_money_history", 0, "DB_DSN");
        $this->pageAry["total"] = $moneyHistoryModel->where($whereArr)->count();
        if($this->pageAry["total"] > 0){

            $this->setPageRow();
            $this->pageAry["list"] = $moneyHistoryModel->where($whereArr)->order("id DESC")->page($this->page, $this->row)->select();
            $this->pageAry["list"] = $this->pageAry["list"] ? $this->pageAry["list"] : array();
        }

        return $this->returnSuccess($this->pageAry);
    }

    /**
        修改地区管理员账号状态
    */
    public function updWithdrawStatus(){

        $keyAry = array(
            'id' => "用户标识不能为空"
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

        $withdrawModel = M("zuban_user_withdraw_history", 0, "DB_DSN");
        $whereArr = array("id" => $parameters["id"]);
        $withdrawInfo = $withdrawModel->where($whereArr)->find();
        $oldStatus = $withdrawInfo["status"];
        $userId = $withdrawInfo["user_id"];
        $price = $withdrawInfo["price"];

        if($oldStatus != 2){
            $this->returnErrorNotice('该笔提现申请已处理!');
        }

        $saveArr = array("status" => 1, "remark" => "提现成功");
        $withdrawModel->where($whereArr)->save($saveArr);

        $moneyModel = M("zuban_user_money_history", 0, "DB_DSN");
        $addArr = array("user_id" => $userId,
                        "price_type" => 5,
                        "price_info" => "提现",
                        "remark" => "提现成功",
                        "price" => $price,
                        "create_time" => date('Y-m-d'));

        $oldStatus = $moneyModel->add($addArr);

        $remark = "修改提现[".$id."],生成新数据:".json_encode($saveArr);
        $operation = "Withdraw-updWithdrawStatus-zuban_user_withdraw_history-".$id;
        $this->insertHistory($adminCode,$operation,$remark);

        return $this->returnSuccess(true);
    }

}