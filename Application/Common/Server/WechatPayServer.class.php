<?php

namespace Common\Server;

/*微信支付服务*/
use Think\Exception;

class WechatPayServer
{
    //const KEY = '商户支付秘钥'; //请修改为自己的
    //const MCHID = '商户号'; //请修改为自己的
    //const APPID = 'APPID';//请修改为自己的
    //const SECRET = 'app密码 appsecret';//请修改为自己的

    private $params;
    private static $_key = '';//商户支付秘钥
    private static $_mchid = ''; //商户号
    private static $_appid = '';//APPID
    private static $_secret = '';//SECRET

    const RPURL = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack';
    const CODEURL = 'https://open.weixin.qq.com/connect/oauth2/authorize?';
    const OPENIDURL = 'https://api.weixin.qq.com/sns/oauth2/access_token?';
    /*微信企业转账 - 零钱*/
    const PAYURL = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
    /*查询企业转账 - 零钱*/
    const SEPAYURL = "https://api.mch.weixin.qq.com/mmpaymkttransfers/gettransferinfo";
    /*获取RSA 加密公钥*/
    const PKURL = "https://fraud.mch.weixin.qq.com/risk/getpublickey";
    /*微信企业转账 - 银行卡*/
    const BANKPAY = "https://api.mch.weixin.qq.com/mmpaysptrans/pay_bank";
    /*查询企业转账 - 银行卡信息*/
    const QUERYBANK = 'https://api.mch.weixin.qq.com/mmpaysptrans/query_bank';

    //构造函数
    public function __construct(array $data)
    {
        self::$_key = $data['key'];//商户支付秘钥
        self::$_mchid = $data['mchid'];//商户号
        self::$_appid = $data['appid'];//APPID
        self::$_secret = $data['secret'];//SECRET
    }
    /*获取RSA加密公钥*/
    public function getPuyKey(){
        $this->params = [
            'mch_id'    => self::$_mchid,//商户ID
            'nonce_str' => md5(time()),
            'sign_type' => 'MD5'
        ];
        //将数据发送到接口地址
        return $this->send(self::PKURL);
    }
    /*企业转账 - 零钱*/
    public function comPay($data){
        //构建原始数据
        $this->params = [
            'mch_appid'         => self::$_appid,//APPid,
            'mchid'             => self::$_mchid,//商户号,
            'nonce_str'         => $data['nonce_str'], //随机字符串
            'partner_trade_no'  => $data['partner_trade_no'], //商户订单号
            'openid'            => $data['openid'], //用户openid
            'check_name'        => 'NO_CHECK',//校验用户姓名选项 NO_CHECK：不校验真实姓名 FORCE_CHECK：强校验真实姓名
            //'re_user_name'    => '',//收款用户姓名  如果check_name设置为FORCE_CHECK，则必填用户真实姓名
            'amount'            => $data['amount'] * 100,//金额 单位分
            //'amount'            => 100,//金额 单位分
            'desc'              => $data['desc'],//付款描述
            'spbill_create_ip'  => get_client_ip(),//$_SERVER['SERVER_ADDR'],//调用接口机器的ip地址
        ];
        //将数据发送到接口地址
        return $this->send(self::PAYURL);
    }
    /*企业转账 - 银行卡*/
    public function bankPay($data){
        $this->params = [
            'mch_id'              => self::$_mchid,//商户号
            'partner_trade_no'    => $data['partner_trade_no'],//商户付款单号
            'nonce_str'           => $data['nonce_str'], //随机串
            'enc_bank_no'         => $data['enc_bank_no'],//收款方银行卡号RSA加密
            'enc_true_name'       => $data['enc_true_name'],//收款方姓名RSA加密
            'bank_code'           => $data['bank_code'],//收款方开户行
            'desc'                => $data['desc'],//备注
            'amount'              => $data['amount'] * 100,//付款金额
        ];
        //将数据发送到接口地址
        return $this->send(self::BANKPAY);
    }

