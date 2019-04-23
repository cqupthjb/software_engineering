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

namespace Seller\Model;

use Think\Model\RelationModel;

/**
 * Class UserModel
 * @package Seller\Model
 */
class UserNewsModel extends RelationModel
{

    /*
     * 商家发货后 添加 用户消息
     * $params order_id 订单ID
     */
    public function OrderNew($order_id)
    {
        $order = D('Order')->where(array('order_id' => $order_id))->field('order_id,order_sn,user_id,order_status,shipping_status,pay_status')->find();
        switch ($order['shipping_status']) {
            case 1:
                $content = '您有个订单已发货，请注意查看';
                break;
            case 2:
                $content = '您有个订单已部分发货，请注意核对';
                break;
            default:
                $content = '';
        }
        $add = array(
            'user_id' => $order['user_id'],//用户id
            'order_id' => $order['order_id'],//订单ID
            'content' => $content,//消息内容
            'time' => time(),//添加时间
        );
        if ($order['shipping_status'] == '2' || $order['shipping_status'] == '1') {  //目前订单消息只有收货
            $data = $this->add($add);
            if ($data) {
                return true;
            } else {
                return false;
            }
        }
    }
}