<?php
/**
 * 支付抽象类
 * */
namespace Pay;
use Pay\AliPay\AliPay;
use Pay\WxPay\WxPay;
use Common\Controller\CommonController;

abstract class BasePay
{
    protected $config = array();
    const CHANNEL_WX = 'wx';
    const CHANNEL_ALIPAY = 'alipay';

    public static function getInstance($channel)
    {
        $instance = null;
        switch($channel)
        {
            case self::CHANNEL_ALIPAY : 
            $instance = new AliPay();
            break;
            case self::CHANNEL_WX :
            $instance = new WxPay();
            break;
        }
        return $instance;
    }
    protected function getConfig($channel)
    {
        $config = array();
        switch($channel)
        {
            case self::CHANNEL_ALIPAY :
                $config =  C('PAY_CONFIG_ALI');
                break;
            case self::CHANNEL_WX :
                $config = C('PAY_CONFIG_WX');
                break;
            default :

                break;
        }
        return $config;
    }
    public function payOrder($orderNo,$price,$tradeNo, $channel)
    {
        $result = array(
            'code' => -1,
            'message' => '系统错误',
        ); 
        //检查订单信息
        $whereOrder = array(
            'order_no' => $orderNo,
        );
        $nowTime = date('Y-m-d H:i:s');
        $orderModel = M('zuban_order','','DB_DSN');
        $orderRs = $orderModel->where($whereOrder)->find();
        if(empty($orderRs)){
            $this->logPay('notify channel='.$channel.' order empty orderNo='. $orderNo, 'ERR');
            $result['message'] = '订单不存在';
            return $result;
        }

        //如果已经支付，则直接返回成功
        $payedStatus = array(1,2,5,6,10,15);
        if(in_array($orderRs['status'], $payedStatus)){
            $result['code'] = 1; 
            $result['message'] = '成功'; 
            return $result;
        }
        //检查订单状态 0和9都通过
        if($orderRs['status'] != 0 && $orderRs['status'] != 9){

            $this->logPay('notify channel='.$channel.' order status orderRs='. json_encode($orderRs), 'ERR');
            $result['message'] = '订单状态错误'; 
            return $result;
        }
        //对比订单金额 保留2位小数比较
        if(round($orderRs['price'],2) != round($price,2)){
            $this->logPay('notify channel='.$channel.' order price orderRs='. json_encode($orderRs), 'ERR');
            $result['message']  = '支付金额与实际金额不一致';
            return $result;
        }
        //检查订单商品
        $orderProductModel = M('zuban_order_product','','DB_DSN');
        $orderProductRs = $orderProductModel->where("`order_no` ='$orderNo' AND `status` >= 0")->getField("product_sys_code");
        $whereOrderProduct = array(
            'order_no' => $orderRs['order_no'],
            'status' => 0,
        );
        //获取分类名称
        $productCategoryModel = M('zuban_product_category', '', 'DB_DSN');
        $category = $productCategoryModel->where("`product_sys_code`= '$orderProductRs'")->getField("`category_name`");

        //非充值会员订单查询商品
        if(in_array($orderRs['order_type'],array(0,1))){
            $orderProductRs = $orderProductModel->where($whereOrderProduct)->field("`product_sys_code`, SUM(`num`) AS `num`,`price`, `total_price`")->group("product_sys_code")->select();
            if(empty($orderProductRs)){
                $this->logPay('notify channel='.$channel.' order product empty orderRs='. json_encode($orderRs), 'ERR');
                $result['message'] = '订单商品不存在';
                return $result;
            }

        }
        //*******事务开始*********
        $transModel = M();
        $transModel->startTrans();
        //0.抽成
        $rakePrice=$this->settlementPrice($orderRs,$tradeNo);
        $returnPrice=$rakePrice['decPrice'];
        if(count($rakePrice['add'])>0){
            $regionMoney=M('admin_region_money_history','','DB_DSN');
            $insertRegionMoneyResult = $transModel->db(1, 'DB_DSN')->table('admin_region_money_history')->addAll($rakePrice['add']);
            if(!$insertRegionMoneyResult){
                $this->logPay('notify channel='.$channel.' insertRegionMoney sql='.$regionMoney->getLastSql(), 'ERR');
                $transModel->rollback();
                return $result;
            }
        }
        //1.订单状态变更
        $upDataOrder = array(
            'notice_trade_no' => $tradeNo,
            'status' => 10,
            'return_price' =>0,
            'update_time' => $nowTime,
        );
        //消费订单状态变成已支付
        if($orderRs['order_type']==1){
            $upDataOrder['status']=1;
            $upDataOrder['return_price']=$returnPrice;//获取
        }
        $upOrderResult = $transModel->db(1, 'DB_DSN')->table('zuban_order')->where($whereOrder)->save($upDataOrder);
        if(!$upOrderResult){
            $this->logPay('notify channel='.$channel.' updateOrder sql='.$orderModel->getLastSql(), 'ERR');
            $transModel->rollback();
            return $result;
        }
        if(in_array($orderRs['order_type'],array(0,1))) {
            //2.订单商品状态变更
            $upOrderProductResult = $transModel->db(1, 'DB_DSN')->table('zuban_order_product')->where($whereOrderProduct)->setField("status", 1);
            if (!$upOrderProductResult) {
                $this->logPay('notify channel=' . $channel . ' updateOrderProduct sql=' . $orderProductModel->getLastSql(), 'ERR');
                $transModel->rollback();
                return $result;
            }
            if($orderRs['order_type']){
                $orderProductRs = $transModel->db(1, 'DB_DSN')->table('zuban_order_product')->where("`order_no` ='$orderNo' AND `status` >= 0")->getField("product_sys_code",true);
                $productCode_str=getListString($orderProductRs);
                $updateAry = array(
                    'status' => 2,
                    'update_time' => date('Y-m-d H:i:s')
                );
                $updateProduct=$transModel->db(1, 'DB_DSN')->table('zuban_product_goods')->where("`product_sys_code`IN($productCode_str)")->setField($updateAry);
                if (!$updateProduct) {
                    $this->logPay('notify channel=' . $channel . ' updateProduct sql=' . $orderProductModel->getLastSql(), 'ERR');
                    $transModel->rollback();
                    return $result;
                }
            }
        }
        //3.付款记录
        $whereOrderPayRecord = array(
            'order_no' => $orderRs['order_no'],
            'status' => 0,
        );
        $upDataOrderPayRecord = array(
            'status' => 1,
            'create_time' => $nowTime,
            'pay_type' => $channel,
        );
        $orderPayRecordModel=M('zuban_order_pay_record','','DB_DSN');
        $upOrderPayRecordResult = $transModel->db(1, 'DB_DSN')->table('zuban_order_pay_record')->where($whereOrderPayRecord)->save($upDataOrderPayRecord);
         if(!$upOrderPayRecordResult){
            $this->logPay('notify channel='.$channel.' updatePayrecord sql='.$orderPayRecordModel->getLastSql(), 'ERR');
            $transModel->rollback();
            return $result;
        }
        //4.消费记录钱包核算
        $moneyHistory=$this->getHistyAry($orderRs,$price,$returnPrice,$category);
        $moneyHistoryModel=M('zuban_user_money_history','','DB_DSN');
        $addMoneyHistoryResult = $transModel->db(1, 'DB_DSN')->table('zuban_user_money_history')->addAll($moneyHistory);
        if(!$addMoneyHistoryResult){
            $this->logPay('notify channel='.$channel.' addMoneyHistory sql='.$moneyHistoryModel->getLastSql(), 'ERR');
            $transModel->rollback();
            return $result;
        }

        //5.会员卡充值 续费会员期限
        if($orderRs['order_type']==2){
            $vipTimeAry=$this->getVipTime($orderRs['user_id'],$orderRs['member_code'],$orderRs['price']);
            $vipModel=M('zuban_user_vip','','DB_DSN');
            $vipResult = $transModel->db(1, 'DB_DSN')->table('zuban_user_vip')->add($vipTimeAry);
            if(!$vipResult){
                $this->logPay('notify channel='.$channel.' addVipTime sql='.$vipModel->getLastSql(), 'ERR');
                $transModel->rollback();
                return $result;
            }
        }
        $transModel->commit();
        //******事务结束*********

        $result['code'] = 1;
        $result['message'] = '成功';
        return $result;
    }


