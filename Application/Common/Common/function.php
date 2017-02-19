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
