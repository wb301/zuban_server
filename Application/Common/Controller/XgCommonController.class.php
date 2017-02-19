<?php
namespace Common\Controller;
use Common\Service\XgCommonService;

class XgCommonController extends CommonController
{
	public $xg = null;
    public function __construct() {
        parent::__construct();
    }


    /**

        子类重构
        全设备推送

    */
    public function xgPushAllDevices($title,$msg){

        $rs = array();
        $rs[] = $this->xg->PushAllAndroidDevices(C('ANDROID_APP_XINGE'),$title,$msg);
        $rs[] = $this->xg->PushAllIosDevices(C('IOS_APP_XINGE'),$msg);

        return $rs;
    }
}