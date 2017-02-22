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
        if(isset($_REQUEST['rows']) && intval($_REQUEST['rows']) > 0) {
            $this->row = $_REQUEST['rows'];
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

}