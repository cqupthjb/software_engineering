<?php
/**
 * tpshop
 * 回复模型
 * @auther：dyr
 */ 
namespace App\Model;
use Think\Model;


class StoreModel extends Model{
    /**
     * 店铺街
     * @author dyr
     * @param $sc_id 店铺分类ID，可不传，不传将检索所有分类
     * @param int $p 分页
     * @param int $item 每页多少条记录
     * @param string $order_by 排序值
     * @return mixed
     */
    public function getStreetList($sc_id,$p=1,$item=10,$order_by='')
    {
        $model = M('');;
        $store_where = array();
        $store_where['s.role_id'] = array('in',array(0,5));
        $db_prefix = C('DB_PREFIX');
        if(!empty($sc_id)){
            $store_where['s.sc_id'] = $sc_id;
        }
        $store_list = $model
            ->table($db_prefix .'store s')
            ->field('s.store_id,s.store_phone,s.store_logo,s.store_name,s.store_desccredit,s.store_servicecredit,
						s.store_deliverycredit,r1.name as province_name,r2.name as city_name,r3.name as district_name,
						s.deleted as goods_array')
            ->join('LEFT JOIN '.$db_prefix . 'region As r1 ON r1.id = s.province_id')
            ->join('LEFT JOIN '.$db_prefix . 'region As r2 ON r2.id = s.city_id')
            ->join('LEFT JOIN '.$db_prefix . 'region As r3 ON r3.id = s.district')
            ->where($store_where)
            ->page($p,$item)
            ->cache(true,TPSHOP_CACHE_TIME)
            ->select();
        return $store_list;
    }

    /**
     * 店铺街   经纬度排序
     * @author dyr
     * @param $sc_id 店铺分类ID，可不传，不传将检索所有分类
     * @param int $p 分页
     * @param int $item 每页多少条记录
     * @param string $order_by 排序值
     * @param string $keywords 关键字
     * @param int $lat 纬度
     * @param int $lng 经度
     * @return mixed
     */
    public function getStreetListLBS($sc_id,$p=1,$item=10,$order_by='',$keywords='',$lat=0,$lng=0,$whoresaler=null)
    {
        $model = M('');
        $store_where = array();
        $store_where['s.role_id'] = array('in',array(0,5));
        $db_prefix = C('DB_PREFIX');
        if(!empty($sc_id)){
            $store_where['s.sc_id'] = $sc_id;
        }
        if (!empty($keywords)){
            $store_where['s.store_name'] = array('like',"%$keywords%");
        }
        if ($whoresaler === '1'){
            $store_where['s.is_wholesaler'] = 1;
        } else {
            $store_where['s.is_wholesaler'] = 0;
        }
        //排序
        $order = " (ABS(s.store_lng - '{$lng}') +  ABS(s.store_lat - '{$lat}') ) asc ";
        if (!empty($order_by)){
            $order = $order_by . ',' .$order;
        }

        $store_list = $model
            ->table($db_prefix .'store s')
            ->field('s.store_id,s.store_phone,s.store_logo,s.store_name,s.store_desccredit,s.store_servicecredit,
                        s.store_lat,s.store_lng,s.store_address,s.description,
						s.store_deliverycredit,r1.name as province_name,r2.name as city_name,r3.name as district_name,
						s.deleted as goods_array')
            ->join('LEFT JOIN '.$db_prefix . 'region As r1 ON r1.id = s.province_id')
            ->join('LEFT JOIN '.$db_prefix . 'region As r2 ON r2.id = s.city_id')
            ->join('LEFT JOIN '.$db_prefix . 'region As r3 ON r3.id = s.district')
            ->order($order)
            ->where($store_where)
            ->page($p,$item)
            ->cache(true,TPSHOP_CACHE_TIME)
            ->select();
        return $store_list;
    }

    /**
     * 获取店铺商品详细
     * @param $store_id
     * @param $limit
     * @return mixed
     */
    public function getStoreGoods($store_id,$limit)
    {
        $goods_model = M('goods');
        $goods_where = array(
            'is_on_sale'=>1,
//            'is_recommend'=>1,
//            'is_hot'=>1,
            'goods_state'=>1,
            'store_id'=>$store_id
        );
        $res['goods_list'] = $goods_model->field('goods_id,goods_name,shop_price')->where($goods_where)->limit($limit)->order('sort desc')->select();
        $count_where = array(
            'goods_state'=>1,
            'store_id'=>$store_id
        );
        $res['goods_count'] = $goods_model->where($count_where)->count();
        return $res;
    }
}