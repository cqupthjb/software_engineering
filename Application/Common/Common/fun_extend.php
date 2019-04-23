<?php
//By ~ Mr-X
//非通用
if (!function_exists('get_next_role_id')){
    //获取店铺下级角色id
    function get_next_role_id($role_id=''){
        switch ($role_id){
            case 1:return 2;break;  //1：省级
            case 2:return 3;break;  //2：地级
            case 3:return 4;break;  //3：县级
            case 4:return 5;break;  //4：分销商
            case 5:return 0;break;  //5：实体店
        }
    }
}

if (!function_exists('get_role_initials')){
    //获取店铺角色首字母
    function get_role_initials($role_id=''){
        switch ($role_id){
            case 1:return ' S';break;  //1：省级
            case 2:return ' D';break;  //2：地级
            case 3:return ' X';break;  //3：县级
            case 4:return ' F';break;  //4：分销商
            case 5:return ' ST';break;  //5：实体店
            default:return ' ';break;
        }
    }
}

//获取购物未支付 发送 客服信息 开关
if (!function_exists('get_is_send_shopping_none')){
    function get_is_send_shopping_none(){
        return false;
    }
}

if (!function_exists('save_user_news')){
    /**
     * 保存用户信息
     * @param int $user_id      用户id
     * @param int $msg_type     消息类型    0：订单信息，1：提现信息，2：分佣信息'
     * @param int $_id          消息对应id
     * @param string $content   消息内容
     * @return bool;            返回值
     */
    function save_user_news($user_id=0,$msg_type=0,$_id=0,$content=''){
        if (empty($user_id) || empty($content))
            return false;
        $data = array(
            'user_id'   =>  $user_id,
            'msg_type'  =>  $msg_type,
            'content'   =>  $content,
            'create_time'=> time(),
        );
        //region消息id绑定
        switch ($msg_type){
            case '0' :
                $data['order_id'] = $_id;
                break;
            case '1':
                $data['cash_id'] = $_id;
                break;
            case '2':
                $data['rebate_id'] = $_id;
                break;
            default :
                $data['other_id'] = $_id;
        }
        //endregion

        return M('user_news')->add($data);
    }
}

if (!function_exists('get_list_group')){
    /**
     * 根据n条记录进行分组组合
     * @param $list     列表
     * @param $group    分组数量
     * @return array    数据
     */
    function get_list_group($list, $group)
    {
        $length = ceil(count($list) / $group);
        $array = array();
        for ($i = 0; $i < $length; $i++) {
            $array[] = array_slice($list, $i * $group, $group);
        }
        return $array;
    }
}

if (!function_exists('confirm_card_order')){
    /**
     * 确认订单
     * @param $order_id 订单id
     * @param int $user_id  用户id
     * @return array
     */
    function confirm_card_order($order_id,$user_id = 0){
        $where = array('order_id'=>$order_id);
        if ($user_id)
            $where['user_id'] = $user_id;
        $order = M('card_order')->where($where)->find();

        if($order['order_status'] != 1)
            return array('status'=>-1,'msg'=>'该订单不能收货确认');

        $data['order_status'] = 2; // 已收货
        $data['pay_status'] = 1; // 已付款
        $data['confirm_time'] = time(); //  收货确认时间
        if($order['pay_code'] == 'cod'){
            $data['pay_time'] = time();
        }
        $row = M('card_order')->where(array('order_id'=>$order_id))->save($data);
        if(!$row)
            return array('status'=>-3,'msg'=>'操作失败');

        //分销设置
        M('card_rebate_log')->where("order_id = $order_id and status < 4")->save(array('status'=>3,'confirm'=>time()));

        return array('status'=>1,'msg'=>'操作成功');
    }
}

if (!function_exists('get_cart_by_cookie')){
    //获取cookie中的购物车信息
    function get_cart_by_cookie($name=''){
        //读取cookie
        $cartCookie = cookie($name);
        //解码cookie数据
        $cartCookie = json_decode($cartCookie, true);
        //过滤空数据
        array_filter($cartCookie);
        return $cartCookie;
    }
}

