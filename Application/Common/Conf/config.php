<?php
return array(
    'RUN_ENV' =>  0, //0开发 1测试 2正式  //此处用的测试环境配置  后续改正
    'SERVER_URL'            =>  "http://www.zuban8.com/zuban_server", //当前服务地址
    /**

    数据库

    */
    'DB_PREFIX'         =>  '',
    'URL_MODEL'         =>  3, // 如果你的环境不支持PATHINFO 请设置为3
    'DB_DSN'            =>  'mysqli://hdm157793421:gemei123456@hdm157793421.my3w.com:3306/hdm157793421_db',


    /**

    日志

    */
    'LOG_RECORD'            =>  true,  // 进行日志记录
    'LOG_EXCEPTION_RECORD'  =>  true,    // 是否记录异常信息日志
    'LOG_LEVEL'             =>  'EMERG,ALERT,CRIT,ERR,WARN,NOTIC,INFO,DEBUG,SQL', // 允许记录的日志级别
    'DB_SQL_LOG'            =>  true, // 记录SQL信息


    /**

    常规

    */
    'TMPL_CACHE_ON'         =>  false,        // 是否开启模板编译缓存,设为false则每次都会重新编译
    'TMPL_STRIP_SPACE'      =>  false,       // 是否去除模板文件里面的html空格与换行
    'SHOW_ERROR_MSG'        =>  true,    // 显示错误信息
    'URL_CASE_INSENSITIVE'  =>  false,  // URL区分大小写
    'DB_FIELDS_CACHE' => false, //字段缓存


    /**

    返回值定义

    */
    'AJAX_STATUS_SUCCESS' => 1, //ajax返还成功
    'AJAX_STATUS_ERROR' => -1,  //ajax返还错误状态

    'REQUEST_ERROR' => array('code'=> -1000, 'msg' => "非法请求！"), //非法请求
    'PARAM_ERROR' => array('code'=> -100, 'msg' => "参数错误！"), //参数错误！

    /**

    自定义

    */
    'REQUEST_OUT_TIME' => 3, //请求超时记录时长  S
    'POST' => 'POST',
    'GET' => 'GET',
    'SMS' => 'P_SMS',

    //权重分母
    'DENO' => 1000,
    'MAX_REGION_CODE' => '1',
    'MAX_BOSS_CODE' => 'BOSS',

    'ARRAY_IP' => array(),


    'IOS_APP_XINGE' => array('ai' => 2200248939, 'sk' => "d9c04b1ae567681952e851e26f99b92a"),//IOS APP
    'ANDROID_APP_XINGE' => array('ai' => 2100248837, 'sk' => "99adc0b69a3e059e15b4247404a55ec2"),  //安卓 测试demo

    'CODE_ERROR' => -100, //参数错误
    'CODE_INFO_ERROR' => -110, //内容长度不正确
    'CODE_MB_ERROR' => -1000, //美邦旧服务器响应失败
    'CODE_LOGIN_ERROR' => -103, //查无此用户
    'CODE_VERIFY_ERROR' => -102, //验证码验证错误
    'CODE_SESSION_ERROR' => -103, //session错误
    'WXTOKEN'=>'zuban666',//微信公众号token

    /*************第三方登陆******************/
    'THIRD_LOGIN' => 'http://test.guleshop.com/youfan/api',
    //微信appid,appsecret
    'APPID_WX' => 'wx3c5e318a8146f352',
    'APPSECRET_WX' =>'f8f83a2db7fa19ba7335e41aef642749',

  /*  //微信appid,appsecret
    'APPID_WX' => 'wxc58bff0ef94a2ecc',
    'APPSECRET_WX' =>'2e3f7e6642efb15eeb85c52b282a4c17',*/


    'URL_MODEL'             =>  1,       // URL访问模式,可选参数0、1、2、3,代表以下四种模式：// 0 (普通模式); 1 (PATHINFO 模式); 2 (REWRITE  模式); 3 (兼容模式)  默认为PATHINFO 模式
    'URL_ROUTER_ON'         =>  true,   // 是否开启URL路由
    'URL_ROUTE_RULES'       =>  array(  // 默认路由规则 针对模块
        'notify'=>'Zb/Order/notify',//回调通知地址
        'prePay'=>'Zb/Order/prePay',//获取预支付订单
        'wx'=>'Wx/check',//微信验证
        'Wechat/ThirdLogin/wxLogin'=>'Wechat/ThirdLogin/wxLogin'

    ),

    'VIPLIST'             =>  'VIP_LIST',

    /*
    * 微信支付配置
    *   * APPID：绑定支付的APPID
    *
    * MCHID：商户号
    *
    * KEY：商户支付密钥
    *
    * APPSECRET：公众帐号secert（仅JSAPI支付的时候需要配置， 登录公众平台，进入开发者中心可设置），
        * */

    'PAY_CONFIG_WX' => array(
        'APPID' => 'wx3c5e318a8146f352',//租伴
        'MCHID' => '1426145902',//租伴
        'KEY' => '6034e609io23f86507eg79887tb0519e',
        'APP_KEY' => '',
        'APPSECRET' => 'f8f83a2db7fa19ba7335e41aef642749',
        'NOTIFY_URL' => 'http://test.guleshop.com/youfan/api/index.php/notify/channel/wx',
        'SSLCERT_PATH' => APP_PATH.'Pay/Wx/cert/apiclient_cert.pem',
        'SSLKEY_PATH' => APP_PATH.'Pay/Wx/cert/apiclient_key.pem',
        'PRE_PAY_URL' => 'https://api.mch.weixin.qq.com/pay/unifiedorder',
        'QUERY_URL' => 'https://api.mch.weixin.qq.com/pay/orderquery',
    ),
    /*
     * *支付宝支付配置
        *  PARTNER  合作者id
        *  PRIVATE_KEY_PATH 商户私钥
        *  PUBLIC_KEY_PATH 公钥
     * */
    'PAY_CONFIG_ALI' => array(
        'PARTNER' => '2088501838178029',
        'PRIVATE_KEY_PATH' => APP_PATH.'Pay/AliPay/cert/rsa_private_key.pem',
        'PUBLIC_KEY_PATH' => APP_PATH.'Pay/AliPay/cert/alipay_public_key.pem',
        'HTTPS_VERIFY_URL' => 'https://mapi.alipay.com/gateway.do?service=notify_verify&',
        'TRANSPROT' => '',
        'HTTP_VERIFY_URL' => 'http://notify.alipay.com/trade/notify_query.do?',
        'CACERT' => APP_PATH.'Lib/Pay/AliPay/cert/cacert.pem',
    ),

);
