<?php
namespace Admin\Controller;
use Admin\Controller\AdminCommonController;

class RegionController extends AdminCommonController {


	/**

		获取地区列表

	*/
    public function getRegionList($code="1",$level=4,$mapping=null)
    {
        if(strlen($code) < 0){
            $this->returnErrorNotice("地区编码错误！");
        }
        $regionList = $this->region_list($code,$level,$mapping);
        $this->returnSuccess($regionList);
    }
}