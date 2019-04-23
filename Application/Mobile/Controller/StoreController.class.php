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
 * Date: 2016-05-28
 */

namespace Mobile\Controller;

use Common\Controller\QrcodeController;
use Home\Logic\StoreLogic;
use Home\Logic\UsersLogic;
use Think\Controller;
use Think\Exception;
use Think\Page;

class StoreController extends MobileBaseController {
	public $store = array();
	
	public function _initialize() {
	    parent::_initialize();
		$store_id = I('store_id');
		if (!in_array(ACTION_NAME,array('collect_list','cancel_collect'))){
            if(empty($store_id)){
                $this->error('参数错误,店铺系列号不能为空',U('Index/index'));
            }
            $store = M('store')->where(array('store_id'=>$store_id))->find();
            if($store){
                if($store['store_state'] == 0){
                    $this->error('该店铺不存在或者已关闭', U('Index/index'));
                }
                $store['mb_slide'] = explode(',', $store['mb_slide']);
                $store['mb_slide_url'] = explode(',', $store['mb_slide_url']);
                $this->store = $store;
                $this->assign('store',$store);
            }else{
                $this->error('该店铺不存在或者已关闭',U('Index/index'));
            }
        }
		if (session('?user')) {
			$user = session('user');
			$this->user_id = $user['user_id'];
			$this->assign('user', $user); //存储用户信息
		}
	}
	
	public function index(){
		//热门商品排行
		$hot_goods = M('goods')->field('goods_content',true)->where(array('store_id'=>$this->store['store_id'],'is_on_sale'=>1))->order('sales_sum desc')->limit(10)->select();
		//新品
		$new_goods = M('goods')->field('goods_content',true)->where(array('store_id'=>$this->store['store_id'],'is_new'=>1,'is_on_sale'=>1))->order('goods_id desc')->limit(10)->select();
		//推荐商品
		$recomend_goods = M('goods')->field('goods_content',true)->where(array('store_id'=>$this->store['store_id'],'is_recommend'=>1,'is_on_sale'=>1))->order('goods_id desc')->limit(10)->select();
		//所有商品
		$total_goods = M('goods')->where(array('store_id'=>$this->store['store_id'],'is_on_sale'=>1))->count();

		$video = M('store_description')->where(array('store_id'=>$this->store['store_id'],'status'=>1,'type'=>2))->find();

		if (!empty($video)){
            $this->assign('video',$video);
        }
		
		$this->assign('hot_goods',$hot_goods);
		$this->assign('new_goods',$new_goods);
		$this->assign('recomend_goods',$recomend_goods);
		$this->assign('total_goods',$total_goods);

		//$total_goods = M('goods')->where(array('store_id'=>$this->store['store_id'],'is_on_sale'=>1))->count();
		//$this->assign('total_goods',$total_goods);
		$this->display();
	}
	
	public function goods_list(){
		$cat_id = I('cat_id', 0);
		$key = I('key', 'is_new');
		$p = I('p', '1');
		$sort = I('sort', 'desc');
		$keywords = I('keywords');
		$map = array('store_id' => $this->store['store_id'], 'is_on_sale' => 1);
		$cat_name = "全部商品";
		if ($cat_id > 0) {
			$map['_string'] = "store_cat_id1=$cat_id OR store_cat_id2=$cat_id";
			$cat_name = M('store_goods_class')->where(array('cat_id' => $cat_id))->getField('cat_name');
		}
		if($keywords){
			$map['goods_name'] = array('like',"%$keywords%");
		}
		$filter_goods_id = M('goods')->where($map)->cache(true)->getField("goods_id", true);
		$count = count($filter_goods_id);
		$page_count = 20;//每页多少个商品
		if ($count > 0) {
			$goods_list = M('goods')->where("is_on_sale=1 and goods_id in (" . implode(',', $filter_goods_id) . ")")->order("$key $sort")->page($p,$page_count)->select();
		}

		$sort = ($sort == 'desc') ? 'asc' : 'desc';
		$this->assign('sort', $sort);
		$this->assign('keys', $key);
		$link_arr = array(
				array('key' => 'is_new', 'name' => '最新', 'url' => U('Store/goods_list', array('store_id' => $this->store['store_id'], 'key' => 'is_new', 'sort' => $sort))),
				array('key' => 'sales_sum', 'name' => '销量', 'url' => U('Store/goods_list', array('store_id' => $this->store['store_id'], 'key' => 'sales_sum', 'sort' => $sort))),
				//array('key' => 'collect_sum', 'name' => '收藏', 'url' => U('Store/goods_list', array('store_id' => $this->store['store_id'], 'key' => 'collect_sum', 'sort' => $sort))),
				array('key' => 'is_recommend', 'name' => '人气', 'url' => U('Store/goods_list', array('store_id' => $this->store['store_id'], 'key' => 'is_recommend', 'sort' => $sort))),
				array('key' => 'shop_price', 'name' => '价格', 'url' => U('Store/goods_list', array('store_id' => $this->store['store_id'], 'key' => 'shop_price', 'sort' => $sort)))
		);

		$this->assign('cat_id', $cat_id);
		$this->assign('key', $key);
		$this->assign('sort', $sort);
		$this->assign('keywords', $keywords);

		$this->assign('link_arr', $link_arr);
		$this->assign('goods_list', $goods_list);
		$this->assign('cat_name', $cat_name);
		$this->assign('goods_list_total_count',$count);
		$this->assign('page_count',$page_count);
		if(IS_AJAX){
			$this->display('ajaxGoodsList');
		}else{
			$this->display();
		}
	}
	
