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
 * 微信交互类
 */
namespace App\Controller;
use Home\Logic\UsersLogic;
use Think\Exception;

class LoginApiController extends MobileBaseController {
    public $config;
    public $oauth;
    public $class_obj;

    public function __construct(){
        parent::__construct();
        $pass = array('wx_login','save_jpush_info','check_reg_id');
        if (!in_array(ACTION_NAME,$pass)){
            $this->oauth = I('get.oauth');
            //获取配置
            $data = M('Plugin')->where("code='{$this->oauth}' and  type = 'login' ")->find();
            $this->config = unserialize($data['config_value']); // 配置反序列化
            if(!$this->oauth)
                $this->error('非法操作',U('Home/User/login'));
            include_once  "plugins/login/{$this->oauth}/{$this->oauth}.class.php";
            $class = '\\'.$this->oauth; //
            $login = new $class($this->config); //实例化对应的登陆插件
            $this->class_obj = $login;
        }
    }

    public function login(){
        if(!$this->oauth)
            $this->error('非法操作',U('Home/User/login'));
        include_once  "plugins/login/{$this->oauth}/{$this->oauth}.class.php";
        $this->class_obj->login();
    }

    public function callback(){
        $data = $this->class_obj->respon();
        $logic = new UsersLogic();
        $data = $logic->thirdLogin($data);
        if($data['status'] != 1)
            $this->error($data['msg']);
        session('user',$data['result']);
        // 登录后将购物车的商品的 user_id 改为当前登录的id            
        M('cart')->where("session_id = '{$this->session_id}'")->save(array('user_id'=>$data['result']['user_id']));
        $this->success('登陆成功',U('User/index'));
    }

    //第三方登录-微信登录
    public function wx_login(){
        try{
            $data = I('post.');
            if (empty($data['unionid']))
                throw new Exception('未获取到用户信息');

            $this->weixin_config = M('wx_user')->find(); //获取微信配置
            $this->assign('wechat_config', $this->weixin_config);
            if(is_array($this->weixin_config) && $this->weixin_config['wait_access'] == 1){
                //$wxuser = $this->GetOpenid(); //授权获取openid以及微信用户信息
                //session('subscribe', $wxuser['subscribe']);// 当前这个用户是否关注了微信公众号
                //$this->ajaxReturn(array('wx'=>$wxuser));


                //组装微信用户信息
                $wxuser = array();
                $wxuser['unionid'] = $data['unionid']; //开放平台绑定之后才会有
                $wxuser['oauth']   = 'weixin';
                $wxuser['openid'] = $data['openid'];

                if (!isset($data['is_auto'])){
                    $wxuser['nickname'] = empty($data['nickname']) ? '微信用户' : preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', trim($data['nickname']));
                    $wxuser['sex'] = $data['sex'];
                    $wxuser['head_pic'] = $data['headimgurl'];
                    $wxuser['subscribe'] = $data['subscribe'];

                    $wxuser['country'] = $data['country'];
                    $wxuser['province'] = json_encode($data['province']);
                    $wxuser['city'] = $data['city'];
                }

                //微信自动登录
                $logic = new UsersLogic();
                $data = $logic->thirdLogin($wxuser,false);

                if($data['status'] == 1){
                    $this->_user = $data['result'];
                    $this->_uid = $data['result']['user_id'];
                    session('user',$data['result']);
                    setcookie('user_id',$data['result']['user_id'],null,'/');
                    setcookie('is_distribut',$data['result']['is_distribut'],null,'/');
                    setcookie('uname',$data['result']['nickname'],null,'/');
                    // 登录后将购物车的商品的 user_id 改为当前登录的id
                    M('cart')->where("session_id = '{$this->session_id}'")->save(array('user_id'=>$data['result']['user_id']));

                    $res = array('status'=>1,'msg'=>'登录成功','url'=>U('Index/index'));
                }else{
                    throw new Exception('登录失败');
                }
            }else{
                throw new Exception('登录失败!');
            }

        }catch(Exception $e){
            $res = array('status'=>0,'msg'=>$e->getMessage());
        }
        $this->ajaxReturn($res);
    }

    //存储Jpush别名
    public function save_jpush_info(){
        $unionid = I('unionid');
        //$alias = I('alias');
        $reg_id = I('reg_id');
        try{
            if (empty($unionid) || empty($reg_id))//empty($alias) ||
                throw new Exception('参数缺省');
            $user = M('users')->where(array('unionid'=>$unionid))->find();
            if (empty($user))
                throw new Exception('该用户不存在');
            $_reg_id = $user['reg_id'];
            $res = M('users')->where(array('unionid'=>$unionid))->save(array('reg_id'=>$reg_id));
            if ($res === false)
                throw new Exception('绑定reg_id失败');

            $res = array('status'=>1,'msg'=>'绑定reg_id成功','old_reg_id'=>$_reg_id,'reg_id'=>$reg_id);
        }catch(Exception $e){
            $res = array('status'=>0,'msg'=>$e->getMessage());
        }
        $this->ajaxReturn($res);
    }

    //检测设备登录
    public function check_reg_id(){
        try{
            $reg_id = I('reg_id');
            $unionid = I('unionid');
            if (empty($reg_id) || empty($unionid))
                throw new Exception('参数缺省',400);
            $_reg_id = M('users')->where(array('unionid'=>$unionid))->getField('reg_id');
            if (empty($_reg_id))
                throw new Exception('oldRegID不存在',401);
            if ($_reg_id == $reg_id){
                throw new Exception('success',200);
            }
            $res = array('status'=>0,'msg'=>'当前账号在其它设备登录');
        }catch (Exception $e){
            $res = array('status'=>$e->getCode(),'msg'=>$e->getMessage());
        }
        $this->ajaxReturn($res);
    }
}