    //消费记录
    protected function getHistyAry($orderInfo,$price,$lastPrice,$category){
        $moneyHistory=array();
        $vipName='银卡';
        switch($orderInfo['member_code']){
            case 1:$vipName='银卡';break;
            case 2:$vipName='金卡';break;
            case 3:$vipName='钻石';break;
            default:  $vipName='银卡';
        }
        switch($orderInfo['order_type']){
            case 0:
                $moneyHistory[]=array(
                    'user_id'=>$orderInfo['user_id'],
                    'price_type'=>1,
                    'price_info'=>$orderInfo['order_no'],
                    'price'=>$price,
                    'remark'=>'查看消费充值',
                    'create_time'=>date('Y-m-d H:i:s'),
                );
                $moneyHistory[]=array(
                    'user_id'=>$orderInfo['user_id'],
                    'price_type'=>2,
                    'price_info'=>$orderInfo['order_no'],
                    'price'=>-$price,
                    'remark'=>'查看['.$category.']消费 订单编号'.$orderInfo['order_no'],
                    'create_time'=>date('Y-m-d H:i:s'),
                );break;
            case 1:
                $moneyHistory[]=array(
                    'user_id'=>$orderInfo['user_id'],
                    'price_type'=>1,
                    'price_info'=>$orderInfo['order_no'],
                    'price'=>$price,
                    'remark'=>'购买消费充值',
                    'create_time'=>date('Y-m-d H:i:s'),
                );
                $moneyHistory[]=array(
                    'user_id'=>$orderInfo['user_id'],
                    'price_type'=>2,
                    'price_info'=>$orderInfo['order_no'],
                    'price'=>-$price,
                    'remark'=>'购买['.$category.']消费 订单编号'.$orderInfo['order_no'],
                    'create_time'=>date('Y-m-d H:i:s'),
                );
                /*if($lastPrice>0){
                    $moneyHistory[]=array(
                        'user_id'=>$orderInfo['product_user'],
                        'price_type'=>3,
                        'price_info'=>$orderInfo['order_no'],
                        'price'=>$lastPrice,
                        'remark'=>'收入',
                        'create_time'=>date('Y-m-d H:i:s'),
                    );break;
                }*/
                break;
            case 2:
                $moneyHistory[]=array(
                    'user_id'=>$orderInfo['user_id'],
                    'price_type'=>1,
                    'price_info'=>$orderInfo['order_no'],
                    'price'=>$price,
                    'remark'=>$vipName.'会员充值',
                    'create_time'=>date('Y-m-d H:i:s'),
                );
                $moneyHistory[]=array(
                    'user_id'=>$orderInfo['user_id'],
                    'price_type'=>6,
                    'price_info'=>$orderInfo['order_no'],
                    'price'=>-$price,
                    'remark'=>$vipName.'会员充值',
                    'create_time'=>date('Y-m-d H:i:s'),
                );break;
        }

        return $moneyHistory;

    }

