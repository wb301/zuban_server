<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
use Zb\Service\CommonService;


class RegionController extends CommonController {


	/**

		获取地区列表

	*/
    public function getRegionList($code="1",$level=100)
    {
        if(strlen($code) < 0){
            $this->returnErrorNotice("地区编码错误！");
        }
        $this->returnSuccess($this->region_list($code,$level));
    }
}