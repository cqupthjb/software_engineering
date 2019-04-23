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
namespace Mobile\Controller;

use Common\Controller\QrcodeController;
use Common\Logic\Withdraw;
use Home\Logic\StoreLogic;
use Home\Logic\UsersLogic;
use Mobile\Logic\FeedbackLogic;
use Mobile\Logic\OrderGoodsLogic;
use Think\Exception;
use Think\Page;
use Think\Verify;

class UserController extends MobileBaseController
{

    public $user_id = 0;
    public $user = array();
    public $_store = array();

    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id = {$user['user_id']}")->find();
            $this->user_id = $user['user_id'];

            $user['withdrawals_count'] = M("withdrawals")->where(array('user_id'=>$this->user_id,'status'=>1))->sum('money');//累计提现
            $max_withdraw_rate = M("config")->where(array("name" => "max_withdraw_rate"))->getField("value");

            if (empty($max_withdraw_rate) || $max_withdraw_rate > 100 || $max_withdraw_rate <= 0){
                $max_withdraw_rate = 100;
            }
            $max_money = ($max_withdraw_rate/100)*$user['user_money'];
            $user['max_money'] = $max_money;
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;

            $this->assign('user', $user); //存储用户信息

            //商户店铺
            $store = M('store')->where(array('user_id'=>$user['user_id']))->find();
            if (!empty($store)){

                $store_max_withdraw_rate = M("config")->where(array("name" => "store_max_withdraw_rate"))->getField("value");

                if (empty($store_max_withdraw_rate) || $store_max_withdraw_rate > 100 || $store_max_withdraw_rate <= 0){
                    $store_max_withdraw_rate = 100;
                }
                $store_max_money = ($store_max_withdraw_rate/100)*$store['store_money'];
                $store['max_money'] = $store_max_money;
                $this->_store = $store;
            }
            $this->assign('store',$this->_store);
        }

        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express','scan_pay',
        );

        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {
            header("location:" . U('Mobile/User/login'));
            exit;
        }
        if($this->payMethod !== 'alipay' && !$this->user_id){
            header("location:" . U('Mobile/User/login'));
            exit;
        }

        $order_status_coment = array(
            'WAITPAY' => '待付款 ', //订单查询状态 待支付
            'WAITSEND' => '待发货', //订单查询状态 待发货
            'WAITRECEIVE' => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );
        $this->assign('order_status_coment', $order_status_coment);
    }

    /*
     * 用户中心首页
     */
    public function index()
    {

        //会员中心顶部信息
        $this->_user_top();

        //关于我们
        $about_type = get_column('config',array('inc_type'=>'about_me','name'=>'about_type'),'value');
        if ($about_type){
            $about_link = get_column('config',array('inc_type'=>'about_me','name'=>'about_link'),'value');
            $this->assign('about_link',$about_link);
        }else{
            $about_me = get_column('config',array('inc_type'=>'about_me','name'=>'about_me'),'value');
            $this->assign('about_me',$about_me);
        }

        $this->assign('about_type',$about_type);

        //region 单商城 copy
        //返利总金额
        $zmoney = M('RebateLog')->where(array('user_id'=>$this->user_id))->sum('money');
        if(empty($zmoney) || $zmoney=='0'){
            $zmoney = '0.00';
        }
        $this->assign('zmoney',$zmoney);
        //我的推广
        $result = M()->query("select sum(goods_price) as goods_price, sum(money) as money from __PREFIX__rebate_log where  user_id = {$this->user_id}");
        $result = $result[0];
        $result['goods_price'] = $result['goods_price'] ? $result['goods_price'] : 0;
        $result['money'] = $result['money'] ? $result['money'] : '0.00';
        $this->assign('sales_volume',$result['money']);
        //endregion

        $this->display();
    }

    //会员中心顶部信息
    protected function _user_top(){
        $order_count = M('order')->where("user_id = {$this->user_id}")->count(); // 我的订单数
        $goods_collect_count = M('goods_collect')->where("user_id = {$this->user_id}")->count(); // 我的商品收藏
        $comment_count = M('comment')->where("user_id = {$this->user_id}")->count();//  我的评论数
        $coupon_count = M('coupon_list')->where("uid = {$this->user_id}")->count(); // 我的优惠券数量
        //$level_name = M('user_level')->where("level_id = '{$this->user['level']}'")->getField('level_name'); // 等级名称
        $level_name="会员ID:".get_role_initials($this->user['role_id']).$this->user_id;
        $this->assign('level_name', $level_name);
        $this->assign('order_count', $order_count);
        $this->assign('goods_collect_count', $goods_collect_count);
        $this->assign('comment_count', $comment_count);
        $this->assign('coupon_count', $coupon_count);
    }

    /*
     * 蛟神 2017-3-15
     * 我的返利列表
     */
    public function rebate(){
        $user_id = $this->user_id; //当前用户ID
        $model = M('RebateLog');
        $rebate_logs = $this->rebateList($user_id);
        $dan = $model->where(array('user_id'=>$user_id))->count(); //返利总单数
        $zmoney = $model->where(array('user_id'=>$user_id))->sum('money'); //返利总金额
        $b = '0.01'; //划算百分比
        $order_count = M('order')->where("user_id = {$this->user_id}")->count(); // 我的订单数
        $goods_collect_count = M('goods_collect')->where("user_id = {$this->user_id}")->count(); // 我的商品收藏
        $comment_count = M('comment')->where("user_id = {$this->user_id}")->count();//  我的评论数
        $this->assign('list',$rebate_logs);
        $this->assign('zmoney',$zmoney);
        $this->assign('dan',$dan);
        $this->assign('b',$b);
        $this->assign('order_count',$order_count);
        $this->assign('goods_collect_count',$goods_collect_count);
        $this->assign('comment_count',$comment_count);
        $this->display();
    }

    /*
     * 蛟神 2017-3-15
     * Mr-X改
     * 我的消息
     */
    public function myNews(){
        $map['user_id'] = $this->user_id; //用户ID
        $list = M('UserNews')->where($map)->select();
        $this->assign('list',$list);
        $this->display();
    }

    //ajax获取我的消息
    public function get_my_news(){
        $res = $this->_page_list($this->_news_list(), '_my_news');
        $this->ajaxReturn($res);
    }

    //获取我的消息
    protected function _news_list(){
        $p = I('page',1);
        $listRows = 5;
        $map = array();
        $map['user_id'] = $this->user_id;
        $count = M('user_news')->where($map)->count();
        $page_count = ceil($count / $listRows);   //总页数
        $news_list = M('user_news')->where($map)->order('create_time desc')->page($p,$listRows)->select();
        return array('list' => $news_list, 'count' => $count, 'page_count' => $page_count, 'page' => $p + 1 ,'now_page'=>$p);
    }

    /**
     * 获取分页数据
     * @param array $res          数据列表
     * @param string $fetch        输出模板
     * @return array        返回数据
     */
    protected function _page_list($res=array('list'=>''), $fetch='')
    {
        if (!empty($res['list'])) {
            $this->assign('list', $res['list']);
            $content = $this->fetch($fetch);
            $res['content'] = $content;
            $res['status'] = 1;
        } else {
            $res = array('status' => 0, 'msg' => '没有更多了');
        }
        return $res;
    }


    /*
	 * 用户返利列表
	 * $user_id  用户ID
	 */
    public function rebateList($user_id){
        $rebate_logs = M('RebateLog')->where(array('user_id'=>$user_id))->select();
        foreach ($rebate_logs as $k =>$v){
            $order_goods = M('order_goods')->where(array('order_id'=>$v['order_id']))->field('goods_id,goods_num')->select();
            foreach ($order_goods as $k1 =>$v1){
                $goods = D('Goods')->relation('rebate')->where(array('goods_id'=>$v1['goods_id']))->field('goods_id,rebate_id,goods_name,original_img,shop_price')->find();
                $order_goods[$k1]['goods_name'] = $goods['goods_name']; //商品名字
                $order_goods[$k1]['original_img'] = $goods['original_img']; //商品图片
                $order_goods[$k1]['shop_price'] = $goods['shop_price']; //商品单价
                $order_goods[$k1]['value'] = $goods['value']; //商品返利百分比
                $order_goods[$k1]['goods_num'] = $v1['goods_num']; //商品数量
            }
            $rebate_logs[$k][good_list]=$order_goods;
            $rebate_logs[$k]['not_money'] = $v['total_money']-$v['money'];    //未返利金额 = 总返利金额 -累计返利金额
        }
        //dump($rebate_logs);exit;
        return $rebate_logs;
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        setcookie('cn', '', time() - 3600, '/');
        setcookie('user_id', '', time() - 3600, '/');
        //$this->success("退出成功",U('Mobile/Index/index'));
        header("Location:" . U('Mobile/Index/index'));
    }

    /*
     * 账户资金
     */
    public function account()
    {
        $user = session('user');
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];

        $this->assign('user', $user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);

        if ($_GET['is_ajax']) {
            $this->display('ajax_account_list');
            exit;
        }
        $this->display();
    }

    public function coupon()
    {
        //
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id, $_REQUEST['type']);
        $coupon_list = $data['result'];
        $this->assign('coupon_list', $coupon_list);
        $this->assign('page', $data['show']);
        if ($_GET['is_ajax']) {
            $this->display('ajax_coupon_list');
            exit;
        }
        $this->display();
    }

    /**
     *  登录
     */
    public function login()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('Mobile/User/index'));
        }
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");
        $this->assign('referurl', $referurl);
        $this->display();
    }


    public function do_login()
    {
        $username = I('post.username');
        $password = I('post.password');
        $username = trim($username);
        $password = trim($password);
        $logic = new UsersLogic();
        $res = $logic->login($username, $password);
        if ($res['status'] == 1) {
            $res['url'] = urldecode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', $nickname, null, '/');
            setcookie('cn',0,time()-3600,'/');
            $cartLogic = new \Home\Logic\CartLogic();
            $cartLogic->login_cart_handle($this->session_id, $res['result']['user_id']);  //用户登录后 需要对购物车 一些操作

            $this->_user = $res['result'];
            $this->_uid = $res['result']['user_id'];

        }
        exit(json_encode($res));
    }

    /**
     *  注册
     */
    public function reg()
    {
    	if($this->user_id > 0) header("Location: " . U('Mobile/User/index'));
        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $username = I('post.username', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            //是否开启注册验证码机制

            if (check_mobile($username) && tpCache('sms.regis_sms_enable')) {
                $code = I('post.mobile_code', '');

                if (!$code)
                    $this->error('请输入验证码');
                $check_code = $logic->sms_code_verify($username, $code, $this->session_id);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);

            }

            $data = $logic->reg($username, $password, $password2);
            if ($data['status'] != 1)
                $this->error($data['msg']);
            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new \Home\Logic\CartLogic();
            $cartLogic->login_cart_handle($this->session_id, $data['result']['user_id']);  //用户登录后 需要对购物车 一些操作
            $this->success($data['msg'], U('Mobile/User/index'));
            exit;
        }
        $this->assign('regis_sms_enable', tpCache('sms.regis_sms_enable')); // 注册启用短信：
        $this->assign('sms_time_out', tpCache('sms.sms_time_out')); // 手机短信超时时间
        $this->display();
    }

    /*
     * 订单列表
     */
    public function order_list()
    {
        $where = ' user_id=' . $this->user_id;
        //条件搜索 
        if (in_array(strtoupper(I('type')), array('WAITCCOMMENT', 'COMMENTED'))) {
            $where .= " AND order_status in(2,4) "; //代评价 和 已评价
        } elseif (I('type')) {
            $where .= C(strtoupper(I('type')));
        }
        $count = M('order')->where($where)->count();
        $Page = new Page($count, 10);

        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
        }
        $storeList = M('store')->getField('store_id,store_name,store_qq'); // 找出所有商品对应的店铺id
        $this->assign('storeList', $storeList); // 店铺列表
        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $this->assign('page', $show);
        $this->assign('lists', $order_list);
        $this->assign('active', 'order_list');
        $this->assign('active_status', I('get.type'));
        if ($_GET['is_ajax']) {
            $this->display('ajax_order_list');
            exit;
        }
        $this->display();
    }

    //扫码支付订单
    public function scan_order_list()
    {
        $pay_status = I('pay_status',1);
        $where = array('user_id'=>$this->user_id);
        $where['pay_status'] = $pay_status;
        $count = M('scan_order')->where($where)->count();
        $Page = new Page($count, 5);

        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = M('scan_order')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->assign('pay_status',$pay_status );
        $this->assign('page', $show);
        $this->assign('lists', $order_list);
        if ($_GET['is_ajax']) {
            $this->display('_scan_order');
            exit;
        }
        $this->display('scan_order');
    }

    /*
     * 订单列表
     */
    public function ajax_order_list()
    {

    }

    /*
     * 订单详情
     */
    public function order_detail()
    {
        $id = I('get.id');
        if (empty($id)) {
            $this->error('参数错误');
        }
        $map['order_id'] = $id;
        $map['user_id'] = $this->user_id;
        $order_info = M('order')->where($map)->find();
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (!$order_info) {
            $this->error('没有获取到订单信息');
            exit;
        }
        //获取订单商品
        $model = new UsersLogic();
        $data = $model->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];
        $order_info['total_fee'] = $order_info['goods_price'] + $order_info['shipping_price'] - $order_info['integral_money'] - $order_info['coupon_price'] - $order_info['discount'];
        //$region_list = get_region_list();
        $store = M('store')->where("store_id = {$order_info['store_id']}")->find(); // 找出这个商家
        // 店铺地址id
        $ids[] = $store['province_id'];
        $ids[] = $store['city_id'];
        $ids[] = $store['district'];

        $ids[] = $order_info['province'];
        $ids[] = $order_info['city'];
        $ids[] = $order_info['district'];
        if (!empty($ids))
            $regionLits = M('region')->where("id in (" . implode(',', $ids) . ")")->getField("id,name");

        $region_list = get_region_list();
        $invoice_no = M('DeliveryDoc')->where("order_id = $id")->getField('invoice_no', true);
        $order_info[invoice_no] = implode(' , ', $invoice_no);
        //获取订单操作记录
        $order_action = M('order_action')->where(array('order_id' => $id))->select();
        $this->assign('store', $store);
        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        //$this->assign('region_list',$region_list);
        $this->assign('regionLits', $regionLits);
        $this->assign('order_info', $order_info);
        $this->assign('order_action', $order_action);
        $this->display();
    }

    public function express()
    {
        $order_id = I('get.order_id', 195);
        $result = $order_goods = $delivery = array();
        $order_goods = M('order_goods')->where("order_id=$order_id")->select();
        $delivery = M('delivery_doc')->where("order_id=$order_id")->limit(1)->find();
        if ($delivery['shipping_code'] && $delivery['invoice_no']) {
            $result = queryExpress($delivery['shipping_code'], $delivery['invoice_no']);
            $this->assign('result', $result);
            $this->assign('order_goods', $order_goods);
            $this->assign('delivery', $delivery);
        }
        $this->display();
    }

    /*
     * 取消订单
     */
    public function cancel_order()
    {
        $id = I('get.id');
        //检查是否有积分，余额支付
        $logic = new UsersLogic();
        $data = $logic->cancel_order($this->user_id, $id);
        if ($data['status'] < 0)
            $this->error($data['msg']);
        $this->success($data['msg']);
    }

    /*
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists = get_user_address_list($this->user_id);
        $region_list = get_region_list();
        $this->assign('region_list', $region_list);
        $this->assign('lists', $address_lists);
        $this->display();
    }

    /*
     * 添加地址
     */
    public function add_address()
    {
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, 0, I('post.'));
            if ($data['status'] != 1)
                $this->error($data['msg']);
            elseif ($_POST['source'] == 'cart2') {
                header('Location:' . U('/Mobile/Cart/cart2', array('address_id' => $data['result'])));
                exit;
            }

            $this->success($data['msg'], U('/Mobile/User/address_list'));
            exit();
        }
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $p);
        //$this->display('edit_address');
        $this->display();

    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $id = I('id');
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->find();
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, $id, I('post.'));
            if ($_POST['source'] == 'cart2') {
                header('Location:' . U('/Mobile/Cart/cart2', array('address_id' => $id)));
                exit;
            } else
                $this->success($data['msg'], U('/Mobile/User/address_list'));
            exit();
        }
        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $address['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $address['city'], 'level' => 3))->select();
        if ($address['twon']) {
            $e = M('region')->where(array('parent_id' => $address['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }

        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);

        $this->assign('address', $address);
        $this->display();
    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('get.id');
        $source = I('get.source');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if ($source == 'cart2') {
            header("Location:" . U('Mobile/Cart/cart2'));
            exit;
        } else {
            header("Location:" . U('Mobile/User/address_list'));
        }
    }

    /*
     * 地址删除
     */
    public function del_address(){
        $id = I('get.id');
        
        $address = M('user_address')->where("address_id = $id")->find();
        $row = M('user_address')->where(array('user_id'=>$this->user_id,'address_id'=>$id))->delete();                
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if($address['is_default'] == 1)
        {
            $address2 = M('user_address')->where("user_id = {$this->user_id}")->find();            
            $address2 && M('user_address')->where("address_id = {$address2['address_id']}")->save(array('is_default'=>1));
        }        
        if(!$row)
            $this->error('操作失败',U('User/address_list'));
        else
            $this->success("操作成功",U('User/address_list'));
    } 

    /*
     * 评论晒单
     */
    public function comment()
    {
        $user_id = $this->user_id;
        $status = I('get.status');
        $logic = new UsersLogic();
        $result = $logic->get_comment($user_id, $status); //获取评论列表

        //region 单商城 copy

//        foreach ($result['result'] as $k =>$v){
//            $rebate_id = M('goods')->where(array('goods_id'=>$v['goods_id']))->getField('rebate_id'); //通过商品ID查到返利ID
//            if($rebate_id!=='0'){  //有设置过返利
//                $rebate['value'] = M('Rebate')->where(array('id'=>$rebate_id))->getField('value'); //查询返利值
//                if($v['goods_num']>1){
//                    $rebate['money'] = (1-$rebate['value']*0.01) * $v['goods_price'] * $v['goods_num'];       //返利后的钱	 （多个商品）
//                }else{
//                    $rebate['money'] = (1-$rebate['value']*0.01) * $v['goods_price'];       //返利后的钱	 （单个个商品）
//                }
//
//            }else{ //没有设置过返利
//                $rebate['value'] = '0';
//                if($v['goods_num']>1){
//                    $rebate['money'] = $v['goods_price'] * $v['goods_num'];       //返利后的钱	 （多个商品）
//                }else{
//                    $rebate['money'] = $v['goods_price'];       //返利后的钱	 （单个个商品）
//                }
//                $rebate['money'] = $v['goods_price'];
//            }
//            $result['result'][$k]['value'] = $rebate['value'];
//            $result['result'][$k]['money'] =  $rebate['money'];
//        }

        //endregion

        $this->assign('comment_list', $result['result']);
        if ($_GET['is_ajax']) {
            $this->display('ajax_comment_list');
            exit;
        }
        $this->display();
    }

    /*
     *添加评论
     */
    public function add_comment()
    {
        if (IS_POST) {
            // 晒图片
            if ($_FILES[comment_img_file][tmp_name][0]) {
                $upload = new \Think\Upload();// 实例化上传类
                $upload->maxSize = $map['author'] = (1024 * 1024 * 3);// 设置附件上传大小 管理员10M  否则 3M
                $upload->exts = array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
                $upload->rootPath = './Public/upload/comment/'; // 设置附件上传根目录
                $upload->replace = true; // 存在同名文件是否是覆盖，默认为false
                //$upload->saveName  =  'file_'.$id; // 存在同名文件是否是覆盖，默认为false
                // 上传文件
                $upinfo = $upload->upload();
                if (!$upinfo) {// 上传错误提示错误信息
                    $this->error($upload->getError());
                } else {
                    foreach ($upinfo as $key => $val) {
                        $comment_img[] = '/Public/upload/comment/' . $val['savepath'] . $val['savename'];
                    }
                    $add['img'] = serialize($comment_img); // 上传的图片文件
                }
            }

            $user_info = session('user');
            $logic = new UsersLogic();
            $add['goods_id'] = I('goods_id');
            $add['email'] = $user_info['email'];
            $hide_username = I('hide_username');
            if (empty($hide_username)) {
                $add['username'] = $user_info['nickname'];
            }
            $add['order_id'] = I('order_id');
            $add['service_rank'] = I('service_rank');
            $add['deliver_rank'] = I('deliver_rank');
            $add['goods_rank'] = I('goods_rank');
            //$add['content'] = htmlspecialchars(I('post.content'));
            $add['content'] = I('content');
            $add['add_time'] = time();
            $add['ip_address'] = getIP();
            $add['user_id'] = $this->user_id;

            //添加评论
            $row = $logic->add_comment($add);
            if ($row[status] == 1) {
                $this->success('评论成功', U('/Mobile/Goods/goodsInfo', array('id' => $add['goods_id'])));
                exit();
            } else {
                $this->error($row[msg]);
            }
        }
        $rec_id = I('rec_id', 0);
        $order_goods = M('order_goods')->where("rec_id = $rec_id")->find();
        $this->assign('order_goods', $order_goods);
        $this->display();
    }


    /**
     * @time 2016/8/5
     * @author dyr
     * 订单评价列表
     */
    public function comment_list()
    {
        $order_id = I('get.order_id');
        $store_id = I('get.store_id');
        $goods_id = I('get.goods_id');
        $part_finish = I('get.part_finish', 0);
        if (empty($order_id) || empty($store_id)) {
            $this->error("参数错误");
        } else {
            //查找店铺信息
            $store_where['store_id'] = $store_id;
            $store_info = M('store')->field('store_id,store_name,store_phone,store_address,store_logo')->where($store_where)->find();
            if (empty($store_info)) {
                $this->error("该商家不存在");
            }
            //查找订单是否已经被用户评价
            $order_comment_where['order_id'] = $order_id;
            $order_comment_where['deleted'] = 0;
            $order_info = M('order')->field('order_id,order_sn,is_comment,add_time')->where($order_comment_where)->find();
            //查找订单下的所有未评价的商品
            $order_goods_logic = new OrderGoodsLogic();
            $no_comment_goods = $order_goods_logic->get_no_comment_goods($order_id, $goods_id);
            $this->assign('store_info', $store_info);
            $this->assign('order_info', $order_info);
            $this->assign('no_comment_goods', $no_comment_goods);
            $this->assign('part_finish', $part_finish);
            $this->display();
        }
    }

    /**
     * @time 2016/8/5
     * @author dyr
     *  添加评论
     */
    public function conmment_add()
    {
        $anonymous = I('post.anonymous');
        $store_score['describe_score'] = I('post.store_packge_hidden');
        $store_score['seller_score'] = I('post.store_speed_hidden');
        $store_score['logistics_score'] = I('post.store_sever_hidden');
        $order_id = $store_score['order_id'] = $store_score_where['order_id'] = I('post.order_id');

        /*if (get_count('order',array('order_id'=>$order_id,'is_comment'=>1)) >0){
            $this->error('该订单已评价');die;
        }*/

        $goods_id = I('post.goods_id');
        $content = I('post.content');
        $spec_key_name = I('post.spec_key_name');
        $rank = I('post.rank');
        $tag = I('post.tag');

        if(!$order_id || !$goods_id)
            $this->error('非法操作');

        //检查订单是否已完成
        $order = M('order')->where("order_id = $order_id AND user_id = {$this->user_id}")->find();
        if($order['order_status'] != 2)
            $this->error('该笔订单还未完成');

        //检查是否已评论过
        $goods = M('comment')->where("order_id = {$order_id} AND goods_id = {$goods_id}")->find();
        if($goods)
            $this->error('您已经评论过该商品');

        $store_score['user_id'] = $store_score_where['user_id'] = $this->user_id;
        $store_score_where['deleted'] = 0;
        $store_id = M('order')->where(array('order_id' => $store_score_where['order_id']))->getField('store_id');
        $store_score['store_id'] = $store_id;
        //处理订单评价
        if (!empty($store_score['describe_score']) && !empty($store_score['seller_score']) && !empty($store_score['logistics_score'])) {
            $order_comment = M('order_comment')->where($store_score_where)->find();
            if ($order_comment) {
                M('order_comment')->where($store_score_where)->save($store_score);
                M('order')->where(array('order_id' => $order_id))->save(array('is_comment' => 1));
            } else {
                M('order_comment')->add($store_score);//订单打分
                M('order')->where(array('order_id' => $order_id))->save(array('is_comment' => 1));
            }
            //订单打分后更新店铺评分
            $store_logic = new StoreLogic();
            $store_logic->updateStoreScore($store_id);
        }

        //处理商品评价
        $comment['goods_id'] = $goods_id;
        $comment['order_id'] = $order_id;
        $comment['store_id'] = $store_id;
        $comment['user_id'] = $this->user_id;
        $comment['content'] = $content;
        $comment['ip_address'] = get_client_ip();
        $comment['spec_key_name'] = $spec_key_name;
        $comment['goods_rank'] = $rank;
        //$comment['img'] = (empty($value['commment_img'][0])) ? '' : serialize($value['commment_img']);
        $comment['img'] = (empty(I('image_file'))) ? '' : serialize(I('image_file'));
        $comment['impression'] = (empty($tag[0])) ? '' : implode(',', $tag);
        $comment['is_anonymous'] = empty($anonymous) ? 1 : 0;
        $comment['add_time'] = time();
        M('comment')->add($comment);//想评论表插入数据
        M('order_goods')->where(array('order_id' => $store_score['order_id'], 'goods_id' => $goods_id))->save(array('is_comment' => 1));
        M('goods')->where(array('goods_id' => $goods_id))->setInc('comment_count', 1);
        unset($comment);
        $this->success("评论成功", U('User/comment'));
    }

    //上传文件
    public function upload_image(){
        //微信浏览器
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            $serverid=I("serverid");
            S('serverid',$serverid);
            $file = $this->get_media($serverid);
            $filename=date("YmdHis").rand(1000,9999).".jpg";
            $dir="./Public/upload/wx/".date("Y").'/'.date('m-d');
            dir_create($dir);

            $path = $dir."/".$filename;
            file_put_contents($path, $file);

            $path = str_replace('./','/',$path);

            $data = array();
            $data['path'] = $path;
            $data['md5'] = md5($path);
            $data['sha1'] = sha1($path);
            $data['create_time'] = time();
            $data['status'] = 1;
            $data['status'] = 1;
            $this->ajaxReturn($data);
        }else{
            $path = I('path','temp');
            $info = array(
                'num'=> I('num'),
                'title' => '',
                'upload' =>U('Admin/Ueditor/imageUp',array('savepath'=>$path,'pictitle'=>'banner','dir'=>'images')),
                'size' => '4M',
                'type' =>'jpg,png,gif,jpeg',
                'input' => I('input'),
            );
            $this->ajaxReturn($info);
        }

    }

    //拉取微信服务器的图片
    public function get_media($serverid){
        $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
        $file=$jssdk->getMedia($serverid);
        /*if ($file == false){
            $this->ajaxReturn(array('status'=>0,'msg'=>'未上传图片'));die;
        }*/
        return $file;
    }

    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.qq') ? $post['qq'] = I('post.qq') : false;  //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;  // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;  //省份
            I('post.city') ? $post['city'] = I('post.city') : false;  // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;  //地区
            I('post.email') ? $post['email'] = I('post.email') : false; //邮箱
            I('post.mobile') ? $post['mobile'] = I('post.mobile') : false; //手机
            $email = I('post.email');
            $mobile = I('post.mobile');
            $code = I('post.mobile_code', '');

            if (!empty($email)) {
                $c = M('users')->where("email = '{$post['email']}' and user_id != {$this->user_id}")->count();
                $c && $this->error("邮箱已被使用");
            }
            if (!empty($mobile)) {
                $c = M('users')->where("mobile = '{$post['mobile']}' and user_id != {$this->user_id}")->count();
                $c && $this->error("手机已被使用");
                if (!$code)
                    $this->error('请输入验证码');
                $check_code = $userLogic->sms_code_verify($mobile, $code, $this->session_id);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);
            }

            if (!$userLogic->update_info($this->user_id, $post))
                $this->error("保存失败");

            $this->success("操作成功");
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        //  获取订单城市
        $city = M('region')->where(array('parent_id' => $user_info['province'], 'level' => 2))->select();
        //  获取订单地区
        $area = M('region')->where(array('parent_id' => $user_info['city'], 'level' => 3))->select();



        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));
        $this->display();
    }


    /*
     * 邮箱验证
     */
    public function email_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['email_validated'] == 0)
            $step = 2;
        //原邮箱验证是否通过
        if ($user_info['email_validated'] == 1 && session('email_step1') == 1)
            $step = 2;
        if ($user_info['email_validated'] == 1 && session('email_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $email = I('post.email');
            $code = I('post.code');
            $info = session('email_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $email || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('email_code', null);
                    session('email_step1', null);
                    if (!$userLogic->update_email_mobile($email, $this->user_id))
                        $this->error('邮箱已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('email_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/email_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码邮箱不匹配');
        }
        $this->assign('step', $step);
        $this->display();
    }

    /*
    * 手机验证
    */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['mobile_validated'] == 0)
            $step = 2;
        //原手机验证是否通过
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') == 1)
            $step = 2;
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $mobile = I('post.mobile');
            $code = I('post.code');
            $info = session('mobile_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $mobile || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('mobile_code', null);
                    session('mobile_step1', null);
                    if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2))
                        $this->error('手机已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('mobile_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/mobile_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码手机不匹配');
        }
        $this->assign('step', $step);
        $this->display();
    }

    public function collect_list()
    {
        $userLogic = new UsersLogic();
        $data = $userLogic->get_goods_collect($this->user_id);
        $this->assign('page', $data['show']);// 赋值分页输出
        $this->assign('goods_list', $data['result']);
        if ($_GET['is_ajax']) {
            $this->display('ajax_collect_list');
            exit;
        }
        $this->display();
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = I('collect_id');
        $user_id = $this->user_id;
        if (M('goods_collect')->where("collect_id = $collect_id and user_id = $user_id")->delete()) {
            $this->success("取消收藏成功", U('User/collect_list'));
        } else {
            $this->error("取消收藏失败", U('User/collect_list'));
        }
    }

    public function message_list()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            $this->verifyHandle('message');

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $user = session('user');
            $data['user_name'] = $user['nickname'];
            $data['msg_time'] = time();
            if (M('feedback')->add($data)) {
                $this->success("留言成功", U('User/message_list'));
                exit;
            } else {
                $this->error('留言失败', U('User/message_list'));
                exit;
            }
        }
        $msg_type = array(0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购');
//        $count = M('feedback')->where("user_id=" . $this->user_id)->count();
//        $Page = new Page($count, 5);
//        $Page->rollPage = 2;
//        $message = M('feedback')->where("user_id=" . $this->user_id)->order('msg_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
//        $showpage = $Page->show();
        header("Content-type:text/html;charset=utf-8");

//        $this->assign('page', $showpage);
//        $this->assign('message', $message);
        $this->assign('msg_type', $msg_type);
        $this->display();
    }

    //ajax获取我的消息
    public function get_message_list(){
        $msg_type = array(0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购');
        $this->assign('msg_type',$msg_type);
        $res = $this->_page_list($this->_message_list(), '_message_list');
        $this->ajaxReturn($res);
    }

    //获取我的消息
    protected function _message_list(){
        $p = I('page',1);
        $listRows = 5;
        $map = array();
        $map['user_id'] = $this->user_id;
        $count = M('feedback')->where($map)->count();
        $page_count = ceil($count / $listRows);   //总页数
        $news_list = M('feedback')->where($map)->order('msg_id desc')->page($p,$listRows)->select();
        return array('list' => $news_list, 'count' => $count, 'page_count' => $page_count, 'page' => $p + 1 ,'now_page'=>$p);
    }

    //回复
    public function reply(){
        $msg_id = I('get.msg_id', 1);
        $page = I('page',1);//页数
        $list_num = 10;//每页条数
        $reply_logic = new FeedbackLogic();
        $reply_list = $reply_logic->getFeedbackPage($msg_id, $page, $list_num);

        $page_sum = ceil($reply_list['count'] / $list_num);
        $comment_info = M('feedback')->where(array('msg_id' => $msg_id))->find();

        $this->assign('msg_info', $comment_info);//评价内容
        $this->assign('page_sum', intval($page_sum));//总页数
        $this->assign('page_current', intval($page));//当前页
        $this->assign('reply_count', $reply_list['count']);//总回复数
        $this->assign('reply_list', $reply_list['list']);//回复列表
        $this->assign('floor', $reply_list['count'] - (intval($page) - 1) * $list_num);//楼层
        $this->display();
    }

    public function points()
    {
    	$type = I('type','all');
    	$this->assign('type',$type);
    	if($type == 'recharge'){
    		$count = M('recharge')->where("user_id=" . $this->user_id)->count();
    		$Page = new Page($count, 16);
    		$account_log = M('recharge')->where("user_id=" . $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
    	}else if($type == 'points'){
    		$count = M('account_log')->where("user_id=" . $this->user_id ." and pay_points!=0 ")->count();
    		$Page = new Page($count, 16);
    		$account_log = M('account_log')->where("user_id=" . $this->user_id." and pay_points!=0 ")->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
    	}else{
    		$count = M('account_log')->where("user_id=" . $this->user_id)->count();
    		$Page = new Page($count, 16);
    		$account_log = M('account_log')->where("user_id=" . $this->user_id)->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
    	}
		$showpage = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $showpage);
        if ($_GET['is_ajax']) {
            $this->display('ajax_points');
            exit;
        }
        $this->display();
    }

    /*
     * 密码修改
     */
    public function password()
    {
        //检查是否第三方登录用户
        $logic = new UsersLogic();
        $data = $logic->get_info($this->user_id);
        $user = $data['result'];
        if ($user['mobile'] == '' && $user['email'] == '')
            $this->error('请先绑定手机', U('/Mobile/User/index'));
        if (IS_POST) {
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password')); // 获取用户信息
            if ($data['status'] == -1)
                $this->error($data['msg']);

            $this->success($data['msg']);
            exit;
        }
        $this->display();
    }

    function forget_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/Index'));
        }
        $username = I('mobile');
        if (IS_POST) {
            if (!empty($username)) {
                $this->verifyHandle('forget');
                $field = 'mobile';
                if (check_email($username)) {
                    $field = 'email';
                }
                $user = M('users')->where("mobile='$username'")->find();//email='$username' or
                if ($user) {
                    session('find_password', array('user_id' => $user['user_id'], 'username' => $username,
                        'email' => $user['email'], 'mobile' => $user['mobile'], 'type' => $field));
                    header("Location: " . U('User/find_pwd'));
                    exit;
                } else {
                    $this->error("用户名不存在，请检查");
                }
            }
        }
        $this->display();
    }

    function find_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/Index'));
        }
        $user = session('find_password');
        if (empty($user)) {
            $this->error("请先验证用户名", U('User/forget_pwd'));
        }
        $this->assign('user', $user);
        $this->display();
    }


    public function set_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/Index'));
        }
        $check = session('validate_code');
        if (empty($check)) {
            header("Location:" . U('User/forget_pwd'));
        } elseif ($check['is_check'] == 0) {
            $this->error('验证码还未验证通过', U('User/forget_pwd'));
        }
        if (IS_POST) {
            $password = I('post.password');
            $password2 = I('post.password2');
            if ($password2 != $password) {
                $this->error('两次密码不一致', U('User/forget_pwd'));
            }
            if ($check['is_check'] == 1) {
                //$user = get_user_info($check['sender'],1);
                $user = M('users')->where("mobile = '{$check['sender']}' or email = '{$check['sender']}'")->find();
                M('users')->where("user_id=" . $user['user_id'])->save(array('password' => encrypt($password)));
                session('validate_code', null);
                //header("Location:".U('User/set_pwd',array('is_set'=>1)));
                $this->success('新密码已设置行牢记新密码', U('User/index'));
                exit;
            } else {
                $this->error('验证码还未验证通过', U('User/forget_pwd'));
            }
        }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
        $this->display();
    }

    //发送验证码
    public function send_validate_code()
    {
        $type = I('type');
        $send = I('send');
        $logic = new UsersLogic();

        $res = $logic->send_validate_code($send, $type);
        $this->ajaxReturn($res);
    }

    //检测 验证码
    public function check_validate_code()
    {
        $code = I('post.code');
        $send = I('send');
        $logic = new UsersLogic();
        $res = $logic->check_validate_code($code, $send);
        $this->ajaxReturn($res);
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

    /**
     * 账户管理
     */
    public function accountManage()
    {
        $this->display();
    }

    public function order_confirm()
    {
        $id = I('get.id', 0);
        $data = confirm_order($id,$this->user_id);
        if (!$data['status'])
            $this->error($data['msg']);
        else
            $this->success($data['msg']);
    }

    /**
     * 申请退货
     */
    public function return_goods()
    {
        $order_id = I('order_id', 0);
        $order_sn = I('order_sn', 0);
        $goods_id = I('goods_id', 0);
        $spec_key = I('spec_key');
        
        $c = M('order')->where("order_id = $order_id and user_id = {$this->user_id}")->count();
        if($c == 0)
        {
            $this->error('非法操作');
            exit;
        }          
        
        $return_goods = M('return_goods')->where("order_id = $order_id and goods_id = $goods_id and spec_key = '$spec_key'")->find();
        if (!empty($return_goods)) {
            $this->success('已经提交过退货申请!', U('Mobile/User/return_goods_info', array('id' => $return_goods['id'])));
            exit;
        }
        if (IS_POST) {

            // 晒图片
            if ($_FILES[return_imgs][tmp_name][0]) {
                $upload = new \Think\Upload();// 实例化上传类
                $upload->maxSize = $map['author'] = (1024 * 1024 * 3);// 设置附件上传大小 管理员10M  否则 3M
                $upload->exts = array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
                $upload->rootPath = './Public/upload/return_goods/'; // 设置附件上传根目录
                $upload->replace = true; // 存在同名文件是否是覆盖，默认为false
                //$upload->saveName  =  'file_'.$id; // 存在同名文件是否是覆盖，默认为false
                // 上传文件
                $upinfo = $upload->upload();
                if (!$upinfo) {// 上传错误提示错误信息
                    $this->error($upload->getError());
                } else {
                    foreach ($upinfo as $key => $val) {
                        $return_imgs[] = '/Public/upload/return_goods/' . $val['savepath'] . $val['savename'];
                    }
                    $data['imgs'] = implode(',', $return_imgs);// 上传的图片文件
                }
            }

            $data['order_id'] = $order_id;
            $data['order_sn'] = $order_sn;
            $data['goods_id'] = $goods_id;
            $data['addtime'] = time();
            $data['user_id'] = $this->user_id;
            $data['type'] = I('type'); // 服务类型  退货 或者 换货
            $data['reason'] = I('reason'); // 问题描述     
            $data['spec_key'] = I('spec_key'); // 商品规格						       
            M('return_goods')->add($data);
            $this->success('申请成功,客服第一时间会帮你处理', U('Mobile/User/order_list'));
            exit;
        }

        $goods = M('goods')->where("goods_id = $goods_id")->find();
        $this->assign('goods', $goods);
        $this->assign('order_id', $order_id);
        $this->assign('order_sn', $order_sn);
        $this->assign('goods_id', $goods_id);
        $this->display();
    }

    /**
     * 退换货列表
     */
    public function return_goods_list()
    {
        $count = M('return_goods')->where("user_id = {$this->user_id}")->count();
        $page = new Page($count, 4);
        $list = M('return_goods')->where("user_id = {$this->user_id}")->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if (!empty($goods_id_arr))
            $goodsList = M('goods')->where("goods_id in (" . implode(',', $goods_id_arr) . ")")->getField('goods_id,goods_name');
        $this->assign('goodsList', $goodsList);
        $this->assign('list', $list);
        $this->assign('page', $page->show());// 赋值分页输出                    	    	
        if ($_GET['is_ajax']){
            $this->display('return_ajax_goods_list');
            exit;
        }
        $this->display();
    }

    /**
     *  退货详情
     */
    public function return_goods_info()
    {
        $id = I('id', 0);
        $return_goods = M('return_goods')->where("id = $id")->find();
        if ($return_goods['imgs'])
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        $goods = M('goods')->where("goods_id = {$return_goods['goods_id']} ")->find();
        $this->assign('goods', $goods);
        $this->assign('return_goods', $return_goods);
        $this->display();
    }
    
    public function recharge(){
       	$order_id = I('order_id');
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();        
        //微信浏览器
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();            
        }        
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach($paymentList as $key => $val)
        {
            $val['config_value'] = unserialize($val['config_value']);
            if($val['config_value']['is_bank'] == 2)
            {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include 'Application/Home/Conf/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList',$paymentList);
        $this->assign('bank_img',$bank_img);
        $this->assign('bankCodeList',$bankCodeList);
        
        if($order_id>0){
        	$order = M('recharge')->where("order_id = $order_id")->find();    
        	$this->assign('order',$order);
        }    
    	$this->display();
    }
    /**
     * 申请提现记录
     */
    public function withdrawals(){

        C('TOKEN_ON',true);
        if(IS_POST)
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
            if($data['money'] > $this->user['max_money'])
            {
                $this->error("你最多可提现{$this->user['max_money']}账户余额.");
                exit;
            }

            //每天可提现的最大限额
            $maxWithdrawADay = M("config")->where(array("name"=>"max_withdraw_money"))->getField("value");

            if (!empty($maxWithdrawADay)){
                $today = date('Y-m-d');
                $time = strtotime($today);
                $where = array('user_id'=>$this->user_id,'status'=>array("in",'0,1'),'create_time'=>array('gt',$time));
                $money = M("withdrawals")->where($where)->sum("money");
                if ($money >= $maxWithdrawADay){
                    $this->error('每天只可申请提现'.$maxWithdrawADay.'元');
                    exit;
                }
            }

            $data['order_sn'] = build_order_no();//订单号
            $withdrawals_id = M('withdrawals')->add($data);
            if($withdrawals_id!==false){

                $withdraw = new Withdraw();
                $re = $withdraw->user_withdraw($withdrawals_id);
                if ($re['success']){
                    //消息记录
                    save_user_news($this->user_id,1,$withdrawals_id,'您有条提现申请已受理');
                    $this->success($re['msg']);
                } else {
                    M('withdrawals')->where(array('id'=>$withdrawals_id))->delete();
                    $this->error($re['msg']);
                }
                exit;
            }else{
                $this->error('提交失败,联系客服!');
                exit;
            }
        }
        //会员中心顶部信息
        $this->_user_top();

        $where = " user_id = {$this->user_id}";
        $count = M('withdrawals')->where($where)->count();
        $page = new Page($count,16);
        $list = M('withdrawals')->where($where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list',$list); // 下线
        if($_GET['is_ajax'])
        {
            $this->display('ajaxx_withdrawals_list');
            exit;
        }
        $this->display();
    }

    //关于我们
    public function about_me(){
        $about_me = get_column('config',array('inc_type'=>'about_me','name'=>'about_me'),'value');
        $this->assign('about_me',$about_me);
        $this->display();
    }

    //扫一扫
    public function scanqrcode(){
        $this->display();
    }

    //支付二维码进入页面
    public function scan_pay(){
        try{
            $store_id = I('store_id',0);
            $pay = $this->payMethod;
            if (empty($store_id))
                throw new Exception('该商家不存在或已关闭');
            $store = M('store')->where(array('store_id'=>$store_id,'deleted'=>0))->find();
            if (empty($store))
                throw new Exception('该商家不存在或已关闭');
            session('_store',$store);
            $this->assign('store',$store);
            //region 支付方式code
            $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
            //微信浏览器
            if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
                $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
            }
            $paymentList = convert_arr_key($paymentList, 'code');
            $this->assign('paymentList',$paymentList);
            //endregion
            if ($pay == 'alipay'){
                $this->display('scan_pay_ali');
                return;
            }
            $this->display();
        }catch(Exception $e){
            $this->error($e->getMessage(),U('Mobile/index/index'));
        }
    }

    //支付成功页面
    public function scan_pay_success(){
        $order_id = I('order_id');
        $order = M('scan_order')->where(array('order_id'=>$order_id))->find();
        $store = M('store')->where(array('store_id'=>$order['store_id']))->find();
        $this->assign('order',$order);
        $this->assign('store',$store);
        //微信浏览器
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');
        $this->assign('paymentList',$paymentList);
        $this->display('scan_pay_success');
    }

    //获取物流 快递100
    public function get_invoice_100(){
        $invoice_no = I('invoice_no');
        try{
            if (empty($invoice_no)){
                throw new Exception('参数缺省');
            }
            //region 智能获取物流码
            $type_url = "https://www.kuaidi100.com/autonumber/autoComNum?text={$invoice_no}";
            $_type = http_curl($type_url);
            if (!empty($_type) && $_type != false){
                $_type = json_decode($_type,true);
            }
            if (!empty($_type['comCode'])){
                $type = $_type['comCode'];
            }else{
                if (!empty($_type['auto'])){
                    $type = $_type['auto'][0]['comCode'];
                }
            }
            //endregion
            if (!empty($type)){
                $url = "http://www.kuaidi100.com/query?type={$type}&postid={$invoice_no}&id=1&valicode=&temp=".I('temp',rand(1,9999999));
                $data = array();
                if (!empty($invoice_no)){
                    $data = http_curl($url);
                }
                if (!empty($data) && $data != false){
                    $data = json_decode($data,true);
                }
                if ($data['status'] == 200){
                    if (isset($data['data']) && !empty($data['data'])){
                        $this->assign('invoice_list',$data['data']);
                    }
                }
            }

            $content = $this->fetch('_invoice_100');
            $this->ajaxReturn(array('status'=>1,'content'=>$content));die;
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>-1,'msg'=>$e->getMessage()));
        }
    }
    //快递鸟版
    public function get_invoice(){
        try{
            $invoice_no = I('invoice_no','');
            $shipping   = I('shipping','');

            if (!empty($invoice_no) && !empty($shipping)){
                $data = getOrderTracesByJson($invoice_no,$shipping);
                if (!empty($data)){
                    $data = json_decode($data,true);
                    if ($data['Success'] == true){
                        $this->assign('invoice_list',$data['Traces']);
                    }
                }
            }
            $content = $this->fetch('_invoice');
            $this->ajaxReturn(array('status'=>1,'content'=>$content));die;
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>-1,'msg'=>$e->getMessage()));
        }
    }

    public function invoice(){
        $invoice = I('invoice_no');
        $shipping   = I('shipping');
        $this->assign('invoice_no',$invoice);
        $this->assign('shipping',$shipping);
        $this->display();
    }


    //客户新加需求，商户资金在用户中心显示并可提现
    //店铺资金信息
    public function store_points(){
        if (empty($this->_store)){
            $this->error('没有您的店铺信息');die;
        }
        $type = I('type','all');
        $this->assign('type',$type);

        $map = array();
        $map['store_id'] = $this->_store['store_id'];
        $count = M('account_log_store')->where($map)->count();
        $Page = new Page($count, 16);
        $account_log = M('account_log_store')->where($map)->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $show_page = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $show_page);
        if ($_GET['is_ajax']) {
            $this->display('_store_points');
            exit;
        }
        $this->display();
    }

    /**
     * 商户资金申请提现记录
     */
    public function store_withdrawals(){

        if (empty($this->_store)){
            $this->error('没有您的店铺信息');die;
        }

        C('TOKEN_ON',true);
        if(IS_POST)
        {
//            $this->verifyHandle('store_withdrawals');
            $data = I('post.');
            $data['store_id'] = $this->_store['store_id'];
            $data['create_time'] = time();
            $withdrawals_min = tpCache('basic.min'); // 最少提现额度
            if($data['money'] < $withdrawals_min)
            {
                $this->error('每次最少提现额度'.$withdrawals_min);
                exit;
            }

            if($data['money'] > $this->_store['max_money'])
            {
                $this->error("你最多可提现{$this->_store['max_money']}账户余额.");
                exit;
            }

            if($data['money'] > $this->_store['store_money'])
            {
                $this->error("你最多可提现{$this->_store['store_money']}账户余额.");
                exit;
            }


            $maxWithdrawADay = M("config")->where(array("name"=>"store_max_withdraw_money"))->getField("value");

            if($data['money'] > $maxWithdrawADay)
            {
                $this->error("每天只可提现{$maxWithdrawADay}元.");
                exit;
            }

            if (!empty($maxWithdrawADay)){
                $today = date('Y-m-d');
                $time = strtotime($today);
                $where = array('store_id'=>$this->_store['store_id'],'status'=>array("in",'0,1'),'create_time'=>array('gt',$time));
                $money = M("store_withdrawals")->where($where)->sum("money");
                if ($money >= $maxWithdrawADay){
                    $this->error('每天只可申请提现'.$maxWithdrawADay.'元');
                    exit;
                }
            }

            $data['order_sn'] = build_order_no();//订单号
            $withdrawals_id = M('StoreWithdrawals')->add($data);


            if($withdrawals_id!==false){
                $withdraw = new Withdraw();
                $re = $withdraw->store_withdraw($withdrawals_id);
                if ($re['success']){
                    //消息记录
                    save_user_news($this->user_id,1,$withdrawals_id,'您有条提现申请已受理');
                    $this->success($re['msg']);
                } else {
                    M('store_withdrawals')->where(array('id'=>$withdrawals_id))->delete();
                    $this->error($re['msg']);
                }
                $this->success("提现成功");
                exit;
            }else{
                $this->error('提交失败,联系客服!');
                exit;
            }
        }

        //会员中心顶部信息
        $this->_user_top();

        $map = array();
        $map['store_id'] = $this->_store['store_id'];
        $count = M('store_withdrawals')->where($map)->count();
        $page = new Page($count,16);
        $list = M('store_withdrawals')->where($map)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list',$list); // 下线
        if($_GET['is_ajax'])
        {
            $this->display('_store_withdrawals_list');
            exit;
        }
        $this->display();
    }


    //region 店铺推广二维码
    public function storeQrCode(){
        $store = M('store')->where(array('user_id'=>$this->user_id))->find();
        if (empty($store)){
            $this->error('您未入驻本商城系统',U('index'));die;
        }
        $end_time = time() + shop_share_time();
        //$ShareLink = urlencode("http://{$_SERVER[HTTP_HOST]}/index.php?m=Mobile&c=NewJoin&a=index&first_leader={$this->_seller['user_id']}&iskaidian=1&end_time={$end_time}"); //默认分享链接
        $ShareLink = "http://{$_SERVER['HTTP_HOST']}/index.php?m=Mobile&c=NewJoin&a=index&first_leader={$this->user_id}&iskaidian=1&end_time={$end_time}"; //默认分享链接
        //$qr_url = U('Home/Index/qr_code',array('data'=>$ShareLink),'',true);
        //if(get_column('users',array('user_id'=>$this->_seller['user_id']),'is_distribut') == 1)
        $logo = M('store')->where(array('store_id' => $store['store_id']))->getField('store_logo');
        if (!empty($logo)) $logo = 'http://' . $_SERVER['HTTP_HOST'] . $logo;
        $qrcode = new QrcodeController();
        $qr_url = $qrcode->create_qrcode($ShareLink, 4, 'L', $logo);
        if ($qr_url){
            $url = U('share_bg','','',true);
            $qr_url = $url . '?code='.urlencode($qr_url);
        }
        $this->assign('qr_url', $qr_url);
        $this->assign('dis_type',true);
        $this->display();
    }

    public function share_bg(){
        $qrcode = new QrcodeController();
        $qrcode->qrcode_bg(urldecode(I('code')),'',115,125);
    }

    //endregion

    //region 店铺收款码
    public function storeReceiptCode(){
        $store = M('store')->where(array('user_id'=>$this->user_id))->find();
        if (empty($store)){
            $this->error('您未入驻本商城系统',U('index'));die;
        }
        if ($store['role_id'] != 5){
            $this->error('您不是实体店商户',U('index'));die;
        }
        //$url = U('Mobile/User/scan_pay',array('store_id'=>$this->_store_id),'',true);
        $url = U('Mobile/User/scan_pay',array('store_id'=>$store['store_id'],'first_leader' => $this->user_id),'',true);
        $logo = M('store')->where(array('store_id' => $store['store_id']))->getField('store_logo');
        if (!empty($logo)) $logo = 'http://' . $_SERVER['HTTP_HOST'] . $logo;
        $qrcode = new QrcodeController();
        $qr_url = $qrcode->create_qrcode($url, 4, 'L', $logo);
        $this->assign('qr_url', $qr_url);
        $this->display();
    }
    //endregion

}