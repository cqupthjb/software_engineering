<?php
//By~Mr-X
/**
 * 获取省级限制人数
 * @return int
 */
function get_max_provincial(){
    $provincial = tpCache('distribut.max_provincial');
    return $provincial != '' ? $provincial : 20;
}

/**
 * 店铺二维码分享时间限制
 */
function shop_share_time(){
    $time = tpCache('distribut.shop_share_time');
    return $time != '' ? $time : 0;
}

if (!function_exists('get_role_name')){
    /**
     * 获取角色名
     * @param int $role_id
     * @return mixed|null|string
     */
    function get_role_name($role_id = 0){
        if ($role_id == 0)
            $name = '平台';
        else
            $name = get_column('distribution_role',array('role_id'=>$role_id),'name');
        return $name;
    }
}

if (!function_exists('get_lat_lng')){
    /**
     * 获取纬经度
     * @return array    lat：纬度  lng：经度
     */
    function get_lat_lng()
    {
        $lat = cookie('lat');
        $lng = cookie('lng');
        if (empty($lat) || empty($lng)) {
            //$lat = C('LOCATION_LAT') ? C('LOCATION_LAT') : '29.538410';  //读取默认纬经度
            //$lng = C('LOCATION_LNG') ? C('LOCATION_LNG') : '106.511490'; //公司地址
            $ip = get_client_ip();
            if (substr($ip,0,1) === '0' || substr($ip,0,3) === '192' || substr($ip,0,3) === '127'){
                $ip = '125.86.184.32';
            }
            $location = get_latlng_by_ip($ip);
            $lat = I('lat',$location['location']['lat']);
            $lng = I('lng',$location['location']['lng']);
            cookie('lat', $lat);
            cookie('lng', $lng);
        }
        return array('lat' => $lat, 'lng' => $lng);
    }
}

if (!function_exists('get_list')){
    /**
     * 分页
     * @param string $model 模型
     * @param array $map    条件
     * @param string $order 排序
     * @param int $page  页码
     * @param int $listRows  条数
     * @param string $alias 别名
     * @param string $join  联合查询
     * @param string $field 查询字段
     * @param bool $is_page 是否分页
     * @param string $group 分组
     * @return array
     */
    function get_list($model='', $map=[], $order='', $page=1,$listRows=10, $alias='', $join='', $field='', $is_page=true,$group='')
    {
        $model = M($model);
        if (!empty($alias)) $model->alias($alias);
        if (!empty($map))   $model->where($map);
        if (!empty($group)) {
            $count = $model->field('count(*) as count')->group($group)->select();
            $count = count($count);
        } else{
            $count = $model->count();
        }
        $listRows = $listRows ? $listRows : 10;//获取显示条数
        $page_count = ceil($count / $listRows);   //总页数
        $start = ($page - 1) * $listRows; //开始记录
        $list = [];
        if ($count) {
            if (!empty($alias)) $model->alias($alias);
            if (!empty($join))  $model->join($join);
            if (!empty($map))   $model->where($map);
            if (!empty($field)) $model->field($field);
            if (!empty($order)) $model->order($order);
            if (!empty($group)) $model->group($group);
            if ((empty($is_page) || $is_page==true) && (!empty($page) && $page > 0))
                $model->limit($start . ',' . $listRows);
            $list = $model->select();
        }
        return array('list' => $list, 'count' => $count, 'page_count' => $page_count, 'page' => $page + 1 ,'now_page'=>$page);
    }
}

if (!function_exists('get_column_by_list')){
    /**
     * 获取列表某个字段
     * @param $list
     * @param string $column
     * @param bool $is_unique  是否返回唯一值
     * @return string
     */
    function get_column_by_list($list, $column = 'id',$is_unique=true)
    {
        $ids = array();
        foreach ($list as $key => $vo) {
            $ids[] = $vo[$column];
        }
        array_filter($ids);
        return $is_unique ? array_unique($ids) : $ids;
    }
}

if (!function_exists('get_card_order_action_name')){
    /**
     * @param string $action
     * @return string
     */
    function get_card_order_action_name($action=''){
        switch ($action){
            case 'pay': //付款
                return '付款';
            case 'pay_cancel': //取消付款
                return '取消付款';
            case 'confirm': //确认订单
                return '确认订单';
            case 'cancel': //取消确认
                return '取消确认';
            case 'invalid': //作废订单
                return '作废订单';
            case 'remove': //移除订单
                return '移除订单';
            case 'delivery_confirm'://确认收货
                return '确认收货';
            case 'delivery':
                return '确认发货';
            case 'edit':
                return '修改订单';
        }
        return $action;
    }
}

if (!function_exists('card_order_log')){
    /**
     * 卡券订单日志 依赖于get_column函数
     * @param int $order_id 主订单号
     * @param int $user_id  用户id
     * @param string $action 操作记录
     * @param string $desc  备注
     * @return mixed
     */
    function card_order_log($order_id=0,$user_id=0,$action='',$desc=''){
        $data['order_id'] = $order_id;  //订单号
        $data['action_user'] = $user_id;//用户id
        $data['action_note'] = $action; //操作日志
        $data['order_status'] = get_column('card_order',array('order_id'=>$order_id,'user_id'=>$user_id),'order_status');
        $data['pay_status'] = get_column('card_order',array('order_id'=>$order_id,'user_id'=>$user_id),'pay_status');
        $data['shipping_status'] = get_column('card_order',array('order_id'=>$order_id,'user_id'=>$user_id),'shipping_status');
        $data['log_time'] = time();
        $data['status_desc'] = $desc;//操作备注
        return M('card_order_action')->add($data);//卡券订单操作记录
    }
}

