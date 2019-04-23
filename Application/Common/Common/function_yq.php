<?php
/**
 * 从新写的函数
 */

/**
 * 发送短信验证码
 * @param  $phone 手机号
 * @param  $code 验证码
 * wj
 * 2016-11-16
 */
function sendSMS($phone, $code){
	if (empty($phone)){
		return false;
	}
	$res = is_phone($phone);//检查手机格式
	if (!$res){//手机格式错误
		return false;
	}
	$sms_config = C('sms_config');
	$username = $sms_config['username'];//用户名
	$password = $sms_config['password'];//密码
	$content_str = $sms_config['content_str'];//提示后缀名
	$url = $sms_config['url'];//提交地址
    $msg="您好，你的验证码:".$code .$content_str;
    $port = '';
    $sendtime = date('Y-m-d H:i:s');
	/*$post_data =
	"account=". $username .
	"&pswd=". $password .
	"&mobile=". $phone .
	"&msg=您好，你的验证码:".
	$code .$content_str.
	"&needstatus=true&port=".$port."&sendtime=".$sendtime;*/
    $post_data = "username=".$username
        ."&passwd=".$password
        ."&phone=".$phone
        ."&msg=".urlencode($msg)
        ."&needstatus=true&port=".$port
        ."&sendtime=".$sendtime;

    $res = curlHttpPost($post_data,$url);
    //return $res;
	if ($res){
		// 从数据库中查询是否有验证码
		$data = M('sms_log')->where("code = '$code' and add_time > ".(time() - 60*60))->find();
		// 没有就插入验证码,供验证用
		empty($data) && M('sms_log')->add(array('mobile' => $phone, 'code' => $code, 'add_time' => time(), 'session_id' => SESSION_ID));
		return true;
	}else{
		return false;
	}
	
}
/**
 *
 * 验证手机号码格式
 * @param  $phone 手机号码
 * wj
 * 2016-10-16
 */
function is_phone($phone){
	$pattern = "/^(1[3|5|8|4|7][0-9]|15[0|3|6|7|8|9]|18[8|9])\d{8}$/is";
	preg_match($pattern,$phone,$arr);
	if(empty($arr)){//验证失败
		return false;
	}else{//验证成功
		return true;
	}
}
/**
 * CURL模拟POST
 * @param string $data 提交数据
 * @param string $url 提交地址
 * @return bool 状态（true 发送成功 false 发送失败）
 */
function curlHttpPost($data,$url){
	$timeout = 20;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	$res = curl_exec($ch);
	curl_close($ch);
	if ($res == false) return false;
    $res = json_decode($res);
    M('test_msg')->add(array('msg' => json_encode($res).'_'.date('Y-m-d H:i:s',time())));
    if($res->respcode==0){
        return true;
        // echo "发送成功";
    }else{
        return false;
        //echo "发送失败，失败原因：".$resultObj->respdesc;
    }
//	$status = substr($res,15,1);
//	if($status == 0){
//		return true;
//	}else{
//		return false;
//	}
}