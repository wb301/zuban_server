<?php
namespace Admin\Controller;
use Admin\Controller\AdminCommonController;

class SysController extends AdminCommonController
{
	//获取系统可修改配置
    public function getSysConfigList()
    {
    	//权限验证
        $adminInfo = $this->checkToken(1);
        $adminCode = $adminInfo['admin_code'];

    	$sysModel = M("admin_system_config", 0, "DB_DSN");
        $sysList = $sysModel->where("`status` = 1 AND `is_auto` = 0 ")->select();

       	$this->returnSuccess($sysList);
    }

    //修改系统配置
    public function updateSysConfig()
    {
    	$this->_POST();
        $keyAry = array(
            'key' => "配置属性名不能为空",
            'value' => "配置属性值不能为空"
        );
        //参数列
        $parameters = $this->getPostparameters($keyAry);
        if (!$parameters) {
            $this->returnErrorNotice('请求失败!');
        }
        $key = $parameters['key'];
        $value = $parameters['value'];

        //权限验证
        $adminInfo = $this->checkToken(1);
        $adminCode = $adminInfo['admin_code'];

        //验证key
    	$sysModel = M("admin_system_config", 0, "DB_DSN");
        $sysConfig = $sysModel->where("`status` = 1 AND `is_auto` = 0 AND `config_key` = '$key' ")->find();
        if(empty($sysConfig)){
        	$this->returnErrorNotice('配置属性名错误!');
        }

        if($key == 'VIP_LIST'){
        	if(!is_array(json_decode($value))){
        		$this->returnErrorNotice('配置属性值错误!');
        	}
        }
        //修改配置
        $sysConfigId = intval($sysConfig['id']);
        $sysModel->where("`id` = $sysConfigId")->save(array(
        	'config_value' => $value,
        	'update_time' => date('Y-m-d H:i:s')
        ));

        $operation = "Sys-updateSysConfig-admin_system_config-".$sysConfigId;
        $remark = "修改[".$sysConfig['config_name']."],将".$sysConfig['config_value']."变更为:".$value;
    	$this->insertHistory($adminCode,$operation,$remark);

    	$this->returnSuccess(1);
    }
}