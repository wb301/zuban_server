<?php
namespace Wechat\Controller;
use Common\Controller\CommonController;
use Wechat\Model\WeiXinLoginModel;
class ThirdLoginController extends CommonController
{

    /*
     * 微信登陆
     * @param $redirect_url 需跳转地址
     * */
    public function wxLogin(){
        $domain = isset($_REQUEST['domain'])?$_REQUEST['domain']:'';
        $redirect_url = isset($_GET['redirect_url'])?$_GET['redirect_url']:'';
        $code = $_GET['code'];
        if(empty($redirect_url) && empty($code)){
            $this->returnErrorNotice('','跳转链接不能为空');
        }
        $r_url = $redirect_url;
        if(!$code){
            $redirect_url = json_decode($r_url,true);
            $redirect_url = $redirect_url['url'];

        }else{
            $redirect_url = $this->getThirdLogin($redirect_url,1);
        }
        $return_id = self::insertIntoThirdLogin($redirect_url,1);
        $url = C('THIRD_LOGIN')."youfan/api/index.php?c=Wechat&m=ThirdLogin&a=wxLogin&redirect_url={$return_id}&";
        $weixin = new WeiXinLoginModel();
        $data = $weixin->getOpenId($domain,$url);
        if($data){
            //获取用户信息
            $openid = $data['openid'];
            $access_token = $data['access_token'];
            $userInfo = $weixin->getOauthUserinfo($access_token,$openid);
            if($userInfo){
                $userData = $weixin->loginByOauth($userInfo);
                if($userData){
                    $return_data = array(
                        'user_id'=> $userData['user_id'],
                        'token' => $userData['token'],
                        'head_img'=>$userData['head_img'],
                        'nickname'=>$data['nick_name'],
                        'openid'=>$data['openid'],
                    );
                    $return_data = json_encode($return_data);
                    $is_check = strpos($redirect_url,'?');
                    if($is_check !==false){
                        $url_param = $redirect_url."&user=".$return_data;
                    }else{
                        $url_param = $redirect_url."?user=".$return_data;
                    }
                    header("Location:$url_param");
                }else{
                    $this->returnErrorNotice('','获取用户信息失败');
                }

            }
        }else{
            $this->returnErrorNotice('','获取openid失败');
        }
    }

    /*
    * qq登陆
     *
    * */
    public function qqLogin(){
        $domain = isset($_REQUEST['domain'])?$_REQUEST['domain']:'';
        $redirect_url = isset($_GET['redirect_url'])?$_GET['redirect_url']:'';
        $code = $_GET['code'];
        if(empty($redirect_url)){
            $this->returnError('','跳转链接不能为空');
        }
        $r_url = $redirect_url;
        if(!$code){
            $redirect_url = json_decode($r_url,true);
            $redirect_url = $redirect_url['url'];
        }else{
            $redirect_url = $this->getThirdLogin($redirect_url,2);
        }
        $return_id = self::insertIntoThirdLogin($redirect_url,2);
        $url =  C('THIRD_LOGIN')."api/youfan_merchant/index.php/wechat/ThirdLogin/qqLogin?redirect_url={$return_id}&";
        $qq = new QqLoginModel();
        $data = $qq->index($domain,$url);
        if($data){
            $openid = $data['openid'];
            $access_token = $data['access_token'];
            $userInfo = $qq->getUserInfo($access_token,$openid);
            $userInfo['openid'] = $openid;
            if($userInfo){
                //todo 将用户信息插入数据表
                //迁服务，调用基础服务接口
                $openid = $userInfo['openid'];
                $nickname = $userInfo['nickname'] ? $userInfo['nickname'] : '';
                $sex = $userInfo['gender'] ? $userInfo['gender'] : '';
                $province = $userInfo['province'] ? $userInfo['province'] : ''; //省份
                $city = $userInfo['city'] ? $userInfo['city'] : ''; //城市
                $country ='中国'; //qq没有返回国家，默认中国
                $headimgurl = $userInfo['figureurl_qq_1'] ? $userInfo['figureurl_qq_1'] : '';
                $params = array(
                    'open_id' => $openid,
                    'app_type' => 2,
                    'nick_name' => $nickname,
                    'head_img' => $headimgurl,
                    'gender'  => $sex,
                    'country' => $country,
                    'province' => $province,
                    'city' => $city,
                    'county' => '',
                );
                $userData = $this->functionPost('api/user/login/qq', $params);
                if($userData){
                    $userData = $userData['data']['user'];
                    $user_id = $userData['user_id'];
                    $token = $userData['user_token'];
                    $photo_path_small = $userData['head_img'];
                    $nickname = $userData['nick_name'];
                    $return_data = array(
                        'user_id'=>$user_id,
                        'token' => $token,
                        'head_img'=>$photo_path_small,
                        'nickname'=>$nickname
                    );
                    $return_data = json_encode($return_data);
                    $is_check = strpos($redirect_url,'?');
                    if($is_check !==false){
                        $url_param = $redirect_url."&user=".$return_data;
                    }else{
                        $url_param = $redirect_url."?user=".$return_data;
                    }
                    header("Location:$url_param");
                }else{
                    $this->returnError('','获取用户信息失败');
                }
            }
        }
    }

