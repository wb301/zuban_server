<?php
namespace Zb\Controller;
use Common\Controller\CommonController AS Controller;

/**

	控制器层 调用service层

*/
class CommonController extends Controller
{

    /**
     * version：2.0.0
     * info：检测用户id web用
     * params:user_Id，fileName
     * return:
     */
    protected function checkUserId($user_Id, $fileName = null, $isNotice = false)
    {

        $userModel = M('zuban_user_base','','DB_DSN');
        $userInfo = $userModel->where("`user_id` = '$user_Id' ")->select();
        if (!$userInfo || count($userInfo) <= 0) {
            if ($isNotice) {
                if ($fileName) {
                    $data[$fileName] = C('CODE_LOGIN_ERROR');
                    $data['message'] = "用户标识错误!";
                    $this->returnSuccess($data);
                } else {
                    return $this->returnErrorNotice("用户标识错误!");
                }
            } else {
                $data = array();
                if ($fileName) {
                    $data[$fileName] = C('CODE_LOGIN_ERROR');
                } else {
                    $data = C('CODE_LOGIN_ERROR');
                }
                return $this->returnSuccess($data);
            }
        }
        return $userInfo[0];
    }

    /**
     * info：token验证
     * params:token
     * return:
     */
    protected function checkToken($isNotice = 1)
    {
        $token=isset($_REQUEST['token'])?$_REQUEST['token']:'';
        $userInfoModel = M('zuban_user_info', '', 'DB_DSN');
        $userInfo = $userInfoModel->where("`token` = '$token' ")->field("`user_id`,`device`,`logitude`,`latitude`")->select();
        if (!$userInfo || count($userInfo) <= 0) {
            if ($isNotice) {
                return $this->returnErrorNotice("用户标识错误!");
            } else {
                return null;
            }
        }
        return $userInfo[0];
    }


    /**
     * info：获取价格信息
     * params:productList
     * return:array
     */
    public function  getProductPrice($productList){

        $proCodeList = array();
        foreach ($productList AS $key => $value) {
            $productList[$key]['price'] = 0;
            $productList[$key]['look_price'] = 0;
            $productList[$key]['price_type'] = 1;
            $productList[$key]['status'] = 0;
            $productList[$key]['num'] = $value['num'];
            array_push($proCodeList, $value['product_sys_code']);
        }
        $proCodeListStr = getListString($proCodeList);
        $productGoodsModel = M('zuban_product_goods', '', 'DB_DSN');
        $productRs = $productGoodsModel->where("`product_sys_code` IN ($proCodeListStr)")->field("`price_type`,`product_sys_code`,`price`,`status`,`look_price`")->select();
        if (count($productRs) > 0) {
            foreach ($productList AS $key => $value) {
                $proCode = $value['product_sys_code'];
                    foreach ($productRs AS $k => $v) {
                        if ($v['product_sys_code'] == $proCode) {
                            $productList[$key]['price'] = $v['price'];
                            $productList[$key]['look_price'] = $v['look_price'];
                            $productList[$key]['price_type'] = $v['price_type'];
                            $productList[$key]['status'] = $v['status'];
                        }
                    }
                }
            }

        return $productList;

    }

