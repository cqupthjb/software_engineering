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
 * $Author: IT宇宙人 2015-08-10 $
 */ 
namespace Api\Controller;

class IndexController extends BaseController {

    /*public function index(){
        $contr = $this->contr;
        switch (strtolower($contr)){
            case 'index':$contr = $this;break;
            case 'user' :$contr = new UserController();break;
            case 'store':$contr = new StoreController();break;
            case 'cart' :$contr = new CartController();break;
            case 'goods':$contr = new GoodsController();break;
            default:
                $this->returnCode(-1,$this->contr.'类不存在');
                break;
        }
        //判断类是否存在
        // 判断方法是否存在
        if (!method_exists($contr,$this->method))
            $this->returnCode(0,$this->method.'方法不存在');
        if ($this->contr == 'IndexController' && $this->method == 'index'){
            $this->method = 'home';
        }
        $func = $this->method;
        $contr->$func();
    }*/
    public function index(){
        $this->home();
    }

     /*
     * 获取首页数据
     */
    public function home(){
        //获取轮播图
        $ad = M('ad')->where('pid = 2')->field(array('ad_link','ad_name','ad_code'))->cache(true,TPSHOP_CACHE_TIME)->select();
        //广告地址转换
        foreach($ad as $k=>$v){
            if(!strstr($v['ad_link'],'http'))
                $ad[$k]['ad_link'] = SITE_URL.$v['ad_link'];
                $ad[$k]['ad_code'] = SITE_URL.$v['ad_code'];
        }
        //获取大分类
//        $category_arr = M('goods_category')->where('id in(4,5,7)')->field('id,name')->limit(3)->cache(true,TPSHOP_CACHE_TIME)->select();
        //获取商品
        //$promotion_goods = D('Goods')->getPromotionGoods();
        $high_quality_goods = D('Goods')->getHighQualityGoods();
        $new_goods = D('Goods')->getNewGoods();
        $hot_goods = D('Goods')->getHotGood();
        $goods = array(
            //array('name'=>'促销商品','goods_list'=>$promotion_goods),
            array('name'=>'精品推荐','goods_list'=>$high_quality_goods),
            array('name'=>'新品上市','goods_list'=>$new_goods),
            array('name'=>'热销商品','goods_list'=>$hot_goods),
        );
       //首页导航 $this->_get_nav();
        $this->ajaxReturn(array('status'=>1,'msg'=>'获取成功','result'=>array('goods'=>$goods,'ad'=>$ad,'nav'=>$this->_get_nav())));

    }
    //获取首页导航
    protected function _get_nav(){
        $nav_num = M('config')->where(array('name'=>'mobile_nav_num','inc_type'=>'basic'))->getField('value');
        if (empty($nav_num)) $nav_num = 10;
        $index = new \Mobile\Controller\IndexController();
        $nav_list = $index->get_shop_cate($nav_num);
        foreach ($nav_list as $key => $vo){
            foreach ($vo as $k => $v){
                $nav_list[$key][$k]['icon'] = SITE_URL.$v['icon'];
                if (stripos($v['url'], 'http') === false){
                    if ( $v['url']!='javascript:void(0)' && $v['url']!='javascript:void(0);' && $v['url']!='#')
                    $nav_list[$key][$k]['url'] = SITE_URL.$v['url'];
                }
            }
        }
        return $nav_list;
    }


    /**
     * 获取首页数据
     */
    public function homePage(){
        $promotion_goods = D('Goods')->getPromotionGoods();
        $high_quality_goods = D('Goods')->getHighQualityGoods();
        $new_goods = D('Goods')->getNewGoods();
        $hot_goods = D('Goods')->getHotGood();
        $adv =  D('Goods')->getHomeAdv();
        $json = array(
            'status'=>1,
            'msg'=>'获取成功',
            'result'=>array(
                'promotion_goods'=>$promotion_goods,
                'high_quality_goods'=>$high_quality_goods,
                'new_goods'=>$new_goods,
                'hot_goods'=>$hot_goods,
                'ad'=>$adv
            ),
        );
       $this->ajaxReturn($json);
    }

