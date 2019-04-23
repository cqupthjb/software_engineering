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
use Think\Controller;
use Api\Logic\GoodsLogic;
use Think\Page;
class CartController extends BaseController {
    
    public $cartLogic; // 购物车逻辑操作类
    /**
     * 析构流函数
     */
    public function  __construct() {   
        parent::__construct();                
        $this->cartLogic = new \Home\Logic\CartLogic();                     
        
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        //$user_id = I("user_id",0); // 用户id                       
        // 给用户计算会员价 登录前后不一样
        if($this->user_id){
            $user = M('users')->where("user_id = {$this->user_id}")->find();
            M('Cart')->execute("update `__PREFIX__cart` set member_goods_price = goods_price * {$user[discount]} where (user_id ={$user[user_id]} or session_id = '{$unique_id}') and prom_type = 0");        
        }
            
        
    }

    /**
     * 将商品加入购物车
     */
    function addCart()
    {
        $goods_id = I("goods_id"); // 商品id
        $goods_num = I("goods_num");// 商品数量
        $goods_spec = I("goods_spec"); // 商品规格                
        $goods_spec = json_decode($goods_spec,true); //app 端 json 形式传输过来
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        //$user_id = I("user_id",0); // 用户id        
        $result = $this->cartLogic->addCart($goods_id, $goods_num, $goods_spec,$unique_id,$this->user_id); // 将商品加入购物车
        exit(json_encode($result)); 
    }
    
