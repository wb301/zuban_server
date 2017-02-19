<?php
namespace Slt\Model;
use Common\Model\CommonModel;


/**

	数据访问,转换层

*/
class SltCommonModel extends CommonModel
{
	public function mid_https($url,$parameters)
	{
	    $func = function($ch){
	    	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	        return $ch;
	    };
	    return self::https($url,$parameters,C('GET'),"",$func);
	}
}
?>