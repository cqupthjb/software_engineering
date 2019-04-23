<?php
namespace Home\Controller;
use Think\Controller;
class TestController extends Controller {
	public function sendCode(){
		$mobile ='13667619389';
		$code = '123456';
		$res = sendSMS($mobile, $code);
		dump($res);
	}
}