	public function about(){
		$total_goods = M('goods')->where(array('store_id'=>$this->store['store_id'],'is_on_sale'=>1))->count();
		$this->assign('total_goods',$total_goods);

		#region 推广二维码
        if ($this->store['is_own_shop'] ==1){ //自营
            $ShareLink = U('Mobile/Store/index', array('store_id' => $this->store['store_id'], ), '', true);// 'first_leader' => $this->_seller['user_id']
        }else{//入驻店铺（实体店）
            $ShareLink = U('Mobile/Store/index', array('store_id' => $this->store['store_id'], 'first_leader' => $this->store['user_id']), '', true);
        }
        $logo = $this->store['store_logo'];
        if (!empty($logo)) $logo = 'http://' . $_SERVER['HTTP_HOST'] . $logo;
        //$share_url = U('Home/Index/qr_code',array('data'=>$ShareLink),'',true);
        $qrcode = new QrcodeController();
        $share_url = $qrcode->create_qrcode($ShareLink,6, 'L', $logo);
        $description = M('store_description')->where(array('store_id'=>$this->store['store_id'],'type'=>1,'status'=>1))->getField('content');
        if (!empty($description)){
            $this->assign('description',$description);
        }
        $this->assign('share_url',$share_url);
        #endregion

		$this->display();
	}

	public function advertising(){

	    $video = M('store_description')->where(array('store_id'=>$this->store['store_id'],'status'=>1,'type'=>2))->order('sort desc')->find();

        if (!empty($video)){
            $this->assign('video',$video);
        }

        $description = M('store_description')->where(array('store_id'=>$this->store['store_id'],'type'=>1,'status'=>1))->getField('content');
        if (!empty($description)){
            $this->assign('description',$description);
        }
	    $this->display();
    }
	
	public function store_goods_class(){
		$store_goods_class_list = M('store_goods_class')->where(array('store_id'=>$this->store['store_id']))->select();
		if($store_goods_class_list){
			$sub_cat = $main_cat = array();
			foreach ($store_goods_class_list as $val){
			    if ($val['parent_id'] == 0) {
                    $main_cat[] = $val;
                } else {
                    $sub_cat[$val['parent_id']][] = $val;
                }
			}
			$this->assign('main_cat',$main_cat);
			$this->assign('sub_cat',$sub_cat);
		}
		$this->display();
	}
    /**
     * 收藏店铺
     */
	public function collect_store()
    {
        $user_id = cookie('user_id');
        $store_id = $this->store['store_id'];
        $type = I('type', 0);
        try{
            if ($type == 1) {
                //删除收藏店铺
                M('store_collect')->where(array('user_id' => $user_id, 'store_id' => $store_id))->delete();
                $store_collect = M('store')->where(array('store_id' => $store_id))->getField('store_collect');
                if ($store_collect > 0){
                    M('store')->where(array('store_id' => $store_id))->setDec('store_collect');
                }
                $this->ajaxReturn(array('status' => 1, 'msg' => '成功取消收藏'),'json');
            }
            $count = M('store_collect')->where(array('user_id' => $user_id, 'store_id' => $store_id))->count();
            if ($count > 0) throw new Exception('您已收藏过该店铺');
            $data = array(
                'store_id' => $store_id,
                'user_id' => $user_id,
                'add_time' => time()
            );
            $data['user_name'] = M('users')->where(array('user_id'=>$user_id))->getField('nickname');
            $data['store_name'] = M('store')->where(array('store_id'=>$store_id))->getField('store_name');
            M('store_collect')->add($data);
            M('store')->where(array('store_id' => $store_id))->setInc('store_collect');
            $this->ajaxReturn(array('status' => 1, 'msg' => '收藏成功'),'json');
        }catch(Exception $e){
            $this->ajaxReturn(array('status' => 0, 'msg' => '您已收藏过该店铺', 'result' => array(),));
        }
    }

    //收藏店铺列表
    public function collect_list(){
        $userLogic = new UsersLogic();
        $data = $userLogic->get_store_collect($this->user_id);
        $this->assign('page', $data['show']);// 赋值分页输出
        $this->assign('store_list', $data['result']);
        if ($_GET['is_ajax']) {
            $this->display('ajax_collect_list');
            exit;
        }
        $this->display();
    }

    public function cancel_collect()
    {
        $collect_id = I('log_id');
        $user_id = $this->user_id;
        if (M('store_collect')->where("log_id = $collect_id and user_id = $user_id")->delete()) {
            $this->success("取消收藏成功", U('Store/collect_list'));
        } else {
            $this->error("取消收藏失败", U('Store/collect_list'));
        }
    }

    public function videos(){
        $list = M('store_description')->where(array('store_id'=>$this->store['store_id'],'status'=>1,'type'=>2))->order('sort desc')->select();
        $this->assign('list',$list);
        $this->display();
    }
}