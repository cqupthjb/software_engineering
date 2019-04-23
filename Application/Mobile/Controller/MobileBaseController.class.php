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
namespace Mobile\Controller;
use Common\Controller\AuthorizeController;
use Home\Logic\UsersLogic;
use Think\Controller;
use Think\Exception;

class MobileBaseController extends Controller {
    public $session_id;
    public $weixin_config;
    public $cateTrre = array();
    public $_user;
    public $_uid;
    public $_is_subscribe = true;//是否开启强制关注
    public $payMethod = "wechat";
    /*
     * 初始化操作
     */
    public function _initialize() {
        $first_leader = I('first_leader',0);
        //region 非微信端访问
        if (strpos($_SERVER['HTTP_USER_AGENT'],'MicroMessenger') === false){
            if (strpos($_SERVER['HTTP_USER_AGENT'],"AlipayClient") && ACTION_NAME =='scan_pay'){
                $this->payMethod = "alipay";
            } else {
                //不在免转换方法中
                $not_action = array('send_validate_code');
                if (!in_array(ACTION_NAME, $not_action)){
                    $url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
                    if (stristr($url,'Mobile')){
                        $url = str_ireplace("Mobile","App",$url);
                        header("Location: $url");
                        exit();
                    }
                }
            }

        }
        //endregion
        $this->session_id = session_id(); // 当前的 session_id       
        define('SESSION_ID',$this->session_id); //将当前的session_id保存为常量，供其它方法调用
        // 判断当前用户是否手机                
        if(isMobile())
            cookie('is_mobile','1',3600); 
        else 
            cookie('is_mobile','0',3600);

        //微信浏览器
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger') && empty($_SESSION['openid'])){
            $this->weixin_config = M('wx_user')->find(); //获取微信配置
            $this->assign('wechat_config', $this->weixin_config);
            if(is_array($this->weixin_config) && $this->weixin_config['wait_access'] == 1){
                $wxuser = $this->GetOpenid(); //授权获取openid以及微信用户信息
                session('subscribe', $wxuser['subscribe']);// 当前这个用户是否关注了微信公众号

                //微信自动登录                             
                $logic = new UsersLogic();
                $data = $logic->thirdLogin($wxuser);
                if($data['status'] == 1){
                    $this->_user = $data['result'];
                    $this->_uid = $data['result']['user_id'];
                    session('user',$data['result']);
                    setcookie('user_id',$data['result']['user_id'],null,'/');
                    setcookie('is_distribut',$data['result']['is_distribut'],null,'/');
                    setcookie('uname',$data['result']['nickname'],null,'/');                    
                    // 登录后将购物车的商品的 user_id 改为当前登录的id
                    M('cart')->where("session_id = '{$this->session_id}'")->save(array('user_id'=>$data['result']['user_id']));
                    $_SESSION['openid'] = $this->_user['openid'];
                    //诱导关注   正式公众号启用
//                    if($wxuser['subscribe']!=1){
//                        $url = "http://mp.weixin.qq.com/s/B97PmTX_zqPxU_fNvkbb-g";
//                        header("Location: $url");
//                        exit();
//                    }
                    //成为下线
                    $this->_subordinate($first_leader);
                }
            }

            // 微信Jssdk 操作类 用分享朋友圈 JS            
            $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
            $signPackage = $jssdk->GetSignPackage();
            $this->assign('signPackage', $signPackage);
        }
        #region 若是微信浏览器 并且session 已存在openid  将其用户信息找出
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger') && !empty($_SESSION['openid'])){
            $user = M('users')->where(array('openid'=>$_SESSION['openid']))->find();
            if (!empty($user)) {
                $this->_user = $user;
                $this->_uid = $user['user_id'];
                session('user',$user);
                setcookie('user_id',$user['user_id'],null,'/');
                setcookie('is_distribut',$user['is_distribut'],null,'/');
                setcookie('uname',$user['nickname'],null,'/');
                // 登录后将购物车的商品的 user_id 改为当前登录的id
                M('cart')->where("session_id = '{$this->session_id}'")->save(array('user_id'=>$user['user_id']));
            }else{
                $_SESSION['openid'] = null;
                $wxuser = $this->GetOpenid(); //授权获取openid以及微信用户信息
                session('subscribe', $wxuser['subscribe']);// 当前这个用户是否关注了微信公众号
                //微信自动登录
                $logic = new UsersLogic();
                $data = $logic->thirdLogin($wxuser);
                if($data['status'] == 1){
                    $this->_user = $data['result'];
                    $this->_uid = $data['result']['user_id'];
                    session('user',$data['result']);
                    setcookie('user_id',$data['result']['user_id'],null,'/');
                    setcookie('is_distribut',$data['result']['is_distribut'],null,'/');
                    setcookie('uname',$data['result']['nickname'],null,'/');
                    // 登录后将购物车的商品的 user_id 改为当前登录的id
                    M('cart')->where("session_id = '{$this->session_id}'")->save(array('user_id'=>$data['result']['user_id']));
                    $_SESSION['openid'] = $this->_user['openid'];
                    //成为下线
                    $this->_subordinate($first_leader);
                }
            }

