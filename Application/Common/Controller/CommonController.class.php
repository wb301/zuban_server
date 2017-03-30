<?php
namespace Common\Controller;
use Think\Controller;

/**

    控制器层 调用service层

*/
class CommonController extends Controller
{

    //模型名称
    public    $modelName = MODULE_NAME;
    //服务对象
    public    $service;

    public function __construct() {
        parent::__construct();
        date_default_timezone_set('PRC');
        $this->requestTime = time();
        //对象实例化
        $this->service = A( "{$this->modelName}/{$this->getControllerName()}", 'Service' );
        $this->_server();
    }

    private function _server()
    {
        $closeModuleList = json_decode($this->getSysConfig('CLOSE_MODULE',2), true);
        if(in_array($this->modelName, $closeModuleList)){
            $this->returnErrorNotice('当前服务器维护中~!');
        }
    }

    //数组分页通用返回结构及统一获取
    protected $pageAry = array(
        'list' => array(),
        'total' => 0
    );
    protected $page = 1;
    protected $row = 10;
    protected function setPageRow() {
        if(isset($_REQUEST['page']) && intval($_REQUEST['page']) > 0) {
            $this->page = $_REQUEST['page'];
        }
        if(isset($_REQUEST['row']) && intval($_REQUEST['row']) > 0) {
            $this->row = $_REQUEST['row'];
        }
    }

    private $requestTime = 0;
    private function returnJQuery($data,$msg,$code,$status)
    {
        $time =  time() - $this->requestTime;
        $result = array(
            'data' => $data,
            'msg' => $msg,
            'code' => $code,
            'status' => $status,
            'time' => $time
        );
        if($time > C('REQUEST_OUT_TIME')){
            // TODO:记录此次访问请求url
        }

        exit(json_encode($result));
    }

    protected function _POST()
    {
        if($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->returnErrorNotice('请求不是POST');
        }
    }

    //通用成功返回
    protected function returnSuccess($data=array(),$msg='success',$code=100)
    {
        $this->returnJQuery($data,$msg,$code,C('AJAX_STATUS_SUCCESS'));
    }


    //通用错误返回
    protected function returnErrorNotice($msg="failed",$code=-100){
        $this->returnJQuery(array(),$msg,$code,C('AJAX_STATUS_ERROR'));
    }


    /**
    +----------------------------------------------------------
     * 获取当前控制器名称
    +----------------------------------------------------------
     * @access  protected
    +----------------------------------------------------------
     * @return srting
    +----------------------------------------------------------
     */
    protected function getControllerName() {
        if(empty($this->controllerName)) {
            // 获取Controller名称
            $nameArray = explode('\\',get_class($this));
            $count = count($nameArray);
            $this->controllerName = substr($nameArray[$count-1],0,-strlen($nameArray[$count-2]));
        }
        return $this->controllerName;
    }


    //参数验证
    protected function getPostparameters($getKeyAry, $data = null){
        if($data)
            $parameters = $data;
        else
            $parameters = $_REQUEST;
        foreach ($getKeyAry as  $key => $value) {
            if($value && strlen($value) > 0){
                if(!isset($parameters[$key]) || (is_string($parameters[$key]) && strlen($parameters[$key]) <= 0) || is_array($parameters[$key]) && count($parameters[$key]) <= 0 ){
                    $this->returnErrorNotice($value);
                }
            }
            if(!isset($parameters[$key])){
                $parameters[$key]='';
            }
        }
        return $parameters;
    }


    //生成六位码
    protected function createCode($configKey='ORDER_CODE'){
        $paramModel = M('admin_system_config','','DB_DSN');
        $paramRs = $paramModel->where("`status` = 1 AND `is_auto` = 1 AND `config_key`='$configKey'")->getField("config_value");
        $paramModel->where("`config_key`='$configKey'")->setInc("config_value");
        return $paramRs+1;
    }

