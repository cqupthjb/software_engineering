<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */

namespace App\Controller;
class PaymentController extends MobileBaseController
{

    public $payment; //  具体的支付类
    public $pay_code; //  具体的支付code

    /**
     * 析构流函数
     */
    public function __construct()
    {
        parent::__construct();
        // tpshop 订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];
        if (!empty($pay_radio)) {
            $pay_radio = parse_url_param($pay_radio);
            $this->pay_code = $pay_radio['pay_code']; // 支付 code
        } else // 第三方 支付商返回
        {
            $_GET = I('get.');
            //file_put_contents('./a.html',$_GET,FILE_APPEND);    
            //$this->pay_code = I('get.pay_code');
            $this->pay_code = I('pay_code');
            unset($_GET['pay_code']); // 用完之后删除, 以免进入签名判断里面去 导致错误
        }
        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        // 导入具体的支付类文件

        $_type = I('type');
        if (empty($_type) || $_type !='app'){
            include_once "plugins/payment/{$this->pay_code}/{$this->pay_code}.class.php"; // D:\wamp\www\svn_tpshop\www\plugins\payment\alipay\alipayPayment.class.php
            $code = '\\' . $this->pay_code; // \alipay
            $this->payment = new $code();
        }
    }

    /**
     * tpshop 提交支付方式
     */
    public function getCode()
    {

        C('TOKEN_ON', false); // 关闭 TOKEN_ON
        header("Content-type:text/html;charset=utf-8");
        // 修改订单的支付方式
        $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
        $order_id = I('order_id', 0); // 订单id
        $master_order_sn = I('master_order_sn', '');// 多单付款的 主单号

        // 如果是主订单号过来的, 说明可能是合并付款的
        if ($master_order_sn) {
            M('order')->where("master_order_sn = '$master_order_sn'")->save(array('pay_code' => $this->pay_code, 'pay_name' => $payment_arr[$this->pay_code]));
            $order = M('order')->where("master_order_sn = '$master_order_sn'")->find();
            $order['order_sn'] = $master_order_sn; // 临时修改 给支付宝那边去支付
            $order['order_amount'] = M('order')->where("master_order_sn = '$master_order_sn'")->sum('order_amount'); // 临时修改 给支付宝那边去支付
        } else {
            M('order')->where("order_id = $order_id")->save(array('pay_code' => $this->pay_code, 'pay_name' => $payment_arr[$this->pay_code]));
            $order = M('order')->where("order_id = $order_id")->find();
        }
        if ($order['pay_status'] == 1) {
            $this->error('此订单，已完成支付!');
        }
        // tpshop 订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];
        $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
        $code_str = $this->payment->get_code($order, $config_value);
        //微信JS支付
        if ($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $code_str = $this->payment->getJSAPI($order, $config_value);
            exit($code_str);
        }
        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);
        $this->assign('master_order_sn', $master_order_sn); // 主订单号
        $this->display('payment');  // 分跳转 和不 跳转
    }

    public function getPay()
    {
        //手机端在线充值
        C('TOKEN_ON', false); // 关闭 TOKEN_ON
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id'); //订单id
        $user = session('user');
        $data['account'] = I('account');
        $_type = I('type');
        //region折扣计算
        $recharge_rate = M('config')->where(array('name'=>'recharge_rate','inc_type'=>'basic'))->getField('value');
        if (empty($recharge_rate)) $recharge_rate = 100;

        //实际支付
        $data['need_pay'] = round($data['account'] * ($recharge_rate / 100 ),2);
        //endregion折扣计算

        if ($order_id > 0) {
            M('recharge')->where(array('order_id' => $order_id, 'user_id' => $user['user_id']))->save($data);
        } else {
            $data['user_id'] = $user['user_id'];
            $data['nickname'] = $user['nickname'];
            $data['order_sn'] = 'recharge' . get_rand_str(10, 0, 1);
            $data['ctime'] = time();
            $order_id = M('recharge')->add($data);
        }
        if ($order_id) {
            $order = M('recharge')->where("order_id = $order_id")->find();
            if (is_array($order) && $order['pay_status'] == 0) {
                $order['order_amount'] = $order['need_pay']; //$order['account']; //折扣金额
                $pay_radio = $_REQUEST['pay_radio'];
                $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
                $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
                M('recharge')->where("order_id = $order_id")->save(array('pay_code' => $this->pay_code, 'pay_name' => $payment_arr[$this->pay_code]));
                //微信JS支付
                if ($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
                    $code_str = $this->payment->getJSAPI($order);
                    exit($code_str);
                } else if(strtolower($_type) == 'app'){
                    $this->ajaxReturn(array('status'=>1,'msg'=>'success','order_sn'=>$order['order_sn']));die;
                }else{
                    $code_str = $this->payment->get_code($order, $config_value);
                }
            } else {
                $this->error('此充值订单，已完成支付!');
            }
        } else {
            $this->error('提交失败,参数有误!');
        }
        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);
        $this->display('recharge'); //分跳转 和不 跳转
    }

    // 服务器点对点 // http://www.tp-shop.cn/index.php/Home/Payment/notifyUrl
    public function notifyUrl()
    {
        $this->payment->response();
        exit();
    }

    // 页面跳转 // http://www.tp-shop.cn/index.php/Home/Payment/returnUrl
    public function returnUrl()
    {
        $result = $this->payment->respond2(); // $result['order_sn'] = '201512241425288593';
        if (stripos($result['order_sn'], 'recharge') !== false) {
            $order = M('recharge')->where("order_sn = '{$result['order_sn']}'")->find();
            $this->assign('order', $order);
            if ($result['status'] == 1)
                $this->display('recharge_success');
            else
                $this->display('recharge_error');
            exit();
        }
        if (stripos($result['order_sn'], 'sc') !== false) {
            $order = M('scan_order')->where("order_sn = '{$result['order_sn']}'")->find();
            $this->assign('order', $order);
            if ($result['status'] == 1)
                $this->display('scan_pay_success');
            else
                $this->display('scan_pay_error');
            exit();
        }
        // 先查看一下 是不是 合并支付的主订单号
        $sum_order_amount = M('order')->where("master_order_sn = '{$result['order_sn']}'")->sum('order_amount');
        if ($sum_order_amount) {
            $this->assign('master_order_sn', $result['order_sn']); // 主订单号
            $this->assign('sum_order_amount', $sum_order_amount); // 所有订单应付金额
        } else {
            $order = M('order')->where("order_sn = '{$result['order_sn']}'")->find();
            $this->assign('order', $order);
        }

        if ($result['status'] == 1)
            $this->display('success');
        else
            $this->display('error');
    }


    //自定义二维码支付
    public function get_scan_pay()
    {
        //手机端在线充值
        C('TOKEN_ON', true); // 开启 TOKEN_ON
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id',0); //订单id

        $store = session('_store');
        $_type = I('type');
        //token验证 防止重复
//        if (!M()->autoCheckToken(I('post.'))){
//            $this->error('请刷新重试',U('User/scan_pay',array('store_id'=>$store['store_id'])));die;
//        }

        $user = session('user');
        $user = M('users')->where(array('user_id'=>$user['user_id']))->find();//更新用户信息

        if (empty($user['first_leader']) && in_array($store['role_id'],array(5,0))) {
            //成为下线
            $user['first_leader'] = $store['user_id'];
            M('users')->save($user);
        }

        $data['account'] = I('account');
        //region折扣计算
        //$recharge_rate = M('config')->where(array('name'=>'recharge_rate','inc_type'=>'basic'))->getField('value');
        //if (empty($recharge_rate)) $recharge_rate = 100;

        //实际支付
        //$data['need_pay'] = round($data['account'] * ($recharge_rate / 100 ),2);
        $data['need_pay'] = $data['account'];

        //endregion折扣计算
        if ($order_id > 0) {
            M('scan_order')->where(array('order_id' => $order_id, 'user_id' => $user['user_id'],'store_id'=>$store['store_id']))->save($data);
        } else {
            $data['user_id']  = $user['user_id'];
            $data['store_id'] = $store['store_id'];
            $data['nickname'] = $user['nickname'];
            $data['order_sn'] = build_order_no('sc');
            $data['ctime'] = time();
            $order_id = M('scan_order')->add($data);
        }
        if ($order_id) {
            $order = M('scan_order')->where("order_id = $order_id")->find();
            //region 分销
                //if(file_exists(APP_PATH.'Common/Logic/DistributLogic.class.php')) {
                $distributLogic = new \Common\Logic\DistributLogic();
                //$distributLogic->rebate_log($order); // 生成分成记录
                $distributLogic->rebate_log_scan($order); // 生成分成记录  记得分成表添加个字段区别订单类型
            //}
            //endregion
            if (is_array($order) && $order['pay_status'] == 0) {
                $order['order_amount'] = $order['need_pay']; //$order['account']; //折扣金额
                $pay_radio = $_REQUEST['pay_radio'];
                $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
                $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
                M('scan_order')->where("order_id = $order_id")->save(array('pay_code' => $this->pay_code, 'pay_name' => $payment_arr[$this->pay_code]));
                //微信JS支付
                if ($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
                    $code_str = $this->payment->getJSAPI($order);
                    exit($code_str);
                } else if(strtolower($_type) == 'app'){
                    $this->ajaxReturn(array('status'=>1,'msg'=>'success','order_sn'=>$order['order_sn']));die;
                } else {
                    $code_str = $this->payment->get_code($order, $config_value);
                }
            } else {
                $this->error('此充值订单，已完成支付!');
            }
        } else {
            $this->error('提交失败,参数有误!');
        }
        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);
        $this->display('scan_pay'); //分跳转 和不 跳转
    }

}
