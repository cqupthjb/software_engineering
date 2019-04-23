<?php

namespace Common\Controller;
Vendor('jpush.autoload');
use \JPush\Client as JPushClient;
use Think\Controller;

/**
 * Class JpushController
 * @package Common\Controller
 */
class JpushController extends Controller
{
    //指定regId推送
    public function jpush_to_regid($reg_id,$title,$msg,$type) {
        $jpush = new JPushClient(get_jpush_appkey(), get_jpush_master_secret());
        $extras = array(
            'type' => $type,
            'msg'  => ''
        );
        $push_payload = $jpush->push()
            ->setPlatform('all')
            //->addAllAudience()
            ->addRegistrationId($reg_id)
            ->setNotificationAlert($title)
            ->iosNotification($msg, array(
                //'sound' => 'sound.caf',
                'badge' => '+1',
                'extras' => $extras,
            ))
            ->androidNotification($msg, array(
                'title' => $title,
                'extras' => $extras,
            ))
//            ->message($msg, [
//                'title' => $title,
//                'content_type' => 'text',
//                'extras' => $extras,
//            ])
            ->options(array(
                "apns_production" => true,
                'sendno' => 100,
                'time_to_live' => 100,
                //'big_push_duration' => 5
                //'override_msg_id' => 100,
            ));
            //->setMessage($msg,$title,'text',array('type'=>$type,'msg'=>''));      //发送自定义消息   通知在iOS中可能无效
            //print_r($push_payload);
        try {
            $response = $push_payload->send();
            if($response['http_code']==200){ //发送成功
                return true;
            }else{
                return false; //发送失败
            }
        } catch (\JPush\Exceptions\APIConnectionException $e) {
            // try something here
            //print $e;
        } catch (\JPush\Exceptions\APIRequestException $e) {
            // try something here
            //print $e;
        }
    }

    //指定别名推送
    public function jpush_to_alias($alias,$title,$msg,$type) {
        $jpush = new JPushClient(get_jpush_appkey(), get_jpush_master_secret());
        //$baiduTTS = new ApiController();
        //$baidu = $baiduTTS->baiduTTS($title.','.$msg);
        //$baidu = 'http://'.$_SERVER['HTTP_HOST'] . substr($baidu,1);
        $push_payload = $jpush->push()
            ->setPlatform('all')
            //->setPlatform(array('ios', 'android'))
            //->addAllAudience()
            ->addAlias($alias)
            ->setMessage($msg,$title,'text',array('type'=>$type,'msg'=>''));      //发送自定义消息   通知在iOS中可能无效
        //print_r($push_payload);
        try {
            $response = $push_payload->send();
            if($response['http_code']==200){ //发送成功
                return true;
            }else{
                return false; //发送失败
            }
        } catch (\JPush\Exceptions\APIConnectionException $e) {
            // try something here
            //print $e;
        } catch (\JPush\Exceptions\APIRequestException $e) {
            // try something here
            //print $e;
        }
    }

    //推送所有人
    public function jpush_to_all($title,$msg,$type,$extras) {
        $jpush = new JPushClient(get_jpush_appkey(), get_jpush_master_secret());
        //$baiduTTS = new ApiController();
        //$baidu = $baiduTTS->baiduTTS($title.','.$msg);
        //$baidu = 'http://'.$_SERVER['HTTP_HOST'] . substr($baidu,1);
        $push_payload = $jpush->push()
            ->setPlatform('all')
            ->addAllAudience()
            ->setMessage($msg,$title,'text',array('type'=>$type,'msg'=>''),$extras);      //发送自定义消息   通知在iOS中可能无效
        //print_r($push_payload);
        try {
            $response = $push_payload->send();
            if($response['http_code']==200){ //发送成功
                return true;
            }else{
                return false; //发送失败
            }
        } catch (\JPush\Exceptions\APIConnectionException $e) {
            // try something here
            //print $e;
        } catch (\JPush\Exceptions\APIRequestException $e) {
            // try something here
            //print $e;
        }
    }
}