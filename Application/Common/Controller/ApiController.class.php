<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/16 0016
 * Time: 下午 3:54
 */

namespace Common\Controller;


use Think\Controller;

class ApiController extends Controller
{
    public function tts()
    {
        import('Common.Util.Ttsapi');
        $tts = new \Ttsapi();


        $text = '您的验证码是020304';//.mt_rand(100000,999999);
        $tts->yuying($text);

    }

    //百度语音合成
    public function baiduTTS($tex)
    {
        $appid = '8620717';
        $apikey = 'BRIviMY56F3W0yknlq81gx4x';
        $secretkey = '6d49f9ecc9588913566eb6bb0185d680';


        if (!$tex) {
            $this->error('字符非法');
        }

        $tex = urlencode($tex);

        //语言文件地址
        $file = './Uploads/mp3/'.date('Y-m-d',time()).'/';
        dir_create($file); //创建目录
        $file .= md5($tex) . '.mp3';
        if (!file_exists($file)) {

            //缓存名称
            $name = 'bdtsn_' . $appid;
            if (!$json = S($name)) {
                //获取百度语音token信息 并缓存
                $jsonStr = http_post('https://openapi.baidu.com/oauth/2.0/token?grant_type=client_credentials&client_id=' . $apikey . '&client_secret=' . $secretkey);
                $json = json_decode($jsonStr, true);
                S($name, $json, $json['expires_in']);
            }

            if (!$json) {
                $this->error('token获取失败');
            }

            //获取语语音数据 并生成 本地mp3文件
            $url = 'http://tsn.baidu.com/text2audio?tex=' . $tex . '&lan=zh&cuid=' . $appid . '&ctp=1&per='.get_voice_mode().'&tok=' . $json['access_token'];
            $data = file_get_contents($url);

            if (!$json) {
                $this->error('音频数据获取失败');
            }

            file_put_contents($file, $data);

        }

        if ($file) {
            //$this->success('success',$file);
            return $file;
        }

    }

}