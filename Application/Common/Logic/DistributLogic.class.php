<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: IT宇宙人
 * Date: 2015-09-09
 *
 * TPshop 公共逻辑类  将放到Application\Common\Logic\   由于很多模块公用 将不在放到某个单独模下面
 */

namespace Common\Logic;

use Think\Exception;
use Think\Model;

//use Think\Page;

/**
 * 分销逻辑层
 * Class CatsLogic
 * @package Home\Logic
 */
class DistributLogic //extends Model
{
    public function hello()
    {
        echo 'function hello(){';
    }

    /**
     * 自营分销记录
     * @param $order
     * @param int $order_type  订单类型
     * @return bool
     */
    public function rebate_log($order,$order_type=0)
    {
        //自营分销信息
        $store_distribut = M('store_distribut')->where("store_id = {$order['store_id']}")->find();
        //如果自营店未设置分销比例
        if (empty($store_distribut) || $store_distribut['switch'] == 0){
            //读取总后台配置的三级分销比例
            $store_distribut['pattern'] = get_column('config',array('name'=>'pattem'),'value');
            $store_distribut['first_rate'] = get_column('config',array('name'=>'first_rate'),'value');
            $store_distribut['second_rate'] = get_column('config',array('name'=>'second_rate'),'value');
            $store_distribut['third_rate'] = get_column('config',array('name'=>'third_rate'),'value');

            //if (!$store_distribut['first_rate'] || !$store_distribut['second_rate'] || !$store_distribut['third_rate'])
            //    return false;
        }

        //1订单用户信息
        $user = M('users')->where(array('user_id' => $order['user_id']))->find();

        //4.>>返利给用户自己
        $order_goods = M('order_goods')->where(array('order_id'=>$order['order_id']))->select();
        if (!empty($order_goods)){
            //计算返利佣金
            $user_money = 0;//初始化用户的佣金
            foreach ($order_goods as $key => $vo){
                $goods = M('goods')->where(array('goods_id'=>$vo['goods_id']))->find();
                if (!empty($goods) && $goods['rebate_id'] > 0){ //如果商品存在
                    //查询返利的比例
                    $rebate_value = get_column('rebate',array('id'=>$goods['rebate_id']),'value');
                    if ($rebate_value > 0){//比例大于0
                        //累计用户佣金
                        $user_money += ($rebate_value / 100) * $vo['goods_price'] * $vo['goods_num'];
                    }
                }
            }
            if ($user_money > 0.0001){
                $this->_store_rebate_log($user,$order,$user_money,0,'您');
            }
        }

        //计算佣金
        $pattern = $store_distribut['pattern']; // 分销模式
        $first_rate = $store_distribut['first_rate']; // 一级比例
        $second_rate = $store_distribut['second_rate']; // 二级比例
        $third_rate = $store_distribut['third_rate']; // 三级比例

        //按照商品分成 每件商品的佣金拿出来
        if ($pattern == 0) {
            // 获取所有商品分类
            //$cat_list =  M('goods_category')->getField('id,parent_id,commission_rate');
            $order_goods = M('order_goods')->where("order_id = {$order['order_id']}")->select(); // 订单所有商品
            $commission = 0;
            foreach ($order_goods as $k => $v) {
                $tmp_commission = 0;
                $goods = M('goods')->where("goods_id = {$v['goods_id']}")->find(); // 单个商品的佣金
                $tmp_commission = $goods['distribut']; // 多商家版 已改名 distribut  为了 不跟平台抽成字段冲突
                $tmp_commission = $tmp_commission * $v['goods_num']; // 单个商品的分佣乘以购买数量
                $commission += $tmp_commission; // 所有商品的累积佣金
            }
        } else {
            $order_rate = $store_distribut['order_rate']; // 订单分成比例
            $commission = $order['goods_price'] * ($order_rate / 100); // 订单的商品总额 乘以 订单分成比例
        }

        // 如果这笔订单没有分销金额
        if ($commission == 0)
            return false;

        $first_money = $commission * ($first_rate / 100); // 一级赚到的钱
        $second_money = $commission * ($second_rate / 100); // 二级赚到的钱
        $thirdmoney = $commission * ($third_rate / 100); // 三级赚到的钱

        //2>>获取上级信息
        $first_leader = M('users')->where(array('user_id'=>$user['first_leader']))->find();
        //3>>根据用户上线 角色 进入不同的返利模式
        //3.1>>用户上线为普通用户  如果不存在实体店分销体系 || 上线在实体店分销体系的角色为普通用户
        if ($first_leader['role_id'] == 0){
           try{
               $this->mode1($user,$order,$first_money,$second_money,$thirdmoney,$order_type);//模式一
           }catch(Exception $e){
               return false;
           }
        }

        //3.2>>用户上线为 实体店
        if ($first_leader['role_id'] == 5){
            try{
                //$this->mode3($user,$order,$first_money,$second_money,$thirdmoney);//模式三

                //凑满三级   模式一
                //例如：用户A 买东西   返  实体店->分销商->县级
                //  ||  用户B 买东西   返  用户A->实体店->分销商
                //  ||  用户C 买东西   返  用户B->用户C->实体店
                //  ||  用户D 买东西   返  用户C->用户B->用户A      正常的三级分销
                $this->mode1($user,$order,$first_money,$second_money,$thirdmoney,$order_type);//模式一
            }catch(Exception $e){
                return false;
            }
        }

        //3.3>>用户上线为 分销商
        if ($first_leader['role_id'] == 4){
            try{
                //$this->mode5($user,$order,$first_money,$second_money,$thirdmoney);//模式五
                $this->mode1($user,$order,$first_money,$second_money,$thirdmoney,$order_type);//模式一
            }catch(Exception $e){
                return false;
            }
        }

        //3.4>>用户上线为 省地县
        if (in_array($first_leader['role_id'],array(1,2,3))){
            try{
                //$this->mode5($user,$order,$first_money,$second_money,$thirdmoney);//模式五
                $this->mode1($user,$order,$first_money,$second_money,$thirdmoney,$order_type);//模式一
            }catch(Exception $e){
                return false;
            }
        }


        return true;

    }

