<?php
namespace Slt\Service;
use Slt\Model\SltModel;

class SltService extends SltCommonService
{
    public $slt = null;
    public function __construct() {
        parent::__construct();
        $this->slt = new SltModel();
    }

    public function slt_https($array)
    {
        return self::trySV(function() use ($array){
            $rs = array();
            foreach ($array as $key => $value) {
                $rangeIDs = $value['func'];
                $sqlParams = $value['params'];
                $rs[$key] = json_decode(
                            self::xml_to_json(
                                $this->slt->slt_https($rangeIDs,$sqlParams)
                            )
                        );
            }
            return $rs;
        },-600,"外部访问异常！");
    }


    public function debugDB()
    {
        return self::trySV(function(){
            return $this->slt->debugDB();
        });
    }
}
?>