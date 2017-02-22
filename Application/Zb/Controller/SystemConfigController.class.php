<?php
namespace Zb\Controller;
use Zb\Controller\CommonController;
use Zb\Service\CommonService;


class SystemConfigController extends CommonController {

	/**
		获取会员充值列表
	*/
    public function getVipPayList()
    {

        $systemConfigModel = M('admin_system_config','','DB_DSN');
        $vipJson = $systemConfigModel->where("`status` = 1 AND `is_auto` = 0 AND `config_key` = 'VIP_LIST'")->getField("config_value");

        $this->returnSuccess($vipJson);
    }

    /**
        修改会员充值列表
    */
    public function updVipPayList($vipList = array())
    {
        $vipList = array(
                        array(
                            "img"   =>  "",
                            "level" =>  1,
                            "price" =>  20
                        ),
                        array(
                            "img"   =>  "",
                            "level" =>  2,
                            "price" =>  50
                        ),
                        array(
                            "img"   =>  "",
                            "level" =>  3,
                            "price" =>  120
                        )
                    );

        $vipJson = json_encode($vipList);
        $systemConfigModel = M('admin_system_config','','DB_DSN');
        $systemConfigModel->where("`status` = 1 AND `is_auto` = 0 AND `config_key` = 'VIP_LIST'")->setField("config_value", $vipJson);

        $this->returnSuccess(true);
    }
}