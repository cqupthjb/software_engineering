<?php
/**
 * tpshop
 * 回复模型
 * @auther：dyr
 */ 
namespace App\Model;
use Think\Model;


class FeedbackModel extends Model{
    /**
     * 根据评论id获取评论下的所有回复
     * @param int $parent_id
     * @param array $result
     * @return array
     */
    public function getFeedbackList($parent_id = 0,$page=1,$item_num=10)
    {
        $reply_where['parent_id']  = $parent_id ;
        $arr = M('Feedback')->where($reply_where)->order('msg_time desc')->page("$page,$item_num")->select();
        if (empty($arr)) {
            return array();
        }
        return $arr;
    }
}