<?php
/**
 * 实体卡券管理
 * By~ Mr-X
 */
namespace Admin\Controller;


use Admin\Logic\CardOrderLogic;
use Think\AjaxPage;
use Think\Exception;
use Think\Page;

class CardController extends BaseController
{
    public  $order_status;
    public  $shipping_status;
    public  $pay_status;
    /*
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
        //C('TOKEN_ON',false); // 关闭表单令牌验证
        // 订单 支付 发货状态
        $this->order_status = C('ORDER_STATUS');
        $this->pay_status = C('PAY_STATUS');
        $this->shipping_status = C('SHIPPING_STATUS');
        $this->assign('order_status',$this->order_status);
        $this->assign('pay_status',$this->pay_status);
        $this->assign('shipping_status',$this->shipping_status);
    }

    #region 卡券管理
    //卡券列表
    public function index(){
        $status = I('type');
        $map = array();
        $map['status'] = $status!='' ? $status : 1 ;
        $list = M('card')->where($map)->order('sort asc , create_time desc')->select();
        $this->assign('list',$list);
        $this->display();
    }

    //新增 | 编辑
    public function info(){
        $card_id = I('card_id');
        if ($card_id>0){
            $row = M('card')->where(array('card_id'=>$card_id))->find();
            $this->assign('row',$row);
        }
        $this->initEditor();
        $this->display();
    }

    /**
     * 初始化编辑器链接
     */
    private function initEditor()
    {
        $this->assign("URL_upload", U('Admin/Ueditor/imageUp',array('savepath'=>'card')));
        $this->assign("URL_fileUp", U('Admin/Ueditor/fileUp',array('savepath'=>'card')));
        $this->assign("URL_scrawlUp", U('Admin/Ueditor/scrawlUp',array('savepath'=>'card')));
        $this->assign("URL_getRemoteImage", U('Admin/Ueditor/getRemoteImage',array('savepath'=>'card')));
        $this->assign("URL_imageManager", U('Admin/Ueditor/imageManager',array('savepath'=>'card')));
        $this->assign("URL_imageUp", U('Admin/Ueditor/imageUp',array('savepath'=>'card')));
        $this->assign("URL_getMovie", U('Admin/Ueditor/getMovie',array('savepath'=>'card')));
        $this->assign("URL_Home", "");
    }

    //存取信息
    public function save_info(){
        try{
            $data = $this->check_info();
            if (isset($data['card_id'])){  //修改
                $res = M('card')->where(array('card_id'=>$data['card_id']))->save($data);
            }else{ //新增
                $data['create_time'] = time();
                $res = M('card')->add($data);
            }
            if ($res === false) throw new Exception('操作失败');
            //$this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
            $this->success('操作成功',U('index'));
        }catch(Exception $e){
            //$this->ajaxReturn(array('status'=>0,'msg'=>$e->getMessage()));
            $this->error($e->getMessage());
        }
    }

    //检测信息
    protected function check_info(){
        $data = I('post.');
        if (empty($data)) throw new Exception('请填写信息');
        if (empty($data['name'])) throw new Exception('请填写卡券名称');
        #region 重复名称判断
        $map = array();
        $map['name'] = $data['name'];
        if (isset($data['card_id']))
            $map['card_id'] = array('neq',$data['card_id']);
        if (get_count('card',$map))
            throw new Exception('该卡券已存在');
        #endregion
        if (empty($data['cover'])) throw new Exception('请填上传卡券图片');
        if ($data['price']<1) throw new Exception('请填写正确的卡券价格');
        if ($data['rate']<0) throw new Exception('请填写正确的佣金比例');
        //if ($data['distribut']<0) throw new Exception('分佣金额大于等于0，为0时为计算的为总金额');
        if ($data['first_rate']<0) throw new Exception('请填写正确的平台分佣比例');
        if ($data['second_rate']<0) throw new Exception('请填写正确的省级分佣比例');
        if ($data['third_rate']<0) throw new Exception('请填写正确的地级分佣比例');
        if ($data['fourth_rate']<0) throw new Exception('请填写正确的县级分佣比例');
        if ($data['third_rate']<0) throw new Exception('请填写正确的分销商分佣比例');
        if ($data['fifth_rate']<0) throw new Exception('请填写正确的库存');
        if ($data['is_free_shipping'] == 1 && $data['postage']<0) throw new Exception('请填写正确的邮费');

        return $data;
    }

