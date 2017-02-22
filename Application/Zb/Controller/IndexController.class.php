<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
class IndexController extends CommonController {
    public function index(){
        $pay = \Pay\BasePay::getInstance('wx');
        $pay->payOrder('14876127851000103530','1','222222222','wx');
       }
}