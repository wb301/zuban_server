<?php
return array(
    'RUN_ENV' =>  0, //0开发 1测试 2正式  //此处用的测试环境配置  后续改正
    'SERVER_URL'            =>  "http://weixin.zuban8.com/zuban_server", //当前服务地址
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
    'DB_FIELDS_CACHE' => true, //字段缓存


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

    'ARRAY_IP' => array(),


    'IOS_APP_XINGE' => array('ai' => 2200248939, 'sk' => "d9c04b1ae567681952e851e26f99b92a"),//IOS APP
    'ANDROID_APP_XINGE' => array('ai' => 2100248837, 'sk' => "99adc0b69a3e059e15b4247404a55ec2"),  //安卓 测试demo
);
