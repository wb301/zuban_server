<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
use Zb\Service\CommonService;


class RegionController extends CommonController {


	/**

		获取地区列表

	*/
    public function getRegionList($code="1",$level=4,$mapping=null,$fixAll=0)
    {
        if(strlen($code) < 0){
            $this->returnErrorNotice("地区编码错误！");
        }
        $regionList = $this->region_list($code,$level,$mapping);
        if($fixAll > 0){
            $push = $regionList[0];
            unset($push['children']);
            $regionList = $this->fixAllForTree($regionList,$push,$level-2,$mapping,"全部","name","code","parent_code");
        }
        $this->returnSuccess($regionList);
    }
}