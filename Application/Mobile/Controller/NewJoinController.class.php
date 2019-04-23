<?php
/**
 * Created by PhpStorm.
 * User: yq
 * Date: 2017/7/24
 * Time: 14:03
 */

namespace Mobile\Controller;

use Home\Logic\StoreLogic;
use Home\Logic\UsersLogic;
use Mobile\Logic\OrderGoodsLogic;
use Think\Exception;
use Think\Page;
use Think\Verify;

class NewJoinController extends MobileBaseController
{

    //入驻
    public function index(){
        #region 上级商户信息保存
        $first_leader = I('first_leader');  //上级user_id
        $iskaidian = I('iskaidian',0);        //推荐人  使用的seller_name
        $end_time = I('end_time',0);             //结束时间  防止恶意注册

        $map = array('user_id'=>$this->_uid);
        $user = M('users')->where($map)->find();
        if (!empty($user['first_leader'])){
            $first_leader = $user['first_leader'];
        }

        session('join_first_leader',$first_leader);
        session('join_iskaidian',$iskaidian);
        session('join_end_time',$end_time);
        #endregion

        #region 判断是否已有商户信息
        try{
            $this->_check_settled();
        }catch(Exception $e){
            $this->error($e->getMessage(),U('Mobile/index/index'));
        }
        #endregion

        if ($first_leader >0){
            $first_seller_name =  get_column('seller',array('user_id'=>$first_leader),'seller_name');//上级名称
            $next_role_id = get_next_role_id(get_column('users',array('user_id'=>$first_leader),'role_id'));
        }else{
            $first_seller_name = '家庭号';
            $next_role_id = 1;//平台->省级  1：省级
        }

        //入驻协议
        $agreement = get_column('distribution_role',array('role_id'=>$next_role_id),'agreement');
        $role_name = get_column('distribution_role',array('role_id'=>$next_role_id),'name');
        $this->assign('first_seller_name',$first_seller_name);//读取上级姓名
        $this->assign('role_name',$role_name);//读取本级级别名
        $this->assign('agreement',$agreement);//读取协议
        $this->display();
    }

