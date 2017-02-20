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
        $tempCategoryModel = M('admin_product_category','','DB_DSN');
        $categoryIdList = $tempCategoryModel->where('`status`= 1 AND ( `id` = '.$categoryId.' OR `parent_id` = '.$categoryId.' )')->getField("id", true);

        if(empty($categoryIdList)){
            $this->returnErrorNotice("分类编码错误！");
        }

        $categoryIdListStr = join(",",$categoryIdList);
        $productModel = M('zuban_product_goods','','DB_DSN');
        $sql = "SELECT count(c.`id`) AS `num` FROM `zuban_product_category` AS c INNER JOIN `zuban_product_goods` AS g ON c.`product_sys_code` = g.`product_sys_code` WHERE c.`category_id` IN ($categoryIdListStr);";
        $productNumRs = $productModel->query($sql);
        $this->pageAry['total'] = intval($productNumRs[0]['num']);

        if($this->pageAry['total'] <= 0){
            $this->returnSuccess($this->pageAry);
        }

        $this->setPageRow();
        $sql = "SELECT g.*, c.`category_id`, c.`category_name` FROM `zuban_product_category` AS c INNER JOIN `zuban_product_goods` AS g ON c.`product_sys_code` = g.`product_sys_code` WHERE c.`category_id` IN ($categoryIdListStr) LIMIT ".($this->page-1).",".$this->row.";";
        $productList = $productModel->query($sql);
        $this->pageAry['list'] = $this->getUserInfoByAryList($productList);

        $this->returnSuccess($this->pageAry);
    }
}
