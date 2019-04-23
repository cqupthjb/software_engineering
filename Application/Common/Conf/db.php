<?php
/* 数据库配置 */
// $local =  array(
//     'DB_TYPE' => 'mysql', //数据库类型
// //    'DB_HOST' => '127.0.0.1', //数据库主机
//     'DB_HOST' => '192.168.10.111', //数据库主机
// //    'DB_NAME' => '588shop', //数据库名称
//     'DB_NAME' => 'jhjt', //数据库名称
// //    'DB_USER' => 'root', //数据库用户名
//     'DB_USER' => 'juhong', //数据库用户名
//     'DB_PWD' => '8cdb0b2f576', //数据库密码
//     //'DB_PWD' => 'Jh-2017', //数据库密码
//     'DB_PORT' => '3306', //数据库端口
//     'DB_PREFIX' => 'tp_', //数据库前缀
//     'DB_CHARSET'=> 'utf8', // 字符集
//     'DB_DEBUG'  => '', // 数据库调试模式 开启后可以记录SQL日志
// );

$local =  array(
    'DB_TYPE' => 'mysql', //数据库类型
    // 'DB_HOST' => '127.0.0.1', //数据库主机
   'DB_HOST' => 'cq1080.com', //数据库主机
//    'DB_NAME' => '588shop', //数据库名称
    'DB_NAME' => 'jhjt', //数据库名称
    // 'DB_USER' => 'root', //数据库用户名
   'DB_USER' => 'juhong', //数据库用户名
    'DB_PWD' => '8cdb0b2f576', //数据库密码
    //'DB_PWD' => 'Jh-2017', //数据库密码
    'DB_PORT' => '3307', //数据库端口
    'DB_PREFIX' => 'tp_', //数据库前缀
    'DB_CHARSET'=> 'utf8', // 字符集
    'DB_DEBUG'  => '', // 数据库调试模式 开启后可以记录SQL日志
);
return $local;

php?>