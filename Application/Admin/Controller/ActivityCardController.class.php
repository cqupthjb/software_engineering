<?php
/**
 * 活动票管理
 * Created by PhpStorm.
 * User: Mr-X
 * Date: 2017/9/13 0013
 * Time: 10:13
 */

namespace Admin\Controller;


use Think\AjaxPage;
use Think\Exception;
use Think\Page;

class ActivityCardController extends BaseController
{
    #region 票务管理
    //活动票列表
    public function card_index(){
        try{
            $status = I('status',1);
            $map = array();
            $map['c.status'] = $status ;
//            $model = M('activity_card');
//            $count = $model->where($map)->count();
//            $Page  = new AjaxPage($count,10);
//            $show = $Page->show();
//            $this->assign('page',$show);// 赋值分页输出
//            $order_str = 'create_time desc , card_id desc';
//            $card_list= $model->where($map)->order($order_str)->limit($Page->firstRow.','.$Page->listRows)->select();
            $card_list = $this->get_card_list($map,true);
            $this->assign('card_list',$card_list);
            $this->assign('status',$status);// 赋值分页输出
            $this->assign('activity_type',$this->get_config_column());//活动类型
            $this->display();
        }catch(Exception $e){
            $this->error($e->getMessage());
        }
    }

    //添加 | 修改活动票
    public function add_card(){
        $card_id = I('card_id',0);
        if (!empty($card_id)){
            $card = M('activity_card')->where(array('card_id'=>$card_id))->find();
            if (empty($card))
                $this->error('活动票不存在');
            $this->assign('row',$card);
        }
        $this->initEditor();
        //卡券等级
        $card_level = M('activity_card_level')->order('level_id asc')->select();
        $this->assign('card_level',$card_level);
        $this->assign('activity_type',$this->get_config_column());//活动类型
        $this->display('add_card');
    }
    /**
     * 初始化编辑器链接
     */
    private function initEditor()
    {
        $this->assign("URL_upload", U('Admin/Ueditor/imageUp',array('savepath'=>'activity_card')));
        $this->assign("URL_fileUp", U('Admin/Ueditor/fileUp',array('savepath'=>'activity_card')));
        $this->assign("URL_scrawlUp", U('Admin/Ueditor/scrawlUp',array('savepath'=>'activity_card')));
        $this->assign("URL_getRemoteImage", U('Admin/Ueditor/getRemoteImage',array('savepath'=>'activity_card')));
        $this->assign("URL_imageManager", U('Admin/Ueditor/imageManager',array('savepath'=>'activity_card')));
        $this->assign("URL_imageUp", U('Admin/Ueditor/imageUp',array('savepath'=>'activity_card')));
        $this->assign("URL_getMovie", U('Admin/Ueditor/getMovie',array('savepath'=>'activity_card')));
        $this->assign("URL_Home", "");
    }
    //添加 | 修改
    public function save_card(){
        try{
            $data = $this->_check_card();
            if (empty($data['card_id'])){
                $data['create_time'] = time();
                $res = M('activity_card')->add($data);
            }else{
                $res = M('activity_card')->save($data);
            }
            if ($res === false)
                throw new Exception('操作失败');
            $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
        }catch(Exception $e){
            //$this->error($e->getMessage());
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
        }
    }

    //检测数据
    protected function _check_card(){
        $data = I('post.');
        if (empty($data))
            throw new Exception('请填写活动票信息');
        if (empty($data['level_id']))
            throw new Exception('请选择活动票等级');
        if (empty($data['name']))
            throw new Exception('请填写活动票名称');
        if (empty($data['cover']))
            throw new Exception('请上活动票传图片');
        if (empty($data['start_time']) || empty($data['end_time']))
            throw new Exception('请选择领取活动票时间');
        if ($data['end_time'] < $data['start_time'])
            throw new Exception('结束时间请大于开始时间');
        //if ($data['start_time'] < time() || $data['end_time'] < time())
        //    throw new Exception('领取时间请大于当前时间');
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        return $data;
    }
    //删除活动票
    public function remove_card(){
        try{
            $card_id = I('card_id',I('id',0));
            if (empty($card_id))
                throw new Exception('缺少活动票参数');

            $map = array('card_id'=>$card_id);
            $card = M('activity_card')->where($map)->find();
            if (empty($card))
                throw new Exception('该活动票不存在');
            if ($card['status'] != 1){ //回收站数据
                $count = get_count('activity_card_log',$map);
                if ($count > 0)
                    throw new Exception('存在用户领取信息');
                $res = M('activity_card')->where($map)->delete();
            }else{ //正常列表数据
                $res = M('activity_card')->where($map)->save(array('status'=>0));
            }
            if ($res === false)
                throw new Exception('操作失败');
            $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
        }
    }
    #endregion

