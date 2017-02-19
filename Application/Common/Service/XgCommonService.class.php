<?php
namespace Common\Service;
use Common\Common\XingeApp;
use Common\Model\XgCommonModel;

class XgCommonService extends CommonService
{
    public $xg = null;
    public function __construct() {
        parent::__construct();
    }


    /**
       安卓信鸽全设备推送
     */
    public function PushAllAndroidDevices($config,$title,$msg,$custom=array())
    {
        $this->xg->xgConfig = $config;
        $mess = $this->xg->getMessage($title,$msg,$custom);
        return $this->xg->PushAllDevices($mess);
    }


    /**
       IOS信鸽全设备推送
     */
    public function PushAllIoSDevices($config,$msg,$custom=array())
    {
        $this->xg->xgConfig = $config;
        $mess = $this->xg->getMessageIOS($msg,$custom);

        if(C('RUN_ENV') >= 2){
            $runDev = XingeApp::IOSENV_PROD;
        }else{
            $runDev = XingeApp::IOSENV_DEV;
        }
        return $this->xg->PushAllDevices($mess,$runDev);
    }
}
?>