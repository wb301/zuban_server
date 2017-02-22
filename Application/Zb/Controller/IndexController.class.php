<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
class IndexController extends CommonController {
    public function index(){
        $pay = \Pay\BasePay::getInstance('wx');
        $pay->payOrder('14877730691001000003','20','222222222','wx');
       }
}