//onethink封装函数
if (!function_exists('msubstr')){
    /**
     * 字符串截取，支持中文和其他编码
     * @static
     * @access public
     * @param string $str 需要转换的字符串
     * @param int $start 开始位置
     * @param int $length 截取长度
     * @param string $charset 编码格式
     * @param bool $suffix 截断显示字符
     * @return string
     */
    function msubstr($str='', $start=0, $length=0, $charset="utf-8", $suffix=true) {
        if(function_exists("mb_substr"))
            $slice = mb_substr($str, $start, $length, $charset);
        elseif(function_exists('iconv_substr')) {
            $slice = iconv_substr($str,$start,$length,$charset);
            if(false === $slice) {
                $slice = '';
            }
        }else{
            $re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
            $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
            $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
            $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
            preg_match_all($re[$charset], $str, $match);
            $slice = join("",array_slice($match[0], $start, $length));
        }
        return $suffix ? $slice.'...' : $slice;
    }
}

if (!function_exists('str2arr')){
    /**
     * 字符串转换为数组，主要用于把分隔符调整到第二个参数
     * @param  string $str  要分割的字符串
     * @param  string $glue 分割符
     * @param  bool $is_filter 是否过滤空值
     * @return array
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    function str2arr($str, $glue = ',',$is_filter = false){
        $array = explode($glue, $str);
        if ($is_filter) array_filter($array);
        return $array;
    }
}

if (!function_exists('arr2str')){
    /**
     * 数组转换为字符串，主要用于把分隔符调整到第二个参数
     * @param  array  $arr  要连接的数组
     * @param  string $glue 分割符
     * @return string
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    function arr2str($arr, $glue = ','){
        return implode($glue, $arr);
    }
}

if (!function_exists('arr2_2_str')){
    /**
     * 二维数组转字符串
     * @param $arr
     * @return bool|string
     */
    function arr2_2_str ($arr)
    {
        foreach ($arr as $v)
        {
            $v = join(",",$v); //可以用implode将一维数组转换为用逗号连接的字符串
            $temp[] = $v;
        }
        $t="";
        foreach($temp as $v){
            $t.= $v.",";
        }
        $t=substr($t,0,-1);
        return $t;
    }
}

//通用
if (!function_exists('get_column')){
    /**
     * 获取字段
     * @param string $model     模型名
     * @param array $where     条件|主键
     * @param string $column    获取的字段
     * @param bool $prefix    前缀
     * @return mixed|null
     */
    function get_column($model='',$where=[],$column='',$prefix=false){
        if (empty($model)) return null;
        $map = array();
        if (is_numeric($where)){
            if ($prefix==false) $prefix = strtolower($model).'_';
            $map[$prefix.'id'] = $where;
        }
        else
            $map = $where;
        return M($model)->where($map)->getField($column);
    }
}
if (!function_exists('get_count')){
    /**
     * 根据条件统计数据
     * @param string $model     模型名
     * @param array $where     条件|主键
     * @return mixed|null
     */
    function get_count($model='',$where=[]){
        if (empty($model)) return null;
        $map = array();
        if (!empty($where)){
            if (is_numeric($where)){
                $map['id'] = $where;
            }else{
                $map = $where;
            }
            M($model)->where($map);
        }
        return M($model)->count();
    }
}

if (!function_exists('is_password')){
    /**
     * 检查密码格式 wj 2016-11-16
     * @param $password 密码只允许包含数字、字母组成的6到16位字符
     * @return bool
     */
    function is_password($password){
        if (preg_match('/^[0-9a-zA-Z]{6,16}$/i',$password)){
            return true;
        }else {
            return false;
        }
    }
}

#region 距离换算
/*
 * 经度纬度 转换成距离
 * $lat1 $lng1 是 数据的经度纬度      数据经纬度
 * $lat2,$lng2 是获取定位的经度纬度   用户经纬度
 */
if (!function_exists('getDistanceNone')){
    //距离换算-无单位版
    function getDistanceNone($lat1=0, $lng1=0, $lat2=0, $lng2=0)
    {
        $EARTH_RADIUS = 6378.137;
        $radLat1 = rad($lat1);
        //echo $radLat1;
        $radLat2 = rad($lat2);
        $a = $radLat1 - $radLat2;
        $b = rad($lng1) - rad($lng2);
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2)));
        $s = $s * $EARTH_RADIUS;
        $s = round($s * 10000);
        return $s;
    }
}

if (!function_exists('getDistance')){
    //距离换算-英文单位版
    function getDistance($lat1=0, $lng1=0, $lat2=0, $lng2=0)
    {
        $s = getDistanceNone($lat1, $lng1, $lat2, $lng2);
        $s = $s / 10000;
        if ($s < 1) {
            $s = round($s * 1000);
            $s .= 'm';
        } else {
            $s = round($s, 2);
            $s .= 'km';
        }
        return $s;
    }
}

