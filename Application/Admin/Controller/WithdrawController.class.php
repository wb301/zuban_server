<?php
namespace Admin\Controller;
use Admin\Controller\AdminCommonController;

class WithdrawController extends AdminCommonController
{

    /**
        =======================   平台的操作   =======================
    */

    /**
     * 提现申请
     * @param $token    用户标示
     */
    public function getUserWithdrawHistoryList(){

        $keyAry = array(
            'pageSize' => "",
            'pageIndex' => "",
            'startTime' => "",
            'endTime' => "",
            'name' => "",
            'status' => "",
            'from' => "",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        $this->setPageRow();
        $rs = array(
            'list' => array(),
            'total' => 0
        );
        $whereSql = " 1=1 ";
        if(strlen($parameters['status'])>0){
            $whereSql .= " AND `status`= '{$parameters['status']}' ";
        }
        /*if(strlen($parameters['name'])>0){
            $whereSql .= " AND `nick_name`= '{$parameters['name']}'  ";
        }*/
        if(strlen($parameters['from'])>0){
            $whereSql .= " AND `from`= '{$parameters['from']}' ";
        }
        if(strlen($parameters['startTime'])>0){
            $whereSql .= " AND `create_time`>= '{$parameters['startTime']}' ";
        }
        if(strlen($parameters['endTime'])>0){
            $whereSql .= " AND `create_time`<= '{$parameters['endTime']}' ";
        }
        $withdrawHistoryModel = M("zuban_user_withdraw_history", '', "DB_DSN");
        $withdrawCount = $withdrawHistoryModel->where($whereSql)->count();
        if ($withdrawCount <= 0) {
            $this->returnSuccess($rs);
        }
        $withdrawRs = $withdrawHistoryModel->where($whereSql)->order("`create_time` DESC ")->page($this->page, $this->row)->select();
        if (count($withdrawRs) <= 0) {
            $this->returnSuccess($rs);
        }
        $rs['list'] = $withdrawRs;
        $rs['total'] = $withdrawCount;
        $this->returnSuccess($rs);
    }
    /**
        提现完成
    */
    public function updWithdrawStatus(){

        $keyAry = array(
            'id' => "编号不能为空"
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