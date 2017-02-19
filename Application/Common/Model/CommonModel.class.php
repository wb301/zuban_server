<?php
namespace Common\Model;
require_once (APP_PATH.'Common/Common/XingeApp.php');
require_once (APP_PATH.'Common/Common/redisCache.php');

use Common\Common\redisCache;
use Common\Common\XingeApp;


/**

	数据访问

*/
class CommonModel
{
	/**

		外部请求  可传入闭包设置ch

	*/
	public function https($url,$parameters,$method,$accessToken="",$func=null)
	{
	    if($method == "GET"){
	        $url = $url . '?' . http_build_query($parameters);
	    }
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_HEADER, 0 );

	    if(in_array($method, array("POST","P_SMS"))){
	        curl_setopt($ch, CURLOPT_POST, 1 );
	        curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
	    }

	    if($func){
	        $ch = $func($ch);
	    }

	    $header = array();
	    $header [] = 'Accept:application/json';
	    $header [] = 'Authorization: Bearer ' . $accessToken="";
	    if($method == "P_SMS"){ //sms
	        $header [] = 'Content-Type:application/json';
	    }

	    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	    $output = curl_exec($ch);
	    curl_close($ch);

	    return $output;
	}
}
?>