<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: 当燃 2016-01-09
 */ 
namespace App\Controller;

use App\Model\StoreModel;
use Think\Exception;

class IndexController extends MobileBaseController {
    public function index(){
        $hot_goods = M('goods')->where("is_hot=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页热卖商品
        $thems = M('goods_category')->where('level=1')->order('sort_order')->limit(9)->cache(true,TPSHOP_CACHE_TIME)->select();
        $this->assign('thems',$thems);
        $this->assign('hot_goods',$hot_goods);
        $favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
        $this->assign('favourite_goods',$favourite_goods);

        //region 手机导航
        //$nav_list = M('mobile_nav')->where(array('is_show'=>1))->order('sort asc')/*->limit(8)*/->select();
        $nav_num = M('config')->where(array('name'=>'mobile_nav_num','inc_type'=>'basic'))->getField('value');
        if (empty($nav_num)) $nav_num = 10;
        $nav_list = $this->get_shop_cate($nav_num);
        $this->assign('nav_num',$nav_num);
        $this->assign('nav_list',$nav_list);
        //endregion
        //$this->_get_ad();//广告
        $this->display();
    }

    //获取广告首页
    protected function _get_ad(){
        $map = array();
        $map['pid'] = 2; //位置
        $map['enabled'] = 1; //是否启动
        $ad_list2 = M('ad')->where($map)->order('end_time desc')->limit(5)->select();
        $map['pid'] = 300;
        $ad_list300 = M('ad')->where($map)->order('end_time desc')->limit(5)->select();
        $map['pid'] = 301;
        $ad_list301 = M('ad')->where($map)->order('end_time desc')->limit(5)->select();
        $map['pid'] = 302;
        $ad_list302 = M('ad')->where($map)->order('end_time desc')->limit(5)->select();
        $map['pid'] = 303;
        $ad_list303 = M('ad')->where($map)->order('end_time desc')->limit(5)->select();
        $map['pid'] = 304;
        $ad_list304 = M('ad')->where($map)->order('end_time desc')->limit(5)->select();
        $map['pid'] = 309;
        $ad_list309 = M('ad')->where($map)->order('end_time desc')->limit(5)->select();
        $this->assign('ad_list2',$ad_list2);
        $this->assign('ad_list300',$ad_list300);
        $this->assign('ad_list301',$ad_list301);
        $this->assign('ad_list302',$ad_list302);
        $this->assign('ad_list303',$ad_list303);
        $this->assign('ad_list304',$ad_list304);
        $this->assign('ad_list309',$ad_list309);
    }

    /*获取商铺所有分类列表*/
    protected function _get_shop_cate()
    {
        $order = 'sort asc';//排序值越小越靠前 , 相同时对比ID
        $map = array('is_show'=>1); //1级分类
        $map['level'] = 1; //1级分类
        $shop_cate = M('mobile_nav')->where($map)->order($order)->select(); //获取数据缓存
        return $shop_cate;
    }

    /*获取店铺分类 n个一组形式*/
    public function get_shop_cate($group = 5)
    {
        $shop_cate = $this->_get_shop_cate();
        $shop_cate = get_list_group($shop_cate, $group);
        return $shop_cate;
    }

    /**
     * 分类列表显示
     */
    public function categoryList(){
        $this->display();
    }

    //所有导航 //客户要求的。。。呵
    public function indexall(){
        $order = 'sort asc';//排序值越小越靠前 , 相同时对比ID
        $map = array('is_show'=>1); //1级分类
        $map['level'] = 2; //1级分类
        $nav_list = M('mobile_nav')->where($map)->order($order)->select(); //获取数据缓存
        $this->assign('nav_list',$nav_list);
        $this->display('indexall');
    }

    /**
     * 模板列表
     */
    public function mobanlist(){
        $arr = glob("D:/wamp/www/svn_tpshop/mobile--html/*.html");
        foreach($arr as $key => $val)
        {
            $html = end(explode('/', $val));
            echo "<a href='http://www.php.com/svn_tpshop/mobile--html/{$html}' target='_blank'>{$html}</a> <br/>";            
        }        
    }
    
    /**
     * 商品列表页
     */
    public function goodsList(){
        $goodsLogic = new \Home\Logic\GoodsLogic(); // 前台商品操作逻辑类
        $id = I('get.id',0); // 当前分类id
        $lists = getCatGrandson($id);
        $this->assign('lists',$lists);
        $this->display();
    }
    
    public function ajaxGetMore(){
    	$p = I('p',1);
    	$favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1  and goods_state = 1 ")->order('sort DESC')->page($p,4)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
    	$this->assign('favourite_goods',$favourite_goods);
    	$this->display();
    }

    /**
     * 店铺街
     * @author dyr
     * @time 2016/08/15
     */
    public function street()
    {
        $store_class = M('store_class')->where('')->select();
        $this->assign('title','店铺街');
        $this->assign('store_class', $store_class);//店铺分类
        $this->display();
    }

    /**
     * 批发商店铺街
     * @Author
     */
        public function wholesalerStreet(){
        $store_class = M('store_class')->where('')->select();
        $this->assign('title','批发商');
        $this->assign('wholesaler',true);
        $this->assign('store_class', $store_class);//店铺分类
        $this->display('street');
    }

    /**
     * ajax 获取店铺街
     */
    public function ajaxStreetList()
    {
        $p = I('p',1);
        $sc_id = I('get.sc_id','');

        //经纬度
        $lat_lng = get_lat_lng();
        $lat = $lat_lng['lat'];
        $lng = $lat_lng['lng'];
        if ($lat && $lng){
            $store_list = D('store')->getStreetListLBS($sc_id,$p,10,$lat,$lng);
        }else{
            $store_list = D('store')->getStreetList($sc_id,$p,10);
        }

        foreach($store_list as $key=>$value){
            $store_list[$key]['goods_array'] = D('store')->getStoreGoods($value['store_id'],4);
        }
        $this->assign('store_list',$store_list);
        $this->display();
    }

    //store extends
    //AJAX获取附近店铺
    public function get_shop_list()
    {
        //主页店铺列表 | 分类店铺列表
        $res = $this->_page_list($this->_shop_list(), '_shop_index');
        $this->ajaxReturn($res);
    }

    /**
     * 获取分页数据
     * @param array $res          数据列表
     * @param string $fetch        输出模板
     * @return array        返回数据
     */
    protected function _page_list($res=array('list'=>''), $fetch='')
    {
        if (!empty($res['list'])) {
            $this->assign('list', $res['list']);
            $content = $this->fetch($fetch);
            $res['content'] = $content;
            $res['status'] = 1;
        } else {
            $res = array('status' => 0, 'msg' => '没有更多了');
        }
        return $res;
    }

    //获取附近店铺
    public function _shop_list($where=array(), $_order='')
    {
        $p = I('page',1);
        $wholesaler = I('wholesaler');
        $sc_id = I('sc_id','');

        //经纬度
        $lat_lng = get_lat_lng();
        $lat = $lat_lng['lat'];
        $lng = $lat_lng['lng'];

        //排序
        $order_by = I('order','');
        switch ($order_by){
            case 'store_sales':
                $order_by = ' store_sales desc';
                break;
            case 'score':
                $order_by = ' store_desccredit desc ,store_servicecredit desc ,store_deliverycredit desc';
                break;
            default:
                $order_by = '';
                break;
        }

        //关键字查询
        $keywords = I('keywords','');

        $listRows = 5;

        $store_list = D('store')->getStreetListLBS($sc_id,$p,$listRows,$order_by,$keywords,$lat,$lng,$wholesaler);
        foreach($store_list as $key=>$value){
            $store_list[$key]['goods_array'] = D('store')->getStoreGoods($value['store_id'],4);
        }
        $store_where = array();
        $store_where['role_id'] = array('in',array(0,5));
        if(!empty($sc_id)){
            $store_where['sc_id'] = $sc_id;
        }
        $count = D('store')->where($store_where)->count();
        $page_count = ceil($count / $listRows);   //总页数
        return array('list' => $store_list, 'count' => $count, 'page_count' => $page_count, 'page' => $p + 1 ,'now_page'=>$p);
    }



    /**
     * 品牌街
     * @author dyr
     * @time 2016/08/15
     */
    public function brand()
    {
        $brand_model = M('brand');
        $brand_where['status'] = 0;
        $brand_class = $brand_model->field('cat_name')->group('cat_name')->order(array('sort'=>'asc','id'=>'desc'))->where($brand_where)->select();
        $brand_list = $brand_model->field('id,name,logo,url')->order(array('sort'=>'asc','id'=>'desc'))->where($brand_where)->select();
        $brand_count = count($brand_list);
        for ($i = 0; $i < $brand_count; $i++) {
            if (($i + 1) % 4 == 0) {
                $brand_list[$i]['brandLink'] = 'brandRightLink';
            } else {
                $brand_list[$i]['brandLink'] = 'brandLink';
            }
        }
        $this->assign('brand_list', $brand_list);//品牌列表
        $this->assign('brand_class', $brand_class);//品牌分类
        $this->display();
    }


    /**
     * 公告详情页
     */
    public function notice()
    {
        $notice_class = M('article_cat')->where('1=1')->order('sort_order desc,cat_id asc')->select();
        $this->assign('notice_class', $notice_class);//公告分类
        $this->display();
    }

    /**
     * ajax 获取店铺街
     */
    public function ajaxNoticeList()
    {
        $p = I('p',1);
        $cat_id = I('get.cat_id','');

        $article_list = D('article')->getNoticeList($cat_id,$p,10);

        $this->assign('article_list',$article_list);
        $this->display();
    }

    //公告详情
    public function notice_detail(){
        try{
            $article_id = I('article_id',0);
            if (empty($article_id))
                throw new Exception('内容不存在');
            $article = M('article')->where(array('article_id'=>$article_id))->find();
            if (empty($article))
                throw new Exception('内容不存在');
            $this->assign('notice',$article);
            $this->display();
        }catch(Exception $e){
            $this->error($e->getMessage());
        }
    }

    //上传文件
    public function upload_mobile(){
        //微信浏览器
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            $serverid=I("serverid");
            S('serverid',$serverid);
            $file = $this->get_media($serverid);
            $filename=date("YmdHis").rand(1000,9999).".jpg";
            $dir="./Public/upload/wx/".date("Y").'/'.date('m-d');
            dir_create($dir);

            $path = $dir."/".$filename;
            file_put_contents($path, $file);

            $path = str_replace('./','/',$path);

            $status = M('users')->where(array('user_id'=>$this->_uid))->save(array('head_pic'=>$path));

            $data = array();
            $data['path'] = $path;
            $data['md5'] = md5($path);
            $data['sha1'] = sha1($path);
            $data['create_time'] = time();
            $data['status'] = $status === false ? 0 : 1;
            $this->ajaxReturn($data);
        }else{
            //$_FILES['file'] = I('file');
            $this->ajaxReturn(array('files'=>$_FILES,'img'=>$_FILES['img']));
            $exname=strtolower(substr($_FILES['upfile']['name'],(strrpos($_FILES['upfile']['name'],'.')+1)));
            $uploadfile = get_filename($exname);
            if (move_uploaded_file($_FILES['upfile']['tmp_name'], $uploadfile)) {
                $status = M('users')->where(array('user_id'=>$this->_uid))->save(array('head_pic'=>$exname));
                $this->ajaxReturn(array('status'=>1,'path'=>$exname,'msg'=>'上传成功'));
            }else{
                $this->ajaxReturn(array('status'=>0,'msg'=>'上传失败'));
            }
           /* if($_FILES['file']['name'])
            {
                $upload = new \Think\Upload();// 实例化上传类
                $upload->maxSize   =    $map['author'] = (1024*1024*3);// 设置附件上传大小 管理员10M  否则 3M
                $upload->exts      =    array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
                $upload->rootPath  =    './Public/upload/head_pic/'; // 设置附件上传根目录
                $upload->replace   =    true; // 存在同名文件是否是覆盖，默认为false
                //$upload->saveName  =  'file_'.$id; // 存在同名文件是否是覆盖，默认为false
                // 上传文件
                try{
                    $upinfo  =  $upload->upload();
                    if(!$upinfo) {// 上传错误提示错误信息
                        throw new Exception($upload->getError());
                    }else{
                        $comment_img = array();
                        foreach($upinfo as $key => $val)
                        {
                            $comment_img[] = '/Public/upload/head_pic/'.$val['savepath'].$val['savename'];
                        }
//                $date['head_pic'] = serialize($comment_img); // 上传的图片文件
                        $status = M('users')->where(array('user_id'=>$this->_uid))->save(array('head_pic'=>$comment_img[0]));

                        $res = array();
                        $res['path'] = $comment_img[0];
                        $res['md5'] = md5($comment_img[0]);
                        $res['sha1'] = sha1($comment_img[0]);
                        $res['create_time'] = time();
                        $res['status'] = $status === false ? 0 : 1;
                    }
                }catch(Exception $e){
                    $res = array('status'=>0,'msg'=>$e->getMessage());
                }
                $this->ajaxReturn($res);
            }*/

        }

    }

