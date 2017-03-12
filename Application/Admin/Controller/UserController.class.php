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
        $this->_POST();
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

        $this->_POST();
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

        $this->_POST();
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

        $this->_POST();
        $keyAry = array(
            'id' => "ID不能为空",   // 0.新增    不为0就是修改
            'account' => "代理商账号不能为空",
            'password' => "代理商密码不能为空",
            'nick_name' => "代理商昵称不能为空",
            'manager_type' => "代理商类型不能为空",
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
        $managerType = $parameters["manager_type"];
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

        $this->_POST();
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
            $regionWhereArr = array("region_code" => $regionCode, "status" => 1);
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
     * 获取用户列表
     * @param $token    用户标示
     */
    public function getUserList(){

        $userBase = $this->checkToken();
        $managerType = $userBase["manager_type"];
        $region_code = $userBase["region_code"];

        $whereArr = array();
        if($isAll <= 0){
            $whereArr["region_code"] = $region_code;
        }

        $userModel = M("zuban_user_base", '', "DB_DSN");
        $this->pageAry["total"] = $userModel->where($whereArr)->count();
        if($this->pageAry["total"] > 0){

            $this->setPageRow();
            $this->pageAry["list"] = $userModel->where($whereArr)->order("id DESC")->page($this->page, $this->row)->select();
            $this->pageAry["list"] = $this->pageAry["list"] ? $this->pageAry["list"] : array();
        }

        return $this->returnSuccess($this->pageAry);
    }

    /**
     * 获取用户信息
     * @param $token    用户标示
     */
    public function getUserInfo(){

        $this->_POST();
        $keyAry = array(
            'userId' => "用户标识不能为空"
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        //获取自己的信息
        $userBase = $this->checkToken();
        $managerType = $userBase["manager_type"];
        $region_code = $userBase["region_code"];

        $userId = $parameters["userId"];
        $whereArr = array();
        if($isAll <= 0){
            $whereArr["region_code"] = $region_code;
        }
        $whereArr["user_id"] = $userId;
        $userModel = M("zuban_user_base", '', "DB_DSN");
        $userInfo = $userModel->where($whereArr)->find();
        if(!$userInfo){
            $this->returnErrorNotice('该用户不存在!');
        }

        return $this->returnSuccess($userInfo);
    }

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
    public function newUserCommonFilter()
    {

        $keyAry = array(
            'pageSize' => "",
            'pageIndex' => "",
            'startTime' => "",
            'endTime' => "",
            'name' => "",
            'phone' => "",
            'type' => "",
            'region' => "",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        $this->setPageRow();
        $rs = array(
            'list' => array(),
            'total' => 0
        );
        $whereSql = " 1=1 ";
        if(strlen($parameters['phone'])>0){
            $whereSql .= " AND `account`= '{$parameters['phone']}' ";
        }
        if(strlen($parameters['name'])>0){
            $whereSql .= " AND `nick_name`= '{$parameters['name']}'  ";
        }
        if(strlen($parameters['region'])>0){
            $whereSql .= " AND `region_code`= '{$parameters['region']}' ";
        }
        if(strlen($parameters['startTime'])>0){
            $whereSql .= " AND `register_time`>= '{$parameters['startTime']}' ";
        }else{
            $nowTime=date('Y-m-d');
            $whereSql .= " AND `register_time`>= '$nowTime' ";
        }
        if(strlen($parameters['endTime'])>0){
            $whereSql .= " AND `register_time`<= '{$parameters['endTime']}' ";
        }
        $userModel = M('zuban_user_base', '', 'DB_DSN');
        $userCount = $userModel->where($whereSql)->count();
        if ($userCount <= 0) {
            $this->returnSuccess($rs);
        }
        $userRs = $userModel->where($whereSql)->order("`register_time` DESC ")->page($this->page, $this->row)->select();
        if (count($userRs) <= 0) {
            $this->returnSuccess($rs);
        }
        $rs['list'] = $userRs;
        $rs['total'] = $userCount;
        $this->returnSuccess($rs);
    }


    /**
     * 新增用户列表
     * http://localhost/zuban_server/index.php?c=Admin&m=User&a=loginUserCommonFilter&token=1111&pageSize=20&pageIndex=1&type=0&phone=15002164396&name=&startTime=&endTime=&source=
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
    public function loginUserCommonFilter()
    {

        $keyAry = array(
            'pageSize' => "",
            'pageIndex' => "",
            'startTime' => "",
            'endTime' => "",
            'name' => "",
            'phone' => "",
            'region' => "",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        $this->setPageRow();
        $rs = array(
            'list' => array(),
            'total' => 0
        );
        $whereSql='';
        if(strlen($parameters['phone'])>0){
            $whereSql .= " AND u.`account`= '{$parameters['phone']}' ";
        }
        if(strlen($parameters['name'])>0){
            $whereSql .= " AND u.`nick_name`= '{$parameters['name']}'  ";
        }
        if(strlen($parameters['region'])>0){
            $whereSql .= " AND u.`region_code`= '{$parameters['region']}' ";
        }
        if(strlen($parameters['startTime'])>0){
            $whereSql .= " AND a.`update_time`>= '{$parameters['startTime']}' ";
        }else{
            $nowTime=date('Y-m-d');
            $whereSql .= " AND a.`update_time`>= '$nowTime' ";
        }
        if(strlen($parameters['endTime'])>0){
            $whereSql .= " AND a.`update_time`<= '{$parameters['endTime']}' ";
        }
        $userModel = M('zuban_user_base', '', 'DB_DSN');
        $sqlCountStr = "SELECT a.`user_id` FROM `zuban_user_info` AS a  LEFT JOIN `zuban_user_base` AS u  ON u.`user_id` = a.`user_id` WHERE u.`status` =1 ".$whereSql;
        $userCount = $userModel->query($sqlCountStr);
        $userCount=count($userCount);
        if ($userCount <= 0) {
            $this->returnSuccess($rs);
        }
        $page=$this->page-1;
        $num=$this->page*$this->row;
        $sqlStr = "SELECT u.`user_id`, u.`nick_name`, u.`account`, u.`head_img`, u.`region_code`, u.`region_name`, u.`register_time`, a.`update_time` FROM `zuban_user_info` AS a  LEFT JOIN `zuban_user_base` AS u  ON u.`user_id` = a.`user_id` WHERE u.`status` =1 ".$whereSql." LIMIT $page,$num ";
        $userRs = $userModel->query($sqlStr);
        if (count($userRs) <= 0) {
            $this->returnSuccess($rs);
        }
        $rs['list'] = $userRs;
        $rs['total'] = $userCount;
        $this->returnSuccess($rs);
    }




}