<?php
//项目应用参数配置
return array(
    //短信配置
    /*'sms_config'=>array(
            'url'=>'http://120.24.167.205/msg/HttpSendSM',//接口地址
            'username'=>'cqyouqu_zjdh',//用户名
            'password'=>'Zjdh123456',//密码
            'content_str'=>'【友趣科技】',//内容提示语
            'time'=>'600000',//验证码失效时间（秒）
    ),*/
    //短信配置
    'sms_config'=>array(
        'url'           =>  'http://www.qybor.com:8500/shortMessage',
        'username'      =>  'junhong123',//用户名
        'password'      =>  'junhong123',//密码
        'content_str'   =>  '【钜宏日化】',//内容提示语
        'time'          =>  '600000',//验证码失效时间（秒）
    ),
);