    //微信上传图片
    public function upload_header(){
        $serverid=I("serverid");
        S('serverid',$serverid);
        //$file = $this->get_media($serverid);   //不知道apicloud的jssdk返回的mead_id为什么不正确  是一个又拍云的临时地址
        $file = file_get_contents($serverid);
        $filename=date("YmdHis").rand(1000,9999).".jpg";
        $dir="./Public/upload/wx/".date("Y").'/'.date('m-d');
        dir_create($dir);

        $path = $dir."/".$filename;
        file_put_contents($path, $file);

        $path = str_replace('./','/',$path);

        $status = M('users')->where(array('user_id'=>$this->_uid))->save(array('head_pic'=>$path));
        if ($status !== false)
            $this->_user['head_pic'] = $path;

        $data = array();
        $data['path'] = $path;
        $data['md5'] = md5($path);
        $data['sha1'] = sha1($path);
        $data['create_time'] = time();
        $data['status'] = $status === false ? 0 : 1;
        $data['uid'] = $this->_uid;
        $this->ajaxReturn($data);
    }

    //拉取微信服务器的图片
    public function get_media($serverid){
        $this->weixin_config = M('wx_user')->find(); //获取微信配置
        $jssdk = new \App\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
        $file=$jssdk->getMedia($serverid);
        if ($file == false){
            $this->ajaxReturn(array('status'=>0,'msg'=>'未上传图片','id'=>$serverid,'file'=>$file));die;
        }
        return $file;
    }