    #region 票务等级管理
    //等级首页
    public function level_index(){
        try{
            $map = array();
            $model = M('activity_card_level');
            $count = $model->where($map)->count();
            $Page  = new AjaxPage($count,20);
            $show = $Page->show();
            $order_str = 'level_id desc';
            $level_list= $model->where($map)->order($order_str)->limit($Page->firstRow.','.$Page->listRows)->select();
            $this->assign('list',$level_list);
            $this->assign('page',$show);// 赋值分页输出
            $this->assign('activity_type',$this->get_config_column());//活动类型
            $this->display();
        }catch(Exception $e){
            $this->error($e->getMessage());
        }
    }

    //活动类型
    protected function get_config_column($inc_type = 'activity',$name = 'activity_type'){
        return get_column('config',array('inc_type'=>$inc_type,'name'=>$name),'value');
    }

    //更改配置
    public function handle()
    {
        $param = I('post.');
        $inc_type = $param['inc_type'];
        unset($param['inc_type']);
        tpCache($inc_type,$param);
        $this->ajaxReturn(array('msg'=>'操作成功'));
    }

    //添加等级
    public function add_level(){
        $level_id = I('level_id',0);
        if (!empty($level_id)){
            $level = M('activity_card_level')->where(array('level_id'=>$level_id))->find();
            if (empty($level))
                $this->error('等级不存在');
            $this->assign('row',$level);
        }
        $activity_type = $this->get_config_column();//活动类型
        if (($activity_type == 1)){ //类型为1：指定商品
            //1.>>获取已参加的商品id
            $ids = M('activity_card_level')->where(array('goods_id'=>array('neq','')))->getField('goods_id',true);
            //2.>>获取指定商品列表
            $map                = array();
            if (!empty($ids))$map['goods_id']    = array('not in',$ids);
            $map['is_activity'] = 1;
            $goods_list = M('goods')->where($map)->field('goods_id,goods_name')->select();
            $this->assign('goods_list',$goods_list);
        }
        $this->assign('activity_type',$activity_type);
        $this->display('add_level');
    }
    //修改等级
    public function save_level(){
        try{
            $data = $this->_check_level();
            if (empty($data['level_id'])){
                $res = M('activity_card_level')->add($data);
            }else{
                $res = M('activity_card_level')->save($data);
            }
            if ($res === false)
                throw new Exception('操作失败');
            $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
        }
    }
    //检测数据
    public function _check_level(){
        $data = I('post.');
        if (empty($data))
            throw new Exception('请填写活动票等级信息');
        if (empty($data['level_name']))
            throw new Exception('请填写等级名称');
        if ($data['activity_type'] == 0)
            if (empty($data['need_price']))
                throw new Exception('请填写满足条件金额');
        if ($data['goods_id'] == 0)
            unset($data['goods_id']);
        unset($data['activity_type']);
        return $data;
    }
    //删除等级
    public function remove_level(){
        try{
            $level_id = I('level_id',0);
            if (empty($level_id))
                throw new Exception('缺少等级参数');
            $count = get_count('activity_card',array('level_id'=>$level_id));
            if ($count > 0)
                throw new Exception('改等级存在活动票信息，可切换活动票等级再删除');
            $res = M('activity_card_level')->where(array('level_id'=>$level_id))->delete();
            if ($res === false)
                throw new Exception('删除失败');
            $this->ajaxReturn(array('status'=>1,'msg'=>'删除成功'));
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
        }
    }
    #endregion

    //region 领取票务管理
    //票务首页
    public function index(){
        //>>1.获取可领取的活动票列表
        $card_list = $this->get_card_list();
        $this->assign('card_list',$card_list);
        $this->assign('activity_type',$this->get_config_column());//活动类型
        $this->display();
    }

