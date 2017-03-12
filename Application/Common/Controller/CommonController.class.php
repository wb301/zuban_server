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
    protected function getSysConfig($key=null){
        $sysModel = M("admin_system_config", 0, "DB_DSN");
        $sysAry = $sysModel->where("`status` = 1 AND `is_auto` = 0 ")->getField("config_key,config_value");
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

}