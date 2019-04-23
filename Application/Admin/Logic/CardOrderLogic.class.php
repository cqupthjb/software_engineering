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
 * Date: 2015-09-14
 */


namespace Admin\Logic;
use Think\Model\RelationModel;

class CardOrderLogic extends RelationModel
{
    /**
     * @param array $condition  搜索条件
     * @param string $order   排序方式
     * @param int $start    limit开始行
     * @param int $page_size  获取数量
     */
    public function getOrderList($condition,$order='',$start=0,$page_size=20){
        return M('card_order')->where($condition)->limit("$start,$page_size")->order($order)->select();
    }
    /*
     * 获取订单商品详情
     */
    public function getOrderCards($order_id = 0){
        //$sql = "SELECT g.*,o.*,(o.goods_num * o.member_goods_price) AS goods_total FROM __PREFIX__card_suborder o ".
        //   "LEFT JOIN __PREFIX__goods g ON o.goods_id = g.goods_id WHERE o.order_id = $order_id";
        //region 联合查询
        $map = array();
        $map['so.order_id'] = $order_id;
        $field = 'so.* , c.* ';
        $join = array('LEFT JOIN __CARD__ AS c ON c.card_id = so.card_id');
        $alias = 'so';
        $res = M('card_suborder')->alias($alias)->join($join)->where($map)->field($field)->select();
        //endregion
        return $res;
    }

    /*
     * 获取订单信息
     */
    public function getOrderInfo($order_id = 0)
    {
        //  订单总金额查询语句		
        $order = M('card_order')->where(array('order_id'=>$order_id))->find();
        $order['address2'] = $this->getAddressName($order['province'],$order['city'],$order['district']);
        $order['address2'] = $order['address2'].$order['address'];		
        return $order;
    }

    /*
     * 根据商品型号获取商品
     */
    public function get_cards($ids = ''){
        $cards = M('card')->where(array('card_id',array('in',$ids)))->find();
        return $cards;
    }

    /*
     * 订单操作记录
     */
    public function orderActionLog($order_id,$action,$note='',$action_user = 0,$user_type = 0){
        $order = M('card_order')->where(array('order_id'=>$order_id))->find();
        $data['order_id'] = $order_id;
        $data['action_user'] = $action_user; // 操作者 session('seller_id');
        $data['user_type'] = $user_type; // 0管理员 1商家 2前台用户
        $data['action_note'] = $note;
        $data['order_status'] = $order['order_status'] ? $order['order_status'] : 5;
        $data['pay_status'] = $order['pay_status'] ? $order['pay_status'] : 0;
        $data['shipping_status'] = $order['shipping_status'] ? $order['shipping_status'] : 0;
        $data['log_time'] = time();
        $data['status_desc'] = $action;        
        return M('card_order_action')->add($data);//订单操作记录
    }

    /*
     * 获取订单商品总价格
     */
    public function getCardsPrice($order_id = 0){
        //$sql = "SELECT SUM(goods_num * goods_price) AS goods_amount FROM __PREFIX__card_suborder WHERE order_id = {$order_id}";
        //$res = $this->query($sql);
        $suborder = M('card_suborder')->where(array('order_id'=>$order_id))->select();
        $card_price = 0;
        $postage = 0;
        if (!empty($suborder)) {
            foreach ($suborder as $key => $vo){
                // 单价 * 数量 + 邮费 = 总价格
                $card_price += get_column('card',array('card_id'=>$vo['card_id']),'price') * $vo['store_count'];
                // 判断是否包邮
                if (get_column('card',array('card_id'=>$vo['card_id']),'is_free_shipping') != 1){
                    $postage += get_column('card',array('card_id',$vo['card_id']),'postage');//邮费
                }
            }
        }
        return array('card_price'=>$card_price,'postage'=>$postage,'total_price'=>$card_price + $postage);
    }

