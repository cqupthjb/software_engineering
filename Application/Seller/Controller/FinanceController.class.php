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

namespace Seller\Controller;

use Think\Page;
use Common\Logic\Withdraw;
use Seller\Logic\OrderLogic;

class FinanceController extends BaseController
{

    /*
     * 初始化操作
     */
    public function _initialize()
    {
        parent::_initialize();
    }


    /**
     *  转账汇款记录
     */
    public function remittance()
    {
        $model = M("store_remittance");
        $_GET = array_merge($_GET, $_POST);
        unset($_GET['create_time']);

        $user_id = I('user_id');
        $account_bank = I('account_bank');
        $account_name = I('account_name');

        $create_time = I('create_time');
        $create_time = str_replace("+", " ", $create_time);
        $create_time = $create_time ? $create_time : date('Y-m-d', strtotime('-1 year')) . ' - ' . date('Y-m-d', strtotime('+1 day'));
        $create_time2 = explode(' - ', $create_time);
        $where = "store_id = " . STORE_ID . " and create_time >= '" . strtotime($create_time2[0]) . "' and create_time <= '" . strtotime($create_time2[1]) . "' ";
        $user_id && $where .= " and user_id = $user_id ";
        $account_bank && $where .= " and account_bank like '%$account_bank%' ";
        $account_name && $where .= " and account_name like '%$account_name%' ";

        $count = $model->where($where)->count();
        $Page = new Page($count, 2);
        $list = $model->where($where)->order("`id` desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('create_time', $create_time);
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        C('TOKEN_ON', false);
        $this->display();
    }


    /**
     * 提现申请记录
     */
    public function withdrawals()
    {
        $model = M("store_withdrawals");
        $status = I('status');
        $account_bank = I('account_bank');
        $account_name = I('account_name');
        $create_time = I('create_time');
        $create_time = str_replace("+", " ", $create_time);
        $create_time = $create_time ? $create_time : date('Y-m-d', strtotime('-1 year')) . ' - ' . date('Y-m-d', strtotime('+1 day'));
        $create_time2 = explode(' - ', $create_time);
        $where = "store_id = " . STORE_ID . " and create_time >= '" . strtotime($create_time2[0]) . "' and create_time <= '" . strtotime($create_time2[1]) . "' ";

        if ($status === '0' || $status > 0)
            $where .= " and status = $status ";
        $account_bank && $where .= " and account_bank like '%$account_bank%' ";
        $account_name && $where .= " and account_name like '%$account_name%' ";

        $count = $model->where($where)->count();
        $Page = new Page($count, 16);
        $list = $model->where($where)->order("`id` desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $show = $Page->show();
        $store = M('store')->where("store_id = " . STORE_ID)->find();

        $this->assign('store', $store);
        $this->assign('create_time', $create_time);
        $this->assign('show', $show);
        $this->assign('list', $list);
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 添加提现申请
     */
    public function add_edit_withdrawals()
    {
        $id = I('id', 0);
        $id = $id ? $id : 0;
        $Model = M('StoreWithdrawals');
        $withdrawals = $Model->where("id = $id")->find();

        if (IS_POST) {
            if ($withdrawals['status'] == 1)
                $this->error('申请成功的不能再修改');
            $Model->create();

            $store = M('store')->where(array('store_id'=>$this->_store_id))->find();

            $store_max_withdraw_rate = M("config")->where(array("name" => "store_max_withdraw_rate"))->getField("value");

            if (empty($store_max_withdraw_rate) || $store_max_withdraw_rate > 100 || $store_max_withdraw_rate <= 0){
                $store_max_withdraw_rate = 100;
            }
            $store_max_money = ($store_max_withdraw_rate/100)*$store['store_money'];

            //最多提现金额
            if($Model->money > $store_max_money)
            {
                $this->error("你最多可提现{$this->_store['max_money']}账户余额.");
                exit;
            }

            if($Model->money > $store['store_money'])
            {
                $this->error("你最多可提现{$this->_store['store_money']}账户余额.");
                exit;
            }

            //每天提现上限
            $maxWithdrawADay = M("config")->where(array("name"=>"store_max_withdraw_money"))->getField("value");

            if($Model->money > $maxWithdrawADay)
            {
                $this->error("每天只可提现{$maxWithdrawADay}元.");
                exit;
            }

            if (!empty($maxWithdrawADay)){
                $today = date('Y-m-d');
                $time = strtotime($today);
                $where = array('store_id'=>$this->_store_id,'status'=>array("in",'0,1'),'create_time'=>array('gt',$time));
                $money = M("store_withdrawals")->where($where)->sum("money");
                if ($money >= $maxWithdrawADay){
                    $this->error('每天只可申请提现'.$maxWithdrawADay.'元');
                    exit;
                }
            }

            if ($Model->id) {
                $Model->save();
            } else {
                $Model->store_id = STORE_ID; //
                $Model->create_time = time();
                $Model->order_sn = build_order_no();
                $withdrawals_id = $Model->add();
                if($withdrawals_id!==false){
                    $withdraw = new Withdraw();
                    $re = $withdraw->store_withdraw($withdrawals_id);
                    if ($re['success']){
                        $this->success($re['msg'],U('withdrawals'));
                    } else {
                        M('store_withdrawals')->where(array('id'=>$withdrawals_id))->delete();
                        $this->error($re['msg']);
                    }
                    exit;
                }else{
                    $this->error('提交失败,联系客服!');
                    exit;
                }

            }
            $this->success('保存完成', U('withdrawals'));
            exit();
        }

        //region 银行信息列表
        $bank_list = M('user_bank')->where(array('user_id' => $this->_seller['user_id']))->select();
        $this->assign('bank_list', []);
        $this->assign('json_bank_list',json_encode(array()));
        if (!empty($bank_list)) {
            $this->assign('bank_list', $bank_list);
            $this->assign('json_bank_list', json_encode($bank_list));
        }
        //endregion

        $withdrawals_max = M('store')->where(array('store_id' => STORE_ID))->getField('store_money');
        $withdrawals_min = tpCache('basic.min');
        $this->assign('withdrawals_max', $withdrawals_max);
        $this->assign('withdrawals_min', empty($withdrawals_min)?0:$withdrawals_min);
        $this->assign('withdrawals', $withdrawals);
        $this->display('_withdrawals');
    }

    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $id = I('id');
        $model = M("StoreWithdrawals");
        $model->where("id = $id and store_id =" . STORE_ID . " and status != 1")->delete();
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
        $user = M('users')->where("user_id = {$withdrawals[user_id]}")->find();

        if (IS_POST) {
            $model->create();

            // 如果是已经给用户转账 则生成转账流水记录
            if ($model->status == 1 && $withdrawals['status'] != 1) {
                if ($user['user_money'] < $withdrawals['money']) {
                    $this->error("用户余额不足{$withdrawals['money']}，不够提现");
                    exit;
                }
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
            $model->save();
            $this->success("操作成功!", U('Admin/Finance/remittance'), 3);
            exit;
        }

        if ($user['nickname'])
            $withdrawals['user_name'] = $user['nickname'];
        elseif ($user['email'])
            $withdrawals['user_name'] = $user['email'];
        elseif ($user['mobile'])
            $withdrawals['user_name'] = $user['mobile'];

        //region 银行信息列表
        $bank_list = M('user_bank')->where(array('user_id' => $this->_seller['user_id']))->select();
        if (!empty($bank_list)) {
            $this->assign('bank_list', $bank_list);
            $this->assign('json_bank_list', json_encode($bank_list));
        }
        //endregion

        $this->assign('user', $user);
        $this->assign('data', $withdrawals);
        $this->display();
    }

    /**
     *  商家结算记录
     */
    public function order_statis()
    {
        $model = M("order_statis");
        $create_date = I('create_date');
        $create_date = str_replace("+", " ", $create_date);
        $create_date2 = $create_date ? $create_date : date('Y-m-d', strtotime('-1 month')) . ' - ' . date('Y-m-d', strtotime('+1 month'));
        $create_date3 = explode(' - ', $create_date2);
        $where = "store_id = " . STORE_ID . " and create_date >= '" . strtotime($create_date3[0]) . "' and create_date <= '" . strtotime($create_date3[1]) . "' ";

        $count = $model->where($where)->count();
        $Page = new Page($count, 16);
        $list = $model->where($where)->order("`id` desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('create_date', $create_date2);
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        C('TOKEN_ON', false);
        $this->display();
    }

    public function order_no_statis()
    {
        $create_date = I('create_date');
        $create_date = str_replace("+", " ", $create_date);
        $create_date2 = $create_date ? $create_date : date('Y-m-d', strtotime('-1 month')) . ' - ' . date('Y-m-d', strtotime('+1 month'));
        $create_date3 = explode(' - ', $create_date2);
        $where = array(
            'store_id' => STORE_ID,
            'pay_status' => 1,
            'add_time' => array(array('gt', strtotime($create_date3[0])), array('lt', strtotime($create_date3[1]))),
            'is_checkout' => 0
        );
        $model = M('order');
        $count = $model->where($where)->count();
        $Page = new Page($count, 16);
        $list = $model->where($where)->order("`add_time` desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('create_date', $create_date2);
        $show = $Page->show();
        $order_status = C('ORDER_STATUS');
        $shipping_status = C('SHIPPING_STATUS');
        $this->assign('shipping_status', $shipping_status);
        $this->assign('order_status', $order_status);
        $this->assign('show', $show);
        $this->assign('list', $list);
        C('TOKEN_ON', false);
        $this->display();
    }

    /**
     * 二维码收款统计
     */
    public function qrcodeSum(){
        if(I('pay_code') != 'all'){
            $condition['pay_code'] = I('pay_code');
        }
        $condition['store_id'] = STORE_ID;
        $condition['pay_status'] = 1;
        $orderList = M('scan_order')->where($condition)->select();
        $this->assign('orderList', $orderList);
        $this->assign('pay_code', I("pay_code"));
        $this->display();
    }

    // /**
    //  * 导出列表
    //  */
    // public function export_scan_order()
    // {
    //     //搜索条件
    //     $where = "where store_id =" . STORE_ID . " ";

    //     $order_sn = I('order_sn');
    //     if ($order_sn) {
    //         $where .= " AND order_sn = '$order_sn' ";
    //     }
    //     if (I('pay_status')) {
    //         $where .= " AND pay_status = " . I('pay_status');
    //     }

    //     $timegap = I('timegap');
    //     if ($timegap) {
    //         $gap = explode('-', $timegap);
    //         $begin = strtotime($gap[0]);
    //         $end = strtotime($gap[1]);
    //         $where .= " AND ctime>$begin and ctime<$end";
    //     }
    //     $region = M('region')->getField('id,name');

    //     $sql = "select *,FROM_UNIXTIME(ctime,'%Y-%m-%d') as create_time from __PREFIX__scan_order $where order by order_id";
    //     $orderList = D()->query($sql);
    //     $strTable = '<table width="500" border="1">';
    //     $strTable .= '<tr>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="100">付款ID</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="*">付款昵称</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收款金额</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付方式</td>';
    //     $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收款时间</td>';
    //     $strTable .= '</tr>';

    //     foreach ($orderList as $k => $val) {
    //         $strTable .= '<tr>';
    //         $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
    //         $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_id'] . '</td>';
    //         $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['nickname'] . '</td>';
    //         $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['account'] . '</td>';
    //         $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['pay_name'] . '</td>';
    //         $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['create_time'] . ' </td>';
    //         $strTable .= '</tr>';
    //     }
    //     $strTable .= '</table>';
    //     unset($orderList);
    //     downloadExcel($strTable, 'scan_order');
    //     exit();
    // }
}