<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Litiefeng
// | 应用函数包
// +----------------------------------------------------------------------

// 将UNICODE编码后的内容进行解码

function unicode_decode($name)
{
    // 转换编码，将Unicode编码转换成可以浏览的utf-8编码
    $pattern = '/([\w]+)|(\\\u([\w]{4}))/i';
    preg_match_all($pattern, $name, $matches);
    if (!empty($matches))
    {
        $name = '';
        for ($j = 0; $j < count($matches[0]); $j++)
        {
            $str = $matches[0][$j];
            if (strpos($str, '\\u') === 0)
            {
                $code = base_convert(substr($str, 2, 2), 16, 10);
                $code2 = base_convert(substr($str, 4), 16, 10);
                $c = chr($code).chr($code2);
                $c = iconv('UCS-2', 'UTF-8', $c);
                $name .= $c;
            }
            else
            {
                $name .= $str;
            }
        }
    }
    return $name;
}

//匹配字符
function getLikeString($str){
    return "\_".$str."\_";
}

//匹配字符
function setLikeString($str){
    return "_".$str."_";
}


function replaceStr($str){
     //去除
    $str = str_replace("\\n", "", $str);
    $str = str_replace("\\t", "", $str);
    $str = str_replace("\\", "", $str);
    // $str = str_replace(" ", "", $str);
    $str = str_replace("\"", "", $str);
    // $str = str_replace("'", "", $str);
    $str = trim($str);
    return $str;
}


function checkKeyWordStr($str)
{
    $str = replaceStr($str);
    $str = str_replace("SELECT", "", $str);
    $str = str_replace("UPDATE", "", $str);
    $str = str_replace("INSERT", "", $str);
    $str = str_replace("DELETE", "", $str);
    if(strlen($str) >= 40){
        return false;
    }
    return true;
}

//获取客户端ip
function getIP()
{
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
        $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function getStrLength($str){
    // 将字符串分解为单元
    preg_match_all("/./us", $str, $match);

    return count($match[0]);
}


function delStr($str){
    $str = str_replace("__", ",", $str);
    $str = str_replace("_", "", $str);
    return $str;
}

/**
 * version：2.0.0
 * info： 模糊查询分割符号
 * params:stringListr
 * return:
 */
 function getListString($stringList){
    $listStr = "";
    foreach ($stringList as $key => $value) {
        $listStr .= ("'".$value."',");
    }

    //切除最后一个“,”
    $listStr = substr($listStr, 0, strlen($listStr) - 1) . "";

    return $listStr;
}

/**
 * info： 检测IP段
 * params:ip
 * return:
 */
function checkIp($ip)
{
    if (strlen($ip) <= 0) {
        return 0;
    }
    $arrayip = C('ARRAY_IP');//ip段
    $ipregexp = implode('|', str_replace(array('*', '.'), array('\d+', '\.'), $arrayip));
    return preg_match("/^(" . $ipregexp . ")$/", $ip);
}

function mk_dir($dir, $mode = 0755)
{
    if (is_dir($dir) || @mkdir($dir,$mode)) return true;
    if (!mk_dir(dirname($dir),$mode)) return false;
    return @mkdir($dir,$mode);
}

function base_url(){
    if(isset($_SERVER['HTTPS'])){
        $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
    }
    else{
        $protocol = 'http';
    }
    return $protocol . "://" . $_SERVER['HTTP_HOST'] .$_SERVER['SCRIPT_NAME'];
}

function current_url(){
    if(isset($_SERVER['HTTPS'])){
        $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
    }
    else{
        $protocol = 'http';
    }
    return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function mb_url($module,$controller,$action)
{
    $para = array(
        'c'=>$module,
        'm'=>$controller,
        'a'=>$action
    );
    $url = base_url().'?'.http_build_query($para);
    return $url;
}


function randStr($len=6,$format='ALL') {
    switch($format) {
        case 'ALL':
            $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'; break;
        case 'CHAR':
            $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'; break;
        case 'NUMBER':
            $chars='0123456789'; break;
        default :
            $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            break;
    }
    mt_srand((double)microtime()*1000000*getmypid());
    $password="";
    while(strlen($password)<$len) {
        $password.=substr($chars,(mt_rand()%strlen($chars)),1);
    }

    return $password;
}

//xml格式转换为json
function xml_to_json($source) {
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
function fixMoney($money,$num=100){
    $money = floor($money * $num) / $num;
    return $money;
}


/**

    按字段排序

*/
function array_sort($arr,$keys,$type='asc', $isNeedKey = false, $isDeleteSort=false){
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
function sortByKey($sortAry, $targetAry, $filedName=null, $isNeedFixIndex = false){
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
    无限分级 树状转数组
*/
function tree_to_List($array,$child='children')
{
    static $result_array = array();
    foreach($array as $key=>$value){
        if(isset($value[$child])){
            tree_to_List($value[$child],$child);
            unset($value[$child]);
        }
        $result_array[]=$value;
    }
    return $result_array;
}

/**
    无限分级 数组转树状
*/
function list_to_tree($list, $root, $pk = 'id', $pid = 'parent_id', $child = 'children')
{
    // 创建Tree
    $tree = array();
    if ( !is_array($list) ) return $tree;
    // 创建基于主键的数组引用
    $refer = array();
    foreach ($list as $key => $data) {
        $refer[$data[$pk]] = & $list[$key];
    }
    foreach ($list as $key => $data) {
        // 判断是否存在parent
        $parentId = $data[$pid];
        if (is_array($root)) {
            if (in_array($parentId, $root)) {
                $tree[] = & $list[$key];
            } else {
                if (isset($refer[$parentId])) {
                    $parent = & $refer[$parentId];
                    $parent[$child][] = & $list[$key];
                }
            }
            continue;
        }
        if ($root == $parentId) {
            $tree[] = & $list[$key];
        } else {
            if (isset($refer[$parentId])) {
                $parent = & $refer[$parentId];
                $parent[$child][] = & $list[$key];
            }
        }
    }
    return $tree;
}

function arrayUnique($data,$field){
    if(count($data)<=0){
        return array();
    }
    $newData=array();
    $checkList=array();
    foreach($data AS $key=>$value){
        $checkfield = $value[$field];
        if(!in_array($checkfield,$checkList)){
            array_push($newData,$value);
        }
        array_push($checkList,$checkfield);
    }
    return $newData;
}
