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
        $accessKey = 'B7a2HYUmAWC0gRx8rGhi9UbPTkYg2_NgxBsMjZ3z';
        $secretKey = 'dpyqdifPjBQO3yISdcEPUYYvM_85Rtqcgsnu6h8l';
        // 要上传的空间
        $bucket = 'mbfun';

        // 构建鉴权对象
        $auth = new Auth($accessKey, $secretKey);

        // 生成上传 Token
        $token = $auth->uploadToken($bucket);
        $this->returnSuccess($token);
    }
}
