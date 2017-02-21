<?php
namespace Zb\Controller;
use Common\Controller\CommonController AS Controller;

/**

	控制器层 调用service层

*/
class CommonController extends Controller
{

    /**
     * version：2.0.0
     * info：检测用户id web用
     * params:user_Id，fileName
     * return:
     */
    protected function checkUserId($user_Id, $fileName = null, $isNotice = false)
    {

        $userModel = M('zuban_user_base','','DB_DSN');
        $userInfo = $userModel->where("`user_id` = '$user_Id' ")->select();
        if (!$userInfo || count($userInfo) <= 0) {
            if ($isNotice) {
                if ($fileName) {
                    $data[$fileName] = C('CODE_LOGIN_ERROR');
                    $data['message'] = "用户标识错误!";
                    $this->returnSuccess($data);
                } else {
                    return $this->returnErrorNotice("用户标识错误!");
                }
            } else {
                $data = array();
                if ($fileName) {
                    $data[$fileName] = C('CODE_LOGIN_ERROR');
                } else {
                    $data = C('CODE_LOGIN_ERROR');
                }
                return $this->returnSuccess($data);
            }
        }
        return $userInfo[0];
    }

    /**
     * info：token验证
     * params:token
     * return:
     */
    protected function checkToken($isNotice = 1)
    {
        $token=isset($_REQUEST['token'])?$_REQUEST['token']:'';
        $userInfoModel = M('zuban_user_info', '', 'DB_DSN');
        $userInfo = $userInfoModel->where("`token` = '$token' ")->field("`user_id`,`device`,`logitude`,`latitude`")->select();
        if (!$userInfo || count($userInfo) <= 0) {
            if ($isNotice) {
                return $this->returnErrorNotice("用户标识错误!");
            } else {
                return null;
            }
        }
        return $userInfo[0];
    }


    /**
     * info：获取价格信息
     * params:productList
     * return:array
     */
    public function  getProductPrice($productList){

        $proCodeList = array();
        foreach ($productList AS $key => $value) {
            $productList[$key]['price'] = 0;
            $productList[$key]['look_price'] = 0;
            $productList[$key]['product_name'] = '';
            $productList[$key]['price_type'] = 1;
            $productList[$key]['status'] = 0;
            $productList[$key]['num'] = $value['num'];
            array_push($proCodeList, $value['product_sys_code']);
        }
        $proCodeListStr = getListString($proCodeList);
        $productGoodsModel = M('zuban_product_goods', '', 'DB_DSN');
        $productRs = $productGoodsModel->where("`product_sys_code` IN ($proCodeListStr)")->field("`price_type`,`product_sys_code`,`price`,`status`,`product_name`,`look_price`")->select();
        if (count($productRs) > 0) {
            foreach ($productList AS $key => $value) {
                $proCode = $value['product_sys_code'];
                    foreach ($productRs AS $k => $v) {
                        if ($v['product_sys_code'] == $proCode) {
                            $productList[$key]['price'] = $v['price'];
                            $productList[$key]['look_price'] = $v['look_price'];
                            $productList[$key]['product_name'] = $v['product_name'];
                            $productList[$key]['price_type'] = $v['price_type'];
                            $productList[$key]['status'] = $v['status'];
                        }
                    }
                }
            }

        return $productList;

    }

    /**
     * info：获取价格信息
     * params:productList
     * return:array
     */
    public function  getProductListByCode($productCodeList){
        $proCodeListStr = getListString($productCodeList);
        $productGoodsModel = M('zuban_product_goods', '', 'DB_DSN');
        $productRs = $productGoodsModel->where("`product_sys_code` IN ($proCodeListStr)")->field("`price_type`,`product_sys_code`,`price`,`status`,`product_name`,`look_price`,`product_image`,`product_phone`,`profession`,`region_code`")->select();
        return $productRs;

    }

    /**
     * version：2015-8-30
     * 获取订单状态名称
     * 参数:
     * 无参数
     */
    protected function getSatusOrder($status){

        $statusNameAry=array(
            '0'=>'待付款',
            '1'=>'待发货',
            '5'=>'已发货',
            '6'=>'交易完成',
            '15'=>'交易关闭',
        );
        return $statusNameAry[$status];
    }

    /**
     * 绑定支付信息
     * @param $payAry
     * @param $orderAry
     * @return mixed
     */
    protected function getOrderPay($payAry, $orderAry)
    {
        foreach ($orderAry as $key => $value) {
            $orderNo = $value['order_no'];
            $orderAry[$key]['paymentList'] = array();
            if (count($payAry) > 0) {
                foreach ($payAry as $ok => $ov) {
                    if ($orderNo == $ov['order_no']) {
                        array_push($orderAry[$key]['paymentList'], $ov);
                    }
                }
            }
        }
        return $orderAry;
    }

    //获取地区列表
    protected function region_list($code,$level=999999,$mapping=null)
    {

        $field  = "";
        if($mapping){
            $field = "";
            foreach ($mapping as $key => $value) {
                $field .= "`$key` AS $value, ";
            }
        }
        $tempBaseRegionModel = M('zuban_temp_base_region','','DB_DSN');
        $regionRs = $tempBaseRegionModel->where('`status`= 1 AND `level`<='.$level)->field($field.'`code`,`parent_code`,`name`,`level`')->order(" `id` ASC,`level` ASC ")->select();

        return list_to_tree($regionRs,$code,"code","parent_code");
    }

    //获取地区列表
    protected function category_list($id,$level=999999)
    {
        $tempCategoryModel = M('admin_product_category','','DB_DSN');
        $categoryRs = $tempCategoryModel->where('`status`= 1 AND `level`<='.$level)->field('`id`,`parent_id`,`category_name`,`level`,`img`')->order(" `sort` ASC,`level` ASC ")->select();

        return list_to_tree($categoryRs,$id);
    }

}