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
                return $this->returnErrorNotice("用户标识错误!", -999);
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
            $productList[$key]['user_id']='';
            $productList[$key]['product_info'] = '';
            $productList[$key]['num'] = $value['num'];
            array_push($proCodeList, $value['product_sys_code']);
        }
        $proCodeListStr = getListString($proCodeList);
        $productGoodsModel = M('zuban_product_goods', '', 'DB_DSN');
        $productRs = $productGoodsModel->where("`product_sys_code` IN ($proCodeListStr)")->field("`price_type`,`product_sys_code`,`price`,`status`,`look_price`,`product_info`,`user_id`")->select();
        if (count($productRs) > 0) {
            foreach ($productList AS $key => $value) {
                $proCode = $value['product_sys_code'];
                    foreach ($productRs AS $k => $v) {
                        if ($v['product_sys_code'] == $proCode) {
                            $productList[$key]['price'] = $v['price'];
                            $productList[$key]['look_price'] = $v['look_price'];
                            $productList[$key]['price_type'] = $v['price_type'];
                            $productList[$key]['product_info'] = $v['product_info'];
                            $productList[$key]['user_id'] = $v['user_id'];
                            $productList[$key]['status'] = $v['status'];
                        }
                    }
                }
            }
        return $productList;

    }


    /**
     * 获取用户钱包
     * params:user_id
     * return:array
     */
    public function getUserMoneyInfo($userId = ''){

        $moneyHistoryModel = M('zuban_user_money_history','','DB_DSN');

        // $oldTime = date('Y-m-d H:i:s', time() - 7 * 24 * 60 * 60);
        // $userIdSqlStr = "`user_id` = '$userId'";

        // //获取七天前可用余额
        // $zhiChuSqlStr = $userIdSqlStr . " AND `create_time` <= '$oldTime'";
        // $maxZhiChuMoney = $moneyHistoryModel->where($zhiChuSqlStr)->SUM("price");
        // $maxZhiChuMoney = $maxZhiChuMoney ? $maxZhiChuMoney : 0;

        // //获取近七天内的提现和余额支付
        // $sevenDaySqlStr = $userIdSqlStr . " AND `create_time` >= '$oldTime' AND `price_type` IN (5,7) ";
        // $sevenDayMoney = $moneyHistoryModel->where($sevenDaySqlStr)->SUM("price");
        // $sevenDayMoney = $sevenDayMoney ? $sevenDayMoney : 0;

        // //提现中的金额
        // $withdrawHistoryModel = M("zuban_user_withdraw_history", '', "DB_DSN");
        // $withdrawHistorySqlStr = $userIdSqlStr . " AND `status` = 2 ";
        // $withdrawMoney = $withdrawHistoryModel->where($withdrawHistorySqlStr)->SUM("price");

        // //七天前的可提现金额  - 近七天内的余额支付和提现 - 提现中的金额
        // $availableMoney = $maxZhiChuMoney - abs($sevenDayMoney) - $withdrawMoney;
        // $availableMoney = $availableMoney ? $availableMoney : 0;

        // //获取现在的总金额
        // $maxMoney = $moneyHistoryModel->where($userIdSqlStr)->SUM("price");

        // //总金额减去可用金额剩余冻结金额
        // $freezeMoney = $maxMoney - $availableMoney;

        //获取现在的总金额
        $maxMoney = $moneyHistoryModel->where($userIdSqlStr)->SUM("price");

        //提现中的金额
        $withdrawHistoryModel = M("zuban_user_withdraw_history", '', "DB_DSN");
        $withdrawHistorySqlStr = $userIdSqlStr . " AND `status` = 2 ";
        $freezeMoney = $withdrawHistoryModel->where($withdrawHistorySqlStr)->SUM("price");

        //可提现金额
        $availableMoney = $maxMoney - $freezeMoney;

        return array("maxMoney" => number_format($maxMoney,2), "available" => number_format($availableMoney,2), "freeze" => number_format($freezeMoney,2));
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
    public function updUserInfoByOpendId($userInfo, $openId, $isUpd = true){

        $userBaseModel = M("zuban_user_base", 0, "DB_DSN");
        if(strlen($openId) > 0){

            $openIdArr = array("wx_openid" => $openId);
            $openIdInfo = $userBaseModel->where($openIdArr)->find();

            $newUserInfo = array("wx_openid" => $openId);
            if(strlen($userInfo["nick_name"]) <= 0)
                $newUserInfo["nick_name"] = $openIdInfo["nick_name"];

            $userBaseModel->where($openIdArr)->delete();
            if($isUpd){
                $userBaseModel->where(array("account" => $userInfo["account"]))->save($newUserInfo);
            }else{
                $userInfo["wx_openid"] = $openId;
                $userInfo["head_img"] = $openIdInfo["head_img"];
                $userInfo["nick_name"] = $openIdInfo["nick_name"];
            }
        }

        return $userInfo;
    }

    /**

        修改用户地理位置

    */
    public function updUserGeographicPosition($userId){

        $userArr = array();
        if(isset($_REQUEST["logitude"])){
            $userArr["logitude"] = $_REQUEST["logitude"];
        }
        if(isset($_REQUEST["latitude"])){
            $userArr["latitude"] = $_REQUEST["latitude"];
        }
        if(count($userArr) > 0){
            $userInfoModel = M("zuban_user_info", 0, "DB_DSN");
            $userInfoModel->where(array("user_id" => $userId))->save($userArr);

            $productModel = M('zuban_product_goods','','DB_DSN');
            $productModel->where(array("user_id" => $userId))->save($userArr);
        }
    }

    /**
        生成用户token
    */
    protected function updUserInfo($userInfo){

        $nowTime = date('Y-m-d H:i:s');
        $token = md5($userInfo['user_id'].time());

        $userBase['token'] = $token;
        $userBase["update_time"] = $nowTime;
        if(isset($_REQUEST['device']))
            $userBase["device"] = $_REQUEST['device'];
        if(isset($_REQUEST['version']))
            $userBase["version"] = $_REQUEST['version'];
        if(isset($_REQUEST['app_name']))
            $userBase["app_name"] = $_REQUEST['app_name'];
        if(isset($_REQUEST['os_mode']))
            $userBase["os_mode"] = $_REQUEST['os_mode'];
        if(isset($_REQUEST['logitude']))
            $userBase["logitude"] = $_REQUEST['logitude'];
        if(isset($_REQUEST['latitude']))
            $userBase["latitude"] = $_REQUEST['latitude'];

        $userInfoModel = M("zuban_user_info", 0, "DB_DSN");
        $userRes = $userInfoModel->where(array("user_id" => $userInfo["user_id"]))->find();

        if( !$userRes ){
            $userBase["user_id"] = $userInfo["user_id"];
            $userInfoModel->add($userBase);
        }else{
            $userInfoModel->where(array("user_id" => $userInfo["user_id"]))->save($userBase);
        }

        //这里需要修改一下用户发布信息的经纬度
        $productModel = M('zuban_product_goods','','DB_DSN');
        $productSaveArr = array("logitude" => $userBase["logitude"], "latitude" => $userBase["latitude"]);
        $productModel->where(array("user_id" => $userInfo["user_id"]))->save($productSaveArr);
        $sysConfig = $this->getSysConfig();
        $userBase['server_phone']=$sysConfig['CUSTOMER_SERVICE'];
        $userBase['as']=intval(($sysConfig['AS_PLATFORM']+$sysConfig['AS_REGISTERED']+$sysConfig['AS_CONSUM']) / C('DENO')*100);
        $userInfo["token"] = $token;
        return $userInfo;
    }


}