    /**
     * 删除购物车的商品
     */
    public function delCart()
    {       
        $ids = I("ids"); // 商品 ids        
        $result = M("Cart")->where(" id in ($ids)")->delete(); // 删除id为5的用户数据
        
        // 查找购物车数量
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        $cart_count =  cart_goods_num(0,$unique_id);
        $return_arr = array('status'=>1,'msg'=>'删除成功','result'=>$cart_count); // 返回结果状态
        exit(json_encode($return_arr));
    }
    
    
    /*
     * 请求获取购物车列表
     */
    public function cartList()
    {                    
        $cart_form_data = $_POST["cart_form_data"]; // goods_num 购物车商品数量
        $cart_form_data = json_decode($cart_form_data,true); //app 端 json 形式传输过来                
        
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
//        $user_id = I("user_id",0); // 用户id
        $where = " session_id = '$unique_id' "; // 默认按照 $unique_id 查询
        $this->user_id && $where = " user_id = ".$this->user_id; // 如果这个用户已经等了则按照用户id查询
        $cartList = M('Cart')->where($where)->getField("id,goods_num,selected"); 
        
        if($cart_form_data)
        {
            // 修改购物车数量 和勾选状态
            foreach($cart_form_data as $key => $val)
            {   
                $data['goods_num'] = $val['goodsNum'];
                $data['selected'] = $val['selected'];
                $cartID = $val['cartID'];
                if(($cartList[$cartID]['goods_num'] != $data['goods_num']) || ($cartList[$cartID]['selected'] != $data['selected'])) 
                    M('Cart')->where("id = $cartID")->save($data);
            }
            //$this->assign('select_all', $_POST['select_all']); // 全选框
        }

        $result = $this->cartLogic->cartList($this->user, $unique_id,1,0); // 选中的商品
        $cartList = $result['result']['cartList'];
        unset($result['result']['cartList']); 
                
        $storeList = M('store')->where("store_id in(select store_id from ".C('DB_PREFIX')."cart where $where)")->getField("store_id,store_name,is_own_shop"); // 找出商家

        foreach($storeList as $k => $v)
        {   
            $store = array("store_id"=>$k,'store_name'=>$v['store_name'],'is_own_shop'=>$v['is_own_shop']);
            foreach($cartList as $k2 => $v2)
            {
                if($v2['store_id'] == $k)
                    $store['cartList'][] = $v2;
            }            
            $result['result']['storeList'][] = $store;
        }
       // if(empty($result['total_price']))
       //     $result['total_price'] = Array( 'total_fee' =>0, 'cut_fee' =>0, 'num' => 0, 'atotal_fee' =>0, 'acut_fee' =>0, 'anum' => 0);        
      //  $result['result']['total_price'] = $result['total_price'];        

        exit(json_encode($result));
    }
    /**
     * 购物车第二步确定页面
     */
    public function cart2()
    {
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        //$user_id = I("user_id"); // 用户id                
        if($this->user_id == 0 ) exit(json_encode (array('status'=>-1,'msg'=>'用户user_id不能为空','result'=>'')));
        if($this->cartLogic->cart_count($this->user_id,1) == 0 ) exit(json_encode (array('status'=>-2,'msg'=>'你的购物车没有选中商品','result'=>'')));
        
        $usersInfo = get_user_info($this->user_id);  // 用户
        // 购物车商品                    
        $cart_result = $this->cartLogic->cartList($this->user, $unique_id,1,1); // 获取购物车商品          
        // 没有选中的不传递过去
        $cartList = array();
        foreach($cart_result['cartList'] as $key => $val)
        {
            if($val['selected'] == 1) 
               $cartList[] = $val;           
        }            
        $cart_result['cartList'] = $cartList;     
        
        $store_id_arr = M('cart')->where("user_id = {$this->user_id} and selected = 1")->getField('store_id',true); // 获取所有店铺id        
        $storeList = M('store')->where("store_id in (".implode(',', $store_id_arr).")")->select(); // 找出所有商品对应的店铺id
        
        $shippingList = M('shipping_area')->where(" store_id in (".implode(',', $store_id_arr).")")->group("store_id,shipping_code")->getField('shipping_area_id,shipping_code,store_id');// 物流公司        
        $shippingList2 = M('plugin')->where("type = 'shipping'")->getField('code,name'); // 查找物流插件
        foreach($shippingList as $k => $v)
            $shippingList[$k]['name']  = $shippingList2[$v['shipping_code']];            
        
        $Model = new \Think\Model(); // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的               
        $sql = "select c1.name,c1.money,c1.condition, c2.* from __PREFIX__coupon as c1 inner join __PREFIX__coupon_list as c2  on c2.cid = c1.id and c1.type in(0,1,2,3) and order_id = 0
            where c2.uid = {$this->user_id}  and ".time()." < c1.use_end_time and c1.condition <= {$cart_result['total_price']['total_fee']} and c2.store_id in (".implode(',', $store_id_arr).")";
        $couponList = $Model->query($sql);
        
        
        // 收货地址
        $addresslist = M('UserAddress')->where("user_id = {$this->user_id}")->select();
        $c = M('UserAddress')->where("user_id = {$this->user_id} and is_default = 1")->count(); // 看看有没默认收货地址        
        if((count($addresslist) > 0) && ($c == 0)) // 如果没有设置默认收货地址, 则第一条设置为默认收货地址
            $addresslist[0]['is_default'] = 1;        
            
        $json_arr = array(
            'status'=>1,
            'msg'=>'获取成功',
            'result'=>array(
                           'addressList' =>$addresslist[0], // 收货地址
                           'totalPrice'  =>$cart_result['total_price'], // 总计                           
                           'userInfo'    =>$usersInfo, // 用户详情                           
                        ));               
        $storeList = M('store')->where("store_id in(select store_id from ".C('DB_PREFIX')."cart where user_id = {$this->user_id} and selected =1)")->getField("store_id,store_name"); // 找出商家
        //$storeList = M('store')->getField("store_id,store_name"); // 找出商家    
        // 循环店铺
        foreach($storeList as $k => $v)
        {
            $store = array('store_id'=>$k,'store_name'=>$v);
            //循环物流
            foreach($shippingList as $k3 => $v3)
            {
                if($v3['store_id'] == $k)
                   $store['shippingList'][] = $v3;
            }
            //循环商品
            foreach($cart_result['cartList'] as $k4 => $v4)
            {
                if($v4['store_id'] == $k)
                   $store['cartList'][] = $v4;
            }            
            //循环优惠券
            foreach($couponList as $k5 => $v5)
            {
                if($v5['store_id'] == $k)
                   $store['couponList'][] = $v5;
            }            
             $json_arr['result']['storeList'][] = $store;
        }                
        exit(json_encode($json_arr));       
    }
       
