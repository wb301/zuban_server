<?php
namespace Common\Service;
use Think\Controller;

/**

	逻辑层,转换层，调用model层

*/
class CommonService extends Controller
{
	/**

		业务层通用

	*/
	protected function trySV($func,$code=-500,$msg="数据异常！")
	{
		$result = array(
			'code' => 100,
			'msg' => 'success',
			'data' => null
		);
        try {
        	$result['data'] = $func();
        }catch (Exception $e) {
			$result['code']  = $code;
			$result['msg']  = $msg;
			//todo:记录错误日志
		}
		return $result;
	}

	//xml格式转换为json
	public function xml_to_json($source) {
	    if(is_file($source)){ //传的是文件，还是xml的string的判断
	        $xml_array = simplexml_load_file($source);
	    }else{
	        $xml_array = simplexml_load_string($source);
	    }
	    $json = json_encode($xml_array); //php5，以及以上，如果是更早版本，请查看JSON.php
	    return $json;
	}

	/**

		价格格式化 100 两位 1000三位

	*/
	public function fixMoney($money,$num=100){
	    $money = floor($money * $num) / $num;
	    return $money;
	}


	/**

		按字段排序

	*/
	public function array_sort($arr,$keys,$type='asc', $isNeedKey = false, $isDeleteSort=false){
	    $keysvalue = $new_array = array();
	    foreach ($arr as $k=>$v){
	        $keysvalue[$k] = $v[$keys];
	    }
	    if($type == 'asc'){
	        asort($keysvalue);
	    }else{
	        arsort($keysvalue);
	    }
	    reset($keysvalue);
	    foreach ($keysvalue as $k=>$v){
	        if($isDeleteSort){
	            unset($arr[$k][$keys]);
	        }
	        if($isNeedKey){
	            $new_array[$k] = $arr[$k];
	         }else{
	            array_push($new_array, $arr[$k]);
	         }
	    }
	    return $new_array; 
	}


	/**

		按外部key排序

	*/
	public function sortByKey($sortAry, $targetAry, $filedName=null, $isNeedFixIndex = false){
	    if(!$filedName){
	        $filedName = "id";
	    }

	    $newAry = array();

	    if($sortAry && count($sortAry) > 0 && $targetAry && count($targetAry) > 0){
	        ksort($sortAry); //按key（pos） 排序

	        foreach ($sortAry as $sk => $sv) {
	            foreach ($targetAry as $tk => $tv) {
	                if($sv == $tv[$filedName]){
	                    if($isNeedFixIndex){
	                        $tv['index'] = $sk; //为安卓煞笔映射实体类单独改的配置
	                    }
	                    array_push($newAry, $tv);
	                    break;
	                }
	            }

	        }
	    }

	    return $newAry;
	}

	/**
    	查找下一级
    */
    public function findChildren($list, $pId){
        $r = array();
        foreach($list as $id=>$item){
            if($item['parent_id'] == $pId) {
                $length = count($r);
                $r[$length] = $item;
                if($t = $this->findChildren($list, $item['id']) ){
                    $r[$length]['subs'] = $t;
                }
            }
        }
        return $r;
    }

}
?>