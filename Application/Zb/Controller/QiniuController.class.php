<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
use Zb\Service\CommonService;

// require_once APP_PATH.'/vendor/autoload.php';
use Qiniu\Auth;// 引入鉴权类


class QiniuController extends CommonController {

    /**

        获取七牛token

    */
    public function getQiniuToken()
    {
        $accessKey = 'UFL2RCDRO71BeQcmVvVdOoeYUpzScSkWMWmhob4n';
        $secretKey = 'w8PqW69HiTs52VbZYTyNx0SgFPSAqmudhxtH-uWy';
        // 要上传的空间
        $bucket = 'zuban';

        // 构建鉴权对象
        $auth = new Auth($accessKey, $secretKey);

        // 生成上传 Token
        $token = $auth->uploadToken($bucket);
        $this->returnSuccess($token);
    }
}
