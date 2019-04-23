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
use Api\Model\StoreModel;
use Home\Logic\GoodsLogic;
use Think\Controller;
use Home\Logic\StoreLogic;
class ActivityController extends BaseController {
    /**
     * @author dyr
     * @time 2016/09/20
     * 团购活动列表
     */
    public function group_list()
    {
        $page_size = I('page_size',10);
        $p = I('p',1);
        $group_by_where = array(
            'start_time'=>array('lt',time()),
            'end_time'=>array('gt',time()),
        );
        $list = M('GroupBuy')->field('goods_id,rebate,virtual_num,buy_num,title,goods_price,end_time,price')->where($group_by_where)->page($p,$page_size)->select(); // 找出这个商品
        $json = array(
            'status'=>1,
            'msg'=>'获取成功',
            'result'=>array(
                'group_goods'=>$list,
            ),
        );
        $this->ajaxReturn($json);
    }
    /**
     * @author dyr
     * @time 2016/09/20
     * 团购详情页
     */
    public function group(){
        //form表单提交
        $goods_id = I('id',66);
        $goodsLogic = new GoodsLogic();
        $group_buy_where = array(
            'goods_id'=>$goods_id,
            'start_time'=>array('lt',time()),
            'end_time'=>array('gt',time()),
        );
        $group_buy_info = M('GroupBuy')->where($group_buy_where)->find(); // 找出这个商品

        $goods = M('Goods')->where('goods_id = '.$goods_id)->find();
        $goods_images_list = M('GoodsImages')->where('goods_id = '.$goods_id)->select(); // 商品 图册
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where('goods_id = '.$goods_id)->select(); // 查询商品属性表

        $Model = M('');
        // 商品规格 价钱 库存表 找出 所有 规格项id
        $keys = M('SpecGoodsPrice')->where('goods_id = '.$goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') ");
        if ($keys) {
            $specImage = M('SpecImage')->where("goods_id = $goods_id and src != '' ")->getField("spec_image_id,src");// 规格对应的 图片表， 例如颜色
            $keys = str_replace('_', ',', $keys);
            $sql = "SELECT a.name,a.order,b.* FROM __PREFIX__spec AS a INNER JOIN __PREFIX__spec_item AS b ON a.id = b.spec_id WHERE b.id IN($keys) ORDER BY a.order";
            $filter_spec2 = $Model->query($sql);
            foreach ($filter_spec2 as $key => $val) {
                $filter_spec[$val['name']][] = array(
                    'item_id' => $val['id'],
                    'item' => $val['item'],
                    'src' => $specImage[$val['id']],
                );
            }
        }
        $spec_goods_price  = M('spec_goods_price')->where("goods_id = $goods_id")->getField("key,price,store_count"); // 规格 对应 价格 库存表
        M('Goods')->where("goods_id=$goods_id")->save(array('click_count'=>$goods['click_count']+1 )); // 统计点击数
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $json = array(
            'status'=>1,
            'msg'=>'获取成功',
            'result'=>array(
                'group_buy_info'=>$group_buy_info,
                'spec_goods_price'=>$spec_goods_price,
                'commentStatistics'=>$commentStatistics,
                'goods_attribute'=>$goods_attribute,
                'goods_attr_list'=>$goods_attr_list,
                'filter_spec'=>$filter_spec,
                'goods_images_list'=>$goods_images_list,
                'goods'=>$goods,
            ),
        );
        $this->ajaxReturn($json);
    }
}