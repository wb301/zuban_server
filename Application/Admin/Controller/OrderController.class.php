<?php
namespace Admin\Controller;
use Admin\Controller\AdminCommonController;

class OrderController extends AdminCommonController
{


    /**
     * 订单列表
     * http://localhost/zuban_server/index.php?c=Admin&m=Order&a=orderCommonFilter&token=1111&status=ALL&pageSize=20&pageIndex=1&type=0&phone=15002164396&orderNo=&outNo=&name=&startTime=&endTime=&source=
     * 请求方式:get
     * 服务名:Wap
     * 参数:
     * @param $token 用户编号
     * @param $status 订单状态
     * @param $pageIndex 页码
     * @param $pageSize 页数
     * @param $isDelete 是否删除
     * * @param $startTime 开始时间
     * * @param $endTime 结束时间
     * * @param $orderNo 订单编号
     * * @param $outNo 外部交易号
     * * @param $phone 手机号
     * @param $name 姓名
     * @param $sourse 区域code
     *
     */
    public function orderCommonFilter()
    {

        $keyAry = array(
            'status ' => "",
            'pageSize' => "",
            'pageIndex' => "",
            'startTime' => "",
            'endTime' => "",
            'name' => "",
            'phone' => "",
            'outNo' => "",
            'orderNo' => "",
            'sourse' => "",
            'type' => "",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        $this->setPageRow();
        $statusMap = array(
            'ALL' => array(),
            'WaitingPay' => array(0),
            'WaitingConfirm' => array(1),
            'Ongoing' => array(5),
            'End' => array(6,9,10,15),
        );

        $rs = array(
            'list' => array(),
            'total' => 0
        );
        $status=$parameters['status'];
        $type=$parameters['type'];
        $statusList = array();
        if (isset($statusMap[$status])) {
            $statusList = $statusMap[$status];
        }
        //检测用户 TODO
        $userInfo=$this->checkToken();
        $whereSql = " 1=1 ";
        if(strlen($type)>0){
            $whereSql .= " AND `order_type`= $type ";
        }
        if(strlen($parameters['phone'])>0){
            $whereSql .= " AND `phone`= '{$parameters['phone']}' ";
        }
        if(strlen($parameters['name'])>0){
            $whereSql .= " AND `receiver`= '{$parameters['name']}'  ";
        }
        if(strlen($parameters['outNo'])>0){
            $whereSql .= " AND `notice_trade_no`= '{$parameters['outNo']}'  ";
        }
        if(strlen($parameters['orderNo'])>0){
            $whereSql .= " AND `order_no`= '{$parameters['orderNo']}' ";
        }
        if($userInfo['manager_type']==0){
            $whereSql .= " AND `from_source`= '{$userInfo['region_code']}' ";
        }else{
            if(strlen($parameters['sourse'])>0){
                $whereSql .= " AND `from_source`= '{$parameters['sourse']}' ";
            }
        }
        if(strlen($parameters['startTime'])>0){
            $whereSql .= " AND `create_time`>= '{$parameters['startTime']}' ";
        }
        if(strlen($parameters['endTime'])>0){
            $whereSql .= " AND `create_time`<= '{$parameters['endTime']}' ";
        }
        if ($statusList && count($statusList) > 0) {
            $statusListStr = getListString($statusList);
            $whereSql .= "AND `status` IN ($statusListStr) ";
        }
        $whereSql.=" AND `is_delete`=0 ";
        $orderModel = M('zuban_order', '', 'DB_DSN');
        $orderCount = $orderModel->where($whereSql)->count();
        if ($orderCount <= 0) {
            $this->returnSuccess($rs);
        }
        $orderRs = $orderModel->where($whereSql)->order("`create_time` DESC ")->page($this->page, $this->row)->select();
        if (count($orderRs) <= 0) {
            $this->returnSuccess($rs);
        }
        $orderNoList = array();
        $userIdList=array();
        foreach ($orderRs as $key => $value) {
            $status = $value['status'];
            $orderRs[$key]['status_name'] = $this->getSatusOrder($status);
            array_push($orderNoList, $value['order_no']);
            if(isset($value['user_id'])&&strlen($value['user_id'])>0){
                array_push($userIdList,$value['user_id']);
            }
            if(isset($value['product_user'])&&strlen($value['product_user'])>0){
                array_push($userIdList,$value['product_user']);
            }
        }
        if(count($userIdList)>0){
            $userBaseModel = M("zuban_user_base", '', "DB_DSN");
            $where['user_id']=array('IN',$userIdList);
            $userInfo = $userBaseModel->where($where)->getField("`user_id`,`head_img`,`nick_name`,`account`",true);
        }
        $orderNoListStr = getListString($orderNoList);
        $orderProductModel = M('zuban_order_product', '', 'DB_DSN');
        $productCodeList=array();
        $orderProductRs = $orderProductModel->where("`order_no` IN ($orderNoListStr) AND `status` >= 0")->select();
        if (!$orderProductRs || count($orderProductRs) <= 0) {
            $this->returnSuccess($rs);
        }
        foreach($orderProductRs AS $key=>$value){
            $productCodeList[]=$value['product_sys_code'];
        }

        $orderPayModel = M('zuban_order_pay_record', '', 'DB_DSN');
        $orderPayRs = $orderPayModel->where("`order_no` IN ($orderNoListStr)")->select();

        //绑定支付
        $orderRs = $this->getOrderPay($orderPayRs, $orderRs);

        //查询商品数据
        $productList = $this->getProductListByCode($productCodeList);
        //print_r($barcodeList);exit;
        //先绑定商品
        foreach ($orderProductRs as $ok => $ov) {
            $code = $ov['product_sys_code'];
            foreach ($productList as $pk => $pv) {
                if ($code == $pv['product_sys_code']) {
                    $orderProductRs[$ok]['product'] = $pv;
                    break;
                }
            }
        }
        //绑定到订单
        foreach ($orderRs as $key => $value) {
            $orderNo = $value['order_no'];
            $orderRs[$key]['buyers'] = $userInfo[$value['user_id']];
            $orderRs[$key]['seller'] = $userInfo[$value['product_user']];
            $orderRs[$key]['productList'] = array();
            foreach ($orderProductRs as $ok => $ov) {
                if ($orderNo == $ov['order_no']) {
                    array_push($orderRs[$key]['productList'], $ov);
                    $orderRs[$key]['category_name']=$ov['product']['category_name'];
                }
            }
        }
        $rs['list'] = $orderRs;
        $rs['total'] = $orderCount;
        $this->returnSuccess($rs);
    }


    //退款完成
    public function confirmReturn($orderNo)
    {

        $orderModel = M('zuban_order', '', 'DB_DSN');
        $orderRs = $orderModel->where(" `order_no` = '$orderNo' ")->field("`return_price`,`user_id`,`order_no`,`price`,`status`,`product_user`,`from_source`,`notice_trade_no`")->select();
        if (!$orderRs || count($orderRs) <= 0) {
            $this->returnErrorNotice('订单异常!');
        }
        $orderRs = $orderRs[0];
        $moneyHistory = array(
            'user_id' => $orderRs['user_id'],
            'price_type' => 4,
            'price_info' => $orderRs['order_no'],
            'price' => $orderRs['price'],
            'remark' => '申请退款记录'.'订单编号:'.$orderRs['order_no'],
            'create_time' => date('Y-m-d H:i:s'),
        );
        $moneyHistoryModel = M('zuban_user_money_history', '', 'DB_DSN');
        $addMoneyHistoryResult = $moneyHistoryModel->add($moneyHistory);
        if (!$addMoneyHistoryResult) {
            $this->returnErrorNotice('退款异常');
        }
        if (intval($orderRs['status']) != 11) {
            $this->returnErrorNotice('订单状态已变更!');
        }
        // 开始付款后的状态变更
        $updateAry = array(
            'status' => 12,
            'update_time' => date('Y-m-d H:i:s')
        );
        $result = $orderModel->where("`order_no` ='$orderNo'")->save($updateAry);
        if (!$result || count($result) <= 0) {
            $this->returnErrorNotice('订单状态变更失败!');
        }
        $this->changeProductStatus($orderNo,1);
        $this->returnSuccess(12);

    }


    /**
     * 订单列表
     * http://localhost/zuban_server/index.php?c=Admin&m=Order&a=orderCommonFilter&token=1111&status=ALL&pageSize=20&pageIndex=1&type=0&phone=15002164396&orderNo=&outNo=&name=&startTime=&endTime=&source=
     * 请求方式:get
     * 服务名:Wap
     * 参数:
     * @param $token 用户编号
     * @param $status 订单状态
     * @param $pageIndex 页码
     * @param $pageSize 页数
     * @param $isDelete 是否删除
     * * @param $startTime 开始时间
     * * @param $endTime 结束时间
     * * @param $orderNo 订单编号
     * * @param $outNo 外部交易号
     * * @param $phone 手机号
     * @param $name 姓名
     * @param $sourse 区域code
     *
     */
    public function orderReturnFilter()
    {

        $keyAry = array(
            'status ' => "",
            'pageSize' => "",
            'pageIndex' => "",
            'startTime' => "",
            'endTime' => "",
            'phone' => "",
            'orderNo' => "",
            'sourse' => ""
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        $userBase = $this->checkToken();
        $this->setPageRow();
        $rs = array(
            'list' => array(),
            'total' => 0
        );
        $status=$parameters['status'];
        //检测用户 TODO
        //$userInfo=$this->checkToken(true);
        $whereSql = " 1=1 ";
        if(strlen($status)>0){
            $whereSql .= " AND `status`= $status ";
        }
        if(strlen($parameters['phone'])>0){
            $whereSql .= " AND `phone`= '{$parameters['phone']}' ";
        }
        if(strlen($parameters['name'])>0){
            $whereSql .= " AND `receiver`= '{$parameters['name']}'  ";
        }
        if(strlen($parameters['orderNo'])>0){
            $whereSql .= " AND `order_no`= '{$parameters['orderNo']}' ";
        }
        if($userBase['manager_type']==0){
            $whereSql .= " AND `from_source`= '{$userBase['region_code']}' ";
        }else{
            if(strlen($parameters['sourse'])>0){
                $whereSql .= " AND `from_source`= '{$parameters['sourse']}' ";
            }
        }
        if(strlen($parameters['startTime'])>0){
            $whereSql .= " AND `create_time`>= '{$parameters['startTime']}' ";
        }
        if(strlen($parameters['endTime'])>0){
            $whereSql .= " AND `create_time`<= '{$parameters['endTime']}' ";
        }
        $whereSql.=" AND `is_delete`=0 AND `status` IN (11,12) ";
        $orderModel = M('zuban_order', '', 'DB_DSN');
        $orderCount = $orderModel->where($whereSql)->count();
        if ($orderCount <= 0) {
            $this->returnSuccess($rs);
        }
        $orderRs = $orderModel->where($whereSql)->order("`create_time` DESC ")->page($this->page, $this->row)->select();
        if (count($orderRs) <= 0) {
            $this->returnSuccess($rs);
        }
        $orderNoList = array();
        $userIdList=array();
        foreach ($orderRs as $key => $value) {
            $status = $value['status'];
            $orderRs[$key]['status_name'] = $this->getSatusOrder($status);
            array_push($orderNoList, $value['order_no']);
            if(isset($value['user_id'])&&strlen($value['user_id'])>0){
                array_push($userIdList,$value['user_id']);
            }
            if(isset($value['product_user'])&&strlen($value['product_user'])>0){
                array_push($userIdList,$value['product_user']);
            }
        }
        if(count($userIdList)>0){
            $userBaseModel = M("zuban_user_base", '', "DB_DSN");
            $where['user_id']=array('IN',$userIdList);
            $userInfo = $userBaseModel->where($where)->getField("`user_id`,`head_img`,`nick_name`,`account`",true);
        }
        $orderNoListStr = getListString($orderNoList);
        $orderProductModel = M('zuban_order_product', '', 'DB_DSN');
        $productCodeList=array();
        $orderProductRs = $orderProductModel->where("`order_no` IN ($orderNoListStr) AND `status` >= 0")->select();
        if (!$orderProductRs || count($orderProductRs) <= 0) {
            $this->returnSuccess($rs);
        }
        foreach($orderProductRs AS $key=>$value){
            $productCodeList[]=$value['product_sys_code'];
        }

        $orderPayModel = M('zuban_order_pay_record', '', 'DB_DSN');
        $orderPayRs = $orderPayModel->where("`order_no` IN ($orderNoListStr)")->select();

        //绑定支付
        $orderRs = $this->getOrderPay($orderPayRs, $orderRs);

        //查询商品数据
        $productList = $this->getProductListByCode($productCodeList);
        //先绑定商品
        foreach ($orderProductRs as $ok => $ov) {
            $code = $ov['product_sys_code'];
            foreach ($productList as $pk => $pv) {
                if ($code == $pv['product_sys_code']) {
                    $orderProductRs[$ok]['product'] = $pv;
                    break;
                }
            }
        }
        //绑定到订单
        foreach ($orderRs as $key => $value) {
            $orderNo = $value['order_no'];
            $orderRs[$key]['buyers'] = $userInfo[$value['user_id']];
            $orderRs[$key]['seller'] = $userInfo[$value['product_user']];
            $orderRs[$key]['productList'] = array();
            foreach ($orderProductRs as $ok => $ov) {
                if ($orderNo == $ov['order_no']) {
                    array_push($orderRs[$key]['productList'], $ov);
                    $orderRs[$key]['category_name']=$ov['product']['category_name'];
                }
            }
        }
        $rs['list'] = $orderRs;
        $rs['total'] = $orderCount;
        $this->returnSuccess($rs);
    }





}