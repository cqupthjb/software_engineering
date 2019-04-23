<?php
/**
 * Created by PhpStorm.
 * User: yq
 * Date: 2017/10/13 0013
 * Time: 11:15
 */

namespace Mobile\Controller;


use Think\AjaxPage;
use Think\Exception;
use Think\Model;

class UserCardController extends MobileBaseController
{
    //我的会员卡
    public function index(){
        $status = I('status',1);//0：全部 1：激活充值
        if ($status){
            $map                = array();
            $map['log.user_id'] = $this->_uid;
            $card_list = $this->get_card_list($map,true);
            $this->assign('card_list',$card_list);
            if (IS_AJAX){
                $this->display('_my_card');die;
            }
            //$level_list = M('user_card_level')->select();
            //$this->assign('level_list',$level_list);
        }else{
            $map                = array();
            $map['user_id']     = $this->_uid;
            $log_list = $this->get_log_list($map,true);
            $this->assign('log_list',$log_list);
            if (IS_AJAX){
                $this->display('_log');die;
            }
        }
        $this->assign('status',$status);
        $this->display();
    }
    //日志信息
    protected function get_log_list($map,$is_page=true){
        //分页信息
        if ($is_page == true){
            $count = M('account_log_card')->where($map)->count();
            $Page  = new AjaxPage($count,10);
            $show = $Page->show();
            $this->assign('page',$show);// 赋值分页输出
        }

        //联合查询
        M('account_log_card')->where($map)->order('change_time desc');

        //利用thinkphp 联动特性
        if ($is_page == true){ //分页  首页需要
            M('account_log_card')->limit($Page->firstRow.','.$Page->listRows);
        }

        $card_list = M('account_log_card')->select();
        return $card_list;
    }

    //获取激活的会员卡信息
    protected function get_card_list($map,$is_page=true){
        $alias = 'log';
        $join = array('LEFT JOIN __USER_CARD__ c ON c.card_id = log.card_id','LEFT JOIN __USER_CARD_LEVEL__ level ON level.level_id = c.level_id');
        $field = 'c.card_no ,level.level_name,level.money,log.log_id,log.user_id,log.create_time';  //注意使用了count后需要使用group分组查询

        //分页信息
        if ($is_page == true){
            $count = M('user_card_log')->alias($alias)
                ->join($join)->where($map)->field($field)
                ->count();
            $Page  = new AjaxPage($count,10);
            $show = $Page->show();
            $this->assign('page',$show);// 赋值分页输出
        }

        //联合查询
        M('user_card_log')->alias($alias)
            ->join($join)->where($map)->field($field)
            ->order('log.create_time desc');

        //利用thinkphp 联动特性
        if ($is_page == true){ //分页  首页需要
            M('user_card_log')->limit($Page->firstRow.','.$Page->listRows);
        }

        $card_list = M('user_card_log')->select();
        return $card_list;
    }