    //region 原始的分佣写法
    /* if ($user['second_leader'] > 0 && $second_money > 0.01) {
            $data = array(
                'user_id' => $user['second_leader'],
                'buy_user_id' => $user['user_id'],
                'nickname' => $user['nickname'],
                'order_sn' => $order['order_sn'],
                'order_id' => $order['order_id'],
                'goods_price' => $order['goods_price'],
                'money' => $second_money,
                'level' => 2,
                'create_time' => time(),
                'store_id' => $order['store_id'],
            );
            M('rebate_log')->add($data);
            // 微信推送消息
            $tmp_user = M('users')->where("user_id = {$user['second_leader']}")->find();
            if ($tmp_user['oauth'] == 'weixin') {
                $wx_content = "你的二级下线,刚刚下了一笔订单:{$order['order_sn']} 如果交易成功你将获得 ￥" . $second_money . "奖励 !";
                $jssdk->push_msg($tmp_user['openid'], $wx_content);
            }*/
    //endregion
    /**
     * 模式一  自营店铺 消费  上线为 普通用户  无上线 不反利
     * @param array $user       订单的用户信息
     * @param array $order      订单信息
     * @param int $first_money  一级佣金
     * @param int $second_money 二级佣金
     * @param int $thirdmoney   三级佣金
     * @param int $order_type   订单模式
     * @return bool
     * @throws Exception
     */
    protected function mode1($user=[],$order=[],$first_money=0,$second_money=0,$thirdmoney=0,$order_type=0){
        $param['order_id'] = $order['order_id'];
        $r = $this->order_rebate($param);

        // 一级 分销商赚 的钱. 小于一分钱的 不存储
        //一级用户信息
        $first_user = M('users')->where(array('user_id'=>$user['first_leader']))->find();
        if (empty($first_user)) throw new Exception('无此上线1');
        if ($first_money > 0.0001) {
            $this->_store_rebate_log($first_user,$order,$first_money,1,'一级好友,',$order_type);
        }
        // 二级 分销商赚 的钱.
        //二级用户信息
        $second_user = M('users')->where(array('user_id'=>$first_user['first_leader']))->find();
        if (empty($second_user)) throw new Exception('无此上线2');
        if ($second_money > 0.0001) {
            $this->_store_rebate_log($second_user,$order,$second_money,2,'二级好友,',$order_type);
        }
        // 三级 分销商赚 的钱.
        //三级用户信息
        $third_user = M('users')->where(array('user_id'=>$second_user['first_leader']))->find();
        if (empty($third_user)) throw new Exception('无此上线3');
        if ($thirdmoney > 0.0001) {
            $this->_store_rebate_log($third_user,$order,$thirdmoney,3,'家庭号粉丝,',$order_type);//你的三级下线
        }
        return true;
    }

    /**
     * 模式五  自营店铺消费  上线为分销商   舍去
     * @param array $user       订单的用户信息
     * @param array $order      订单信息
     * @param int $first_money  一级佣金
     * @param int $second_money 二级佣金
     * @param int $thirdmoney   三级佣金
     * @return bool
     * @throws Exception
     */
    protected function mode5($user=[],$order=[],$first_money=0,$second_money=0,$thirdmoney=0){
        // 一级 分销商赚 的钱. 小于一分钱的 不存储
        //用户上线为分销商  分销商信息
        $first_user = M('users')->where(array('user_id'=>$user['first_leader']))->find();
        if (empty($first_user)) throw new Exception('无此上线1');
        if ( $first_money > 0.01) {
            $this->_store_rebate_log($first_user,$order,$first_money,1,'你的一级下线,');
        }
    }

    /**
     * 模式三  自营店铺消费  上线为实体店  实体店下线去自营店过后买东西 实体店算2级  分销商算1级  赵总语音述   舍去 （后面改成组满三级分销）
     * @param array $user       订单的用户信息
     * @param array $order      订单信息
     * @param int $first_money  一级佣金
     * @param int $second_money 二级佣金
     * @param int $thirdmoney   三级佣金
     * @return bool
     * @throws Exception
     */
    protected function mode3($user=[],$order=[],$first_money=0,$second_money=0,$thirdmoney=0){
        // 一级 分销商赚 的钱. 小于一分钱的 不存储
        //用户的上线  实体店
        $first_user = M('users')->where(array('user_id'=>$user['first_leader']))->find();
        //实体店的上线 分销商
        $second_user = M('users')->where(array('user_id'=>$first_user['first_leader']))->find();

        if (empty($first_user)) throw new Exception('无此上线1');
        if (empty($second_user)) throw new Exception('无此上线2');

        //分销商 分佣  分销商算1级
        if ($first_money > 0.01) {
            $this->_store_rebate_log($second_user,$order,$first_money,1,'你的一级下线,');
        }
        // 实体店分佣  实体店算2级
        if ($second_money > 0.01) {
            $this->_store_rebate_log($first_user,$order,$second_money,2,'你的二级下线,');
        }
        return true;
    }

    /**
     * 保存分成记录
     * @param array $user 获得分佣的人
     * @param array $order 订单信息
     * @param int $money 获得的佣金
     * @param int $level 当前用户等级
     * @param string $content 微信推送内容前缀
     * @return bool
     */
    /*protected function _rebate_log($user = [], $order = [], $money = 0, $level = 0, $content = ''){
        $data = array(
            'user_id' => $user['third_leader'],
            'buy_user_id' => $user['user_id'],
            'nickname' => $user['nickname'],
            'order_sn' => $order['order_sn'],
            'order_id' => $order['order_id'],
            'goods_price' => $order['goods_price'],
            'money' => $money,
            'level' => $level,
            'create_time' => time(),
            'store_id' => $order['store_id'],
        );
        M('rebate_log')->add($data);
        // 微信推送消息
        $tmp_user = M('users')->where("user_id = {$user['third_leader']}")->find();
        if ($tmp_user['oauth'] == 'weixin' && !empty($tmp_user['openid'])) {
            $wx_content = $content . "刚刚下了一笔订单:{$order['order_sn']} 如果交易成功你将获得 ￥" . $money . "奖励 !";
            send_wx_msg($tmp_user['openid'], $wx_content); //发送微信消息
        }
    }*/