if (!function_exists('getDistanceCN')){
    //距离换算-中文单位版
    function getDistanceCN($lat1=0, $lng1=0, $lat2=0, $lng2=0)
    {
        $s = getDistanceNone($lat1, $lng1, $lat2, $lng2);
        $s = $s / 10000;
        if ($s < 1) {
            $s = round($s * 1000);
            $s .= '米';
        } else {
            $s = round($s, 2);
            $s .= '千米';
        }
        return $s;
    }
}

if (!function_exists('rad')){
    function rad($d)
    {
        return $d * 3.1415926535898 / 180.0;
    }
}

if (!function_exists('get_distance')){
    /**
     * 获取距离
     * @param string $lat1     纬度
     * @param string $lng1     经度
     * @param string $lat      当前纬度
     * @param string $lng      当前精度
     * @return float|string    距离
     */
    function get_distance($lat1='', $lng1='', $lat='', $lng='')
    {
        if (empty($lat1) || empty($lng1)) return '';
        //获取当前定位坐标
        if(empty($lat) || empty($lng)){
            $lat_lng = get_lat_lng();
            $lat = $lat_lng['lat'];
            $lng = $lat_lng['lng'];
        }

        return getDistance($lat1, $lng1,$lat, $lng);
    }
}
#endregion

#region 获取经纬度

if (!function_exists('get_latlng_by_ip')){
    //根据ip获取经纬度及地址信息
    function get_latlng_by_ip($ip = '', $key = 'VFGBZ-3ZJKG-TYCQX-IOF5J-YWKDZ-J6BKQ'){
        $url = 'http://apis.map.qq.com/ws/location/v1/ip?ip='.$ip.'&key='.$key;
        $data = file_get_contents($url);
        $data = json_decode($data,true);
        if ($data['status']!=0) throw new Exception($data['message']);
        return $data['result']; //$data['result']['ad_info']是地址信息 // $data['result']['location']是经纬度信息
    }
}

if (!function_exists('get_ip')){
    //获取ip
    function get_ip() {
        if (isset($_SERVER["HTTP_CDN_SRC_IP"])) {
            $realip = $_SERVER["HTTP_CDN_SRC_IP"];
        } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/',$_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $realip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $realip = $_SERVER['REMOTE_ADDR'];
        }
        //如果是代理服务器，有可能返回两个IP,这是取第一个即可
        if (stristr($realip, ','))
            $realip = strstr($realip, ',', true);
        return (str_replace('#', '', $realip));
    }
}

#endregion

#region 更改某个字段
if (!function_exists('set_field')){
    /**
     * 更改字段
     * @param string $model 模型
     * @param array $where  条件
     * @param array $column 更改的值数组
     * @return bool
     */
    function set_field($model = '' ,$where = [] ,$column = [] ){
        $map = array();
        if (is_numeric($where)){
            $map[strtolower($model).'_id'] = $where;
        }else{
            $map = $where;
        }
        return M($model)->where($map)->setField($column);
    }
}
#region

#region 时间格式转换
if (!function_exists('format_date')){
    /**
     * 时间格式转换
     * @param string $date  日期
     * @param string $format    格式
     * @return false|string
     */
    function format_date($date='',$format='Y-m-d'){
        return date($format,$date);
    }
}
#endregion

#region 订单辅助
if (!function_exists('build_order_no')){
    /**
     * 生成订单号
     * @param string $name 前缀标识
     * @return string
     */
    function build_order_no($name = '')
    {
        return $name . date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }
}
#endregion

#region 表单辅助
if (!function_exists('get_token')){
    /**
     * 获取token  防止表单重复提交
     * @param string $name      token名
     * @param string $prefix    token前缀 防止和其它重复
     * @return mixed|string
     */
    function get_token($name='',$prefix='_token_'){
        $token = session($prefix.$name);
        if(empty($token)){
            $token = md5(time());
            session($prefix.$name,$token);
        }
        return $token;
    }
}

if (!function_exists('check_token')){
    /**
     * 验证token  防止表单重复提交
     * @param string $name  token名
     * @param string $token token前缀
     * @param string $prefix
     * @return bool
     */
    function check_token($name='',$token='',$prefix='_token_'){
        $session_token = get_token($name,$prefix);
        if($token != $session_token){
            return false;
        }
        return true;
    }
}

