<?php

if (!function_exists('get_full_link')){
    /**
     * 获取构造完整链接
     * @param array &$data
     * @param string|array $column
     */
    function get_full_link(&$data=[],$column=''){
        if (!empty($data) && is_array($data)){
            foreach ($data as $key => &$vo){
                if (is_array($vo)){  //二维数组情况
                    if (is_array($column)){
                        foreach ($column as $k => $v){
                            if (!empty($vo[$v]) && stripos($vo[$v], 'http') === false && $vo[$v]!='javascript:void(0)' && $vo[$v]!='javascript:void(0);' && $vo[$v]!='#'){
                                $vo[$v] = SITE_URL.$vo[$v];
                            }
                        }
                    }else{
                        if (!empty($vo[$column]) && stripos($vo[$column], 'http') === false && $vo[$column]!='javascript:void(0)' && $vo[$column]!='javascript:void(0);' && $vo[$column]!='#'){
                            $vo[$column] = SITE_URL.$vo[$column];
                        }
                    }
                }else{ //一维数组情况
                    if (is_array($column)){
                        foreach ($column as $k => $v){
                            if (!empty($data[$v]) && stripos($data[$v], 'http') === false && $data[$v]!='javascript:void(0)' && $data[$v]!='javascript:void(0);' && $data[$v]!='#'){
                                $data[$v] = SITE_URL.$data[$v];
                            }
                        }
                    }else{
                        if (!empty($data[$column]) && stripos($data[$column], 'http') === false && $data[$column]!='javascript:void(0)' && $data[$column]!='javascript:void(0);' && $data[$column]!='#'){
                            $data[$column] = SITE_URL.$data[$column];
                        }
                    }
                }
            }
        }else{ //字符串情况
            if (!empty($data)&& stripos($data, 'http') === false && $data!='javascript:void(0)' && $data!='javascript:void(0);' && $data!='#'){
                if (!is_array($column)) {
                    $data = SITE_URL.$data;
                }
            }
        }
    }
}

if (!function_exists('get_filename')){
    function get_filename($exname){
        $dir = ".Public/upload/app/";
        $i=1;
        if(!is_dir($dir)){
            mkdir($dir,0777);
        }
        while(true){
            if(!is_file($dir.$i.".".$exname)){
                $name=$i.".".$exname;
                break;
            }
            $i++;
        }
        return $dir.$name;
    }
}

if (!function_exists('get_thumb_images')){
    /**
     * 获取缩略图 | 生成缩略图
     * @param string $model     模型
     * @param string $primary   主键名
     * @param int $id           主键值
     * @param string $img       图片字段名
     * @param int $width        宽
     * @param int $height       高
     * @return mixed|string     返回缩略图路径
     */
    function get_thumb_images($model = '',$primary = '',$id = 0, $img = '', $width = 400, $height = 400)
    {
        if (empty($id)) return '';
        //判断缩略图是否存在
        $path = "Public/upload/$model/thumb/$id/";
        $thumb_name = "{$model}_thumb_{$id}_{$width}_{$height}";

        // 这个商品 已经生成过这个比例的图片就直接返回了
        if (file_exists($path . $thumb_name . '.jpg')) return '/' . $path . $thumb_name . '.jpg';
        if (file_exists($path . $thumb_name . '.jpeg')) return '/' . $path . $thumb_name . '.jpeg';
        if (file_exists($path . $thumb_name . '.gif')) return '/' . $path . $thumb_name . '.gif';
        if (file_exists($path . $thumb_name . '.png')) return '/' . $path . $thumb_name . '.png';

        $original_img = M($model)->where("$primary = $id")->getField($img);
        if (empty($original_img)) return '';

        $original_img = '.' . $original_img; // 相对路径
        if (!file_exists($original_img)) return '';

        try {
            $image = new \Think\Image();
            $image->open($original_img);
            $thumb_name = $thumb_name . '.' . $image->type();
            // 生成缩略图
            if (!is_dir($path)) mkdir($path, 0777, true);
            // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
            $image->thumb($width, $height, 2)->save($path . $thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
            //图片水印处理
            $water = tpCache('water');
            if ($water['is_mark'] == 1) {
                $imgresource = './' . $path . $thumb_name;
                if ($width > $water['mark_width'] && $height > $water['mark_height']) {
                    if ($water['mark_type'] == 'img') {
                        $image->open($imgresource)->water("." . $water['mark_img'], $water['sel'], $water['mark_degree'])->save($imgresource);
                    } else {
                        //检查字体文件是否存在,注意是否有字体文件
                        if (file_exists('./zhjt.ttf')) {
                            $image->open($imgresource)->text($water['mark_txt'], './zhjt.ttf', 20, '#000000', $water['sel'])->save($imgresource);
                        }
                    }
                }
            }
            return '/' . $path . $thumb_name;
        } catch (Think\Exception $e) {
            return $original_img;
        }
    }
}


//region 极光推送
//Appkey
if (!function_exists('get_jpush_appkey')){
    function get_jpush_appkey(){
        return '3e26b270cccd786772989447';
    }
}
//master_secret
if (!function_exists('get_jpush_master_secret')){
    function get_jpush_master_secret(){
        return '85b3bea59d6d3a39bb6bb938';
    }
}
//send_jpush_msg_by_regid
if (!function_exists('send_jpush_msg')){
    /**
     * 极光推送 By Reg_id
     * @param string $reg_id    设备Reg_id
     * @param string $title     消息标题
     * @param string $content   消息内容
     * @param string $type      消息类型 -> 前端可做判断操作
     * @return bool
     */
    function send_jpush_msg($reg_id = '',$title='', $content = '',$type='order'){
        if (empty($reg_id) || empty($content))
            return false;
        $jpush = new \Common\Controller\JpushController();
        return $jpush->jpush_to_regid($reg_id,$title,$content,$type);
    }
}
//endregion