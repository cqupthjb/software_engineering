<?php
/**
 * tpshop
 * 回复模型
 * @auther：dyr
 */ 
namespace Mobile\Model;
use Think\Model;


class CommentModel extends Model{
    /**
     * 根据评论id获取评论下的所有回复
     * @param $comment_id
     * @param int $parent_id
     * @param array $result
     * @return array
     */
//    public function getCommentList($comment_id, $parent_id = 0, &$result = array())
//    {
//        $reply_where = array('deleted' => 0);
//        if ($comment_id)
//            $reply_where['comment_id'] = $comment_id ;
//        if ($parent_id)
//            $reply_where['parent_id']  = $parent_id ;
//        $arr = M('Comment')->where($reply_where)->order('add_time desc')->select();
//
//        if (empty($arr)) {
//            return array();
//        }
//        /*foreach ($arr as $cm) {
//            $thisArr =& $result[];
//            $cm['children'] = $this->getCommentlist(0, $comment_id, $thisArr);
//            $thisArr = $cm;
//        }*/
//        $comment = M('comment')->where(array('comment_id'=>$parent_id))->select();
//        $comment['children'] = $arr;
//        return $comment;
//    }
    public function getCommentList($parent_id = 0,$page=1,$item_num=10)
    {
        $reply_where = array('deleted' => 0);
        $reply_where['parent_id']  = $parent_id ;
        $arr = M('Comment')->where($reply_where)->order('add_time desc')->page("$page,$item_num")->select();
        if (empty($arr)) {
            return array();
        }
        return $arr;
    }
}