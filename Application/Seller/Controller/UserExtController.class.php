<?php
/**
 * Created by PhpStorm.
 * User: yq
 * Date: 2017/8/2
 * Time: 14:22
 */

namespace Seller\Controller;


use Think\Exception;
use Think\Page;

class UserExtController extends BaseController
{
    /**
     * 申请提现记录
     */
    public function withdrawals(){

        C('TOKEN_ON',true);
        /*if(IS_POST)
        {
            //$this->verifyHandle('withdrawals');
            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $distribut_min = tpCache('distribut.min'); // 最少提现额度
            if($data['money'] < $distribut_min)
            {
                $this->error('每次最少提现额度'.$distribut_min);
                exit;
            }
            if($data['money'] > $this->user['user_money'])
            {
                $this->error("你最多可提现{$this->user['user_money']}账户余额.");
                exit;
            }

            if(M('withdrawals')->add($data)){
                $this->success("已提交申请");
                exit;
            }else{
                $this->error('提交失败,联系客服!');
                exit;
            }
        }*/

        $where = array('user_id'=>$this->_seller['user_id']);
        $count = M('withdrawals')->where($where)->count();
        $page = new Page($count,16);
        $list = M('withdrawals')->where($where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $user = M('users')->where(array('user_id'=>$this->_seller['user_id']))->find();

        $this->assign('user',$user);
        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list',$list); // 下线
        if($_GET['is_ajax'])
        {
            $this->display('ajaxx_withdrawals_list');
            exit;
        }
        $this->display();
    }


    /**
     * 提现申请记录
     */
//    public function withdrawals()
//    {
//        $model = M("store_withdrawals");
//        $status = I('status');
//        $account_bank = I('account_bank');
//        $account_name = I('account_name');
//        $create_time = I('create_time');
//        $create_time = str_replace("+"," ",$create_time);
//        $create_time = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
//        $create_time2 = explode(' - ',$create_time);
//        $where = "store_id = ".STORE_ID." and create_time >= '".strtotime($create_time2[0])."' and create_time <= '".strtotime($create_time2[1])."' ";
//
//        if($status === '0' || $status > 0)
//            $where .= " and status = $status ";
//        $account_bank && $where .= " and account_bank like '%$account_bank%' ";
//        $account_name && $where .= " and account_name like '%$account_name%' ";
//
//        $count = $model->where($where)->count();
//        $Page  = new Page($count,16);
//        $list = $model->where($where)->order("`id` desc")->limit($Page->firstRow.','.$Page->listRows)->select();
//
//        $show  = $Page->show();
//        $store = M('store')->where("store_id = ".STORE_ID)->find();
//
//        $this->assign('store',$store);
//        $this->assign('create_time',$create_time);
//        $this->assign('show',$show);
//        $this->assign('list',$list);
//        C('TOKEN_ON',false);
//        $this->display();
//    }

    /**
     * 添加提现申请
     */
    public function add_edit_withdrawals()
    {
        $id = I('id',0);
        $id = $id ? $id : 0;
        $Model = M('withdrawals');
        $withdrawals = $Model->where("id = $id")->find();
        $withdrawals_max = M('users')->where(array('user_id'=>$this->_seller['user_id']))->getField('user_money');
        if(IS_POST)
        {
            try{
                $data = I('post.');
                $distribut_min = tpCache('distribut.min'); // 最少提现额度
                if($data['money'] < $distribut_min) {
                    throw new Exception('每次最少提现额度'.$distribut_min);
                }
                if($data['money'] > $withdrawals_max) {
                    throw new Exception("你最多可提现{$withdrawals_max}账户余额.");
                }

                if (isset($data['id']) && $data['id'] >0){ //修改
                    if($withdrawals['status'] == 1)
                        throw new Exception('申请成功的不能再修改');
                        if(M('withdrawals')->save($data) === false){
                            throw new Exception('提交失败,联系客服!');
                        }
                }else{ //添加
                    $data['user_id'] = $this->_seller['user_id'];
                    $data['create_time'] = time();
                    if(M('withdrawals')->add($data) === false){
                        throw new Exception('提交失败,联系客服!');
                    }
                }
                $this->success("已提交申请",U('withdrawals'));die;
            }catch(Exception $e){
                $this->error($e->getMessage());die;
            }
        }

        //region 银行信息列表
        $bank_list = M('user_bank')->where(array('user_id'=>$this->_seller['user_id']))->select();
        if (!empty($bank_list)){
            $this->assign('bank_list',$bank_list);
            $this->assign('json_bank_list',json_encode($bank_list));
        }
        //endregion

        $this->assign('withdrawals_max',$withdrawals_max);
        $this->assign('withdrawals_min',tpCache('distribut.min'));
        $this->assign('withdrawals',$withdrawals);
        $this->display('_withdrawals');
    }

    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        try{
            $id = I('id');
            if (empty($id))
                throw new Exception('参数错误');
            $model = M("withdrawals");
            $map = array();
            $map['id'] = $id;
            $map['user_id'] = $this->_seller['user_id'];
            $map['status'] = array('neq',1);
            $res = $model->where($map)->delete();
            if ($res === false)
                throw new Exception('删除失败');
            $return_arr = array('status' => 1,'msg' => '操作成功');
        }catch(Exception $e){
            $return_arr = array('status' => -1,'msg' => $e->getMessage());
        }
        $this->ajaxReturn($return_arr);
    }


