<?php
/**
 * Created by PhpStorm.
 * User: yq
 * Date: 2017/8/23 0023
 * Time: 16:36
 */

namespace Mobile\Model;


use Think\Model;

class ArticleModel extends Model
{
    /**
     * 公告
     * @author dyr
     * @param $cat_id 公告分类ID，可不传，不传将检索所有分类
     * @param int $p 分页
     * @param int $item 每页多少条记录
     * @param string $order_by 排序值
     * @return mixed
     */
    public function getNoticeList($cat_id,$p=1,$item=10,$order_by='')
    {
        $store_where = array();
        if(!empty($cat_id)){
            $store_where['cat_id'] = $cat_id;
        }
        $article_list = $this
            ->where($store_where)
            ->page($p,$item)
            ->cache(true,TPSHOP_CACHE_TIME)
            ->select();
        return $article_list;
    }
}