    //会员续费
    protected function getVipTime($userId,$vip,$price){
        $nowTime=date('Y-m-d H:i:s');
        $vipAry=array(
            'user_id'=>$userId,
            'vip_type'=>$vip,
            'price'=>$price,
            'create_time'=>$nowTime,
        );
        $sysModel = M("admin_system_config", 0, "DB_DSN");
        $key=C('VIPLIST');
        $sysAry = $sysModel->where("`status` = 1 AND `config_key` = '$key' ")->getField("config_value");
        $time='';
        $config=json_decode($sysAry,true);
        foreach($config AS $key=>$value){
            if($value['level']==$vip){
                $time= $value['month'];
                break;
            }
        }
        //查询会员
        $vipModel=M('zuban_user_vip','','DB_DSN');
        $startTime=$vipModel->where("`start_time` <= '$nowTime' AND `end_time` > '$nowTime' AND `user_id` = '$userId'")->order("`id` desc")->getField("end_time");
        if(!$startTime){
            $startTime=$nowTime;
        }
        $vipAry['start_time']=$startTime;
        $vipAry['end_time']=date("Y-m-d H:i:s", strtotime("+$time months", strtotime($startTime)));
        return $vipAry;
    }

    protected function logPay($message,$level='INFO')
    {
        $now = date('Y-m-d H:i:s');
        $destination = C('LOG_PATH').'/pay_'.date('y_m_d').'.log';
        error_log("{$now} {$level}: {$message}\r\n", 3,$destination);
    }

    protected function addNotifyLog($orderNo, $request)
    {
        $request = json_encode($request);
        $m = M('zuban_log_pay_notify','','DB_DSN');
        //检查是否通知过
        $where = array(
            'order_no' => $orderNo,
        );
        $failRs = $m->where($where)->find();
        if(empty($failRs)){
            $data = array(
                'order_no' => $orderNo,
                'request' => $request,
                'create_time' => date('Y-m-d H:i:s'),
                'times' => 1,
            ); 
            $m->add($data);
        }else{
            $m->where($where)->setInc('times', 1);
        }
    }