    /**
     * 分销记录
     * @param $order
     * @return bool
     */
    public function rebate_log2($order)
    {
        //region 判断是否为实体店
        $store = M('store')->where(array('store_id' => $order['store_id']))->find();

        if ($store['is_own_shop'] != 1) {//是否自营店铺 1是 0否
            $res = $this->store_rebate_log($order,0);
        }else{
            $res = $this->rebate_log($order,0);
        }
        //endregion
        M('order')->where("order_id = {$order['order_id']}")->save(array("is_distribut" => 1));  //修改订单为已经分成
        return $res;
    }


//         `rate` '佣金比例'
//         `platform_rate`'平台返利比例',
//         `province_rate` '省级返利比例',
//         `city_rate` '地级返利比例',
//         `county_rate` '县级返利比例',
//         `distributor_rate` '分销商返利比例',
//         `shop_rate` '店铺返利比例',
//         `user_rate` '用户返利比例',
//            1	省级
//            2	地级
//            3	县级
//            4	分销商
//            5	实体店
    /**
     * 实体店分销记录
     * @param $order
     * @param int $order_type
     * @return bool
     */
    public function store_rebate_log($order,$order_type=0)
    {
        //自营店情况判断
        $store = M('store')->where(array('store_id' => $order['store_id']))->find();

        if ($store['is_own_shop'] == 1 || $store['discount_id'] == 0) {//是否自营店铺 1是 0否
            return false;
        }
        //用户信息
        $user = M('users')->where(array('user_id' => $order['user_id']))->find();

        //获取当前店铺的折扣模式
        $discount = M('discount')->where(array('discount_id' => $store['discount_id']))->find();

        if (empty($discount)) return false;
//         dump($order);dump($store);dump($user);die;

        //region 判断分销模式  根据模式  返利
        //1>>判断 用户是否 不存在 上线
        if (empty($user['first_leader'])) {
            //1.1>>不存在上线则成为当前店铺店主的下线
            //模式六
            $this->mode6($user,$store['user_id']);
            //重新获取用户信息
            $user = M('users')->where(array('user_id' => $order['user_id']))->find();
            //分销模式二
            try{
                $this->mode2($user,$order,$discount,$order_type);
            }catch(Exception $e){
                return false;
            }

            return true;
        }

        //2.>>存在上级的情况
        //2.1>>获取上级信息
        $first_leader = M('users')->where(array('user_id'=>$user['first_leader']))->find();

        //2.2判断上级是否 就是 当前购买东西的店铺  || 上级 或者 为普通消费者
        if ($user['first_leader'] == $store['user_id'] || $first_leader['role_id'] == 0){
            //分销模式二
            try{
                $this->mode2($user,$order,$discount,$order_type);
            }catch(Exception $e){
                return false;
            }
            return true;
        }

        //3>>上级不是当前购买东西的店铺  ||  上级 不是 普通消费者
        //3.1>>判断用户的上级 是否是 实体店
        if ($first_leader['role_id'] == 5 && $user['first_leader'] != $store['user_id']){
            try{
                $this->mode4($user,$order,$discount,$order_type);
            }catch(Exception $e){
                return false;
            }
            return true;
        }

        //3.2>>如果用户的上级 是 分销商   疑问
        if ($first_leader['role_id'] == 4){
            //分销模式二
            try{
                //$this->mode2($user,$order,$discount,true);
                $this->mode2_2($user,$order,$discount,$order_type);
            }catch(Exception $e){
                return false;
            }
            return true;
        }
        //用户的上级是县级 及其以上,相当于当前用户最低等级是分销商
        if ($first_leader['role_id'] >0 and $first_leader['role_id'] <=3){
            $this->store_mode($user,$order,$discount,$order_type);
            return true;
        }

    }