    //删除
    public function remove(){
        $card_id = I('id');
        $map = array('card_id'=>$card_id);
        //$status = M('card')->where($map)->getField('status');
        //if ($status == 1) //为1 假删除
            $res = M('card')->where($map)->setField('status',0);
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
        $res = M('card')->where($map)->setField('status',$value);
        if ($res === false)
            $this->ajaxReturn(array('status'=>0,'msg'=>'操作失败'));
        else
            $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
    }

    #endregion

    #region 订单管理
    //订单列表
    public function order_list(){
        $begin = date('Y/m/d',(time()-30*60*60*24));//30天前
        $end = date('Y/m/d',strtotime('+1 days'));
        $this->assign('timegap',$begin.'-'.$end);
        $this->display();
    }

    //ajax 分页列表
    public function ajax_order_list(){
        $orderLogic = new CardOrderLogic();
        $timegap = I('timegap');
        if($timegap){
            $gap = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end = strtotime($gap[1]);
        }
        // 搜索条件 STORE_ID
        $condition = array();
        I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
        if($begin && $end){
            $condition['create_time'] = array('between',"$begin,$end");
        }
        $store_name = I('store_name','','trim');
        if($store_name)
        {
            $store_id_arr = M('store')->where("store_name like '%$store_name%'")->getField('store_id',true);
            if($store_id_arr)
            {
                $condition['store_id'] = array('in',$store_id_arr);
            }
        }

        I('order_sn') ? $condition['order_sn'] = trim(I('order_sn')) : false;
        I('order_status') != '' ? $condition['order_status'] = I('order_status') : false;
        I('pay_status') != '' ? $condition['pay_status'] = I('pay_status') : false;
        I('pay_code') != '' ? $condition['pay_code'] = I('pay_code') : false;
        I('shipping_status') != '' ? $condition['shipping_status'] = I('shipping_status') : false;

        $sort_order = I('order_by','DESC').' '.I('sort');
        $count = M('card_order')->where($condition)->count();
        $Page  = new AjaxPage($count,20);
        $show = $Page->show();
        //获取订单列表
        $orderList = $orderLogic->getOrderList($condition,$sort_order,$Page->firstRow,$Page->listRows);
        $this->assign('orderList',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->display('_order_list');
    }

    //Excel导出
    public function export_order()
    {
        //搜索条件
        $where = 'where 1=1 ';
        $consignee = I('consignee');
        if($consignee){
            $where .= "AND consignee like '%$consignee%' ";
        }
        $order_sn =  I('order_sn');
        if($order_sn){
            $where .= "AND order_sn = '$order_sn' ";
        }
        if(I('order_status')){
            $where .= "AND order_status = ".I('order_status');
        }

        $timegap = I('timegap');
        if($timegap){
            $gap = explode('-', $timegap);
            $begin = strtotime($gap[0]);
            $end = strtotime($gap[1]);
            $where .= "AND create_time>$begin and create_time<$end";
        }
        $region	= M('region')->getField('id,name');

        $sql = "select *,FROM_UNIXTIME(create_time,'%Y-%m-%d') as create_time from __PREFIX__card_order $where order by order_id";
        $orderList = D()->query($sql);
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">日期</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货地址</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">电话</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">实际支付</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付方式</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">发货状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品信息</td>';
        $strTable .= '</tr>';

        foreach($orderList as $k=>$val){
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['create_time'].' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['consignee'].'</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">'."{$region[$val['province']]},{$region[$val['city']]},{$region[$val['district']]},{$region[$val['twon']]}{$val['address']}".' </td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].'</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['need_pay'].'</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['total_pay'].'</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_name'].'</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">'.$this->pay_status[$val['pay_status']].'</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">'.$this->shipping_status[$val['shipping_status']].'</td>';
            $orderGoods = M('card_suborder')->where('order_id='.$val['order_id'])->select();
            $strGoods="";
            foreach($orderGoods as $goods){
                $card_name = $goods['card_name'] ? $goods['card_name'] : get_column('card',array('card_id'=>$goods['card_id']),'name');
                $strGoods .= "卡券编号：".$goods['card_id']." 商品名称：".$card_name;
                $strGoods .= "<br />";
            }
            unset($orderGoods);
            $strTable .= '<td style="text-align:left;font-size:12px;">'.$strGoods.' </td>';
            $strTable .= '</tr>';
        }
        $strTable .='</table>';
        unset($orderList);
        downloadExcel($strTable,'card_order');
        exit();
    }

    /**
     * 订单详情
     * @param int $order_id 订单id
     */
    public function detail($order_id){
        $orderLogic = new CardOrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $orderCards = $orderLogic->getOrderCards($order_id);
        $button = $orderLogic->getOrderButton($order);
        // 获取操作记录
        $action_log = M('card_order_action')->where(array('order_id'=>$order_id))->order('log_time desc')->select();
        $this->assign('order',$order);
        $this->assign('action_log',$action_log);
        $this->assign('orderCards',$orderCards);
        $split = count($orderCards) >1 ? 1 : 0;
        foreach ($orderCards as $val){
            if($val['card_num']>1){
                $split = 1;
            }
        }
        #region 发货记录
        if (get_column('card_delivery_doc',array('order_id'=>$order_id)) > 0){
            $delivery_record = M('card_delivery_doc')->join('LEFT JOIN __SELLER__ ON __SELLER__.seller_id = __CARD_DELIVERY_DOC__.admin_id')->where(array('order_id'=>$order_id))->select();
            $this->assign('delivery_record',$delivery_record);//发货记录
        }
        #endregion
        $this->assign('split',$split);
        $this->assign('button',$button);
        $this->display();
    }

    /**
     * 订单操作
     */
    public function order_action(){
        $orderLogic = new CardOrderLogic();
        $action = I('get.type');
        $order_id = I('get.order_id');
        if($action && $order_id){
            $a = $orderLogic->orderProcessHandle($order_id,$action);
            $admin_id = session('admin_id'); // 当前操作的管理员
            $res = $orderLogic->orderActionLog($order_id,get_card_order_action_name($action),I('note'),$admin_id);
            if($res!==false && $a!==false){
                if ($action == 'remove')
                    $this->ajaxReturn(array('status' => 1,'msg' => '操作成功','data'=>array('url'=>U('order_list'))));
                if ($action == 'delivery_confirm')
                    $this->ajaxReturn($a);
                $this->ajaxReturn(array('status' => 1,'msg' => '操作成功'));
            }else{
                $this->ajaxReturn(array('status' => 0,'msg' => '操作失败'));
            }
        }else{
            $this->error('参数错误',U('detail',array('order_id'=>$order_id)));
        }
    }

    /**
     * 订单编辑
     * @param int $id 订单id
     */
    public function edit_order(){
        $order_id = I('order_id');
        $orderLogic = new CardOrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        if($order['shipping_status'] != 0){
            $this->error('已发货订单不允许编辑');
            exit;
        }
        //获取购买的卡券
        $orderCards = $orderLogic->getOrderCards($order_id);

        if(IS_POST)
        {
            $order['consignee'] = I('consignee');// 收货人
            $order['province'] = I('province'); // 省份
            $order['city'] = I('city'); // 城市
            $order['district'] = I('district'); // 县
            $order['address'] = I('address'); // 收货地址
            $order['mobile'] = I('mobile'); // 手机
            //$order['invoice_title'] = I('invoice_title');// 发票
            $order['admin_note'] = I('admin_note'); // 管理员备注
            $order['admin_note'] = I('admin_note'); //
            $order['shipping_code'] = I('shipping');// 物流方式
            $order['shipping_name'] = M('plugin')->where(array('status'=>1,'type'=>'shipping','code'=>I('shipping')))->getField('name');
            $order['pay_code'] = I('payment');// 支付方式
            $order['pay_name'] = M('plugin')->where(array('status'=>1,'type'=>'payment','code'=>I('payment')))->getField('name');

            $order_id = I('order_id');
            $res = M('card_order')->where(array('order_id'=>$order_id))->save($order);
            if ($res === false) $this->success('修改失败',U('detail',array('order_id'=>$order_id)));
            #region 废弃代码
            /*$card_ids = I("ids");
                $new_card = $old_cards = array();
                //################################订单添加商品
                if($card_ids){
                    $new_card = $orderLogic->get_cards($card_ids);
                    foreach($new_card as $key => $val)
                    {
                        $val['order_id'] = $order_id;
                        $sub_id = M('card_suborder')->add($val);//订单添加商品
                        if(!$sub_id)
                            $this->error('添加失败');
                    }
                }

                //################################订单修改删除商品
                $old_card = I('old_cards');
                foreach ($orderCards as $val){
                    if(empty($old_card[$val['sub_id']])){
                        M('card_suborder')->where("sub_id=".$val['sub_id'])->delete();//删除商品
                    }else{
                        //修改商品数量
                        if($old_card[$val['sub_id']] != $val['card_num']){
                            $val['card_num'] = $old_card[$val['sub_id']];
                            M('card_suborder')->where("sub_id=".$val['sub_id'])->save(array('card_num'=>$val['card_num']));
                        }
                        $old_cards[] = $val;
                    }
                }

                $goodsArr = array_merge($old_cards,$new_card);
                $result = calculate_price($order['user_id'],$goodsArr,$order['shipping_code'],0,$order['province'],$order['city'],$order['district'],0,0,0,0);
                if($result['status'] < 0)
                {
                    $this->error($result['msg']);
                }
                //################################修改订单费用
                $order['goods_price']    = $result['result']['goods_price']; // 商品总价
                $order['shipping_price'] = $result['result']['shipping_price'];//物流费
                $order['order_amount']   = $result['result']['order_amount']; // 应付金额
                $order['total_amount']   = $result['result']['total_amount']; // 订单总价
                $o = M('order')->where('order_id='.$order_id)->save($order);
             */
            #endregion


            $admin_id = session('admin_id'); // 当前操作的管理员
            $l = $orderLogic->orderActionLog($order_id,'edit','修改订单',$admin_id);//操作日志
            if($l){
                $this->success('修改成功',U('detail',array('order_id'=>$order_id)));//editprice
            }else{
                $this->success('修改失败',U('detail',array('order_id'=>$order_id)));
            }
            exit;
        }
        // 获取省份
        $province = M('region')->where(array('parent_id'=>0,'level'=>1))->select();
        //获取订单城市
        $city =  M('region')->where(array('parent_id'=>$order['province'],'level'=>2))->select();
        //获取订单地区
        $area =  M('region')->where(array('parent_id'=>$order['city'],'level'=>3))->select();
        //获取支付方式
        $payment_list = M('plugin')->where(array('status'=>1,'type'=>'payment'))->select();
        //获取配送方式
        $shipping_list = M('plugin')->where(array('status'=>1,'type'=>'shipping'))->select();

        $this->assign('order',$order);
        $this->assign('province',$province);
        $this->assign('city',$city);
        $this->assign('area',$area);
        $this->assign('orderCards',$orderCards);
        $this->assign('shipping_list',$shipping_list);
        $this->assign('payment_list',$payment_list);
        $this->display();
    }

    /*
     * 价钱修改
     */
    public function editprice($order_id){
        $orderLogic = new CardOrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $this->editable($order);
        if(IS_POST){
            $admin_id = session('admin_id');
            if(empty($admin_id)){
                $this->error('非法操作');
                exit;
            }
            $update['discount'] = I('post.discount');
            $update['shipping_price'] = I('post.shipping_price');
            $update['order_amount'] = $order['goods_price'] + $update['shipping_price'] - $update['discount'] - $order['user_money'] - $order['integral_money'] - $order['coupon_price'];
            $row = M('order')->where(array('order_id'=>$order_id))->save($update);
            if(!$row){
                $this->success('没有更新数据',U('editprice',array('order_id'=>$order_id)));
            }else{
                $this->success('操作成功',U('detail',array('order_id'=>$order_id)));
            }
            exit;
        }
        $this->assign('order',$order);
        $this->display();
    }

    /**
     * 订单删除
     * @param int $id 订单id
     */
    public function delete_order($order_id){
        $orderLogic = new CardOrderLogic();
        $del = $orderLogic->delOrder($order_id);
        if($del){
            $this->success('删除订单成功');
        }else{
            $this->error('订单删除失败');
        }
    }

    /**
     * 订单取消付款
     */
    public function pay_cancel($order_id){
        if(I('remark')){
            $data = I('post.');
            $note = array('退款到用户余额','已通过其他方式退款','不处理，误操作项');
            if($data['refundType'] == 0 && $data['amount']>0){
                accountLog($data['user_id'], $data['amount'], 0,  '退款到用户余额');
            }
            $orderLogic = new CardOrderLogic();
            $admin_id = session('admin_id'); // 当前操作的管理员
            $d = $orderLogic->orderActionLog($data['order_id'],'pay_cancel',$data['remark'].':'.$note[$data['refundType']],$admin_id);
            if($d){
                exit("<script>window.parent.pay_callback(1);</script>");
            }else{
                exit("<script>window.parent.pay_callback(0);</script>");
            }
        }else{
            $order = M('card_order')->where(array('order_id'=>$order_id))->find();
            $this->assign('order',$order);
            $this->display();
        }
    }

    /**
     * 订单打印
     * @param int $id 订单id
     */
    public function order_print($order_id){
        $orderLogic = new CardOrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $order['province'] = getRegionName($order['province']);
        $order['city'] = getRegionName($order['city']);
        $order['district'] = getRegionName($order['district']);
        $order['full_address'] = $order['province'].' '.$order['city'].' '.$order['district'].' '. $order['address'];
        $orderCards = $orderLogic->getOrderCards($order_id);
        $shop = tpCache('shop_info');
        $this->assign('order',$order);
        $this->assign('shop',$shop);
        $this->assign('orderCards',$orderCards);
        $this->display('print');
    }

    /**
     * 快递单打印
     */
    public function shipping_print(){
        $order_id = I('get.order_id');
        $orderLogic = new CardOrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        //查询是否存在订单及物流
        $shipping = M('plugin')->where(array('code'=>$order['shipping_code'],'type'=>'shipping'))->find();
        if(!$shipping){
            $this->error('物流插件不存在');
        }
        if(empty($shipping['config_value'])){
            $this->error('请设置'.$shipping['name'].'打印模板');
        }
        $shop = tpCache('shop_info');//获取网站信息
        $shop['province'] = empty($shop['province']) ? '' : getRegionName($shop['province']);
        $shop['city'] = empty($shop['city']) ? '' : getRegionName($shop['city']);
        $shop['district'] = empty($shop['district']) ? '' : getRegionName($shop['district']);

        $order['province'] = getRegionName($order['province']);
        $order['city'] = getRegionName($order['city']);
        $order['district'] = getRegionName($order['district']);
        if(empty($shipping['config'])){
            $config = array('width'=>840,'height'=>480,'offset_x'=>0,'offset_y'=>0);
            $this->assign('config',$config);
        }else{
            $this->assign('config',unserialize($shipping['config']));
        }
        $template_var = array("发货点-名称", "发货点-联系人", "发货点-电话", "发货点-省份", "发货点-城市",
            "发货点-区县", "发货点-手机", "发货点-详细地址", "收件人-姓名", "收件人-手机", "收件人-电话",
            "收件人-省份", "收件人-城市", "收件人-区县", "收件人-邮编", "收件人-详细地址", "时间-年", "时间-月",
            "时间-日","时间-当前日期","订单-订单号", "订单-备注","订单-配送费用");
        $content_var = array($shop['store_name'],$shop['contact'],$shop['phone'],$shop['province'],$shop['city'],
            $shop['district'],$shop['phone'],$shop['address'],$order['consignee'],$order['mobile'],$order['phone'],
            $order['province'],$order['city'],$order['district'],$order['zipcode'],$order['address'],date('Y'),date('M'),
            date('d'),date('Y-m-d'),$order['order_sn'],$order['admin_note'],$order['shipping_price'],
        );
        $shipping['config_value'] = str_replace($template_var,$content_var, $shipping['config_value']);
        $this->assign('shipping',$shipping);
        $this->display("Plugin/print_express");
    }

    /**
     * 生成发货单
     */
    public function deliveryHandle(){
        $orderLogic = new CardOrderLogic();
        $data = I('post.');
        $res = $orderLogic->deliveryHandle($data);
        if($res!==false){
            $this->success('操作成功',U('delivery_info',array('order_id'=>$data['order_id'])));
        }else{
            $this->error('操作失败',U('delivery_info',array('order_id'=>$data['order_id'])));
        }
    }

    //发货单
    public function delivery_info(){
        $order_id = I('order_id');
        $orderLogic = new CardOrderLogic();
        $order = $orderLogic->getOrderInfo($order_id);
        $orderCards = $orderLogic->getOrderCards($order_id);
        $this->assign('order',$order);
        $this->assign('orderCards',$orderCards);
        $delivery_record = M('card_delivery_doc')->join('LEFT JOIN __SELLER__ ON __SELLER__.seller_id = __CARD_DELIVERY_DOC__.admin_id')->where('order_id='.$order_id)->select();
        $this->assign('delivery_record',$delivery_record);//发货记录
        $this->display();
    }

    /**
     * 发货单列表
     */
    public function delivery_list(){
        $this->display();
    }

    /*
     * ajax 发货订单列表
    */
    public function ajaxdelivery(){
        $orderLogic = new CardOrderLogic();
        $condition = array();
        I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
        I('order_sn') != '' ? $condition['order_sn'] = trim(I('order_sn')) : false;
        $condition['order_status'] = array('in','1,2,4');
        $shipping_status = I('shipping_status');
        $condition['shipping_status'] = empty($shipping_status) ? array('neq',1) : $shipping_status;
        $count = M('card_order')->where($condition)->count();
        $Page  = new AjaxPage($count,10);
        //搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }
        $show = $Page->show();
        $orderList = M('card_order')->where($condition)->limit($Page->firstRow.','.$Page->listRows)->order('create_time DESC')->select();
        $this->assign('orderList',$orderList);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

    /*
     * ajax 退货订单列表
     */
    public function ajax_return_list(){
        // 搜索条件
        $order_sn =  trim(I('order_sn'));
        $order_by = I('order_by') ? I('order_by') : 'id';
        $sort_order = I('sort_order') ? I('sort_order') : 'desc';
        $status =  I('status');

        $where = " 1 = 1 ";
        $order_sn && $where.= " and order_sn like '%$order_sn%' ";
        empty($order_sn) && $where.= " and status = '$status' ";
        $count = M('return_goods')->where($where)->count();
        $Page  = new AjaxPage($count,13);
        $show = $Page->show();
        $list = M('return_goods')->where($where)->order("$order_by $sort_order")->limit("{$Page->firstRow},{$Page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if(!empty($goods_id_arr))
            $goods_list = M('goods')->where("goods_id in (".implode(',', $goods_id_arr).")")->getField('goods_id,goods_name');
        $store_list = M('store')->getField('store_id,store_name');
        $this->assign('store_list',$store_list);
        $this->assign('goods_list',$goods_list);
        $this->assign('list',$list);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }


    #endregion

    #region 分成管理
    //分成日志列表
    public function rebate_log()
    {
        $model = M("card_rebate_log");
        $status = I('status');
        $user_id = I('user_id');
        $order_sn = I('order_sn');
        $create_time = I('create_time');
        $create_time = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));

        $create_time2 = explode(' - ',$create_time);
        $where = " create_time >= '".strtotime($create_time2[0])."' and create_time <= '".strtotime($create_time2[1])."' ";

        if($status === '0' || $status > 0)
            $where .= " and status = $status ";
        $user_id && $where .= " and user_id = $user_id ";
        $order_sn && $where .= " and order_sn like '%{$order_sn}%' ";

        $count = $model->where($where)->count();
        $Page  = new Page($count,16);
        $list = $model->where($where)->order("`id` desc")->limit($Page->firstRow.','.$Page->listRows)->select();

        $get_user_id = get_arr_column($list, 'user_id'); // 获佣用户
        $buy_user_id = get_arr_column($list, 'user_id'); // 购买用户
        $user_id_arr = array_merge($get_user_id,$buy_user_id);
        if(!empty($user_id_arr))
            $user_arr = M('users')->where("user_id in (".  implode(',', $user_id_arr).")")->select();
        $this->assign('user_arr',$user_arr);

        $this->assign('create_time',$create_time);
        $show  = $Page->show();
        $this->assign('show',$show);
        $this->assign('list',$list);
        C('TOKEN_ON',false);
        $this->display();
    }
    #endregion
}