    /**
     * 猜你喜欢
     */
    public function favourite()
    {
        $p = I('p',1);
        $goods_where = array('is_recommend'=>1,'is_on_sale'=>1,'goods_state'=>1);
        $favourite_goods = M('goods')
            ->field('goods_id,goods_name,shop_price')
            ->where($goods_where)
            ->order('sort DESC')
            ->page($p,10)
            ->cache(true,TPSHOP_CACHE_TIME)
            ->select();
        $json = array(
            'status'=>1,
            'msg'=>'获取成功',
            'result'=>array(
                'favourite_goods'=>$favourite_goods,
            ),
        );
        $this->ajaxReturn($json);
    }

    /**
     * 获取服务器配置
     */
    public function getConfig()
    {
        $config_arr = M('config')->select();
        $this->ajaxReturn(array('status'=>1,'msg'=>'获取成功','result'=>$config_arr));
    }
    /**
     * 获取插件信息
     */
    public function getPluginConfig()
    {
        $data = M('plugin')->where("type='payment' OR type='login'")->select();
        $arr = array();
        foreach($data as $k=>$v){
            unset( $data[$k]['config']);
        
			if(!$v['config_value']){
				$data[$k]['config_value'] = "";
			}else{
				$data[$k]['config_value'] = unserialize($v['config_value']);
			}
			
            if($data[$k]['type'] == 'payment'){
                $arr['payment'][] =  $data[$k];
            }
            if($data[$k]['type'] == 'login'){
                $arr['login'][] =  $data[$k];
            }
        }
        $this->ajaxReturn(array('status'=>1,'msg'=>'获取成功','result'=>$arr ? $arr : ''));
    }

    /**
     * 店铺街
     * @author dyr
     * @time 2016/08/15
     */
    public function storeStreet(){

        $index = new \Mobile\Controller\IndexController();
        $store_list = $index->_shop_list();

        if (!empty($store_list['list'])){
            foreach($store_list['list'] as $key=>$value){
                $store_list['list'][$key]['store_logo'] = SITE_URL.$value['store_logo'];
                //商品
                $goods = D('store')->getStoreGoods($value['store_id'],4);
                foreach ($goods as $k => $v){
                    $goods[$k]['original_img'] = SITE_URL.$v['original_img'];
                }
                $store_list['list'][$key]['goods_array'] = $goods;
            }
        }

        $result = array('store_list' => $store_list, 'store_class' =>$this->_store_class());
        $this->ajaxReturn(array('status'=>1, 'msg' => 'success', 'result' => $result));
    }
/*    public function storeStreet()
    {
        $sc_id = I('get.sc_id', '');
        $store_class = M('store_class')->where('')->select();//店铺分类
        $p = I('p',1);
        $store_list = D('store')->getStreetList($sc_id,$p,10);//获取店铺列表
        //遍历获取店铺的四个商品数据
        foreach ($store_list as $key => $value) {
            $goodsList = D('store')->getStoreGoods($value['store_id'], 4);
            $store_list[$key]['cartList'] = $goodsList['goods_list'];
            $store_list[$key]['store_count'] = $goodsList['goods_count'];
        }
        $result = array('store_list' => $store_list, 'store_class' => $store_class);
        $this->ajaxReturn(array('status' => 1, 'msg' => 'success', 'result' => $result));
    }*/

    /**
     * 店铺分类
     */
    protected function _store_class(){
        return M('store_class')->where('')->select();
    }
    public function storeClass()
    {
        $store_class = $this->_store_class();
        $this->ajaxReturn(array('status'=>1, 'msg' => 'success', 'result' => $store_class));
    }

    /**
     * 品牌街
     * @author dyr
     * @time 2016/08/15
     */
    public function brandStreet()
    {
        $brand_model = M('brand');
        $brand_where['status'] = 0;
        //品牌分类
        $brand_list = $brand_model->field('id,name,logo,url')->order(array('sort' => 'desc', 'id' => 'asc'))->where($brand_where)->limit("1, 20")->select();
        foreach ($brand_list as $key => $vo){
            if (!empty($vo['logo']))
                $brand_list[$key]['logo'] = SITE_URL.$vo['logo'];
        }
        $this->ajaxReturn(array('status'=>1, 'msg' => 'success', 'result' => $brand_list));
    }

}