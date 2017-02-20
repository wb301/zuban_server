<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
use Zb\Service\CommonService;


class ProductController extends CommonController {


	/**

		获取分类列表

	*/
    public function getProductList($categoryId=1)
    {
    	$categoryId = intval($categoryId);
        if($categoryId < 0){
            $this->returnErrorNotice("分类编码错误！");
        }
        $tempCategoryModel = M('admin_product_category');
        $categoryIdList = $tempCategoryModel->db(0,'DB_DSN')->where('`status`= 1 AND ( `id` = '.$categoryId.' OR `parent_id` = '.$categoryId.' )')->getField("id", true);

        // $categoryIdList


        // getUserInfoByAryList

        $this->returnSuccess($categoryIdList);
    }
}