    //用户领取票务记录
    public function log_index(){
//        $map = array();
//        $model = M('activity_card_log');
//        $alias = 'log';
//        $count = $model->where($map)->count();
//        $Page  = new AjaxPage($count,10);
//        $show = $Page->show();
//        $order_str = 'log.create_time desc , log.log_id desc';
//        $join = array('LEFT JOIN __USERS__ as u ON u.user_id = log.user_id','LEFT JOIN __ACTIVITY_CARD__ as c ON c.card_id = log.card_id');
//        $field = 'log.log_id,log.create_time,u.user_id,u.nickname,u.head_pic,u.mobile,c.card_id,c.name as card_name';
//        $log_list= $model->alias($alias)->where($map)->field($field)->order($order_str)->join($join)->limit($Page->firstRow.','.$Page->listRows)->select();
//        $this->assign('list',$log_list);
//        $this->assign('page',$show);// 赋值分页输出
        $begin = date('Y/m/d',(time()-30*60*60*24));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $this->assign('timegap',$begin.'-'.$end);
        $this->display();
    }
    //ajax分页
    public function ajax_log_index(){

        $timegap = I('create_time');
        if($timegap){
            $gap = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end = strtotime($gap[1])+60*60*24;
        }
        // 搜索条件 STORE_ID
        $condition = array();
        if($begin && $end){
            $condition['log.create_time'] = array('between',"$begin,$end");
        }

        $model = M('activity_card_log');
        $alias = 'log';
        $order_str = 'log.create_time desc , log.log_id desc';
        $join = array('LEFT JOIN __USERS__ as u ON u.user_id = log.user_id','LEFT JOIN __ACTIVITY_CARD__ as c ON c.card_id = log.card_id');
        $field = 'log.log_id,log.create_time,u.user_id,u.nickname,u.head_pic,u.mobile,c.card_id,c.name as card_name';
        $count = $model->alias($alias)->where($condition)->count();
        $Page  = new AjaxPage($count,10);
        $show = $Page->show();
        $log_list= $model->alias($alias)->where($condition)->field($field)->order($order_str)->join($join)->limit($Page->firstRow.','.$Page->listRows)->select();

        $this->assign('list',$log_list);
        $this->assign('page',$show);// 赋值分页输出
        $this->display('_log_index');
    }
    //记录导出//Excel导出
    public function export_log()
    {
        $timegap = I('create_time');
        if($timegap){
            $gap = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end = strtotime($gap[1]);
        }
        // 搜索条件 STORE_ID
        $condition = array();
        if($begin && $end){
            $condition['log.create_time'] = array('between',"$begin,$end");
        }

        $model = M('activity_card_log');
        $alias = 'log';
        $order_str = 'log.create_time desc , log.log_id desc';
        $join = array('LEFT JOIN __USERS__ as u ON u.user_id = log.user_id','LEFT JOIN __ACTIVITY_CARD__ as c ON c.card_id = log.card_id');
        $field = 'log.log_id,log.create_time,u.user_id,u.nickname,u.head_pic,u.mobile,c.card_id,c.name as card_name';

        $log_list= $model->alias($alias)->where($condition)->field($field)->order($order_str)->join($join)->select();

        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">用户编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">用户昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">领取的票</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">领取时间</td>';
        $strTable .= '</tr>';

        foreach($log_list as $k=>$val){
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['log_id'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['user_id'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['nickname'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['mobile'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['card_name'].'</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.date('Y/m/d H:i:s',$val['create_time']).'</td>';
            $strTable .= '</tr>';
        }
        $strTable .='</table>';
        unset($orderList);
        downloadExcel($strTable,'activity_card_log');
        exit();
    }

    //用户领票
    public function receive_card(){
        try{
            //验证数据
            $data = $this->_check_receive();
            $data['create_time'] = time();
            $res = M('activity_card_log')->add($data);
            if ($res === false)
                throw new Exception('领取活动票失败');
            M('activity_card')->where(array('card_id'=>$data['card_id']))->setDec('store_count',1);
            //发送微信消息
            $user = M('users')->where(array('user_id'=>$data['user_id']))->field('user_id,openid,nickname')->find();
            if (!empty($user['openid'])){
                $card_name = M('activity_card')->where(array('card_id'=>$data['card_id']))->getField('name');
                $wx_content = '恭喜用户：'.$user['nickname'].','.date('Y-m-d H:i:s',$data['create_time']).'成功领取：'.$card_name.' 活动票';
                send_wx_msg($user['openid'],$wx_content);
            }
            if (!empty($user['reg_id'])){
                $card_name = M('activity_card')->where(array('card_id'=>$data['card_id']))->getField('name');
                $title = '活动票消息';
                $content = '恭喜用户：'.$user['nickname'].','.date('Y-m-d H:i:s',$data['create_time']).'成功领取：'.$card_name.' 活动票';
                send_jpush_msg($user['reg_id'] ,$title, $content,'activity');
            }
            $this->ajaxReturn(array('status'=>1,'msg'=>'领取活动票成功','url'=>U('log_index')));
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
        }
    }

    //验证领票信息
    protected function _check_receive(){
        $data = I('post.');
        if (empty($data))
            throw new Exception('没有领取活动票信息,请重试');
        if (empty($data['user_id']))
            throw new Exception('会员信息缺省');
        if (empty($data['card_id']))
            throw new Exception('活动票信息缺省');
        //用户信息
        if (get_count('users',array('user_id'=>$data['user_id'])) <=0)
            throw new Exception('该会员不存在');
        $map = array();
        $map['card_id']     = array('eq',$data['card_id']);  //有库存的
        $map['store_count'] = array('gt',0);  //有库存的
        $map['status']      = array('eq',1);  //状态正常的
        $map['end_time']    = array('egt',strtotime(date('Y/m/d',time()))); //时间未结束的
        $card = M('activity_card')->where($map)->find();
        if (empty($card) || $card['store_count'] <=0)
            throw new Exception('该活动票不存在或已领完');
        //领取信息查询
        $this->_check_already_receive($data['user_id']);

        //统计消费额
        $map = array('is_own_shop'=>1,'store_state'=>1);
        $store_ids = M('store')->where($map)->field('store_id')->select();
        $store_ids = arr2_2_str($store_ids);//二维数组转字符串，以逗号分割
        if (empty($store_ids))
            throw new Exception('未获取到自营店信息');

        $total_amount = $this->get_total_amount($data['user_id'],$store_ids,$card['start_time'],$card['end_time']);
        if ($total_amount < $card['need_price'])
            throw new Exception('该会员消费为:'.$total_amount.',不能领取该活动票');

        return $data;
    }

    //检查是否已领取此次活动票
    protected function _check_already_receive($user_id){
        //>>2.获取可领取的活动票列表
        //$card_list = $this->get_card_list();
        $card_ids = $this->get_card_ids();
        if (empty($card_ids))
            throw new Exception('该活动票不存在或已领完');
        //>>2.1.判断用户是否领取过券
        //$card_ids = array_column($card_list,'card_id');
        //$card_ids = arr2str($card_ids); //将一维数组以某个字符分割成字符串  似乎以数组组装条件时可以是一维数组
        $map = array();
        $map['user_id'] = $user_id;
        $map['card_id'] = array('in',$card_ids);
        $count = get_count('activity_card_log',$map);
        if ($count>0)
            throw new Exception('该会员已领取过此次活动票');
        return true;
    }

    //扫码领票
    public function scan(){
        try{
            $user_id = $this->get_user_id();
            if (empty($user_id))
                throw new Exception('缺少会员参数');
            //>>0.查询用户信息
            $user = M('users')->where(array('user_id'=>$user_id))->field('user_id,nickname,head_pic,mobile,first_leader')->find();
            if (empty($user)){
                throw new Exception('该会员不存在');
            }
            $this->assign('user',$user);//用户信息
            //region 查找用户消费信息  -  自营店消费

            //>>1.查询自营店铺id
            $map = array('is_own_shop'=>1,'store_state'=>1);
            $store_ids = M('store')->where($map)->field('store_id')->select();
            if (empty($store_ids))
                throw new Exception('未获取到自营店信息');
            $store_ids = arr2_2_str($store_ids);//二维数组转字符串，以逗号分割

            //>>2.获取可领取的活动票列表
            $card_list = $this->get_card_list();
            if (empty($card_list))
                throw new Exception('没有有效活动票');

            //>>2.1.判断用户是否领取过券
            $card_ids = array_column($card_list,'card_id');
            //$card_ids = arr2str($card_ids); //将一维数组以某个字符分割成字符串  似乎 以 数组 组装条件 时 可以是 一维数组 直接使用
            $map = array();
            $map['user_id'] = $user['user_id'];
            $map['card_id'] = array('in',$card_ids);
            $count = get_count('activity_card_log',$map);
            if ($count>0)
                throw new Exception('该会员已领取过此次活动票');

            //>>3.获取可领取的活动票列表
            $activity_type = $this->get_config_column();
            if ($activity_type == 0){ //累计消费
                //消费总额
                $total_amount = $this->get_total_amount($user_id,$store_ids,$card_list[0]['start_time'],$card_list[0]['end_time']);
                foreach ($card_list as $key => $vo){
                    //>>3.1.统计用户在自营店中消费的金额
//                            if (isset($map['ctime'])) unset($map['ctime']);
//                            $map['order_status']= array('in',array(2,4));//订单状态为待评价||已评价
//                            $map['add_time'] = array('between',"{$vo['start_time']},{$vo['end_time']}");//消费时间在票务时间区间内
//                            //>>3.1.1.商品购物消费
//                            $goods_price = M('order')->where($map)->sum('total_amount');//order_amount
//                            //>>3.1.2.扫码消费
//                            unset($map['order_status']);
//                            unset($map['add_time']);
//                            $map['ctime'] = array('between',"{$vo['start_time']},{$vo['end_time']}");//消费时间在票务时间区间内;
//                            $scan_price  = M('scan_order')->where($map)->sum('account');//need_pay
                    if ($total_amount < $vo['need_price']){
                        //过滤不符合条件的活动票
                        unset($card_list[$key]);
                    }
                }
                $this->assign('activity_type',$activity_type);//活动类型
                $this->assign('total_amount',$total_amount);//区间总消费
                $this->assign('card_list',$card_list);//活动票列表
                $content = $this->fetch('_scan_info');
                $this->ajaxReturn(array('status'=>1,'msg'=>'获取信息成功','content'=>$content));
            }else{ //指定商品
                //>>获取已参加活动的商品id
                $ids = M('activity_card_level')->where(array('goods_id'=>array('neq','')))->getField('goods_id',true);
                $ids = $this->get_buy_goods_ids($user_id,$ids,$card_list[0]['start_time'],$card_list[0]['end_time']);//获取购买了的商品ids
                if (empty($ids))
                    throw new Exception('该用户未购买指定商品');

                //过滤未购买的商品卡券
                $list = array();
                foreach ($card_list as $key => $vo){
                    foreach ($ids as $k => $v){
                        if ($vo['goods_id'] == $v){
                            $list = $vo;
                        }
                    }
                }
                if (empty($list))
                    throw new Exception('未有符合条件的活动票');
                //查询订单
                $order_list = $this->get_order_list($user_id,$ids,$card_list[0]['start_time'],$card_list[0]['end_time']);
                $this->assign('order_list',$order_list);//订单信息
                $this->assign('activity_type',$activity_type);//活动类型
                $this->assign('card_list',$card_list);//活动票列表
                $content = $this->fetch('_scan_info');
                $this->ajaxReturn(array('status'=>1,'msg'=>'获取信息成功','content'=>$content));
            }
            //endregion
        }catch(Exception $e){
            $this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
        }
    }

    //扫码领票页面
    public function scan_user(){
        $this->assign('activity_type',$this->get_config_column());//活动类型
        $this->display();
    }

    //获取购买了的商品ids  新加的
    protected function get_buy_goods_ids($user_id=0,$goods_ids=[],$start_time=0,$end_time=0){
        //>>2.查询对应的订单消费
        $alias = 'o';
        $map = array();
        $map['o.user_id']     = $user_id;
        $map['o.pay_status']  = array('eq',1);//支付状态为1
        //改：//一次活动的活动票需要活动时间一致  因为消费金额是只计算一个时间区间的
        $map['o.order_status']= array('in',array(0,1,2,4));//订单状态为待评价||已评价  || 待确认&已支付  || 已确认
        $map['o.add_time'] = array('between',"{$start_time},{$end_time}");//消费时间在票务时间区间内
        $map['og.goods_id']= array('in',$goods_ids);//在此商品范围内
        //$field = 'o.order_id,o.add_time,og.goods_num,og.goods_price';
        $join = array('LEFT JOIN __ORDER_GOODS__ as og ON og.order_id = o.order_id');
        //>>2.1.购物消费的商品id
        return M('order')->alias($alias)->join($join)->where($map)->getField('goods_id',true);
    }

    protected function get_order_list($user_id=0,$goods_ids=[],$start_time=0,$end_time=0){
        //>>2.查询对应的订单消费
        $alias = 'o';
        $map = array();
        $map['o.user_id']     = $user_id;
        $map['o.pay_status']  = array('eq',1);//支付状态为1
        //改：//一次活动的活动票需要活动时间一致  因为消费金额是只计算一个时间区间的
        $map['o.order_status']= array('in',array(0,1,2,4));//订单状态为待评价||已评价  || 待确认&已支付  || 已确认
        $map['o.add_time'] = array('between',"{$start_time},{$end_time}");//消费时间在票务时间区间内
        $map['og.goods_id']= array('in',$goods_ids);//在此商品范围内
        $field = 'o.order_id,o.add_time,o.user_id';
        $join = array('LEFT JOIN __ORDER_GOODS__ as og ON og.order_id = o.order_id');
        //>>2.1.购物消费的订单
        $order_list = M('order')->alias($alias)->join($join)->field($field)->where($map)->select();
        $order_ids = array_column($order_list,'order_id');
        //>>2.2.获取消费订单商品
        $order_goods = M('order_goods')->where(array('order_id'=>array('in',$order_ids)))->select();
        foreach ($order_list as $key => $vo){
            foreach ($order_goods as $k => $v){
                if ($vo['order_id'] == $v['order_id']){
                    $order_list[$key]['goods'][] = $v;
                }
            }
        }
        return $order_list;
    }

    //获取消费金额  暂时不用 原：get_total_amount 指定店铺 自营店内总消费
    protected function get_total_amount_bak($user_id=0,$store_ids=[],$start_time=0,$end_time=0){
        $map = array();
        $map['user_id']     = $user_id;
        $map['store_id']    = array('in',$store_ids);//自营店
        $map['pay_status']  = array('eq',1);//支付状态为1

        //改：//一次活动的活动票需要活动时间一致  因为消费金额是只计算一个时间区间的
        //>>3.1.统计用户在自营店中消费的金额
        if (isset($map['ctime'])) unset($map['ctime']);
        $map['order_status']= array('in',array(0,1,2,4));//订单状态为待评价||已评价
        $map['add_time'] = array('between',"{$start_time},{$end_time}");//消费时间在票务时间区间内
        //>>3.1.1.商品购物消费
        $goods_price = M('order')->where($map)->sum('total_amount');//order_amount
        //>>3.1.2.扫码消费
        unset($map['order_status']);
        unset($map['add_time']);
        $map['ctime'] = array('between',"{$start_time},{$end_time}");//消费时间在票务时间区间内;
        $scan_price  = M('scan_order')->where($map)->sum('account');//need_pay

        return $goods_price + $scan_price;
    }
    //获取消费金额 根据指定活动商品消费金额
    protected function get_total_amount($user_id=0,$store_ids=[],$start_time=0,$end_time=0){
        //>>1.查询指定的活动商品
        $map                = array();
        $map['is_activity'] = 1;//参与活动的
        //$map['store_id']    = array('in',$store_ids);//在指定店铺的  自营店
        $goods_ids = M('goods')->where($map)->field('goods_id')->select();
        $goods_ids = arr2_2_str($goods_ids);//二维数组转字符串
        //>>2.查询对应的订单消费
        $alias = 'o';
        $map = array();
        $map['o.user_id']     = $user_id;
        $map['o.pay_status']  = array('eq',1);//支付状态为1
        //改：//一次活动的活动票需要活动时间一致  因为消费金额是只计算一个时间区间的
        $map['o.order_status']= array('in',array(0,1,2,4));//订单状态为待评价||已评价  || 待确认&已支付  || 已确认
        $map['o.add_time'] = array('between',"{$start_time},{$end_time}");//消费时间在票务时间区间内
        $map['og.goods_id']= array('in',$goods_ids);//在此商品范围内
        //$field = 'o.order_id,o.add_time,og.goods_num,og.goods_price';
        $join = array('LEFT JOIN __ORDER_GOODS__ as og ON og.order_id = o.order_id');
        //>>2.1.商品购物消费
        $goods_price = M('order')->alias($alias)->join($join)->where($map)->sum('og.goods_price * og.goods_num');//order_amount
        return $goods_price ? $goods_price : 0;
    }

    //获取用户id
    protected function get_user_id(){
        $info = I('info','');
        //判断是否是数字
        if (is_numeric($info)){
            return $info;
        }
        //判断是url的情况 正则分割数据获取参数
        if (stripos($info, 'http') !== false){
            preg_match_all("/(\w+=\w+)(#\w+)?/i",$info,$match);
            array_filter($match);
            $user_id = '';
            if (!empty($match)) {
                foreach ($match as $key => $vo){
                    foreach ($vo as $k => $v){
                        if (stripos($v, 'myself') !== false){
                            parse_str($v,$arr);
                            $user_id = $arr['myself'];
                            break 2;
                        }
                    }
                }
            }
            if (is_string($user_id) && !is_numeric($user_id)){
                $user_id = base64_decode($user_id);
            }
            return $user_id;
        }

        throw new Exception('缺少会员参数');
    }

    //获取有效的活动票 或者活动票首页分页数据 type 代表是否是卡券首页
    protected function get_card_list($map = [],$is_page = false){
        if (empty($map)){
            $map = array();
            $map['c.store_count'] = array('gt',0);  //有库存的
            $map['c.status']      = array('eq',1);  //状态正常的
            $map['c.end_time']    = array('egt',strtotime(date('Y/m/d',time()))); //时间未结束的
        }
        $alias = 'c';
        $join = array('LEFT JOIN __ACTIVITY_CARD_LEVEL__ as level ON level.level_id = c.level_id','LEFT JOIN __ACTIVITY_CARD_LOG__ as log ON log.card_id = c.card_id');
        $field = 'c.* ,level.level_name,level.need_price,level.goods_id,count(log.log_id) as user_count';  //注意使用了count后需要使用group分组查询

        //分页信息
        if ($is_page == true){
            $count = M('activity_card')->alias($alias)
                ->join($join)->where($map)->field($field)
                ->group('c.card_id')->count();
            $Page  = new Page($count,10);
            $show = $Page->show();
            $this->assign('page',$show);// 赋值分页输出
        }

        //联合查询
        M('activity_card')->alias($alias)
            ->join($join)->where($map)->field($field)
            ->group('c.card_id')->order('level.need_price desc');

        //利用thinkphp 联动特性
        if ($is_page == true){ //分页  首页需要
            M('activity_card')->limit($Page->firstRow.','.$Page->listRows);
        }

        $card_list = M('activity_card')->select();
        return $card_list;
    }

    //获取有效的活动票的ids
    protected function get_card_ids(){
        $map = array();
        $map['store_count'] = array('gt',0);  //有库存的
        $map['status']      = array('eq',1);  //状态正常的
        $map['end_time']    = array('egt',strtotime(date('Y/m/d',time()))); //时间未结束的
        $field = 'card_id';

        $ids = M('activity_card')->where($map)->field($field)->select();
        if (!empty($ids)){
            $ids = arr2_2_str($ids);
        }
        return $ids;
    }
    //endregion

    //region 辅助操作
    //删除
    public function remove(){
        $card_id = I('id');
        $map = array('card_id'=>$card_id);
        //$status = M('card')->where($map)->getField('status');
        //if ($status == 1) //为1 假删除
        $res = M('activity_card')->where($map)->setField('status',0);
        //else  //为0 真删除
        //    $res = M('card')->where($map)->delete();
        if ($res === false)
            $this->ajaxReturn(array('status'=>0,'msg'=>'操作失败'));
        else
            $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
    }

    //更改字段
    public function change_column(){
        $card_id = I('id');
        $map = array('card_id'=>$card_id);
        $value = I('value');
        //$status = M('card')->where($map)->getField('status');
        //if($value==0 && $status==0) //为0 真删除
        //    $res = M('card')->where($map)->delete();
        // else
        $res = M('activity_card')->where($map)->setField('status',$value);
        if ($res === false)
            $this->ajaxReturn(array('status'=>0,'msg'=>'操作失败'));
        else
            $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
    }
    //endregion
}