<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
class OrderController extends CommonController {

    /**
     * 订单创建接口
     * http://localhost/zuban_server/index.php?c=Zb&m=Order&a=createOrder&token=1111&source=36&receiver=%E9%99%86%E5%B9%BF&phone=110&paymentAry={%22payment%22:%22ON_LINE%22,%22pay_type%22:%22WX%22}&cartList=[{%22product_sys_code%22:%221%22,%22num%22:%221%22}]&allPrice=1&order_type=1&product_user=738e3568-b01c-362b-49ad-964b6c3817bf&check_code=888333
     * 请求方式:post
     * 参数:
     * token     用户id
     * memo       备注
     * source     订单来源
     * receiver   收货人
     * check_code 验证code
     * phone  手机号码
     * order_type 订单类型 0 查看订单，1购买订单。
     * @param $paymentAry    支付信息
     * 支付列表paymentAry参数: {payment,pay_type}
     * @param $payment       ON_LINE 在线支付、OFF_LINE 货到付款
     * @param $pay_type      WX 微信支付、ZFB 支付宝支付、CASH 现金支付、APPLE_PAY 苹果支付
     * @param $cartList      购物车信息
     * 列表集合参数:          [{product_sys_code,num}]
     * @param $product_code      sku
     * @param $num          数量
     */
    public function createOrder()
    {
        $keyAry = array(
            'source' => "来源信息不能为空",
            'check_code' => "",
            'paymentAry' => "支付信息不能为空",   //支付列表集合参数{payment 付款方式 固定值 ON_LINE , pay_type 支付类型(微信或支付宝),amount支付金额}
            'allPrice' => "支付金额异常!",
            'cartList' => "商品异常!",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        //支付方式
        $paymentAry = json_decode($parameters['paymentAry'], true);
        if (!isset($paymentAry['payment'])) {
            $paymentAry['payment'] = "ON_LINE";
        }
        if (!isset($paymentAry['pay_type'])) {
            $this->returnErrorNotice('支付类型异常!');
        }

        $nowTime = date('Y-m-d H:i:s');
        //检测用户
        $userInfo=$this->checkToken(true);
        $userId=$userInfo['user_id'];
        if($userId==$parameters['product_user']){
            $this->returnErrorNotice('不能自己给自己下单!');
        }
        $allPrice = $parameters['allPrice'];
        $this->checkUserId($userId,'', true);
        // 验证订单是否重复提交
        $orderModel = M('zuban_order','','DB_DSN');
        $checkCode = $parameters['check_code'];
        if ($checkCode && strlen($checkCode) > 0) {
            $orderCount = intval($orderModel->where("`user_id` = '$userId' AND `status` = 0 AND `check_code` = '$checkCode' ")->count());
            if ($orderCount > 0) {
                $this->returnErrorNotice('订单信息已生成!');
            }
        }
        $productList = json_decode($parameters['cartList'], true);
        $productList=$this->getProductPrice($productList);
        $price=0;
        foreach ($productList as $key => $value) {
            if($value['price']<0){
                $this->returnErrorNotice('商品价格错误!');
            }
            if(!$parameters['order_type']&&$value['look_price']<0){
                $this->returnErrorNotice('查看价格错误!');
            }
            if($value['status']<=0){
                $this->returnErrorNotice('商品状态已变更!');
            }
            if($parameters['order_type']){
                $price+=$value['price'];
            }else{
                $price+=$value['look_price'];
            }
        }
        if($allPrice<$price){
            $this->returnErrorNotice('商品价格已变更!');
        }
        //生成订单号
        $orderId=$this->createCode("ORDER_CODE");
        $orderNo = time() . "" . (1000000000 + $orderId);
        $newOrderAry = array(
            'user_id' => $userId,
            'order_no' => $orderNo,
            'order_type' => $parameters['order_type'],
            'product_user' => $parameters['product_user'],
            'total_price' => $price, //统计总价
            'from_source' => $parameters['source'],
            'price' => $price, //商品统计价
            'status' => 0,//未支付状态
            'memo' => '',
            'create_time' => $nowTime,
            'update_time' => $nowTime,
            'receiver' => "",
            'phone' => "",
            'check_code' => '',
        );
        $orderId = $orderModel->add($newOrderAry);
        if ($orderId <= 0) {
            $this->returnErrorNotice("订单维护中，请稍后再试,");
        }
        // 开始生成未支付的记录
        $newOrderPayAry = array(
            'order_no' => $orderNo,
            'payment' => $paymentAry['payment'],
            'pay_type' => $paymentAry['pay_type'],
            'pay_price' => $price,
            'create_time' => $nowTime,
            'status' => 0 //未支付
        );
        $orderPayRecordModel = M('zuban_order_pay_record','','DB_DSN');
        $orderPayId = $orderPayRecordModel->add($newOrderPayAry);
        if ($orderPayId <= 0) {
            $this->returnErrorNotice('订单生成维护中，请稍后再试!');
        }
        $addProductList=array();
        foreach ($productList as $key => $value) {
            $_ = array(
                'product_sys_code'=>$value['product_sys_code'],
                'order_no' => $orderNo,
                'dec_price' => '0.00',
                'price' => $value['price'],
                'total_price' => $value['price'] * $value['num'],
                'num' => $value['num'],
                'status' => 0,
                'create_time' => $nowTime,
            );
            array_push($addProductList,$_);
        }
        if (count($addProductList)<=0) {
            $this->returnErrorNotice('订单商品维护中，请稍后再试!');
        }
        $orderProductModel=M('zuban_order_product','','DB_DSN');

        $productRs= $orderProductModel->addAll($addProductList);
        if ($productRs <= 0) {
            $this->returnErrorNotice('订单商品维护中，请稍后再试!');
        }
        $rs=array(
            'price'=>$price,
            'order_no'=>$orderNo
        );
        $this->returnSuccess($rs,'下单成功！');

    }


    /**
     * 会员购买订单
     * http://localhost/zuban_server/index.php?c=Zb&m=Order&a=createMembersOrder&token=1111&source=36&paymentAry={%22payment%22:%22ON_LINE%22,%22pay_type%22:%22WX%22}&allPrice=20&vip=1&check_code=
     * 请求方式:post
     * 参数:
     * token     用户id
     * source     订单来源
     * allPrice
     * check_code 验证code
     * member_code 会员编号
     * @param $paymentAry    支付信息
     * 支付列表paymentAry参数: {payment,pay_type}
     * @param $payment       ON_LINE 在线支付、OFF_LINE 货到付款
     * @param $pay_type      WX 微信支付、ZFB 支付宝支付、CASH 现金支付、APPLE_PAY 苹果支付
     */
    public function createMembersOrder()
    {
        $keyAry = array(
            'source' => "来源信息不能为空",
            'vip' => "请选择充值会员卡类型",
            'paymentAry' => "支付信息不能为空",   //支付列表集合参数{payment 付款方式 固定值 ON_LINE , pay_type 支付类型(微信或支付宝),amount支付金额}
            'allPrice' => "支付金额异常!",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        //支付方式
        $paymentAry = json_decode($parameters['paymentAry'], true);
        if (!isset($paymentAry['payment'])) {
            $paymentAry['payment'] = "ON_LINE";
        }
        if (!isset($paymentAry['pay_type'])) {
            $this->returnErrorNotice('支付类型异常!');
        }

        $nowTime = date('Y-m-d H:i:s');
        //检测用户
        $userInfo=$this->checkToken(true);
        $userId=$userInfo['user_id'];
        if($userId==$parameters['product_user']){
            $this->returnErrorNotice('不能自己给自己下单!');
        }
        $allPrice = $parameters['allPrice'];
        $this->checkUserId($userId,'', true);
        // 验证订单是否重复提交
        $orderModel = M('zuban_order','','DB_DSN');
        $checkCode = $parameters['check_code'];
        if ($checkCode && strlen($checkCode) > 0) {
            $orderCount = intval($orderModel->where("`user_id` = '$userId' AND `status` = 0 AND `check_code` = '$checkCode' ")->count());
            if ($orderCount > 0) {
                $this->returnErrorNotice('订单信息已生成!');
            }
        }
        $price=0;
        $vipList=json_decode($this->getSysConfig(C('VIPLIST')),true);
        foreach($vipList AS $key=>$value){
            if($value['level']==$parameters['vip']){
                $price=$value['price'];
                break;
            }
        }
        //获取会员卡价格
        if($allPrice!=$price){
            $this->returnErrorNotice('价格错误!');
        }
        //生成订单号
        $orderId=$this->createCode("ORDER_CODE");
        $orderNo = time() . "" . (1000000000 + $orderId);
        $newOrderAry = array(
            'user_id' => $userId,
            'order_no' => $orderNo,
            'order_type' => 2,
            'total_price' => $price, //统计总价
            'from_source' => $parameters['source'],
            'price' => $price, //商品统计价
            'status' => 0,//未支付状态
            'remark' => '会员卡充值',
            'member_code' => $parameters['vip'],
            'create_time' => $nowTime,
            'update_time' => $nowTime,
            'phone' => "",
            'check_code' => '',
        );
        $orderId = $orderModel->add($newOrderAry);
        if ($orderId <= 0) {
            $this->returnErrorNotice("订单维护中，请稍后再试,");
        }
        // 开始生成未支付的记录
        $newOrderPayAry = array(
            'order_no' => $orderNo,
            'payment' => $paymentAry['payment'],
            'pay_type' => $paymentAry['pay_type'],
            'pay_price' => $price,
            'create_time' => $nowTime,
            'status' => 0 //未支付
        );
        $orderPayRecordModel = M('zuban_order_pay_record','','DB_DSN');
        $orderPayId = $orderPayRecordModel->add($newOrderPayAry);
        if ($orderPayId <= 0) {
            $this->returnErrorNotice('订单生成维护中，请稍后再试!');
        }
        $rs=array(
            'price'=>$price,
            'order_no'=>$orderNo
        );
        $this->returnSuccess($rs,'支付成功！');

    }



    /**
     * 我的订单列表
     * http://localhost/zuban_server/index.php?c=Zb&m=Order&a=orderCommonFilter&token=1111&status=ALL&pageSize=20&pageIndex=1&type=0
     * 请求方式:get
     * 服务名:Wap
     * 参数:
     * @param $token 用户编号
     * @param $status 订单状态
     * @param $pageIndex 页码
     * @param $pageSize 页数
     * @param $isDelete 是否删除
     */
    public function orderCommonFilter($token, $status = "ALL",$type = 0)
    {

        if (!$token || strlen($token) <= 0 || !$status || strlen($status) < 0) {
            $this->returnErrorNotice('参数错误!');
        }
        $this->setPageRow();
        $statusMap = array(
            'ALL' => array(),
            'WaitingPay' => array(0),
            'WaitingConfirm' => array(1),
            'Ongoing' => array(5),
            'End' => array(6,10),
        );

        $rs = array(
            'list' => array(),
            'total' => 0
        );
        $statusList = array();
        if (isset($statusMap[$status])) {
            $statusList = $statusMap[$status];
        }
        //检测用户
        $userInfo=$this->checkToken(true);
        $userId=$userInfo['user_id'];
        $whereSql = " `user_id` = '$userId' ";
        if($type){
            $whereSql = " `product_user` = '$userId' ";
        }
        if ($statusList && count($statusList) > 0) {
            $statusListStr = getListString($statusList);
            $whereSql .= "AND `status` IN ($statusListStr) ";
        }
        $whereSql.=" AND `is_delete`=0  AND `order_type`!= 2 ";
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
            $userInfo = $userBaseModel->where($where)->getField("`user_id`,`head_img`,`nick_name`",true);
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
                }
            }
        }
        $rs['list'] = $orderRs;
        $rs['total'] = $orderCount;
        $this->returnSuccess($rs);
    }





    /**
     * 订单详情
     * http://localhost/zuban_server/index.php?c=Zb&m=Order&a=getOrderDetails&token=422c7de3a240bbc32b684657cd947bd9&orderNo=14878565271001000008
     * */
    public function getOrderDetails($orderNo,$token){

        if ( strlen($token) <= 0 ||strlen($orderNo) < 0) {
            $this->returnErrorNotice('参数错误!');
        }
        //检测用户
        $userInfo=$this->checkToken(true);
        $userId=$userInfo['user_id'];
        $orderModel = M('zuban_order','','DB_DSN');
        $orderRs = $orderModel->where("`user_id` = '$userId' AND `order_no` = '$orderNo'")->order("`create_time` DESC ")->select();
        if (count($orderRs) <= 0) {
            $this->returnErrorNotice('订单不存在或已经删除!');
        }
        $orderRs=$orderRs[0];
        $orderNo=$orderRs['order_no'];
        $orderRs['status_name']=$this->getSatusOrder($orderRs['status']);
        $userIdList[]=$orderRs['user_id'];
        $userIdList[]=$orderRs['product_user'];
        if(count($userIdList)>0){
            $userBaseModel = M("zuban_user_base", '', "DB_DSN");
            $where['user_id']=array('IN',$userIdList);
            $userInfo = $userBaseModel->where($where)->getField("`user_id`,`head_img`,`nick_name`",true);
        }
        $orderProductModel = M('zuban_order_product','','DB_DSN');
        $orderProductRs = $orderProductModel->where("`order_no` ='$orderNo' AND `status` >= 0")->select();
        if (!$orderProductRs || count($orderProductRs) <= 0) {
            $this->returnErrorNotice('订单商品数据异常!');
        }
        $orderPayModel = M('zuban_order_pay_record','','DB_DSN');
        $orderPayRs = $orderPayModel->where("`order_no` ='$orderNo'")->select();

        //绑定支付
        $orderRs['paymentList'] = $orderPayRs[0];
        $proCodeList = array();
        foreach ($orderProductRs as $key => $value) {
            $proCodeList[]=$value['product_sys_code'];
        }
        //查询商品数据
        $productList = $this->getProductListByCode($proCodeList);

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
        $orderRs['productList']=$orderProductRs[0];
        $orderRs['buyers'] = $userInfo[$orderRs['user_id']];
        $orderRs['seller'] = $userInfo[$orderRs['product_user']];
        $this->returnSuccess($orderRs);

    }


    /**
     * 0 查看订单，1购买订单
     * 确认订单 订单价格
     * http://localhost/zuban_server/index.php?c=Zb&m=Order&a=getOderPrice&productSysCode=100022&num=1&type=0
     * */
    public function getOderPrice($productSysCode,$num,$type=0){

        $rs=array(
            'product'=>array(),
            'userInfo'=>array(),
        );
        $product=$this->getProductListByCode(array($productSysCode));
        foreach($product AS $key=>$value){
            if($value['status']!=1){
                $this->returnErrorNotice('商品已下架!');
            }
            if($type==0) {
                if($value['look_price']<=0){
                    $this->returnErrorNotice('商品查看价格异常!');
                }
                $rs['product']['product_price'] = $value['look_price'];
                if($num!=1){
                    $this->returnErrorNotice('购买一次即可!');
                }
            }else{
                if($value['price']<=0){
                    $this->returnErrorNotice('商品价格异常!');
                }
                $rs['product']['product_price'] = $value['price'];
            }
            $rs['product']['num']= $num;
            $rs['product']['allPrice']= $num*$rs['product']['product_price'];
        }
        $userId=$product[0]['user_id'];
        $rs['userInfo']=$this->getUserInfoByUserId($userId);
        if(count($rs['product'])<=0||count($rs['userInfo'])<=0){
            $this->returnErrorNotice('数据异常!');
        }
        $this->returnSuccess($rs);
    }


    /**
     * 删除订单
     * */
    public function deleteOrder(){

    }

    /**
     * http://localhost/zuban_server/index.php?c=Zb&m=Order&a=deliveryOrder&token=1111&orderNo=14876127851000103530
     * 发货
     */
    public function deliveryOrder($orderNo)
    {
        $rs=$this->updateOrderStatus($orderNo,1,5);
        if(count($rs)<=0){
            $this->returnErrorNotice('发货失败!');
        }
        $orderProductModel = M('zuban_order_product','','DB_DSN');
        $orderProductRs = $orderProductModel->where("`order_no` ='$orderNo' AND `status` >= 0")->getField("product_sys_code",true);
        if(count($orderProductRs)>0){
            $productCode_str=getListString($orderProductRs);
            $productModel = M('zuban_product_goods','','DB_DSN');
            $updateAry = array(
                'status' => 2,
                'update_time' => date('Y-m-d H:i:s')
            );
            $productModel->where("`product_sys_code`IN($productCode_str)")->setField($updateAry);
        }
        $this->returnSuccess('发货成功！');
    }


    /**
     * 更改订单状态
     * @param $token 用户token
     * @param $orderNo 订单No
     * @param $checkStatus
     * @param $status 状态
     * @return bool
     */
    protected function updateOrderStatus($orderNo, $checkStatus, $status)
    {

        if (strlen($orderNo) <= 0) {
            $this->returnErrorNotice('参数错误!');
        }
        //检测用户userId
        $userId = $this->checkToken(1)['user_id'];
        $orderModel = M('zuban_order', '', 'DB_DSN');
        $orderRs = $orderModel->where("`user_id` = '$userId' AND `order_no` = '$orderNo' ")->field("`return_price`,`user_id`,`order_no`,`price`,`status`,`product_user`")->select();
        if (!$orderRs || count($orderRs) <= 0) {
            $this->returnErrorNotice('订单编号错误!');
        }
        $orderRs = $orderRs[0];
        if (intval($orderRs['status']) != $checkStatus) {
            $this->returnErrorNotice('订单状态已变更!');
        }
        // 开始付款后的状态变更
        $updateAry = array(
            'status' => $status,
            'update_time' => date('Y-m-d H:i:s')
        );
        $result = $orderModel->where("`order_no` ='$orderNo'")->save($updateAry);
        if (!$result || count($result) <= 0) {
            $this->returnErrorNotice('订单状态变更失败!');
        }
        return $orderRs;
    }





    /**
     * http://localhost/zuban_server/index.php?c=Zb&m=Order&a=orderConfirm&token=a69c61a9416b393cf4ad28a2fd575839&orderNo=14878564061001000007
     * 确认收货接口
     * 请求方式:get
     * 服务名:Order
     * 参数:
     * @param $orderNo 订单No
     * status 5->10
     */
    public function orderConfirm($orderNo)
    {
        $rs=$this->updateOrderStatus($orderNo,5,6);
        if(count($rs)<=0){
            $this->returnErrorNotice('确认失败!');
        }
        $moneyHistory=array(
            'user_id'=>$rs['product_user'],
            'price_type'=>3,
            'price_info'=>$rs['order_no'],
            'price'=>$rs['return_price'],
            'remark'=>'收入',
            'create_time'=>date('Y-m-d H:i:s'),
        );
        $moneyHistoryModel=M('zuban_user_money_history','','DB_DSN');
        $addMoneyHistoryResult = $moneyHistoryModel->add($moneyHistory);
        if(!$addMoneyHistoryResult){
            $this->returnErrorNotice('收货异常');
        }
        $orderProductModel = M('zuban_order_product','','DB_DSN');
        $orderProductRs = $orderProductModel->where("`order_no` ='$orderNo' AND `status` >= 0")->getField("product_sys_code",true);
        if(count($orderProductRs)>0){
            $productCode_str=getListString($orderProductRs);
            $productModel = M('zuban_product_goods','','DB_DSN');
            $updateAry = array(
                'status' => 1,
                'update_time' => date('Y-m-d H:i:s')
            );
            $productModel->where("`product_sys_code`IN($productCode_str)")->setField($updateAry);
        }
        $this->returnSuccess('确认成功！');

    }



    /**
     * 退款
     * http://localhost/zuban_server/index.php?c=Zb&m=Order&a=orderReturn&token=1111&orderNo=14876127851000103530
     * 请求方式:get
     * 服务名:Order
     * 参数:
     * @param $orderNo 订单No
     */
    public function orderReturn($orderNo){
        if (strlen($orderNo) <= 0) {
            $this->returnErrorNotice('参数错误!');
        }
        //检测用户userId
        $userId = $this->checkToken(1)['user_id'];
        $orderModel = M('zuban_order', '', 'DB_DSN');
        $orderRs = $orderModel->where("`user_id` = '$userId' AND `order_no` = '$orderNo' ")->field("`return_price`,`user_id`,`order_no`,`price`,`status`,`order_type`")->select();
        if (!$orderRs || count($orderRs) <= 0) {
            $this->returnErrorNotice('订单编号错误!');
        }
        $orderRs = $orderRs[0];
        if ($orderRs['order_type']!=1) {
            $this->returnErrorNotice('该订单不支持退款!');
        }
        if ($orderRs['status']!=1) {
            $this->returnErrorNotice('该订单状态有误!');
        }
        //退款记录
        $orderReturnAry = array(
            'order_no' => $orderNo,
            'user_id' => $orderRs['user_id'],
            'reason' => '申请退款',
            'return_money' => $orderRs['return_price'],
            'create_time' => date('Y-m-d H:i:s'),
            'status' => 1
        );
        $orderReturnModel = M('zuban_order_return','','DB_DSN');
        $orderPayId = $orderReturnModel->add($orderReturnAry);
        if ($orderPayId <= 0) {
            $this->returnErrorNotice('退款失败，请稍后再试!');
        }
        $this->returnSuccess('申请成功!');
    }


    /**
     * @desc 支付回调
     * */
    public function notify()
    {
        switch($_REQUEST['channel'])
        {
            case 'alipay' :
                $pay = \Pay\BasePay::getInstance('alipay');
                $pay->notify();
                break;
            case 'wx' :
                $pay = \Pay\BasePay::getInstance('wx');
                $pay->notify();
                break;
            default :

                break;
        }
    }

    /**
     * @desc 微信预支付
     * */
    public function prePay()
    {
        $pay = \Pay\BasePay::getInstance('wx');
        $pay->prePay();
    }



}