<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
use Zb\Service\CommonService;


class CategoryController extends CommonController {


	/**

		获取分类列表

	*/
    public function getCategoryList($id=1,$level=3,$mapping=null,$fixAll=0)
    {
    	$id = intval($id);
        if($id < 0){
            $this->returnErrorNotice("分类编码错误！");
        }
        $categoryList = $this->category_list($id,$level,$mapping);
        if($fixAll > 0){
            $push = $categoryList[0];
            unset($push['children']);
            $categoryList = $this->fixAllForTree($categoryList,$push,$level-2,$mapping,"分类","category_name","id","parent_id");
        }
        $this->returnSuccess($categoryList);
    }
}