    //激活会员卡
    public function use_card(){
        $r_data['status']  =1;
        try{
            $data = $this->_check_card();
            //$card = M('user_card')->where(array('card_no'=>$data['card_no'],'level_id'=>$data['level_id']))->find();

            $map                = array();
            $map['card_pwd']    = $data['card_pwd'];
            $map['status']      = 0;
            if (isset($data['card_no'])){
                $map['card_no'] = $data['card_no'];
            }

            $card = M('user_card')->where($map)->find();
            if (empty($card))
                throw new Exception('该卡已失效',0);
            if ($card['status'] != 0)
                throw new Exception('该卡已使用或失效',0);
//            if ($card['card_pwd'] != trim($data['card_pwd']))
//                throw new Exception('卡号或卡密不正确');
            $res = $this->_use_card($card);
            if ($res['status'] !=1 )
                throw new Exception($res['msg'],0);
                $res = array('status'=>1,'msg'=>'激活成功,金额已汇入充值金额中');
        }catch(Exception $e){
            $content = '';
            if ($e->getCode() == 400){
                $this->assign('card_pwd',I('post.card_pwd'));
                $content = $this->fetch('_card_no');
            }
            $res = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'content'=>$content);
        }
        $this->ajaxReturn($res);
    }

    //会员卡验证
    protected function _check_card(){
        //数据验证
        $data = I('post.');
//        if (empty($data['level_id']))
//            throw new Exception('请选择面值');
//        if (empty($data['card_no']))
//            throw new Exception('请输入会员卡号');
        if (empty($data['card_pwd']))
            throw new Exception('请输入卡密',0);
        if (get_count('user_card',array('card_pwd'=>$data['card_pwd'])) > 1){
            if (empty($data['card_no']))
                throw new Exception('该卡需输入卡号',400);
        }

        return $data;
    }

    //使用卡券
    protected function _use_card($card){
        try{
            $model = new Model();
            $model->startTrans();//开启事务
            //卡券金额

//            $money = $model->table(C('DB_PREFIX').'user_card_level')->where(array('level_id'=>$card['level_id']))->getField('money');

            $cardType = $model->table(C('DB_PREFIX').'user_card_level')->where(array('level_id'=>$card['level_id']))->find();

            $money = $cardType['money'];

            if ($money==15 || $money ==30){//金额是15元的卡 或者是30元的卡
                unset($map);
                $map['user_id'] = array('eq',$this->_uid);
                $user_info = M('Users')->where($map)->find();
                if (empty($user_info)){
                    throw new Exception('充值失败');
                }
                if ($user_info['is_active_card']==1){
                    throw new Exception('15元卡，30元卡只能充值一次');
                }
            } else  if($money == 100){
                $count = $model->table(C('DB_PREFIX').'user_card_log')->where(array('user_id'=>$this->_uid,'level_id'=>$cardType['level_id']))->count();
                if($count >=10) {
                    throw new Exception('100元卡只能充值10次');
                }
            }
            //添加日志
            $log = array(
                'user_id'   => $this->_uid,
                'card_id'   => $card['card_id'],
                'create_time'=> time(),
                'card_no'  =>  $card['card_no'],
                'card_pwd'  =>  $card['card_pwd'],
                'card_token'  =>  $card['card_token'],
                'level_id'  =>  $card['level_id'],
                'money'     =>  $money,
            );
            $res = $model->table(C('DB_PREFIX').'user_card_log')->add($log);
            if ($res === false){
                throw new Exception('日志添加失败');
            }
            //更改会员卡状态
            $info = array();
            $info['status'] = 1;
            $info['user_id'] = $log['user_id'];
            $info['use_time']= $log['create_time'];
            $res = $model->table(C('DB_PREFIX').'user_card')->where(array('card_id'=>$card['card_id']))->save($info);
            if ($res === false){
                throw new Exception('激活失败');
            }
            //增加余额
            //$money = $model->table(C('DB_PREFIX').'user_card_level')->where(array('level_id'=>$card['level_id']))->getField('money');
            $res = $model->table(C('DB_PREFIX').'users')->where(array('user_id'=>$this->_uid))->setInc('card_money',$money);
            if ($money==15 || $money == 30 || $money == 100){//金额是15元的卡 或者是30元的卡
                unset($map);
                $map['user_id'] = array('eq',$this->_uid);
                $data_user['is_active_card'] = 1;
                $data_user['active_card_money'] = $money;
                M('Users')->where($map)->save($data_user);
            }

            if ($res === false){
                throw new Exception('充值失败');
            }
            $model->commit();
            $account_log = array(
                'user_id' => $this->_uid,
                'user_money' => $money,
                'card_money' => $money,
                'pay_points' => 0,
                'frozen_money'=> 0 ,
                'change_time' => time(),
                'desc' => '会员卡'.$card['card_no'].':激活充值',
                'order_id' => 0
            );
            M('account_log_card')->add($account_log);
            $msg = '您于'.date('Y-m-d H:i:s',time()).'成功激活卡:'.$card['card_no'].'.成功充值:'.$money.'元';
            send_wx_msg($this->_user['openid'],$msg);
            if (!empty($this->_user['reg_id'])){
                $title = '会员卡消息';
                send_jpush_msg($this->_user['reg_id'] ,$title, $msg,'user_card');
            }
        }catch (Exception $e){
            $model->rollback();
            $r_data['status'] = 0;
            $r_data['msg'] = $e->getMessage();
            return $r_data;
        }
        $r_data['status'] = 1;
        return $r_data;
    }

}