    /*查询 企业转账 - 银行卡*/
    public function queryBankPay($oid){
        $this->params = [
            'nonce_str'  => md5(time()),//随机串
            'partner_trade_no'  => $oid, //商户订单号
            'mch_id'  => self::$_mchid,//商户号
        ];
        //将数据发送到接口地址
        return $this->send(self::QUERYBANK);
    }

    /*查询 企业转账 - 零钱*/
    public function searchPay($oid){
        $this->params = [
            'nonce_str'  => md5(time()),//随机串
            'partner_trade_no'  => $oid, //商户订单号
            'mch_id'  => self::$_mchid,//商户号
            'appid'  => self::$_appid //APPID
        ];
        //将数据发送到接口地址
        return $this->send(self::SEPAYURL);
    }
    public function sign(){
        return $this->setSign($this->params);
    }
    public function send($url){
        $res = $this->sign();
        $xml = $this->ArrToXml($res);
        $returnData = $this->postData($url, $xml);
        return $this->XmlToArr($returnData);
    }


    /**
     * 获取签名
     * @param array $arr
     * @return string
     */
    public function getSign($arr){
        //去除空值
        $arr = array_filter($arr);
        if(isset($arr['sign'])){
            unset($arr['sign']);
        }
        //按照键名字典排序
        ksort($arr);
        //生成url格式的字符串
        $str = $this->arrToUrl($arr) . '&key=' . self::$_key;
        return strtoupper(md5($str));
    }
    /**
     * 获取带签名的数组
     * @param array $arr
     * @return array
     */
    public function setSign($arr){
        $arr['sign'] = $this->getSign($arr);;
        return $arr;
    }
    /**
     * 数组转URL格式的字符串
     * @param array $arr
     * @return string
     */
    public function arrToUrl($arr){
        return urldecode(http_build_query($arr));
    }

    //数组转xml
    function ArrToXml($arr)
    {
        if(!is_array($arr) || count($arr) == 0) return '';

        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }

    //Xml转数组
    function XmlToArr($xml)
    {
        if($xml == '') return '';
        libxml_disable_entity_loader(true);
        $arr = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $arr;
    }
    function postData($url,$postfields){

        $ch = curl_init();
        $params[CURLOPT_URL] = $url;    //请求url地址
        $params[CURLOPT_HEADER] = false; //是否返回响应头信息
        $params[CURLOPT_RETURNTRANSFER] = true; //是否将结果返回
        $params[CURLOPT_FOLLOWLOCATION] = true; //是否重定向
        $params[CURLOPT_POST] = true;
        $params[CURLOPT_POSTFIELDS] = $postfields;
        $params[CURLOPT_SSL_VERIFYPEER] = false;
        $params[CURLOPT_SSL_VERIFYHOST] = false;
        //以下是证书相关代码
        $params[CURLOPT_SSLCERTTYPE] = 'PEM';
        $params[CURLOPT_SSLCERT] = 'plugins/payment/weixin/cert/apiclient_cert.pem';
        $params[CURLOPT_SSLKEYTYPE] = 'PEM';
        $params[CURLOPT_SSLKEY] = 'plugins/payment/weixin/cert/apiclient_key.pem';

        curl_setopt_array($ch, $params); //传入curl参数
        $content = curl_exec($ch); //执行
        curl_close($ch); //关闭连接
        return $content;
    }
}


//查询
/*
$oid = "20180129201209";
$res = $obj->searchPay($oid);
 *
 */

//获取公钥
/*
$res = $obj->getPuyKey();
file_put_contents('./cert/pubkey.pem', $res['pub_key']);
 *
 */
//企业付款到银行卡
/*
$rsa = new RSA(file_get_contents('./cert/newpubkey.pem'), '');
$data = [
     'enc_bank_no'         => $rsa->public_encrypt('1234342343234234'),//收款方银行卡号RSA加密
     'enc_true_name'       => $rsa->public_encrypt('李明'),//收款方姓名RSA加密
     'bank_code'           => '1002',//收款方开户行
     'amount'              => '100',//付款金额
];

$res = $obj->bankPay($data);
 *
 */
//echo '<pre>';
//print_r($res);
