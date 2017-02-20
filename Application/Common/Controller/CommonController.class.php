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

    /**
        限定请求方式
    */
    protected function astrict_method($method)
    {
        if($method !== $_SERVER['REQUEST_METHOD']){
            $this->returnErrorNotice(C('REQUEST_ERROR')['msg'],C('REQUEST_ERROR')['code']);
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


    //生成六位码
    protected function createCode($code='ORDER_CODE'){
        $paramModel = M('zuban_param','','DB_DSN');
        $paramRs = $paramModel->where("`is_delete` = 0 AND `code`='$code'")->getField("`value`");
        $paramModel->where("`code`='$code'")->setInc("value");
        return $paramRs+1;
    }
}