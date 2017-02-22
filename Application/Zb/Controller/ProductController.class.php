<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
use Zb\Service\CommonService;


class ProductController extends CommonController {

    private $orderByMap = array(
        'zt' => " ORDER BY g.`status` DESC ",
        'mr' => " ORDER BY g.`update_time` DESC ",  //上新排序
        'jg_0' => " ORDER BY g.`price` ASC, g.`update_time` DESC ",  //价格升序排序
        'jg_1' => " ORDER BY g.`price` DESC, g.`update_time` DESC ",  //价格降序序排序
        'jl_0' => " ORDER BY `juli` ASC, g.`update_time` DESC ",  //距离升序排序
        'jl_1' => " ORDER BY `juli` DESC, g.`update_time` DESC ",  //距离降序排序
        'zh' => " ORDER BY `juli` ASC, g.`price` ASC, g.`update_time` DESC ",  //综合排序
    );

    //通用获取服务列表
    private function getProductList($where,$km=null,$orderBy='mr')
    {
        $productModel = M('zuban_product_goods','','DB_DSN');
        $sql = "SELECT count(c.`id`) AS `num` FROM `zuban_product_goods` AS g LEFT JOIN `zuban_product_category` AS c  ON c.`product_sys_code` = g.`product_sys_code` WHERE $where;";
        $productNumRs = $productModel->query($sql);
        $this->pageAry['total'] = intval($productNumRs[0]['num']);

        if($this->pageAry['total'] <= 0){
            return $this->pageAry;
        }
        $this->setPageRow();

        $fied = "";
        if($km && isset($km['lat']) && isset($km['log'])){
            $lat = floatval($km['lat']);
            $log = floatval($km['log']);
            $fied = ", round(6378.138*2*asin(sqrt(pow(sin( ($lat*pi()/180-g.`latitude`*pi()/180)/2),2)+cos($lat*pi()/180)*cos(g.`latitude`*pi()/180)* pow(sin( ($log*pi()/180-g.`logitude`*pi()/180)/2),2)))*1000) AS `juli`";
        }
        $order = "";
        if(isset($this->orderByMap[$orderBy])){
            $order = $this->orderByMap[$orderBy];
        }
        $sql = "SELECT g.* $fied FROM `zuban_product_goods` AS g LEFT JOIN  `zuban_product_category` AS c ON c.`product_sys_code` = g.`product_sys_code` WHERE $where $order LIMIT ".($this->page-1).",".$this->row.";";
        $this->pageAry['list'] = $productModel->query($sql);

        return $this->pageAry;
    }

	/**

		获取发布列表

	*/
    public function getShowProductList($latitude,$logitude,$orderBy='zh',$categoryId=1,$regionCode="1")
    {
    	$categoryId = intval($categoryId);
        if($categoryId < 0){
            $this->returnErrorNotice("分类编码错误！");
        }
        if(strlen($regionCode) < 0){
            $this->returnErrorNotice("地区编码错误！");
        }
        //条件格式化
        $categoryList = $this->category_list($categoryId);
        if(empty($categoryList)){
            $this->returnErrorNotice("分类信息错误！");
        }
        $regionList = $this->region_list($regionCode);
        if(empty($regionList)){
            $this->returnErrorNotice("地区信息错误！");
        }

        $categoryIdList = array_merge(array($categoryId),array_column(tree_to_List($categoryList), 'id'));
        $regionCodeList = array_merge(array($regionCode),array_column(tree_to_List($regionList), 'code'));

        $categoryIdListStr = join(",",$categoryIdList);
        $regionCodeListStr = getListString($regionCodeList);
        $where = "g.`status` = 1 AND c.`category_id` IN ($categoryIdListStr) AND g.`region_code` IN ($regionCodeListStr)";

        //查询
        $this->pageAry = $this->getProductList($where,array('lat'=>$latitude,'log'=>$logitude),$orderBy);
        $this->pageAry['list'] = $this->getUserInfoByAryList($this->pageAry['list']);

        $this->returnSuccess($this->pageAry);
    }

    /**

        获取发布列表

    */
    public function getMyProductList($status=-1,$orderBy='mr')
    {
        $userInfo = $this->checkToken();
        $userId = $userInfo['user_id'];

        $status = intval($status);
        $where = "g.`user_id` = '$userId' ";
        if($status >= 0 && $status <= 2){
            $where .= " AND `status` = $status";
        }
        $this->pageAry = $this->getProductList($where,$orderBy);

        $this->returnSuccess($this->pageAry);
    }

