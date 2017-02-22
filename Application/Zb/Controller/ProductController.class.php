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
            'region_info' => "地区信息不能为空!",
            'category_list' => "服务类型不能为空!"
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry,$productInfo);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }

        $userInfo = $this->checkToken();
        $userId = $userInfo['user_id'];

        $productCode = $this->createCode("PRODUCT_CODE");
        $lookPrice = floatval($this->getSysConfig("LOOK_PRICE"));

        $price = $productInfo['price'];


        //新增商品数据
        $goodsAry = array(



        );

          // `user_id` varchar(255) NOT NULL COMMENT '用户id',
          // // `product_sys_code` varchar(255) NOT NULL DEFAULT '' COMMENT '服务编码',
          // `price` decimal(18,6) NOT NULL COMMENT '价格',
          // // `look_price` decimal(18,6) NOT NULL,
          // `price_type` tinyint(4) NOT NULL COMMENT '价格类型  1.时薪  2.日薪',
          // `product_info` text NOT NULL COMMENT '服务详情',
          // `product_image` varchar(255) NOT NULL,
          // `status` int(5) NOT NULL DEFAULT '0' COMMENT '状态',
          // // `region_info` varchar(255) NOT NULL COMMENT '具体地址  xx-xx-xx',
          // `region_code` varchar(255) NOT NULL DEFAULT '' COMMENT '地区归属地',
          // // `logitude` varchar(255) NOT NULL DEFAULT '' COMMENT '经度',
          // // `latitude` varchar(255) NOT NULL DEFAULT '' COMMENT '纬度',
          // `start_time` datetime NOT NULL COMMENT '开始时间',
          // `end_time` datetime NOT NULL COMMENT '结束时间',
          // `create_time` datetime NOT NULL COMMENT '创建时间',
          // `update_time` datetime NOT NULL COMMENT '修改时间',

        // $productModel = M('zuban_product_goods','','DB_DSN');
        // $productAry = $productModel->where("`product_sys_code` = '$productCode'")->find();
        // if(empty($productAry)){
        //     $this->returnErrorNotice("服务信息错误！");
        // }
        // //分类列表
        // $categoryModel = M('zuban_product_category','','DB_DSN');
        // $categoryRs = $categoryModel->where("`product_sys_code` = '$productCode'")->field("`category_id`,`category_name`")->select();
        // if(empty($categoryRs)){
        //     $this->returnErrorNotice("分类信息错误！");
        // }

        // //图片列表  image_list
        // $galleryModel = M('zuban_product_gallery','','DB_DSN');
        // $galleryRs = $galleryModel->where("`product_sys_code` = '$productCode'")->order("sort ASC ")->getField("image_url", true);
        // if(empty($galleryRs)){
        //     $galleryRs = array();
        // }


        $this->returnSuccess($rs);
    }





}
