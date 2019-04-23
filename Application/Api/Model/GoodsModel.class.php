<?php
/**
 * tpshop
 * 商品模型
 * @auther：dyr
 */
namespace Api\Model;

use Think\Model;


class GoodsModel extends Model
{
    private $model;
    private $db_prefix;
    public function _initialize()
    {
        $this->db_prefix = C('DB_PREFIX');
        $this->model = M('');
        parent::_initialize();
    }

    /**
     * 获取促销商品数据
     * @return mixed
     */
    public function getPromotionGoods()
    {
        $goods_where = array('g.goods_state' => 1, 'g.is_on_sale' => 1 );
        $promotion_goods = $this->model
            ->field('g.goods_id,g.goods_name,f.price AS shop_price,f.end_time')
            ->table($this->db_prefix . 'goods g')
            ->join('INNER JOIN ' . $this->db_prefix . 'flash_sale AS f ON g.goods_id = f.goods_id')
            ->where($goods_where)
            ->limit(3)
            ->select();
        return $promotion_goods;
    }

    /**
     * 获取精品商品数据
     * @return mixed
     */
    public function getHighQualityGoods()
    {
        $goods_where = array('is_recommend' => 1, 'goods_state' => 1,  );/*'is_on_sale' => 1,*//*'cat_id1'=>5*/
        $orderBy = array('sort' => 'desc');
        $promotion_goods = M('goods')
            ->field('goods_id,goods_name,shop_price,original_img')
            ->where($goods_where)
            ->order($orderBy)
            ->limit(9)
            ->select();
        foreach ($promotion_goods as $key => $vo){
            $promotion_goods[$key]['original_img'] = SITE_URL.$vo['original_img'];
        }
        return $promotion_goods;
    }

    /**
     * 获取新品商品数据
     * @return mixed
     */
    public function getNewGoods()
    {
        $goods_where = array('is_new' => 1, 'goods_state' => 1,);/* 'is_on_sale' => 1, 'cat_id1'=>6*/
        $orderBy = array('sort' => 'desc');
        $new_goods = M('goods')
            ->field('goods_id,goods_name,shop_price,original_img')
            ->where($goods_where)
            ->order($orderBy)
            ->limit(9)
            ->select();
        foreach ($new_goods as $key => $vo){
            $new_goods[$key]['original_img'] = SITE_URL.$vo['original_img'];
        }
        return $new_goods;
    }

    /**
     * 获取热销商品数据
     * @return mixed
     */
    public function getHotGood()
    {
        $goods_where = array('is_hot' => 1, 'goods_state' => 1, );/*'is_on_sale' => 1, 'cat_id1'=>4*/
        $orderBy = array('sort' => 'desc');
        $new_goods = M('goods')
            ->field('goods_id,goods_name,shop_price,original_img')
            ->where($goods_where)
            ->order($orderBy)
            ->limit(9)
            ->select();
        foreach ($new_goods as $key => $vo){
            $new_goods[$key]['original_img'] = SITE_URL.$vo['original_img'];
        }
        return $new_goods;
    }

    /**
     * 获取首页轮播图片
     * @return mixed
     */
    public function getHomeAdv()
    {
        $adv = M('ad')->where('pid = 2')->field(array('ad_link','ad_name','ad_code'))->cache(true,TPSHOP_CACHE_TIME)->select();
        //广告地址转换
        foreach($adv as $k=>$v){
            if(!strstr($v['ad_link'],'http')){
                $adv[$k]['ad_link'] = SITE_URL.$v['ad_link'];
            }
            $adv[$k]['ad_code'] = SITE_URL.$v['ad_code'];
        }
        return $adv;
    }
}