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
        $tempBaseRegionModel = M('zuban_temp_base_region');
        $regionRs = $tempBaseRegionModel->db(0,'DB_TEMP')->where('`status`= 1 AND `level`<='.$level)->field('`id`,`code`,`parent_id`,`name`,`level`')->order(" `id` ASC,`level` ASC ")->select();

        $service = new CommonService();
        $regionList = $service->findChildren($regionRs,$id);
        $this->returnSuccess($regionList);
    }
}