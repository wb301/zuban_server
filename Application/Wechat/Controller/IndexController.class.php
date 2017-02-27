<?php
namespace Wechat\Controller;
use Platform\Controller\PaymentController;
use Platform\Model\OrderModel;
use Platform\Model\WxPreOrderModel;
use Think\Exception;
use Wechat\Model\JsApiPay;
use Wechat\Model\WxPayApi;
use Wechat\Model\WxPayException;
use Wechat\Model\WxPayUnifiedOrder;
use Think\Controller;
use Think\Log;
use Wechat\Model\YoufanMerchantChargeConfig;
use Wechat\Model\YoufanMerchantChargeNotify;
use Wechat\Model\YoufanOfflineConfig;
use Wechat\Model\YoufanOfflineNotify;
use Wechat\Model\YoufanWapConfig;
use Wechat\Model\YoufanWapNotify;

class IndexController extends PaymentController {
    public function index(){
        $this->display();
    }

    /**
     * 生成qr预支付订单
     * @throws WxPayException
     */
    public function qrpay()
    {
        Log::record('wechat qr pay begin',Log::DEBUG,true);
        $out_trade_no = $_REQUEST['order_no'] ? $_REQUEST['order_no'] : '';
        if(!$out_trade_no) {
            $this->returnErrorNotice('参数order_no不能为空');
        }
        $returnPage = C('WX_SUCCESS_RETURN_URL');
        $needPay = $this->checkOrderNeedPay($out_trade_no);
        if(!$needPay) {
            header("Location:".$returnPage);
        }

        if(isset($_REQUEST['code']) && $_REQUEST['code']) {
            Log::record('code is:'.$_REQUEST['code'],Log::DEBUG,true);
            $codeInSession = session('wap_wechat_code_'.$out_trade_no);
            Log::record('code in session is:'.$codeInSession,Log::DEBUG,true);
            if($codeInSession == $_REQUEST['code']) {
                //如果用户再一次会到提交支付页面，我们直接让他到订单列表页面
                header("Location:".$returnPage);
            }
        }
        $body = $_REQUEST['body'] ? $_REQUEST['body'] : $out_trade_no;
        if(!$body) {
            $this->returnErrorNotice('参数body不能为空');
        }
        if(isset($_REQUEST['total_fee']) && $_REQUEST['total_fee']>0) {
            $total_fee = $_REQUEST['total_fee'];
        }else {
            $total_fee = OrderModel::getOrderTotalFee($out_trade_no);
            $total_fee = $total_fee * 100;
        }
        if(!$total_fee) {
            $this->returnErrorNotice('参数total_fee不能为0');
        }
        $config = new YoufanWapConfig();
        $wechatOrderId = $config->getMchId().date("YmdHis");
        $tools = new JsApiPay($config);
        $baseUrl = base_url();
        Log::record('base url:'.$baseUrl,Log::DEBUG,true);
        $baseUrl .= '?order_no='.$out_trade_no.'&body='.$body.'&total_fee='.$total_fee;
        Log::record($baseUrl,Log::DEBUG,true);
        $openId = $tools->GetOpenid($baseUrl);
        Log::record('open id--------:'.$openId,Log::DEBUG,true);
        $input = new WxPayUnifiedOrder();
        $input->setConfig($config);
        $input->SetBody($body);
        $input->SetAttach($out_trade_no);
        $input->SetOut_trade_no($wechatOrderId);
        $input->SetTotal_fee($total_fee);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        //$input->SetGoods_tag("test");
        $input->SetNotify_url($config->getNotifyUrl());
        $input->SetTrade_type("NATIVE");
        $input->SetOpenid($openId);
        $productIds = OrderModel::getOrderProductIds($out_trade_no);
        $productIdStr= implode(',',$productIds);
        $input->SetProduct_id($productIdStr);
        $order = WxPayApi::unifiedOrder($input);
        Log::record('order info:'.json_encode($order),Log::DEBUG,true);

        $this->returnSuccess($order);

    }

