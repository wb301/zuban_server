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
        $allMaxPrice = 0; // 订单流水金额
        $allIncomePrice = 0; // 平台收入金额
        $allLookPrice = 0; // 查看金额
        $allPercentagePrice = 0; // 平台抽成金额
        $allVipPrice = 0; // 会员金额
        $regionPercentagePrice = 0; // 代理商抽成金额
        $regionSurplusPrice = 0; // 代理商剩余金额
        $regionSettlementPrice = 0; // 代理商结算金额
        $userWithdrawPrice = 0; // 用户提现金额
        $userSurplusPrice = 0; // 用户剩余金额

        $orderModel = M("zuban_order", 0, "DB_DSN");
        $allMaxPrice = $orderModel->where(array("status" => array("IN", array(6, 10))))->SUM("price");

        // 平台
        $regionMoneyHistoryModel = M("admin_region_money_history", 0, "DB_DSN");
        $allHistoryList = $regionMoneyHistoryModel->where("`order_type` < 3")->group("order_type")->field("order_type,SUM(price) as price")->select();
        foreach ($allHistoryList as $key => $value) {
            $allIncomePrice += $value["price"];
            switch ($value["order_type"]) {
                case '0':
                    $allLookPrice = $value["price"];
                    break;
                case '1':
                    $allPercentagePrice = $value["price"];
                    break;
                case '2':
                    $allVipPrice = $value["price"];
                    break;
            }
        }

        // 代理商
        $regionHistoryList = $regionMoneyHistoryModel->where("`order_type` = 3")->group("price_type")->field("price_type,SUM(price) as price")->select();
        foreach ($regionHistoryList as $key => $value) {
            $regionSurplusPrice += $value["price"];
            switch ($value["price_type"]) {
                case '1':
                    $regionPercentagePrice = $value["price"];
                    break;
                case '2':
                    $regionSettlementPrice = $value["price"];
                    break;
            }
        }

        // 用户
        $userIncomePrice = 0;
        $userMoneyHistoryModel = M("zuban_user_money_history", 0, "DB_DSN");
        $allHistoryList = $userMoneyHistoryModel->group("price_type")->field("price_type,SUM(price) as price")->select();
        foreach ($allHistoryList as $key => $value) {
            switch ($value["price_type"]) {
                case '5':
                    $userWithdrawPrice = $value["price"];
                    break;
                case '3':
                    $userIncomePrice = $value["price"];
                    break;
            }
        }
        $userSurplusPrice = $userIncomePrice + $userWithdrawPrice;

        $priceInfo = array(
            array("name" => "平台流水",
                "mingxi" => "订单流水金额:".$allMaxPrice."元"),
            array("name" => "平台",
                "mingxi" => "收入金额:".$allIncomePrice."元    查看金额:".$allLookPrice."元     抽成金额:".$allPercentagePrice."元   会员金额:".$allVipPrice."元"),
            array("name" => "代销商",
                "mingxi" => "剩余金额:".$regionSurplusPrice."元    抽成金额:".$regionPercentagePrice."元    结算金额:".$regionSettlementPrice."元"),
            array("name" => "用户",
                "mingxi" => "剩余金额:".$userSurplusPrice."元      提现金额:".$userWithdrawPrice."元")
        );

        return $this->returnSuccess($priceInfo);
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
            'admin_code' => "",
            'status' => "",
            'region_code' => "",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        //获取自己的信息
        $userBase = $this->checkToken();
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
            $whereSql .= " AND `price_type`= '{$parameters['status']}' ";
        }
        if($userBase['manager_type']==0){
            $whereSql .= " AND `admin_code`= '{$userBase['admin_code']}' ";
        }else{
            if(strlen($parameters['admin_code'])>0){
                $whereSql .= " AND `admin_code`= '{$parameters['admin_code']}' ";
            }
        }
        if($userBase['manager_type']==0){
            $whereSql .= " AND `region_code`= '{$userBase['region_code']}' ";
        }else{
            if(strlen($parameters['region_code'])>0){
                $whereSql .= " AND `region_code`= '{$parameters['region_code']}' ";
            }
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
        $rs['report']['balance']=0;
        $balance=$adminRegionMoneyHistoryModel->where($whereSql)->SUM("price");//剩余
        if($balance){
            $rs['report']['balance']=abs($balance);
        }
        $rs['report']['withdrawal']=0;
        $withdrawal=$adminRegionMoneyHistoryModel->where("$whereSql AND `price_type`=2 ")->SUM("price");//提现
        if($withdrawal){
            $rs['report']['withdrawal']=abs($withdrawal);
        }
        $rs['report']['cumulative']=0;
        $cumulative=$adminRegionMoneyHistoryModel->where("$whereSql AND `price_type`=1 ")->SUM("price");//累计
        if($cumulative){
            $rs['report']['cumulative']=abs($cumulative);
        }
        $rs['report']['cumulative']=$cumulative;
        $rs['list'] = $withdrawRs;
        $rs['total'] = $withdrawCount;
        $this->returnSuccess($rs);
    }


    /**
     * 代理商结算
     * @param $token    用户标示
     */
    public function settlement(){

        $keyAry = array(
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
        $whereSql = " `region_code`!=1 ";
        if(strlen($parameters['region_code'])>0){
            $whereSql .= " AND `region_code`= '{$parameters['region_code']}' ";
        }
        $adminRegionMoneyHistoryModel = M("admin_region_money_history", '', "DB_DSN");
        //收入
        $cumulativePrice = $adminRegionMoneyHistoryModel->where("$whereSql AND `price_type`=1 AND `region_code`!=''")->field("`region_code`,sum(price) as price,`create_time`")->order("`create_time` DESC")->group('region_code')->select();
        //结算
        $withdrawalPrice = $adminRegionMoneyHistoryModel->where("$whereSql AND `price_type`=2 AND `region_code`!=''")->field("`region_code`,sum(price) as price,`create_time`")->order("`create_time` DESC")->group('region_code')->select();

        if(count($cumulativePrice)<=0){
            $this->returnSuccess($rs);
        }
        $region=array();
        foreach($cumulativePrice AS $key=>$value){
            $cumulativePrice[$key]['withdrawal']=0;
            $cumulativePrice[$key]['balance']=$value['price'];
            $cumulativePrice[$key]['region_name']='';
            array_push($region,$value['region_code']);
        }
        //结算
        $newWithdrawal=array();
        if(count($withdrawalPrice)>0){
            foreach($withdrawalPrice AS $key=>$value){
                $newWithdrawal[$value['region_code']]=$value;
            }
        }
        $userBaseModel = M("admin_region_manager", 0, "DB_DSN");
        $where['region_code']=array('IN',$region);
        $userInfo = $userBaseModel->where($where)->getField("`region_code`,`region_name`");
        foreach($cumulativePrice AS $key=>$value){
            if(isset($newWithdrawal[$value['region_code']])){
                $cumulativePrice[$key]['withdrawal']=abs($newWithdrawal[$value['region_code']]['price']);
                $cumulativePrice[$key]['create_time']=$newWithdrawal[$value['region_code']]['create_time'];
                $cumulativePrice[$key]['balance']=$newWithdrawal[$value['region_code']]['price']+$value['price'];
            }
            $cumulativePrice[$key]['region_name']=$userInfo[$value['region_code']];
        }
        $rs['list'] = $cumulativePrice;
        $rs['total'] = count($cumulativePrice);
        $rs['report']['balance']=0;//剩余
        $rs['report']['withdrawal']=0;//提现
        $rs['report']['cumulative']=0;//累计
        $this->returnSuccess($rs);
    }


    //核销接口
    //http://localhost/zuban_server/index.php?c=Admin&m=MoneyHistory&a=verification&bossCode=5ccdff89-387a-7e89-f84b-59dad88cd71c&region=425&price=0.01
    public function verification($region,$bossCode,$price,$remark){
        $userBase = $this->checkToken(1);
        $whereSql = " `admin_code`= '$bossCode' ";
        $adminRegionMoneyHistoryModel = M("admin_region_money_history", '', "DB_DSN");
        $lastprice=0;
        $last=$adminRegionMoneyHistoryModel->where($whereSql)->SUM("price");//剩余
        if($last){
            $lastprice =$last;
        }
        if(round($price,2)<=0){
            $this->returnErrorNotice('核销金额不可小于0元！');
        }
        if(round($price,2)>round($lastprice,2)){
            $this->returnErrorNotice('核销金额大于剩余金额！');
        }
        $addAry=array(
                'region_code' => $region,
                'admin_code' => $bossCode,
                'price_type' => 2,
                'remark' => '平台核销'.$remark,
                'price' => -$price,
                'price_info' =>'',
                'order_type' => 3,
                'create_time' => date('Y-m-d H:i:s')
        );
        $rs=$adminRegionMoneyHistoryModel->add($addAry);
        if(!$rs){
            $this->returnErrorNotice('核销失败！');
        }
        $this->returnSuccess('核销成功！');

    }
    //获取核销接口
    //http://localhost/zuban_server/index.php?c=Admin&m=MoneyHistory&a=getverification&bossCode=5ccdff89-387a-7e89-f84b-59dad88cd71c
    public function getverification($bossCode){

        $userBase = $this->checkToken(1);
        $whereSql = " `admin_code`= '$bossCode' ";
        $adminRegionMoneyHistoryModel = M("admin_region_money_history", '', "DB_DSN");
        $lastprice=0;
        $last=$adminRegionMoneyHistoryModel->where($whereSql)->SUM("price");//剩余
        if($last){
            $lastprice=$last;
        }
        $userBaseModel = M("admin_region_manager", 0, "DB_DSN");
        $userInfo = $userBaseModel->where($whereSql)->select();
        $userInfo[0]['lastprice']=$lastprice;
        $this->returnSuccess($userInfo);
    }





}