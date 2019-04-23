<?php
namespace Api\Controller;

use Think\Controller;

class WxpayController extends BaseController
{
    public function _initialize()
    {
        $wxPay = M('plugin')->where(array('type'=>'payment','code'=>'weixin'))->find();
        if(!$wxPay){
            $res = array('msg'=>'没有配置微信支付插件','status'=>-1);
            $this->ajaxReturn($res);
        }
        $wxPayVal = unserialize($wxPay['config_value']);
        if(!$wxPayVal['appid'] || !$wxPayVal['key'] || !$wxPayVal['mchid']){
            $res = array('msg'=>'没有配置微信支付插件参数','status'=>-1);
            $this->ajaxReturn($res);
        }
        require_once("plugins/payment/weixin/app_notify/Wxpay/WxPayApi.class.php");
        require_once("plugins/payment/weixin/app_notify/Wxpay/WxPayUnifiedOrder.class.php");
    }


    /**
     * 支付通知
     */
    public function  notify()
    {
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $result = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if($result['return_code'] == 'SUCCESS'){
            $order_sn = substr($result['out_trade_no'],0,18);
            update_pay_status($order_sn);

            //region 更改支付方式
            $map = "order_sn = '$order_sn'";
            $data = array('pay_code'=>'weixin','pay_name'=>'App微信');
            if (stripos($order_sn, 'recharge') !== false) {
                $model = 'recharge';
            }else if (stripos($order_sn, 'sc') !== false){
                $model = 'scan_order';
            }else {
                $model = 'order';
            }
            if ($model)
                M($model)->where($map)->save($data);
            //endregion
        }

        $test = array('return_code'=>'SUCCESS','return_msg'=>'OK');
        header('Content-Type:text/xml; charset=utf-8');
        exit(arrayToXml($test));
    }

    /**
     * 统一下单
     */
    public function dopay()
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-type: text/plain');
        require_once("plugins/payment/weixin/app_notify/Wxpay/WxPayConfig.class.php");
        require_once("plugins/payment/weixin/app_notify/Wxpay/WxPayNotify.class.php");
        require_once("plugins/payment/weixin/app_notify/Wxpay/WxPayReport.class.php");
        require_once("plugins/payment/weixin/app_notify/Wxpay/WxPayResults.class.php");

        $order_sn = I('order_sn',0);
        $model = I('model','order');
        $order = M($model)->where(array('order_sn'=>$order_sn))->find();
        if(!$order){
            $res = array('msg'=>'该订单不存在','status'=>-1);
            $this->ajaxReturn($res);
        }
        if ($order['pay_status'] == 1) {
            $res = array('msg'=>'该订单已支付','status'=>-1);
            $this->ajaxReturn($res);
        }
        // 获取支付金额
        if ($model == 'order')
            $amount = $order['order_amount'];
        else if ($model == 'recharge'){
            $amount = $order['need_pay'];
        } else if ($model == 'scan_order'){
            $amount = $order['need_pay'];
        }

        $total = floatval($amount);
        $total = round($total * 100); // 将元转成分
        if (empty($total)) {
            $total = 1;
        }
        // 商品名称
        $shop_info = tpCache('shop_info');
        $subject = $shop_info['store_name'];
        $detail = "订单金额";
        $native = "NATIVE";
        // 订单号，示例代码使用时间值作为唯一的订单ID号
        $unifiedOrder = new \WxPayUnifiedOrder();
        $WxPayApi = new \WxPayApi();
        $unifiedOrder->SetBody($subject);//商品或支付单简要描述

        $WxPayConfig = \WxPayConfig::getInstance();
        $unifiedOrder->SetAppid($WxPayConfig::$APPID);//appid
        $unifiedOrder->SetMch_id($WxPayConfig::$MCHID);//商户标识
        $unifiedOrder->SetNonce_str($WxPayApi::getNonceStr($length = 32));//随机字符串
        $unifiedOrder->SetDetail($detail);//详情
        $unifiedOrder->SetOut_trade_no($order_sn.time());//交易号
        $unifiedOrder->SetTotal_fee($total);//交易金额
        //$unifiedOrder->SetTotal_fee(1);//交易金额
        $unifiedOrder->SetTrade_type("APP");//应用类型
        $unifiedOrder->SetSpbill_create_ip($_SERVER['REMOTE_ADDR']);//发起充值的ip
        $unifiedOrder->SetNotify_url($WxPayConfig::$NOTIFY_URL);//交易成功通知url
        //$unifiedOrder->SetTrade_type($native);//支付类型
        $unifiedOrder->SetProduct_id(time());

        $result = $WxPayApi::unifiedOrder($unifiedOrder);

        if (is_array($result)) {
            $res = array('msg'=>'获取成功','status'=>1,'result'=>$result);
            $this->ajaxReturn($res);
        }else{
            $res = array('msg'=>'获取失败','status'=>-1,'result'=>$result);
            $this->ajaxReturn($res);
        }

    }

}

?>