    /**
     * 获取订单商品价格 或者提交 订单
     */
    public function cart3(){
                        
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        //$user_id = I("user_id"); // 用户id        
        $usersInfo = get_user_info($this->user_id);  // 用户               
        $address_id = I("address_id"); //  收货地址id        
        $invoice_title = I('invoice_title'); // 发票        
        $pay_points =  I("pay_points",0); //  使用积分
        $user_money =  I("user_money",0); //  使用余额        
        $user_money = $user_money ? $user_money : 0;                                              
        
        $cart_form_data = $_POST["cart_form_data"]; // goods_num 购物车商品数量          
        $cart_form_data = json_decode($cart_form_data,true); //app 端 json 形式传输过来                                  
     
        
        
        $shipping_code    = $cart_form_data['shipping_code']; // $shipping_code =  I("shipping_code"); //  物流编号  数组形式
        $user_note        = $cart_form_data['user_note']; // $user_note = I('user_note'); // 给卖家留言      数组形式  
        $couponTypeSelect = $cart_form_data['couponTypeSelect']; // $couponTypeSelect =  I("couponTypeSelect"); //  优惠券类型  1 下拉框选择优惠券 2 输入框输入优惠券代码  数组形式 
        $coupon_id        = $cart_form_data['coupon_id']; // $coupon_id =  I("coupon_id",0); //  优惠券id  数组形式
        $couponCode       = $cart_form_data['couponCode']; // $couponCode =  I("couponCode"); //  优惠券代码  数组形式        
        
        if($this->cartLogic->cart_count($this->user_id,1) == 0 ) exit(json_encode(array('status'=>-1,'msg'=>'你的购物车没有选中商品','result'=>null))); // 返回结果状态
        if(!$address_id) exit(json_encode(array('status'=>-1,'msg'=>'请完善收货人信息','result'=>null))); // 返回结果状态
        if(!$shipping_code) exit(json_encode(array('status'=>-1,'msg'=>'请选择物流信息','result'=>null))); // 返回结果状态
        
 	$address = M('UserAddress')->where("address_id = $address_id")->find();
	$order_goods = M('cart')->where("user_id = {$this->user_id} and selected = 1")->select();
        $result = calculate_price($this->user_id,$order_goods,$shipping_code,0,$address[province],$address[city],$address[district],$pay_points,$user_money,$coupon_id,$couponCode);
                
	if($result['status'] < 0)	
		exit(json_encode($result));      
        
            $car_price = array(
                'postFee'      => $result['result']['shipping_price'], // 物流费
                'couponFee'    => $result['result']['coupon_price'], // 优惠券            
                'balance'      => $result['result']['user_money'], // 使用用户余额
                'pointsFee'    => $result['result']['integral_money'], // 积分支付            
                'payables'     => array_sum($result['result']['store_order_amount']), // 订单总额 减去 积分 减去余额
                'goodsFee'     => $result['result']['goods_price'],// 总商品价格
                'order_prom_amount' => array_sum($result['result']['store_order_prom_amount']), // 总订单优惠活动优惠了多少钱

                'store_order_prom_id'=> $result['result']['store_order_prom_id'], // 每个商家订单优惠活动的id号
                'store_order_prom_amount'=> $result['result']['store_order_prom_amount'], // 每个商家订单活动优惠了多少钱
                'store_order_amount' => $result['result']['store_order_amount'], // 每个商家订单优惠后多少钱, -- 应付金额
                'store_shipping_price'=>$result['result']['store_shipping_price'],  //每个商家的物流费
                'store_coupon_price'=>$result['result']['store_coupon_price'],  //每个商家的优惠券抵消金额
                'store_point_count' => $result['result']['store_point_count'], // 每个商家平摊使用了多少积分            
                'store_balance'=>$result['result']['store_balance'], // 每个商家平摊用了多少余额
                'store_goods_price'=>$result['result']['store_goods_price'], // 每个商家的商品总价
            );   

            // 提交订单        
            if($_REQUEST['act'] == 'submit_order')
            {  
                if(empty($coupon_id) && !empty($couponCode))
                {
                    foreach($couponCode as $k => $v)
                    $coupon_id[$k] = M('CouponList')->where("`code`='$v' and store_id = $k")->getField('id');
                }                
                $result = $this->cartLogic->addOrder($this->user_id,$address_id,$shipping_code,$invoice_title,$coupon_id,$car_price,$user_note); // 添加订单                        
                exit(json_encode($result));            
            }
                $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$car_price); // 返回结果状态
                exit(json_encode($return_arr));      
    }
 
}
