<?php
/**
 * 支付抽象类
 * */
namespace Pay;
use Pay\AliPay\AliPay;
use Pay\WxPay\WxPay;

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
        $orderModel = M('mbfun_order');
        $orderRs = $orderModel->db(0, 'DB_DSN')->where($whereOrder)->find();
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
        $orderProductModel = M('mbfun_order_product');
        $whereOrderProduct = array(
            'order_id' => $orderRs['id'],
            'status' => 0,
        );
        $orderProductRs = $orderProductModel->db(0, 'DB_DSN')->where($whereOrderProduct)->field("`barcode`, SUM(`num`) AS `num`,`price`, `sale_price`")->group("barcode")->select();
        if(empty($orderProductRs)){
            $this->logPay('notify channel='.$channel.' order product empty orderRs='. json_encode($orderRs), 'ERR');
            $result['message'] = '订单商品不存在';
            return $result;
        }

        //如果有范票使用记录 范票设置为已使用 状态2-3，如果订单为关闭订单(9)，检查范票是否被使用，如果使用返回false
        $orderDecRecordModel = M('mbfun_order_dec_record');
        $whereOrderDec = array(
            'order_id' => $orderRs['id'],
            'type'     => 2, 
        );
        $vouchersId = $orderDecRecordModel->db(0, 'DB_DSN')->where($whereOrderDec)->getField("supplement");
        if(!empty($vouchersId)){
            $userVouchersModel = M('mbfun_user_vouchers'); 
            $whereUserVouchers = array(
                'user_id' => $orderRs['user_id'],
                'is_delete' => 0,
                'id' => $vouchersId,
            );
            $userVoucherRs = $userVouchersModel->db(0, 'DB_DSN')->where($whereUserVouchers)->find();

            //验证用户范票
            $checkUserVouchers = $this->checkUserVouchersByOrderStatus($userVoucherRs, $orderRs['status']);
            if(!$checkUserVouchers){
                $commonAction = A('Common');
                $checkVoucherMailContent = '用户范票错误,orderNo='.$orderNo.' userVoucherRs='.json_encode($userVoucherRs);
                $commonAction->sendMailByIp($checkVoucherMailContent);
                $result['message'] = '用户范票错误';    
                $this->logPay('notify channel='.$channel.' checkUserVouchers '. $checkVoucherMailContent, 'ERR');
                return $result;
            }
        }

        
        //*******事务开始*********
        $transModel = M();
        $transModel->startTrans();
        //1.订单状态变更
        $upDataOrder = array(
            'pay_price' => $orderRs['price'],
            'notice_trade_no' => $tradeNo,
            'status' => 1,
            'update_time' => $nowTime,
        );
        $upOrderResult = $transModel->db(0, 'DB_DSN')->table('mbfun_order')->where($whereOrder)->save($upDataOrder);
        if(!$upOrderResult){
            $this->logPay('notify channel='.$channel.' updateOrder sql='.$orderModel->getLastSql(), 'ERR');
            $transModel->rollback();
            return $result;
        }
        //1.订单商品状态变更

        $upOrderProductResult= $transModel->db(0, 'DB_DSN')->table('mbfun_order_product')->where($whereOrderProduct)->setField("status", 1);
        if(!$upOrderProductResult){
            $this->logPay('notify channel='.$channel.' updateOrderProduct sql='.$orderProductModel->getLastSql(), 'ERR');
            $transModel->rollback();
            return $result;
        }
        //3.付款记录
        $whereOrderPayRecord = array(
            'order_id' => $orderRs['id'],
            'status' => 0,
        );
        $upDataOrderPayRecord = array(
            'status' => 1,
            'create_time' => $nowTime,
            'pay_type' => $channel,
        );
        $upOrderPayRecordResult = $transModel->db(0, 'DB_DSN')->table('mbfun_order_pay_record')->where($whereOrderPayRecord)->save($upDataOrderPayRecord);
         if(!$upOrderPayRecordResult){
            $this->logPay('notify channel='.$channel.' updatePayrecord sql='.$orderPayRecordModel->getLastSql(), 'ERR');
            $transModel->rollback();
            return $result;
        }

        //4.更新销量
        $upSaleResult = $this->updateProductSaled($orderProductRs);
        if(!$upSaleResult){
            $commonAction = A('Common');
            $updateSaleMailContent = '更新销量失败,orderNo='.$orderNo;
            $commonAction->sendMailByIp($updateSaleMailContent);
            $this->logPay('notify channel='.$channel.' updateSale ', 'ERR');
            $transModel->rollback();
            return $result;
        }
        
        $upStockLockResult = true;
        //5.更新库存锁量(状态9 不需要再次更新)
        if($orderRs['status'] != 9){
            $upStockLockResult = $this->setProductStockLockNumByOrderIdList(array($orderRs['id']));
        }
        if(!$upStockLockResult){
            $commonAction = A('Common');
            $updateStockLockMailContent = '更新库存锁量失败,orderNo='.$orderNo;
            $commonAction->sendMailByIp($updateStockLockMailContent);

            $this->logPay('notify channel='.$channel.' updateStockLock ', 'ERR');
            $transModel->rollback();
            return $result;
        }

        //6.更新用户范票状态
        if(!empty($vouchersId)){
            //更新范票为已使用
            $upDataUserVoucher = array(
                'status' => 3,
                'update_time' => $nowTime,
            );
            $whereUserVouchers = array(
                'user_id' => $orderRs['user_id'],
                'is_delete' => 0,
                'id' => $vouchersId,
            );
            $upUserVoucherResult = $transModel->db(0, 'DB_DSN')->table('mbfun_user_vouchers')->where($whereUserVouchers)->save($upDataUserVoucher);
            if(!$upUserVoucherResult){
                $commonAction = A('Common');
                $updateUserVoucherMailContent = '更新用户范票失败,orderNo='.$orderNo;
                $commonAction->sendMailByIp($updateUserVoucherContent);

                $this->logPay('notify channel='.$channel.' updateUserVoucherResult ', 'ERR');
                $transModel->rollback();
                $result['message'] = '更新用户范票失败';
                return $result;
            }
        }
        $transModel->commit(); 
        //******事务结束*********

        //批量更新库存
        $upStockResult = $this->updateSkuStocks($orderProductRs);
        if($upStockResult===false){
            //如果更新库存失败发邮件报警
            $mailContentUpStock = '更新库存失败，订单商品'.json_encode($orderProductRs);
            $commonAction = A('Common');
            $commonAction->sendMailByIp($mailContentUpStock);
            $this->logPay('notify channel='.$channel.' updateStock  orderProductRs='.json_encode($orderProductRs), 'ERR');
        }
        //用户积分
        $upUserIntegralResult = $this->setIntegralProductList($orderRs['user_id'], $orderProductRs, $orderRs['id'], 1);

        if(!$upUserIntegralResult){
            $this->logPay('notify channel='.$channel.' upUserIntegral ', 'ERR');
        }
        //支付成功释放限时抢购锁
        foreach($orderProductRs as $pK=>$pV)
        {
            if($this->checkOrderSecProduct($orderRs['order_no'], $pV['barcode'])){
                $this->releaseSecLock(substr($pV['barcode'], 0, 6), $pV['num']);
            }
        }
        $result['code'] = 1; 
        $result['message'] = '成功'; 
        return $result;
    }

    private function checkUserVouchersByOrderStatus($userVouchers, $orderStatus)
    {
        if(empty($userVouchers)){
            return false;
        }
        switch($orderStatus)
        {
        case 0 :
            if($userVouchers['status'] != 2){
                return false;
            }
            break;

        case 9 :
            if($userVouchers['status'] != 1){
                return false;
            }
            break;
        }
        return true;
    }

    /**
     * 更新销量
     *
     * */
    private function updateProductSaled($productList)
    {
        $addResult = true;
        $upResult = true;
        $barcodeList = array();

        foreach($productList as $productK=>$productV)
        {
            $barcodeList[] = $productV['barcode'];
        }
        if(empty($barcodeList)){
            return false;
        }
        $productSaledModel = M('mbfun_product_saled');
        $whereProductSaled = array(
            'barcode' => array('in', $barcodeList),
        );
        $saleBarcodeList = $productSaledModel->db(0, 'DB_DSN')->where($whereProductSaled)->getField("barcode", true);

        $upSql = '';
        $addSql    = '';
        $nowTime = date('Y-m-d H:i:s');
        //遍历商品列表，有则更新，无则插入
        foreach ($productList as $productK => $productV) 
        {
            $barcode = $productV["barcode"];
            $num = $productV["num"];
            if(in_array($barcode, $saleBarcodeList)){
                //更新销量
                $upSql .= " WHEN '$barcode' THEN `num`+$num ";
            } else {
                //新增销量
                $addSql .= "('$barcode','', $num, '$nowTime'),";
            }

        }
        // 批量创建新销量
        if ($addSql){
            $addSql = substr($addSql, 0, strlen($addSql)-1);
            $sql = "INSERT INTO `mbfun_product_saled` (`barcode`,`remark`,`num`,`create_time`) VALUES " . $addSql;
            $addResult = $productSaledModel->db(0, 'DB_DSN')->query($sql);
        }
        // 批量更新销量
        if ($upSql){
            $barcodeListStr = '';
            foreach($barcodeList as $barcodeListK=>$barcodeListV) {
                $barcodeListStr .= $barcodeListV.',';
            }
            $barcodeListStr = trim($barcodeListStr, ',');

            $sql = "UPDATE `mbfun_product_saled` SET `num` = CASE `barcode` " . $upSql . " END WHERE `barcode` IN ($barcodeListStr);";
            $upResult = $productSaledModel->db(0, 'DB_DSN')->query($sql);
        }

        if($upResult===false || $addResult===false){
            return false;
        }
        return true;
    }

    /**
     * 更新库存
     * */
    private function updateSkuStocks($productList)
    {
        $upSql = '';
        foreach($productList as $productK=>$productV)
        {
            $barcodeList[] = $productV['barcode'];

            $barcode = $productV["barcode"];
            $num = $productV["num"];
            $upSql .= " WHEN '$barcode' THEN `last_sync_stock`-$num ";
        }

        $barcodeListStr = $this->getListString($barcodeList);

        $channelCode = C('CHANNEL_CODE');
        $sql = "UPDATE `sc_channel_sku` SET `last_sync_stock` = CASE `sku` " . $upSql . " END WHERE `status` = 1 AND `channel_code` = '$channelCode' AND `last_sync_stock` > 0 AND `sku` IN ($barcodeListStr);";
        $channelSkuModel = M('sc_channel_sku');
        return $channelSkuModel->db(0, 'DB_STOCK')->query($sql);
    }

    protected function logPay($message,$level='INFO')
    {
        $now = date('Y-m-d H:i:s');
        $destination = C('LOG_PATH').'/pay_'.date('y_m_d').'.log';
        error_log("{$now} {$level}: {$message}\r\n", 3,$destination);
    }

    private function setProductStockLockNumByOrderIdList($orderIdList=null){
        $orderProductSqlStr = "";
        if($orderIdList && count($orderIdList) > 0){
            $orderIdListStr = join(",", $orderIdList);
            $orderProductSqlStr = " AND `order_id` IN ($orderIdListStr) ";
        }
        //需要更新的barcode
        $orderProductModel = M('mbfun_order_product');
        $barcodeList = $orderProductModel->db(1,'DB_DSN')->where("`status` >= 0 ".$orderProductSqlStr)->group("barcode")->getField("barcode", true);

        if(!$barcodeList || count($barcodeList) <= 0){
            return 0;
        }
        $barcodeListStr = $this->getListString($barcodeList);

        //包含以上barcode的所有未支付订单
        $sqlStr = "SELECT p.`barcode`, SUM(p.`num`) AS `num` FROM `mbfun_order_product` AS p LEFT JOIN `mbfun_order` AS o ON o.`id` = p.`order_id` AND p.`barcode` IN ($barcodeListStr)  WHERE o.`status` = 0  GROUP BY `barcode`";
        $orderProductRs = $orderProductModel->db(1,'DB_DSN')->query($sqlStr);

        $barcodeNumAry = array();
        foreach ($barcodeList as $k => $barcode) {
            $barcodeNumAry[$barcode] = 0;
            if($orderProductRs && count($orderProductRs) > 0){
                foreach ($orderProductRs as $key => $value) {
                    if($barcode == $value['barcode'] && intval($value['num']) > 0){
                        $barcodeNumAry[$barcode] = intval($value['num']);
                        break;
                    }
                }
            }
        }

        $orderProductLockModel = M('mbfun_order_product_lock');
        $havingBarcodeList = $orderProductLockModel->db(1,'DB_DSN')->where("`barcode` IN ($barcodeListStr) ")->group("barcode")->getField("barcode", true);
        if(!$havingBarcodeList || count($havingBarcodeList) <= 0){
            $havingBarcodeList = array();
        }

        $nowTime = date('Y-m-d H:i:s');
        $addLockSqlList = array(array());
        $addLockSqlStrList = array("");
        $updateLockSqlList = array(array());
        $updateLockSqlStrList = array("");
        foreach ($barcodeNumAry as $barcode => $num) {
            if(in_array($barcode, $havingBarcodeList)){
                $updateIndex = count($updateLockSqlStrList)-1;
                if(count($updateLockSqlList[$updateIndex]) >= 300){
                    array_push($updateLockSqlList, array());
                    array_push($updateLockSqlStrList, "");
                    $updateIndex += 1;
                }
                //更新销量
                array_push($updateLockSqlList[$updateIndex], $barcode);
                $updateLockSqlStrList[$updateIndex] .= " WHEN '$barcode' THEN  $num";
            }else{
                $addIndex = count($addLockSqlStrList)-1;
                if(count($addLockSqlList[$addIndex]) >= 500){
                    array_push($addLockSqlList, array());
                    array_push($addLockSqlStrList, "");
                    $addIndex += 1;
                }
                //新增销量
                array_push($addLockSqlList[$addIndex], $barcode);
                $addLockSqlStrList[$addIndex] .= "('$barcode', $num, '$nowTime'),";
            }
        }

        // 批量创建新销量
        if ($addLockSqlStrList[0] && strlen($addLockSqlStrList[0])) {
            foreach ($addLockSqlStrList as $key => $addLockSqlStr) {
                $addLockSqlStr = substr($addLockSqlStr, 0, strlen($addLockSqlStr) - 1) . ";";
                $sql = "INSERT INTO `mbfun_order_product_lock` (`barcode`,`num`,`create_time`) VALUES " . $addLockSqlStr;
                $orderProductLockModel->db(0, 'DB_DSN')->query($sql);
            }
        }
        // 批量更新销量
        if ($updateLockSqlStrList[0] && strlen($updateLockSqlStrList[0]) > 0) {
            foreach ($updateLockSqlStrList as $key => $updateLockSqlStr) {
                $barcodeListStr = $this->getListString($updateLockSqlList[$key]);
                $sql = "UPDATE `mbfun_order_product_lock` SET `num` = CASE `barcode` " . $updateLockSqlStr . " END WHERE `barcode` IN ($barcodeListStr);";
                $orderProductLockModel->db(0, 'DB_DSN')->query($sql);
            }
        }

        return 1;
    }
    protected function getListString($stringList){
        $listStr = "";
        foreach ($stringList as $key => $value) {
            $listStr .= ("'".$value."',");
        }

        //切除最后一个“,”
        $listStr = substr($listStr, 0, strlen($listStr) - 1) . "";

        return $listStr;
    }

    //购物积分
    protected function setIntegralProductList($userId,$productList,$orderNo){

        foreach($productList as $pK=>$pV)
        {
            $productList[$pK]['product_sys_code'] = substr($pV['barcode'],0,6);
        }
        $integral=0;
        $commonAction = A('Common');
        $productList=$commonAction->getProductIntegral($userId,$productList,'price');
        foreach($productList AS $key=>$value){
            $integral+=$value['integral']*$value['num'];
        }
        if($integral <= 0){
            return true;
        }

        $productRecord=array();
        foreach($productList AS $key=>$value){
            array_push($productRecord,array(
                "product_sys_code"=>$value['product_sys_code'],
                "barcode_sys_code"=>$value['barcode_sys_code'],
                "num"=>$value['num'],
                "price"=>$value['price'],
                "integral"=>$value['integral'],
                "double"=>$value['double']
            ));
        }
        $userIntegralRecordModel = M('mbfun_user_integral_record');
        $addIntegralRecord=array(
            'user_id' => $userId,
            'remark' => "购物送积分",
            'value' => $integral,
            'temp_code' => $orderNo,
            'type' => 4,
            'json' => json_encode($productRecord),
            'create_time' => date('Y-m-d H:i:s')
        );
        $addRs=$userIntegralRecordModel->db(1,'DB_DSN')->add($addIntegralRecord);
        if($addRs<=0){
            $mailContent="用户购物积分记录异常!用户ID:$userId 积分记录数据:".json_encode($addIntegralRecord);;
            $commonAction->sendMailByIp($mailContent);
            $this->logPay('notify channel='.$channel.' addUserIntegralRecord ' , 'ERR');
            return false;
        }
        return true;
    }

    protected function addNotifyLog($orderNo, $request)
    {
        $request = json_encode($request);
        $m = M('mbfun_log_pay_notify');
        //检查是否通知过
        $where = array(
            'order_no' => $orderNo,
        );
        $failRs = $m->db(0, 'DB_LOG')->where($where)->find();
        if(empty($failRs)){
            $data = array(
                'order_no' => $orderNo,
                'request' => $request,
                'create_time' => date('Y-m-d H:i:s'),
                'times' => 1,
            ); 
            $m->db(0, 'DB_LOG')->add($data);
        }else{
            $m->db(0, 'DB_LOG')->where($where)->setInc('times', 1);
        }
    }

    private function checkOrderSecProduct($orderNo, $barcode)
    {
        $orderSecModel = M('mbfun_temp_order_seckill_product');
        $where = array(
            'order_no' => $orderNo,
            'barcode'  => $barcode,
        );
        $orderSecCount = $orderSecModel->db(0, 'DB_ACTIVITY')->where($where)->count();
        if($orderSecCount <= 0){
            return false;
        }

        return true;
    }

    private function releaseSecLock($code, $num)
    {
        $secModel = M('mbfun_temp_product_seckill'); 
        $nowTime = date('Y-m-d H:i:s');
        $where = array(
           'start_time' => array('elt', $nowTime),
           'end_time' => array('egt', $nowTime),
           'product_sys_code' => $code,
        );
        return $secModel->db(0, 'DB_ACTIVITY')->where($where)->setDec('lock_num', $num);
    }

}

