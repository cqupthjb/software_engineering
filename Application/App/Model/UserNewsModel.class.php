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

namespace App\Model;

use Think\Model\RelationModel;

/**
 * Class UserModel
 * @package Admin\Model
 */
class UserNewsModel extends RelationModel
{
    
/*
	 * 用户申请提现成功后 添加 用户消息 
	 * $params $user_id 用户
	 * $params $id 提现表ID
	 */
	public function OrderNew($user_id,$id){
		$content = '您有条提现申请已受理';
			$add = array(
				'user_id'=>$user_id,//用户id
				'cash_id'=>$id,//提现ID户id
				'content'=>$content,//消息内容
				'time'=>time(),//添加时间
			);
			$data = $this->add($add);
			if($data){
				return true;
			}else{
				return false;
			}
	
	}

	
	
	
	
}