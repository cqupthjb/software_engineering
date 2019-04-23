<?php

namespace Admin\Controller;
use Think\Exception;

/**
 * By ~ Mr-X
 */
class DistributionController extends BaseController
{
    //分销角色列表
    public function role_list()
    {
        $list = M('distribution_role')->order('role_id asc')->select();
        $this->assign('list', $list);
        $this->display('role_list');
    }

    //分销角色 添加 | 修改
    public function role_info(){
        $role_id = I('get.role_id');
        if($role_id){
            $row = M('distribution_role')->where(array('role_id'=>$role_id))->find();
            $this->assign('row',$row);
        }
        $this->display();
    }

    //存取分销角色信息
    public function roleSave(){
        $model = 'distribution_role';
        try{
            $data = $this->check_role();
            if(empty($data['role_id'])){
                $r = M($model)->add($data);
            }else{
                $r = M($model)->where(array('role_id'=>$data['role_id']))->save($data);
            }
            if($r !== false){
                $this->success("操作成功!",U('Admin/Distribution/role_list'));
            }else{
                $this->success("操作失败!",U('Admin/Distribution/role_info',array('role_id'=>$data['role_id'])));
            }
        }catch(Exception $e){
            $this->error($e->getMessage());
        }
    }

    //验证数据
    protected function check_role(){
        $role = I('post.');
        if (empty($role))
            throw new Exception('请填写分销角色数据');
        if (empty($role['name']))
            throw new Exception('请填写分销角色名称');
//        if ($role['proportion'] < 0)
//            throw new Exception('返现比例请在0-100之间,百分比');
        if (empty($role['description']))
            unset($role['description']);
        #region 重复过滤
        $map = array();
        $map['name'] = $role['name'];
        if (!empty($role['role_id']))
            $map['role_id'] = array('neq',$role['role_id']);
        if ( get_count('distribution_role',$map) )
            throw new Exception('分销角色已存在');
        #endregion
        return $role;
    }

    //删除分销角色
    public function roleDel(){
        $role_id = I('post.role_id');
        $admin = D('admin')->where(array('dis_role_id'=>$role_id))->find();
        if($admin){
            exit(json_encode("请先清空所属该角色的管理员"));
        }else{
            $d = M('distribution_role')->where(array('role_id'=>$role_id))->delete();
            if($d!==false){
                exit(json_encode(1));
            }else{
                exit(json_encode("删除失败"));
            }
        }
    }

    //修改字段
    public function change_column(){
        try{
            $role_id = intval(I('role_id'));
            $num = intval(I('num'));
            $old_num = M('distribution_role')->where(array('role_id'=>$role_id))->getField('num');
            if ($old_num == $num) $this->ajaxReturn(array('status'=>1));
            if ($num < 0) throw new Exception('下线数量请大于等于0');
            $res = M('distribution_role')->where(array('role_id'=>$role_id))->setField(array('num'=>$num));
            if ($res === false){
                throw new Exception('修改失败');
            }
            $this->ajaxReturn(array('status'=>1,'msg'=>'修改成功','num'=>$num),'json');
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()),'json');
        }
    }

    /**
     * 分销树状关系
     */
    public function tree(){
        $where = 'is_distribut = 1 and first_leader = 0';
        if($_POST['user_id'])
            $where = "user_id = '{$_POST['user_id']}'";
        $list = M('users')->where($where)->select();
        $this->assign('list',$list);
        $this->display();
    }

}