    /**
     * info：获取价格信息
     * params:productList
     * return:array
     */
    public function  getProductListByCode($productCodeList){
        $proCodeListStr = getListString($productCodeList);
        $productGoodsModel = M('zuban_product_goods', '', 'DB_DSN');
        $productRs = $productGoodsModel->where("`product_sys_code` IN ($proCodeListStr)")->field("`price_type`,`product_sys_code`,`price`,`status`,`product_name`,`look_price`,`product_image`,`product_phone`,`profession`,`region_code`")->select();
        return $productRs;

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
            '1'=>'待发货',
            '5'=>'已发货',
            '6'=>'交易完成',
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
    protected function region_list($code,$level=999999,$mapping=null)
    {
        $field = $this->formatMapping($mapping);
        $tempBaseRegionModel = M('zuban_temp_base_region','','DB_DSN');
        $regionRs = $tempBaseRegionModel->where("`status`= 1 AND `level`<= $level")->field("$field `code`,`parent_code`,`name`,`level`")->order(" `id` ASC,`level` ASC ")->select();

        return list_to_tree($regionRs,$code,"code","parent_code");
    }

    //获取地区列表
    protected function category_list($id,$level=999999,$mapping=null)
    {
        $field = $this->formatMapping($mapping);
        $tempCategoryModel = M('admin_product_category','','DB_DSN');
        $categoryRs = $tempCategoryModel->where("`status`= 1 AND `level`<= $level")->field("$field `id`,`parent_id`,`category_name`,`level` ")->order(" `sort` ASC,`level` ASC ")->select();

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
                unset($push[$children]);
                $list[$key][$children] = $this->fixAllForTree($value[$children],$push,$level,$mapping,$set,$name,$pk,$ppk,$children);
            }

        }
        return $list;
    }

    /**
     * 获取用户钱包
     * params:user_id
     * return:array
     */
    public function getUserMoneyInfo($userId = ''){

        $moneyHistoryModel = M('zuban_user_money_history','','DB_DSN');

        $oldTime = date('Y-m-d H:i:s', time() - 7 * 24 * 60 * 60);
        $userIdSqlStr = "`user_id` = '$userId'";

        //获取七天前可用余额
        $zhiChuSqlStr = $userIdSqlStr . " AND `create_time` <= '$oldTime'";
        $maxZhiChuMoney = $moneyHistoryModel->where($zhiChuSqlStr)->SUM("price");
        $maxZhiChuMoney = $maxZhiChuMoney ? $maxZhiChuMoney : 0;

        //获取近七天内的提现和余额支付
        $sevenDaySqlStr = $userIdSqlStr . " AND `create_time` >= '$oldTime' AND `price_type` IN (5,7) ";
        $sevenDayMoney = $moneyHistoryModel->where($sevenDaySqlStr)->SUM("price");
        $sevenDayMoney = $sevenDayMoney ? $sevenDayMoney : 0;

        //提现中的金额
        $withdrawHistoryModel = M("zuban_user_withdraw_history", '', "DB_DSN");
        $withdrawHistorySqlStr = $userIdSqlStr . " AND `status` = 2 ";
        $withdrawMoney = $withdrawHistoryModel->where($withdrawHistorySqlStr)->SUM("price");

        //七天前的可提现金额  - 近七天内的余额支付和提现 - 提现中的金额
        $availableMoney = $maxZhiChuMoney - abs($sevenDayMoney) - $withdrawMoney;
        $availableMoney = $availableMoney ? $availableMoney : 0;

        //获取现在的总金额
        $maxMoney = $moneyHistoryModel->where($whereArr)->SUM("price");

        //总金额减去可用金额剩余冻结金额
        $freezeMoney = $maxMoney - $availableMoney * 2;

        return array("available" => $availableMoney, "freeze" => $freezeMoney);
    }


    public function getVip($userId)
    {
        $nowTime = date('Y-m-d H:i:s');
        //查询是否为会员
        return M('zuban_user_vip','','DB_DSN')->where("`user_id` = '$userId' AND `start_time` <= '$nowTime' AND `end_time` >= '$nowTime'")->find();
    }


    /**
        保存手机号码和验证码
    */
    protected function saveAccountByCode($account, $code, $from) {

        $validationModel = M("zuban_sms_validation", 0, "DB_DSN");
        $validationModel->where(array("account" => $account))->save(array("status" => 2));

        $nowTime = date('Y-m-d H:i:s');
        $validationArr = array("account" => $account,
                               "code" => $code,
                               "create_time" => $nowTime,
                               "update_time" => $nowTime,
                               "status" => 0,
                               "from" => $from);
        $validationModel->add($validationArr);
    }  

    /**
        检测手机号码和验证码
    */
    protected function checkAccountByCode($account, $code) {

        $validationModel = M("zuban_sms_validation", 0, "DB_DSN");

        $whereArr = array("account" => $account, "code" => $code, "status" => 0);
        $validationInfo = $validationModel->where($whereArr)->find();
        if(!$validationInfo){
            return false;
        }

        // $validationModel->where($whereArr)->save(array("status" => 1));
        return true;
    }  

    /**
        根据token获取用户信息
    */
    protected function getUserInfoByToken($token){

        $userInfoModel = M("zuban_user_info", 0, "DB_DSN");
        $userRes = $userInfoModel->where(array("token" => $token))->find();

        if($userRes === false){
            return $this->returnErrorNotice("用户标示错误");
        }

        $userInfo = $this->getUserInfoByUserId($userRes["user_id"]);
        return $userInfo;
    }

    /**
        根据user_id获取用户信息
    */
    protected function getUserInfoByUserId($userId){

        $userBaseModel = M("zuban_user_base", 0, "DB_DSN");
        $userInfo = $userBaseModel->where(array("user_id" => $userId))->find();

        if($userInfo === false){
            return $this->returnErrorNotice("用户不存在");
        }

        return $userInfo;
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

}