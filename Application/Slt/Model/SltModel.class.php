<?php
namespace Slt\Model;

class SltModel extends SltCommonModel
{
    public function slt_https($rangeIDs,$sqlParams)
    {
        return self::mid_https(C('SLT_URL'),array(
            'rangeIDs' => $rangeIDs,
            'sqlParams' => $sqlParams
        ));
    }


    public function debugDB()
    {
        return M("rbac_users")->db(0, 'DB_DSN')->count();
    }
}
?>