if (!function_exists('store_log')){
    /**
     * * 管理员操作记录
     * @param int $store_id     店铺id
     * @param int $first_leader    上级店铺id
     * @param string $log_info 记录信息
     * @param int $log_type 日志类别
     */
    function store_log($store_id=0,$first_leader=0,$log_info='',$log_type=0){
        $add['log_time'] = time();
        $add['store_id'] = $store_id;
        $add['first_leader'] = $first_leader;
        $add['log_info'] = $log_info;
        $add['log_ip'] = getIP();
        $add['log_url'] = __ACTION__;
        $add['log_type'] = $log_type;
        M('store_log')->add($add);
    }
}



if (!function_exists('check_need_consumption')){
    /**
     * 检测是否满足消费金额，开启分享分佣功能
     * @param $user_id  用户id
     * @return bool
     */
    function check_need_consumption($user_id){
        $reg_time = M('users')->where(array('user_id'=>$user_id))->getField('reg_time');
        $time = '2017-10-20 23:59:59';
        $time = strtotime($time);
        if (intval($reg_time) <= $time) //此时间之前的用户可以享受分享功能
            return true;
        $type = M('config')->where(array('inc_type'=>'distribut','name'=>'dis_type'))->getField('value'); //获取配置信息
        switch ($type){
            case 0://根据消费金额
                    return check_need_con($user_id);
                break;
            case 1://根据充值金额
                    return check_recharge_money($user_id);
                break;
        }
        //默认不限制分享
        return true;
    }
}

if (!function_exists('check_need_con')){
    /**
     * 判断是否满足消费金额  开启分佣分享功能
     * @param $user_id
     * @return bool
     */
    function check_need_con($user_id){
        $check_need_consumption = M('config')->where(array('inc_type'=>'distribut','name'=>'need_consumption'))->getField('value'); //获取配置信息
        if ($check_need_consumption > 0){ //判断满足金额是否大于0
            //1.>>获取用户在自营店的消费金额统计
            //1.1>>获取自营店铺的id
            $store_ids = M('store')->where(array('is_own_shop'=>1))->getField('store_id',true);
            if (empty($store_ids))
                return false;
            //1.2>>统计用户消费金额
            $alias = 'o';
            $map = array();
            $map['o.user_id']       = $user_id;
            $map['o.store_id']      = array('in',$store_ids);
            $map['o.pay_status']    = array('eq',1);//支付状态为1
            $map['o.order_status']  = array('in',array(0,1,2,4));//订单状态为待评价||已评价  || 待确认&已支付  || 已确认
            $join = array('LEFT JOIN __ORDER_GOODS__ as og ON og.order_id = o.order_id');
            $sum = M('order')->alias($alias)->join($join)->where($map)->sum('og.goods_price * og.goods_num');
            if ($sum >= $check_need_consumption)
                return true;
            else
                return false;
        }//否则为不限制分享等功能
        return true;
    }
}

if (!function_exists('check_recharge_money')){
    /**
     * 判断是否满足会员卡充值金额  开启分佣分享功能
     * @param $user_id
     * @return bool
     */
    function check_recharge_money($user_id){
        $need_recharge = M('config')->where(array('inc_type'=>'distribut','name'=>'need_recharge'))->getField('value'); //获取配置信息
        if ($need_recharge > 0){ //判断满足金额是否大于0
            //>>1.查询条件
            $map = array();
            $map['user_id'] = $user_id;
            //>>2.统计充值金额
            $sum = M('user_card_log')->where($map)->sum('money');
            if ($sum >= $need_recharge)
                return true;
            else
                return false;
        }//否则为不限制分享等功能
        return true;
    }
}

if (!function_exists('send_sms')){
    /**
     * 验证码发送
     * @param string $mobile 手机号码
     * @param string $content 发送内容
     * @param string $type 验证码类型
     * @return bool
     */
    function send_sms($mobile='',$content='',$type=''){
        if (empty($mobile)){
            return false;
        }
        $res = is_phone($mobile);//检查手机格式
        if (!$res){//手机格式错误
            return false;
        }
        $sms_config = C('sms_config');
        $username = $sms_config['username'];//用户名
        $password = $sms_config['password'];//密码
        $content_str = $sms_config['content_str'];//提示后缀名
        $url = $sms_config['url'];//提交地址
        $msg=$content .$content_str;
        $port = '';
        $sendtime = time();

        $post_data = "username=".$username
            ."&passwd=".$password
            ."&phone=".$mobile
            ."&msg=".urlencode($msg)
            ."&needstatus=true&port=".$port
            ."&sendtime=".$sendtime;

        $res = curlHttpPost($post_data,$url);
        M('test_msg')->add(array('msg' => json_encode($res).$mobile.'_'.date('Y-m-d H:i:s',time())));
        if ($res){
            return true;
        }else{
            return false;
        }
    }
}
