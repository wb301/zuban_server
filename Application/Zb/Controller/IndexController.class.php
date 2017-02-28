<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
class IndexController extends CommonController {
    public function index(){
        $a=array(
            'url'=>'www.baidu.com',
            'user'=>'heheh'
        );
        print_r(json_encode($a));
        $b='{"url":"www.baidu.com","user":"heheh"}';
        print_r($_REQUEST);exit;
       }
}