    /*
    * 支付宝登陆
    * */
    public function zfbLogin(){
        $redirect_url = isset($_GET['redirect_url'])?$_GET['redirect_url']:'';
        $is_success = $_REQUEST['is_success'];
        $r_url = $redirect_url;
        if(empty($redirect_url)){
            $this->returnError('','跳转链接不能为空');
        }
        if(!$is_success){
            $redirect_url = json_decode($r_url,true);
            $redirect_url = $redirect_url['url'];
        }else{
            $redirect_url = $this->getThirdLogin($redirect_url,3);
        }
        $return_id = self::insertIntoThirdLogin($redirect_url,3);
        $url =  C('THIRD_LOGIN')."api/youfan_merchant/index.php/wechat/ThirdLogin/zfbLogin?redirect_url={$return_id}";
        $zfb = new ZfbLoginModel();
        $userInfo = $zfb->getUser($url);
        if($userInfo){
            //todo 将用户信息插入数据表
            //迁服务，调用基础服务接口
            $openid = $userInfo['user_id'];
            $nickname = $userInfo['real_name'] ? $userInfo['user_id'] : '';//支付宝只返回名字，连名字都有可能是空
            $sex = '';
            $province =''; //省份
            $city =''; //城市
            $country ='中国'; //qq没有返回国家，默认中国
            $headimgurl = '';
            $params = array(
                'open_id' => $openid,
                'app_type' => 2,
                'nick_name' => $nickname,
                'head_img' => $headimgurl,
                'gender'  => $sex,
                'country' => $country,
                'province' => $province,
                'city' => $city,
                'county' => '',
            );
            $userData = $this->functionPost('api/user/login/zfb', $params);
            if ($userData) {
                $userData = $userData['data']['user'];
                $user_id = $userData['user_id'];
                $token = $userData['user_token'];
                $photo_path_small = $userData['head_img'];
                $nickname = $userData['nick_name'];
                $return_data = array(
                    'user_id'=>$user_id,
                    'token' => $token,
                    'head_img'=>$photo_path_small,
                    'nickname'=>$nickname
                );
                $return_data = json_encode($return_data);
                $is_check = strpos($redirect_url,'?');
                if($is_check !==false){
                    $url_param = $redirect_url."&user=".$return_data;
                }else{
                    $url_param = $redirect_url."?user=".$return_data;
                }
                header("Location:$url_param");
            }else{
                $this->returnError('','获取用户信息失败');
            }
        }
    }

    public function insertIntoThirdLogin($url,$type){
        if(!$url) return false;
        $thirdLoginModel = M('zuban_third_login','','DB_DSN');
        $data = $thirdLoginModel->where("`url`='$url' and `type`=$type ")->find();
        $return_id = 0;
        if($data){
            $return_id = $data['id'];
        }else{
            $add_data = array(
                'url' =>$url,
                'type' =>$type,
                'create_time'=>date('Y-m-d H:i:s'),
            );
            $return_id =  $thirdLoginModel->add($add_data);
        }
        return $return_id;
    }
    public function getThirdLogin($id,$type){
        if(!id) return false;
        $thirdLoginModel = M('zuban_third_login','','DB_DSN');
        $data = $thirdLoginModel->where("`id`='$id' and `type`=$type ")->find();
        if($data){
            return $data['url'];
        }else{
            return false;
        }
    }
}