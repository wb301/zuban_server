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
        $userInfo = $userInfoModel->where("`token` = '$token' ")->feild("`user_id`,`device`,`logitude`,`latitude`")->select();
        if (!$userInfo || count($userInfo) <= 0) {
            if ($isNotice) {
                return $this->returnErrorNotice("用户标识错误!");
            } else {
                return $this->returnSuccess(array());
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
            array_push($proCodeList, $value['product_sys_code']);
        }
        $proCodeListStr = getListString($proCodeList);
        $productGoodsModel = M('zuban_product_goods', '', 'DB_DSN');
        $productRs = $productGoodsModel->where("`product_sys_code` IN ($proCodeListStr)")->field("`price_type`,`product_sys_code`,`price`,`status`,`product_name`")->select();
        if (count($productRs) > 0) {
        
            foreach ($productList AS $key => $value) {
                $proCode = $value['product_sys_code'];
                    foreach ($productRs AS $k => $v) {
                        if ($v['product_sys_code'] == $proCode) {
                            $productList[$key]['price'] = $v['price'];
                            $productList[$key]['product_name'] = $v['product_name'];
                            $productList[$key]['price_type'] = $v['price_type'];
                            $productList[$key]['status'] = $v['status'];
                        }
                    }
                }
            }

    }


}