if (!function_exists('clear_token')){
    /**
     * 清除token
     * @param string $name      token名
     * @param string $prefix    token前缀
     */
    function clear_token($name='',$prefix='_token_'){
        session($prefix.$name,null);
    }
}
#endregion

#region 数组辅助
if (!function_exists('count_array_num')){
    //统计数组不同元素的个数
    function count_array_num(array $arr = []){
        return count(array_unique($arr));
    }
}
#endregion

#region 工具辅助
if (!function_exists('format_date_ago')){
    //多少时间前
    function format_date_ago($time=1){
        $t=time()-$time;
        $f=array(
            '31536000'=>'年',
            '2592000'=>'个月',
            '604800'=>'星期',
            '86400'=>'天',
            '3600'=>'小时',
            '60'=>'分钟',
            '1'=>'秒'
        );
        foreach ($f as $k=>$v)    {
            if (0 !=$c=floor($t/(int)$k)) {
                return $c.$v.'前';
            }
        }
    }
}

if (!function_exists('get_week')){
    /**
     * 获取星期几
     * @param string $date
     * @return mixed
     */
    function get_week($date='')
    {
        $date_str = date('Y-m-d', strtotime($date));
        $arr = explode("-", $date_str);
        $year = $arr[0];
        $month = sprintf('%02d', $arr[1]);
        $day = sprintf('%02d', $arr[2]);
        $hour = $minute = $second = 0;
        $strap = mktime($hour, $minute, $second, $month, $day, $year);
        $number_wk = date("w", $strap);
        $weekArr = array("星期日", "星期一", "星期二", "星期三", "星期四", "星期五", "星期六");
        return $weekArr[$number_wk];
    }
}

//创建目录
if (!function_exists('dir_create')){
    /**
     * 创建目录
     * @param string $path  路径目录
     * @param int $mode     权限
     * @return bool
     */
    function dir_create($path='', $mode = 0777) {
        if (is_dir($path))
            return true;
        $path = dir_path($path);
        $temp = explode('/', $path);
        $cur_dir = '';
        $max = count($temp) - 1;
        for ($i = 0; $i < $max; $i++) {
            $cur_dir .= $temp[$i] . '/';
            if (@is_dir($cur_dir))
                continue;
            @mkdir($cur_dir, $mode, true);
            @chmod($cur_dir, $mode);
        }
        return is_dir($path);
    }
}

if (!function_exists('dir_path')){
    //转换斜杠
    function dir_path($path='') {
        $path = str_replace('\\', '/', $path);
        if(substr($path, -1) != '/') $path = $path.'/';
        return $path;
    }
}

#endregion

#region curl
if (!function_exists('http_curl')){
    /**
     * 发送HTTP请求方法
     * @param  string $url    请求URL
     * @param  array  $params 请求参数
     * @param  string $method 请求方法GET/POST
     * @return array  $data   响应数据
     * @param array $header
     * @param bool $multi
     * @return mixed
     * @throws Exception
     */
    function http_curl($url='', $params=[], $method = 'GET', $header = array(), $multi = false){
        $opts = array(
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => $header
        );
        /* 根据请求类型设置特定参数 */
        switch(strtoupper($method)){
            case 'GET':
                $opts[CURLOPT_URL] = $url ;
                if (!empty($params)){
                    $opts[CURLOPT_URL].= '?' . http_build_query($params);
                }
                break;
            case 'POST':
                //判断是否传输文件
                $params = $multi ? $params : http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            default:
                throw new \Think\Exception('不支持的请求方式！');
        }
        /* 初始化并执行curl请求 */
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $data  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if($error) throw new \Think\Exception('请求发生错误：' . $error);
        return  $data;
    }
}

if (!function_exists('https_request')){
    function https_request($url,$type='get',$res='json',$data = ''){
        //1.初始化curl
        $curl = curl_init();
        //2.设置curl的参数
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,2);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        if ($type == "post"){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        //3.采集
        $output = curl_exec($curl);
        //4.关闭
        curl_close($curl);
        if ($res == 'json') {
            return json_decode($output,true);
        }
        return $output;
    }
}
#endregion


//region 快递鸟物流api

