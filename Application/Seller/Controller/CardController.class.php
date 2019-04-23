<?php
/**
 * 实体卡券管理
 * By~ Mr-X
 */
namespace Seller\Controller;

use Api\Controller\WxpayController;
use Common\Controller\QrcodeController;
use Seller\Logic\UsersLogic;
use Seller\Logic\CardOrderLogic;
use Think\AjaxPage;
use Think\Exception;
use Think\Page;

class CardController extends BaseController
{

    public  $order_status;
    public  $shipping_status;
    public  $pay_status;
    /*
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
        //C('TOKEN_ON',false); // 关闭表单令牌验证
        // 订单 支付 发货状态
        $this->order_status = C('ORDER_STATUS');
        $this->pay_status = C('PAY_STATUS');
        $this->shipping_status = C('SHIPPING_STATUS');
        $this->assign('order_status',$this->order_status);
        $this->assign('pay_status',$this->pay_status);
        $this->assign('shipping_status',$this->shipping_status);
    }

    //卡券列表  减少一步流程
//    public function index(){
//        $map = array();
//        $map['is_on_sale'] = 1;
//        $map['status'] = 1;
//        $list = M('card')->where($map)->order('sort asc , create_time desc')->select();
//        $this->assign('list',$list);
//        $this->display();
//    }

    //购买卡券
    public function index(){ //buy_card
        //$card_id = I('card_id');
        //$card_id = rtrim($card_id,',');//去除末尾多余的逗号
        $map = array();
        $map['is_on_sale'] = 1;
        $map['status'] = 1;
        //$map['card_id'] = array('in',$card_id);
        $map['store_count'] = array('gt',0);
        $list = M('card')->where($map)->select();
//        if (empty($list)){
//            $this->error('卡券不存在或已下架',U('card/index'));die;
//        }
        $this->assign('list',$list);
        $this->display('buy_card');
    }

    //购买卡券流程2
    public function buy_card2(){
        $data = I('post.');
        try{
            if (empty($data)) throw new Exception('请重新选择卡券');
            session('card_cart',$data);
            $this->ajaxReturn(array('status'=>1,'url'=>U('address_list',array('token'=>get_token('card_cart')))));
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
        }

    }

    ///购买卡券流程3 提交订单
    public function order(){
        try{
            $card = $this->_check_order();
            //库存验证
            $this->_check_order_num($card);

            $address_id = I('address_id');
            if ($address_id < 0 || $address_id == 'undefined') throw new Exception('请先选择或创建收货地址');
            //收货地址
            $address = M('user_address')->where(array('address_id'=>$address_id,'user_id'=>$this->_seller['user_id']))->find();
            if (empty($address)) throw new Exception('请重新选择或创建收货地址');
            //创建住订单
            $sn = build_order_no('cd');//订单号
            $order_id = $this->_submit_order($card,$address,$sn,I('user_note',''));
            if ($order_id === false) throw new Exception('创建订单失败,请重试');
            //创建子订单
            $res = $this->_submit_suborder($card,$order_id);
            if ($res === false){ //子订单创建失败 删除住订单
                M('card_order')->where(array('order_id'=>$order_id))->delete();
                throw new Exception('创建子订单失败,请重试');
            }
            //清除token 防止重复下单
            clear_token('card_cart');
            //卡券订单日志记录
            card_order_log($order_id,$this->_seller['user_id'],'创建订单','购买卡券');
            $this->ajaxReturn(array('status'=>0,'url'=>U('pay',array('order_id'=>$order_id))));
        }catch (Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
            //$this->error($e->getMessage());
        }
    }

    //检测卡券库存
    protected function _check_order_num($card=[],$order_id=0){
        //如果是后期支付订单库存判断
        if (empty($card) && $order_id >0){
            $card = M('card_suborder')->where(array('order_id'=>$order_id))->select();
        }
        if (empty($card)) throw new Exception('订单错误');
        //判断库存
        foreach ($card as $key => $vo){
            $num = $vo['store_count'];
            if (empty($card)) $num = $vo['card_num'];
            if (get_column('card',array('card_id'=>$vo['card_id']),'store_count') < $num) {
                throw new Exception(get_column('card',array('card_id'=>$vo['card_id']),'name').'库存不足');
                break;
            }
        }
    }

    //支付二维码页面
    public function pay(){
        try{
            $order_id = I('order_id');
            $order = M('card_order')->where(array('order_id'=>$order_id,'user_id'=>$this->_seller['user_id'],'pay_status'=>0))->find();
            //订单状态判断
            if (empty($order)) throw new Exception('该订单已支付或不存在');

            //库存验证
            $this->_check_order_num(null,$order_id);

            //微信付款二维码
            require_once("plugins/payment/weixin/weixin.class.php");
            $wx = new \weixin();
            $qr_path = $wx->get_code2('卡券购买',$order['order_sn'],$order['total_pay'],$order['order_id']);
            if (empty($qr_path)) {
                M('card_order')->where(array('order_id'=>$order_id))->save(array('order_status'=>5));
                throw new Exception('订单已失效，请重新下单');
            }
            //$qr_path = U('Home/Index/qr_code',array('data'=>urlencode($qr_path)),'',true);
            $logo = M('wx_user')->getField('headerpic');
            if (!empty($logo)) $logo = 'http://'.$_SERVER['HTTP_HOST'].$logo;
            $qrcode = new QrcodeController();
            $qr_path = $qrcode->create_qrcode($qr_path,4, 'L',$logo);
            //$qr_path = $qrcode->create_qrcode(urlencode($qr_path));

            $this->assign('qr_path',$qr_path);
            $this->assign('sn',$order['order_sn']);
            $this->display();
        }catch(Exception $e){
            $this->error($e->getMessage());
        }
    }

    //ajax 轮询获取订单支付状态
    public function get_order_status(){
        try{
            $sn = I('sn');
            $status = M('card_order')->where(array('order_sn'=>$sn))->getField('pay_status');
            if ($status == 1){
                $this->ajaxReturn(array('status'=>200,'order_msg'=>'支付成功'));
            }
            //$scan_qr = cookie('scan_qr');
            /*if ($scan_qr == true){
                //session('scan_qr',null);
                $this->ajaxReturn(array('status'=>201,'order_msg'=>'扫码成功'));
            }else{
                $this->ajaxReturn(array('status'=>0,'info'=>$scan_qr.'-'.session('pay_code')));
            }*/
            $this->ajaxReturn(array('status'=>0,'order_msg'=>'waiting'));
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
        }
    }

    //创建主订单
    protected function _submit_order($card = [],$address = [],$sn = '',$user_note = ''){
        #region 计算需支付金额
        $card_price = 0; //卡券价格
        $postage = 0;    //邮费
        $coupon_price = 0;//优惠价格
        foreach ($card as $key => $vo){
            $map = array('card_id'=>$vo['card_id']);
            // 单价 * 数量 = 卡券总价格
            $card_price += get_column('card',$map,'price') * $vo['store_count'];

            //默认单个优惠价 * 优惠价格 = 总优惠价格
            $coupon_price += get_column('card',$map,'default_rebate') * $vo['store_count'];

            // 判断是否包邮
            if (get_column('card',$map,'is_free_shipping') != 1){
                $postage += get_column('card',$map,'postage');//邮费
            }
        }
        // 卡券总价 + 邮费 - 默认优惠 = 实际支付总价
        $total_price = $card_price + $postage - $coupon_price;
        if ($total_price <= 0) throw new Exception('订单错误');

        #endregion

        //主订单信息构造
        $order = array(
            'order_sn'      =>  $sn,//订单号
            'user_id'       =>  $this->_seller['user_id'],//用户id
            'store_id'      =>  $this->_seller['store_id'],//用户id
            'need_pay'      =>  $card_price + $postage,//需支付 卡费+邮费
            'total_pay'     =>  $total_price,//实际支付   未有折扣即等于需支付金额
            'total_card'    =>  $card_price,//卡券总金额
            'postage'       =>  $postage,//邮费
            'card_price'    =>  $card_price,//卡券总计
            'coupon_price'  =>  $coupon_price,//优惠总计
            'order_status'  =>  0,//订单状态
            'shipping_status'=> 0,//发货状态
            'pay_status'    =>  0,//支付状态
            'pay_code'      =>  'weixin', //默认为微信
            'pay_name'      =>  '微信支付',//get_column('Plugin',array('type'=>'payment'),'code,name')['weixin'],
            'consignee'     =>  $address['consignee'],//收货人
            'country'       =>  $address['country'],//国家
            'province'      =>  $address['province'],//省份
            'city'          =>  $address['city'],//城市
            'district'      =>  $address['district'],//区县
            'twon'          =>  $address['twon'],//乡政
            'address'       =>  $address['address'],//地址
            'zipcode'       =>  $address['zipcode'],//邮编
            'mobile'        =>  $address['mobile'],//手机
            'email'         =>  $address['email'],//邮箱
            'shipping_code' =>  $address['shipping_code'],//物流号
            'user_note'     =>  $user_note,//用户备注
            'create_time'   =>  time(),//创建时间
        );
        return M('card_order')->add($order);
    }

    //创建子订单
    protected function _submit_suborder($card,$order_id){
        $data = array();
        foreach ($card as $key => $vo){
            $_card = M('card')->where(array('card_id'=>$vo['card_id']))->find();
            //构造子订单信息
            $data[] = array(
                'order_id'      =>  $order_id,//主订单号
                'user_id'       =>  $this->_seller['user_id'],//用户id
                'card_id'       =>  $_card['card_id'],//卡券id
                'card_name'     =>  $_card['name'],//卡券名称
                'card_price'    =>  $_card['price'],//卡券单价
                'default_rebate'=>  $_card['default_rebate'],//默认优惠价格
                'postage'       =>  $_card['is_free_shipping'] !=1 ?$_card['postage'] : 0,//邮费
                'card_num'      =>  $vo['store_count'],//购买数量
                'rate'          =>  $_card['rate'],//三级分销的金额
                'first_rate'    =>  $_card['first_rate'],//一级佣金比例
                'second_rate'   =>  $_card['second_rate'],//二级佣金比例
                'third_rate'    =>  $_card['third_rate'],//三级佣金比例
                'fourth_rate'   =>  $_card['fourth_rate'],//四级佣金比例
                'fifth_rate'    =>  $_card['fifth_rate'],//五级佣金比例
            );
        }
        if (empty($data)) return false;
        return M('card_suborder')->addAll($data);
    }

    //检测订单数据
    protected function _check_order(){
        $card = session('card_cart');
        $token = I('token');
        if (empty($card))
            throw new Exception('信息已过期');
        if (!check_token('card_cart',$token))
            throw new Exception('请勿重复提交');
        return $card;
    }


    /*
     * 用户地址列表
     */
    public function address_list(){
        $address_lists = get_user_address_list($this->_seller['user_id']);
        $region_list = get_region_list();
        $this->assign('region_list',$region_list);
        $this->assign('lists',$address_lists);
        $this->assign('active','address_list');
        #region 如果存在session card_cart
        $token = I('token');
        if (!empty($token)){
            $this->assign('token',$token);
            $card = session('card_cart');
            if (!empty($card)) {
                $this->assign('card',$card);
            }
        }
        #endregion
        $this->display();
    }

    /*
     * 添加地址
     */
    public function add_address(){
        header("Content-type:text/html;charset=utf-8");
        if(IS_POST){
            $logic = new UsersLogic();
            $data = $logic->add_address($this->_seller['user_id'],0,I('post.'));
            if($data['status'] != 1)
                exit('<script>alert("'.$data['msg'].'");history.go(-1);</script>');
            $call_back = $_REQUEST['call_back'];
            echo "<script>parent.{$call_back}('success');</script>";
            exit(); // 成功 回调closeWindow方法 并返回新增的id
        }
        $p = M('region')->where(array('parent_id'=>0,'level'=> 1))->select();
        $this->assign('province',$p);
        $this->display('edit_address');

    }

    /*
     * 地址编辑
     */
    public function edit_address(){
        header("Content-type:text/html;charset=utf-8");
        $id = I('get.id');
        $address = M('user_address')->where(array('address_id'=>$id,'user_id'=> $this->_seller['user_id']))->find();
        if(IS_POST){
            $logic = new UsersLogic();
            $data = $logic->add_address($this->_seller['user_id'],$id,I('post.'));
            if($data['status'] != 1)
                exit('<script>alert("'.$data['msg'].'");history.go(-1);</script>');

            $call_back = $_REQUEST['call_back'];
            echo "<script>parent.{$call_back}('success');</script>";
            exit(); // 成功 回调closeWindow方法 并返回新增的id
        }
        //获取省份
        $p = M('region')->where(array('parent_id'=>0,'level'=> 1))->select();
        $c = M('region')->where(array('parent_id'=>$address['province'],'level'=> 2))->select();
        $d = M('region')->where(array('parent_id'=>$address['city'],'level'=> 3))->select();
        if($address['twon']){
            $e = M('region')->where(array('parent_id'=>$address['district'],'level'=>4))->select();
            $this->assign('twon',$e);
        }

        $this->assign('province',$p);
        $this->assign('city',$c);
        $this->assign('district',$d);
        $this->assign('address',$address);
        $this->display('edit_address');
    }

    /*
     * 设置默认收货地址
     */
    public function set_default(){
        $id = I('get.id');
        M('user_address')->where(array('user_id'=>$this->_seller['user_id']))->save(array('is_default'=>0));
        $row = M('user_address')->where(array('user_id'=>$this->_seller['user_id'],'address_id'=>$id))->save(array('is_default'=>1));
        if(!$row)
            $this->error('操作失败');
        $this->success("操作成功");
    }

    /*
     * 地址删除
     */
    public function del_address(){
        $id = I('get.id');
        $address = M('user_address')->where("address_id = $id")->find();
        $row = M('user_address')->where(array('user_id'=>$this->_seller['user_id'],'address_id'=>$id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if($address['is_default'] == 1)
        {
            $address2 = M('user_address')->where("user_id = {$this->_seller['user_id']}")->find();
            $address2 && M('user_address')->where("address_id = {$address2['address_id']}")->save(array('is_default'=>1));
        }
        if(!$row)
            $this->error('操作失败');
        else
            $this->success("操作成功");
    }

    #region 订单管理
    //订单列表
    public function order_list(){
        $begin = date('Y/m/d',(time()-30*60*60*24));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $this->assign('timegap',$begin.'-'.$end);
        $this->display();
    }

    //ajax 分页列表
    public function ajax_order_list(){
        $orderLogic = new CardOrderLogic();
        $timegap = I('timegap');
        if($timegap){
            $gap = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end = strtotime($gap[1]);
        }
        // 搜索条件 STORE_ID
        $condition = array();
        $condition['user_id'] = $this->_seller['user_id'];
        I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
        if($begin && $end){
            $condition['create_time'] = array('between',"$begin,$end");
        }

        I('order_sn') ? $condition['order_sn'] = trim(I('order_sn')) : false;
        I('order_status') != '' ? $condition['order_status'] = I('order_status') : false;
        I('pay_status') != '' ? $condition['pay_status'] = I('pay_status') : false;
        I('pay_code') != '' ? $condition['pay_code'] = I('pay_code') : false;
        I('shipping_status') != '' ? $condition['shipping_status'] = I('shipping_status') : false;

        $sort_order = I('order_by','DESC').' '.I('sort');
        $count = M('card_order')->where($condition)->count();
        $Page  = new AjaxPage($count,20);
        $show = $Page->show();
        //获取订单列表
        $orderList = $orderLogic->getOrderList($condition,$sort_order,$Page->firstRow,$Page->listRows);
        $this->assign('orderList',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->display('_order_list');
    }

    /**
     * 订单详情
     * @param int $order_id 订单id
     */
    public function detail($order_id){
        $orderLogic = new CardOrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $orderCards = $orderLogic->getOrderCards($order_id);
        $button = $orderLogic->getCardOrderButton($order);
        // 获取操作记录
        $action_log = M('card_order_action')->where(array('order_id'=>$order_id))->order('log_time desc')->select();
        $this->assign('order',$order);
        $this->assign('action_log',$action_log);
        $this->assign('orderCards',$orderCards);
        $split = count($orderCards) >1 ? 1 : 0;
        foreach ($orderCards as $val){
            if($val['card_num']>1){
                $split = 1;
            }
        }
        #region 发货记录
        if (get_column('card_delivery_doc',array('order_id'=>$order_id)) > 0){
            $delivery_record = M('card_delivery_doc')->join('LEFT JOIN __SELLER__ ON __SELLER__.seller_id = __CARD_DELIVERY_DOC__.admin_id')->where(array('order_id'=>$order_id))->select();
            $this->assign('delivery_record',$delivery_record);//发货记录
        }
        #endregion
        $this->assign('split',$split);
        if (!empty($button)){
            $this->assign('button',$button);
        }
        $this->display('order_detail');
    }
    /**
     * 订单操作
     */
    public function order_action(){
        $orderLogic = new CardOrderLogic();
        $action = I('get.type');
        $order_id = I('get.order_id');
        if($action && $order_id){
            $a = $orderLogic->orderProcessHandle($order_id,$action);
            $admin_id = $this->_seller['user_id']; // 当前操作的管理员
            $res = $orderLogic->orderActionLog($order_id,$action,I('note'),$admin_id);
            if($res!==false && $a!==false){
                if ($action == 'remove')
                    $this->ajaxReturn(array('status' => 1,'msg' => '操作成功','data'=>array('url'=>U('order_list'))));
                if ($action == 'delivery_confirm')
                    $this->ajaxReturn($a);
                $this->ajaxReturn(array('status' => 1,'msg' => '操作成功'));
            }else{
                $this->ajaxReturn(array('status' => 0,'msg' => '操作失败'));
            }
        }else{
            $this->error('参数错误',U('detail',array('order_id'=>$order_id)));
        }
    }

    #endregion


    #region 分成管理
    //分成日志列表
    public function rebate_log()
    {
        $model = M("card_rebate_log");
        $status = I('status');
        $user_id = $this->_seller['user_id'];
        $order_sn = I('order_sn');
        $create_time = I('create_time');
        $create_time = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));

        $create_time2 = explode(' - ',$create_time);
        $where = " create_time >= '".strtotime($create_time2[0])."' and create_time <= '".strtotime($create_time2[1])."' ";

        if($status === '0' || $status > 0)
            $where .= " and status = $status ";
        $user_id && $where .= " and user_id = $user_id ";
        $order_sn && $where .= " and order_sn like '%{$order_sn}%' ";

        $count = $model->where($where)->count();
        $Page  = new Page($count,16);
        $list = $model->where($where)->order("`id` desc")->limit($Page->firstRow.','.$Page->listRows)->select();

        $get_user_id = get_arr_column($list, 'user_id'); // 获佣用户
        $buy_user_id = get_arr_column($list, 'user_id'); // 购买用户
        $user_id_arr = array_merge($get_user_id,$buy_user_id);
        if(!empty($user_id_arr))
            $user_arr = M('users')->where("user_id in (".  implode(',', $user_id_arr).")")->select();
        $this->assign('user_arr',$user_arr);

        $this->assign('create_time',$create_time);
        $show  = $Page->show();
        $this->assign('show',$show);
        $this->assign('list',$list);
        C('TOKEN_ON',false);
        $this->display();
    }
    #endregion


}