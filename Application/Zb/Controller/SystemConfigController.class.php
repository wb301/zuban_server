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

        $userInfo = $this->checkToken();
        $vipInfo = $this->getVip($userInfo["user_id"]);
        $vipConfig = json_decode($this->getSysConfig('VIP_LIST'), true);

        $nowTime = time();
        $endTime = strtotime($vipInfo["end_time"]);
        $days = intval(($endTime - $nowTime) / (24 * 3600));

        $this->returnSuccess(array("info" => $vipInfo, "config" => $vipConfig, "days" => $days));
    }

    /**
        修改会员充值列表
    */
    public function updVipPayList($vipList = array())
    {
        $vipList = array(
                        array(
                            "img"   =>  "",
                            "name" => "银卡会员",
                            "level" =>  1,
                            "price" =>  20,
                            "month" => 1
                        ),
                        array(
                            "img"   =>  "",
                            "name" => "金卡会员",
                            "level" =>  2,
                            "price" =>  50,
                            "month" => 3
                        ),
                        array(
                            "img"   =>  "",
                            "name" => "钻卡会员",
                            "level" =>  3,
                            "price" =>  120,
                            "month" => 12
                        )
                    );

        $vipJson = json_encode($vipList);
        $systemConfigModel = M('admin_system_config','','DB_DSN');
        $systemConfigModel->where("`status` = 1 AND `is_auto` = 0 AND `config_key` = 'VIP_LIST'")->setField("config_value", $vipJson);

        $this->returnSuccess(true);
    }
}