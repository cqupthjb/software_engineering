<?php
/**
 * Created by PhpStorm.
 * User: Mrs-Y
 * Date: 2017/08/27 0027
 * Time: 22:50
 */

namespace Admin\Controller;


use Think\AjaxPage;
use Think\Exception;

class FeedbackController extends BaseController
{
    public function index(){
        $this->display();
    }

    public function detail()
    {
        $id = I('id');
        $res = M('feedback')->where(array('msg_id' => $id))->find();
        if (!$res) {
            $this->error('不存在该评论');exit();
        }
        if (IS_POST) {
            try{
                $reply = trim(I('post.content'));
                if (empty($reply)) throw new Exception('说点什么吧');
                $add['parent_id'] = $id;
                $add['msg_content'] = $reply;
                $add['msg_time'] = time();
                $add['user_name'] = '管理员';
                $add['msg_title'] = '回复';
                $add['msg_type'] = 0;

                $row = M('feedback')->add($add);
                if ($row===false) throw new Exception('回复失败');
                //更改回复数量
                M('feedback')->where(array('msg_id'=>$id))->setInc('reply_num',1);
                $res = array('status'=>1,'info'=>'添加成功');
            }catch(Exception $e){
                $res = array('status'=>0,'info'=>$e->getMessage());
            }
            $this->ajaxReturn($res);die;
        }
        $reply = M('feedback')->where(array('parent_id' => $id))->select(); // 评论回复列表

        $this->assign('msg', $res);
        $this->assign('reply', $reply);
        $this->display();
    }

    public function del()
    {
        $id = I('get.id');
        $row = M('feedback')->where(array('msg_id' => $id))->delete();
        if ($row) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    public function op(){
        $type = I('post.type');
        $selected_id = I('post.selected');
        if(!in_array($type,array('del','show','hide')) || !$selected_id)
            $this->error('非法操作');
        $where = "msg_id IN ({$selected_id})";
        if($type == 'del'){
            //删除回复
            $where .= " OR parent_id IN ({$selected_id})";
            $row = M('feedback')->where($where)->delete();
        }
        if($type == 'show'){
            $row = M('feedback')->where($where)->save(array('is_show'=>1));
        }
        if($type == 'hide'){
            $row = M('feedback')->where($where)->save(array('is_show'=>0));
        }
        if(!$row)
            $this->error('操作失败');
        $this->success('操作成功');

    }

    public function ajaxindex(){
        $model = M('feedback');
        $username = I('nickname','','trim');
        $content = I('content','','trim');
        $where['c.parent_id'] = 0;
        if($username){
            $where['u.nickname'] = $username;
        }
        if($content){
            $where['c.msg_content'] = array('like','%'.$content.'%');
        }
        $msg_type = I('msg_type',-1);
        if ($msg_type > -1){
            $where['c.msg_type'] = $msg_type;
        }
        $count = $model->alias('c')->join('LEFT JOIN __USERS__ u ON u.user_id = c.user_id')->where($where)->count();
        $Page  = new AjaxPage($count,16);


        /*//是否从缓存中读取Page
        if(session('is_back')==1){
            $Page = getPageFromCache();
            delIsBack();
        }*/

        $msg_list = $model->alias('c')->field('c.*,u.nickname as nickname')->join('LEFT JOIN __USERS__ u ON u.user_id = c.user_id')->where($where)->order('msg_time DESC')->limit($Page->firstRow.','.$Page->listRows)->select();

        cachePage($Page);
        $show = $Page->show();

        $this->assign('msg_list',$msg_list);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

}