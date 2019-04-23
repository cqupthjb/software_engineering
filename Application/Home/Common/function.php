<?php

/**
 * 检查密码格式
 * @param $password 密码只允许包含数字、字母组成的6到16位字符
 * wj
 * 2016-11-16
 */
if (!function_exists('is_password')){
    function is_password($password){
        if (preg_match('/^[0-9a-zA-Z]{6,16}$/i',$password)){
            return true;
        }else {
            return false;
        }
    }
}
/**
 * 面包屑导航  用于前台用户中心
 * 根据当前的控制器名称 和 action 方法
 */
function navigate_user()
{    
    $navigate = include APP_PATH.'Common/Conf/navigate.php';    
    $location = strtolower('Home/'.CONTROLLER_NAME);
    $arr = array(
        '首页'=>'/',
        $navigate[$location]['name']=>U('/Home/'.CONTROLLER_NAME),
        $navigate[$location]['action'][ACTION_NAME]=>'javascript:void();',
    );
    return $arr;
}

/**
*  面包屑导航  用于前台商品
 * @param type $id 商品id  或者是 商品分类id
 * @param type $type 默认0是传递商品分类id  id 也可以传递 商品id type则为1
 */
function navigate_goods($id,$type = 0)
{
    $cat_id = $id; //
    // 如果传递过来的是
    if($type == 1){
        $cat_id = M('goods')->where("goods_id = $id")->getField('cat_id3');
    }
    $categoryList = M('GoodsCategory')->getField("id,name,parent_id");

    // 第一个先装起来
    $arr[$cat_id] = $categoryList[$cat_id]['name'];
    while (true)
    {
        $cat_id = $categoryList[$cat_id]['parent_id'];
        if($cat_id > 0)
            $arr[$cat_id] = $categoryList[$cat_id]['name'];
        else
            break;
    }
    $arr = array_reverse($arr,true);
    return $arr;
}