    /**
        获取系统配置
    */
    protected function getSysConfig($key=null,$isAuto=0){
        $sysModel = M("admin_system_config", 0, "DB_DSN");
        $sysAry = $sysModel->where("`status` = 1 AND `is_auto` = $isAuto ")->getField("config_key,config_value");
        if($key){
            return $sysAry[$key];
        }
        return $sysAry;
    }


    //验证签名
    public function valid()
    {
        $request=$_REQUEST;
        $echoStr = $request['echostr'];
        $signature = $request['signature'];
        $timestamp = $request['timestamp'];
        $nonce = $request['nonce'];
        $token = C('WXTOKEN');
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if ($tmpStr == $signature) {
            echo $echoStr;
            exit;
        }
    }

    private function formatMapping($mapping)
    {
        $field  = "";
        if($mapping){
            $field = "";
            foreach ($mapping as $key => $value) {
                $field .= "`$key` AS $value, ";
            }
        }
        return $field;
    }

    //获取地区列表
    protected function region_list($code,$level=999999,$mapping=null,$where=" AND `status`= 1")
    {

        $field = $this->formatMapping($mapping);
        $tempBaseRegionModel = M('zuban_temp_base_region','','DB_DSN');
        $regionRs = $tempBaseRegionModel->where("`level`<= $level".$where)->field("$field `code`,`parent_code`,`name`,`level`")->order(" `id` ASC,`level` ASC ")->select();

        return list_to_tree($regionRs,$code,"code","parent_code");
    }

    //获取地区列表
    protected function category_list($id,$level=999999,$mapping=null,$where=" AND `status`= 1")
    {
        $field = $this->formatMapping($mapping);
        $tempCategoryModel = M('admin_product_category','','DB_DSN');
        $categoryRs = $tempCategoryModel->where("`level`<= $level".$where)->field("$field `id`,`parent_id`,`category_name`,`level` ")->order(" `sort` ASC,`level` ASC ")->select();

        return list_to_tree($categoryRs,$id);
    }

    protected function fixAllForTree($list,$p,$level,$mapping=null,$set,$name,$pk,$ppk,$children="children")
    {
        $p[$name] = $set;
        $p[$pk] = $p[$ppk];
        if($mapping){
            $p[$mapping[$name]] = $p[$name];
            $p[$mapping[$pk]] = $p[$pk];
        }
        if($level > 0){
            $p[$children] = [];
        }
        $level--;
        $list = array_merge(array($p), $list);
        foreach ($list as $key => $value) {
            if(isset($value[$children])){
                if(count($value[$children]) > 0){
                    $push = $value[$children][0];
                }else{
                    $push = $value;
                }
                $set = $value[$name];
                unset($push[$children]);
                $list[$key][$children] = $this->fixAllForTree($value[$children],$push,$level,$mapping,$set,$name,$pk,$ppk,$children);
            }

        }
        return $list;
    }


    /**
     * version：2015-8-30
     * 获取订单状态名称
     * 参数:
     * 无参数
     */
    protected function getSatusOrder($status){

        $statusNameAry=array(
            '0'=>'待付款',
            '1'=>'待确认',
            '5'=>'进行中',
            '6'=>'已完成',
            '9'=>'已取消',
            '10'=>'已完成',
            '11'=>'退款中',
            '12'=>'退款已完成',
            '15'=>'交易关闭',
        );
        return $statusNameAry[$status];
    }

    /**
     * 绑定支付信息
     * @param $payAry
     * @param $orderAry
     * @return mixed
     */
    protected function getOrderPay($payAry, $orderAry)
    {
        foreach ($orderAry as $key => $value) {
            $orderNo = $value['order_no'];
            $orderAry[$key]['paymentList'] = array();
            if (count($payAry) > 0) {
                foreach ($payAry as $ok => $ov) {
                    if ($orderNo == $ov['order_no']) {
                        array_push($orderAry[$key]['paymentList'], $ov);
                    }
                }
            }
        }
        return $orderAry;
    }