    private function settlementPrice($orderInfo,$tradeNo)
    {
        $price = $orderInfo['price'];
        $orderNo = $orderInfo['order_no'];
        $nowTime = date('Y-m-d H:i:s');
        $decPrice = 0; //卖家抽成
        //判断订单类型
        $orderType = intval($orderInfo['order_type']);
        $addAry=array();
        if($orderType == 1){ //购买订单
           /* $userId = $orderInfo['user_id'];
            $regRegionCode = M('zuban_user_base','','DB_DSN')->where("`user_id` = '$userId' ")->getField("region_code");//注册地code
            $serverRegionCode = $orderInfo['from_source'];//服务地code
            $maxRegionCode = C('MAX_REGION_CODE');//平台默认

            $regionCodeStr = "'$regRegionCode','$serverRegionCode','$maxRegionCode'";

            //查询adminCode
            $regionAdminAry = M('admin_region_manager','','DB_DSN')->where("`region_code` IN ($regionCodeStr) AND `status` = 1")->getField("region_code, admin_code");

            $maxAdminCode = C('MAX_BOSS_CODE');
            if(isset($regionAdminAry[$maxRegionCode])){
                $maxAdminCode = $regionAdminAry[$maxRegionCode];
            }
            $regAdminCode = $maxAdminCode;
            if(isset($regionAdminAry[$regRegionCode])){
                $regAdminCode = $regionAdminAry[$regRegionCode];
            }
            $serverAdminCode = $maxAdminCode;
            if(isset($regionAdminAry[$serverRegionCode])){
                $serverAdminCode = $regionAdminAry[$serverRegionCode];
            }
            //系统配置
            $sysAry = M("admin_system_config", 0, "DB_DSN")->where("`status` = 1 AND `is_auto` = 0 ")->getField("config_key,config_value");
            $toMaxPrice = floatval($sysAry['AS_PLATFORM'] / C('DENO') * $price);
            $toRegPrice = floatval($sysAry['AS_REGISTERED'] / C('DENO') * $price);
            $toServerPrice = floatval($sysAry['AS_CONSUM'] / C('DENO') * $price);

            $remark = "服务购买收费,订单号:".$orderNo.",交易流水号:".$tradeNo."。";
            //插入收入记录
            $addAry=array(
                array(//平台
                    'region_code' => $maxRegionCode,
                    'admin_code' => $maxAdminCode,
                    'price_type' => 1,
                    'remark' => $remark,
                    'price' => $toMaxPrice,
                    'create_time' => $nowTime
                ),
                array(//注册地
                    'region_code' => $regRegionCode,
                    'admin_code' => $regAdminCode,
                    'price_type' => 1,
                    'remark' => $remark,
                    'price' => $toRegPrice,
                    'create_time' => $nowTime
                ),
                array(//服务地
                    'region_code' => $serverRegionCode,
                    'admin_code' => $serverAdminCode,
                    'price_type' => 1,
                    'remark' => $remark,
                    'price' => $toServerPrice,
                    'create_time' => $nowTime
                )
            );
            $decPrice = $price-$toMaxPrice-$toRegPrice-$toServerPrice;*/
        }
        else{
            //查看订单,会员充值订单，其他
            $toMaxPrice = $price;
            $decPrice = $price - $toMaxPrice;
            //抽成100%归平台
            $maxRegionCode = C('MAX_REGION_CODE'); //平台默认
            $maxAdminCode = M('admin_region_manager','','DB_DSN')->where("`region_code` = '$maxRegionCode' AND `status` = 1")->getField("admin_code");
            if(!$maxAdminCode){
                $maxAdminCode = C('MAX_BOSS_CODE');
            }
            $remarkMap = array(
                0 => "查看服务信息",
                2 => "充值会员"
            );
            $remark = "其他";
            if(isset($remarkMap[$orderType])){
                $remark = $remarkMap[$orderType];
            }
            $remark .="收费,订单号:".$orderNo.",交易流水号:".$tradeNo."。";
            $addAry=array(
                array(
                    'region_code' => $maxRegionCode,
                    'admin_code' => $maxAdminCode,
                    'price_type' => 1,
                    'remark' => $remark,
                    'price' => $toMaxPrice,
                    'price_info' => $orderNo,
                    'create_time' => $nowTime
                )
            );
        }
        return array('decPrice'=>$decPrice,'add'=>$addAry);
    }

}

