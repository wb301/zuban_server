<?php
/**

	redis缓存简单封装 by LG
	调用示例:
		$mRedis = new redisCache("key",30);
		$cache  = $mRedis->mGet();
		if($cache){
			return  $cache;
		}

		//不存在时掉生成数据代码
		xxxxxx
		$data = xxx;

		$mRedis->mSet(data);
		return data;

*/
namespace Common\Common;
class redisCache {
   /**
  	* $lifetime : 缓存文件有效期,单位为秒 默认1天
  	* $cacheid : 缓存文件路径,包含文件名
   */
   private $lifetime;
   private $cacheid;
   private $data;
   public $redis;
   private $hashKey = "PHP_";
   /**
  	* 析构函数,检查缓存目录是否有效,默认赋值
   */
   function __construct($cacheid,$lifetime=86400) {
	    //配置
	    $redis = new \Redis();
	    // redis服务器ip  端口  链接时长
	    $redis->connect(C('REDIS_HOST'),C('REDIS_PORT'), C('REDIS_TIMEOUT'));
	    $this->redis=$redis;
	    $this->cacheid = "PHP_".$cacheid;
	    $this->lifetime = $lifetime;
   }

   /**
  	* 检查缓存是否有效
   */
    private function isvalid(){
		$data=$this->redis->hMGet($this->cacheid, array('content','creattime'));
		$this->data=$data;

		if (!$data || !isset($data['content'])){
			return false;
		}
		if (time() - intval($data['creattime']) > $this->lifetime){
			return false;
		}
		return true;
    }

    /**
 	*取值
    */
    public function mGet(){
		if ($this->isvalid()) {
			return json_decode($this->data['content'], true);
		}
		return null;
    }


    /**
 	*赋值并设置有效期
    */
    public function mSet($content,$remark=""){
		try {
        $overdue = time() + $this->lifetime;
        $this->redis->hMset($this->cacheid, array('content'=>json_encode($content), 'creattime'=>time()));
        $this->redis->expireAt($this->cacheid, $overdue);
        $_ = array();
        $_[$this->cacheid] = json_encode(array('overdue' => $overdue,'remark' => $remark));
        $this->redis->hMset($this->hashKey, $_);
	    }catch (Exception $e) {
	       //写入缓存失败
	    }
    }

   /**
   * 清除缓存
   */
    public function mDelete() {
      	if ($this->isvalid()) {
            $this->redis->delete($this->cacheid);
            $this->redis->hDel($this->hashKey, $this->cacheid);
     	}
    }

    /**
 	    *模糊匹配keys  生产环境不支持使用keys
    */
    public function mGetAll(){
      $allAry = $this->redis->hgetall($this->hashKey);

      $returnAry = array();
      if($allAry){
        $checkTime = time();
        foreach ($allAry as $key => $value) {
              $value = json_decode($value, true);
              $overdue = intval($value['overdue']);
              if($overdue <= $checkTime){
                  $this->redis->hDel($this->hashKey, $key);
              }else{
                  $returnAry[$key] = $value['remark'];
              }
        }
      }
      return $returnAry;
    }
}