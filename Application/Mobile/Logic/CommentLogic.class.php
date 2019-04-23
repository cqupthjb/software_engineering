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
 * Author: dyr
 * Date: 2016-08-09
 */

namespace Mobile\Logic;

use Think\Model\RelationModel;

/**
 * 回复
 * Class CatsLogic
 * @package Home\Logic
 */
class CommentLogic extends RelationModel{
    /**
     * 把回复树状数组转换成二维数组
     * @param $comment_id 回复id
     * @param int $item_num 条数
     * @return array
     */
    public function getCommentList($comment_id, $item_num = 0)
    {
        $Comment_tree = D('Comment')->getCommentList($comment_id);
        if(empty($Comment_tree)){
            return $Comment_tree;
        }
        $Comment_flat_list = $this->treeToArray($Comment_tree);
        if ($item_num == 0 || count($Comment_flat_list) <= $item_num) {
            $res =  $Comment_flat_list;
        } else {
            $res =  array_slice($Comment_flat_list, $item_num);
        }
        return $res;
    }

    /**
     * 回复分页
     * @param $comment_id
     * @param int $page
     * @param int $item_num
     * @return mixed
     */
    public function getCommentPage($comment_id, $page = 1, $item_num = 20)
    {
        $Comment_tree = D('Comment')->getCommentList($comment_id,$page,$item_num);
        //$Comment_flat_list = $this->treeToArray($Comment_tree);

        $count = count($Comment_tree);
        //$list['list'] = array_slice($Comment_tree, $page * $item_num, $item_num);
        $list['list'] = $Comment_tree;
        $list['count'] = $count;

        return $list;
    }

    /**
     * 将树状数组转换二维数组
     * @param $tree
     * @return array
     */
    public function treeToArray($tree) {
        $list = array();
        foreach($tree as $key) {
            $node = $key['children'];
            unset($key['children']);
            $list[] = $key;
            if($node) $list = array_merge($list, $this->treeToArray($node));
        }
        return $list;
    }
}