            // 微信Jssdk 操作类 用分享朋友圈 JS
            $this->weixin_config = M('wx_user')->find(); //获取微信配置
            if ($this->weixin_config){
                $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
                $signPackage = $jssdk->GetSignPackage();
                $this->assign('signPackage', $signPackage);
            }
        }
        #endregion
        //成为下线
        $this->_subordinate($first_leader);
        $is_subscribe = session('is_subscribe');
        //强制关注
        if (strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger') && $this->_is_subscribe && $is_subscribe!=true){//&& empty(I('first_leader'))
            $this->is_subscribe($this->get_access_token(),session('user')['openid']);
        }
        $this->assign('_uid',$this->_uid);
        $this->assign('_user',$this->_user);
        $this->public_assign();
    }

    //region 强制关注
    protected function is_subscribe($access_token,$openid){
        if (empty($openid)){
            return ;
        }
        // 获取用户 信息
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$openid";
        $subscribe_info = httpRequest($url,'GET');
        $subscribe_info = json_decode($subscribe_info,true);
        if (isset($subscribe_info['errcode']) && $subscribe_info == 40001){
            $access_token = $this->get_access_token(1);
            $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$openid";
            $subscribe_info = httpRequest($url,'GET');
            $subscribe_info = json_decode($subscribe_info,true);
        }
        //TODO test message
//        M('test_msg')->add(array('msg'=>serialize($subscribe_info)));
        if(isset($subscribe_info['subscribe']) && $subscribe_info['subscribe']!=1){
            //$url = "http://mp.weixin.qq.com/s/B97PmTX_zqPxU_fNvkbb-g";
            $url = "http://mp.weixin.qq.com/s/gjgIvJhqAhBuAdAWh-WiXg";
            header("Location: $url");
            exit();
        }else{
            session('subscribe',true);
        }
    }
    //endregion

    //成为下线
    protected function _subordinate($first_leader){
        //region 实体店下线
        $first_leader = $first_leader ? $first_leader : I('first_leader',0);  //上级user_id
        $iskaidian = I('iskaidian',0);        //推荐人  使用的seller_name
        $end_time = I('end_time',0);             //结束时间  防止恶意注册
        if (empty($this->_uid)){
            $this->_user = session('user');
            $this->_uid = $this->_user['user_id'];
        }
        
        $user = M('users')->where(array('user_id'=>$this->_uid))->find();
        //过滤商户入驻  以上信息都存在则为商户入驻
        if ($iskaidian == 0 && $end_time == 0 && $first_leader > 0 && $first_leader != $user['user_id']){
            setcookie('first_leader',$first_leader,time()+60,'/');

            if(empty($user['first_leader']) && get_count('users',array('user_id'=>$first_leader)) > 0 && $this->_uid !=$first_leader)
            {
                //上级信息
                $first_leader = M('users')->where(array('user_id'=>$first_leader))->find();
                //自营店统计
                $store_count = M('store')->where(array('user_id'=>$first_leader['user_id'],'is_own_shop'=>1))->count();
                //角色ID   0：用户|自营店   5：实体店   4：分销商
                if (!empty($first_leader) && in_array($first_leader['role_id'],array(0,4,5)) && $store_count == 0){

                    M('users')->where(array('user_id'=>$this->_uid))->save(array('first_leader'=>$first_leader['user_id']));  //成为下线
                    $this->_user = M('users')->where(array('user_id'=>$this->_uid))->find();

                    //消息推送
                    $wx_content = "恭喜~~~ \n  昵称：".$this->_user['nickname']."\n  定位：". $this->_user['wxprovince'].$this->_user['wxcity']." \n  时间：".date('Y-m-d H:i',time())." \n 通过扫描你的分享二维码,成为您的一级粉丝！";
                    send_wx_msg($first_leader['openid'], $wx_content); //发送微信消息   一级
                    if (!empty($first_leader['reg_id'])){
                        $title = '下线消息';
                        send_jpush_msg($first_leader['reg_id'] ,$title, $wx_content,'sub');
                    }

                    if (!empty($first_leader['first_leader'])){  //二级
                        $second_leader = M('users')->where(array('user_id'=>$first_leader['first_leader']))->find();
                        if(!empty($second_leader)){
                            $wx_content = "恭喜~~~ \n  昵称：".$this->_user['nickname']."\n  定位：". $this->_user['wxprovince'].$this->_user['wxcity']." \n  时间：".date('Y-m-d H:i',time())."通过扫描分享二维码,成为您的二级粉丝！";
                            send_wx_msg($second_leader['openid'], $wx_content); //发送微信消息 二级
                            if (!empty($second_leader['reg_id'])){
                                $title = '下线消息';
                                send_jpush_msg($second_leader['reg_id'] ,$title, $wx_content,'sub');
                            }

                            if (!empty($second_leader['first_leader'])){ //三级
                                $third_leader = M('users')->where(array('user_id'=>$second_leader['first_leader']))->find();
                                if(!empty($third_leader)){
                                    $wx_content = "恭喜~~~ \n  昵称：".$this->_user['nickname']."\n  定位：". $this->_user['wxprovince'].$this->_user['wxcity']." \n  时间：".date('Y-m-d H:i',time())."通过扫描分享二维码,成为您的家庭号粉丝！";
                                    send_wx_msg($third_leader['openid'], $wx_content); //发送微信消息  三级
                                    if (!empty($third_leader['reg_id'])){
                                        $title = '下线消息';
                                        send_jpush_msg($third_leader['reg_id'] ,$title, $wx_content,'sub');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        //endregion

        //region XX下线  已禁用
        //$first_leader = $_GET['first_leader'];
        /*$_new = array();
        $_new['first_leader'] = 0;
        if($first_leader > 0)
            $_new['first_leader'] = $first_leader; // 微信授权登录返回时 get 带着参数的
        // 如果找到他老爸还要找他爷爷他祖父等
        if($_new['first_leader'] > 0 && empty($user['first_leader']) && $first_leader != $user['user_id'])
        {
            $first_leader = M('users')->where("user_id = {$_new['first_leader']}")->find();
            $_new['second_leader'] = $first_leader['first_leader']; //  第一级推荐人
            $_new['third_leader'] = $first_leader['second_leader']; // 第二级推荐人
            M('users')->where(array('user_id'=>$this->_uid))->save($_new);  //添加上级分销商
            $this->_user = M('users')->where(array('user_id'=>$this->_uid))->find();
        }*/

        //endregion
    }
    
    /**
     * 保存公告变量到 smarty中 比如 导航 
     */   
    public function public_assign()
    {
        
       $tpshop_config = array();
       $tp_config = M('config')->cache(true,TPSHOP_CACHE_TIME)->select();       
       foreach($tp_config as $k => $v)
       {
       	  if($v['name'] == 'hot_keywords'){
       	  	 $tpshop_config['hot_keywords'] = explode('|', $v['value']);
       	  }       	  
          $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
       }                        
       
       $goods_category_tree = get_goods_category_tree();    
       $this->cateTrre = $goods_category_tree;
       $this->assign('goods_category_tree', $goods_category_tree);                     
       $brand_list = M('brand')->cache(true,TPSHOP_CACHE_TIME)->field('id,cat_id1,logo,is_hot')->where("cat_id1>0")->select();              
       $this->assign('brand_list', $brand_list);
       $this->assign('tpshop_config', $tpshop_config);
    }      

    // 网页授权登录获取 OpendId
    public function GetOpenid()
    {
        if($_SESSION['openid'])
            return $_SESSION['openid'];
        //通过code获得openid
        if (!isset($_GET['code'])){
            //触发微信返回code码
            //$baseUrl = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
            $baseUrl = urlencode($this->get_url());
            $url = $this->__CreateOauthUrlForCode($baseUrl); // 获取 code地址
            
            Header("Location: $url"); // 跳转到微信授权页面 需要用户确认登录的页面
            exit();
        } else {
            //上面获取到code后这里跳转回来
            $code = $_GET['code'];
            $data = $this->getOpenidFromMp($code);//获取网页授权access_token和用户openid
            $data2 = $this->GetUserInfo($data['access_token'],$data['openid']);//获取微信用户信息
            $data['nickname'] = empty($data2['nickname']) ? '微信用户' : trim($data2['nickname']);
            $data['sex'] = $data2['sex'];
            $data['head_pic'] = $data2['headimgurl']; 
            $data['subscribe'] = $data2['subscribe'];                         
            $_SESSION['openid'] = $data['openid'];
            $data['oauth'] = 'weixin';

            $data['country'] = $data2['country'];
            $data['province'] = $data2['province'];
            $data['city'] = $data2['city'];

            if(isset($data2['unionid'])){
            	$data['unionid'] = $data2['unionid'];
            }
            return $data;
        }
    }

    /**
     * 获取当前的url 地址
     * @return type
     */
    private function get_url() {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    }    
    
    /**
     *
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     *
     * @return openid
     */
    public function GetOpenidFromMp($code)
    {
        //通过code获取网页授权access_token 和 openid 。网页授权access_token是一次性的，而基础支持的access_token的是有时间限制的：7200s。
    	//1、微信网页授权是通过OAuth2.0机制实现的，在用户授权给公众号后，公众号可以获取到一个网页授权特有的接口调用凭证（网页授权access_token），通过网页授权access_token可以进行授权后接口调用，如获取用户基本信息；
    	//2、其他微信接口，需要通过基础支持中的“获取access_token”接口来获取到的普通access_token调用。
        $url = $this->__CreateOauthUrlForOpenid($code);       
        $ch = curl_init();//初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);         
        $res = curl_exec($ch);//运行curl，结果以jason形式返回            
        $data = json_decode($res,true);         
        curl_close($ch);
        return $data;
    }
    
    
        /**
     *
     * 通过access_token openid 从工作平台获取UserInfo      
     * @return openid
     */
    public function GetUserInfo($access_token,$openid)
    {         
        // 获取用户 信息
        $url = $this->__CreateOauthUrlForUserinfo($access_token,$openid);
        $ch = curl_init();//初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);         
        $res = curl_exec($ch);//运行curl，结果以jason形式返回            
        $data = json_decode($res,true);            
        curl_close($ch);
        //获取用户是否关注了微信公众号， 再来判断是否提示用户 关注
        if(!isset($data['unionid'])){
        	$access_token2 = $this->get_access_token();//获取基础支持的access_token
        	$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token2&openid=$openid";
        	$subscribe_info = httpRequest($url,'GET');
        	$subscribe_info = json_decode($subscribe_info,true);
        	$data['subscribe'] = $subscribe_info['subscribe'];
        }
        return $data;
    }
    
    
    public function get_access_token($expire_time=0){
        //判断是否过了缓存期
        if ($expire_time == 0 )
            $expire_time = get_column('wx_user','1=1','web_expires');//$this->weixin_config['web_expires'];
        if($expire_time > time()){
           return $this->weixin_config['web_access_token'];
        }
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->weixin_config[appid]}&secret={$this->weixin_config[appsecret]}";
        $return = httpRequest($url,'GET');
        $return = json_decode($return,1);
        $web_expires = time() + 7140; // 提前60秒过期
        M('wx_user')->where(array('id'=>$this->weixin_config['id']))->save(array('web_access_token'=>$return['access_token'],'web_expires'=>$web_expires));
        return $return['access_token'];
    }    

    /**
     *
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     *
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"] = $this->weixin_config['appid'];
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
//        $urlObj["scope"] = "snsapi_base";
        $urlObj["scope"] = "snsapi_userinfo";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }

    /**
     *
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = $this->weixin_config['appid'];
        $urlObj["secret"] = $this->weixin_config['appsecret'];
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }

    /**
     *
     * 构造获取拉取用户信息(需scope为 snsapi_userinfo)的url地址     
     * @return 请求的url
     */
    private function __CreateOauthUrlForUserinfo($access_token,$openid)
    {
        $urlObj["access_token"] = $access_token;
        $urlObj["openid"] = $openid;
        $urlObj["lang"] = 'zh_CN';        
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/userinfo?".$bizString;                    
    }    
    
    /**
     *
     * 拼接签名字符串
     * @param array $urlObj
     *
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign"){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

}