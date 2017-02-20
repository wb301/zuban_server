<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
use Zb\Service\CommonService;


class RegionController extends CommonController {


	/**

		获取地区列表

	*/
    public function getRegionList($id=1,$level=100)
    {
    	$id = intval($id);
        if($id < 0){
            $this->returnErrorNotice("地区编码错误！");
        }
        $tempBaseRegionModel = M('zuban_temp_base_region','','DB_DSN');
        $regionRs = $tempBaseRegionModel->where('`status`= 1 AND `level`<='.$level)->field('`id`,`code`,`parent_id`,`name`,`level`')->order(" `id` ASC,`level` ASC ")->select();

        $regionList = findChildren($regionRs,$id);
        $this->returnSuccess($regionList);
    }
}