    //商家入驻
    public function setting_info(){
        try{
            //检测上级信息
            $this->_check_settled();
        }catch(Exception $e){
            $this->error($e->getMessage(),U('Mobile/index/index'));
        }
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->_uid); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
            try{
                $data = $post = $this->_check_data();
                $post['mobile'] = $post['seller_name'];
//                $post['birthday'] = strtotime($data['birth_year'].'-'.$data['birth_month'].'-'.$data['birth_day']);
                unset($post['store_name']);
                unset($post['seller_name']);
                unset($data['id_card']);
                unset($data['wx_account']);
//                unset($data['birth_year']);
//                unset($data['birth_month']);
//                unset($data['birth_day']);
//                unset($post['birth_year']);
//                unset($post['birth_month']);
//                unset($post['birth_day']);
                if (!$userLogic->update_info($this->_uid, $post))
                    throw new Exception('绑定失败',0);

                $this->_settled($data);//入驻或成为实体店下线
                $this->success('绑定成功,请进入商户管理后台进行系列管理',U('Mobile/index/index'));
            }catch(Exception $e){
                if ($e->getCode()==0){
                    $this->error($e->getMessage());die;
                }
                $this->error($e->getMessage(),U('Mobile/index/index'));
            }
        }

        #region 商户入驻 GET
        /*$first_leader = I('first_leader','');
        if (!empty($first_leader)){
            $this->_settled('get');//入驻或成为实体店下线
        }*/
        #endregion

        #region 折扣方式获取
        $first_leader = session('join_first_leader');
        $first_role_id = get_column('users',array('user_id'=>$first_leader),'role_id');
        //如果是经销商推广 则是添加店铺 店铺需绑定折扣模式
        if ($first_role_id == 4){
            $discount = M('discount')->order('discount_id asc')->select();
            $this->assign('discount',$discount);
        }
        #endregion

        //region 获取当前角色名
        if ($first_role_id == 0){
            $next_role_id = 1;
        }else{
            $next_role_id = get_next_role_id($first_role_id);
        }
        $role_name = get_column('distribution_role',array('role_id'=>$next_role_id),'name');
        $this->assign('role_name',$role_name);
        $this->assign('role_id',$next_role_id);
        //endregion

        $this->assign('user', $user_info);
        $this->display();
    }

    public function wholesaler_register(){
        $user = M('users')->where('user_id='.$this->_uid)->find();
        if ($user['role_id'])
        $role = $this->_get_role_by_id($user['role_id']);

        if ($role != null && $user['role_id'] != 6){
            $this->error('您已经是'.$role,U('Mobile/index/index'));
        }

        $store = M('store')->where('user_id='.$this->_uid)->find();
        if (!empty($store)){
            $status = intval($store['store_state']);
            if ($status == 3){
                $url = U('Mobile/NewJoin/wholesaler_pay');
                header("Location: $url");
                exit();
            }
                $url = U('Mobile/NewJoin/wholesaler_complete',array('status'=>$status));
                header("Location: $url");
                exit();
        }

        $userLogic = new UsersLogic();

        $user_info = $userLogic->get_info($this->_uid); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
            try{
                $data = $post = $this->_check_data();
//                $post['birthday'] = strtotime($data['birth_year'].'-'.$data['birth_month'].'-'.$data['birth_day']);
                unset($post['store_name']);
                unset($post['seller_name']);
                unset($data['id_card']);
                unset($data['wx_account']);
//                unset($data['birth_year']);
//                unset($data['birth_month']);
//                unset($data['birth_day']);
//                unset($post['birth_year']);
//                unset($post['birth_month']);
//                unset($post['birth_day']);
                if (!$userLogic->update_info($this->_uid, $post))
                    throw new Exception('绑定失败',0);
                $this->saveWholesaler($user,$data);
                $this->success('绑定成功,请进入商户管理后台进行系列管理',U('Mobile/index/index'));
            }catch(Exception $e){
                if ($e->getCode()==0){
                    $this->error($e->getMessage());die;
                }
                $this->error($e->getMessage(),U('Mobile/index/index'));
            }
        }
        $this->display();
    }

    /**
     * 入驻批发商支付费用
     */
    public function wholesaler_pay(){
        $register_fee = M('config')->where(array('name'=>'wholesaler_register_fee'))->getField('value');
        $this->assign('register_fee',$register_fee);
        $this->display();
    }

    public function wholesaler_complete(){
        $msg = '你已经是批发商';
        $status  =intval(I("status",0));
        if ($status == 2) {
            $msg = '你的申请已经在审核中';
        }
        $this->assign('msg',$msg);
        $this->display();
    }

    private function saveWholesaler($user,$data){
        $map = array('user_id'=>$this->_uid);
        $first_leader = $user['first_leader'];
        $seller = M('seller')->where($map)->find();
        $store = M('store')->where($map)->find();

        if (!empty($seller) || !empty($store)){
            throw new Exception('已存在入驻信息');
        }
        #region 下线个数控制
//        if ($first_leader>0){
//            $_first_user = get_count('users',array('user_id'=>$first_leader));
//            $_first_store = get_count('store',array('user_id'=>$first_leader));
//            $_first_seller = get_count('seller',array('user_id'=>$first_leader));
//            if ( $_first_user == 0 || $_first_store == 0 || $_first_seller == 0){
//                throw new Exception('该上级不存在');
//            }
//            //上线的角色id
//            $role_id = get_column('users',array('user_id'=>$first_leader),'role_id');
//            //上线对应的最大下线人数
//            $max_num = get_column('distribution_role',array('role_id'=>$role_id),'num');
//            //上线对应的下线人数
//            $fmap = array();
//            $fmap['first_leader'] = $first_leader;
////            if ($role_id == 4)
////                $map['role_id'] = get_next_role_id($role_id); //如果是分销商，则限制的是实体店个数
////            else
//            $map['role_id'] = get_next_role_id($role_id);  //限制的角色id为下一级
//            $count_store = get_count('users',$fmap);
//        }else{
//            //平台控制下线
//            $max_num = get_max_provincial();
//            $count_store = get_count('users',array('first_leader'=>$first_leader,'role_id'=>1));
//        }
//
//        if ($max_num>0 && $count_store >= $max_num){ //数量为0则不限制
//            $this->error('下线数量已满'.$max_num.'个',U('Mobile/Index/index'));die;
//        }
            $next_role_id = 6;
        if ($next_role_id > 0){
            if ($first_leader > 0){
                $store_first = get_column('store',array('user_id'=>$first_leader),'store_id');
                if (empty($store_first)){
                    $store_first = 0;
                }
            } else{
                $store_first = 0;
            }
            //店铺信息
            $store = array(
                'store_name'    =>  $data['store_name'],
                'user_name'     =>  $data['seller_name'],
                'store_state'   =>  1,//0关闭，1开启，2审核中,3 等待支付 因为是二维码注册，以防再次分享注册
                'seller_name'   =>  $data['seller_name'],
                //'password'    =>  $data['password'],
                'user_id'       =>  $this->_uid,
                'store_time'    =>  time(),
                'is_own_shop'   =>  0,
                'is_wholesaler' => 1,
                'first_leader' =>  $store_first ,
                'discount_id'   =>  isset($data['discount_id']) ? $data['discount_id'] : 0, //折扣id
                'store_address' => $data['store_address'] ? $data['store_address'] : '',
                'store_lat'     => $data['store_lat'] ? $data['store_lat'] : '',
                'store_lng'     => $data['store_lng'] ? $data['store_lng'] : '',
            );

            $storeLogic = new \Mobile\Logic\StoreLogic();
            if($storeLogic->addStore($store,6)){
                $new_info = array('first_leader'=>$first_leader,'iskaidian'=>1,'role_id'=>6,'is_distribut'=>1); //除了自营店铺  其他入驻均为分销商
                $res = M('users')->where($map)->save($new_info);
                if ($res === false) {
                    M('store')->where($map)->delete();
                    M('seller')->where($map)->delete();
                    throw new Exception('入驻信息绑定失败');
                }

                //发送短信消息到上级
                //$this->_send_msg_to_superior($first_leader,$store['user_id']);
                //发送短信
                $this->_send_sms($store['user_id']);

                $this->success('店铺入驻成功,请前往pc端商户管理后台进行操作',U('Mobile/Index/index'));die;
            }else{
                $new_info = array('first_leader'=>0,'iskaidian'=>0,'role_id'=>0,'is_distribut'=>0); //除了自营店铺  其他入驻均为分销商
                M('users')->where($map)->save($new_info);
                throw new Exception('店铺添加失败');
            }
        }

    }

    //检测信息
    protected function _check_data(){
        $data = I('post.');
//        if (!check_mobile($data['mobile']))
//            throw new Exception('手机格式不正确',0);
        if (empty($data['new_password']) || strlen($data['new_password']) < 6 || strlen($data['new_password'])>16)
            throw new Exception('密码为6-16位字母数字字符组成',0);
        if ($data['new_password'] != $data['confirm_password'])
            throw new Exception('两次密码不一致',0);

        $data['password'] = encrypt($data['new_password']);
        unset($data['new_password']);
        unset($data['confirm_password']);

        //店铺信息
        if (empty($data['store_name']))
            throw new Exception('请输入店铺名');
        if (get_count('store',array('store_name'=>$data['store_name'])))
            throw new Exception('店铺名称已存在',0);

        if (empty($data['seller_name']))
            throw new Exception('请输入卖家账户');
        if (get_count('seller',array('seller_name'=>$data['seller_name'])))
            throw new Exception('手机号已被占用,请更换其他手机号作为商户账号',0);

        if (isset($data['discount_id'])){
            if (empty($data['discount_id'])){
                throw new Exception('请选择入驻折扣方式');
            }
        }

        //region 新加字段
        if (empty($data['user_name']))
            throw new Exception('请输真实姓名');
//        if (empty($data['id_card']))
//            throw new Exception('请输身份证号');
        //endregion

        //region 验证码确认

        if (!$data['mobile_code'])
            throw new Exception('请输入验证码',0);

        $userLogic = new UsersLogic();

        $check_code = $userLogic->sms_code_verify($data['seller_name'], $data['mobile_code'], $this->session_id);
        if ($check_code['status'] != 1)
            throw new Exception($check_code['msg'],0);
        //endregion
        return $data;
    }

    //检测上级信息
    protected function _check_settled(){
        $first_leader = I('first_leader') ? session('join_first_leader') : session('join_first_leader') ;  //上级user_id

        $iskaidian = I('iskaidian',0) ? I('iskaidian',0) : session('join_iskaidian');        //推荐人  使用的seller_name
        $end_time = I('end_time',0) ? session('join_end_time') : session('join_end_time') ;             //结束时间  防止恶意注册
        if ($first_leader == '')
            throw new Exception('上级信息不存在或已过期,请重新获取二维码'); //上级id不存在直接返回
        if ($iskaidian !=1)
            throw new Exception('入驻信息不正确,请重新获取二维码');
        if (shop_share_time() >0 && $end_time > 0 && $end_time < time())
            throw new Exception('信息过期,请重新获取二维码');

        $map = array('user_id'=>$this->_uid);
        $user = M('users')->where($map)->find();
        $seller = M('seller')->where($map)->find();
        $store = M('store')->where($map)->find();
        if (!empty($user['first_leader']) ){
            throw new Exception('已存在上级');
        }
        if (!empty($seller) || !empty($store)){
            throw new Exception('已存在入驻信息');
        }

        #region 下线个数控制
        if ($first_leader>0){
            $_first_user = get_count('users',array('user_id'=>$first_leader));
            $_first_store = get_count('store',array('user_id'=>$first_leader));
            $_first_seller = get_count('seller',array('user_id'=>$first_leader));
            if ( $_first_user == 0 || $_first_store == 0 || $_first_seller == 0){
                throw new Exception('该上级不存在');
            }
            //上线的角色id
            $role_id = get_column('users',array('user_id'=>$first_leader),'role_id');
            //上线对应的最大下线人数
            $max_num = get_column('distribution_role',array('role_id'=>$role_id),'num');
            //上线对应的下线人数
            $map = array();
            $map['first_leader'] = $first_leader;
//            if ($role_id == 4)
//                $map['role_id'] = get_next_role_id($role_id); //如果是分销商，则限制的是实体店个数
//            else
            $map['role_id'] = get_next_role_id($role_id);  //限制的角色id为下一级
            $count_store = get_count('users',$map);
        }else{
            //平台控制下线
            $max_num = get_max_provincial();
            $count_store = get_count('users',array('first_leader'=>$first_leader,'role_id'=>1));
        }

        if ($max_num>0 && $count_store >= $max_num){ //数量为0则不限制
            $this->error('下线数量已满'.$max_num.'个',U('Mobile/Index/index'));die;
        }
        #endregion
    }

    //入驻或成为实体店下线
    protected function _settled($data){
        //检测上级信息
        $this->_check_settled();
        $first_leader = session('join_first_leader');  //上级user_id
        $iskaidian = session('join_iskaidian');        //推荐人  使用的seller_name

        //商户入驻代码
        //>>1.设置商户入驻判断条件  is_distribut（是否为经销商） introducer（介绍人-seller_name）
        $map = array('user_id'=>$this->_uid);

        //获取下一级角色id
        if ($first_leader>0){
            $next_role_id = get_next_role_id(get_column('users',array('user_id'=>$first_leader),'role_id'));
        }else{
            $next_role_id = 1;//平台->省级  1：省级
        }

        if ($next_role_id > 0){
            if ($first_leader > 0)
                $store_first = get_column('store',array('user_id'=>$first_leader),'store_id');
            else
                $store_first = 0;
            //店铺信息
			$state  = 1;
            if ($next_role_id < 5){
                $state = 2;
            }
			//分销商和县级商不审核
			if($next_role_id == 3 || $next_role_id == 4){
				$state = 1;
			}
			
            $store = array(
                'store_name'    =>  $data['store_name'],
                'user_name'     =>  $data['seller_name'],
                'store_state'   =>  $state,//0关闭，1开启，2审核中  因为是二维码注册，以防再次分享注册
                'seller_name'   =>  $data['seller_name'],
                //'password'    =>  $data['password'],
                'user_id'       =>  $this->_uid,
                'store_time'    =>  time(),
                'is_own_shop'   =>  0,
                'first_leader' =>  $store_first ,
                'discount_id'   =>  isset($data['discount_id']) ? $data['discount_id'] : 0, //折扣id

                'store_address' => $data['store_address'] ? $data['store_address'] : '',
                'store_lat'     => $data['store_lat'] ? $data['store_lat'] : '',
                'store_lng'     => $data['store_lng'] ? $data['store_lng'] : '',
            );

            $storeLogic = new \Mobile\Logic\StoreLogic();
            if($storeLogic->addStore($store,$next_role_id)){
                $new_info = array('first_leader'=>$first_leader,'iskaidian'=>$iskaidian,'role_id'=>$next_role_id,'is_distribut'=>1); //除了自营店铺  其他入驻均为分销商
                $res = M('users')->where($map)->save($new_info);
                if ($res === false) {
                    M('store')->where($map)->delete();
                    M('seller')->where($map)->delete();
                    throw new Exception('入驻信息绑定失败');
                }

                //发送短信消息到上级
                //$this->_send_msg_to_superior($first_leader,$store['user_id']);
                //发送短信
                $this->_send_sms($store['user_id']);

                $this->success('店铺入驻成功,请前往pc端商户管理后台进行操作',U('Mobile/Index/index'));die;
            }else{
                $new_info = array('first_leader'=>0,'iskaidian'=>0,'role_id'=>0,'is_distribut'=>0); //除了自营店铺  其他入驻均为分销商
                M('users')->where($map)->save($new_info);
                throw new Exception('店铺添加失败');
            }
        }

    }

    /**
     * 新发送短信方法
     * @param $uid      用户ID
     * @return bool
     */
    private function _send_sms($uid){
        try{
            //>>1.查找当前入驻的会员信息
            $alias = 'u';
            $join = 'JOIN __STORE__ as s ON s.user_id = u.user_id';
            $field = 'u.nickname,u.mobile,u.user_name,u.role_id,u.first_leader,u.reg_id,u.openid,s.store_id,s.store_name';
            $user = M('users')->alias($alias)->join($join)->field($field)
                ->where(array('u.user_id'=>$uid))->find();
            if (empty($user))
                throw new Exception('用户信息错误');
            //>>2.递归查出所有上级
            $leader_list = $this->_find_leader($user,[]);
            if (empty($leader_list))
                throw new Exception('上级信息不存在');
            //>>3.循环构造短信内容并发送短信
            if ($user['role_id'] == 4){
                $content = $user['user_name'];
            }else{
                $content = $user['store_name'].','.$user['user_name'];
            }
            $content .= " 成功成为".$this->_build_content_tail($leader_list,$user).$this->_get_role_by_id($user['role_id']);
            M('test_msg')->add(array('msg'=>$content));
            foreach ($leader_list as $key => $vo){
                send_sms($vo['mobile'],$content);   //发送短信
                send_jpush_msg($vo['reg_id'],$content);     //极光推送
                //send_wx_msg($vo['openid'],$content);        //微信消息
                save_user_news($vo['user_id'],4,0,$content);//消息记录
            }
            //>>4.发送用户入驻成功信息
            send_sms($user['mobile'],$content);   //发送短信
            send_jpush_msg($user['reg_id'],$content);     //极光推送
            //send_wx_msg($user['openid'],$content);        //微信消息
            save_user_news($user['user_id'],4,0,$content);//消息记录
        }catch(Exception $e){
            //暂不做其他操作
            return false;
        }
    }

    /**
     * 构造短信尾部
     * @param array $list     上级列表
     * @return string   返回的短信尾部信息
     */
    /**
     * 构造短信尾部
     * @param array $list     上级列表
     * @param array $user    用户
     * @return string   返回的短信尾部信息
     */
    private function _build_content_tail($list = [],$user = []){
        $tail = '';
        $list = array_reverse($list); //保留原始数组的key，返回value顺序相反的数组
        if ($user['role_id'] > 3){ //县级商以下的角色
            foreach ($list as $key => $vo){
                if ($vo['role_id'] == 3){ //县级商名
                    $tail .= $vo['store_name'] . $this->_get_role_by_id($vo['role_id']) .'下的 ';
                    break;
                }
            }
        }/*else{
            foreach ($list as $key => $vo){
                $tail .= $vo['store_name'] . $this->_get_role_by_id($vo['role_id']) .'下的 ';
                break;
            }
        }*/
        return $tail;
    }

    private function _build_content_tail_back($list = []){
        $tail = '';
        $list = array_reverse($list); //保留原始数组的key，返回value顺序相反的数组
        foreach ($list as $key => $vo){
            $tail .= $vo['store_name'] . $this->_get_role_by_id($vo['role_id']) .'下的 ';
        }
        return $tail;
    }

    /**
     * 根据当前用户查询上级
     * @param array $user   起始用户信息
     * @param array $list   返回的列表数据
     * @return array
     */
    private function _find_leader($user = [],$list = []){
        if ($user['role_id'] >1){
            $alias = 'u';
            $join = 'JOIN __STORE__ as s ON s.user_id = u.user_id';
            $field = 'u.nickname,u.mobile,u.role_id,u.user_name,u.first_leader,u.reg_id,u.openid,s.store_id,s.store_name';
            $_user = M('users')->alias($alias)->join($join)->field($field)
                ->where(array('u.user_id'=>$user['first_leader']))->find();
            if (!empty($_user)){
                $list[] = $_user;
                return $this->_find_leader($_user,$list);
            }else{
                return $list;
            }
        }
        return $list;
    }

    //发送短信
    private function _send_msg_to_superior($first_leader,$uid){
        M('test_msg')->add(array('msg'=>'_send_msg_to_superior'.$first_leader));
        $store = M('store')->where(array('user_id'=>$uid))->find();
        $user = M('users')->where(array('user_id'=>$uid))->find();
        $content = $content = "{$store['store_name']},{$user['mobile']},成功注册成为您的".
            $this->_get_role_by_id(get_column('users',array('user_id'=>$uid),'role_id'));
        if ($first_leader == 0){
            $content .= '.上级：平台';
            $this->_send_msg_to_level([],$content);
        }else {
            $first_leader_user = M('users')->where(array('user_id'=>$first_leader))->find();
            if (empty($first_leader_user)) return false;
            $content .= '.上级：'.$this->_send_content($first_leader_user,'',3);
            M('test_msg')->add(array('msg'=>$content));
            $this->_send_msg_to_level($first_leader_user,$content);
        }
    }

    /**
     * 构造内容
     * @param array $user   上级用户
     * @param string $content   内容
     * @param int $time 次数
     * @return string
     */
    private function _send_content($user = array(),$content = '',$time = 3){
        if (!empty($user)){
            $store_name = M('store')->where(array('user_id'=>$user['user_id']))->getField('store_name');
            if (!empty($store_name))
                $content .= $store_name . ' ';
            M('test_msg')->add(array('msg'=>$time .'-'.serialize($content)));
            if ($user['first_leader'] > 0 && $time >= 1){
                $_user = M('users')->where(array('user_id'=>$user['first_leader']))->find();
                if (!empty($_user))
                    return  $this->_send_content($_user,$content,$time-1);
                else
                    return $content;
            }
        }
        return $content;
    }

    //发送短信
    private function _send_msg_to_level($user,$content){
        M('test_msg')->add(array('msg'=>'first_leader'.$user['user_id']));
        M('test_msg')->add(array('msg'=>'__first_leader'.$user['first_leader']));
        if (!empty($user)){ //上级为非平台
            //发送本次短信
            send_sms($user['mobile'],$content);//$user['mobile']
            if ($user['first_leader'] > 0 && $user['role_id'] != 1){ //上级为非平台
                //找出上级
                $_user = M('users')->where(array('user_id'=>$user['first_leader']))->find();
                M('test_msg')->add(array('msg'=>'--'.serialize($_user)));
                //发送短信
                $this->_send_msg_to_level($_user,$content);
            }else{
                send_sms('17623208524',$content);
                return true;
            }
        }else{
            send_sms('17623208524',$content);
            return true;
        }//平台 单独操作
        return true;
    }

    /**
     * 获取角色名
     * @param $role_id
     * @return string
     */
    private function _get_role_by_id($role_id){
        switch ($role_id){
            case 1:$role =  '省级商';break;
            case 2:$role =  '地级商';break;
            case 3:$role =  '县级商';break;
            case 4:$role =  '分销商';break;
            case 5:$role =  '实体店';break;
            case 6:$role =  '批发商';break;
        }
        return $role;
    }



}