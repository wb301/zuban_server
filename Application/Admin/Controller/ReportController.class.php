<?php
namespace Admin\Controller;
use Admin\Controller\AdminCommonController;

class ReportController extends AdminCommonController
{
    
    /**
     * 用户统计
     * http://localhost/zuban_server/index.php?c=Admin&m=Report&a=userStatistics&token=1111&type=0&startTime=&endTime=&region=
     * 请求方式:get
     * 服务名:Wap
     * 参数:
     * @param $token 用户编号
     * @param $startTime 开始时间
     * @param $endTime 结束时间
     * @param $region 区域code
     *
     */
    public function userStatistics(){
        $keyAry = array(
            'startTime' => "",
            'endTime' => "",
            'region' => "",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        $rs=array(
            'today'=>array(
                'all_num'=>$this->getAllNum(0,$parameters['region']),
                'register_num'=>$this->getRegisterNum(0,$parameters['region']),
                'login_num'=>$this->getloginNum(0,$parameters['region']),
            ),
            'yestoday'=>array(
                'all_num'=>$this->getAllNum(1,$parameters['region']),
                'register_num'=>$this->getRegisterNum(1,$parameters['region']),
                'login_num'=>$this->getloginNum(1,$parameters['region']),
            ),
            'search'=>array(
                'all_num'=>0,
                'register_num'=>0,
                'login_num'=>0,
            ),
        );
        if(strlen($parameters['startTime'])>0&&strlen($parameters['endTime'])>0){
            $rs['today']['search']=array(
                'all_num'=>$this->getAllNum(2,$parameters['region'],$parameters['startTime'],$parameters['endTime']),
                'register_num'=>$this->getRegisterNum(2,$parameters['region'],$parameters['startTime'],$parameters['endTime']),
                'login_num'=>$this->getloginNum(2,$parameters['region'],$parameters['startTime'],$parameters['endTime']),
            );
        }

        $this->returnSuccess($rs);
    }

    /**
     * 注册用户统计
     * @param $startTime 开始时间
     * @param $endTime 结束时间
     * @param $region 区域code
     *
     */
    protected function getRegisterNum($type=0,$region='',$startTime='',$endTime='')
    {
        $registerNum=0;
        $today=date("Y-m-d");
        $yestoday=date("Y-m-d",strtotime("-1 day"));
        $where=' 1=1 ';
        if(strlen($region)>0){
            $where.=" AND `region_code`= '$region' ";
        }
        if($type==0){
            $where.=" AND `register_time`>= '$today' ";
        }
        if($type==1){
            $where.=" AND `register_time`>= '$yestoday' AND `register_time`<= '$today'";
        }
        if($type==2){
            $where.=" AND `register_time`>= '$startTime' AND `register_time`<= '$endTime'";
        }
        $userModel = M('zuban_user_base', '', 'DB_DSN');
        $registerNum = $userModel->where("$where")->count();

        return $registerNum;
    }


    /**
     * 注册用户统计
     * @param $startTime 开始时间
     * @param $endTime 结束时间
     * @param $region 区域code
     *
     */
    protected function getloginNum($type=0,$region='',$startTime='',$endTime='')
    {
        $loginNum=0;
        $today=date("Y-m-d");
        $yestoday=date("Y-m-d",strtotime("-1 day"));
        $where=' 1=1 ';
        if(strlen($region)>0){
            $where.=" AND u.`region_code`= '$region' ";
        }
        if($type==0){
            $where.=" AND a.`update_time`>= '$today' ";
        }
        if($type==1){
            $where.=" AND a.`update_time`>= '$yestoday' AND a.`update_time`<= '$today'";
        }
        if($type==2){
            $where.=" AND a.`update_time`>= '$startTime' AND a.`update_time`<= '$endTime'";
        }
        $userModel = M('zuban_user_base', '', 'DB_DSN');
        $sqlCountStr = "SELECT a.`user_id` FROM `zuban_user_info` AS a  LEFT JOIN `zuban_user_base` AS u  ON u.`user_id` = a.`user_id` WHERE ".$where;
        $userCount = $userModel->query($sqlCountStr);
        $loginNum=count($userCount);
        return $loginNum;
    }


    /**
     * 总注册用户统计
     * @param $startTime 开始时间
     * @param $endTime 结束时间
     * @param $region 区域code
     * @param $type 0今天1昨天2筛选
     *
     */
    protected function getAllNum($type=0,$region='',$startTime='',$endTime='')
    {
        $allNum=0;
        $today=date("Y-m-d");
        $yestoday=date("Y-m-d",strtotime("-1 day"));
        $where=' 1=1 ';
        if(strlen($region)>0){
            $where.=" AND `region_code`= '$region' ";
        }
        if($type==1){
            $where.="  AND `register_time`<= '$today'";
        }
        if($type==2){
            $where.=" AND `register_time`>= '$startTime' ADN `register_time`<= '$endTime'";
        }
        $userModel = M('zuban_user_base', '', 'DB_DSN');
        $allNum = $userModel->where("$where")->count();

        return $allNum;
    }





}