<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
class IndexController extends CommonController {
    public function index(){
        $pay=new \Pay\BasePay();
        $rs=$pay->payOrder('14889774121001000543',0.01,'11111','wx');
        print_r($rs);exit;
    }
}