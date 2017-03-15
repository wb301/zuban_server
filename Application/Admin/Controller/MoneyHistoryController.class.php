<?php
namespace Admin\Controller;
use Admin\Controller\AdminCommonController;

class MoneyHistoryController extends AdminCommonController
{

    /**
        =======================   平台的操作   =======================
    */

    /**
        获取收支明细
    */
    public function getMoneyHistoryList(){

        $status = $_REQUEST["status"] ? $_REQUEST["status"] : 0;
        $orderNo = $_REQUEST["orderNo"] ? $_REQUEST["orderNo"] : '';
        $startTime = $_REQUEST["sTime"] ? $this->fixDate($_REQUEST["sTime"]) : "1991-01-01 00:00:00";
        $endTime = $_REQUEST["eTime"] ? $this->fixDate($_REQUEST["eTime"]) : "2911-01-01 00:00:00";

        //获取自己的信息
        $userBase = $this->checkToken(1);

        $whereArr = array("create_time" => array("between", array($startTime, $endTime)));
        if($status > 0){
            $whereArr["price_type"] = $status;
        }
        if(strlen($orderNo) > 0){
            $whereArr["proce_info"] = $orderNo;
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
        获取抽成数据
    */
    public function getMaxPriceInfo(){

        //获取自己的信息
        $userBase = $this->checkToken(1);

        $maxPriceInfo = array("maxPrice" => 0,
                              "maxPercentagePrice" => 0,
                              "maxVipPrice" => 0,
                              "regionPercentagePrice" => 0);

        $orderModel = M("zuban_order", 0, "DB_DSN");
        $maxPriceInfo["maxPrice"] = $orderModel->where(array("status" => array("IN", array(6, 10))))->SUM("price");
        $maxPriceInfo["maxPrice"] = $maxPriceInfo["maxPrice"] ? $maxPriceInfo["maxPrice"] : 0;

        $moneyHistoryModel = M("zuban_user_money_history", 0, "DB_DSN");
        $maxPriceInfo["maxVipPrice"] = $moneyHistoryModel->where("`price_type` = 6")->SUM("price");
        $maxPriceInfo["maxVipPrice"] = $maxPriceInfo["maxVipPrice"] ? abs($maxPriceInfo["maxVipPrice"]) : 0;

        $regionMoneyHistoryModel = M("admin_region_money_history", 0, "DB_DSN");
        $maxPriceInfo["maxPercentagePrice"] = $regionMoneyHistoryModel->where("`region_code` = 1 AND `price_type` = 1")->SUM("price");
        $maxPriceInfo["regionPercentagePrice"] = $regionMoneyHistoryModel->where("`region_code` > 1 AND `price_type` = 1")->SUM("price");
        $maxPriceInfo["maxPercentagePrice"] = $maxPriceInfo["maxPercentagePrice"] ? $maxPriceInfo["maxPercentagePrice"] : 0;
        $maxPriceInfo["regionPercentagePrice"] = $maxPriceInfo["regionPercentagePrice"] ? $maxPriceInfo["regionPercentagePrice"] : 0;

        return $this->returnSuccess($maxPriceInfo);
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



    /**
     * 代理商分成
     * @param $token    用户标示
     */
    public function getDividedList(){

        $keyAry = array(
            'pageSize' => "",
            'pageIndex' => "",
            'startTime' => "",
            'endTime' => "",
            'status' => "",
            'region_code' => "",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        $this->setPageRow();
        $rs = array(
            'report'=>array(
                'balance'=>0,
                'withdrawal'=>0,
                'cumulative'=>0,
            ),
            'list' => array(),
            'total' => 0
        );
        $whereSql = " 1=1 ";
        if(strlen($parameters['status'])>0){
            $whereSql .= " AND `status`= '{$parameters['status']}' ";
        }
        if(strlen($parameters['region_code'])>0){
            $whereSql .= " AND `region_code`= '{$parameters['region_code']}' ";
        }
        if(strlen($parameters['startTime'])>0){
            $whereSql .= " AND `create_time`>= '{$parameters['startTime']}' ";
        }
        if(strlen($parameters['endTime'])>0){
            $whereSql .= " AND `create_time`<= '{$parameters['endTime']}' ";
        }
        $adminRegionMoneyHistoryModel = M("admin_region_money_history", '', "DB_DSN");
        $withdrawCount = $adminRegionMoneyHistoryModel->where($whereSql)->count();
        if ($withdrawCount <= 0) {
            $this->returnSuccess($rs);
        }
        $withdrawRs = $adminRegionMoneyHistoryModel->where($whereSql)->order("`create_time` DESC ")->page($this->page, $this->row)->select();
        if (count($withdrawRs) <= 0) {
            $this->returnSuccess($rs);
        }
        $rs['report']['balance']=$adminRegionMoneyHistoryModel->where($whereSql)->SUM("price");//剩余
        $rs['report']['withdrawal']=$adminRegionMoneyHistoryModel->where("$whereSql AND `price_type`=2 ")->SUM("price");//提现
        $rs['report']['cumulative']=$adminRegionMoneyHistoryModel->where("$whereSql AND `price_type`=1 ")->SUM("price");//累计
        $rs['list'] = $withdrawRs;
        $rs['total'] = $withdrawCount;
        $this->returnSuccess($rs);
    }

}