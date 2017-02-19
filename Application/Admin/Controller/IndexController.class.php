<?php
namespace Admin\Controller;
use Think\Controller;
class IndexController extends Controller {
    public function index(){
        $this->show('<style type="text/css">*{ padding: 0; margin: 0; } div{ padding: 4px 48px;} body{ background: #fff; font-family: "微软雅黑"; color: #333;font-size:24px} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.8em; font-size: 36px }</style><div style="padding: 24px 48px;"> <h1>:)</h1><p>欢迎使用 <b>ThinkPHP</b>！</p><br/>[ 您现在访问的是Admin模块的Index控制器 ]</div><script type="text/javascript" src="http://tajs.qq.com/stats?sId=9347272" charset="UTF-8"></script>','utf-8');
    }


    /**

        文档生成及重定向展示

    */
    public function swagger(){
        $docPath = "";
        if(C('RUN_ENV')  === 0){
            //开发
            $docPath = $_SERVER['DOCUMENT_ROOT'];
        }else{
            $this->index();exit();
        }
        $path = $docPath.'/youfan_bi_server';
        //你想要哪个文件夹下面的注释生成对应的API文档
        $swagger = \Swagger\scan($path."/Application");
        header('Content-Type: application/json');
        $swagger_path = $path.'/swagger-ui/swagger.json';
        $res = file_put_contents($swagger_path, $swagger);
        if ($res == true) {
            $url = "http://".$_SERVER['HTTP_HOST']."/youfan_bi_server/swagger-ui/dist/";
            header('Location: '.$url);
        }else{
            $this->index();exit();
        }
    }
}