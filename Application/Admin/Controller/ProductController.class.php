<?php
namespace Admin\Controller;
use Admin\Controller\AdminCommonController;


class ProductController extends AdminCommonController {

    //通用获取服务列表
    private function getProductList($where)
    {
        $productModel = M('zuban_product_goods','','DB_DSN');
        $sql = "SELECT count(c.`id`) AS `num` FROM `zuban_product_goods` AS g LEFT JOIN `zuban_product_category` AS c  ON c.`product_sys_code` = g.`product_sys_code` WHERE $where;";
        $productNumRs = $productModel->query($sql);
        $this->pageAry['total'] = intval($productNumRs[0]['num']);

        if($this->pageAry['total'] <= 0){
            return $this->pageAry;
        }
        $this->setPageRow();

        $order = "ORDER BY g.`update_time` DESC";

        $sql = "SELECT c.`category_id`,c.`category_name`,g.* FROM `zuban_product_goods` AS g LEFT JOIN  `zuban_product_category` AS c ON c.`product_sys_code` = g.`product_sys_code` WHERE $where $order LIMIT ".($this->page-1)*$this->row.",".$this->row.";";
        $this->pageAry['list'] = $productModel->query($sql);

        //查询商品图
        $productCodeList = array();
        foreach ($this->pageAry['list'] as $key => $value) {
            $this->pageAry['list'][$key]['image_list'] = array();
            array_push($productCodeList, $value['product_sys_code']);
        }
        $productCodeListStr = getListString($productCodeList);
        $galleryModel = M('zuban_product_gallery','','DB_DSN');
        $galleryList = $galleryModel->where("`product_sys_code` IN ($productCodeListStr) ")->order('sort')->select();

        foreach ($this->pageAry['list'] as $key => $value) {
            foreach ($galleryList as $gk => $gv) {
                if($value['product_sys_code'] == $gv['product_sys_code']){
                    array_push($this->pageAry['list'][$key]['image_list'], $gv['image_url']);
                }
            }
        }

        return $this->pageAry;
    }


	/**

		获取发布列表

	*/
    public function getShowProductList($status=1,$categoryId=1,$regionCode="1")
    {
    	$categoryId = intval($categoryId);
        if($categoryId < 0){
            $this->returnErrorNotice("分类编码错误！");
        }
        if(strlen($regionCode) < 0){
            $this->returnErrorNotice("地区编码错误！");
        }
        //权限验证
        $adminInfo = $this->checkToken(1);
        $adminCode = $adminInfo['admin_code'];

        //条件格式化
        $where = "g.`status` = $status";
        if($categoryId > 1){
            $categoryIdList = array_merge(array($categoryId),array_column(tree_to_List($this->category_list($categoryId)), 'id'));
            $categoryIdListStr = join(",",$categoryIdList);
            $where .= " AND c.`category_id` IN ($categoryIdListStr) ";
        }
        if(intval($regionCode) > 1){
            $regionCodeList = array_merge(array($regionCode),array_column(tree_to_List($this->region_list($regionCode)), 'code'));
            $regionCodeListStr = getListString($regionCodeList);
            $where .= " AND g.`region_code` IN ($regionCodeListStr)";
        }

        //查询
        $this->pageAry = $this->getProductList($where);
        $this->pageAry['list'] = $this->getUserInfoByAryList($this->pageAry['list']);

        $this->returnSuccess($this->pageAry);
    }

    /**

        删除服务

    */
    public function deleteProductBySys()
    {
        $this->_POST();
        $productInfo = $_POST['productInfo'];
        if(empty($productInfo)){
            $this->returnErrorNotice("服务参数错误！");
        }
        $keyAry = array(
            'id' => "服务id不能为空!"
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry,$productInfo);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        //权限验证
        $adminInfo = $this->checkToken(1);
        $adminCode = $adminInfo['admin_code'];

        $id = intval($parameters['id']);

        $productModel = M('zuban_product_goods','','DB_DSN');
        $updateRow = M('zuban_product_goods','','DB_DSN')->where("`id` = $id AND `status` != 2")->save(array(
            'status' => 0,
            'update_time' => date('Y-m-d H:i:s')
        ));
        if($updateRow <= 0){
            $this->returnErrorNotice('商品处于出售状态,暂不可删除');
        }

        $operation = "Product-deleteProductBySys-admin_product_category-".$id;
        $remark = "删除服务[".$id."]";
        $this->insertHistory($adminCode,$operation,$remark);

        $this->returnSuccess($id);
    }
}
