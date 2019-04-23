<?php
/**
 * Created by PhpStorm.
 * User: yq
 * Date: 2017/8/10
 * Time: 14:38
 */

namespace Admin\Controller;


class MobileNavController extends AdminController
{
    /**
     * 自定义导航
     */
    public function navigationList(){
        $model = M("mobile_nav");
        $navigationList = $model->order("sort asc")->select();
        $this->assign('navigationList',$navigationList);
        $this->display('navigationList');
    }

    /**
     * 添加修改编辑 前台导航
     */
    public  function addEditNav(){
        $model = M("mobile_nav");
        if(IS_POST)
        {
            $model->create();
            // $model->url = strstr($model->url, 'http') ? $model->url : "http://".$model->url; // 前面自动加上 http://
            if($_GET['id'])
                $model->save();
            else
                $model->add();

            $this->success("操作成功!!!",U('navigationList'));
            exit;
        }
        // 点击过来编辑时
        $id = $_GET['id'] ? $_GET['id'] : 0;
        $navigation = $model->find($id);

        $this->assign('navigation',$navigation);
        $this->display('_navigation');
    }

    /**
     * 删除前台 自定义 导航
     */
    public function delNav()
    {
        // 删除导航
        M('mobile_nav')->where("id = {$_GET['id']}")->delete();
        $this->success("操作成功!!!",U('navigationList'));
    }

}