    /**

        获取发布详细信息

    */
    public function getProductInfo($productCode)
    {
        if(strlen($productCode) < 0){
            $this->returnErrorNotice("服务编码错误！");
        }

        $productModel = M('zuban_product_goods','','DB_DSN');
        $productAry = $productModel->where("`product_sys_code` = '$productCode'")->find();
        if(empty($productAry)){
            $this->returnErrorNotice("服务信息错误！");
        }
        //分类列表
        $categoryModel = M('zuban_product_category','','DB_DSN');
        $categoryRs = $categoryModel->where("`product_sys_code` = '$productCode'")->field("`category_id`,`category_name`")->select();
        if(empty($categoryRs)){
            $this->returnErrorNotice("分类信息错误！");
        }

        //图片列表
        $galleryModel = M('zuban_product_gallery','','DB_DSN');
        $galleryRs = $galleryModel->where("`product_sys_code` = '$productCode'")->order("sort ASC ")->getField("image_url", true);
        if(empty($galleryRs)){
            $galleryRs = array();
        }

        //查询当前用户
        $userInfo = $this->checkToken(0);
        $returnUserInfo = "";
        if(!empty($userInfo)){
            $userId = $userInfo['user_id'];
            if($userId == $productAry['user_id']){
                //是自己
                $returnUserInfo = $userInfo;
            }else{
                //查询是否为会员
                $vipCount = intval($this->getVip($userId));
                if($vipCount > 0){
                    $userModel = M('zuban_user_base','','DB_DSN');
                    $returnUserInfo = $userModel->where("`user_id` = '$userId' ")->find();
                }
            }
            if(!empty($returnUserInfo)){
                $returnUserInfo = base64_encode(json_encode($returnUserInfo));
            }
        }
        $productAry['category_list'] = $categoryRs;
        $productAry['image_list'] = $galleryRs;
        $productAry['user_info'] = $returnUserInfo;

        $this->returnSuccess($productAry);
    }


    /**

        发布上传

    */
    public function createProductInfo()
    {
        $this->_POST();
        $productInfo = $_POST['productInfo'];
        if(empty($productInfo)){
            $this->returnErrorNotice("服务参数错误！");
        }

        $keyAry = array(
            'product_info' => "服务内容不能为空!",
            'price' => "服务价格不能为空",
            'price_type' => "结算方式不能为空",
            'product_image' => "服务主图不能为空",
            'region_code' => "地区编码不能为空!",
            'region_name' => "地区信息不能为空!",
            'category_list' => "服务类型不能为空!",
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry,$productInfo);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        if(empty($productInfo['category_list'])|| count($productInfo['category_list']) <= 0){
            $this->returnErrorNotice('请添加服务类型!');
        }

        $userInfo = $this->checkToken();
        $userId = $userInfo['user_id'];

        $productCode = $this->createCode("PRODUCT_CODE");
        $lookPrice = floatval($this->getSysConfig("LOOK_PRICE"));
        $nowTime = date('Y-m-d H:i:s');
        //新增商品数据
        $goodsNewAry = array(
            'user_id' => $userId,
            'product_sys_code' => $productCode,
            'price' => floatval($productInfo['price']),
            'look_price' => $lookPrice,
            'price_type' => intval($productInfo['price_type']),
            'product_info' => $productInfo['product_info'],
            'product_image' => $productInfo['product_image'],
            'region_code' => $productInfo['region_code'],
            'region_name' => $productInfo['region_name'],
            'logitude' => $userInfo['logitude'],
            'latitude' => $userInfo['latitude'],
            'create_time' => $nowTime,
            'update_time' => $nowTime,
            'status' => 1
        );
        $productModel = M('zuban_product_goods','','DB_DSN');
        $productId = $productModel->add($goodsNewAry);

        //新增分类关系
        $categoryList = $productInfo['category_list'];
        $categoryNewList = array();
        foreach ($categoryList as $key => $value) {
            array_push($categoryNewList, array(
                'product_sys_code' => $productCode,
                'category_id' => $value['id'],
                'category_name' => $value['name']
            ));
        }
        $categoryModel = M('zuban_product_category','','DB_DSN');
        $categoryModel->addAll($categoryNewList);

        //新增附图
        if(!empty($productInfo['image_list'])&& count($productInfo['image_list']) > 0){
            $imageList = $productInfo['image_list'];
            $imageNewList = array();
            $index = 1;
            foreach ($imageList as $key => $value) {
                array_push($imageNewList, array(
                    'product_sys_code' => $productCode,
                    'image_url' => $value,
                    'sort' => $index,
                    'create_time' => $nowTime,
                    'update_time' => $nowTime
                ));
                $index++;
            }

            $galleryModel = M('zuban_product_gallery','','DB_DSN');
            $galleryModel->addAll($imageNewList);
        }

        $this->returnSuccess($productId);
    }





}
