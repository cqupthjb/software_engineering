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
 * 2015-11-21
 */

namespace App\Controller;

use Common\Controller\QrcodeController;
use Home\Logic\UsersLogic;
use Think\Page;
use Think\Verify;

class DistributController extends MobileBaseController
{
    public $_store = array();
    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        if (session('?user')) {
            $user = session('user');
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->_user = $user;
            $this->_uid = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
            //商户店铺
            $store = M('store')->where(array('user_id'=>$user['user_id']))->find();
            if (!empty($store)){
                $this->_store = $store;
            }
            $this->assign('store',$this->_store);
        }

        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code',
        );
        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {
            header("location:" . U('User/login'));
            exit;
        }

        $order_count = M('order')->where(array("user_id" => $this->user['user_id']))->count(); // 我的订单数

        $goods_collect_count = M('goods_collect')->where(array("user_id" => $this->user['user_id']))->count(); // 我的商品收藏
        $comment_count = M('comment')->where(array("user_id" => $this->user['user_id']))->count();//  我的评论数
        $coupon_count = M('coupon_list')->where(array("uid" => $this->user['user_id']))->count(); // 我的优惠券数量

        $first_nickname = M('users')->where(array("user_id" => $this->user['first_leader']))->getField('nickname');


        //$level_name = M('user_level')->where(array("level_id" => $this->user['level']))->getField('level_name'); // 等级名称
        $level_name = '会员ID:'.$this->_uid;
        // P($this->user);

        $this->assign('level_name', $level_name);
        $this->assign('first_nickname', $first_nickname);
        $this->assign('order_count', $order_count);
        $this->assign('goods_collect_count', $goods_collect_count);
        $this->assign('comment_count', $comment_count);
        $this->assign('coupon_count', $coupon_count);
    }

    /**
     * 分销用户中心首页
     */
    public function index()
    {
        // 销售额 和 我的奖励
        $zmoney = M('RebateLog')->where(array('user_id' => $this->user_id))->sum('money'); //返利总金额
        if (empty($zmoney) || $zmoney == '0') {
            $zmoney = '0.00';
        }
        //  $result = M()->query("select sum(goods_price) as goods_price, sum(money) as money from __PREFIX__rebate_log where is_reward=1 and user_id = {$this->user_id}");
        $result = M()->query("select sum(goods_price) as goods_price, sum(money) as money from __PREFIX__rebate_log where  user_id = {$this->user_id}");
        $result = $result[0];
        $result['goods_price'] = $result['goods_price'] ? $result['goods_price'] : 0;
        $result['money'] = $result['money'] ? $result['money'] : 0;


        //$lower_count[1] = M('users')->where("first_leader = {$this->user_id}")->count();
        //$lower_count[2] = M('users')->where("second_leader = {$this->user_id}")->count();
        //$lower_count[3] = M('users')->where("third_leader = {$this->user_id}")->count();

        //下级人数
        $map = array("first_leader" => $this->_uid);
        $lower_count[1] = M('users')->where($map)->count();
        $first_uid = M('users')->where($map)->field('user_id')->select();
        $first_uid = arr2_2_str($first_uid);//二维数组转字符串
        if (empty($first_uid) || $first_uid == false) {
            $lower_count[2] = 0;
            $lower_count[3] = 0;
        } else {
            $map = array('first_leader' => array('in', $first_uid));
            $lower_count[2] = M('users')->where($map)->count();

            $second_uid = M('users')->where($map)->field('user_id')->select();
            $second_uid = arr2_2_str($second_uid);//二维数组转字符串
            if (empty($second_uid) || $second_uid == false) {
                $lower_count[3] = 0;
            } else {
                $map = array('first_leader' => array('in', $second_uid));
                $lower_count[3] = M('users')->where($map)->count();
            }

        }

        // 我的下线 订单数
        $result2 = M()->query("select status,count(1) as c , sum(goods_price) as goods_price, sum(money) as money  from `__PREFIX__rebate_log` where user_id = {$this->user_id} group by status");

        $level_order = convert_arr_key($result2, 'status');
        for ($i = 0; $i < 5; $i++) {
            $level_order[$i]['c'] = $level_order[$i]['c'] ? $level_order[$i]['c'] : 0;
            $level_order[$i]['goods_price'] = $level_order[$i]['goods_price'] ? $level_order[$i]['goods_price'] : 0;
            $level_order[$i]['money'] = $level_order[$i]['money'] ? $level_order[$i]['money'] : 0;
        }
        //奖励金：
        $wherc['user_id'] = $this->user_id;
        $wherc['level'] = 3;
        $semoney = M('rebate_log')->where($wherc)->sum('money');
        $semoney = $semoney ? $semoney : "0";


        $withdrawals_money = M('withdrawals')->where("user_id = {$this->user_id} and `status` = 1")->sum('money');
        //print_r($level_order);
        $inc_type = 'distribut';
        $this->assign('tpshop_config', tpCache($inc_type));
        $this->assign('level_order', $level_order); // 下线订单
        $this->assign('semoney', $semoney); // 管理金
        $this->assign('lower_count', $lower_count); // 下线人数
        $this->assign('sales_volume', $result['goods_price']); // 销售额
        $this->assign('reward', $result['money']);// 奖励
        $this->assign('withdrawals_money', $withdrawals_money);// 已提现财富
        $this->assign('zmoney', $zmoney);
        $this->display();
    }

    /**
     * 下线列表
     */
    public function lower_list()
    {
        $level = I('get.level', 1);
        $q = I('post.q', '', 'trim');
        //$condition = array(1=>'first_leader',2=>'second_leader',3=>'third_leader');

        //$where = "{$condition[$level]} = {$this->user_id}";

        //条件组装
        $where = '';
        if ($level == 1) {
            //下级人数
            $where = 'first_leader=' . $this->user_id;
        }
        if ($level == 2) {
            $map = array("first_leader" => $this->user_id);
            $first_uid = M('users')->where($map)->field('user_id')->select();
            if (!empty($first_uid)){
                $first_uid = arr2_2_str($first_uid);//二维数组转字符串

                if (!empty($first_uid) && $first_uid !== false) {
                    $where = 'first_leader in(' . $first_uid . ') ';
                }
            }else{
                $this->assign('count', 0);// 总人数
                $this->display();die;
            }
        }

        if ($level == 3) {
            $map = array("first_leader" => $this->user_id);
            $first_uid = M('users')->where($map)->field('user_id')->select();
            $first_uid = arr2_2_str($first_uid);//二维数组转字符串
            if (!empty($first_uid) && $first_uid !== false) {
                $map = array('first_leader' => array('in', $first_uid));
                $second_uid = M('users')->where($map)->field('user_id')->select();
                $second_uid = arr2_2_str($second_uid);//二维数组转字符串
                if (!empty($second_uid) && $second_uid !== false) {
                    $where = 'first_leader in(' . $second_uid . ') ';
                }else{
                    $this->assign('count', 0);// 总人数
                    $this->display();die;
                }

            }else{
                $this->assign('count', 0);// 总人数
                $this->display();die;
            }
        }

        $q && $where .= " and (nickname like '%$q%' or user_id = '$q' or mobile = '$q')";
        $count = M('users')->where($where)->count();
        $page = new Page($count, 10);
        $list = M('users')->where($where)->limit("{$page->firstRow},{$page->listRows}")->order('user_id desc')->select();

        $this->assign('count', $count);// 总人数
        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list', $list); // 下线
        if ($_GET['is_ajax']) {
            $this->display('ajax_lower_list');
            exit;
        }
        $this->display();
    }

    /**
     * 下线订单列表
     */
    public function order_list()
    {
        $status = I('get.status', 0);
        $where = " user_id = {$this->user_id} and status in($status)";
        $count = M('rebate_log')->where($where)->count();
        $page = new Page($count, 10);
        $list = M('rebate_log')->where($where)->order('id desc')->limit("{$page->firstRow},{$page->listRows}")->select();
        $user_id_list = get_arr_column($list, 'buy_user_id');
        if (!empty($user_id_list))
            $userList = M('users')->where("user_id in (" . implode(',', $user_id_list) . ")")->getField('user_id,nickname,mobile,head_pic');

        $this->assign('count', $count);// 总人数
        $this->assign('page', $page->show());// 赋值分页输出        
        $this->assign('userList', $userList); //
        $this->assign('list', $list); // 下线
        if ($_GET['is_ajax']) {
            $this->display('ajax_order_list');
            exit;
        }
        $this->display();
    }

    /**
     * 申请提现记录
     */
    public function withdrawals()
    {

        C('TOKEN_ON', true);
        if (IS_POST) {
            $this->verifyHandle('withdrawals');
            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $distribut_min = tpCache('distribut.min'); // 最少提现额度
            if ($data['money'] < $distribut_min) {
                $this->error('每次最少提现额度' . $distribut_min);
                exit;
            }
            if ($data['money'] > $this->user['user_money']) {
                $this->error("你最多可提现{$this->user['user_money']}账户余额.");
                exit;
            }

            if (M('withdrawals')->add($data)) {
                $this->success("已提交申请");
                exit;
            } else {
                $this->error('提交失败,联系客服!');
                exit;
            }
        }

        $where = " user_id = {$this->user_id}";
        $count = M('withdrawals')->where($where)->count();
        $page = new Page($count, 16);
        $list = M('withdrawals')->where($where)->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list', $list); // 下线
        if ($_GET['is_ajax']) {
            $this->display('ajaxx_withdrawals_list');
            exit;
        }
        $this->display();
    }

    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            $this->error("验证码错误");
        }
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 40,
            'length' => 4,
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
    }

    /*
     *个人推广二维码 
     */
    /*public function qr_code()
    {
        $ShareLink = urlencode("http://{$_SERVER[HTTP_HOST]}/index.php?m=Mobile&c=Index&a=index&first_leader={$this->user_id}"); //默认分享链接   
        // P($ShareLink);
        // if($this->user['is_distribut'] == 1)
        $this->assign('ShareLink', $ShareLink);
        $this->display();
    }*/
    public function qr_code(){
        $myself = base64_encode($this->_uid);
        $ShareLink = "http://{$_SERVER['HTTP_HOST']}/index.php?m=Mobile&c=Index&a=index&myself={$myself}&first_leader={$this->_uid}"; //默认分享链接
        if (!check_need_consumption($this->_uid)){
            $ShareLink = "http://{$_SERVER['HTTP_HOST']}/index.php?m=Mobile&c=Index&a=index&myself={$myself}";
            //$this->error('您暂未获得推广功能');die;
        }
        $this->assign('share_link',$ShareLink);
        /*if($this->user['is_distribut'] == 1)*/
        $logo = $this->_user['head_pic'];
        if (stripos($logo,'http') === false && !empty($logo)){
            $logo2 = "http://{$_SERVER['HTTP_HOST']}/$logo";
            list($width, $height, $type, $attr) = getimagesize($logo2);
            if ($height-50 > $width)
                $logo = '';
            else
                $logo = SITE_URL.$logo;
        }else{
            $logo = '';
        }
        $qrcode = new QrcodeController();
        $ShareLink = $qrcode->create_qrcode($ShareLink,6, 'L',$logo);
        $this->assign('ShareLink',$ShareLink);
        $this->display();
    }
}