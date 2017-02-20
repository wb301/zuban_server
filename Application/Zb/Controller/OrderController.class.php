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
     * source     订单来源
     * receiver   收货人
     * phone  手机号码
     * @param $paymentAry    支付信息
     * 支付列表paymentAry参数: {payment,pay_type}
     * @param $payment       ON_LINE 在线支付、OFF_LINE 货到付款
     * @param $pay_type      WX 微信支付、ZFB 支付宝支付、CASH 现金支付、APPLE_PAY 苹果支付
     * @param $cartList      购物车信息
     * 列表集合参数:          [{product_code,num}]
     * @param $product_code      sku
     * @param $num          数量
     */
    public function createOrder()
    {
        $keyAry = array(
            'userId' => "用户标识不能为空！",
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
        $userId = $parameters['userId'];
        $allPrice = $parameters['allPrice'];
        $source = $parameters['source'];

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
        //生成订单号
        $orderId=$this->createCode("ORDER_CODE");
        $orderNo = time() . "" . (1000000000 + $orderId);
        $newOrderAry = array(
            'user_id' => $userId,
            'order_no' => $orderNo,
            'total_price' => $allPrice, //统计总价
            'from_source' => $parameters['source'],
            'price' => $allPrice, //商品统计价
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
            'pay_price' => $allPrice,
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
                'order_no' => $value['channel_code'].$orderNo,
                'dec_price' => '0.00',
                'price' => $value['spec_price'],
                'total_price' => $value['spec_price'] * $value['num'],
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

        $productRs= $orderProductModel->add($addProductList);
        if ($productRs <= 0) {
            $this->returnErrorNotice('订单商品维护中，请稍后再试!');
        }



    }

    //获取商品价格信息
    public function  getProductPrice($productList){

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