    //获取移动端accessToken
    public function get_app_access_token(){
        $conf = M('wx_user')->field('app_access_token as access_token,app_expires as expires , dynamic_token as dynamicToken ')->find();
        $this->ajaxReturn(array('status'=>1,'data'=>$conf));
    }

    //设置移动端accessToken
    public function set_app_access_token(){
        $accessToken = I('access_token','');
        $expires = time() + intval(I('expires',0));
        $dynamic_token = I('dynamic_token','');
        $conf = M('wx_user')->find();
        $res = M('wx_user')->where(array('id'=>$conf['id']))->setField(array('app_access_token'=>$accessToken,'app_expires'=>$expires,'dynamic_token'=>$dynamic_token));
        $this->ajaxReturn(array('status'=>true,'msg'=>'success'));
    }

    public function get_dynamic_token(){
        $dynamic_token = M('wx_user')->getField('dynamic_token');
        if (!empty($dynamic_token))
            $this->ajaxReturn(array('status'=>1,'msg'=>'获取成功','dynamicToken'=>$dynamic_token));
        else
            $this->ajaxReturn(array('status'=>0,'msg'=>'获取失败'));
    }


    //极光推送
    public function jpush_test(){
        $res = send_jpush_msg(I('reg_id','121c83f76020d51af0d'),I('title','测试'),I('content','测试内容'),I('type','test'));
        dump($res);
    }

    //测试方法
    public function test(){
        /*$list = M('rebate')->where(array('value'=>array('elt',50)))->getField('value',true);
        for($i=0;$i<count($list);$i++)
        {
            $list[$i] = intval($list[$i]);
        }
        $num = range("1","50");
        $num2= array_diff($num,$list);
        dump($num2);
        if (!empty($num2)){
            $data = [];
            foreach ($num2 as $key => $vo){
                $data[]['value'] = $vo;
            }
            $res = M('rebate')->addAll($data);
            dump($data);
            dump(M()->getLastSql());
            dump($res);die;
        }*/
    }


}