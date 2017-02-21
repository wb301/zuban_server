<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
use Zb\Service\CommonService;


class CategoryController extends CommonController {


	/**

		获取分类列表

	*/
    public function getCategoryList($id=1,$level=100,$mapping=null)
    {
    	$id = intval($id);
        if($id < 0){
            $this->returnErrorNotice("分类编码错误！");
        }
        $this->returnSuccess($this->category_list($id,$level,$mapping));
    }
}
