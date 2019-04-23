<?php
/**
 * Created by PhpStorm.
 * User: yq
 * Date: 2017/9/11 0011
 * Time: 11:54
 */

namespace Api\Controller;


use Think\Controller;

class ApiController extends Controller
{
    private $is_crypt = false;  // 是否开启加密（false 禁用 true 启用）

    public $client_version;     // 客户端版本号
    public $client_time;        // 客户端时间戳
    public $client_plat;        // 客户端平台（1 ios 2 安卓）
    public $client_sign;        // 客户端唯一标识
    public $method;             // 当前执行的方法

    public $contr;             // 当前执行的控制器

    ///////////////////////////////////////
    public $apiparams;             // 接口的参数
    ///////////////////////////////////////

    public function _initialize()
    {
        if($this->is_crypt){
            $params_str = $this->decrypt($_POST['params']);
        }else{
            $params_str = $_POST['params'];
        }

        if ($params_str){
            $params = json_decode(trim($params_str),true)[0];
            $this->client_version   = $params['version'];
            $this->client_time      = $params['time'];
            $this->client_plat      = $params['plat'];
            $this->client_sign      = $params['sign'];
            $this->method           = $params['method'];
            $this->contr            = $params['contr'];

            // 所有公共参数必传
            //if (empty($this->client_version))   $this->returnCode(0,'缺少版本号参数');
            //if (empty($this->client_time))      $this->returnCode(0,'缺少时间戳参数');
            //if (empty($this->client_plat))      $this->returnCode(0,'缺少平台公共参数');
            //if (empty($this->client_sign))      $this->returnCode(0,'缺少唯一标识参数');
            if (empty($this->method))           $this->returnCode(0,'缺少请求方法参数');
            if (empty($this->contr))            $this->returnCode(0,'缺少请求类参数');

            //判断类是否存在
            //if (!class_exists("Api\Controller\\".$this->contr,false)) $this->returnCode(0,$this->contr.'类不存在');
            // 判断方法是否存在
            //if (!method_exists($this,$this->method)) $this->returnCode(0,$this->method.'方法不存在');

            //$this->apiparams = json_decode($params['data'],true);
            $this->apiparams = $params['data'];
            $_GET  = $params['data'];
            $_POST = $params['data'];

        }else{
            $this->returnCode(0,'获取参数失败','');
        }
    }

    /**
     * 加密函数
     * @param $encrypt 需加密的字符串
     * @return string 加密好的字符串
     */
    public function encrypt($encrypt)
    {
        return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, C('AES_KEY'), $encrypt, MCRYPT_MODE_CBC, C('AES_KEY')));
    }

    /**
     * 解密函数
     * @param $decrypt 需解密的字符串
     * @return string 解密好的字符串
     */
    public function decrypt($decrypt)
    {
        return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, C('AES_KEY'), base64_decode($decrypt), MCRYPT_MODE_CBC, C('AES_KEY'));
    }

    /**
     * @param $status 返回状态
     * @param $msg 返回消息
     * @param string $source 返回数据
     * @return bool
     */
    public function returnCode($status, $msg, $source="")
    {
        if (!is_numeric($status)) return false;
        $return_data = [
            'status' => $status,
            'msg' => $msg,
            'data' => $source
        ];
        if($this->is_crypt){
            exit($this->encrypt(json_encode($return_data)));
        }else{
            header('Content-Type:application/json; charset=utf-8');
            exit(json_encode($return_data));
        }
    }

    /*public function test()
    {
        // 推送消息
        $param = array(
            // 'plat' => $oPlat,	// 平台 1 IOS 2 安卓
            'alias' => '148249202021417',	// 推送用户
            'title' => '帐号在其他设备登录'
            // 'summary' => $oImei
            // 'summary' => '您的帐号已在其他设备上登录，如非本人操作请尽快修改密码'
        );
        JPushMessage($param);
    }*/
}