    //region 银行账户管理
    //银行卡列表
    public function bank_list(){
        $where = array('user_id'=>$this->_seller['user_id']);
        $count = M('user_bank')->where($where)->count();
        $page = new Page($count,16);
        $list = M('user_bank')->where($where)->order("bank_id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list',$list); // 下线
        $this->display();
    }

    //添加 | 修改 银行信息
    public function add_bank(){
        if (IS_POST) {
            try{
                $data = $this->_check_bank();
                if(isset($data['bank_id'])){
                    $res = M('user_bank')->where(array('bank_id'=>$data['bank_id']))->save($data);
                }else{
                    $data['user_id'] = $this->_seller['user_id'];
                    $res = M('user_bank')->add($data);
                }
                if ($res === false)
                    throw new Exception('操作失败');
                $res = array('status'=>1,'msg'=>'操作成功');
            }catch(Exception $e){
                $res = array('status'=>0,'msg'=>$e->getMessage());
            }
            $this->ajaxReturn($res,'json');die;
        }
        //region 修改的信息
        $bank_id = I('bank_id');
        if ($bank_id){
            $info = M('user_bank')->where(array('bank_id'=>$bank_id))->find();
            if (!empty($info)) $this->assign('info',$info);
        }
        //endregion
        $this->display();
    }
    //验证银行信息
    protected function _check_bank(){
        $data = I('post.');
        if (empty($data['bank_name'])) {
            throw new Exception('银行名称必填');
        }
        if (empty($data['account_bank'])) {
            throw new Exception('收款账号必填');
        }
        if (empty($data['account_name'])) {
            throw new Exception('开户名必填');
        }
        //防止重复账户添加
        $map = array();
        $map['account_bank'] = $data['account_bank'];
        $map['user_id'] = $this->_seller['user_id'];
        if (isset($data['bank_id'])) $map['bank_id'] = array('neq',$data['bank_id']);
        if (get_count('user_bank',$map)>0){
            throw new Exception('该收款账户已存在');
        }

        return $data;
    }
    //删除银行信息
    public function del_bank(){
        try{
            $bank_id = I('bank_id');
            $map = array();
            $map['bank_id'] = $bank_id;
            $map['user_id'] = $this->_seller['user_id'];
            $res = M('user_bank')->where($map)->delete();
            if ($res === false) throw new Exception('删除失败');
            $res = array('status'=>1,'msg'=>'删除成功');
        }catch(Exception $e){
            $res = array('status'=>0,'msg'=>$e->getMessage());
        }
        $this->ajaxReturn($res,'json');die;
    }
    //endregion

}