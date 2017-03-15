<?php
namespace Admin\Controller;
use Admin\Controller\AdminCommonController;


class CategoryController extends AdminCommonController {


	/**

		获取分类列表

	*/
    public function getCategoryList($id=1,$level=3,$mapping=array(),$fixAll=0)
    {
    	$id = intval($id);
        if($id < 0){
            $this->returnErrorNotice("分类编码错误！");
        }
        //权限验证
        $adminInfo = $this->checkToken(1);
        $adminCode = $adminInfo['admin_code'];
        $map = array(
            'is_free' => 'is_free',
            'sort' => 'sort',
            'status' => 'status',
            'create_time' => 'create_time',
            'update_time' => 'update_time'
        );
        $map = array_merge($map,$mapping);
        $categoryList = $this->category_list($id,$level,$map,"");
        if($fixAll > 0){
            $push = $categoryList[0];
            unset($push['children']);
            $categoryList = $this->fixAllForTree($categoryList,$push,$level-2,$map,"全部","category_name","id","parent_id");
        }

        $this->returnSuccess($categoryList);
    }

    /**

        新增分类

    */
    public function createCategoryInfo()
    {
        $this->_POST();
        $categoryInfo = $_POST['categoryInfo'];
        if(empty($categoryInfo)){
            $this->returnErrorNotice("分类参数错误！");
        }
        $keyAry = array(
            'parent_id' => "父级id不能为空!",
            'category_name' => "分类名不能为空！",
            'is_free' => "分类是否收费不能为空！",
            'sort' => "分类排序不能为空!",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry,$categoryInfo);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        if(intval($categoryInfo['parent_id']) <= 0){
            $this->returnErrorNotice('请添加父级id!');
        }
        if(intval($categoryInfo['sort']) <= 0){
            $this->returnErrorNotice('请添加排序!');
        }
        //权限验证
        $adminInfo = $this->checkToken(1);
        $adminCode = $adminInfo['admin_code'];

        //查询分类level
        $categoryLevel = $this->getCategoryLevel(intval($categoryInfo['parent_id']));
        //修正排序信息
        $this->fixCategorySort(intval($categoryInfo['parent_id']),intval($categoryInfo['sort']));

        //新增分类信息
        $nowTime = date('Y-m-d H:i:s');
        $newId = M('admin_product_category','','DB_DSN')->add(array(
            'parent_id' => intval($categoryInfo['parent_id']),
            'category_name' => $categoryInfo['category_name'],
            'is_free' => intval($categoryInfo['is_free']),
            'sort' => intval($categoryInfo['sort']),
            'level' => $categoryLevel,
            'status' => 1,
            'create_time' => $nowTime,
            'update_time' => $nowTime,
            'img' => ""
        ));

        $operation = "Category-createCategoryInfo-admin_product_category-".$newId;
        $remark = "新增分类[".$categoryInfo['category_name']."],生成新数据id:".$newId;
        $this->insertHistory($adminCode,$operation,$remark);

        $this->returnSuccess($newId);
    }

    private function getCategoryLevel($parentId)
    {
        $tempCategoryModel = M('admin_product_category','','DB_DSN');
        $categoryLevel = intval($tempCategoryModel->where("`status`= 1 AND `id` = $parentId")->getField("level"))+1;
        if($categoryLevel > 3){
            $this->returnErrorNotice('暂时只支持2级分类');
        }
        return $categoryLevel;
    }

    private function fixCategorySort($parentId,$sort)
    {
        //所有往后的排序重置+1
        $tempCategoryModel = M('admin_product_category','','DB_DSN');
        $tempCategoryModel->where("`status`= 1 AND `parent_id` = $parentId AND `sort` >= $sort ")->setInc("sort");
    }


    /**

        编辑分类

    */
    public function updateCategoryInfo()
    {
        $this->_POST();
        $categoryInfo = $_POST['categoryInfo'];
        if(empty($categoryInfo)){
            $this->returnErrorNotice("分类参数错误！");
        }
        $keyAry = array(
            'id' => "分类id不能为空!"
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry,$categoryInfo);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        //权限验证
        $adminInfo = $this->checkToken(1);
        $adminCode = $adminInfo['admin_code'];

        $id = intval($parameters['id']);
        $tempCategoryModel = M('admin_product_category','','DB_DSN');
        $categoryAry = $tempCategoryModel->where("`id` = $id")->find();
        if(empty($categoryAry)){
            $this->returnErrorNotice('分类信息错误!');
        }

        $updateAry = array(
            'update_time' => date('Y-m-d H:i:s')
        );
        if(isset($categoryInfo['sort'])){
            //修正排序信息
            $this->fixCategorySort(intval($categoryAry['parent_id']),intval($categoryInfo['sort']));
            $updateAry['sort'] = intval($categoryInfo['sort']);
        }
        if(isset($categoryInfo['is_free'])){
            $updateAry['is_free'] = intval($categoryInfo['is_free']);
            if($updateAry['is_free'] > 1){
                $updateAry['is_free'] = 1;
            }else if($updateAry['is_free'] < 0){
                $updateAry['is_free'] = 0;
            }
            //修复该分类下商品信息
            $categoryModel = M('zuban_product_category','','DB_DSN');
            $productCodeList = $categoryModel->where("`category_id` = $id")->getField("product_sys_code", true);
            if(!empty($productCodeList)){
                $lookPrice = $this->getLookPrice($updateAry['is_free']);
                $productCodeListStr = getListString($productCodeList);
                M('zuban_product_goods','','DB_DSN')->where("`product_sys_code` IN  ($productCodeListStr)")->save(array(
                    'update_time' => date('Y-m-d H:i:s'),
                    'look_price' => $lookPrice
                ));
            }
        }
        if(isset($categoryInfo['status'])){
            $updateAry['status'] = intval($categoryInfo['status']);
        }
        if(isset($categoryInfo['category_name']) && strlen($categoryInfo['category_name']) > 0){
            $updateAry['category_name'] = $categoryInfo['category_name'];
        }
        if(isset($categoryInfo['img']) && strlen($categoryInfo['img']) > 0){
            $updateAry['img'] = $categoryInfo['img'];
        }

        //修改分类信息
        $tempCategoryModel->where("`id` = $id")->save($updateAry);

        $operation = "Category-updateCategoryInfo-admin_product_category-".$id;
        $remark = "修改分类[".$id."],生成新数据:".json_encode($updateAry);
        $this->insertHistory($adminCode,$operation,$remark);

        $this->returnSuccess($id);
    }
}