    /**
     * 得到发货单流水号
     */
    public function get_delivery_sn()
    {
        /* 选择一个随机的方案 */send_http_status('310');
		mt_srand((double) microtime() * 1000000);
        return date('YmdHi') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    /*
     * 获取当前可操作的按钮
     */
    public function getOrderButton($order){
        /*
         *  操作按钮汇总 ：付款、设为未付款、确认、取消确认、无效、去发货、确认收货、申请退货
         * 
         */
    	$os = $order['order_status'];//订单状态
    	$ss = $order['shipping_status'];//发货状态
    	$ps = $order['pay_status'];//支付状态
        $btn = array();
        if($order['pay_code'] == 'cod') {
        	if($os == 0 && $ss == 0){
        		$btn['confirm'] = '确认';
        	}elseif($os == 1 && $ss == 0 ){
        		$btn['delivery'] = '去发货';
        		$btn['cancel'] = '取消确认';
        	}elseif($ss == 1 && $os == 1 && $ps == 0){
        		$btn['pay'] = '付款';
        	}elseif($ps == 1 && $ss == 1 && $os == 1){
        		//$btn['pay_cancel'] = '设为未付款';
        	}
        }else{
        	if($ps == 0 && $os == 0){
        		//$btn['pay'] = '付款';
        	}elseif($os == 0 && $ps == 1){
        		//$btn['pay_cancel'] = '设为未付款';
        		$btn['confirm'] = '确认';
        	}elseif($os == 1 && $ps == 1 && $ss==0){
        		$btn['cancel'] = '取消确认';
        		$btn['delivery'] = '去发货';
        	}
        } 
               
        if($ss == 1 && $os == 1 && $ps == 1){
        	$btn['delivery_confirm'] = '确认收货';
        	//$btn['refund'] = '申请退货';
        }elseif($os == 2 || $os == 4){
        	//$btn['refund'] = '申请退货';
        }elseif($os == 3 || $os == 5){
        	//$btn['remove'] = '移除';
        }
        if($os != 5  && $ps !=1 ){
        	$btn['invalid'] = '无效';
        }
        return $btn;
    }

    //更改订单状态
    public function orderProcessHandle($order_id,$act,$store_id = 0){
    	$updata = array();
    	switch ($act){
    		case 'pay': //付款
                $order_sn = M('card_order')->where(array('order_id'=>$order_id))->getField("order_sn");
                update_pay_status($order_sn); // 调用确认收货按钮
    			return true;    			
    		case 'pay_cancel': //取消付款
    			$updata['pay_status'] = 0;
    			$this->order_pay_cancel($order_id);
    			return true;
    		case 'confirm': //确认订单
    			$updata['order_status'] = 1;
    			break;
    		case 'cancel': //取消确认
    			$updata['order_status'] = 0;
    			break;
    		case 'invalid': //作废订单
    			$updata['order_status'] = 5;
    			break;
    		case 'remove': //移除订单
                return $this->delOrder($order_id);
    			break;
    		case 'delivery_confirm'://确认收货
    			return confirm_card_order($order_id); // 调用确认收货按钮
                break;
    		default:
    			return true;
    	}

    	if (!isset($updata['order_status'])) return false;
    	$map = array();
    	$map['order_id'] = $order_id;
    	//$map['store_id'] = $store_id;
    	return M('card_order')->where($map)->save($updata);//改变订单状态
    }

    //获取卡券订单操作名
    public function get_card_order_action_name($action=''){
        switch ($action){
            case 'pay': //付款
                return '付款';
            case 'pay_cancel': //取消付款
                return '取消付款';
            case 'confirm': //确认订单
               return '确认订单';
            case 'cancel': //取消确认
                return '取消确认';
            case 'invalid': //作废订单
                return '作废订单';
            case 'remove': //移除订单
               return '移除订单';
            case 'delivery_confirm'://确认收货
                return '确认收货';
            case 'delivery':
                return '确认发货';
        }
        return '';
    }
    
    //管理员取消付款
    function order_pay_cancel($order_id)
    {
    	//如果这笔订单已经取消付款过了
    	$count = M('card_order')->where("order_id = $order_id and pay_status = 1")->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
    	if($count == 0) return false;
    	// 找出对应的订单
    	$order = M('card_order')->where(array('order_id'=>$order_id))->find();
    	// 增加对应商品的库存
    	$orderCardArr = M('card_suborder')->where(array('order_id'=>$order_id))->select();
    	foreach($orderCardArr as $key => $val)
    	{
    	    //增加库存
            M('Card')->where(array('card_id'=>$val['card_id']))->setInc('store_count',$val['card_num']); // 增加商品总数量

    		//M('Card')->where(array('card_id'=>$val['card_id']))->setDec('sales_sum',$val['card_num']); // 减少商品销售量
    	}
    	// 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
    	M('card_order')->where("order_id=$order_id")->save(array('pay_status'=>0));
    	update_user_level($order['user_id']);
    	// 记录订单操作日志
        card_order_log($order['order_id'],$order['user_id'],'订单取消付款','付款取消');
    	//分销设置
    	M('card_rebate_log')->where(array('order_id'=>$order['order_id']))->save(array('status'=>0));
    }
    
    /**
     *	处理发货单
     * @param array $data  查询数量
     */
    public function deliveryHandle($data,$store_id=0){
		$order = $this->getOrderInfo($data['order_id']);
		$orderCards = $this->getOrderCards($data['order_id']);
		$selectcard = $data['card'];
		$data['order_sn'] = $order['order_sn'];
		$data['delivery_sn'] = $this->get_delivery_sn();
		$data['zipcode'] = $order['zipcode'];
		$data['user_id'] = $order['user_id'];
		$data['admin_id'] = session('admin_id');
		$data['consignee'] = $order['consignee'];
		$data['mobile'] = $order['mobile'];
		$data['country'] = $order['country'];
		$data['province'] = $order['province'];
		$data['city'] = $order['city'];
		$data['district'] = $order['district'];
		$data['address'] = $order['address'];
		$data['shipping_code'] = $order['shipping_code'];
		$data['shipping_name'] = $order['shipping_name'];
		$data['shipping_price'] = $order['shipping_price'];
		$data['create_time'] = time();
        //$data['store_id'] = $store_id;
        if ($store_id ==0 ){
        	$data['store_id'] = $order['store_id'];
        }
		
                
		$did = M('card_delivery_doc')->add($data);
		$is_delivery = 0;
		foreach ($orderCards as $k=>$v){
			if($v['is_send'] == 1){
				$is_delivery++;
			}			
			if($v['is_send'] == 0 && in_array($v['sub_id'],$selectcard)){
				$res['is_send'] = 1;
				$res['delivery_id'] = $did;
                 //$r = M('card_suborder')->where("sub_id={$v['sub_id']}  and store_id = $store_id")->save($res);//改变订单商品发货状态
                 $r = M('card_suborder')->where(array('sub_id'=>$v['sub_id']))->save($res);//改变订单商品发货状态
                if ($r === false) {
                    return false;break;
                }
				$is_delivery++;
			}
		}
		$updata['shipping_time'] = time();
		if($is_delivery == count($orderCards)){
			$updata['shipping_status'] = 1;
		}else{
			$updata['shipping_status'] = 2;
		}
                
        $res = M('card_order')->where(array('order_id'=>$data['order_id']))->save($updata);//改变订单状态 and store_id = $store_id
	        //$seller_id = session('seller_id');
		$s = $this->orderActionLog($order['order_id'],$this->get_card_order_action_name('delivery'),$data['note'],session('admin_id'));//操作日志
		return $s!==false && $res!==false ? true : false;
    }

    /**
     * 获取地区名字
     * @param int $p
     * @param int $c
     * @param int $d
     * @return string
     */
    public function getAddressName($p=0,$c=0,$d=0){
        $p = M('region')->where(array('id'=>$p))->field('name')->find();
        $c = M('region')->where(array('id'=>$c))->field('name')->find();
        $d = M('region')->where(array('id'=>$d))->field('name')->find();
        return $p['name'].','.$c['name'].','.$d['name'].',';
    }

    /**
     * 删除订单
     * @param int $order_id
     * @return bool
     */
    function delOrder($order_id=0){//,$store_id
    	$a = M('card_order')->where(array('order_id'=>$order_id))->delete();
    	if ($a === false)
    	    return false;
        $a = M('card_suborder')->where(array('order_id'=>$order_id))->delete();

    	return $a;
    }

}