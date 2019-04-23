<?php
/**
 * Created by PhpStorm.
 * User: Mrs-Y
 * Date: 2017/09/16 0016
 * Time: 11:17
 */

namespace Admin\Controller;


use Mobile\Controller\MobileBaseController;

class TestController extends BaseController
{
    public function index(){
        header("Content-type: text/html; charset=utf-8");
        //自定义订单号，此处仅作举例
        $timeStamp = time();
        $out_trade_no = "$timeStamp";

        //获取用户一维码
        if (isset($_POST["auth_code"]))
        {
            $auth_code = $_POST["auth_code"];
            include_once "plugins/payment/weixin/weixin.class.php";
            $wx = new \weixin();
            try{
                $micropayCallResult = $wx->get_micro_pay($auth_code,1);
            }catch(\WxPayException $e){
                dump($e->getMessage());die;
            }
            //提交订单
            //$micropayCallResult = $micropayCall->getResult();

            //商户根据实际情况设置相应的处理流程,此处仅作举例
            if ($micropayCallResult["return_code"] == "FAIL")
            {
                echo "通信出错：".$micropayCallResult['return_msg']."<br>";
            }
            elseif($micropayCallResult["result_code"] == "FAIL")
            {
                echo "出错"."<br>";
                echo "错误代码：".$micropayCallResult['err_code']."<br>";
                echo "错误代码描述：".$micropayCallResult['err_code_des']."<br>";
            }
            else
            {
                echo "用户标识：".$micropayCallResult['openid']."<br>";
                echo "是否关注公众账号：".$micropayCallResult['is_subscribe']."<br>";
                echo "交易类型：".$micropayCallResult['trade_type']."<br>";
                echo "付款银行：".$micropayCallResult['bank_type']."<br>";
                echo "总金额：".$micropayCallResult['total_fee']."<br>";
                echo "现金券金额：".$micropayCallResult['coupon_fee']."<br>";
                echo "货币种类：".$micropayCallResult['fee_type']."<br>";
                echo "微信支付订单号：".$micropayCallResult['transaction_id']."<br>";
                echo "商户订单号：".$micropayCallResult['out_trade_no']."<br>";
                echo "商家数据包：".print_r($micropayCallResult['attach'])."<br>";
                echo "支付完成时间：".$micropayCallResult['time_end']."<br>";
            }
        }
        else
        {
            $this->assign('out_trade_no',$out_trade_no);
            $this->display();
        }
    }

    //批量获取UnionID -> 根据微信公众号的Openid
    public function get_unionid(){
        set_time_limit(0);
        $map = array();
        $map['openid'] = array('neq','');
        $map['unionid'] = array('eq','');
        $users = M('users')->field('user_id ,openid ,nickname')->where($map)->order('user_id asc')->select();

        if (!empty($users)){
            $users = get_list_group($users, 5);
            foreach ($users as $k => $v){
                set_time_limit(0);
                foreach ($v as $key => $vo){
                    if (empty($vo['openid']))
                        continue;
                    $access_token = $this->get_access_token();
                    //$url = 'https://api.weixin.qq.com/cgi-bin/user/info/batchget';
                    $user = $this->GetUserInfo($access_token,$vo['openid']);//获取微信用户信息

                    if (!empty($user) && !empty($user['unionid'])){
                        $res = M('users')->where(array('user_id'=>$vo['user_id'],'openid'=>$user['openid']))->save(array('unionid'=>$user['unionid']));
                    }
                }
                echo $k.'<br/>';
                sleep(5);
            }
        }
        echo 'success';die;
    }

    public function GetUserInfo($access_token,$openid)
    {
        // 获取用户 信息
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$openid";
        $subscribe_info = httpRequest($url,'GET');
        $subscribe_info = json_decode($subscribe_info,true);

        return $subscribe_info;
    }

    public function get_access_token(){
        $weixin_config = M('wx_user')->find(); //获取微信配置
        //判断是否过了缓存期
        $expire_time = $weixin_config['web_expires'];
        if($expire_time > time()){
            return $weixin_config['web_access_token'];
        }
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$weixin_config['appid']}&secret={$weixin_config['appsecret']}";
        $return = httpRequest($url,'GET');
        $return = json_decode($return,1);
        $web_expires = time() + 7140; // 提前60秒过期
        M('wx_user')->where(array('id'=>$weixin_config['id']))->save(array('web_access_token'=>$return['access_token'],'web_expires'=>$web_expires));
        return $return['access_token'];
    }


    public function get_unionid2(){
        set_time_limit(0);
        $map = array();
        $map['openid'] = array('neq','');
        $map['unionid'] = array('eq','');
        $users = M('users')->field('user_id ,openid ,nickname')->where($map)->order('user_id desc')->select();

        if (!empty($users)){
            $users = get_list_group($users, 5);
            foreach ($users as $k => $v){
                set_time_limit(0);
                foreach ($v as $key => $vo){
                    if (empty($vo['openid']))
                        continue;
                    $access_token = $this->get_access_token();
                    //$url = 'https://api.weixin.qq.com/cgi-bin/user/info/batchget';
                    $user = $this->GetUserInfo($access_token,$vo['openid']);//获取微信用户信息

                    if (!empty($user) && !empty($user['unionid'])){
                        $res = M('users')->where(array('user_id'=>$vo['user_id'],'openid'=>$user['openid']))->save(array('unionid'=>$user['unionid']));
                    }
                }
                echo $k.'<br/>';
                sleep(5);
            }
        }
        echo 'success';die;
    }


    public function get_uuid(){
        //$res = base_convert(uniqid(), 13, 10);
        //$res = substr(strtotime(date("Y-m-d", time())), 0, 2) . substr(strrev(microtime()), 0, 2) . substr(mt_rand(), 0, 5) . substr(rand(), 0, 2);
        //dump($res);
        $now = 1 ;
        for ($i=0;$i<=500000;$i++){
            echo $now .substr(rand(), 0, 1) . substr(strrev(microtime()), 0, 2).'<br/>';
            $now ++ ;
        }
    }

    public function test_pwd(){
        set_time_limit(0);
        ini_set("error_reporting","E_ALL & ~E_NOTICE");//错误显示
        ini_set('max_execution_time', '0');//解除时间限制
        //获取当前最大卡号
        $data = array();
        for ($i = 0 ; $i <= I('num',10000) ; $i++){
            $data[] =  $i .'-' . uninum($i+1,12) ;
        }
        dump($data);
        echo '<br/>';
        echo '<br/>';
        dump(array_unique($data));
        die;
    }

    public function test(){
        $zerosFun   =   function ( $number, $length = 4){

            $endLen =   strlen($number) < $length ? $length - strlen($number) : 0;

            $zeroLen='';
            for ($i = 1; $i <= $endLen; $i++){
                $zeroLen.='0';
            }

            return $zeroLen.$number;
        };

        $data   =   [];
        for ($i = 1;$i <= 10000; $i++){

            $numberStr  =   (int)(explode(' ', microtime())[0] * explode(' ', microtime())[1]);

            $data[] = $zerosFun(substr($numberStr, 0, 8), 8) . $zerosFun($i);
        }
        dump($data);die;
    }
}