<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
class IndexController extends CommonController {
    public function index(){
        $pay = \Pay\BasePay::getInstance('wx');
        $pay->payOrder('14878567701001000009','1','222222222','wx');
       }
}