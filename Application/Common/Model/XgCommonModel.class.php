<?php
namespace Common\Model;
use Common\Common\XingeApp;
use Common\Common\Message;
use Common\Common\MessageIOS;
use Common\Common\Style;
use Common\Common\ClickAction;

class XgCommonModel extends CommonModel
{
    public $xgConfig = array('ai' => '', 'sk' => '');

    public $mess = null;  //消息体


    /**
        安卓-消息体
     */
    public function getMessage($title,$content,$custom)
    {
        $mess = new Message();
        $mess->setTitle($title);
        $mess->setExpireTime(60); //推送消息保存时间，单位秒
        $mess->setContent($content);
        $mess->setType(Message::TYPE_NOTIFICATION);
        $style = new Style(0,1,1,1,-1);
        $mess->setStyle($style);
        $action = new ClickAction();
        $action->setActionType(ClickAction::TYPE_ACTIVITY);
        $mess->setAction($action);

        $mess->setCustom($custom);

        return $mess;
    }


    /**
        IOS-消息体
    */
    public function getMessageIOS($content,$custom)
    {
        $mess = new MessageIOS();
        $mess->setExpireTime(60); //推送消息保存时间，单位秒
        $mess->setAlert($content);
        $mess->setCustom($custom);

        return $mess;
    }

    /**

        全设备推送
        runEnv  运行环境 安卓默认0，IOS开发IOSENV_DEV 生产 IOSENV_PROD

     */
    public function PushAllDevices($mess,$runEnv = 0)
    {
        $push = new XingeApp($this->xgConfig['ai'], $this->xgConfig['sk']);

        $retAry = $push->PushAllDevices(0, $mess, $runEnv);

        return $retAry;
    }
}
?>