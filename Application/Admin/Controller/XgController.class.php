<?php
namespace Admin\Controller;
use Admin\Service\XgService;
use Common\Controller\XgCommonController;

class XgController extends XgCommonController
{
    public function __construct() {
        parent::__construct();
        $this->xg = new XgService();
    }
     /**
         * @SWG\Get(
         *     path="/youfan_bi_server/index.php?c=Admin&m=Xg&a=xgPushAllDevices",
         *     summary="信鸽全设备推送",
         *     tags={"ADMIN"},
         *     description="信鸽全设备推送",
         *     operationId="xgPushAllDevices",
         *     consumes={"application/json"},
         *     produces={"application/json"},
         *     @SWG\Parameter(
         *         name="title",
         *         in="query",
         *         description="标题",
         *         required=true,
         *         type="string",
         *     ),
         *     @SWG\Parameter(
         *         name="msg",
         *         in="query",
         *         description="内容",
         *         required=true,
         *         type="string",
         *     ),
         *     @SWG\Response(
         *         response=1,
         *         description="successful operation",
         *         @SWG\Schema(
         *              @SWG\Property(property="time",  type="integer" ),
         *              @SWG\Property(property="status",  type="integer" ),
         *              @SWG\Property(property="code",  type="integer", description="100表示成功,<=0失败" ),
         *              @SWG\Property(property="msg",  type="string",  ),
         *              @SWG\Property(
         *                  property="data",
         *              ),
         *
         *         ),
         *     ),
         * )
    */
    public function xgPushAllDevices(){
        $this->astrict_method(C('GET'));
        if(!isset($_REQUEST['msg']) || !isset($_REQUEST['title'])){
            $this->returnResult(array(),C('PARAM_ERROR')['msg'],C('PARAM_ERROR')['code']);
        }

        //调用父类重写
        $rs = parent::xgPushAllDevices($_REQUEST['title'], $_REQUEST['msg']);

        $this->returnResult($rs);
    }
}