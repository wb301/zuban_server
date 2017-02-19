<?php
namespace Common\Controller;
use Think\Controller;

/**

	控制器层 调用service层

*/
class CommonController extends Controller
{
	public function __construct() {
        parent::__construct();
        date_default_timezone_set('PRC');
        $this->requestTime = time();
    }

    //数组分页通用返回结构
    protected $pageAry = array(
        'list' => array(),
        'total' => 0
    );

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

    /**

        通用返回

    */
    protected function returnResult($rs=array(),$msg="failed",$code=-100)
    {
        if(empty($rs)){
            $this->returnErrorNotice($msg,$code);
        }else{
            if(isset($rs['code'])&&isset($rs['msg'])){
                $code = $rs['code'];
                $msg = $rs['msg'];
                if($code > 0){
                    $this->returnSuccess($rs['data'],$msg,$code);
                }else{
                    $this->returnErrorNotice($msg,$code);
                }
            }else{
                $this->returnSuccess($rs);
            }
        }
    }

    //通用成功返回(尽量不使用)
    protected function returnSuccess($data=array(),$msg='success',$code=100)
    {
        $this->returnJQuery($data,$msg,$code,C('AJAX_STATUS_SUCCESS'));
    }


    //通用错误返回(尽量不使用)
    protected function returnErrorNotice($msg="failed",$code=-100){
        $this->returnJQuery(array(),$msg,$code,C('AJAX_STATUS_ERROR'));
    }
}