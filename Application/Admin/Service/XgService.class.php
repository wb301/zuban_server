<?php
namespace Admin\Service;
use Common\Service\XgCommonService;
use Admin\Model\XgModel;

class XgService extends XgCommonService
{
    public function __construct() {
        parent::__construct();
        $this->xg = new XgModel();
    }


    /**
       安卓信鸽全设备推送
     */
    public function PushAllAndroidDevices($config,$title,$msg,$custom=array())
    {
        //逻辑层
        return parent::PushAllAndroidDevices($config,$title,$msg,$custom);
    }

    /**
       安卓信鸽全设备推送
     */
    public function PushAllIoSDevices($config,$msg,$custom=array())
    {
        //逻辑层
        return parent::PushAllIoSDevices($config,$msg,$custom);
    }
}
?>