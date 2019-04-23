<?php

namespace Common\Logic;


use Common\Server\WechatPayServer;

class Withdraw
{


    public function user_withdraw($applyId)
    {
        $id = $applyId;
        $model = M("withdrawals");
        $withdrawals = $model->find($id);
        $user = M('users')->where("user_id = {$withdrawals['user_id']}")->find();
        $model->create();
        $model->status = 1;
        $model->remark = '通过';
        $model->id = $applyId;
        $model->user_id = $user['user_id'];
        // 如果是已经给用户转账 则生成转账流水记录
        if ($model->status == 1 && $withdrawals['status'] != 1) {
            if ($user['user_money'] < $withdrawals['money']) {
                return array('success' => false, 'msg' => "用户余额不足{$withdrawals['money']}，不够提现");
            }

            //region 微信企业转账 - 零钱
            {
                $user = M('users')->where(array('user_id' => $withdrawals['user_id']))->find();
                if (empty($user['openid'])) {
                    return array('success' => false, 'msg' => "用户OPENID缺省，无法通过微信转账到零钱");
                }
                $config = M('wx_user')->find(); //获取微信配置;
                $data = array(
                    'key' => 'IaGI1gyJe2r0nsXx8jWwCEaqmvB8HjAZ',//$config['paykey'],//商户支付秘钥
                    'mchid' => 1480394892,//$config['mchid'],//商户号
                    'appid' => $config['appid'],//APPID
                    'secret' => $config['appsecret'],//SECRET
                );
                $wechatPayServer = new WechatPayServer($data);
                $_data = [
                    'openid' => $user['openid'],
                    //'desc'                => '开发测试',//描述
                    'desc' => '用户提现',//描述
                    'amount' => $withdrawals['money'],//付款金额
                    'partner_trade_no' => $withdrawals['order_sn'],//商户付款单号
                    'nonce_str' => md5(time()), //随机串
                ];
                try {
                    $res = $wechatPayServer->comPay($_data);
                    if ($res['result_code'] != 'SUCCESS') {
                        throw new \Exception($res['err_code_des']);
                    }
                } catch (\Exception $e) {
                    return array('success' => false, 'msg' => $e->getMessage());
                }

            }
            //endregion


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
        return array('success' => true, 'msg' => '操作成功');
    }

    public function store_withdraw($applyId)
    {
        $id = $applyId;
        $model = M("store_withdrawals");
        $withdrawals = $model->find($id);
        $store = M('store')->where("store_id = {$withdrawals[store_id]}")->find();
        $model->create();
        $model->status = 1;
        $model->remark = '通过';
        $model->id = $applyId;
        $model->user_id = $store['store_id'];
        // 如果是已经给店家转账 则生成转账流水记录
        if ($model->status == 1 && $withdrawals['status'] != 1) {
            if ($store['store_money'] < $withdrawals['money']) {
                return array('success' => false, 'msg' => "用户余额不足{$withdrawals['money']}，不够提现");
            }

            //region 微信企业转账 - 零钱
            {
                $user = M('users')->where(array('user_id' => $store['user_id']))->find();
                if (empty($user['openid'])) {
//                        $this->error("用户OPENID缺省，无法通过微信转账到零钱");
                    return array('success' => false, 'msg' => "用户OPENID缺省，无法通过微信转账到零钱");
                }
                $config = M('wx_user')->find(); //获取微信配置;
                $data = array(
                    'key' => 'IaGI1gyJe2r0nsXx8jWwCEaqmvB8HjAZ',//$config['paykey'],//商户支付秘钥
                    'mchid' => 1480394892,//$config['mchid'],//商户号
                    'appid' => $config['appid'],//APPID
                    'secret' => $config['appsecret'],//SECRET
                );
                $wechatPayServer = new WechatPayServer($data);
                $_data = [
                    'openid' => $user['openid'],
                    //'openid'              => 'oRHLf0RgtJFP8Fr7ynQnCunMne0c',
                    //'desc'                => '开发测试',//描述
                    'desc' => '商户提现',//描述
                    'amount' => $withdrawals['money'],//付款金额
                    'partner_trade_no' => $withdrawals['order_sn'],//商户付款单号
                    'nonce_str' => md5(time()), //随机串
                ];
                try {
                    $res = $wechatPayServer->comPay($_data);
                    if ($res['result_code'] != 'SUCCESS') {
                        throw new \Exception($res['err_code_des']);
                    }
                } catch (\Exception $e) {
//                        $this->ajaxReturn(array('info'=>$e->getMessage(),'status'=>0));
                    return array('success' => false, 'msg' => $e->getMessage());
                }

            }
            //endregion
            storeAccountLog($withdrawals['store_id'], ($withdrawals['money'] * -1), 0, $desc = '平台提现');
            $remittance = array(
                'store_id' => $withdrawals['store_id'],
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
            M('store_remittance')->add($remittance);
        }
        $model->save();
        return array('success' => true, 'msg' => '操作成功');
    }
//        $this->assign('store',$store);
//        $this->assign('data',$withdrawals);
//        $this->display();
}

