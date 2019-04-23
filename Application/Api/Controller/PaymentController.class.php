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
use Think\Exception;
use Think\Page;
class PaymentController extends BaseController {
        
    /**
     * 析构流函数
     */
    /*public function  __construct() {
        parent::__construct();                
    }*/
    public function _initialize(){

    }

    /**
     * app端发起支付宝,支付宝返回服务器端,  返回到这里    tpshop自带的，没多大用
     * http://www.tp-shop.cn/index.php/Api/Payment/alipayNotify
     */
    public function alipayNotify(){
             
        $paymentPlugin = M('Plugin')->where("code='alipay' and  type = 'payment' ")->find(); // 找到支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化        
        
        require_once("plugins/payment/alipay/app_notify/alipay.config.php");
        require_once("plugins/payment/alipay/app_notify/lib/alipay_notify.class.php");
        
        $alipay_config['partner'] = $config_value['alipay_partner'];//合作身份者id，以2088开头的16位纯数字        
     
        //计算得出通知验证结果
        $alipayNotify = new \AlipayNotify($alipay_config);
        $verify_result = $alipayNotify->verifyNotify();                        
        //验证成功
        if($verify_result) 
        {                           
                $order_sn = $out_trade_no = trim($_POST['out_trade_no']); //商户订单号
                $trade_no = $_POST['trade_no'];//支付宝交易号
                $trade_status = $_POST['trade_status'];//交易状态
            

            if($_POST['trade_status'] == 'TRADE_FINISHED') 
            {            
                update_pay_status($order_sn); // 修改订单支付状态                
            }
            else if ($_POST['trade_status'] == 'TRADE_SUCCESS') 
            {            
                update_pay_status($order_sn); // 修改订单支付状态                
            }               
            //M('order')->where("order_sn = '$order_sn' or master_order_sn = '$order_sn'")->save(array('pay_code'=>'alipay','pay_name'=>'app支付宝'));

            $map = "order_sn = '$order_sn'";
            $data = array('pay_code'=>'alipay','pay_name'=>'App支付宝');
            if (stripos($order_sn, 'recharge') !== false) {
                $model = 'recharge';
            }else if (stripos($order_sn, 'sc') !== false){
                $model = 'scan_order';
            }else {
                $model = 'order';
            }
            if ($model)
                M($model)->where($map)->save($data);

            echo "success"; //  告诉支付宝支付成功 请不要修改或删除               
        }
        else 
        {                
            echo "fail"; //验证失败         
        }
    }

    /**
     * 支付宝App支付
     */
    public function alipay(){
       try{
           //region 订单信息
           $order_sn = I('order_sn',0);
           $model = I('model','order');
           $order = M($model)->where(array('order_sn'=>$order_sn))->find();
           if(!$order){
               throw new Exception('该订单不存在',401);
           }
           if ($order['pay_status'] == 1) {
              throw new Exception('该订单已支付',402);
           }
           // 获取支付金额
           if ($model == 'order')
               $amount = $order['order_amount'];
           else if ($model == 'recharge'){
               $amount = $order['need_pay'];
           } else if ($model == 'scan_order'){
               $amount = $order['need_pay'];
           }
           //endregion

           //region 支付宝支付
           vendor('Alipay.aop.AopClient');
           vendor('Alipay.aop.request.AlipayTradeAppPayRequest');
           //vendor('Alipay.aop.SignData');
           $config = C('ALIPAY_CONFIG');//获取支付宝配置
           if (empty($config) || !is_array($config))
               throw new Exception('系统配置错误');
           $aop = new \AopClient();//AopClient
           //**沙箱测试支付宝开始
           $aop->gatewayUrl = $config['gatewayUrl'];
           //实际上线app id需真实的
           $aop->appId = $config['appId'];//应用ID
           $aop->rsaPrivateKey = $config['rsaPrivateKey'];//私钥
           $aop->format = $config['format'];//数据类型
           //$aop->charset = "UTF-8";
           $aop->postCharset = $config['postCharset'];//数据编码
           $aop->signType = $config['signType'];//验证方式
           $aop->alipayrsaPublicKey = $config['alipayrsaPublicKey'];//公钥
           //支付信息
           $bizcontent = json_encode([
               'body'=>'钜宏家庭号-'.get_column('store',array('store_id'=>$order['store_id']),'store_name'),
               'subject'=>'商品购买',
               'out_trade_no'=>$order['order_sn'],//此订单号为商户唯一订单号
               'total_amount'=> $amount,//保留两位小数
               //'total_amount'=> 0.01,//保留两位小数 注意支付宝是正常的金额
               'product_code'=>'QUICK_MSECURITY_PAY'
           ]);
           //**沙箱测试支付宝结束
           //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
           $request = new \AlipayTradeAppPayRequest();//AlipayTradeAppPayRequest
           //支付宝回调
           $request->setNotifyUrl($config['NotifyUrl']);
           $request->setBizContent($bizcontent);
           //这里和普通的接口调用不同，使用的是sdkExecute
           $response = $aop->sdkExecute($request);
           $this->ajaxReturn(array('status'=>1,'msg'=>'success','orderInfo'=>$response));
           //endregion
       }catch(Exception $e){
           $this->ajaxReturn(array('status'=>$e->getCode(),'msg'=>$e->getMessage()));
       }
    }

    /**
     * 支付宝App支付回调
     */
    public function alipay_notify(){
        vendor('Alipay.aop.AopClient');
        $aop = new \AopClient();//AopClient
        $config = C('ALIPAY_CONFIG');//获取支付宝配置  Api\Conf\config.php
        $aop->alipayrsaPublicKey = $config['alipayrsaPublicKey'];//公钥
        $flag = $aop->rsaCheckV1($_POST, NULL, $config['signType']);
        M('test_msg')->add(array('msg'=>serialize($flag)));
        M('test_msg')->add(array('msg'=>serialize($_POST)));
        if ($flag){
            $order_sn = $out_trade_no = trim($_POST['out_trade_no']); //商户订单号
            //M('test_msg')->add(array('msg'=>$order_sn.'alipay'));
            if($_POST['trade_status'] == 'TRADE_FINISHED') {
                update_pay_status($order_sn); // 修改订单支付状态
            }
            else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
                update_pay_status($order_sn); // 修改订单支付状态
            }

            $map = "order_sn = '$order_sn'";
            $data = array('pay_code'=>'alipay','pay_name'=>'App支付宝');
            if (stripos($order_sn, 'recharge') !== false) {
                $model = 'recharge';
            }else if (stripos($order_sn, 'sc') !== false){
                $model = 'scan_order';
            }else {
                $model = 'order';
            }
            if ($model)
                M($model)->where($map)->save($data);

            echo "success"; //  告诉支付宝支付成功 请不要修改或删除
        }else{
            echo "fail"; //验证失败
        }

    }
}
