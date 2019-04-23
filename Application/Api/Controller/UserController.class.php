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
namespace Api\Controller;
use Home\Logic\StoreLogic;
use Think\Controller;
use Home\Logic\UsersLogic;
use Think\Exception;

class UserController extends BaseController {
    public $userLogic;

    /**
     * 析构流函数
     */
    public function  __construct() {
        parent::__construct();

    }

    public function _initialize(){
        parent::_initialize();
        $this->userLogic = new UsersLogic();
    }


    /**
     *  登录
     */
    public function login(){
        try{
            $username = I('username','');
            $password = I('password','');
            $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
            $data = $this->userLogic->app_login($username,$password);

            if($data['status'] != 1)
                $this->ajaxReturn($data);

            //构造完整图片链接
            get_full_link($data['result']['head_pic']);

            $cartLogic = new \Home\Logic\CartLogic();
            $cartLogic->login_cart_handle($unique_id,$data['result']['user_id']); // 用户登录后 需要对购物车 一些操作
        }catch (Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }
    /*
     * 第三方登录
     */
    public function thirdLogin(){
        $map['openid'] = I('openid','');
        $map['oauth'] = I('from','');
        $map['nickname'] = I('nickname','');
        $map['head_pic'] = I('head_pic','');
        $data = $this->userLogic->thirdLogin($map);
        //构造完整图片链接
        get_full_link($data['result']['head_pic']);
        $this->ajaxReturn($data);
    }

    /**
     * 用户注册
     */
    public function reg(){
        $username = I('post.username','');
        $password = I('post.password','');
        $password2 = I('post.password2','');
        $unique_id = I('unique_id');
        //是否开启注册验证码机制
        try{
            if(check_mobile($username) && TpCache('sms.regis_sms_enable')){
                $code = I('post.code');
                if(empty($code))
                    throw new Exception('请输入验证码',-1);
                $check_code = $this->userLogic->sms_code_verify($username,$code,$unique_id);
                if($check_code['status'] != 1)
                    throw new Exception($check_code['msg'],-2);
            }
            $data = $this->userLogic->reg($username,$password,$password2);
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }

    /*
     * 获取用户信息
     */
    public function userInfo(){
        //$user_id = I('user_id');
        $data = $this->userLogic->get_info($this->user_id);
        $this->ajaxReturn($data);
    }

    /*
     *更新用户信息
     */
    public function updateUserInfo(){
        if(IS_POST){
            //$user_id = I('user_id');
            try{
                if(!$this->user_id)
                    throw new Exception('参数缺省',-1);

                I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
                I('post.qq') ? $post['qq'] = I('post.qq') : false;  //QQ号码
                I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
                I('post.sex') ? $post['sex'] = I('post.sex') : false;  // 性别
                I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;  // 生日
                I('post.province') ? $post['province'] = I('post.province') : false;  //省份
                I('post.city') ? $post['city'] = I('post.city') : false;  // 城市
                I('post.district') ? $post['district'] = I('post.district') : false;  //地区

                if(!$this->userLogic->update_info($this->user_id,$post))
                    throw new Exception('更新失败',-2);

                $data = array('status'=>1,'msg'=>'更新成功','result'=>'');
            }catch(Exception $e){
                $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
            }
            $this->ajaxReturn($data);
        }
    }

    /*
     * 修改用户密码
     */
    public function password(){
        if(IS_POST){
            //$user_id = I('user_id');
            try{
                if(!$this->user_id)
                    throw new Exception('参数缺省',-1);
                $data = $this->userLogic->password($this->user_id,I('post.old_password'),I('post.new_password'),I('post.confirm_password')); // 获取用户信息
            }catch (Exception $e){
                $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
            }
            $this->ajaxReturn($data);
        }
    }

    /**
     * 获取收货地址
     */
    public function getAddressList(){
        //$user_id = I('user_id');
        try{
            if(!$this->user_id)
                throw new Exception('参数缺省',-1);
            $address = M('user_address')->where(array('user_id'=>$this->user_id))->select();
            $data = array('status'=>1,'msg'=>'获取成功','result'=>$address);
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }

    /*
     * 添加地址
     */
    public function addAddress(){
        //$user_id = I('user_id',0);
        try{
            if(!$this->user_id)
                throw new Exception('参数缺省',-1);
            $address_id = I('address_id',0);
            $data = $this->userLogic->add_address($this->user_id,$address_id,I('post.')); // 获取用户信息
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }
    /*
     * 地址删除
     */
    public function del_address(){
        try{
            $id = I('address_id',0);
            if(!$this->user_id || !$id)
                throw new Exception('参数缺省',-1);
            $address = M('user_address')->where("address_id = $id")->find();
            $row = M('user_address')->where(array('user_id'=>$this->user_id,'address_id'=>$id))->delete();

            // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
            if($address['is_default'] == 1)
            {
                $address = M('user_address')->where("user_id = {$this->user_id}")->find();

                //@mobify by wangqh {
                if($address) {
                    M('user_address')->where("address_id = {$address['address_id']}")->save(array('is_default'=>1));
                }//@}

            }

            //@mobify by wangqh
            if($row)
                $data = array('status'=>1,'msg'=>'删除成功','result'=>'');
            else
                throw new Exception('删除失败',-1);

        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }

    /*
     * 设置默认收货地址
     */
    public function setDefaultAddress(){
//        $user_id = I('user_id',0);
        try{
            $address_id = I('address_id',0);
            if(!$this->user_id || !$address_id)
                throw new Exception('参数缺省',-1);
            if (get_count('user_address',array('user_id'=>$this->user_id,'address_id'=>$address_id)) == 0)
                throw new Exception('该地址不存在',-3);

            $res = $this->userLogic->set_default($this->user_id,$address_id); // 获取用户信息
            if(!$res)
                throw new Exception('操作失败',-2);
            $data = array('status'=>1,'msg'=>'操作成功','result'=>'');
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }

    /*
     * 获取优惠券列表
     */
    public function getCouponList(){
        //$user_id = I('user_id',0);
        try{
            if(!$this->user_id)
                throw new Exception('参数缺省',-1);
            $data = $this->userLogic->get_coupon($this->user_id,$_REQUEST['type']);
            unset($data['show']);
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }
    /*
     * 获取商品收藏列表
     */
    public function getGoodsCollect(){
        //$user_id = I('user_id',0);
        try{
            if(!$this->user_id)
                throw new Exception('参数缺省',-1);
            $data = $this->userLogic->get_goods_collect($this->user_id);
            //构造完整图片链接
            get_full_link($data['result'],'original_img');

            unset($data['show']);
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }

    /*
     * 用户订单列表
     */
    public function getOrderList(){
        // $user_id = I('user_id',0);
        try{
            $type = I('type','');
            if(!$this->user_id)
                throw new Exception('参数缺省',-1);
            //条件搜索
            //I('field') && $map[I('field')] = I('value');
            //I('type') && $map['type'] = I('type');
            //$map['user_id'] = $user_id;
            $map = " user_id = {$this->user_id} ";
            $map = $type ? $map.C($type) : $map;


            if(I('type') )
                $count = M('order')->where($map)->count();
            $Page = new \Think\Page($count,10);

            $show = $Page->show();
            $order_str = "order_id DESC";
            $order_list = M('order')->order($order_str)->where($map)->limit($Page->firstRow.','.$Page->listRows)->select();

            //获取订单对应的店铺
            $stores = M('store')->where("store_id in (SELECT DISTINCT store_id FROM ".C('DB_PREFIX')."order WHERE ".$map.")")->getField("store_id , store_name");

            //获取订单商品
            foreach($order_list as $k=>$v){
                $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
                //订单总额
                //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount'];
                $data = $this->userLogic->get_order_goods($v['order_id']);
                //构造完整图片链接
                get_full_link($data['result'],'original_img');

                $order_list[$k]['goods_list'] = $data['result'];

                //设置每个订单对应的店铺名称
                foreach ($stores as $sk => $sv){
                    if($order_list[$k]['store_id'] == $sk){
                        $order_list[$k]['store_name'] = $sv;
                        break;
                    }
                }
            }
            $res = array('status'=>1,'msg'=>'获取成功','result'=>$order_list);
        }catch(Exception $e){
            $res = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($res);
    }
    /*
    * 获取订单详情
    */
    public function getOrderDetail(){
        //$user_id = I('user_id',0);
        try{
            if(!$this->user_id)
                throw new Exception('参数缺省',-1);
            $order_id = I('order_id');
            $map = array();
            if($order_id){
                $map['order_id'] = $order_id;
            }else{
                $map['master_order_sn'] = I('sn');//主订单号
            }
            if (empty($map))
                throw new Exception('订单参数缺省',-2);

            $map['user_id'] = $this->user_id;

            $order_info = M('order')->where($map)->find();

            $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性

            if(!$order_info)
                throw new Exception('订单不存在',-2);

            //获取店铺名称
            $store = M('store')->where("store_id = ".$order_info['store_id'])->find();

            $order_info['store_name'] = $store['store_name'];
            $order_info['store_qq'] = $store['store_qq'];
            $order_info['store_phone'] = $store['store_phone'];

            $invoice_no = M('DeliveryDoc')->where("order_id =".$order_info['store_id'])->getField('invoice_no',true);
            $order_info['invoice_no'] = implode(' , ', $invoice_no);
            // 获取 最新的 一次发货时间
            $order_info['shipping_time'] = M('DeliveryDoc')->where("order_id =".$order_info['store_id'])->order('id desc')->getField('create_time');

            //获取订单商品
            $data = $this->userLogic->get_order_goods($order_info['order_id']);
            //构造完整图片链接
            get_full_link($data['result'],'original_img');

            $order_info['goods_list'] = $data['result'];

            //$order_info['total_fee'] = $order_info['goods_price'] + $order_info['shipping_price'] - $order_info['integral_money'] -$order_info['coupon_price'] - $order_info['discount'];
            $res = array('status'=>1,'msg'=>'获取成功','result'=>$order_info);
        }catch(Exception $e){
            $res = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($res);
    }

    /**
     * 取消订单
     */
    public function cancelOrder(){
        //        $user_id = I('user_id',0);
        try{
            $id = I('order_id');
            if(!$this->user_id || !$id)
                throw new Exception('参数缺省',-1);
            $data = $this->userLogic->cancel_order($this->user_id,$id);
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }

    /**
     * 发送手机注册验证码
     * http://www.tp-shop.cn/index.php?m=Api&c=User&a=send_sms_reg_code&mobile=13800138006&unique_id=123456
     */
    public function send_sms_reg_code(){
        try{
            $mobile = I('mobile');
            $unique_id = I('unique_id');
            if(!check_mobile($mobile))
                throw new Exception('手机号码格式有误',-1);
            $code =  rand(1000,9999);
            $send = $this->userLogic->sms_log($mobile,$code,$unique_id);
            if($send['status'] != 1)
                throw new Exception($send['msg'],-2);
            $data = array('status'=>1,'msg'=>'验证码已发送，请注意查收');
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }

    /**
     *  收货确认
     */
    public function orderConfirm(){
        //$user_id = I('user_id',0);
        try{
            $id = I('order_id',0);
            if(!$this->user_id || !$id)
                throw new Exception('参数有误',-1);
            $data = confirm_order($id,$this->user_id);
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }


    /*
     *添加评论
     */
    public function add_comment(){
        try{
            // 晒图片
            $comment_img = '';
            if($_FILES[img_file][tmp_name][0])
            {
                $upload = new \Think\Upload();// 实例化上传类
                $upload->maxSize   =    $map['author'] = (1024*1024*3);// 设置附件上传大小 管理员10M  否则 3M
                $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
                $upload->rootPath  =     './Public/upload/comment/'; // 设置附件上传根目录
                $upload->replace  =     true; // 存在同名文件是否是覆盖，默认为false
                //$upload->saveName  =   'file_'.$id; // 存在同名文件是否是覆盖，默认为false
                // 上传文件
                $info   =   $upload->upload();
                if(!$info) {// 上传错误提示错误信息
                    throw new Exception($upload->getError(),-1);
                }else{
                    foreach($info as $key => $val)
                    {
                        $comment_img[] = '/Public/upload/comment/'.$val['savepath'].$val['savename'];
                    }
                    $comment_img = serialize($comment_img); // 上传的图片文件
                }
            }

            $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
            //$user_id = I('user_id'); // 用户id
            $user_info = M('users')->where("user_id = {$this->user_id}")->find();

            $add['goods_id'] = I('goods_id');
            $add['email'] = $user_info['email'];
            //$add['nick'] = $user_info['nickname'];
            $add['username'] = $user_info['nickname'];
            $add['order_id'] = I('order_id');
            $add['service_rank'] = I('service_rank');
            $add['deliver_rank'] = I('deliver_rank');
            $add['goods_rank'] = I('goods_rank');
            // $add['content'] = htmlspecialchars(I('post.content'));
            $add['content'] = I('content');
            $add['img'] = $comment_img;
            $add['add_time'] = time();
            $add['ip_address'] = $_SERVER['REMOTE_ADDR'];
            $add['user_id'] = $this->user_id;

            //添加评论
            $data = $this->userLogic->add_comment($add);
        }catch (Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }

    /*
     * 账户资金
     */
    public function account(){
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        // $user_id = I('user_id'); // 用户id
        //获取账户资金记录
        try{
            $data = $this->userLogic->get_account_log($this->user_id,I('get.type'));
            $account_log = $data['result'];
            $data = array('status'=>1,'msg'=>'获取成功','result'=>$account_log);
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }

    /**
     * 退换货列表
     */
    public function return_goods_list()
    {

        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        // $user_id = I('user_id'); // 用户id
        try{
            $count = M('return_goods')->where("user_id = {$this->user_id}")->count();
            $page = new \Think\Page($count,4);
            $list = M('return_goods')->where("user_id = {$this->user_id}")->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
            $goods_id_arr = get_arr_column($list, 'goods_id');
            if(!empty($goods_id_arr))
                $goodsList = M('goods')->where("goods_id in (".  implode(',',$goods_id_arr).")")->getField('goods_id,goods_name');
            foreach ($list as $key => $val)
            {
                $val['goods_name'] = $goodsList[$val[goods_id]];
                $list[$key] = $val;
            }
            //$this->assign('page', $page->show());// 赋值分页输出
            $data = array('status'=>1,'msg'=>'获取成功','result'=>$list);
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }


    /**
     *  售后 详情
     */
    public function return_goods_info()
    {
        try{
            $id = I('id',0);
            $return_goods = M('return_goods')->where("id = $id")->find();
            if($return_goods['imgs'])
                $return_goods['imgs'] = explode(',', $return_goods['imgs']);
            $goods = M('goods')->where("goods_id = {$return_goods['goods_id']} ")->find();
            $return_goods['goods_name'] = $goods['goods_name'];
            $data = array('status'=>1,'msg'=>'获取成功','result'=>$return_goods);
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }


    /**
     * 申请退货状态
     */
    public function return_goods_status()
    {
        $order_id = I('order_id',0);
        $goods_id = I('goods_id',0);
        $spec_key = I('spec_key','');

        $return_goods = M('return_goods')->where("order_id = $order_id and goods_id = $goods_id and spec_key = '$spec_key' and status in(0,1)")->find();
        if(!empty($return_goods))
            $this->ajaxReturn(array('status'=>1,'msg'=>'已经在申请退货中..','result'=>$return_goods['id']));
        else
            $this->ajaxReturn(array('status'=>2,'msg'=>'可以去申请退货','result'=>-1));
    }
    /**
     * 申请退货
     */
    public function return_goods()
    {
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        //$user_id = I('user_id'); // 用户id
        try{
            $order_id = I('order_id',0);
            $order_sn = I('order_sn',0);
            $goods_id = I('goods_id',0);
            $type = I('type',0); // 0 退货  1为换货
            $reason = I('reason',''); // 问题描述
            $spec_key = I('spec_key');

            if(empty($order_id) || empty($order_sn) || empty($goods_id)|| empty($this->user_id)|| empty($type)|| empty($reason))
                throw new Exception('参数不齐',-1);

            $c = M('order')->where("order_id = $order_id and user_id = {$this->user_id}")->count();
            if($c == 0)
            {
                throw new Exception('非法操作',-3);
            }

            $return_goods = M('return_goods')->where("order_id = $order_id and goods_id = $goods_id and spec_key = '$spec_key' and status in(0,1)")->find();
            if(!empty($return_goods))
            {
                throw new Exception('已经提交过退货申请',-2);
            }
            if(IS_POST)
            {
                // 晒图片
                if($_FILES['img_file']['tmp_name'][0])
                {
                    $upload = new \Think\Upload();// 实例化上传类
                    $upload->maxSize   =    $map['author'] = (1024*1024*3);// 设置附件上传大小 管理员10M  否则 3M
                    $upload->exts      =    array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
                    $upload->rootPath  =    './Public/upload/return_goods/'; // 设置附件上传根目录
                    $upload->replace   =    true; // 存在同名文件是否是覆盖，默认为false
                    //$upload->saveName  =  'file_'.$id; // 存在同名文件是否是覆盖，默认为false
                    // 上传文件
                    $upinfo  =  $upload->upload();
                    if(!$upinfo) {// 上传错误提示错误信息
                        throw new Exception($upload->getError(),-4);
                    }else{
                        foreach($upinfo as $key => $val)
                        {
                            $return_imgs[] = '/Public/upload/return_goods/'.$val['savepath'].$val['savename'];
                        }
                        $data['imgs'] = implode(',', $return_imgs);// 上传的图片文件
                    }
                }
                $data['order_id'] = $order_id;
                $data['order_sn'] = $order_sn;
                $data['goods_id'] = $goods_id;
                $data['addtime'] = time();
                $data['user_id'] = $this->user_id;
                $data['type'] = $type; // 服务类型  退货 或者 换货
                $data['reason'] = $reason; // 问题描述
                $data['spec_key'] = $spec_key; // 商品规格
                $res= M('return_goods')->add($data);

                if ($res === false)
                    throw new Exception('申请失败',-5);
                $data = array('status'=>1,'msg'=>'申请成功,客服第一时间会帮你处理!');
            }
        }catch(Exception $e){
            $data = array('status'=>$e->getCode(),'msg'=>$e->getMessage(),'result'=>'');
        }
        $this->ajaxReturn($data);
    }
    /**
     * 获取收藏店铺列表集合, 只用于查询用户收藏的店铺, 页面判断用, 区别于getUserCollectStore
     */
    public function getCollectStoreData()
    {
        $where = array('user_id' => $this->user_id);
        $storeCollects = M('store_collect')->where($where)->select();
        $json_arr = array('status' => 1, 'msg' => '获取成功', 'result' => $storeCollects);
        $this->ajaxReturn($json_arr);
    }

    /**
     * @author dyr
     * 获取用户收藏店铺列表
     */
    public function getUserCollectStore()
    {
        $page = I('page', 1);
        $store_list = D('store')->getUserCollectStore($this->user_id,$page,10);
        $json_arr = array('status' => 1, 'msg' => '获取成功', 'result' => $store_list);
        $this->ajaxReturn($json_arr);
    }
}