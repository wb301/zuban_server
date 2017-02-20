<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
use Zb\Service\CommonService;


class CategoryController extends CommonController {


	/**

		获取分类列表

	*/
    public function getCategoryList($id=1,$level=100)
    {
    	$id = intval($id);
        if($id < 0){
            $this->returnErrorNotice("分类编码错误！");
        }
        $tempCategoryModel = M('admin_product_category');
        $categoryRs = $tempCategoryModel->db(0,'DB_DSN')->where('`status`= 1 AND `level`<='.$level)->field('`id`,`parent_id`,`category_name`,`level`,`img`')->order(" `sort` ASC,`level` ASC ")->select();

        $service = new CommonService();
        $categoryList = $service->findChildren($categoryRs,$id);
        $this->returnSuccess($categoryList);
    }
}
