<?php
/**
 * Created by PhpStorm.
 * User: yq
 * Date: 2017/9/12 0012
 * Time: 14:51
 */

namespace Api\Controller;


class TestController extends ApiController
{
    /**
     * 接口入口
     */
    public function index()
    {
        if ($this->contr == 'TestController'){
            $contr = $this;
        }else{
            $contr = new $this->contr();
        }
        $func = $this->method;
        $contr->$func();
    }

    /**
     * APP安装统计
     */
    public function installCount()
    {
        $Installcount = D('Installcount');
        $status = $Installcount->installcount($this->client_plat,$this->client_version,$this->client_sign);
        if($status){
            $this->returnCode(1,'安装统计成功');
        }else{
            $this->returnCode(0,'安装统计失败');
        }
    }

}