defined('EBusinessID') or define('EBusinessID', '1303335');
//电商加密私钥，快递鸟提供，注意保管，不要泄漏
defined('KDN_AppKey') or define('KDN_AppKey', 'e8c5bff4-21a2-4fd4-aca5-255cc4385d53');
//请求url
//测试地址
//defined('KDN_ReqURL') or define('KDN_ReqURL', 'http://testapi.kdniao.cc:8081/Ebusiness/EbusinessOrderHandle.aspx');
//正式地址
defined('KDN_ReqURL') or define('KDN_ReqURL', 'http://api.kdniao.cc/Ebusiness/EbusinessOrderHandle.aspx');

if (!function_exists('getOrderTracesByJson')){
    /**
     * Json方式 单号识别
     * @param string $invoice_no
     * @param string $shipper_code
     * @return url响应返回的html
     */
    function getOrderTracesByJson($invoice_no='',$shipper_code=''){
        if (empty($invoice_no) || empty($shipper_code))
            return null;
        $requestData= "{'LogisticCode':'$invoice_no','ShipperCode':'$shipper_code'}";
        $datas = array(
            'EBusinessID' => EBusinessID,
            'RequestType' => '1002',
            'RequestData' => urlencode($requestData) ,
            'DataType' => '2',
        );
        $datas['DataSign'] = encrypt2($requestData, KDN_AppKey);
        $result=sendPost(KDN_ReqURL, $datas);

        //根据公司业务处理返回的信息......

        return $result;
    }
}

if (!function_exists('sendPost')){
    /**
     *  post提交数据
     * @param  string $url 请求Url
     * @param  array $datas 提交的数据
     * @return url响应返回的html
     */
    function sendPost($url, $datas) {
        $temps = array();
        foreach ($datas as $key => $value) {
            $temps[] = sprintf('%s=%s', $key, $value);
        }
        $post_data = implode('&', $temps);
        $url_info = parse_url($url);
        if($url_info['port']=='')
        {
            $url_info['port']=80;
        }

        $httpheader = "POST " . $url_info['path'] . " HTTP/1.0\r\n";
        $httpheader.= "Host:" . $url_info['host'] . "\r\n";
        $httpheader.= "Content-Type:application/x-www-form-urlencoded\r\n";
        $httpheader.= "Content-Length:" . strlen($post_data) . "\r\n";
        $httpheader.= "Connection:close\r\n\r\n";
        $httpheader.= $post_data;
        $fd = fsockopen($url_info['host'], $url_info['port']);
        fwrite($fd, $httpheader);
        $gets = "";
        $headerFlag = true;
        while (!feof($fd)) {
            if (($header = @fgets($fd)) && ($header == "\r\n" || $header == "\n")) {
                break;
            }
        }
        while (!feof($fd)) {
            $gets.= fread($fd, 128);
        }
        fclose($fd);

        return $gets;
    }
}

if (!function_exists('encrypt2')){
    /**
     * 电商Sign签名生成
     * @param data
     * @param appkey Appkey
     * @return string DataSign签名
     */
    function encrypt2($data, $appkey) {
        return urlencode(base64_encode(md5($data.$appkey)));
    }
}

//endregion


//region 生成卡号
if (!function_exists('build_card_no')){
    /**
     * 生成卡号
     * @param string $str   起始字符
     * @param int $len      卡号长度
     * @param string $pad_str
     * @param int $pad_type   STR_PAD_BOTH - 填充字符串的两侧。如果不是偶数，则右侧获得额外的填充。
     *                         STR_PAD_LEFT - 填充字符串的左侧。
     *                         STR_PAD_RIGHT - 填充字符串的右侧。这是默认的。
     * @return string
     */
    function build_card_no($str='',$len=8,$pad_str = '0',$pad_type=STR_PAD_LEFT){
        return str_pad($str,$len,$pad_str,$pad_type);
    }
}
//endregion

//region uninum 唯一数值
if (!function_exists('uninum')){
    /**
     * 获取唯一数值  | 并不保证唯一性
     * @param int $base_num   基数
     * @param int $bit        位数
     * @return bool|string
     */
    function uninum($base_num=0,$bit = 12){
        $num = $base_num . substr(rand(0,9), 0, 1) . substr(strrev(microtime()), 0, 2).rand(100000,999999);
        //$num = substr(rand(), 0, 1) . $base_num . intval(substr(strrev(microtime()), 0, 2))*round(1,9);
        if (strlen($num) < $bit){
            $num = substr($num.$num,0,$bit);
        }else if (strlen($num) > $bit){
            $num = substr($num,0,$bit);
        }
        return $num;
    }
}
//endregion


