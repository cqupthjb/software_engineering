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
namespace Mobile\Controller;

use Home\Controller\UeditorController;
use Mobile\Model\StoreModel;
use Think\Exception;

class IndexController extends MobileBaseController {

    public function index(){                
        /*
            //获取微信配置
            $wechat_list = M('wx_user')->select();
            $wechat_config = $wechat_list[0];
            $this->weixin_config = $wechat_config;        
            // 微信Jssdk 操作类 用分享朋友圈 JS            
            $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
            $signPackage = $jssdk->GetSignPackage();              
            print_r($signPackage);
        */

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

        $this->display();
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
    	$favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1  and goods_state = 1 ")->order('sort DESC')->page($p,10)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
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


    public function store_map(){
        $lat_lng = get_lat_lng();
        $lat = $lat_lng['lat'];
        $lng = $lat_lng['lng'];
        $this->assign("lat",$lat);
        $this->assign("lng",$lng);
        $this->display();
    }

    public function get_map_shop(){
        $lat = I("lat");
        $lng = I("lng");
        $p = I('p',1);
        $sc_id = I('get.sc_id','');
        $store_list = D('store')->getStreetListLBS($sc_id,$p,10,$lat,$lng);
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
            $data['status'] = 1;
            $data['status'] = $status === false ? 0 : 1;
            $this->ajaxReturn($data);
        }else{
            $path = I('path','');
            $info = array(
                'num'=> I('num'),
                'title' => '',
                'upload' =>U('Admin/Ueditor/imageUp',array('savepath'=>$path,'pictitle'=>'header','dir'=>'head_pic')),
                'size' => '4M',
                'type' =>'jpg,png,gif,jpeg',
            );
            $this->ajaxReturn($info);
        }

    }

    //拉取微信服务器的图片
    public function get_media($serverid){
        $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
        $file=$jssdk->getMedia($serverid);
        if ($file == false){
            $this->ajaxReturn(array('status'=>0,'msg'=>'未上传图片'));die;
        }
        return $file;
    }

}