    /**
     * 模式二  用户在上级实体店形成消费  返利
     * 七级分销 （用户->实体店->分销商->县级->地级->省级->平台）
     * @param array $user       用户
     * @param array $order      订单
     * @param array $discount   分销返利模式
     * @param int $order_type      订单类型
     * @return bool
     * @throws Exception
     */
    protected function mode2($user=[],$order=[],$discount=[],$order_type=0){
        if (empty($user) || empty($order)) return false;
        //店铺信息
        $store = M('store')->where(array('store_id'=>$order['store_id']))->find();
        //1.2>>根据 用户->实体店->分销商->县级->地级->平台 返利
        //1.2.1>>计算分销佣金   总佣金 = 订单实际金额 * 选择的折扣比例
        $commission = $order['goods_price'] * ( $discount['rate'] / 100 );
        //1.2.2>>计算各级佣金   各级佣金 = 各级比例 * 佣金总额
        $user_comm       =   $commission * ( $discount['user_rate'] / 100 );//round( $commission * ( $discount['user_rate'] / 100 ),2);       //用户佣金
        $shop_comm       =   $commission * ( $discount['shop_rate'] / 100 );//round($commission * ( $discount['shop_rate'] / 100 ),2);        //店铺佣金
        $distributor_comm=   $commission * ( $discount['distributor_rate'] /100 );//round($commission * ( $discount['distributor_rate'] /100 ),2);  //分销商佣金
        $county_comm     =   $commission * ( $discount['county_rate'] / 100 );//round($commission * ( $discount['county_rate'] / 100 ),2);      //县级佣金
        $city_comm       =   $commission * ( $discount['city_rate'] / 100 );//round($commission * ( $discount['city_rate'] / 100 ),2);        //地级佣金
        $province_comm   =   $commission * ( $discount['province_rate'] / 100 );//round($commission * ( $discount['province_rate'] / 100 ),2);    //省级佣金
        $platform_comm   =   $commission * ( $discount['platform_rate'] / 100 );//round($commission * ( $discount['platform_rate'] / 100 ),2);    //平台佣金

        //1.3>>当前级别为分销商  返利从分销商往上
        //region 佣金记录
        //用户返利
        if ($user_comm > 0){
            $this->_store_rebate_log($user, $order, $user_comm, 0, '您',$order_type);
        }

        //店铺返利
        $user = M('users')->where(array('user_id' => $store['user_id']))->find();
        if ($shop_comm > 0){
            if (empty($user)) {
                throw new Exception('无此上线1');
            }
            $this->_store_rebate_log($user, $order, $shop_comm, 1, '一级好友,',$order_type);
        }

        //分销商返利
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 4))->find();
        if ($distributor_comm > 0){
            if (empty($user)) {
                throw new Exception('无此上线2');
            }
            $this->_store_rebate_log($user, $order, $distributor_comm, 2,  '二级好友,',$order_type);
        }
        //县级返利
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 3))->find();
        if ($county_comm > 0){
            if (empty($user)) {
                throw new Exception('无此上线3');
            }
            $this->_store_rebate_log($user, $order, $county_comm, 3, '家庭号粉丝,',$order_type);//您的三级
        }

        //地级返利
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 2))->find();
        if ($city_comm > 0){
            if (empty($user)) {
                throw new Exception('无此上线4');
            }
            $this->_store_rebate_log($user, $order, $city_comm, 4, '家庭号粉丝,',$order_type);//您的四级
        }

        //省级返利
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 1))->find();
        if ($province_comm > 0){
            if (empty($user)) {
                throw new Exception('无此上线5');
            }
            $this->_store_rebate_log($user, $order, $province_comm, 5, '家庭号粉丝,',$order_type);//您的五级
        }

        //平台
        if ($platform_comm > 0){
            $user = array('user_id' => 0, 'nickname' => '家庭号');
            $this->_store_rebate_log($user, $order, $platform_comm, 6, '家庭号粉丝,',$order_type);//您的六级
        }

        //endregion
    }

    /**
     * @param array $user
     * @param array $order
     * @param array $discount
     * @param int $order_type
     * 实体店返利
     * wj
     * 2018-6-11
     * @return bool
     */
    public function store_mode($user=[],$order=[],$discount=[],$order_type=0){
        if (empty($user)){
            return false;
        }
        if (empty($order)){
            return false;
        }
        if (empty($discount)){
            return false;
        }
        $store_info = M('store')->where(array('store_id'=>$order['store_id']))->find();
        $rebate_amount = $order['goods_price'] * ( $discount['rate'] / 100 );//提成总金额

        $rebate_amount = $rebate_amount - $rebate_amount * 0.006;//2019-04-20 从总提成中去掉

        //1.2.2>>计算各级佣金   各级佣金 = 各级比例 * 佣金总额
        $user_rebate       =   $rebate_amount * ( $discount['user_rate'] / 100 );//用户返利
        $shop_rebate       =   $rebate_amount * ( $discount['shop_rate'] / 100 );//实体店返利
        $distributor_rebate=   $rebate_amount * ( $discount['distributor_rate'] /100 );//分销商返利
        $county_rebate     =   $rebate_amount * ( $discount['county_rate'] / 100 );//县级返利
        $city_rebate       =   $rebate_amount * ( $discount['city_rate'] / 100 );//地级返利
        $province_rebate   =   $rebate_amount * ( $discount['province_rate'] / 100 );//省级返利
        $platform_rebate  =   $rebate_amount * ( $discount['platform_rate'] / 100 );//平台返利
        //用户返利
        if ($user_rebate > 0){
            $this->_store_rebate_log($user, $order, $user_rebate, 0, '您',$order_type);
        }
        //平台返利
        if ($platform_rebate > 0){
            $user = array('user_id' => 0, 'nickname' => '家庭号');
            $this->_store_rebate_log($user, $order, $platform_rebate, 6, '家庭号粉丝,',$order_type);
        }
        //实体店返利
        if (empty($store_info)){
            return false;
        }
        $store_user_info = M('Users')->where(array('user_id' => $store_info['user_id']))->find();//实体店主用户信息
        if (empty($store_user_info)){
            return  false;
        }
        if ($shop_rebate>0){
            $this->_store_rebate_log($store_user_info, $order, $shop_rebate, 1, '实体店返利,',$order_type);
        }
        //分销商返利
        $distributor_user_info = M('Users')->where(array('user_id' => $store_user_info['first_leader']))->find();//分销商用户信息
        if (empty($distributor_user_info)){//分销商不存在
            return false;
        }
        if ($distributor_user_info['role_id'] !=4){//不是分销商
            return false;
        }
        if ($distributor_rebate>0){
            $this->_store_rebate_log($distributor_user_info, $order, $distributor_rebate, 2, '分销商返利,',$order_type);
        }
        //县级返利
        $county_user_info = M('Users')->where(array('user_id' => $distributor_user_info['first_leader']))->find();//县级用户信息
        if (empty($county_user_info)){//县级用户不存在
            return false;
        }
        if ($county_user_info['role_id'] !=3){//不是县级
            return false;
        }
        if ($county_rebate>0){
            $this->_store_rebate_log($county_user_info, $order, $county_rebate, 3, '县级返利,',$order_type);
        }
        //地级返利
        $city_user_info = M('Users')->where(array('user_id' => $county_user_info['first_leader']))->find();//地级用户信息
        if (empty($city_user_info)){//地级用户不存在
            return false;
        }
        if ($city_user_info['role_id'] !=2){//不是地级
            return false;
        }
        if ($city_rebate>0){
            $this->_store_rebate_log($city_user_info, $order, $city_rebate, 4, '地级返利,',$order_type);
        }
        //省级返利
        $province_user_info = M('Users')->where(array('user_id' => $city_user_info['first_leader']))->find();//省级用户信息
        if (empty($province_user_info)){//省级用户不存在
            return false;
        }
        if ($province_user_info['role_id'] !=1){//不是省级
            return false;
        }
        if ($province_rebate>0){
            $this->_store_rebate_log($province_user_info, $order, $province_rebate, 5, '省级返利,',$order_type);
        }
    }
    /**
     * 模式二.二  当用户上线是分销商时
     * 七级分销 （用户->实体店->（用户的上线分销商A）->县级->地级->省级->平台）
     * @param array $user       用户
     * @param array $order      订单
     * @param array $discount   分销返利模式
     * @param int $order_type      订单类型
     * @return bool
     * @throws Exception
     */
    protected function mode2_2($user=[],$order=[],$discount=[],$order_type=0){
        if (empty($user) || empty($order)) return false;
        //店铺信息
        $store = M('store')->where(array('store_id'=>$order['store_id']))->find();
        //1.2>>根据 用户->实体店->分销商->县级->地级->平台 返利
        //1.2.1>>计算分销佣金   总佣金 = 订单实际金额 * 选择的折扣比例
        $commission = $order['goods_price'] * ( $discount['rate'] / 100 );
        //1.2.2>>计算各级佣金   各级佣金 = 各级比例 * 佣金总额
        $user_comm       =   $commission * ( $discount['user_rate'] / 100 );//round( $commission * ( $discount['user_rate'] / 100 ),2);       //用户佣金
        $shop_comm       =   $commission * ( $discount['shop_rate'] / 100 );//round($commission * ( $discount['shop_rate'] / 100 ),2);        //店铺佣金
        $distributor_comm=   $commission * ( $discount['distributor_rate'] /100 );//round($commission * ( $discount['distributor_rate'] /100 ),2);  //分销商佣金
        $county_comm     =   $commission * ( $discount['county_rate'] / 100 );//round($commission * ( $discount['county_rate'] / 100 ),2);      //县级佣金
        $city_comm       =   $commission * ( $discount['city_rate'] / 100 );//round($commission * ( $discount['city_rate'] / 100 ),2);        //地级佣金
        $province_comm   =   $commission * ( $discount['province_rate'] / 100 );//round($commission * ( $discount['province_rate'] / 100 ),2);    //省级佣金
        $platform_comm   =   $commission * ( $discount['platform_rate'] / 100 );//round($commission * ( $discount['platform_rate'] / 100 ),2);    //平台佣金

        //1.3>>当前级别为分销商  返利从分销商往上
        //region 佣金记录
        //用户返利
        if ($user_comm > 0){
            $this->_store_rebate_log($user, $order, $user_comm, 0, '您',$order_type);
        }

        //店铺返利
        $shop_user = M('users')->where(array('user_id' => $store['user_id']))->find();//店铺用户
        if ($shop_comm > 0){
            if (empty($shop_user)) {
                throw new Exception('无此上线1');
            }
            $this->_store_rebate_log($shop_user, $order, $shop_comm, 1, '家庭号粉丝,',$order_type);
        }

        //用户的上线分销商返利
        $user_first = M('users')->where(array('user_id' => $user['first_leader']))->find();//用户的上线  分销商A
        if ($distributor_comm > 0){
            if (empty($user_first)) {
                throw new Exception('无此上线2');
            }
            $this->_store_rebate_log($user_first, $order, $distributor_comm, 2, '一级好友,',$order_type);
        }
        //县级返利
        $distributor_user = M('users')->where(array('user_id' => $shop_user['first_leader'], 'role_id' => 4))->find();//店铺的上级 分销商B
        $user = M('users')->where(array('user_id' => $distributor_user['first_leader'], 'role_id' => 3))->find();//县级
        if ($county_comm > 0){
            if (empty($user)) {
                throw new Exception('无此上线3');
            }
            $this->_store_rebate_log($user, $order, $county_comm, 3, '家庭号粉丝,',$order_type);
        }

        //地级返利
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 2))->find();
        if ($city_comm > 0){
            if (empty($user)) {
                throw new Exception('无此上线4');
            }
            $this->_store_rebate_log($user, $order, $city_comm, 4, '家庭号粉丝,',$order_type);
        }

        //省级返利
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 1))->find();
        if ($province_comm > 0){
            if (empty($user)) {
                throw new Exception('无此上线5');
            }
            $this->_store_rebate_log($user, $order, $province_comm, 5, '家庭号粉丝,',$order_type);
        }

        //平台
        if ($platform_comm > 0){
            $user = array('user_id' => 0, 'nickname' => '家庭号');
            $this->_store_rebate_log($user, $order, $platform_comm, 6, '家庭号粉丝,',$order_type);
        }

        //endregion
    }

    /**
     * 模式四  用户在非上级的实体店形成消费  返利
     * 一级分销 （用户上线）X   需求更改  返给用户上线  实体店A后   依次往实体店B的上线返利  七级分销 （）
     * @param array $user       用户
     * @param array $order      订单
     * @param array $discount   分销返利模式
     * @param int $order_type   订单类型
     * @return bool
     * @throws Exception
     */
    /*protected function mode4($user=[],$order=[],$discount=[]){
        if (empty($user) || empty($order)) return false;

        //$store = M('store')->where(array('store_id'=>$order['store_id']))->find();

        //1.1>>计算分销佣金   总佣金 = 订单实际金额 * 选择的折扣比例
        $commission = $order['goods_price'] * ( $discount['rate'] / 100 );
        //1.2>>计算出用户上线 实体店A 的返利  各级佣金 = 各级比例 * 佣金总额
        $shop_comm      =   round($commission * ( $discount['shop_rate'] / 100 ),2);        //店铺佣金
        //1.3>>当前级别为分销商  返利从分销商往上
        //region 佣金记录
        //实体店A返利
        $user = M('users')->where(array('user_id' => $user['first_leader']))->find();
        if ($shop_comm >0){
            if (empty($user)) {
                throw new Exception('无此上线1');
            }
            $this->_store_rebate_log($user, $order, $shop_comm, 1, '您的一级,');
        }
        return true;
        //endregion
    }*/
    protected function mode4($user=[],$order=[],$discount=[],$order_type=0){
        if (empty($user) || empty($order)) return false;

        $store = M('store')->where(array('store_id'=>$order['store_id']))->find();
        //1.1>>计算分销佣金   总佣金 = 订单实际金额 * 选择的折扣比例
        $commission = $order['goods_price'] * ( $discount['rate'] / 100 );
        //1.2>>计算出用户上线 实体店A 的返利
        $shop_a         =   $commission * ($discount['shop_rate'] / 100);//round( $commission * ($discount['shop_rate'] / 100) ,2);

        //1.2.1>>根据 用户 （实体店B除外） ->（实体店B的上级）分销商->县级->地级->平台 返利
        //1.2.2>>计算各级佣金   各级佣金 = 各级比例 * 佣金总额
        $user_comm       =   $commission * ( $discount['user_rate'] / 100 );//round( $commission * ( $discount['user_rate'] / 100 ),2);        //用户佣金
        //$shop_comm       =   round($commission * ( $discount['shop_rate'] / 100 ),2);        //店铺佣金
        $distributor_comm=   $commission * ( $discount['distributor_rate'] /100 );//round($commission * ( $discount['distributor_rate'] /100 ),2);  //分销商佣金
        $county_comm     =   $commission * ( $discount['county_rate'] / 100 );//round($commission * ( $discount['county_rate'] / 100 ),2);      //县级佣金
        $city_comm       =   $commission * ( $discount['city_rate'] / 100 );//round($commission * ( $discount['city_rate'] / 100 ),2);        //地级佣金
        $province_comm   =   $commission * ( $discount['province_rate'] / 100 );//round($commission * ( $discount['province_rate'] / 100 ),2);    //省级佣金
        $platform_comm   =   $commission * ( $discount['platform_rate'] / 100 );//round($commission * ( $discount['platform_rate'] / 100 ),2);    //平台佣金

        //1.3>>当前级别为分销商  返利从分销商往上
        //region 佣金记录
        //用户返利
        $this->_store_rebate_log($user, $order, $user_comm, 0, '您',$order_type);
        //实体店A返利
        $user = M('users')->where(array('user_id' => $user['first_leader']))->find();
        if (empty($user)) {
            throw new Exception('无此上线1');
        }
        $this->_store_rebate_log($user, $order, $shop_a, 1, '一级好友,',$order_type);
        //购买东西的实体店B的上线  分销商 返利
        $user = M('users')->where(array('user_id' => $store['user_id']))->find();//实体店B
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 4))->find();// 实体店B的上级 分销商

        if (empty($user)) {
            throw new Exception('无此上线2');
        }
        $this->_store_rebate_log($user, $order, $distributor_comm, 2, '家庭号粉丝,',$order_type);
        //实体店B的县级返利
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 3))->find();//县级
        if (empty($user)) {
            throw new Exception('无此上线4');
        }
        $this->_store_rebate_log($user, $order, $county_comm, 3, '家庭号粉丝,',$order_type);
        //实体店B的地级返利
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 2))->find();//地级
        if (empty($user)) {
            throw new Exception('无此上线5');
        }
        $this->_store_rebate_log($user, $order, $city_comm, 4, '家庭号粉丝,',$order_type);
        //实体店B的省级返利
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 1))->find();//省级
        if (empty($user)) {
            throw new Exception('无此上线6');
        }
        $this->_store_rebate_log($user, $order, $province_comm, 5, '家庭号粉丝,',$order_type);
        //平台
        $user = array('user_id' => 0, 'nickname' => '家庭号');//平台
        $this->_store_rebate_log($user, $order, $platform_comm, 6, '家庭号粉丝,',$order_type);
        //endregion
    }

    /**
     * 模式六  无上线，在实体店消费，成为实体店下线
     * @param array $user
     * @param $first_leader
     * @return bool
     */
    protected function mode6($user=[],$first_leader){
        $user['first_leader'] = $first_leader;
        return M('users')->save($user);
    }


    /**
     * 保存分成记录
     * @param array $user 获得分佣的人
     * @param array $order 订单信息
     * @param int $money 获得的佣金
     * @param int $level 当前用户等级
     * @param string $content 微信推送内容前缀
     * @param int $order_type 订单类型   0 商品订单  1 扫码支付订单
     * @return bool
     */
    protected function _store_rebate_log($user = [], $order = [], $money = 0, $level = 0, $content = '',$order_type=0)
    {
        $data = array(
            'user_id' => $user['user_id'],
            'buy_user_id' =>$order['user_id'],
            'nickname' => $user['nickname'],
            'order_sn' => $order['order_sn'],
            'order_id' => $order['order_id'],
            'goods_price' => $order['goods_price'],
            'money' => $money,
            'level' => $level,
            'create_time' => time(),
            'store_id' => $order['store_id'],
            'order_type' => $order_type,
        );
        $res = M('rebate_log')->add($data);
        if ($res === false) return false; //添加记录失败
        //if ($level == 0) return true; //等级为0表示是用户自己
        // 微信推送消息
        //get_is_send_shopping_none()是否未支付也发送微信
        if (get_is_send_shopping_none()){
            $tmp_user = M('users')->where("user_id = {$user['user_id']}")->find();
            if ($tmp_user['oauth'] == 'weixin' && !empty($tmp_user['openid'])) {
                $goods_names = M('order_goods')->where(array('order_id'=>$order['order_id']))->field('goods_name')->select();
                if (!empty($goods_names)) {
                    $goods_names = arr2_2_str($goods_names);
                }
                //$wx_content = $content . "刚刚下了一笔订单:{$order['order_sn']} 如果交易成功你将获得 ￥" . $money . "奖励 !";
                if ($user['user_id'] == $order['user_id']){
                    $wx_content = "亲,您".$content."下了一笔订单   \n ";
                }else{
                    $wx_content = "亲,您的".$content."下了一笔订单   \n ";
                }
                $wx_content .= "  昵称 : 【".$user['nickname']."】 \n 商品名称：【".$goods_names."】   \n 时间：".date('Y-m-d H:i',time())." \n  支付完成后，您蒋获得  ￥".$money."佣金 !";

                send_wx_msg($tmp_user['openid'], $wx_content); //发送微信消息
                if (!empty($tmp_user['reg_id'])){
                    $title = '订单分佣';
                    send_jpush_msg($tmp_user['reg_id'] ,$title, $wx_content,'comm');
                }
            }
        }


    }

    /**
     * 自动分成 符合条件的 分成记录
     */
    function auto_confirm($store_id)
    {

        $store_distribut = M('store_distribut')->where("store_id = {$store_id}")->find();
        if (empty($store_distribut) || $store_distribut['switch'] == 0)
            return false;

        $today_time = time();
        $distribut_date = tpCache('shopping.auto_transfer_date'); // 商家结算时间拿来 跟分销结算一起同一时间
        $distribut_time = $distribut_date * (60 * 60 * 24); // 计算天数 时间戳
        $rebate_log_arr = M('rebate_log')->where("store_id = $store_id and status = 2 and ($today_time - confirm) >  $distribut_time")->select();
        foreach ($rebate_log_arr as $key => $val) {
            accountLog($val['user_id'], $val['money'], 0, "订单:{$val['order_sn']}分佣", $val['money']);
            $val['status'] = 3;
            $val['confirm_time'] = $today_time;
            $val['remark'] = $val['remark'] . "满{$distribut_date}天,程序自动分成.";
            M("rebate_log")->where("id = {$val[id]}")->save($val);
        }
    }


    /**
     * 卡券生成分销记录
     * @param int $order_id 主订单id
     * @return bool
     */
    public function card_rebate_log($order_id = 0)
    {
        //分销设置
        /*$store_distribut = M('store_distribut')->where("store_id = {$order['store_id']}")->find();
        if(empty($store_distribut) || $store_distribut['switch'] == 0)
            return false;*/
        //订单信息
        $map = array('order_id' => $order_id);
        $order = M("card_order")->where($map)->find();
        //子订单
        $sub_order = M('card_suborder')->where($map)->select();
        //下订单的用户信息
        //找的不应该是订单支付人 应该是店铺的店主 因为返利是从经销商往上返利
        $user_id = get_column('store', array('store_id' => $order['store_id']), 'user_id');
        $user = M('users')->where(array('user_id' => $user_id))->find(); //$order['user_id']
        //如果以上信息有不存在的 即为数据错乱订单
        if (empty($order) || empty($sub_order) || empty($user)) return false;

        // 获取所有商品分类
        //$commission         = 0 ;//佣金总金额
        $fifth_commission = 0;//分销店佣金
        $fourth_commission = 0;//县级佣金
        $third_commission = 0;//地级佣金
        $second_commission = 0;//省级佣金
        $first_commission = 0;//平台佣金
        //region 各级佣金计算
        foreach ($sub_order as $key => $vo) {
            // 单张卡返利金额 = 单价 * 返利比例 - 默认返现
            // 总返利金额   = (单价 * 返利比例 - 默认返现) * 数量
            //总返利金额
            $sum_comm = (round($vo['card_price'] * ($vo['rate'] / 100), 2) - $vo['default_rebate']) * $vo['card_num'];
            //分销店佣金
            $fifth_comm = (round($sum_comm * ($vo['fifth_rate'] / 100), 2));
            $fifth_commission += $fifth_comm;
            //县级佣金
            $fourth_comm = (round($sum_comm * ($vo['fourth_rate'] / 100), 2));
            $fourth_commission += $fourth_comm;
            //地级佣金
            $third_comm = (round($sum_comm * ($vo['third_rate'] / 100), 2));
            $third_commission += $third_comm;
            //省级店佣金
            $second_comm = (round($sum_comm * ($vo['second_rate'] / 100), 2));
            $second_commission += $second_comm;
            //平台佣金
            $first_comm = ($sum_comm - $fifth_comm - $fourth_comm - $third_comm - $second_comm);
            $first_commission += $first_comm;
            //region 记录到订单
            $data = array(
                'fifth_commission' => $fifth_comm,
                'fourth_commission' => $fourth_comm,
                'third_commission' => $third_comm,
                'second_commission' => $second_comm,
                'first_commission' => $first_comm,
            );
            M('card_suborder')->where(array('sub_id' => $vo['sub_id']))->save($data);
            //endregion
        }
        //endregion

        //当前级别为分销商  返利从分销商往上

        //region 分销商返利记录
        $this->_card_rebate_log($user, $order, $fifth_commission, $user['role_id'], '');
        //endregion
        //region 县级佣金
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 3))->find();
        $this->_card_rebate_log($user, $order, $fourth_commission, $user['role_id'], '您的一级,');
        //endregion
        //region 地级佣金
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 2))->find();
        $this->_card_rebate_log($user, $order, $third_commission, $user['role_id'], '您的第二级,');
        //endregion
        //region 省级佣金
        $user = M('users')->where(array('user_id' => $user['first_leader'], 'role_id' => 1))->find();
        $this->_card_rebate_log($user, $order, $second_commission, $user['role_id'], '您的第三级,');
        //endregion
        //region 平台佣金
        $user = array('user_id' => 0, 'nickname' => '家庭号');
        $this->_card_rebate_log($user, $order, $first_commission, 0, '您的第四级,');
        //endregion

        M('card_order')->where(array('order_id' => $order['order_id']))->save(array("is_distribut" => 1));  //修改订单为已经分成
    }

    /**
     * 保存分成记录
     * @param array $user 获得分佣的人
     * @param array $order 订单信息
     * @param int $money 获得的佣金
     * @param int $level 当前用户等级
     * @param string $content 微信推送内容前缀
     * @return bool
     */
    protected function _card_rebate_log($user = [], $order = [], $money = 0, $level = 0, $content = '')
    {
        $data = array(
            'user_id' => $user['user_id'],        //分佣金的人id
            'buy_user_id' => $order['user_id'],       //买卡券的人id
            'nickname' => $user['nickname'],       //分佣金的人昵称
            'order_sn' => $order['order_sn'],      //订单sn号
            'order_id' => $order['order_id'],      //订单id
            'card_price' => $order['card_price'],    //卡券总额
            'money' => $money,                  //佣金
            'level' => $level,                  //等级
            'create_time' => time(),                  //记录时间
            'store_id' => $order['store_id'],      //订单店铺id
            'confirm_time' => time(),                  //分成时间
        );
        if ($user['user_id'] == 0) $data['admin_id'] = 1;//如果用户id不存在
        $res = M('card_rebate_log')->add($data);
        if ($res === false) return false;
        // 微信推送消息
        /*if(!empty($user['openid']))
        {
            $wx_content = $content."刚刚下了一笔订单:{$order['order_sn']} 如果交易成功你将获得 ￥".$money."奖励 !";
            send_wx_msg($user['openid'],$wx_content); //发送微信消息
        }*/
    }


    //收款码扫码分成
    public function rebate_log_scan($order){
        //region 判断是否为实体店
        $store = M('store')->where(array('store_id' => $order['store_id']))->find();

        $order['goods_price'] = $order['need_pay'];
        if ($store['is_own_shop'] != 1) {//是否自营店铺 1是 0否
            $res = $this->store_rebate_log($order,1);
        }else{
            $res = $this->rebate_log($order,1);
        }
        return $res;
    }
    /**
     * 订单返利 自营店订单
     * @param $param
     * $param['order_id'] 订单id
     * @return  array
     * wj
     * 2018-6-5
     */
    public function order_rebate($param){
        $map['order_id'] = array('eq',$param['order_id']);
        $order_info = M('Order')->where($map)->find();
        if (empty($order_info)){
            $r_data['status'] = 0;
            $r_data['msg'] = '订单不存在';
            return $r_data;
        }
        $user_id = $order_info['user_id'];//用户id
        //$store_id = $order_info['store_id'];//店铺id
        $total_amount = $order_info['total_amount'];//订单总额
        if ($total_amount<=0){
            $r_data['status'] = 0;
            $r_data['msg'] = '订单金额为0';
            return $r_data;
        }
        unset($map);
        $map['user_id'] = array('eq',$user_id);
        $user_info = M('Users')->where($map)->find();
        if (empty($user_info)){
            $r_data['status'] = 0;
            $r_data['msg'] = '用户不存在';
            return $r_data;
        }
        $first_leader_id = $user_info['first_leader'];
        if (empty($first_leader_id)){
            $r_data['status'] = 0;
            $r_data['msg'] = '无上级';
            return $r_data;
        }
        unset($map);
        $map['user_id'] = array('eq',$first_leader_id);
        $first_info = M('Users')->where($map)->find();
        if (empty($first_info)){
            $r_data['status'] = 0;
            $r_data['msg'] = '上级不存在';
            return $r_data;
        }
        if ($first_info['role_id'] <=0 || empty($first_info['role_id'])){
            $r_data['status'] = 0;
            $r_data['msg'] = '上级不是提成的等级范围';
            return $r_data;
        }
        $config = array(
            7=>0.11,//平台比例
            6=>0.05,//订单总额的5%用来分成
            5=>0.4,//实体店比例 key 5标示role_id 对应的值
            4=>0.2,//分销商比例
            3=>0.15,//县级比例
            2=>0.1,//地级比例
            1=>0.04,//省级比例
        );
        $rebate_amount =$config[6] *$total_amount;//用来分成的总金额
        if ($rebate_amount<0.01){
            $r_data['status'] = 0;
            $r_data['msg'] = '分成总金额小于1分';
            return $r_data;
        }
        $role_id = $first_info['role_id'];
        $rebate_money =$rebate_amount*$config[$role_id];//提成金额
        if ($rebate_money<0.01){
            $r_data['status'] = 0;
            $r_data['msg'] = '分成单金额小于1分';
            return $r_data;
        }else{
            $param_f['user_id'] = $first_leader_id;
            $param_f['order_info'] = $order_info;
            //dump($param_f);
            //平台返利
            $user = array('user_id' => 0, 'nickname' => '家庭号');//平台
            $platform_comm = $rebate_amount*$config[7];//平台返利金额
            if ($platform_comm>=0.001){
                $this->_store_rebate_log($user, $order_info, $platform_comm, 6, '家庭号粉丝,',0);
            }
            $r_data = $this->do_order_rebate($param_f);
            $data_log['msg'] = $r_data['msg'];
            $data_log['order_id'] = $order_info['order_id'];
            $data_log['user_id'] = $order_info['user_id'];
            $data_log['order_info'] = json_encode($order_info);
            $data_log['add_date'] = date('Y-m-d H:i:s');
            $data_log['json_str'] = json_encode($r_data);
            $data_log['type'] = '主函数';
            M('RebateMsgLog')->add($data_log);
            //dump($r);
        }
        $r_data['status'] = 1;
        $r_data['msg'] = '成功';
        return $r_data;
    }

    /**
     * 递归执行返利操作(自营店)
     * @param $param
     * wj
     * 2018-6-5
     * @return bool
     * $param['order_info'] 订单信息
     * $param['user_id'] 用户id
     */
    public function do_order_rebate($param){
        $order_info = $param['order_info'];//订单信息
        static $i = 1;//避免出现死循环，最多递归5次（ps：最多就5级）
        $data_log['times'] = $i;
        if ($i>5){
            $data_log['msg'] = '5次循环结束';
            $data_log['order_id'] = $order_info['order_id'];
            $data_log['user_id'] = $order_info['user_id'];
            $data_log['order_info'] = json_encode($order_info);
            $data_log['add_date'] = date('Y-m-d H:i:s');
            M('RebateMsgLog')->add($data_log);
            return false;
        }
        $i++;
        $config = array(
            6=>0.05,//订单总额的5%用来分成
            5=>0.4,//实体店比例 key 5标示role_id 对应的值
            4=>0.2,//分销商比例
            3=>0.15,//县级比例
            2=>0.1,//地级比例
            1=>0.04,//省级比例
        );

        $total_amount = $order_info['total_amount'];//订单总额
        $user_id = $param['user_id'];//获取返利的用户id
        if (empty($user_id)){
            $data_log['msg'] = '用户为空';
            $data_log['order_id'] = $order_info['order_id'];
            $data_log['user_id'] = $order_info['user_id'];
            $data_log['order_info'] = json_encode($order_info);
            $data_log['add_date'] = date('Y-m-d H:i:s');
            M('RebateMsgLog')->add($data_log);
            return false;
        }
        $map_user['user_id'] = array('eq',$user_id);
        $user_info =M('Users')->where($map_user)->find();
        //echo M()->getLastSql();
        if (empty($user_info)){//提成用户不存在
            $data_log['msg'] = '用户不存在';
            $data_log['order_id'] = $order_info['order_id'];
            $data_log['user_id'] = $order_info['user_id'];
            $data_log['order_info'] = json_encode($order_info);
            $data_log['add_date'] = date('Y-m-d H:i:s');
            M('RebateMsgLog')->add($data_log);
            return false;
        }
        $param_func['user_id'] = $user_info['first_leader'];
        $param_func['order_info'] = $order_info;
        $rebate_amount =$config[6] *$total_amount;//用来分成的总金额
        if ($rebate_amount<0.01){
            $data_log['msg'] = '分成总金额小于1分：'.$rebate_amount;
            $data_log['order_id'] = $order_info['order_id'];
            $data_log['user_id'] = $order_info['user_id'];
            $data_log['order_info'] = json_encode($order_info);
            $data_log['add_date'] = date('Y-m-d H:i:s');
            M('RebateMsgLog')->add($data_log);
            $r_data['status'] = 0;
            $r_data['msg'] = '分成总金额小于1分：'.$rebate_amount;
            //echo $r_data['msg'];
            $this->do_order_rebate($param_func);
        }
        $role_id = $user_info['role_id'];//用户的角色id
        if ($role_id<=0 or $role_id>5){//用户等级不在提成范围内
            $data_log['msg'] = '上级不是提成的等级范围'.$role_id;
            $data_log['order_id'] = $order_info['order_id'];
            $data_log['user_id'] = $order_info['user_id'];
            $data_log['order_info'] = json_encode($order_info);
            $data_log['add_date'] = date('Y-m-d H:i:s');
            M('RebateMsgLog')->add($data_log);
            $r_data['status'] = 0;
            $r_data['msg'] = '上级不是提成的等级范围';
            //echo $r_data['msg'];
            $this->do_order_rebate($param_func);
        }
        $rebate_money=$rebate_amount*$config[$role_id];
        if ($rebate_money<0.01){
            $data_log['msg'] = '分成单金额小于1分'.$rebate_money;
            $data_log['order_id'] = $order_info['order_id'];
            $data_log['user_id'] = $order_info['user_id'];
            $data_log['order_info'] = json_encode($order_info);
            $data_log['add_date'] = date('Y-m-d H:i:s');
            M('RebateMsgLog')->add($data_log);
            $r_data['status'] = 0;
            $r_data['msg'] = '分成单金额小于1分';
            //echo $r_data['msg'];
            $this->do_order_rebate($param_func);
        }else{

            $this->_store_rebate_log($user_info, $order_info, $rebate_money, $role_id, $content = '自营订单总价返利',0);
            $this->do_order_rebate($param_func);
        }
    }

    /**
     * 批发商入驻费用分成
     */
    public function wholesalerRegisterRebate(){
        $store_id = I('id');
        $result = array('status'=>1,'msg'=>'操作成功');
        $model = M();
        $model->startTrans();
        $dbPrefix = C('DB_PREFIX');
        //找到付款记录
        $record = M('wholesaler_fee')->where(array('store_id'=>$store_id,'status'=>1))->find();
        if (empty($record)){
            $result['status'] = -1;
            $result['msg'] = '没有找到付款成功记录';
            exit(json_encode($result));
        }

        try {
            //平台分成比例
            $platformRate = M('config')->where(array('name' => 'wholesaler_register_fee_platform_rate'))->getField('value');

            //直接推荐人分成比例
            $firstLeaderRate = M('config')->where(array('name' => 'wholesaler_register_fee_direct_rate'))->getField('value');

            //批发商用户
            $user = M('users')->where(array('user_id' => $record['user_id']))->find();

            //直接推荐人用户ID
            $firstLeaderId = $user['first_leader'];

            //直接推荐人分成操作
            if ($firstLeaderId > 0 && $firstLeaderRate > 0) {
                $firstLeader = M('users')->where(array('user_id' => $firstLeaderId))->find();
                if (!empty($firstLeader)) {
                    $re = $this->saveWholesalerRebate($model, $dbPrefix, $firstLeader, $record['fee'], $firstLeaderRate, 1);
                }
            } else {
                //如果没有上级，全部给平台
                $platformRate = 100;
            }

            //平台分成操作
            if ($re !== false) {
                $re = $this->saveWholesalerRebate($model, $dbPrefix, null, $record['fee'], $platformRate, 1);
            }

            //更新付款记录
            if ($re !== false) {
                $model->table($dbPrefix . 'wholesaler_fee')->where(array('id' => $record['id']))->save(array('status' => 2));
            }

            //更新店铺状态
            if ($re !== false) {
                $model->table($dbPrefix . 'store')->where(array('store_id' => $store_id))->save(array('store_state' => 1));
            }

            if ($re === false) {
                $model->rollback();
                $result['status'] = -1;
                $result['msg'] = '操作失败';
                exit(json_encode($result));
            }
        }catch (\Exception $e){
            $model->rollback();
            $result['status'] = -1;
            $result['msg'] = $e->getMessage();
            exit(json_encode($result));
        }


         $model->commit();

        //更新店铺信息

        exit(json_encode($result));
    }

    /**
     * 保存批发商分成信息
     */
    private function saveWholesalerRebate($model,$dbPrefix,$user,$total,$rate,$type){
        $money = floatval($total)*(floatval($rate)/100);
        $user_id = $user != null ? $user['user_id'] : 0;
        $re = $model->table($dbPrefix.'wholesaler_rebate')->add(array(
            'user_id'=>$user_id,
            'money'=>$money,
            'type'=>$type,
            'rate'=>$rate,
            'create_time'=>time()
        ));
        if ($user_id > 0 && $re !== false ){
            $re = accountLog($user['user_id'],$money,0,'批发商'.$user['nickname'].'入驻金分佣',$money);
        }
        return $re;
    }
}