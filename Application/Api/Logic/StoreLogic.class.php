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
 * Author: IT宇宙人
 * Date: 2015-09-09
 */

namespace Api\Logic;

use Think\Model\RelationModel;
/**
 * @package Api\Logic
 */
class StoreLogic extends RelationModel
{
    /**
     * 店铺收藏or取消操作
     * @author dyr
     * @param $user_id
     * @param $store_id
     * @return array
     */
    public function collectStoreOrNo($user_id,$store_id)
    {
        if (empty($store_id) || empty($user_id)) {
            $res = array('status' => '-1', 'msg' => '参数错误');
            return $res;
        }
        $store_collect_info = M('store_collect')->where(array('store_id' => $store_id, 'user_id' => $user_id))->find();
        if (empty($store_collect_info)) {
            //收藏
            $store_name = M('store')->where(array('store_id' => $store_id))->getField('store_name');
            $store_collect_data = array(
                'user_id' => $user_id,
                'store_id' => $store_id,
                'add_time' => time(),
                'store_name' => $store_name,
                'user_name' => $this->user['nickname']
            );
            M('store_collect')->add($store_collect_data);
            M('store')->where(array('store_id' => $store_id))->setInc('store_collect');
            $res = array('status' => '1', 'msg' => '收藏成功');
        } else {
            //取消收藏
            $store_collect = M('store')->where(array('store_id' => $store_id))->getField('store_collect');
            if ($store_collect > 0){
                M('store')->where(array('store_id' => $store_id))->setDec('store_collect');
            }
            M('store_collect')->where(array('store_id' => $store_id, 'user_id' => $user_id))->delete();
            $res = array('status' => '1', 'msg' => '取消成功');
        }
        return $res;
    }
}