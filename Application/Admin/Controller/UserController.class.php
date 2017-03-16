<?php
namespace Admin\Controller;
use Admin\Controller\AdminCommonController;

class UserController extends AdminCommonController
{


    /**
     *  用户登陆
     */
    public function login()
    {
        $keyAry = array(
            'account' => "用户名不能为空",
            'password' => "密码不能为空"
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        $account = $parameters['account'];
        $password = $parameters['password'];

        $userBaseModel = M("admin_region_manager", 0, "DB_DSN");
        $userInfo = $userBaseModel->where(array("account" => $account))->find();
        if( !$userInfo )
            $this->returnErrorNotice("帐号不存在");
        if( $userInfo['password'] != md5($password) )
            $this->returnErrorNotice("密码错误");

        $userInfo["token"] = md5($userInfo['user_id'].time());
        $saveArr = array("token" => $userInfo["token"], "update_time" => date('Y-m-d H:i:s'));
        $userBaseModel->where(array("account" => $account))->save($saveArr);

        return $this->returnSuccess($userInfo);
    }

    /**
    =======================   平台的操作   =======================
     */

    /**
    获取地区管理员账号列表
     */
    public function getRegionManagerList(){

        $keyAry = array(
            'region_code' => ""
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        //获取自己的信息
        $userBase = $this->checkToken(1);
        $region_code = $parameters["region_code"];

        $whereArr = array("status" => array("lt", 3));
        if(strlen($region_code) > 0){
            $whereArr["region_code"] = $region_code;
        }

        $userBaseModel = M("admin_region_manager", 0, "DB_DSN");
        $this->pageAry["total"] = $userBaseModel->where($whereArr)->count();
        if($this->pageAry["total"] > 0){

            $this->setPageRow();
            $this->pageAry["list"] = $userBaseModel->where($whereArr)->order("id DESC")->page($this->page, $this->row)->select();
            $this->pageAry["list"] = $this->pageAry["list"] ? $this->pageAry["list"] : array();
        }

        return $this->returnSuccess($this->pageAry);
    }

    /**
    获取地区管理员账号详情
     */
    public function getRegionManagerInfo(){

        $keyAry = array(
            'id' => "用户标识不能为空"
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        //获取自己的信息
        $userBase = $this->checkToken(1);
        $userBaseModel = M("admin_region_manager", 0, "DB_DSN");
        $whereArr = array("status" => array("lt", 3), "id" => $parameters["id"]);
        $userInfo = $userBaseModel->where($whereArr)->find();
        if(!$userInfo){
            $this->returnErrorNotice('用户不存在!');
        }

        return $this->returnSuccess($userInfo);
    }

    /**
    添加地区管理员账号
     */
    public function updRegionManager(){

        $keyAry = array(
            'id' => "ID不能为空",   // 0.新增    不为0就是修改
            'account' => "代理商账号不能为空",
            'password' => "代理商密码不能为空",
            'nick_name' => "代理商昵称不能为空",
            'region_code' => "代理商地区不能为空",
            'region_name' => "代理商地区名称不能为空",
            'status' => "代理商状态不能为空"
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        //获取自己的信息
        $userBase = $this->checkToken(1);
        $adminCode = $userBase["admin_code"];

        $id = $parameters["id"];
        $account = $parameters["account"];
        $password = $parameters["password"];
        $nickName = $parameters["nick_name"];
        $managerType = 0;
        $regionCode = $parameters["region_code"];
        $regionName = $parameters["region_name"];
        $status = $parameters["status"];

        $userBaseModel = M("admin_region_manager", 0, "DB_DSN");
        if($managerType <= 0){

            $whereArr = array("region_code" => $regionCode, "status" => 1);
            $userInfo = $userBaseModel->where($whereArr)->count();
            if($userInfo > 0){
                $this->returnErrorNotice('该地区已存在代理商!');
            }
        }

        $addArr = array("account" => $account,
            "password" => $password,
            "nick_name" => $nickName,
            "manager_type" => $managerType,
            "region_code" => $regionCode,
            "region_name" => $regionName,
            "status" => $status);

        if($id > 0){
            $remark = "修改代理商[".$id."],生成新数据:".json_encode($addArr);
            $userBaseModel->where(array("id" => $id))->save($addArr);
        }else{
            $addArr["admin_code"] = create_guid();
            $remark = "新增代理商[".$nickName."],生成新数据id:".$id;
            $id = $userBaseModel->add($addArr);
        }

        $operation = "User-updRegionManager-admin_region_manager-".$id;
        $this->insertHistory($adminCode,$operation,$remark);
        return $this->returnSuccess(true);
    }

    /**
    修改地区管理员账号状态
     */
    public function updRegionManagerStatus(){

        $keyAry = array(
            'id' => "用户标识不能为空",
            'status' => "代理商状态不能为空"
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        //获取自己的信息
        $userBase = $this->checkToken(1);
        $adminCode = $userBase["admin_code"];
        $status = $parameters["status"];

        $userBaseModel = M("admin_region_manager", 0, "DB_DSN");
        $whereArr = array("id" => $parameters["id"]);
        if($status > 0){

            $userInfo = $userBaseModel->where($whereArr)->find();
            if(!$userInfo){
                $this->returnErrorNotice('用户不存在!');
            }
            $regionCode = $userInfo["region_code"];
            $regionWhereArr = array("region_code" => $regionCode, "status" => 1, "manager_type" => 0);
            $userInfo = $userBaseModel->where($regionWhereArr)->count();
            if($userInfo > 0){
                $this->returnErrorNotice('该地区已存在代理商!');
            }

        }

        $saveArr = array("status" => $status);
        $userBaseModel->where($whereArr)->save($saveArr);

        $remark = "修改代理商[".$id."],生成新数据:".json_encode($saveArr);
        $operation = "User-updRegionManagerStatus-admin_region_manager-".$id;
        $this->insertHistory($adminCode,$operation,$remark);

        return $this->returnSuccess(true);
    }

    /**
    =======================   平台和代理商的操作   =======================
     */

    /**
     * 新增用户列表
     * http://localhost/zuban_server/index.php?c=Admin&m=User&a=newUserCommonFilter&token=1111&pageSize=20&pageIndex=1&type=0&phone=15002164396&name=&startTime=&endTime=&source=
     * 请求方式:get
     * 服务名:Wap
     * 参数:
     * @param $token 用户编号
     * @param $pageIndex 页码
     * @param $pageSize 页数
     * @param $startTime 开始时间
     * @param $endTime 结束时间
     * @param $phone 手机号
     * @param $name 姓名
     * @param $region 区域code
     *
     */
    public function getUserList()
    {
        $keyAry = array(
            'name' => "",
            'phone' => "",
            'status' => "请选择用户状态",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        // $this->checkToken(1);
        $this->setPageRow();
        $whereSql = " `status`= {$parameters['status']} ";
        if(strlen($parameters['phone'])>0){
            $whereSql .= " AND `account`  LIKE  '%{$parameters['phone']}%' ";
        }
        if(strlen($parameters['name'])>0){
            $whereSql .= " AND `nick_name` LIKE  '%{$parameters['name']}%'  ";
        }
        $userModel = M('zuban_user_base', '', 'DB_DSN');
        $this->pageAry["total"] = intval($userModel->where($whereSql)->count());
        if($this->pageAry["total"] > 0){
            $this->pageAry["list"] = $userModel->where($whereSql)->order("`register_time` DESC ")->page($this->page, $this->row)->select();
        }

        $this->returnSuccess($this->pageAry);
    }


    /**
     * 删除用户
     * http://localhost/zuban_server/index.php?c=Admin&m=User&a=deleteUser&userId=1111
     * 请求方式:get
     * 参数:
     * @param $userId 用户编号
     *
     */
    public function  deleteUser($userId){
        //用户表
        $userModel = M('zuban_user_base', '', 'DB_DSN');
        $userRs = $userModel->where("`user_id`='$userId' AND `status`=1 ")->select();
        if (count($userRs) <= 0) {
            $this->returnErrorNotice('该用户已经被删除!');
        }
        //订单表
        $orderModel = M('zuban_order', '', 'DB_DSN');
        $orderRs=$orderModel->where("(`user_id`='$userId' OR `product_user`='$userId') AND `status` IN(1,5,11)")->count();
        if($orderRs>0){
            $this->returnErrorNotice('该用户存在交易订单不可删除!');
        }

        $moneyHistoryModel = M('zuban_user_money_history','','DB_DSN');
        $userIdSqlStr = "`user_id` = '$userId'";
        $money = $moneyHistoryModel->where($userIdSqlStr)->SUM("price");
        if($money>0){
            $this->returnErrorNotice('该用户有为提现金额不可删除!');
        }
        $userModel->where($userIdSqlStr)->setField(array(
            'status' => -1
        ));
        $productModel = M('zuban_product_goods', '', 'DB_DSN');
        $updateAry = array(
            'status' => -1,
            'update_time' => date('Y-m-d H:i:s')
        );
        $productModel->where($userIdSqlStr)->setField($updateAry);
        $this->returnSuccess('删除成功');
    }

}