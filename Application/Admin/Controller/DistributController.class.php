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
 *
 * Date: 2016-03-09
 */

namespace Admin\Controller;

use Think\Page;

class DistributController extends BaseController
{

    /*
     * 初始化操作
     */
    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 分销树状关系
     */
    public function tree()
    {
        //默认省级
        $map = array();
        $map['first_leader']    = 0;
        $map['role_id']         = 1;

        $user_id = I('user_id',0);
        if ($user_id)
        {
            $map = array();
            $map['user_id'] = $user_id;
        }

        $list = M('users')->where($map)->select();
        $this->assign('list', $list);
        $this->display();
    }
    public function tree2()
    {
        //默认用户
        $map = array();
        $map['first_leader']    = 0;
        $map['role_id']         = 0;

        $user_id = I('user_id',0);
        if ($user_id)
        {
            $map = array();
            $map['user_id'] = $user_id;
        }

        $list = M('users')->where($map)->select();
        $this->assign('list', $list);
        $this->display('tree');
    }
    /*public function tree()
    {
        $where = 'is_distribut = 1 and first_leader = 0';

        if ($_POST['user_id'])
            $where = "user_id = '{$_POST['user_id']}'";

        $list = M('users')->where($where)->select();
        $this->assign('list', $list);
        $this->display();
    }*/


    /**
     * 分销商列表
     */
    public function distributor_list()
    {
        $condition['is_distribut'] = 1;
        $nickname = I('nickname');
        if (!empty($nickname)) {
            $condition['nickname'] = array('like', "%$nickname%");
        }
        $count = M('users')->where($condition)->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $user_list = M('users')->where($condition)->order('distribut_money DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($user_list as $k => $val) {
            $user_list[$k]['fisrt_leader'] = M('users')->where(array('first_leader' => $val['user_id']))->count();
            $user_list[$k]['second_leader'] = M('users')->where(array('second_leader' => $val['user_id']))->count();
            $user_list[$k]['third_leader'] = M('users')->where(array('third_leader' => $val['user_id']))->count();
            $user_list[$k]['lower_sum'] = $user_list[$k]['fisrt_leader'] + $user_list[$k]['second_leader'] + $user_list[$k]['third_leader'];
        }
        $this->assign('page', $show);
        $this->assign('user_list', $user_list);
        $this->display();
    }

    /**
     * 分销设置
     */
    public function set()
    {
        header("Location:" . U('Admin/System/index', array('inc_type' => 'distribut')));
        exit;
    }

//    /**
//     *  转账汇款记录
//     */
//    public function remittance(){
//        $model = M("remittance");
//        $_GET = array_merge($_GET,$_POST);
//        unset($_GET['create_time']);
//
//        $status = I('status');
//        $user_id = I('user_id');
//        $account_bank = I('account_bank');
//        $account_name = I('account_name');
//
//        $create_time = I('create_time');
//        $create_time = $create_time  ? $create_time  : date('Y/m/d',strtotime('-1 year')).'-'.date('Y/m/d',strtotime('+1 day'));
//        $create_time2 = explode('-',$create_time);
//        $where = " create_time >= '".strtotime($create_time2[0])."' and create_time <= '".strtotime($create_time2[1])."' ";
//        $user_id && $where .= " and user_id = $user_id ";
//        $account_bank && $where .= " and account_bank like '%$account_bank%' ";
//        $account_name && $where .= " and account_name like '%$account_name%' ";
//
//        $count = $model->where($where)->count();
//        $Page  = new Page($count,16);
//        $list = $model->where($where)->order("`id` desc")->limit($Page->firstRow.','.$Page->listRows)->select();
//
//        $this->assign('create_time',$create_time);
//        $show  = $Page->show();
//        $this->assign('show',$show);
//        $this->assign('list',$list);
//        C('TOKEN_ON',false);
//        $this->display();
//    }

