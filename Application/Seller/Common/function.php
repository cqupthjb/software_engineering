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
 * Author: 当燃
 * Date: 2015-09-09
 */

/**
 * 管理员操作记录
 * @param $log_url 操作URL
 * @param $log_info 记录信息
 */
function sellerLog($log_info)
{
    $seller = session('seller');
    $add['log_time'] = time();
    $add['log_seller_id'] = $seller['seller_id'];
    $add['log_seller_name'] = $seller['seller_name'];
    $add['log_content'] = $log_info;
    $add['log_seller_ip'] = getIP();
    $add['log_store_id'] = $seller['store_id'];
    $add['log_url'] = __ACTION__;
    M('seller_log')->add($add);
}


function getAdminInfo($admin_id)
{
    return D('admin')->where("admin_id=$admin_id")->find();
}

function tpversion()
{
    if (!empty($_SESSION['isset_push']))
        return false;
    $_SESSION['isset_push'] = 1;
    error_reporting(0);//关闭所有错误报告
    $app_path = dirname($_SERVER['SCRIPT_FILENAME']) . '/';
    $version_txt_path = $app_path . '/Application/Admin/Conf/version.txt';
    $curent_version = file_get_contents($version_txt_path);

    $vaules = array(
        'domain' => $_SERVER['HTTP_HOST'],
        'last_domain' => $_SERVER['HTTP_HOST'],
        'key_num' => $curent_version,
        'install_time' => INSTALL_DATE,
        'cpu' => '0001',
        'mac' => '0002',
        'serial_number' => SERIALNUMBER,
    );
    $url = "http://service.tp-shop.cn/index.php?m=Home&c=Index&a=user_push&" . http_build_query($vaules); // 检测版本升级
    stream_context_set_default(array('http' => array('timeout' => 3)));
    file_get_contents($url);
}

/**
 * 面包屑导航  用于后台管理
 * 根据当前的控制器名称 和 action 方法
 */
function navigate_admin()
{
    $navigate = include APP_PATH . 'Common/Conf/navigate.php';
    $location = strtolower('Seller/' . CONTROLLER_NAME);
    $arr = array(
        '后台首页' => 'javascript:void();',
        $navigate[$location]['name'] => 'javascript:void();',
        $navigate[$location]['action'][ACTION_NAME] => 'javascript:void();',
    );
    return $arr;
}

/**
 * 导出excel
 * @param $strTable    表格内容
 * @param $filename 文件名
 */
function downloadExcel($strTable, $filename)
{
    header("Content-type: application/vnd.ms-excel");
    header("Content-Type: application/force-download");
    header("Content-Disposition: attachment; filename=" . $filename . "_" . date('Y-m-d') . ".xls");
    header('Expires:0');
    header('Pragma:public');
    echo '<html><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />' . $strTable . '</html>';
}

/**
 * 格式化字节大小
 * @param  number $size 字节数
 * @param  string $delimiter 数字和单位分隔符
 * @return string            格式化后的带单位的大小
 */
function format_bytes($size, $delimiter = '')
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    for ($i = 0; $size >= 1024 && $i < 5; $i++) $size /= 1024;
    return round($size, 2) . $delimiter . $units[$i];
}

/**
 * 根据id获取地区名字
 * @param $regionId id
 */
function getRegionName($regionId)
{
    $data = M('region')->where(array('id' => $regionId))->field('name')->find();
    return $data['name'];
}

function respose($res)
{
    header('Content-type:text/json');
    exit(json_encode($res));
}