    /**
     * 生成预支付订单
     * @throws WxPayException
     */
    public function pay()
    {
        Log::record('wechat pay begin',Log::DEBUG,true);
        $isWx =1;
        $para = $this->checkParameters($isWx);
        try {
            $this->WapPayBusiness($para,$isWx);
        }catch (Exception $e) {
            $this->returnErrorNotice($e->getMessage());
        }
        $out_trade_no = $para['order_no'];
        $total_fee = $para['total_fee'];

        if($this->isDevEnvironment()) {
            $payUrl = mb_url('platform','payment','simulateWechatPay');
            $payUrl .= '&order_no='.$out_trade_no;
            $this->assign('payUrl',$payUrl);
            $this->assign('totalFee',$total_fee/100);
            $this->display('Index:pay_dev');
            return;
        }

        $fee = isset($_REQUEST['total_fee']) ? $_REQUEST['total_fee'] :0;
        $wxPreOrder = WxPreOrderModel::infoByOrderNo($out_trade_no);
        $config = new YoufanWapConfig();
        $tools = new JsApiPay($config);
        if(!empty($wxPreOrder)) {
            $openId = $wxPreOrder['open_id'];
        }else {
            $baseUrl = C('MERCHANT_API_HOST').'/wechat/index/pay';
            $baseUrl .= '?order_no='.$out_trade_no.'&total_fee='.$fee;
            $openId = $tools->GetOpenid($baseUrl);
            $data = array(
                'order_no' => $out_trade_no,
                'open_id' => $openId
            );
            WxPreOrderModel::add($data);
        }

        $preOrderParameters = array(
            'config' => $config,
            'order_no' => $out_trade_no,
            'total_fee' => $total_fee,
            'open_id' => $openId
        );

        try {
            $order = $this->getWapPreOrder($preOrderParameters);
            $jsApiParameters = '';
            $jsApiParameters = $tools->GetJsApiParameters($order);
            Log::record('js api parameters:'.$jsApiParameters,Log::DEBUG,true);
        }catch (WxPayException $e){
            Log::record('error happend:'.$e->getMessage(),Log::DEBUG,true);
            $this->assign('error',$e->getMessage());
        }

        $this->assign('jsApiParameters',$jsApiParameters);
        $this->assign('returnPage',$config->getSuccessUrl($out_trade_no));
        $this->assign('cancelPage',$config->getCancelUrl());
        if(isset($_REQUEST['code']) && $_REQUEST['code']) {
            session('wap_wechat_code_'.$out_trade_no,$_REQUEST['code']);
        }

        $this->display();
    }

    public function notify()
    {
        Log::record('-------begin wx notify-----',Log::INFO,true);
        $notify = new YoufanWapNotify();
        $config = new YoufanWapConfig();
        $notify->Handle(false,$config);

        Log::record('-------end wx notify-----',Log::INFO,true);
    }


    /**
     * 店铺用户充值
     * @throws WxPayException
     */
    public function charge()
    {
        Log::record('wechat merchant pay begin',Log::DEBUG,true);
        $isWx = 1;
        $para = $this->checkParameters($isWx);
        try {
            $this->merchantChargeBusiness($para,$isWx);
        }catch (Exception $e) {
            $code =  $e->getCode();
            if($code==20) {
                $userCenterUrl = MERCHANT_HOST.'/wap-shop/view.html?';
                $channelCode = $_GET['channel_code'];
                $userCenterUrl .= 'channel_code='.$channelCode;
                header("Location:".$userCenterUrl);
            }else {
                $this->returnErrorNotice($e->getMessage());
            }
        }

        $out_trade_no = $para['order_no'];
        $total_fee = $para['total_fee'];
        $reason = $_GET['reason'];

        if($this->isDevEnvironment()) {
            Log::record('this dev env',Log::DEBUG,true);
            $payUrl = mb_url('platform','payment','simulateWechatPay');
            $payUrl .= '&order_no='.$out_trade_no;
            $this->assign('payUrl',$payUrl);
            $this->assign('totalFee',$total_fee/100);
            $this->display('Index:pay_dev');
            return;
        }

        $config = new YoufanMerchantChargeConfig();
        $tools = new JsApiPay($config);
        //构建微信认证再次回调的url
        $channelCode = isset($_REQUEST['channel_code']) ? $_REQUEST['channel_code'] : '';
        //mode表示是普通充值，还是分销采购单支付时余额不足再过来充值的
        $mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : '';
        $paidType = isset($_REQUEST['pay_type']) ? $_REQUEST['pay_type'] : '';
        $cashierId = isset($_REQUEST['cashier_id']) ? $_REQUEST['cashier_id'] : '';
        $baseUrl = C('MERCHANT_API_HOST').'/wechat/index/charge';
        $baseUrl .= '?order_no='.$out_trade_no.'&total_fee='.$total_fee.'&channel_code='.$channelCode.'&mode='.$mode.'&order_type=99&reason='.$reason.'&pay_type='.$paidType.'&cashier_id='.$cashierId;

        Log::record('base url:'.$baseUrl,Log::DEBUG,true);
        $openId = $tools->GetOpenid($baseUrl);
        Log::record('open id:'.$openId,Log::DEBUG,true);

        $preOrderParameters = array(
            'config' => $config,
            'order_no' => $out_trade_no,
            'total_fee' => $total_fee,
            'open_id' => $openId
        );


        try {
            $order = $this->getWapPreOrder($preOrderParameters);
            $jsApiParameters = $tools->GetJsApiParameters($order);
            Log::record('js api parameters:'.$jsApiParameters,Log::DEBUG,true);

        }catch (WxPayException $e){
            Log::record('error happend:'.$e->getMessage(),Log::DEBUG,true);
            $this->assign('error',$e->getMessage());
        }

        $this->assign('jsApiParameters',$jsApiParameters);
        $returnUrl = $config->getSuccessUrl($out_trade_no);


        if($paidType ==1) {
            $cancelUrl = $config->getCancelUrl().'order='.$out_trade_no.'&channel_code='.$channelCode.'&mode='.$mode.'&order_type=99&reason='.$reason;
        }else {
            $cancelUrl = $config->getCancelUrl().'order='.$out_trade_no.'&total_fee='.($total_fee/100).'&channel_code='.$channelCode.'&mode='.$mode.'&order_type=99&reason='.$reason;
        }

        $this->assign('returnPage',$returnUrl);
        $this->assign('cancelPage',$cancelUrl);
        Log::record('cancel url:'.$cancelUrl,Log::DEBUG,true);
        if(isset($_REQUEST['code']) && $_REQUEST['code']) {
            session('wap_wechat_code_'.$out_trade_no,$_REQUEST['code']);
        }

        $this->display('Index:pay');
    }