    /**
     * 分成记录
     */
    public function rebate_log()
    {
        $model = M("rebate_log");
        $status = I('status');
        $user_id = I('user_id');
        $order_sn = I('order_sn');
        $create_time = I('create_time');
        $create_time = $create_time ? $create_time : date('Y-m-d', strtotime('-1 year')) . ' - ' . date('Y-m-d', strtotime('+1 day'));

        $create_time2 = explode(' - ', $create_time);
        $where = " create_time >= '" . strtotime($create_time2[0]) . "' and create_time <= '" . strtotime($create_time2[1]) . "' ";

        if ($status === '0' || $status > 0)
            $where .= " and status = $status ";
        $user_id && $where .= " and user_id = $user_id ";
        $order_sn && $where .= " and order_sn like '%{$order_sn}%' ";

        $count = $model->where($where)->count();
        $Page = new Page($count, 16);
        $list = $model->where($where)->order("`id` desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $get_user_id = get_arr_column($list, 'user_id'); // 获佣用户
        $buy_user_id = get_arr_column($list, 'user_id'); // 购买用户
        $user_id_arr = array_merge($get_user_id, $buy_user_id);
        if (!empty($user_id_arr))
            $user_arr = M('users')->where("user_id in (" . implode(',', $user_id_arr) . ")")->select();
        $this->assign('user_arr', $user_arr);

        $this->assign('create_time', $create_time);
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 获取某个人下级元素
     */
    public function ajax_lower()
    {
        $list = M('users')->where("first_leader =" . $_GET[id])->select();
        $this->assign('list', $list);
        $this->display();
    }

    /**
     * 修改编辑 分成
     */
    public function editRebate()
    {
        $id = I('id');
        $model = M("rebate_log");
        $rebate_log = $model->find($id);
        if (IS_POST) {
            $model->create();

            // 如果是确定分成 将金额打入分佣用户余额
            if ($model->status == 3 && $rebate_log['status'] != 3) {
                accountLog($model->user_id, $rebate_log['money'], 0, "订单:{$rebate_log['order_sn']}分佣", $rebate_log['money']);
            }
            $model->save();
            $this->success("操作成功!!!", U('Admin/Distribut/rebate_log'));
            exit;
        }

        $user = M('users')->where("user_id = {$rebate_log[user_id]}")->find();

        if ($user['nickname'])
            $rebate_log['user_name'] = $user['nickname'];
        elseif ($user['email'])
            $rebate_log['user_name'] = $user['email'];
        elseif ($user['mobile'])
            $rebate_log['user_name'] = $user['mobile'];

        $this->assign('user', $user);
        $this->assign('rebate_log', $rebate_log);
        $this->display();
    }

    /**
     * 提现申请记录
     */
    public function withdrawals()
    {
        $model = M("withdrawals");
        $_GET = array_merge($_GET, $_POST);
        unset($_GET['create_time']);

        $status = I('status');
        $user_id = I('user_id');
        $account_bank = I('account_bank');
        $account_name = I('account_name');
        $create_time = I('create_time');
        $create_time = $create_time ? $create_time : date('Y/m/d', strtotime('-1 year')) . '-' . date('Y/m/d', strtotime('+1 day'));
        $create_time2 = explode('-', $create_time);
        $where = " create_time >= '" . strtotime($create_time2[0]) . "' and create_time <= '" . strtotime($create_time2[1]) . "' ";

        if ($status === '0' || $status > 0)
            $where .= " and status = $status ";
        $user_id && $where .= " and user_id = $user_id ";
        $account_bank && $where .= " and account_bank like '%$account_bank%' ";
        $account_name && $where .= " and account_name like '%$account_name%' ";

        $count = $model->where($where)->count();
        $Page = new Page($count, 16);
        $list = $model->where($where)->order("`id` desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('create_time', $create_time);
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $model = M("withdrawals");
        $model->where('id =' . $_GET['id'])->delete();
        $return_arr = array('status' => 1, 'msg' => '操作成功', 'data' => '',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        $this->ajaxReturn(json_encode($return_arr));
    }

    /**
     * 修改编辑 申请提现
     */
    public function editWithdrawals()
    {
        $id = I('id');
        $model = M("withdrawals");
        $withdrawals = $model->find($id);
        $user = M('users')->where("user_id = {$withdrawals['user_id']}")->find();

        if (IS_POST) {
            $model->create();

            // 如果是已经给用户转账 则生成转账流水记录
            if ($model->status == 1 && $withdrawals['status'] != 1) {
                if ($user['user_money'] < $withdrawals['money']) {
                    $this->error("用户余额不足{$withdrawals['money']}，不够提现");
                    exit;
                }

                /*
                  $resinfo=     M('users')->where(array("user_id"=>$withdrawals['user_id']))->find();
                  $code=	'AD'.uniqid().$withdrawals['user_id'];
                  $arr = array(
                           "mch_appid" => "wxae651a4c2bdfc519", //公众号APPID
                           "mchid" => "1480394892", //商户号ID
                           "key" => "IaGI1gyJe2r0nsXx8jWwCEaqmvB8HjAZ",
                           "partner_trade_no" => $code, //商户订单号
                           "openid" => $resinfo['openid'], //用户的openid
                           "check_name" => "OPTION_CHECK",  //校验用户姓名选项
                           "re_user_name" => $withdrawals['account_name'], //收款用户姓名
                           "amount" => $withdrawals['money']*100, //金额
                           "desc" => "用户提现", // 企业付款描述信息
                   );

                   $wxpay = new WxTransfers($arr);
                   $xml = $wxpay->setParam();
                   $info = $wxpay->ssl_post($xml);
                   P($info);
                   if($info["status"] == 1){
                       //$this->success('提现成功！');
                   }else{
                       $this->error($info["error"]);  exit;
                   }*/

                accountLog($withdrawals['user_id'], ($withdrawals['money'] * -1), 0, "平台提现");
                $remittance = array(
                    'user_id' => $withdrawals['user_id'],
                    'bank_name' => $withdrawals['bank_name'],
                    'account_bank' => $withdrawals['account_bank'],
                    'account_name' => $withdrawals['account_name'],
                    'money' => $withdrawals['money'],
                    'status' => 1,
                    'create_time' => time(),
                    'admin_id' => session('admin_id'),
                    'withdrawals_id' => $withdrawals['id'],
                    'remark' => $model->remark,
                );
                M('remittance')->add($remittance);
            }
            $info = $model->save();
            if ($info) {
                //$user_news = D('UserNews')->OrderNews($id);
                //重新获取信息
                $withdrawals = $model->find($withdrawals['id']);
                if ($withdrawals['status'] == '1') {//1申请成功
                    $content = '您有条提现订单已提现';
                } elseif ($withdrawals['status'] == '2') {//2申请失败
                    $content = '您有条提现订单提现失败';
                }
                //消息记录
                save_user_news($withdrawals['user_id'],1,$withdrawals['id'],$content);
            }

            $this->success("操作成功!", U('Admin/Distribut/remittance'), 3);
            exit;
        }


        if ($user['nickname'])
            $withdrawals['user_name'] = $user['nickname'];
        elseif ($user['email'])
            $withdrawals['user_name'] = $user['email'];
        elseif ($user['mobile'])
            $withdrawals['user_name'] = $user['mobile'];

        $this->assign('user', $user);
        $this->assign('data', $withdrawals);
        $this->display();
    }


}