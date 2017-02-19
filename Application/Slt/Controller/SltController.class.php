<?php
namespace Slt\Controller;
use Slt\Service\SLTService;

class SltController extends SltCommonController
{
	public $slt = null;
    public function __construct() {
        parent::__construct();
        $this->slt = new SLTService();
    }


/**
     * @SWG\Post(
     *     path="/youfan_bi_server/index.php?c=Slt&m=Slt&a=getSltData",
     *     summary="获取商灵通图表",
     *     tags={"SLT"},
     *     description="获取商灵通图表",
     *     operationId="getSltData",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         name="ary",
     *         in="query",
     *         description="查询方法及参数",
     *         required=false,
     *         type="array",
     *     @SWG\Items(
     *           type="string",
     *         )
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
    public function getSltData($ary){
        $this->astrict_method(C('POST'));
        if(!is_array($ary)){
            $this->returnResult(array(),C('PARAM_ERROR')['msg'],C('PARAM_ERROR')['code']);
        }
        $rs = $this->slt->slt_https($ary);
        $this->returnResult($rs);
    }

    /**
     * @SWG\Get(
     *     path="/youfan_bi_server/index.php?c=Slt&m=Slt&a=debugDB",
     *     summary="测试DB",
     *     tags={"SLT"},
     *     description="测试DB",
     *     operationId="debugDB",
     *     consumes={"application/json"},
     *     produces={"application/json"},
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
    public function debugDB(){
        $this->astrict_method(C('GET'));
        $rs = $this->slt->debugDB();
        $this->returnResult($rs);
    }
}