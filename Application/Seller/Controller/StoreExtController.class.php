<?php

namespace Seller\Controller;


use Common\Controller\QrcodeController;
use Seller\Logic\StoreLogic;
use Think\Exception;

class StoreExtController extends BaseController
{
    //普通店铺列表
    public function store_list()
    {
        $model = M('store');
        $map['is_own_shop'] = 0;
        $map['role_id'] = get_next_role_id($this->_seller['role_id']); //获取当前店铺下级角色id
        $map['first_leader'] = $this->_seller['store_id']; //当前店铺id即 下级店铺父级id
        $grade_id = I("grade_id");
        if ($grade_id > 0) $map['grade_id'] = $grade_id;
        $sc_id = I('sc_id');
        if ($sc_id > 0) $map['sc_id'] = $sc_id;
        $store_state = I("store_state");
        if ($store_state > 0) $map['store_state'] = $store_state;
        $seller_name = I('seller_name');
        if ($seller_name) $map['seller_name'] = array('like', "%$seller_name%");
        $store_name = I('store_name');
        if ($store_name) $map['store_name'] = array('like', "%$store_name%");

        $count = $model->where($map)->count();
        $Page = new \Think\Page($count, 10);

        $list = $model->where($map)->order('store_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('list', $list);

        $show = $Page->show();
        $this->assign('page', $show);
        $store_grade = M('store_grade')->getField('sg_id,sg_name');
        $this->assign('store_grade', $store_grade);
        $this->assign('store_class', M('store_class')->getField('sc_id,sc_name'));
        $this->assign('role_name', get_column('distribution_role', array('role_id' => get_next_role_id($this->_seller['role_id'])), 'name'));
        $this->assign('role_num', get_column('distribution_role', array('role_id' => $this->_seller['role_id']), 'num'));
        $this->display();
    }


    /*添加店铺*/
    public function store_add()
    {
        if (IS_POST) {
            try {
                $store_name = I('store_name');
                $user_name = I('user_name');
                $seller_name = I('seller_name');
                if (M('store')->where("store_name='$store_name'")->count() > 0) {
                    throw new Exception('店铺名称已存在');
                }
                if (M('seller')->where("seller_name='$seller_name'")->count() > 0) {
                    throw new Exception('此名称已被占用');
                }
                $user_id = M('users')->where("email='$user_name' or mobile='$user_name'")->getField('user_id');
                if ($user_id) {
                    if (M('store')->where(array('user_id' => $user_id))->count() > 0) {
                        throw new Exception('该会员已经申请开通过店铺');
                    }
                }
                $store = array('store_name' => $store_name, 'user_name' => $user_name, 'store_state' => 1,
                    'seller_name' => $seller_name, 'password' => I('password'), 'discount_id' => I('discount_id', 0),
                    'store_time' => time(), 'is_own_shop' => I('is_own_shop')
                );
                $storeLogic = new StoreLogic();
                if ($storeLogic->addStore($store, get_next_role_id($this->_seller['role_id']), $this->_seller)) {
                    if (I('is_own_shop') == 1) {
                        $this->success('店铺添加成功', U('store_own_list'));
                    } else {
                        $this->success('店铺添加成功', U('store_list'));
                    }
                    exit;
                } else {
                    throw new Exception('店铺添加失败');
                }
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
        }
        $is_own_shop = I('is_own_shop', 1);
        $this->assign('is_own_shop', $is_own_shop);

        #region 下线个数控制
        $max_num = get_column('distribution_role', array('role_id' => $this->_seller['role_id']), 'num');
        $count_store = get_count('users', array('first_leader' => $this->_seller['user_id']));
        if ($max_num > 0 && $count_store >= $max_num) { //数量为0则不限制
            $this->error('下线数量已满' . $max_num . '个');
            die;
        }
        #endregion

        #region 分销角色获取
        $role = M('distribution_role')->where(array('role_id' => get_next_role_id($this->_seller['role_id'])))->find();
        $this->assign('role', $role);
        #endregion

        #region 折扣方式获取
        $discount = M('discount')->order('discount_id asc')->select();
        $this->assign('discount', $discount);
        #endregion

        $this->display();
    }

    /*验证店铺名称，店铺登陆账号*/
    public function store_check()
    {
        $store_name = I('store_name');
        $seller_name = I('seller_name');
        $user_name = I('user_name');
        $res = array('stat' => 'ok');
        if ($store_name && M('store')->where("store_name='$store_name'")->count() > 0) {
            $res = array('stat' => 'fail', 'msg' => '店铺名称已存在');
        }

        if (!empty($user_name)) {
            if (!check_mobile($user_name)) {//!check_email($user_name) &&
                $res = array('stat' => 'fail', 'msg' => '店主账号格式为手机号');
            }
            if (M('users')->where("email='$user_name' or mobile='$user_name'")->count() > 0) {
                $res = array('stat' => 'fail', 'msg' => '会员名称已被占用');
            }
        }

        if ($seller_name && M('seller')->where("seller_name='$seller_name'")->count() > 0) {
            $res = array('stat' => 'fail', 'msg' => '此账号名称已被占用');
        }
        respose($res);
    }

    //编辑外驻店铺
    public function store_info_edit()
    {
        if (IS_POST) {
            $map = I('post.');
            $store = $map['store'];
            unset($map['store']);
            $a = M('store')->where(array('store_id' => $map['store_id']))->save($store);
            $b = M('store_apply')->where(array('user_id' => $map['user_id']))->save($map);
            if ($b || $a) {
                if ($store['store_state'] == 0) {
                    //关闭店铺，同时下架店铺所有商品
                    M('goods')->where(array('store_id' => $map['store_id']))->save(array('is_on_sale' => 0));
                }
                $this->success('编辑店铺成功', U('Store/store_list'));
                exit;
            } else {
                $this->error('编辑失败');
            }
        }
        $store_id = I('store_id');
        if ($store_id > 0) {
            $store = M('store')->where("store_id=$store_id")->find();
            $this->assign('store', $store);
            $apply = M('store_apply')->where('user_id=' . $store['user_id'])->find();
            $this->assign('apply', $apply);
        }
        $this->assign('store_grade', M('store_grade')->getField('sg_id,sg_name'));
        $this->assign('store_class', M('store_class')->getField('sc_id,sc_name'));
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $province);
        $this->display();
    }

    /*删除店铺*/
    public function store_del()
    {
        $store_id = I('del_id');
        try {
            if ($store_id > 1) {
                $map = array('store_id' => $store_id);
                $store = M('store')->where($map)->find();
                $role_name = get_column('distribution_role', array('role_id' => $store['role_id']), 'name');
                if (M('goods')->where($map)->count() > 0) {
                    throw new Exception('该店铺有发布商品，不得删除');
                } else if (get_count('store', array('first_leader' => $store_id)) > 0) {
                    throw new Exception('该' . $role_name . '存在下线,无法删除');
                } else {
                    //店铺日志记录
                    $now_store_name = get_column('store', array('store_id' => $store['first_leader']), 'store_name');
                    store_log($store_id, $store['first_leader'], $now_store_name . '删除下级' . $role_name . ':' . $store['store_name'], $log_type = 0);
                    //删除店铺信息
                    $map = array('store_id' => $store_id);
                    M('store')->where($map)->delete();
                    M('store_extend')->where($map)->delete();
                    M('seller')->where($map)->delete();
                    //清除上级信息
                    M('users')->where(array('user_id' => $store['user_id']))->save(array('first_leader' => 0, 'iskaidian' => 0, 'role_id' => 0));
                    //adminLog("删除店铺".$store['store_name']);
                    $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功'));
                }
            } else {
                throw new Exception('基础自营店，不得删除');
            }
        } catch (Exception $e) {
            $this->ajaxReturn(array('status' => 0, 'msg' => $e->getMessage()));
        }
    }

    //店铺信息
    public function store_info()
    {
        $store_id = I('store_id');
        $store = M('store')->where("store_id=" . $store_id)->find();
        $this->assign('store', $store);
        $apply = M('store_apply')->where("user_id=" . $store['user_id'])->find();
        $this->assign('apply', $apply);
        $bind_class_list = M('store_bind_class')->where("store_id=" . $store_id)->select();
        $goods_class = M('goods_category')->getField('id,name');
        for ($i = 0, $j = count($bind_class_list); $i < $j; $i++) {
            $bind_class_list[$i]['class_1_name'] = $goods_class[$bind_class_list[$i]['class_1']];
            $bind_class_list[$i]['class_2_name'] = $goods_class[$bind_class_list[$i]['class_2']];
            $bind_class_list[$i]['class_3_name'] = $goods_class[$bind_class_list[$i]['class_3']];
        }
        $this->assign('bind_class_list', $bind_class_list);
        $this->display();
    }

    //店铺审核
    public function change_column()
    {
        $store_id = I('id');
        $value = I('value');
        $res = set_field('store', $store_id, array('store_state' => $value));
        if ($res !== false)
            $this->ajaxReturn(array('status' => 1, 'msg' => '审核成功'));
        $this->ajaxReturn(array('status' => 0, 'msg' => '审核失败'));

    }

    /*
     * 商户入驻二维码
     */
    public function dis_code()
    {
        $end_time = time() + shop_share_time();
        //$ShareLink = urlencode("http://{$_SERVER[HTTP_HOST]}/index.php?m=Mobile&c=NewJoin&a=index&first_leader={$this->_seller['user_id']}&iskaidian=1&end_time={$end_time}"); //默认分享链接
        $ShareLink = "http://{$_SERVER[HTTP_HOST]}/index.php?m=Mobile&c=NewJoin&a=index&first_leader={$this->_seller['user_id']}&iskaidian=1&end_time={$end_time}"; //默认分享链接
        //$qr_url = U('Home/Index/qr_code',array('data'=>$ShareLink),'',true);
        //if(get_column('users',array('user_id'=>$this->_seller['user_id']),'is_distribut') == 1)
        $logo = M('store')->where(array('store_id' => $this->_store_id))->getField('store_logo');
        if (!empty($logo)) $logo = 'http://' . $_SERVER['HTTP_HOST'] . $logo;
        $qrcode = new QrcodeController();
        $qr_url = $qrcode->create_qrcode($ShareLink, 4, 'L', $logo);
        $this->assign('qr_url', $qr_url);
        $this->assign('dis_type',true);
        $this->display('qr_code');
    }

    public function share_bg(){
        $qrcode = new QrcodeController();
        $qrcode->qrcode_bg(urldecode(I('code')),'',115,125);
    }

    /*
     *推广二维码
     */
    public function qr_code()
    {
        //$ShareLink = urlencode("http://{$_SERVER[HTTP_HOST]}/index.php?m=Mobile&c=Index&a=index&first_leader={}"); //默认分享链接
        //$qr_url = U('Home/Index/qr_code',array('data'=>$ShareLink),'',true);
        //if(get_column('users',array('user_id'=>$this->_seller['user_id']),'is_distribut') == 1)
        //$ShareLink = urlencode(U('Mobile/Store/index',array('store_id'=>$this->_store_id,'first_leader'=>$this->_seller['user_id']),'',true));
        $store = M('store')->where(array('store_id' => $this->_store_id))->find();//是否时自营店铺  1是  0否
        if ($store['role_id'] == 5 && $store['is_own_shop']==1){ //自营
            $ShareLink = U('Mobile/Store/index', array('store_id' => $this->_store_id, ), '', true);// 'first_leader' => $this->_seller['user_id']
        }else if ($store['role_id'] == 5 && $store['is_own_shop']==0){//入驻店铺（实体店）
            $ShareLink = U('Mobile/Store/index', array('store_id' => $this->_store_id, 'first_leader' => $this->_seller['user_id']), '', true);
        }else if ($store['role_id'] == 4){ //分销商
            $ShareLink = U('Mobile/index/index', array('first_leader' => $this->_seller['user_id']), '', true);
        }else{
            $ShareLink = U('Mobile/index/index', [], '', true);
        }

        $logo = M('store')->where(array('store_id' => $this->_store_id))->getField('store_logo');
        if (!empty($logo)) $logo = 'http://' . $_SERVER['HTTP_HOST'] . $logo;
        $qrcode = new QrcodeController();
        $qr_url = $qrcode->create_qrcode($ShareLink, 4, 'L', $logo);
        $this->assign('qr_url', $qr_url);

        $this->display();
    }

    //商户入住日志
    public function log()
    {
        //商户入住日志
        $Log = M('store_log');
        $p = I('p', 1);
        $map = array('sl.first_leader' => $this->_store_id);
        $alias = 'sl';
        $logs = $Log->alias($alias)->where($map)
            ->join(array('LEFT JOIN __STORE__ as s ON s.store_id =sl.store_id'))
            ->order('log_time DESC')->page($p . ',20')->select();
        $this->assign('list', $logs);
        $count = $Log->alias($alias)->where($map)->count();
        $Page = new \Think\Page($count, 20);
        $show = $Page->show();
        $this->assign('page', $show);
        $this->display();
    }

    /**
     * 分销树状关系
     */
    public function tree()
    { //实体店分销 判断字段 first_leader   自营店分销 判断 first_leader
        //我的上级
        $map = array();
        $map['user_id'] = $this->_seller['user_id'];
        $first_leader = get_column('users', $map, 'first_leader');
        if ($first_leader == 0) {
            $first_leader = array('nickname' => '平台');
        } else {
            $map = array();
            $map['user_id'] = $first_leader;
            $first_leader = M('users')->where($map)->find();
        }

        //我的下级
        $map = array();
        //$map['is_distribut'] = 1;
        $map['first_leader'] = $this->_seller['user_id'];

        if ($_POST['user_id'])
            $map['user_id'] = $_POST['user_id'];

        $list = M('users')->where($map)->select();

        $this->assign('list', $list);
        $this->assign('first_leader', $first_leader);
        $this->display();
    }

    /**
     * 获取某个人下级元素
     */
    public function ajax_lower()
    {
        $map = array();
        //$map['is_distribut'] = 1;
        //$map['iskaidian'] = 1;
        $map['first_leader'] = I('id');
        $list = M('users')->where($map)->select();
        $this->assign('list', $list);
        $this->display();
    }
}