    /**
     * info：获取价格信息
     * params:productList
     * return:array
     */
    public function  getProductListByCode($productCodeList){
        $proCodeListStr = getListString($productCodeList);
        $productGoodsModel = M('zuban_product_goods', '', 'DB_DSN');
        $productRs = $productGoodsModel->where("`product_sys_code` IN ($proCodeListStr)")->field("`price_type`,`product_sys_code`,`price`,`status`,`look_price`,`product_image`,`region_name`,`region_code`,`product_info`,`user_id`")->select();
        //获取分类名称
        $productCategoryModel = M('zuban_product_category', '', 'DB_DSN');
        $category = $productCategoryModel->where("`product_sys_code` IN ($proCodeListStr)")->getField("`product_sys_code`,`category_name`");

        foreach($productRs AS $key=>$vale){
            $productRs[$key]['category_name']=$category[$vale['product_sys_code']];
        }
        return $productRs;

    }


    /**

        绑定用户信息函数

    */
    public function getUserInfoByAryList($aryList,$fileName="user_id"){
        if(!$aryList || count($aryList) <= 0){
            return array();
        }
        $userIdList = array();
        foreach ($aryList as $key => $value) {
            if(isset($value[$fileName]) && strlen($value[$fileName]) > 0){
                array_push($userIdList, $value[$fileName]);
            }
        }
        if(count($userIdList) <= 0){
            return $aryList;
        }
        $userIdListStr = getListString($userIdList);

        $userModel = M('zuban_user_base','','DB_DSN');
        $userInfoRs = $userModel->where("`user_id` IN ($userIdListStr) ")->select();

        if(!$userInfoRs || count($userInfoRs) <= 0){
            return $aryList;
        }
        foreach ($aryList as $ak => $av) {
            $userId = $av[$fileName];
            foreach ($userInfoRs as $uk => $uv) {
                if($userId == $uv['user_id']){
                    $aryList[$ak]['userInfo'] = $uv;
                    break;
                }
            }
        }

        return $aryList;
    }

    /**
     * @desc 更新商品状态
     * */
    public function changeProductStatus($orderNo,$status=1){

        $orderProductModel = M('zuban_order_product','','DB_DSN');
        $orderProductRs = $orderProductModel->where("`order_no` ='$orderNo' AND `status` >= 0")->getField("product_sys_code",true);
        if(count($orderProductRs)>0){
            $productCode_str=getListString($orderProductRs);
            $productModel = M('zuban_product_goods','','DB_DSN');
            $updateAry = array(
                'status' => $status,
                'update_time' => date('Y-m-d H:i:s')
            );
            $productModel->where("`product_sys_code`IN($productCode_str)")->setField($updateAry);
        }

    }

    /**
     * version：2.0.0
     * info： 检测内容是否合法
     * params:contentInfo，checkLength，isCheckErr
     * return:
     */
    protected function checkContentInfo($contentInfo,$checkLength,$isCheckErr = false){
        if(!$contentInfo || strlen($contentInfo) <= 0){
            if($isCheckErr){
                return $this->returnErrorNotice('内容长度不合法！');
            }else{
                return $contentInfo;
            }
        }
        //去除
        $contentInfo = replaceStr($contentInfo);
        $contentInfo = checkForbiddenStr($contentInfo);

        // 返回单元个数
        if(getStrLength($contentInfo) > $checkLength){
            return $this->returnErrorNotice('内容长度不合法！');
        }
        return $contentInfo;
    }



        //获取查看价格
    public function getLookPrice($isFree=1)
    {
        $lookPrice = 0;
        if($isFree <= 0){
            $lookPrice = floatval($this->getSysConfig("LOOK_PRICE"));
        }
        return $lookPrice;
    }

    //时间修正
    public function fixDate($date)
    {
        $date = str_replace("T", " ", $date);
        $date = str_replace(".00Z", "", $date);
        return $date;
    }

}