function getMenuList($role_id=0)
{
    switch ($role_id){
        case '1':
            $menu_list = array(
                'store' => array('name' => '个人资料', 'icon' => 'fa-cog', 'child' => array(
                    array('name' => '店铺设置', 'act' => 'store_setting', 'op' => 'Store'),
                    array('name' => '店铺列表', 'act'=>'store_list', 'op'=>'StoreExt'),
                    array('name' => '入驻日志', 'act'=>'log', 'op'=>'StoreExt'),
                    array('name' => '入驻协议', 'act'=>'agreement', 'op'=>'Store'),
                )),
//                'statistics' => array('name' => '统计报表', 'icon' => 'fa-signal', 'child' => array(
//                    array('name' => '店铺概况', 'act' => 'index', 'op' => 'Report'),
//                    array('name' => '商品分析', 'act' => 'saleTop', 'op' => 'Report'),
//                    array('name' => '运营报告', 'act' => 'finance', 'op' => 'Report'),
//                    array('name' => '销售排行', 'act' => 'saleTop', 'op' => 'Report'),
//                    array('name' => '流量统计', 'act' => 'visit', 'op' => 'Report'),
//                )),
                // http://www.tpshop.com/Admin/Distribut/remittance
                /**/
                // http://www.tp-shop.cn/Admin/Distribut/rebate_log     /index.php/Seller/Store/distribut
                'distribut' => array('name' => '我的分享', 'icon' => 'fa-cubes', 'child' => array(
                    //array('name' => '分销商品', 'act' => 'goods_list', 'op' => 'Distribut'),
                    //array('name' => '分销设置', 'act' => 'store_setting', 'op' => 'Store'),
                    array('name' => '推广地级二维码', 'act' => 'dis_code', 'op' => 'StoreExt'),
                    array('name' => '分销关系', 'act' => 'tree', 'op' => 'StoreExt'),
                    array('name' => '卡券分成', 'act' => 'rebate_log', 'op' => 'Card'),
                    array('name' => '订单分成', 'act' => 'rebate_log', 'op' => 'Distribut'),
                )),
                'finance' => array('name' => '财务管理', 'icon' => 'fa-book', 'child' => array(
                    //array('name' => '提现申请', 'act' => 'withdrawals', 'op' => 'Finance'),
                    //array('name' => '汇款记录', 'act' => 'remittance', 'op' => 'Finance'),
                    //array('name' => '商家结算记录', 'act' => 'order_statis', 'op' => 'Finance'),
                    //array('name' => '未结算订单', 'act' => 'order_no_statis', 'op' => 'Finance'),
                    array('name' => '提现申请', 'act' => 'withdrawals', 'op' => 'UserExt'),
                    array('name' => '银行信息', 'act' => 'bank_list', 'op' => 'UserExt'),
                )),
                'statistics' => array('name' => '统计报表', 'icon' => 'fa-signal', 'child' => array(
                    array('name' => '卡券分成', 'act' => 'card_rebate_log', 'op' => 'Report'),
                )),

            );
            break;
        case '2':
            $menu_list = array(
                'store' => array('name' => '个人资料', 'icon' => 'fa-cog', 'child' => array(
                    array('name' => '店铺设置', 'act' => 'store_setting', 'op' => 'Store'),
                    array('name' => '店铺列表', 'act'=>'store_list', 'op'=>'StoreExt'),
                    array('name' => '入驻日志', 'act'=>'log', 'op'=>'StoreExt'),
                    array('name' => '入驻协议', 'act'=>'agreement', 'op'=>'Store'),
                )),
//                'statistics' => array('name' => '统计报表', 'icon' => 'fa-signal', 'child' => array(
//                    array('name' => '店铺概况', 'act' => 'index', 'op' => 'Report'),
//                    array('name' => '商品分析', 'act' => 'saleTop', 'op' => 'Report'),
//                    array('name' => '运营报告', 'act' => 'finance', 'op' => 'Report'),
//                    array('name' => '销售排行', 'act' => 'saleTop', 'op' => 'Report'),
//                    array('name' => '流量统计', 'act' => 'visit', 'op' => 'Report'),
//                )),
                // http://www.tpshop.com/Admin/Distribut/remittance
                /**/
                // http://www.tp-shop.cn/Admin/Distribut/rebate_log     /index.php/Seller/Store/distribut
                'distribut' => array('name' => '分销管理', 'icon' => 'fa-cubes', 'child' => array(
                    //array('name' => '分销设置', 'act' => 'store_setting', 'op' => 'Store'),
                    array('name' => '推广县级二维码', 'act' => 'dis_code', 'op' => 'StoreExt'),
                    array('name' => '分销关系', 'act' => 'tree', 'op' => 'StoreExt'),
                    array('name' => '卡券分成', 'act' => 'rebate_log', 'op' => 'Card'),
                    array('name' => '订单分成', 'act' => 'rebate_log', 'op' => 'Distribut'),
                )),
                'finance' => array('name' => '财务管理', 'icon' => 'fa-book', 'child' => array(
                    //array('name' => '提现申请', 'act' => 'withdrawals', 'op' => 'Finance'),
                    //array('name' => '汇款记录', 'act' => 'remittance', 'op' => 'Finance'),
                    //array('name' => '商家结算记录', 'act' => 'order_statis', 'op' => 'Finance'),
                    //array('name' => '未结算订单', 'act' => 'order_no_statis', 'op' => 'Finance'),
                    array('name' => '提现申请', 'act' => 'withdrawals', 'op' => 'UserExt'),
                    array('name' => '银行信息', 'act' => 'bank_list', 'op' => 'UserExt'),
                )),
                'statistics' => array('name' => '统计报表', 'icon' => 'fa-signal', 'child' => array(
                    array('name' => '卡券分成', 'act' => 'card_rebate_log', 'op' => 'Report'),
                )),

            );
            break;
        case '3':
            $menu_list = array(
                'store' => array('name' => '个人资料', 'icon' => 'fa-cog', 'child' => array(
                    array('name' => '店铺设置', 'act' => 'store_setting', 'op' => 'Store'),
                    array('name' => '店铺列表', 'act'=>'store_list', 'op'=>'StoreExt'),
                    array('name' => '入驻日志', 'act'=>'log', 'op'=>'StoreExt'),
                    array('name' => '入驻协议', 'act'=>'agreement', 'op'=>'Store'),
                )),
//                'statistics' => array('name' => '统计报表', 'icon' => 'fa-signal', 'child' => array(
//                    array('name' => '店铺概况', 'act' => 'index', 'op' => 'Report'),
//                    array('name' => '商品分析', 'act' => 'saleTop', 'op' => 'Report'),
//                    array('name' => '运营报告', 'act' => 'finance', 'op' => 'Report'),
//                    array('name' => '销售排行', 'act' => 'saleTop', 'op' => 'Report'),
//                    array('name' => '流量统计', 'act' => 'visit', 'op' => 'Report'),
//                )),
                // http://www.tpshop.com/Admin/Distribut/remittance
                /**/
                // http://www.tp-shop.cn/Admin/Distribut/rebate_log     /index.php/Seller/Store/distribut
                'distribut' => array('name' => '分销管理', 'icon' => 'fa-cubes', 'child' => array(
                    //array('name' => '分销商品', 'act' => 'goods_list', 'op' => 'Distribut'),
                    //array('name' => '分销设置', 'act' => 'store_setting', 'op' => 'Store'),
                    array('name' => '推广分销商二维码', 'act' => 'dis_code', 'op' => 'StoreExt'),
                    array('name' => '分销关系', 'act' => 'tree', 'op' => 'StoreExt'),
                    array('name' => '卡券分成', 'act' => 'rebate_log', 'op' => 'Card'),
                    array('name' => '订单分成', 'act' => 'rebate_log', 'op' => 'Distribut'),
                )),
                'finance' => array('name' => '财务管理', 'icon' => 'fa-book', 'child' => array(
                    //array('name' => '提现申请', 'act' => 'withdrawals', 'op' => 'Finance'),
                    //array('name' => '汇款记录', 'act' => 'remittance', 'op' => 'Finance'),
                    //array('name' => '商家结算记录', 'act' => 'order_statis', 'op' => 'Finance'),
                    //array('name' => '未结算订单', 'act' => 'order_no_statis', 'op' => 'Finance'),
                    array('name' => '提现申请', 'act' => 'withdrawals', 'op' => 'UserExt'),
                    array('name' => '银行信息', 'act' => 'bank_list', 'op' => 'UserExt'),
                )),
                'statistics' => array('name' => '统计报表', 'icon' => 'fa-signal', 'child' => array(
                    array('name' => '卡券分成', 'act' => 'card_rebate_log', 'op' => 'Report'),
                )),

            );
            break;
        case '4':
            $menu_list = array(
                'store' => array('name' => '个人资料', 'icon' => 'fa-cog', 'child' => array(
                    array('name' => '店铺设置', 'act' => 'store_setting', 'op' => 'Store'),
                    array('name' => '店铺列表', 'act'=>'store_list', 'op'=>'StoreExt'),
                    array('name' => '入驻日志', 'act'=>'log', 'op'=>'StoreExt'),
                    array('name' => '入驻协议', 'act'=>'agreement', 'op'=>'Store'),
                )),
//                'statistics' => array('name' => '统计报表', 'icon' => 'fa-signal', 'child' => array(
//                    array('name' => '店铺概况', 'act' => 'index', 'op' => 'Report'),
//                    array('name' => '商品分析', 'act' => 'saleTop', 'op' => 'Report'),
//                    array('name' => '运营报告', 'act' => 'finance', 'op' => 'Report'),
//                    array('name' => '销售排行', 'act' => 'saleTop', 'op' => 'Report'),
//                    array('name' => '流量统计', 'act' => 'visit', 'op' => 'Report'),
//                )),

                'distribut' => array('name' => '分销管理', 'icon' => 'fa-cubes', 'child' => array(
                    //array('name' => '分销商品', 'act' => 'goods_list', 'op' => 'Distribut'),
                    //array('name' => '分销设置', 'act' => 'store_setting', 'op' => 'Store'),
                    array('name' => '推广实体店二维码', 'act' => 'dis_code', 'op' => 'StoreExt'),//店铺入驻二维码
                    array('name' => '分销关系', 'act' => 'tree', 'op' => 'StoreExt'),
                    array('name' => '卡券分成', 'act' => 'rebate_log', 'op' => 'Card'),
                    array('name' => '订单分成', 'act' => 'rebate_log', 'op' => 'Distribut'),

                    array('name' => '推广个人二维码', 'act' => 'qr_code', 'op' => 'StoreExt'),
                )),
                'card' => array('name' => '卡券管理', 'icon'=>'fa-cc-mastercard', 'child' => array(
                    array('name' => '卡券列表', 'act'=>'index', 'op'=>'Card'),
                    array('name' => '我的订单', 'act'=>'order_list', 'op'=>'Card'),
                    array('name' => '收货地址', 'act'=>'address_list', 'op'=>'Card'),
                )),
                'finance' => array('name' => '财务管理', 'icon' => 'fa-book', 'child' => array(
                    //array('name' => '提现申请', 'act' => 'withdrawals', 'op' => 'Finance'),
                    //array('name' => '汇款记录', 'act' => 'remittance', 'op' => 'Finance'),
                    //array('name' => '商家结算记录', 'act' => 'order_statis', 'op' => 'Finance'),
                    //array('name' => '未结算订单', 'act' => 'order_no_statis', 'op' => 'Finance'),
                    array('name' => '提现申请', 'act' => 'withdrawals', 'op' => 'UserExt'),
                    array('name' => '银行信息', 'act' => 'bank_list', 'op' => 'UserExt'),
                )),
                'statistics' => array('name' => '统计报表', 'icon' => 'fa-signal', 'child' => array(
                    array('name' => '卡券分成', 'act' => 'card_rebate_log', 'op' => 'Report'),
                )),
            );
            break;
        case '5':
            $menu_list = array(
                'store' => array('name' => '店铺管理', 'icon' => 'fa-cog', 'child' => array(
                    array('name' => '店铺设置', 'act' => 'store_setting', 'op' => 'Store'),
                    //array('name' => '店铺装修', 'act' => 'store_decoration', 'op' => 'Store'),
                    //array('name' => '店铺导航', 'act' => 'navigation_list', 'op' => 'Store'),
                    array('name' => '经营类目', 'act' => 'bind_class_list', 'op' => 'Store'),
                    //array('name' => '店铺信息', 'act' => 'store_info', 'op' => 'Store'),
                    array('name' => '商品分类', 'act' => 'goods_class', 'op' => 'Store'),
                    array('name' => '店铺关注', 'act' => 'store_collect', 'op' => 'Store'),
                    array('name' => '入驻协议', 'act'=>'agreement', 'op'=>'Store'),
                    array('name' => '收款码', 'act'=>'scan_order', 'op'=>'Store'),
                )),
                'goods' => array('name' => '商品管理', 'icon' => 'fa-tasks', 'child' => array(
                    array('name' => '商品发布', 'act' => 'addEditGoods', 'op' => 'Goods'), ///index.php/Seller/goods/addEditGoods.html'
                    //array('name' => '淘宝导入', 'act'=>'import', 'op'=>'index'),             //临时屏蔽淘宝商品导入
                    array('name' => '出售中的商品', 'act' => 'goodsList?goods_state=1', 'op' => 'Goods'),
                    array('name' => '仓库中的商品', 'act' => 'goodsList?goods_state=0,2,3', 'op' => 'Goods'),
                    //array('name' => '关联版式', 'act'=>'store_plate', 'op'=>'index'),
                    array('name' => '商品规格', 'act' => 'specList', 'op' => 'Goods'),
                    array('name' => '品牌申请', 'act' => 'brandList', 'op' => 'Goods'),
                    //array('name' => '图片空间', 'act'=>'store_album', 'op'=>'album_cate'),
                )),
                'order' => array('name' => '订单物流', 'icon' => 'fa-money', 'child' => array(
                    array('name' => '订单列表', 'act' => 'index', 'op' => 'Order'),
                    array('name' => '发货列表', 'act' => 'delivery_list', 'op' => 'Order'),
                    array('name' => '发货设置', 'act' => 'index', 'op' => 'Plugin'),
                    //array('name' => '运单模板', 'act'=>'store_waybill', 'op'=>'waybill_manage'),
                    array('name' => '商品评论', 'act' => 'index', 'op' => 'Comment'),
                    //array('name' => '商品咨询', 'act' => 'ask_list', 'op' => 'Comment'),
                    array('name' => '收款码订单列表', 'act' => 'scan_order', 'op' => 'Order'),
                )),
//                'card' => array('name' => '卡券管理', 'icon'=>'fa-card', 'sub_menu' => array(
//                    //array('name' => '卡券列表', 'act'=>'index', 'op'=>'Card'),
//                )),
                /*'promotion' => array('name' => '促销管理', 'icon' => 'fa-bell', 'child' => array(
                    //array('name' => '抢购管理', 'act' => 'flash_sale', 'op' => 'Promotion'),
                    //array('name' => '团购管理', 'act' => 'group_buy_list', 'op' => 'Promotion'),
                    array('name' => '商品促销', 'act' => 'prom_goods_list', 'op' => 'Promotion'),
                    //array('name' => '订单促销', 'act' => 'prom_order_list', 'op' => 'Promotion'),
                    //array('name' => '代金券管理', 'act' => 'index', 'op' => 'Coupon'),
                    //array('name' => '分销管理', 'act'=>'store_activity', 'op'=>'promotion'),
                )),*/
                'statistics' => array('name' => '统计报表', 'icon' => 'fa-signal', 'child' => array(
                    array('name' => '店铺概况', 'act' => 'index', 'op' => 'Report'),
                    array('name' => '商品分析', 'act' => 'saleTop', 'op' => 'Report'),
                    array('name' => '运营报告', 'act' => 'finance', 'op' => 'Report'),
                    array('name' => '销售排行', 'act' => 'saleTop', 'op' => 'Report'),
                    //array('name' => '流量统计', 'act' => 'visit', 'op' => 'Report'),
                )),
//                'message' => array('name' => '客服消息', 'icon' => 'fa-comments', 'child' => array(
//                    array('name' => '客服设置', 'act' => 'store_service', 'op' => 'Index'),
//                    array('name' => '系统消息', 'act' => 'store_msg', 'op' => 'Index'),
//                    //array('name' => '聊天记录查询', 'act'=>'store_im', 'op'=>'store'),
//                )),

                // http://www.tpshop.com/Admin/Distribut/remittance
                'finance' => array('name' => '财务管理', 'icon' => 'fa-book', 'child' => array(
                    array('name' => '银行信息', 'act' => 'bank_list', 'op' => 'UserExt'),
                    array('name' => '商户提现', 'act' => 'withdrawals', 'op' => 'Finance'),
                    array('name' => '分佣提现', 'act' => 'withdrawals', 'op' => 'UserExt'),
                    array('name' => '汇款记录', 'act' => 'remittance', 'op' => 'Finance'),
                    array('name' => '商家结算记录', 'act' => 'order_statis', 'op' => 'Finance'),
                    array('name' => '未结算订单', 'act' => 'order_no_statis', 'op' => 'Finance'),
                    array('name' => '收款码记录','act' => 'qrcodeSum', 'op' => 'Finance'),
                )),
                /**/
                // http://www.tp-shop.cn/Admin/Distribut/rebate_log     /index.php/Seller/Store/distribut
                'distribut' => array('name' => '分销管理', 'icon' => 'fa-cubes', 'child' => array(
                    //array('name' => '分销商品', 'act' => 'goods_list', 'op' => 'Distribut'),
                    //array('name' => '分销设置', 'act' => 'distribut', 'op' => 'Store'),
                    array('name' => '分销关系', 'act' => 'tree', 'op' => 'StoreExt'),
                    array('name' => '订单分成', 'act' => 'rebate_log', 'op' => 'Distribut'),
                    array('name' => '推广二维码', 'act' => 'qr_code', 'op' => 'StoreExt'),
                )),
                'consult' => array('name' => '售后服务', 'icon' => 'fa-flag', 'child' => array(
                    array('name' => '咨询管理', 'act' => 'ask_list', 'op' => 'Comment'),
                    //array('name' => '退款记录', 'act'=>'store_refund', 'op'=>'Order'),
                    array('name' => '退款换货', 'act' => 'return_list', 'op' => 'Order'),
                    //array('name' => '投诉管理', 'act' => 'complain_list', 'op' => 'Comment'),
                )),
                'account' => array('name' => '账号管理', 'icon' => 'fa-home', 'child' => array(
                    //array('name' => '账号列表', 'act' => 'index', 'op' => 'Admin'),
                    //array('name' => '账号组', 'act' => 'role', 'op' => 'Admin'),
                    array('name' => '账号日志', 'act' => 'log', 'op' => 'Admin'),
                    //array('name' => '店铺消费', 'act'=>'store_cost', 'op'=>'cost_list'),
                )),

            );
            break;
        default:
            $menu_list = array(
                'store' => array('name' => '店铺管理', 'icon' => 'fa-cog', 'child' => array(
                    array('name' => '店铺设置', 'act' => 'store_setting', 'op' => 'Store'),
                    //array('name' => '店铺装修', 'act' => 'store_decoration', 'op' => 'Store'),
                    //array('name' => '店铺导航', 'act' => 'navigation_list', 'op' => 'Store'),
                    array('name' => '经营类目', 'act' => 'bind_class_list', 'op' => 'Store'),
                    //array('name' => '店铺信息', 'act' => 'store_info', 'op' => 'Store'),
                    array('name' => '商品分类', 'act' => 'goods_class', 'op' => 'Store'),
                    array('name' => '店铺关注', 'act' => 'store_collect', 'op' => 'Store'),
                    //array('name' => '入驻协议', 'act'=>'agreement', 'op'=>'Store'),
                    array('name' => '收款码', 'act'=>'scan_order', 'op'=>'Store'),
                )),
                'goods' => array('name' => '商品管理', 'icon' => 'fa-tasks', 'child' => array(
                    array('name' => '商品发布', 'act' => 'addEditGoods', 'op' => 'Goods'), ///index.php/Seller/goods/addEditGoods.html'
                    //array('name' => '淘宝导入', 'act'=>'import', 'op'=>'index'),             //临时屏蔽淘宝商品导入
                    array('name' => '出售中的商品', 'act' => 'goodsList?goods_state=1', 'op' => 'Goods'),
                    array('name' => '仓库中的商品', 'act' => 'goodsList?goods_state=0,2,3', 'op' => 'Goods'),
                    //array('name' => '关联版式', 'act'=>'store_plate', 'op'=>'index'),
                    array('name' => '商品规格', 'act' => 'specList', 'op' => 'Goods'),
                    array('name' => '品牌申请', 'act' => 'brandList', 'op' => 'Goods'),
                    //array('name' => '图片空间', 'act'=>'store_album', 'op'=>'album_cate'),
                )),
                'order' => array('name' => '订单物流', 'icon' => 'fa-money', 'child' => array(
                    array('name' => '订单列表', 'act' => 'index', 'op' => 'Order'),
                    array('name' => '发货列表', 'act' => 'delivery_list', 'op' => 'Order'),
                    array('name' => '发货设置', 'act' => 'index', 'op' => 'Plugin'),
                    //array('name' => '运单模板', 'act'=>'store_waybill', 'op'=>'waybill_manage'),
                    array('name' => '商品评论', 'act' => 'index', 'op' => 'Comment'),
                    //array('name' => '商品咨询', 'act' => 'ask_list', 'op' => 'Comment'),
                    array('name' => '收款码订单列表', 'act' => 'scan_order', 'op' => 'Order'),
                )),
//                'card' => array('name' => '卡券管理', 'icon'=>'fa-card', 'sub_menu' => array(
//                    //array('name' => '卡券列表', 'act'=>'index', 'op'=>'Card'),
//                )),
//                'promotion' => array('name' => '促销管理', 'icon' => 'fa-bell', 'child' => array(
//                    //array('name' => '抢购管理', 'act' => 'flash_sale', 'op' => 'Promotion'),
//                    //array('name' => '团购管理', 'act' => 'group_buy_list', 'op' => 'Promotion'),
//                    array('name' => '商品促销', 'act' => 'prom_goods_list', 'op' => 'Promotion'),
//                    //array('name' => '订单促销', 'act' => 'prom_order_list', 'op' => 'Promotion'),
//                    //array('name' => '代金券管理', 'act' => 'index', 'op' => 'Coupon'),
//                    //array('name' => '分销管理', 'act'=>'store_activity', 'op'=>'promotion'),
//                )),

                'consult' => array('name' => '售后服务', 'icon' => 'fa-flag', 'child' => array(
                    array('name' => '咨询管理', 'act' => 'ask_list', 'op' => 'Comment'),
                    //array('name' => '退款记录', 'act'=>'store_refund', 'op'=>'Order'),
                    array('name' => '退款换货', 'act' => 'return_list', 'op' => 'Order'),
                    //array('name' => '投诉管理', 'act' => 'complain_list', 'op' => 'Comment'),
                )),
                'statistics' => array('name' => '统计报表', 'icon' => 'fa-signal', 'child' => array(
                    array('name' => '店铺概况', 'act' => 'index', 'op' => 'Report'),
                    array('name' => '商品分析', 'act' => 'saleTop', 'op' => 'Report'),
                    array('name' => '运营报告', 'act' => 'finance', 'op' => 'Report'),
                    array('name' => '销售排行', 'act' => 'saleTop', 'op' => 'Report'),
                    //array('name' => '流量统计', 'act' => 'visit', 'op' => 'Report'),
                )),
//                'message' => array('name' => '客服消息', 'icon' => 'fa-comments', 'child' => array(
//                    array('name' => '客服设置', 'act' => 'store_service', 'op' => 'Index'),
//                    array('name' => '系统消息', 'act' => 'store_msg', 'op' => 'Index'),
//                    //array('name' => '聊天记录查询', 'act'=>'store_im', 'op'=>'store'),
//                )),

                // http://www.tpshop.com/Admin/Distribut/remittance
                'finance' => array('name' => '财务管理', 'icon' => 'fa-book', 'child' => array(
                    array('name' => '银行信息', 'act' => 'bank_list', 'op' => 'UserExt'),
                    array('name' => '商户提现', 'act' => 'withdrawals', 'op' => 'Finance'),
                    array('name' => '分佣提现', 'act' => 'withdrawals', 'op' => 'UserExt'),
                    array('name' => '汇款记录', 'act' => 'remittance', 'op' => 'Finance'),
                    array('name' => '商家结算记录', 'act' => 'order_statis', 'op' => 'Finance'),
                    array('name' => '未结算订单', 'act' => 'order_no_statis', 'op' => 'Finance'),
                )),
                /**/
                // http://www.tp-shop.cn/Admin/Distribut/rebate_log     /index.php/Seller/Store/distribut
                'distribut' => array('name' => '分销管理', 'icon' => 'fa-cubes', 'child' => array(
                    //array('name' => '分销商品', 'act' => 'goods_list', 'op' => 'Distribut'),
                    array('name' => '分销设置', 'act' => 'distribut', 'op' => 'Store'),
                    array('name' => '订单分成', 'act' => 'rebate_log', 'op' => 'Distribut'),
                    array('name' => '推广二维码', 'act' => 'qr_code', 'op' => 'StoreExt'),
                )),
                'account' => array('name' => '账号管理', 'icon' => 'fa-home', 'child' => array(
                    //array('name' => '账号列表', 'act' => 'index', 'op' => 'Admin'),
                    //array('name' => '账号组', 'act' => 'role', 'op' => 'Admin'),
                    array('name' => '账号日志', 'act' => 'log', 'op' => 'Admin'),
                    //array('name' => '店铺消费', 'act'=>'store_cost', 'op'=>'cost_list'),
                )),

            );
            break;
    }

    return $menu_list;
}



