<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
class OrderController extends CommonController {
    public function index(){
        print_r(2);exit;
       }


    /**
     * 订单创建接口
     * 请求方式:post
     * 参数:
     * userId     用户id
     * memo       备注
     * type       固定值RETAIL
     * source     订单来源 固定值为5
     * receiver   收货人
     * teL_PHONE  手机号码
     * country    国家
     * province   省
     * city       市
     * county     区/县
     * address    详细地址
     * posT_CODE  邮政编码
     * senD_REQUIRE 配送要求
     * invoicE_TITLE  发票信息
     * SHOP_CODE    门店编码
     * uniquesessionid  时间戳
     * paymentAry     支付信息    支付列表集合参数[{paY_TYPE 11 WX num , aid cid}] payment: ON_LINE 在线支付、OFF_LINE 货到付款;paY_TYPE:WX 微信支付、ZFB 支付宝支付、CASH 现金支付、APPLE_PAY 苹果支付
     * cartList 购物车信息  列表集合参数[{cartId baecode 11 位码 num , aid cid}]
     * vouchers  范票优惠信息  范票id
     */
    public function createOrder()
    {
        $rs = $this->rs;
        $keyAry = array(
            'userId' => "用户标识不能为空！",
            'memo' => "",
            'type' => "来源信息不能为空", //1 WAP 2微信 3APP应用  4PC网站  5 APPC应用
            'source' => "来源信息不能为空",
            'receiver' => "收货人姓名不能为空",
            'teL_PHONE' => "收货人电话不能为空",
            'country' => "收货地址(国家)不能为空",
            'province' => "收货地址(省)不能为空",
            'city' => "收货地址(市)不能为空",
            'county' => "收货地址不能(区县)为空",
            'street' => "",
            'address' => "详细地址不能为空",
            'posT_CODE' => "",
            'senD_REQUIRE' => "",
            'invoicE_TITLE' => "",
            'uniquesessionid' => "",
            'paymentAry' => "支付信息不能为空",   //支付列表集合参数{payment 付款方式 固定值 ON_LINE , paY_TYPE 支付类型(微信或支付宝),makE_AMOUNT 支付金额}
            'cartList' => "商品信息不能为空",
            'vouchers' => "",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->errorRs('请求失败!');
        }

        if (!isset($parameters['invoicE_TITLE']) || strlen($parameters['invoicE_TITLE']) <= 0) {
            $parameters['invoicE_TITLE'] = "";
        }
        //支付方式
        $paymentAry = json_decode($parameters['paymentAry'], true);
        $paymentAry = $paymentAry[0];
        if (!isset($paymentAry['payment'])) {
            $paymentAry['payment'] = "ON_LINE";
        }
        if (!isset($paymentAry['paY_TYPE'])) {
            $this->errorRs('支付类型不能为空!');
        }
        if (!isset($paymentAry['makE_AMOUNT'])) {
            $this->errorRs('支付金额不能为空!');
        }
        //商品skuList
        $cartList = json_decode($parameters['cartList'], true);
        if (!$cartList || count($cartList) <= 0) {
            $this->errorRs('商品信息不能为空!');
        }

        $activityIdList = array();
        $barcodeList = array();
        $cartIds = array();
        $productSysCodeList = array();
        foreach ($cartList as $key => $value) {
            //判断是否缺参数
            if (!isset($value['barcode']) || strlen($value['barcode']) < 11 || !isset($value['num'])) {
                $this->errorRs('商品信息有误!');
            }
            array_push($barcodeList, $value['barcode']);
            array_push($cartIds, $value['cartId']);

            if (isset($value['aid']) && intval($value['aid']) > 0) {
                array_push($activityIdList, intval($value['aid']));
            }
            $productSysCodeList[] = substr($value['barcode'], 0, 6);
        }

        //检查是否有非卖品
        $isNoSale = $this->checkIsNoSale($productSysCodeList);
        if($isNoSale>0){
            $this->errorRs('订单中含有非卖品!');
        }

        $nowTime = date('Y-m-d H:i:s');

        //先检测活动开关表数据
        if (count($activityIdList) > 0) {
            $activityIdListStr = $this->getListString($activityIdList);
            $tempNormalActivityModel = M('mbfun_temp_normal_activity');
            $tempNormalActivityCount = $tempNormalActivityModel->db(0, 'DB_TEMP')->where("`json` IN ($activityIdListStr) AND `is_delete` = 0 AND `type` = 1 AND `start_time` <= '$nowTime' AND `end_time` >= '$nowTime' ")->select();
            if ($tempNormalActivityCount < count($activityIdList)) {
                $this->errorRs('已有活动已过期，请更改订单后重试!');
            }
        }

        //检测用户
        $userId = $parameters['userId'];
        if (isset($parameters['token'])) {
            $userId = $this->getUserIdByToken($parameters['token'], true);
        }
        $this->checkUserId($userId, '', false, true);

        // 验证订单是否重复提交
        $orderModel = M('mbfun_order');
        $checkCode = $parameters['uniquesessionid'];
        if ($checkCode && strlen($checkCode) > 0) {
            $orderCount = intval($orderModel->db(0, 'DB_DSN')->where("`user_id` = '$userId' AND `status` = 0 AND `check_code` = '$checkCode'")->count());
            if ($orderCount > 0) {
                $this->errorRs('订单信息已生成!');
            }
        }

        $newOrderAry = array(
            'user_id' => $userId,
            'invoice_title' => $parameters['invoicE_TITLE'],
            'total_price' => $priceAry['totalPrice'], //统计总价
            'dec_price' => $priceAry['dec_price'],
            'from_type' => $parameters['type'],
            'from_source' => $parameters['source'],
            'trans_price' => $priceAry['trans_price'], //运费
            'price' => $priceAry['price'], //商品统计价
            'status' => 0,//未支付状态
            'memo' => $parameters['memo'],
            'send_require' => $parameters['senD_REQUIRE'],
            'create_time' => $nowTime,
            'update_time' => $nowTime,
            'receiver' => $parameters['receiver'],
            'phone' => $parameters['teL_PHONE'],
            'country' => $parameters['country'],
            'province' => $parameters['province'],
            'city' => $parameters['city'],
            'county' => $parameters['county'],
            'street' => '',
            'address' => $parameters['address'],
            'post_code' => $parameters['posT_CODE'],
            'check_code' => $checkCode
        );

        $orderId = $orderModel->db(0, 'DB_DSN')->add($newOrderAry);
        if ($orderId <= 0) {
            $this->errorRs('商品维护中，请稍后再试!');
        }

        //成功后开始批量插入商品barcode信息
        $addSqlStr = "";
        $productList = $priceAry['productList'];
        foreach ($productList as $key => $value) {
            $barcode = $value['barcode_sys_code'];
            $marketPrice = $value['market_price'];
            $specPrice = $value['spec_price'];
            $salePrice = $value['sale_price'];
            $num = $value['num'];
            $totalPrice = $value['total_price'];
            $decPrice = $value['dec_price'];
            $collocationId = $value['cid'];

            $addSqlStr .= "($orderId, $collocationId, '$barcode', $marketPrice, $specPrice,$salePrice, $num, $totalPrice, $decPrice, 0, '$nowTime'),";
        }


        $this->successRs(array(), 1, $orderNo, $fieldArr);
    }


    /**
     * 订单列表
     * */
    public function getOrderList(){

    }

    /**
     * 订单详情
     * */
    public function getOrderDetails(){

    }


    /**
     * 删除订单
     * */
    public function deleteOrder(){

    }

    /**
     * 发货
     */
    public function deliveryOrder($orderno)
    {
        
    }





    /**
     * 确认收货接口
     * 请求方式:get
     * 服务名:Order
     * 参数:
     * @param $orderId 订单id
     * @param $token  token
     * status 5->10
     */
    public function orderConfirm($token, $orderId)
    {

    }



    /**
     * @desc 支付回调
     * */
    public function notify()
    {
        import("@.Pay.BasePay");
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
        import("@.Pay.BasePay");
        $pay = \Pay\BasePay::getInstance('wx');
        $pay->prePay();
    }

}