    public function merchantChargeNotify()
    {
        Log::record('-------begin merchant wx notify-----',Log::INFO,true);
        $config = new YoufanMerchantChargeConfig() ;
        $notify = new YoufanMerchantChargeNotify();
        $notify->Handle(false,$config);
        Log::record('-------end merchant wx notify-----',Log::INFO,true);
    }


    /**
     * 收银台支付
     * @throws WxPayException
     */
    public function offlinePay()
    {
        Log::record('wechat cashier pay begin',Log::DEBUG,true);
        $para = $this->checkParameters(1);
        try {
            $this->merchantCashierBusiness($para,1);
        }catch (Exception $e) {
            $this->assign('error',$e->getMessage());
            $this->display('Index:pay_error');
            return;
        }

        $out_trade_no = $para['order_no'];
        $total_fee = $para['total_fee'];
        if($this->isDevEnvironment()) {
            $payUrl = mb_url('platform','payment','simulateWechatPay');
            $payUrl .= '&order_no='.$out_trade_no;
            $this->assign('payUrl',$payUrl);
            $this->assign('totalFee',$total_fee/100);
            $this->display('Index:pay_dev');
            return;
        }
        $config = new YoufanOfflineConfig();
        $tools = new JsApiPay($config);
        //构建微信认证再次回调的url
        $channelCode = isset($_REQUEST['channel_code']) ? $_REQUEST['channel_code'] : '';
        //mode表示是普通充值，还是分销采购单支付时余额不足再过来充值的
        $mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : '';
        $reason = isset($_REQUEST['reason']) ? $_REQUEST['reason'] : '';
        $paidType = isset($_REQUEST['pay_type']) ? $_REQUEST['pay_type'] : '';
        $cashierQrcodeId = isset($_REQUEST['cashier_id']) ? $_REQUEST['cashier_id'] : '';

        $baseUrl = C('MERCHANT_API_HOST').'/wechat/index/offlinePay';
        $baseUrl .= '?order_no='.$out_trade_no.'&total_fee='.$total_fee.'&channel_code='.$channelCode.'&mode='.$mode.'&order_type=3&reason='.$reason.'&pay_type='.$paidType.'&cashier_id='.$cashierQrcodeId;

        Log::record('base url:'.$baseUrl,Log::DEBUG,true);
        $openId = $tools->GetOpenid($baseUrl);
        Log::record('open id:'.$openId,Log::DEBUG,true);

        $preOrderParameters = array(
            'config' => $config,
            'order_no' => $out_trade_no,
            'total_fee' => $total_fee,
            'open_id' => $openId
        );

        try {
            $order = $this->getWapPreOrder($preOrderParameters);
            $jsApiParameters = $tools->GetJsApiParameters($order);
            Log::record('js api parameters:'.$jsApiParameters,Log::DEBUG,true);

        }catch (WxPayException $e){
            Log::record('error happend:'.$e->getMessage(),Log::DEBUG,true);
            $this->assign('error',$e->getMessage());
        }

        $this->assign('jsApiParameters',$jsApiParameters);
        $this->assign('returnPage',$config->getSuccessUrl($out_trade_no));

        if($paidType ==1) {
            $cancelUrl = $config->getCancelUrl().'order='.$out_trade_no.'&channel_code='.$channelCode.'&mode='.$mode.'&order_type=3&reason='.$reason.'&cashier_id='.$cashierQrcodeId;
        }else {
            $cancelUrl = $config->getCancelUrl().'order='.$out_trade_no.'&total_fee='.($total_fee/100).'&channel_code='.$channelCode.'&mode='.$mode.'&order_type=3&reason='.$reason.'&cashier_id='.$cashierQrcodeId;
        }
        Log::record('cancel url:'.$config->getSuccessUrl($out_trade_no),Log::DEBUG,true);
        Log::record('return page url:'.$cancelUrl,Log::DEBUG,true);
        $this->assign('cancelPage',$cancelUrl);
        if(isset($_REQUEST['code']) && $_REQUEST['code']) {
            session('wap_wechat_code_'.$out_trade_no,$_REQUEST['code']);
        }

        $this->display('Index:pay');
    }

    public function offlinePayNotify()
    {
        Log::record('-------begin offline wx notify-----',Log::INFO,true);
        $config = new YoufanOfflineConfig();
        $notify = new YoufanOfflineNotify();
        $notify->Handle(false,$config);
        Log::record('-------end offline wx notify-----',Log::INFO,true);
    }

}