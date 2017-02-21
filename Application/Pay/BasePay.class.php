<?php
/**
 * 支付抽象类
 * */
namespace Pay;
use Pay\AliPay\AliPay;
use Pay\WxPay\WxPay;
use Common\Controller\CommonController;

abstract class BasePay extends CommonController
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
    protected function payOrder($orderNo,$price,$tradeNo, $channel)
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
        $whereOrderProduct = array(
            'order_no' => $orderRs['order_no'],
            'status' => 0,
        );
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
        //1.订单状态变更
        $upDataOrder = array(
            'notice_trade_no' => $tradeNo,
            'status' => 1,
            'update_time' => $nowTime,
        );
        $upOrderResult = $transModel->table('zuban_order')->where($whereOrder)->save($upDataOrder);
        if(!$upOrderResult){
            $this->logPay('notify channel='.$channel.' updateOrder sql='.$orderModel->getLastSql(), 'ERR');
            $transModel->rollback();
            return $result;
        }
        if(in_array($orderRs['order_type'],array(0,1))) {
            //2.订单商品状态变更
            $upOrderProductResult = $transModel->db(0, 'DB_DSN')->table('mbfun_order_product')->where($whereOrderProduct)->setField("status", 1);
            if (!$upOrderProductResult) {
                $this->logPay('notify channel=' . $channel . ' updateOrderProduct sql=' . $orderProductModel->getLastSql(), 'ERR');
                $transModel->rollback();
                return $result;
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
        $upOrderPayRecordResult = $transModel->table('zuban_order_pay_record')->where($whereOrderPayRecord)->save($upDataOrderPayRecord);
         if(!$upOrderPayRecordResult){
            $this->logPay('notify channel='.$channel.' updatePayrecord sql='.$orderPayRecordModel->getLastSql(), 'ERR');
            $transModel->rollback();
            return $result;
        }
        //4.消费记录钱包核算
        $moneyHistory=array();
        //会员卡充值
        if($orderRs['order_type']==2){
            $moneyHistory[]=array(
                'user_id'=>$orderRs['user_id'],
                'price_type'=>1,
                'price_info'=>$orderRs['order_no'],
                'price'=>$price,
                'remark'=>'会员充值',
            );
            $moneyHistory[]=array(
                'user_id'=>$orderRs['user_id'],
                'price_type'=>6,
                'price_info'=>$orderRs['order_no'],
                'price'=>-$price,
                'remark'=>'会员充值',
            );
        }
        if($orderRs['order_type']==0){
            $moneyHistory[]=array(
                'user_id'=>$orderRs['user_id'],
                'price_type'=>1,
                'price_info'=>$orderRs['order_no'],
                'price'=>$price,
                'remark'=>'查看消费充值',
            );
            $moneyHistory[]=array(
                'user_id'=>$orderRs['user_id'],
                'price_type'=>6,
                'price_info'=>$orderRs['order_no'],
                'price'=>-$price,
                'remark'=>'会员充值',
            );
        }



        $moneyHistoryModel=M('zuban_user_money_history','','DB_DSN');
        $addMoneyHistoryResult = $transModel->table('zuban_user_money_history')->addAll($moneyHistory);
        if(!$upOrderPayRecordResult){
            $this->logPay('notify channel='.$channel.' updatePayrecord sql='.$orderPayRecordModel->getLastSql(), 'ERR');
            $transModel->rollback();
            return $result;
        }

        $transModel->commit(); 
        //******事务结束*********

        $result['code'] = 1; 
        $result['message'] = '成功'; 
        return $result;
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

}

