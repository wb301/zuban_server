<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
use Zb\Service\CommonService;


class ProductController extends CommonController {

    //通用获取商品列表
    private function getProductList($where,$km=null)
    {
        $productModel = M('zuban_product_goods','','DB_DSN');
        $sql = "SELECT count(c.`id`) AS `num` FROM `zuban_product_category` AS c INNER JOIN `zuban_product_goods` AS g ON c.`product_sys_code` = g.`product_sys_code` WHERE $where;";
        $productNumRs = $productModel->query($sql);
        $this->pageAry['total'] = intval($productNumRs[0]['num']);

        if($this->pageAry['total'] <= 0){
            return $this->pageAry;
        }
        $this->setPageRow();

        $fied = "";
        $order = " ORDER BY g.`update_time` DESC ";
        if($km){
            $lat = $km['latitude'];
            $log = $km['logitude'];
            $fied = ", round(6378.138*2*asin(sqrt(pow(sin( ($lat*pi()/180-g.`latitude`*pi()/180)/2),2)+cos($lat*pi()/180)*cos(g.`latitude`*pi()/180)* pow(sin( ($log*pi()/180-g.`logitude`*pi()/180)/2),2)))*1000) AS `juli`";
            $order = " ORDER BY `juli` ASC, g.`update_time` DESC ";
        }

        $sql = "SELECT g.*, c.`category_id`, c.`category_name` $fied FROM `zuban_product_category` AS c INNER JOIN `zuban_product_goods` AS g ON c.`product_sys_code` = g.`product_sys_code` WHERE $where $order LIMIT ".($this->page-1).",".$this->row.";";
        $this->pageAry['list'] = $productModel->query($sql);

        return $this->pageAry;
    }

	/**

		获取发布列表

	*/
    public function getShowProductList($latitude,$logitude,$categoryId=1)
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

        $this->pageAry = $this->getProductList("c.`category_id` IN ($categoryIdListStr)",array('latitude'=>$latitude,'logitude'=>$logitude));
        $this->pageAry['list'] = $this->getUserInfoByAryList($this->pageAry['list']);

        $this->returnSuccess($this->pageAry);
    }

    /**

        获取发布列表

    */
    public function getMyProductList()
    {
        $userInfo = $this->checkToken();
        $userId = $userInfo['user_id'];
        $this->pageAry = $this->getProductList("g.`user_id` = '$userId' ");

        $this->returnSuccess($this->pageAry);
    }
}
