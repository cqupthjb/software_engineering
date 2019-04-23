<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: 当燃      
 * Date: 2015-09-09
 */

namespace Mobile\Model;

use Think\Model\RelationModel;

/**
 * Class UserModel
 * @package Admin\Model
 */
class RebateLogsModel extends RelationModel{
	
	
	protected $_link = array(
		'rebate' => array(
			'mapping_type'=>self::BELONGS_TO,
			'class_name'=>'Rebate',
			'foreign_key'=>'rebate_id',
			'mapping_name'=>'rebate',
			'mapping_fields'=>'value',
			'as_fields'=>'value:value',
		),
	);
	
	/*
	 * 用户返利列表
	 * $user_id  用户ID
	 */
	public function rebateList($user_id){
		$rebate_logs = D('RebateLogs')->where(array('user_id'=>$user_id))->select();
		foreach ($rebate_logs as $k =>$v){
			$order_goods = M('order_goods')->where(array('order_id'=>$v['order_id']))->field('goods_id,goods_num')->select();
			foreach ($order_goods as $k1 =>$v1){
				$goods = D('Goods')->relation('rebate')->where(array('goods_id'=>$v1['goods_id']))->field('goods_id,rebate_id,goods_name,original_img,shop_price')->find();	
				$order_goods[$k1]['goods_name'] = $goods['goods_name']; //商品名字
				$order_goods[$k1]['original_img'] = $goods['original_img']; //商品图片
				$order_goods[$k1]['shop_price'] = $goods['shop_price']; //商品单价
				$order_goods[$k1]['value'] = $goods['value']; //商品返利百分比
				$order_goods[$k1]['goods_num'] = $v1['goods_num']; //商品数量
			}	
			$rebate_logs[$k][good_list]=$order_goods;
			$rebate_logs[$k]['not_money'] = $v['total_money']-$v['distribut_money'];    //未返利金额 = 总返利金额 -累计返利金额
		}
		//dump($rebate_logs);exit;
		return $rebate_logs;
	}
	
	
	
	
	
}